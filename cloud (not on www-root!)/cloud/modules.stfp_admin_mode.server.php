<?php
/**
 * ============================================================================
 * MODULE: Secure Server Administration Addon via SFTP
 * ============================================================================
 * Manages connections via SFTP. Class module for background actions and 
 * UI logic for foreground 
 */

if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}

// ==========================================
// 1. GLOBAL DEPENDENCIES
// ==========================================
$wd = $GLOBALS['work_dir'] ?? dirname(__DIR__);
if (file_exists($wd . '/vendor/autoload.php')) {
    require_once $wd . '/vendor/autoload.php';
}

// ==========================================
// 2. SHARED CLASSES
// ==========================================
class AdminExitException extends \Exception {
    public $data;
    public function __construct($data) { $this->data = $data; parent::__construct("Exit"); }
}

class SFTPDaemonProxy {
    private $ipcDir;
    private $target;
    private $pwd;
    private $key;
    private $username;

    public function __construct($key, $username, $target, $pwd) {
        global $work_dir;
        $this->target = $target;
        $this->pwd = $pwd;
        $this->key = $key;
        $this->username = $username;
        
        // Strict check: tied to exact browser session ID, user, and cloud key
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->ipcDir = ($work_dir ?? dirname(__DIR__)) . '/data/ssh_ipc/' . hash('sha256', $username . $key . session_id());
        
        if (!is_dir($this->ipcDir)) {
            @mkdir($this->ipcDir, 0700, true);
        }
        $this->ensureDaemon();
    }

    private function ensureDaemon() {
        $pidFile = $this->ipcDir . '/daemon.pid';
        clearstatcache(true, $pidFile);
        if (file_exists($pidFile)) {
            if (time() - @filemtime($pidFile) < 900) {
                return;
            }
        }

        array_map('unlink', glob($this->ipcDir . '/*.*')); // Clear stale queue

        $configFile = $this->ipcDir . '/config.json';
        file_put_contents($configFile, json_encode([
            'host' => $this->target['host'],
            'port' => $this->target['port'],
            'user' => $this->target['user'],
            'pwd'  => $this->pwd
        ]));
        chmod($configFile, 0600);

        $script = escapeshellarg(__FILE__);
        $ipcArg = escapeshellarg($this->ipcDir);
        
        $phpBin = (PHP_BINARY && strpos(strtolower(PHP_BINARY), 'php') !== false && strpos(strtolower(PHP_BINARY), 'fpm') === false) ? escapeshellarg(PHP_BINARY) : 'php';
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        // Detach STDIN and use pclose(popen) to completely drop the process handle from FPM
        $cmd = $isWin ? "start /B \"\" $phpBin $script sftp_daemon $ipcArg < NUL > NUL 2>&1" : "nohup $phpBin $script sftp_daemon $ipcArg </dev/null >/dev/null 2>&1 &";
        pclose(popen($cmd, 'r'));

        // Wait up to 3s for daemon to write its PID
        $timeout = 30;
        while (!file_exists($pidFile) && $timeout > 0) {
            clearstatcache(true, $pidFile);
            usleep(100000);
            $timeout--;
        }
    }

    public function executeTask($action, $post, $files, $pathConfig) {
        $reqId = uniqid('req_', true);
        $reqFile = $this->ipcDir . '/' . $reqId . '.json';
        $tmpReq = $this->ipcDir . '/' . $reqId . '.tmp';
        $resFile = $this->ipcDir . '/' . $reqId . '.res';

        file_put_contents($tmpReq, json_encode([
            'action' => $action,
            'post' => $post,
            'files' => $files,
            'key' => $this->key,
            'username' => $this->username,
            'pathConfig' => $pathConfig
        ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        rename($tmpReq, $reqFile);

        // Release the PHP session lock so the UI doesn't hang while waiting for SFTP
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);
        if ($sessionActive) session_write_close();

        $timeout = 9600; // Allow 4 mins (9600 * 25ms) for operations to queue and complete
        $pidFile = $this->ipcDir . '/daemon.pid';
        while ($timeout > 0) {
            clearstatcache(true, $resFile);
            if (file_exists($resFile)) break;
            
            usleep(5000);
            $timeout--;
            if ($timeout % 80 === 0) { // Check daemon health every 2 seconds
                clearstatcache(true, $pidFile);
                if (!file_exists($pidFile) || (time() - @filemtime($pidFile) > 5)) break; // Fast-fail
            }
        }

        // Re-open session if it was active so the parent script can safely write tokens
        if ($sessionActive) @session_start();

        if (file_exists($resFile)) {
            $res = json_decode(file_get_contents($resFile), true);
            @unlink($reqFile);
            @unlink($resFile);
            if (isset($res['error'])) return false; // Fail gracefully like phpseclib does
            return $res['result'] ?? true;
        }

        @unlink($reqFile);
        return false;
    }
}

class AdminModeServer {
    private $key;
    private $pathConfig;
    private $username;
    private $lockoutFile;
    private $uidMap = null;
    private $gidMap = null;
    private $lifetime = 60 * 60 * 12;
    private $sftp = null;

    public function __construct($key, $pathConfig, $username, $sftp = null) {
        global $work_dir;
        $this->key = $key;
        $this->pathConfig = $pathConfig;
        $this->username = $username;
        $this->lockoutFile = ($work_dir ?? __DIR__) . '/data/admin_lockout.json';
        $this->sftp = $sftp;
    }

    private function sendJsonAndExit($data) {
        if (defined('DAEMON_MODE')) {
            throw new AdminExitException($data);
        }
        while (ob_get_level() > 0) ob_end_clean();
        if (($data['status'] ?? '') === 'OK') {
            $data['admin_nonce'] = $_SESSION['admin_nonce'][$this->key] ?? '';
        }
        echo json_encode($data);
        exit;
    }

    private function checkLockout() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $tracker = $this->username . '_' . $ip;
        if (!file_exists($this->lockoutFile)) return;
        $data = json_decode(file_get_contents($this->lockoutFile), true) ?: [];
        if (isset($data[$tracker])) {
            $fails = $data[$tracker]['fails'];
            $lastFail = $data[$tracker]['last_fail'];
            if ($fails >= 5) {
                $penaltyMinutes = min(pow(2, $fails - 5) * 30, 525600);
                if (time() < ($lastFail + ($penaltyMinutes * 60))) {
                    $remaining = ceil((($lastFail + ($penaltyMinutes * 60)) - time()) / 60);
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => "Locked out for $remaining mins."]);
                }
            }
        }
    }

    private function recordFail() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $tracker = $this->username . '_' . $ip;
        $data = file_exists($this->lockoutFile) ? json_decode(file_get_contents($this->lockoutFile), true) : [];
        if (!isset($data[$tracker])) $data[$tracker] = ['fails' => 0];
        $data[$tracker]['fails']++;
        $data[$tracker]['last_fail'] = time();
        if (!is_dir(dirname($this->lockoutFile))) mkdir(dirname($this->lockoutFile), 0755, true);
        file_put_contents($this->lockoutFile, json_encode($data));
    }

    private function clearFail() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $tracker = $this->username . '_' . $ip;
        $data = file_exists($this->lockoutFile) ? json_decode(file_get_contents($this->lockoutFile), true) : [];
        if (isset($data[$tracker])) { unset($data[$tracker]); file_put_contents($this->lockoutFile, json_encode($data)); }
    }

    private function isAllowedPath($path, $sftp = null) {
        $blocked = ['/.ssh', '/passwd', '/shadow', '/gshadow', '/group', '/sudoers'];
        
        foreach ($blocked as $b) { if (strpos(strtolower($path), $b) !== false) return false; }
        
        if ($sftp) {
            $real = $sftp->realpath($path);
            if ($real) {
                foreach ($blocked as $b) {
                    if (strpos(strtolower($real), $b) !== false) return false;
                }
            }
        }
        return true;
    }

    private function getSftpTarget() {
        global $user_details;
        $pureConfig = '';
        if (!empty($user_details) && is_array($user_details)) {
            foreach ($user_details as $ud) {
                if (isset($ud['cloud'][$this->key]['path'])) { $pureConfig = $ud['cloud'][$this->key]['path']; break; }
            }
        }

        $cleanConfig = trim((string)$pureConfig, " \t\n\r\0\x0B/\\");
        if (preg_match('/^([^@]+)@([^:\/]+)(?::(\d+))?/', $cleanConfig, $m)) {
            return ['user' => $m[1], 'host' => $m[2], 'port' => !empty($m[3]) ? (int)$m[3] : 22, 'raw' => $cleanConfig];
        }
        return null;
    }

    private function getDirectSftpConnection($overridePwd = null) {
        if (!class_exists('phpseclib3\Net\SFTP')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'SFTP library missing. Use Composer to install phpseclib3.']);
        
        $pwd = $overridePwd ?? ($_SESSION['admin_ssh_pwd'][$this->key] ?? null);
        if (empty($pwd)) return null;

        $target = $this->getSftpTarget();
        if (!$target) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid config format in server path.']);

        $sftp = new \phpseclib3\Net\SFTP($target['host'], $target['port']);
        if (!$sftp->login($target['user'], $pwd)) return null;
        return $sftp;
    }

    private function checkAutoLogin() {
        global $cloud_admin_mode_cloudlist, $log_file;
        if (empty($cloud_admin_mode_cloudlist) || !is_readable($cloud_admin_mode_cloudlist)) return false;

        $target = $this->getSftpTarget();
        if (!$target) return false;

        $hashTarget = sprintf('%s@%s:%d', $target['user'], $target['host'], $target['port']);
        $hash1 = hash('sha256', $hashTarget);
        $hash2 = hash('sha256', $target['raw']);

        $lines = @file($cloud_admin_mode_cloudlist, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return false;

        foreach ($lines as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $k = trim($parts[0]);
                $p = trim($parts[1]);
                if ($k === $hash1 || $k === $hash2) {
                    if ($this->getDirectSftpConnection($p)) {
                        $_SESSION['admin_ssh_pwd'][$this->key] = $p;
                        $_SESSION['admin_auth_time'][$this->key] = time();
                        if (empty($_SESSION['admin_nonce'][$this->key])) $_SESSION['admin_nonce'][$this->key] = bin2hex(random_bytes(32));
                        if (function_exists('WriteLogLine') && !empty($log_file)) WriteLogLine($log_file, 'success', 'AdminMode: 🔑 Auto-Login successful via cloudlist for ' . $this->username);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function verifyAuth($action, $isWrite = false) {
        if (defined('DAEMON_MODE')) return; // Validated prior to dispatch

        if (empty($_SESSION['admin_ssh_pwd'][$this->key]) || empty($_SESSION['admin_auth_time'][$this->key])) {
            if (!$this->checkAutoLogin()) {
                if ($action === 'list') $this->sendJsonAndExit(['status' => 'OK', 'data' => [], 'cwd' => '/', 'role' => 'admin_mode']);
                $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'AUTH_REQUIRED', 'msg' => 'Auth required']);
            }
        } elseif (time() - $_SESSION['admin_auth_time'][$this->key] > $this->lifetime) {
            if (!$this->checkAutoLogin()) {
                unset($_SESSION['admin_ssh_pwd'][$this->key]);
                if ($action === 'list') $this->sendJsonAndExit(['status' => 'OK', 'data' => [], 'cwd' => '/', 'role' => 'admin_mode']);
                $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'AUTH_REQUIRED', 'msg' => 'Session expired']);
            }
        }

        $_SESSION['admin_auth_time'][$this->key] = time(); // Refresh on any valid activity
        if ($isWrite) {
            $clientNonce = $_POST['admin_nonce'] ?? '';
            $serverNonce = $_SESSION['admin_nonce'][$this->key] ?? 'none';
            if (!hash_equals($serverNonce, $clientNonce)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Security Error: Invalid Nonce.']);
            $_SESSION['admin_nonce'][$this->key] = bin2hex(random_bytes(32));
        }
    }

    public function handleRequest($action) {
        if ($action === 'admin_auth') { $this->actionAuth(); return; }
        if ($action === 'admin_check') { $this->actionCheck(); return; }
        if ($action === 'admin_heartbeat') { $this->actionCheck(); return; }
        if ($action === 'ssh_write') { $this->actionSshWrite(); return; }
        if ($action === 'ssh_resize') { $this->actionSshResize(); return; }         
        if ($action === 'ssh_stream') { $this->actionSshStream(); return; }         
        if ($action === 'admin_sync') { 
            $this->sftp = $this->getDirectSftpConnection();
            $this->actionAdminSync($this->sftp); 
            return; 
        }

        $isWrite = in_array($action, ['upload', 'edit-save', 'rename', 'delete', 'mkdir', 'mkfile', 'apply_permissions', 'copy', 'move']);
        $this->verifyAuth($action, $isWrite);

        $exec_enabled = function_exists('exec') && !in_array('exec', array_map('trim', explode(', ', strtolower(ini_get('disable_functions')))));

        if (!defined('DAEMON_MODE') && $exec_enabled) {
            $pwd = $_SESSION['admin_ssh_pwd'][$this->key] ?? null;
            $target = $this->getSftpTarget();
            if (!$target || empty($pwd)) {
                $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'AUTH_REQUIRED', 'msg' => 'SSH connection failed.']);
            }

            $proxy = new SFTPDaemonProxy($this->key, $this->username, $target, $pwd);
            $response = $proxy->executeTask($action, $_POST, $_FILES, $this->pathConfig);

            if ($response === false) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Daemon proxy failed or timed out.']);
            }

            if (isset($response['_session_data'])) {
                if ($action === 'get_download_token') {
                    $_SESSION['myCloud_dl_tokens'][$response['token']] = $response['_session_data'];
                }
                unset($response['_session_data']);
            }
            $this->sendJsonAndExit($response);
            return;
        }

        // --- Execute directly (Daemon or FPM fallback) ---
        if ($this->sftp === null) {
            $this->sftp = $this->getDirectSftpConnection();
        }
        if (!$this->sftp) {
            if ($action === 'list') $this->sendJsonAndExit(['status' => 'OK', 'data' => [], 'cwd' => '/', 'role' => 'admin_mode']);
            if ($action === 'check_paths') $this->sendJsonAndExit(['status' => 'OK', 'valid' => []]);
            $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'AUTH_REQUIRED', 'msg' => 'SSH connection failed. Check password/host.']);
        }

        switch ($action) {
            case 'list': $this->actionList($this->sftp); break;
            case 'edit-fetch': $this->actionEditFetch($this->sftp); break;
            case 'edit-save': $this->actionEditSave($this->sftp); break;
            case 'upload': $this->actionUpload($this->sftp); break;
            case 'mkfile': $this->actionMkfile($this->sftp); break;
            case 'mkdir': $this->actionMkdir($this->sftp); break;
            case 'rename': $this->actionRename($this->sftp); break;
            case 'delete': $this->actionDelete($this->sftp); break;
            case 'get_download_token': $this->actionGetDownloadToken($this->sftp); break;
            case 'check_paths': $this->actionCheckPaths($this->sftp); break;
            case 'get_users_groups': $this->actionGetUsersGroups($this->sftp); break;
            case 'apply_permissions': $this->actionApplyPermissions($this->sftp); break;
            case 'copy':
            case 'move': $this->actionCopyMove($this->sftp, $action); break;
            default: $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Not supported']);
        }
    }
    
    private function actionCheckPaths($sftp) {
        $paths = json_decode($_POST['paths'] ?? '[]', true);
        $valid = [];
        if (is_array($paths)) {
            foreach ($paths as $p) {
                if (!$this->isAllowedPath($p)) continue;
                $stat = $sftp->stat($p);
                if ($stat) $valid[$p] = ($stat['type'] === 2);
            }
        }
        $this->sendJsonAndExit(['status' => 'OK', 'valid' => $valid]);
    }
    
    private function actionGetUsersGroups($sftp) {
        $this->resolveUid($sftp, 0); // Forces cache load
        $this->resolveGid($sftp, 0);
        $users = array_values($this->uidMap ?? []);
        $groups = array_values($this->gidMap ?? []);
        sort($users); sort($groups);
        $this->sendJsonAndExit(['status' => 'OK', 'users' => $users, 'groups' => $groups]);
    }

    private function actionApplyPermissions($sftp) {
        $paths = json_decode($_POST['paths'] ?? '[]', true);
        $owner = $_POST['owner'] ?? '';
        $group = $_POST['group'] ?? '';
        $perms = $_POST['perms'] ?? '';
        $recursive = ($_POST['recursive'] ?? 'false') === 'true';

        if (!is_array($paths) || empty($paths)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'No paths provided.']);

        $uid = $owner !== '' ? $this->getUidFromName($sftp, $owner) : null;
        $gid = $group !== '' ? $this->getGidFromName($sftp, $group) : null;

        if ($recursive) @set_time_limit(300); // Allow time for deep directory iteration

        foreach ($paths as $path) {
            if (!$this->isAllowedPath($path)) continue;
            $this->applyPermsRecursive($sftp, $path, $uid, $gid, $perms, $recursive);
        }
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function getUidFromName($sftp, $name) {
        if (is_numeric($name)) return (int)$name;
        $this->resolveUid($sftp, 0);
        $rev = array_flip($this->uidMap ?? []);
        return isset($rev[$name]) ? (int)$rev[$name] : null;
    }

    private function getGidFromName($sftp, $name) {
        if (is_numeric($name)) return (int)$name;
        $this->resolveGid($sftp, 0);
        $rev = array_flip($this->gidMap ?? []);
        return isset($rev[$name]) ? (int)$rev[$name] : null;
    }

    private function applyPermsRecursive($sftp, $path, $uid, $gid, $perms, $recursive) {
        if ($perms !== '') @$sftp->chmod(octdec($perms), $path);
        if ($uid !== null) @$sftp->chown($path, $uid);
        if ($gid !== null) @$sftp->chgrp($path, $gid);

        if ($recursive) {
            $stat = $sftp->stat($path);
            if ($stat && $stat['type'] === 2) {
                $list = $sftp->rawlist($path);
                if ($list) {
                    foreach($list as $name => $itemStat) {
                        if ($name === '.' || $name === '..') continue;
                        $this->applyPermsRecursive($sftp, rtrim($path, '/') . '/' . $name, $uid, $gid, $perms, true);
                    }
                }
            }
        }
    }

    private function actionCheck() {
        if (empty($_SESSION['admin_ssh_pwd'][$this->key]) || empty($_SESSION['admin_auth_time'][$this->key])) {
            if (!$this->checkAutoLogin()) {
                $this->sendJsonAndExit(['status' => 'ERR']);
            }
        } elseif (time() - $_SESSION['admin_auth_time'][$this->key] > 900) { 
            if (!$this->checkAutoLogin()) {
                unset($_SESSION['admin_ssh_pwd'][$this->key]); 
                $this->sendJsonAndExit(['status' => 'ERR']); 
            }
        }
         $this->sendJsonAndExit(['status' => 'OK']);
     }

    private function actionAuth() {
        global $log_file;
        $this->checkLockout();

        $_SESSION['admin_ssh_pwd'][$this->key] = $_POST['password'] ?? '';
        if ($this->getDirectSftpConnection()) {
            $this->clearFail();
            $_SESSION['admin_auth_time'][$this->key] = time();
            $_SESSION['admin_nonce'][$this->key] = bin2hex(random_bytes(32));
            if (function_exists('WriteLogLine') && !empty($log_file)) {
                WriteLogLine($log_file, 'success', 'AdminMode: ✅ SFTP connection successful for user ' . $this->username . ' (Key: ' . $this->key . ')');
            }
            $this->sendJsonAndExit(['status' => 'OK']);
        } else {
            unset($_SESSION['admin_ssh_pwd'][$this->key]);
            $this->recordFail();
            if (function_exists('WriteLogLine') && !empty($log_file)) {
                WriteLogLine($log_file, 'error', 'AdminMode: ❌ SFTP connection failed for user ' . $this->username . ' (Key: ' . $this->key . ')');
            }
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Auth failed']);
        }
    }

    private function resolveUid($sftp, $uid) {
        if (!is_numeric($uid)) return $uid;
        if ($this->uidMap === null) {
            $this->uidMap = [];
            $passwd = @$sftp->get('/etc/passwd');
            if ($passwd) {
                foreach (explode("\n", $passwd) as $line) {
                    $parts = explode(':', $line);
                    if (count($parts) >= 3) $this->uidMap[trim($parts[2])] = trim($parts[0]);
                }
            }
        }
        return $this->uidMap[$uid] ?? $uid;
    }

    private function resolveGid($sftp, $gid) {
        if (!is_numeric($gid)) return $gid;
        if ($this->gidMap === null) {
            $this->gidMap = [];
            $group = @$sftp->get('/etc/group');
            if ($group) {
                foreach (explode("\n", $group) as $line) {
                    $parts = explode(':', $line);
                    if (count($parts) >= 3) $this->gidMap[trim($parts[2])] = trim($parts[0]);
                }
            }
        }
        return $this->gidMap[$gid] ?? $gid;
    }

    private function actionList($sftp) {
        $path = $_POST['path'] ?? '/';
        $maxDepth = isset($_POST['depth']) ? (int)$_POST['depth'] : 1;
        
        if (!$this->isAllowedPath($path, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        
        // Zip Listing
        if (preg_match('/(.*\.zip)(\/.*)?/i', $path, $matches)) {
            $zipFilePath = $matches[1];
            $internalPath = isset($matches[2]) ? ltrim($matches[2], '/') : '';

            $stat = $sftp->stat($zipFilePath);
            if ($stat && $stat['type'] !== 2) {
                $tempFile = sys_get_temp_dir() . '/myCloud_sftp_zip_' . uniqid() . '.zip';
                if ($sftp->get($zipFilePath, $tempFile)) {
                    $zip = new ZipArchive;
                    if ($zip->open($tempFile) === TRUE) {
                        $data = [];
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $statZip = $zip->statIndex($i);
                            $zipEntryName = $statZip['name'];
                            if ($internalPath === '' || strpos($zipEntryName, $internalPath) === 0) {
                                $relativeEntry = substr($zipEntryName, strlen($internalPath));
                                if ($relativeEntry === '' || $relativeEntry === false) continue;
                                $parts = explode('/', trim($relativeEntry, '/'));
                                $entryName = $parts[0];
                                if ($entryName === '') continue;
                                $fullVirtualPath = rtrim($matches[1], '/') . '/' . rtrim($internalPath, '/') . '/' . $entryName;
                                $fullVirtualPath = str_replace('//', '/', $fullVirtualPath);
                                $isDir = (count($parts) > 1 || substr($zipEntryName, -1) === '/');
                                if (!isset($data[$fullVirtualPath])) {
                                    $data[$fullVirtualPath] = [
                                        'name' => $fullVirtualPath, 
                                        'size' => $isDir ? 'DIR' : $statZip['size'], 
                                        'date' => date('Y-m-d H:i', $statZip['mtime']), 
                                        'owner' => '-', 
                                        'perms' => '0000', 
                                        'isZipContent' => true
                                    ];
                                }
                            }
                        }
                        $zip->close();
                        @unlink($tempFile);
                        $this->sendJsonAndExit(['status' => 'OK', 'data' => array_values($data), 'role' => 'admin_mode']);
                    }
                    @unlink($tempFile);
                }
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to read zip archive.']);
            }
        }

        
        $data = [];
        $stack = [[$path, 1]];
        $isFirst = true;

        // Directories strictly excluded from deep preloading to prevent system lockups
        $forbiddenPreload = ['proc', 'dev', 'tmp', 'mnt'];

        while (!empty($stack)) {
            [$currPath, $currLevel] = array_pop($stack);
            
            $rawList = $sftp->rawlist($currPath);
            if ($rawList === false) {
                if ($isFirst) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Read failed.']);
                continue;
            }
            $isFirst = false;

            foreach ($rawList as $name => $stat) {
                if ($name === '.' || $name === '..') continue;
                
                $itemPath = rtrim($currPath, '/') . '/' . $name;
                $isDir = ($stat['type'] === 2);
                $isLink = ($stat['type'] === 3);
                
                if ($isLink) {
                    $linkStat = @$sftp->stat($itemPath);
                    if ($linkStat && $linkStat['type'] === 2) {
                        $isDir = true;
                    }
                }
                
                $pBits = $stat['permissions'] ?? ($stat['mode'] ?? 0);
                $perms = $pBits ? sprintf('%04o', $pBits & 0777) : '0000';
                
                $u = $stat['owner'] ?? ($stat['uid'] ?? '0');
                $g = $stat['group'] ?? ($stat['gid'] ?? '0');
                $uStr = $this->resolveUid($sftp, $u);
                $gStr = $this->resolveGid($sftp, $g);

                if (is_numeric($uStr) && !empty($stat['longname']) && preg_match('/^[-dcbpsl][rwxst-]{9}\s+\S+\s+([^\s]+)\s+([^\s]+)/i', $stat['longname'], $m)) {
                    $uStr = $m[1]; $gStr = $m[2];
                }

                $entry = [
                    'name' => $itemPath, 
                    'size' => $isDir ? 'DIR' : $stat['size'], 
                    'date' => date('Y-m-d H:i', $stat['mtime'] ?? time()), 
                    'owner' => "$uStr:$gStr", 
                    'perms' => $perms
                ];
                
                if ($isLink) {
                    $entry['isLink'] = true;
                }
                
                $data[] = $entry;
                
                if ($isDir && $currLevel < $maxDepth) {
                    if (!in_array($name, $forbiddenPreload)) {
                        $stack[] = [$itemPath, $currLevel + 1];
                    }
                }
            }
        }
        $this->sendJsonAndExit(['status' => 'OK', 'data' => $data, 'role' => 'admin_mode']);
    }

    private function actionGetDownloadToken($sftp) {
        $path = $_POST['path'] ?? '';

         $isPreview = !empty($_POST['preview']);

         if (preg_match('/(.*\.zip)\/(.*)/i', $path, $matches)) {
             $zipFilePath = $matches[1];
             $internalPath = ltrim($matches[2], '/');
             if (!$this->isAllowedPath($zipFilePath, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);

             $token = bin2hex(random_bytes(20));
             $tempZip = sys_get_temp_dir() . '/myCloud_sftp_zip_' . $token . '.zip';
             if (!$sftp->get($zipFilePath, $tempZip)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to retrieve archive.']);

             $zip = new ZipArchive;
             $extractedFile = sys_get_temp_dir() . '/myCloud_sftp_ext_' . $token;
             if ($zip->open($tempZip) === TRUE) {
                 $stream = $zip->getStream($internalPath);
                 if ($stream) {
                     $out = fopen($extractedFile, 'wb');
                     stream_copy_to_stream($stream, $out);
                     fclose($out);
                     fclose($stream);
                 }
                 $zip->close();
             }
             @unlink($tempZip);

             if (!file_exists($extractedFile)) {
                 $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'File not found in archive.']);
             }

             $sessionData = [
                 'path' => $extractedFile,
                 'filename' => $_POST['filename'] ?? basename($internalPath),
                 'preview' => $isPreview,
                 'is_icon' => !empty($_POST['is_icon']),
                 'is_temp' => true,
                 'expires' => time() + 300
             ];
             $this->sendJsonAndExit(['status' => 'OK', 'token' => $token, '_session_data' => $sessionData]);
         }

         if (!$this->isAllowedPath($path, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);

        $stat = $sftp->stat($path);
        if (!$stat || $stat['type'] === 2) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'File not found.']);
        
        $token = bin2hex(random_bytes(20));
        $tempFile = sys_get_temp_dir() . '/myCloud_sftp_' . $token;
        if (!$sftp->get($path, $tempFile)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to retrieve file.']);
        
        $sessionData = [
            'path' => $tempFile, 'filename' => $_POST['filename'] ?? basename($path), 'preview' => $isPreview,
            'is_temp' => false, 'expires' => time() + 300
        ];
        $this->sendJsonAndExit(['status' => 'OK', 'token' => $token, '_session_data' => $sessionData]);
    }

    private function actionEditFetch($sftp) {
        $path = $_POST['path'] ?? '';
         if (preg_match('/(.*\.zip)\/(.*)/i', $path, $matches)) {
             $zipFilePath = $matches[1];
             $internalPath = ltrim($matches[2], '/');
             if (!$this->isAllowedPath($zipFilePath, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);

             $tempZip = sys_get_temp_dir() . '/myCloud_sftp_zip_' . uniqid() . '.zip';
             if (!$sftp->get($zipFilePath, $tempZip)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to retrieve archive.']);

             $zip = new ZipArchive;
             $content = false;
             if ($zip->open($tempZip) === TRUE) {
                 $stream = $zip->getStream($internalPath);
                 if ($stream) {
                     $content = stream_get_contents($stream);
                     fclose($stream);
                 }
                 $zip->close();
             }
             @unlink($tempZip);

             if ($content === false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'File read failed from zip.']);
             if (!mb_check_encoding($content, 'UTF-8')) $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
             $this->sendJsonAndExit(['status' => 'OK', 'content' => $content]);
         }

         if (!$this->isAllowedPath($path, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        $content = $sftp->get($path);
        if ($content === false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'File read failed.']);
        if (!mb_check_encoding($content, 'UTF-8')) $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        $this->sendJsonAndExit(['status' => 'OK', 'content' => $content]);
    }

    private function actionEditSave($sftp) {
        $path = $_POST['path'] ?? '';
        if (!$this->isAllowedPath($path, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        if (preg_match('/\.zip(\/|$)/i', $path)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        if (!$sftp->put($path, $_POST['content'] ?? '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Save failed.']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionUpload($sftp) {
        $dest = rtrim($_POST['dir'] ?? '/', '/') . '/' . basename($_FILES['file']['name']);
         if (preg_match('/\.zip(\/|$)/i', $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
         if (!$this->isAllowedPath($dest, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        if (!$sftp->put($dest, $_FILES['file']['tmp_name'], \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Upload failed.']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }


    private function actionMkdir($sftp) {
        $dest = rtrim($_POST['parent'] ?? '/', '/') . '/' . ($_POST['name'] ?? '');
         if (preg_match('/\.zip(\/|$)/i', $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
         if (!$this->isAllowedPath($dest, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        if (!$sftp->mkdir($dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Mkdir failed.']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionMkfile($sftp) {
        $dest = rtrim($_POST['parent'] ?? '/', '/') . '/' . ($_POST['name'] ?? '');
        if (preg_match('/\.zip(\/|$)/i', $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        if (!$this->isAllowedPath($dest, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        if (!$sftp->put($dest, '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Create file failed.']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionRename($sftp) {
        $src = $_POST['src'] ?? '';
        $dest = dirname($src) . '/' . $_POST['newName'];
         if (preg_match('/\.zip(\/|$)/i', $src) || preg_match('/\.zip(\/|$)/i', $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
         if (!$this->isAllowedPath($src, $sftp) || !$this->isAllowedPath($dest, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        if (!$sftp->rename($src, $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Rename failed.']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionDelete($sftp) {
        $src = $_POST['src'] ?? '';
         if (preg_match('/\.zip(\/|$)/i', $src)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
         if (!$this->isAllowedPath($src, $sftp)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        $stat = $sftp->stat($src);
        if ($stat && $stat['type'] === 2) {
            if (!$sftp->rmdir($src)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Folder delete failed.']);
        } else {
            if (!$sftp->delete($src)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Delete failed.']);
        }
        $this->sendJsonAndExit(['status' => 'OK']);
    }
    
    
    private function actionCopyMove($sftp, $mode) {
        $src = $_POST['src'] ?? '';
        $destDir = rtrim($_POST['dest'] ?? '/', '/');
        $preserve = ($_POST['preserve_rights'] ?? '0') === '1';

        if (!$this->isAllowedPath($src, $sftp) || (!$this->isAllowedPath($destDir, $sftp))) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Path restricted.']);
        }

        $dest = $destDir . '/' . basename($src);

        $stat = $sftp->stat($dest);
        if ($stat) {
            $res = $_POST['resolution'] ?? null;
            if (!$res) $this->sendJsonAndExit(['status' => 'CONFLICT', 'msg' => 'Exists', 'file' => basename($src)]);
            if ($res === 'keep_both') {
                $info = pathinfo($dest);
                $dir = $info['dirname'];
                $name = $info['filename'];
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $counter = 1;
                while ($sftp->stat($dir . '/' . $name . " ($counter)" . $ext)) { $counter++; }
                $dest = $dir . '/' . $name . " ($counter)" . $ext;
            } else if ($res === 'overwrite') {
                $sftp->exec("rm -rf " . escapeshellarg($dest));
            }
        }

        if ($mode === 'copy') {
            $sftp->exec("cp -r" . ($preserve ? "p" : "") . " " . escapeshellarg($src) . " " . escapeshellarg($dest));
        } else {
            if ($preserve) $sftp->exec("mv " . escapeshellarg($src) . " " . escapeshellarg($dest));
            else $sftp->exec("cp -r " . escapeshellarg($src) . " " . escapeshellarg($dest) . " && rm -rf " . escapeshellarg($src));
        }

        if ($sftp->stat($dest)) $this->sendJsonAndExit(['status' => 'OK']);
        else $this->sendJsonAndExit(['status' => 'ERR', 'msg' => ucfirst($mode) . ' failed. Check target permissions.']);
    }

    private function getSshBufferFile() {
        global $work_dir;
        $dir = ($work_dir ?? __DIR__) . '/data/ssh_ipc';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir . '/ssh_in_' . hash('sha256', $this->username . $this->key);
    }

    private function actionSshStream() {
        global $log_file;
        $this->verifyAuth('ssh_stream', false);
        
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: close');
        
        while (ob_get_level() > 0) @ob_end_clean();
        session_write_close();
        @set_time_limit(0);

        echo ":\n\n";
        @flush();

        try {
            $target = $this->getSftpTarget();
            $pwd = $_SESSION['admin_ssh_pwd'][$this->key] ?? null;
            if (!$target || !$pwd) throw new \Exception("Missing SSH credentials.");

            $ssh = new \phpseclib3\Net\SSH2($target['host'], $target['port']);
            if (!$ssh->login($target['user'], $pwd)) throw new \Exception("SSH Login Failed.");
            
            if (function_exists('WriteLogLine') && !empty($log_file)) {
                 WriteLogLine($log_file, 'info', 'AdminMode: 🖥️ SSH Terminal session started for user ' . $this->username . ' (Key: ' . $this->key . ')');
             }

            $cols = (int)($_POST['cols'] ?? 200);
            $rows = (int)($_POST['rows'] ?? 48);
            
            $ssh->enablePTY('xterm-256color');
//          $ssh->setWindowSize($cols, $rows);
            $ssh->setTimeout(1); 

            $inFile = $this->getSshBufferFile() . '.buf';
            $resizeFile = $this->getSshBufferFile() . '.resize';
            file_put_contents($inFile, ''); 
            
            $ssh->write("export TERM=xterm-256color; clear\n");
            $lastPing = time();

            while (!connection_aborted()) {
                // 1. Handle Window Resize
                clearstatcache(true, $resizeFile);
                if (file_exists($resizeFile)) {
                    $dim = explode('x', @file_get_contents($resizeFile));
                         if (count($dim) === 2) {
                             $cols = (int)$dim[0];
                             $rows = (int)$dim[1];
                             $ssh->setWindowSize($cols, $rows);
                             // Forced protocol nudge: Tell the remote TTY to update its boundaries
                             $ssh->write("stty cols $cols rows $rows\n");
                         }
                    @unlink($resizeFile);
                }

                // 2. Safe IPC Read with Rewind (Fixes the Ghost Cursor Bug)
                clearstatcache(true, $inFile);
                if (filesize($inFile) > 0) {
                    $fp = @fopen($inFile, 'c+');
                    if ($fp && flock($fp, LOCK_EX)) {
                        rewind($fp); // [CRITICAL FIX] Reset cursor to start before reading
                        $in = fread($fp, filesize($inFile));
                        if ($in !== '') {
                            $ssh->write($in);
                            ftruncate($fp, 0); 
                            rewind($fp);
                        }
                        flock($fp, LOCK_UN);
                        fclose($fp);
                    }
                }

                // 3. Read Stream Output
                 // [SMART POLLING] Peek at the socket/buffer before entering the blocking read()
                 $hasData = (function() {
                     if (!empty($this->channel_buffers)) {
                         foreach ($this->channel_buffers as $buf) {
                             if (is_string($buf) && strlen($buf) > 0) return true;
                         }
                     }
                     if (isset($this->fsock) && is_resource($this->fsock)) {
                         $r = [$this->fsock]; $w = $e = null;
                         return @stream_select($r, $w, $e, 0, 20000) > 0; // 20ms micro-timeout
                     }
                     return false;
                 })->call($ssh);

                 if ($hasData) {
                     $out = @$ssh->read('', \phpseclib3\Net\SSH2::READ_NEXT);
                     if (is_string($out) && $out !== '') {
                         echo "data: " . json_encode(base64_encode($out)) . "\n\n";
                         @flush();
                         
                     }
                }

                // 4. Heartbeat
                if (time() - $lastPing > 10) {
                    echo "data: \":ping\"\n\n"; 
                    @flush();
                    $lastPing = time();
                }
                
                if (!$ssh->isConnected()) break;
                usleep(20000); 
            }
        } catch (\Throwable $e) {
            echo "data: " . json_encode(base64_encode("\r\n\x1b[31;1m[Terminal Error]\x1b[0m " . $e->getMessage() . "\r\n")) . "\n\n";
            @flush();
        }
        @unlink($this->getSshBufferFile() . '.buf');
        exit;
    }

    private function actionSshWrite() {
        $this->verifyAuth('ssh_write', false);
        $inFile = $this->getSshBufferFile() . '.buf';
        $input = $_POST['data'] ?? '';
        if ($input !== '') {
            file_put_contents($inFile, base64_decode($input), FILE_APPEND | LOCK_EX);
        }
        $this->sendJsonAndExit(['status' => 'OK']);
    }
    
    private function actionSshResize() {
        @session_start(); session_write_close();
        $this->verifyAuth('ssh_resize', false);
        $resizeFile = $this->getSshBufferFile() . '.resize';
        $cols = (int)($_POST['cols'] ?? 200);
        $rows = (int)($_POST['rows'] ?? 48);
        file_put_contents($resizeFile, "{$cols}x{$rows}");
        $this->sendJsonAndExit(['status' => 'OK']);
    }
    
    private function actionAdminSync($sftpProxy) {
        $src = rtrim($_POST['src'] ?? '', '/');
        $dest = rtrim($_POST['dest'] ?? '', '/');
        $mirror = ($_POST['mirror'] ?? '0') === '1';
        $update = ($_POST['update'] ?? '1') === '1';
        $dryRun = ($_POST['dry_run'] ?? '0') === '1';

        // Rsync Sync requires a callback passed to exec(), which cannot be serialized over IPC.
        // Because it also blocks execution for minutes/hours, we spin up a direct connection for it,
        // leaving the persistent daemon free for other UI browsing requests.
        $sftp = $this->getDirectSftpConnection();
        if (!$sftp) {
            echo "data: " . json_encode(['status' => 'ERR', 'msg' => 'Direct SSH connection failed.']) . "\n\n";
            exit;
        }

        if (!$this->isAllowedPath($src, $sftp) || !$this->isAllowedPath($dest, $sftp)) {
            echo "data: " . json_encode(['status' => 'ERR', 'msg' => 'Path restricted.']) . "\n\n";
            exit;
        }

        // Requires SSH Stream setup to stream output (similar to actionSshStream / actionZip)
        @ini_set('output_buffering', 'off'); while (@ob_end_clean());
        header('Content-Type: text/event-stream'); header('Cache-Control: no-cache'); header('X-Accel-Buffering: no');
        
        $sendMsg = function($pct, $msg, $sts='RUNNING') { echo "data: " . json_encode(['percent'=>$pct, 'msg'=>$msg, 'status'=>$sts]) . "\n\n"; @flush(); };
        
        // Build rsync command
        // We assume src and dest are both local to the server for this command since it's SSH.
        $cmd = "rsync -avh --info=progress2 ";
        if ($update) $cmd .= "--update ";
        if ($mirror) $cmd .= "--delete ";
        if ($dryRun) $cmd .= "--dry-run ";
        
        // Note trailing slash on src to sync contents, not the folder itself
        $cmd .= escapeshellarg($src . '/') . " " . escapeshellarg($dest . '/');
        
        $sendMsg(10, 'Initializing sync...');
        
        // Execute via SSH and capture output
        $sftp->exec($cmd, function($str) use ($sendMsg) {
            // Parse rsync progress string (e.g., "  12.34M  45%  10.00MB/s    0:00:01")
            if (preg_match('/(\d+)%/', $str, $matches)) {
                $pct = (int)$matches[1];
                // Constrain between 10 and 99 during operation
                $pct = max(10, min(99, $pct));
                $sendMsg($pct, trim(preg_replace('/\s+/', ' ', $str)));
            }
        });
        
        $sendMsg(100, 'Sync Complete', 'OK');
        exit;
    }
}

// ==========================================
// BACKGROUND SFTP DAEMON (Persistent Session)
// ==========================================
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'sftp_daemon') {
    @ini_set('display_errors', 0);
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '-1');
    define('DAEMON_MODE', true);

    $ipcDir = $argv[2] ?? '';
    if (empty($ipcDir) || !is_dir($ipcDir)) exit;

    $configFile = $ipcDir . '/config.json';
    $pidFile = $ipcDir . '/daemon.pid';

    // Write PID immediately so the proxy knows we spawned successfully
    file_put_contents($pidFile, getmypid());

    if (!file_exists($configFile)) exit;
    $config = json_decode(file_get_contents($configFile), true);
    @unlink($configFile); // Immediately destroy credentials

    try {
        $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port']);
        if (!$sftp->login($config['user'], $config['pwd'])) {
            @unlink($pidFile);
            exit;
        }
    } catch (\Throwable $e) {
        @unlink($pidFile);
        exit;
    }

    $lastActive = time();
	$lastTouch = 0;

    while (true) {
        $now = time();
        if ($now - $lastTouch >= 2) {
            @touch($pidFile);
            $lastTouch = $now;
        }

        $reqFiles = glob($ipcDir . '/req_*.json');
        if (empty($reqFiles)) {
            if (time() - $lastActive > 600) { // 10 mins inactivity timeout
                // Race condition check: Ensure no client is actively writing a payload right now
                $tmpFiles = glob($ipcDir . '/req_*.tmp');
                if (empty($tmpFiles)) {
                    break; // Truly safe to exit
                }
            }
            usleep(25000); // 25ms sleep for near-instant response
            continue;
        }

        foreach ($reqFiles as $reqFile) {
            $lastActive = time();
            $req = json_decode(file_get_contents($reqFile), true);
            $resFile = str_replace('.json', '.res', $reqFile);
            $tmpRes = $resFile . '.tmp';

            try {
                if (!is_array($req)) throw new \Exception("Corrupt IPC payload");
                
                // Overwrite globals for local execution
                $_POST = $req['post'];
                $_FILES = $req['files'];
                
                $server = new AdminModeServer($req['key'], $req['pathConfig'], $req['username'], $sftp);
                try {
                    $server->handleRequest($req['action']);
                    throw new \Exception("Action did not return a response.");
                } catch (AdminExitException $exitEx) {
                    file_put_contents($tmpRes, json_encode(['result' => $exitEx->data], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
                }
            } catch (\Throwable $e) {
                file_put_contents($tmpRes, json_encode(['error' => $e->getMessage()], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            }
            @rename($tmpRes, $resFile);
            @unlink($reqFile);
        }
    }
    
    @unlink($pidFile);

    // Clean up remaining files and the IPC folder itself
    $leftovers = glob($ipcDir . '/*');
    if (is_array($leftovers)) {
        foreach ($leftovers as $file) {
            // Only delete stale files. This protects against an edge case where a brand new
            // proxy instance just spawned and started writing before we finish exiting.
            if (time() - @filemtime($file) > 10) {
                @unlink($file);
            }
        }
    }
    @rmdir($ipcDir);
    exit;
}

// ==========================================
// FRONTEND POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['myCloud_action'])) {
    // The main server.php script dynamically injects the class below when needed
}
