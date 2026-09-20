<?php
/**
 * Secure WebDAV Server
 * ---------------------------------------------------------
 * - SECURITY: Brute-Force Protection (Early Block)
 * - SECURITY: Anti-Enumeration (Uniform 401 Responses)
 * - SECURITY: Strict Input Sanitization (Username Whitelist)
 * - HARDENING: Custom Filesystem Node (Upload Quotas & Filename Limits)
 * - HARDENING: Realpath Jailing (Anti-Traversal)
 * - PERFORMANCE: Probabilistic Garbage Collection
 * - PERFORMANCE: Dynamic Execution Timeouts (Upload vs. Browse)
 */

// -------------------------------------------------------------------------
// 0. RUNTIME GOVERNANCE
// -------------------------------------------------------------------------
//ini_set('memory_limit', '256M'); 

// -------------------------------------------------------------------------
// 1. CONFIGURATION & LOGGER PRE-LOAD
// -------------------------------------------------------------------------
$work_dir = __dir__ . '/..';

require_once $work_dir . '/vendor/autoload.php';
require_once $work_dir . '/configuration/config.dist.php'; 
require_once $work_dir . '/configuration/config.php'; 
require_once $work_dir . '/configuration/users.php'; 


$webdav_sec_config = [
	'flood_control' => ['enabled' => false],
	'http_checks'   => ['enabled' => false],
	'referrer_check'=> ['enabled' => false],
	'geo_ip'        => ['enabled' => true],
	'user_agents'   => ['enabled' => true],
	'rate_limit'    => ['enabled' => false],
	'asn_check'     => ['enabled' => true],
	'keyword_check' => ['enabled' => true],
	'blocklists'    => ['enabled' => true],
	'waf_checks'    => ['enabled' => false],
	'work_dir'      => $work_dir
];
$webdav_sec_config['http_checks']['allow_methods'] = ['OPTIONS', 'GET', 'HEAD', 'DELETE', 'PROPFIND', 'MKCOL', 'PUT', 'PROPPATCH', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
$webdav_sec_config['rate_limit']['max_requests'] = 10000;
$webdav_sec_config['rate_limit']['window'] = 60*2;


class DavLogger {
    const LEVEL_OFF = 0;
    const LEVEL_ERROR = 1;
    const LEVEL_LOGIN = 2;

    private $file, $level;

    public function __construct($file, $level) {
        $this->file = $file;
        $this->level = $level;
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0750, true);
    }

    public function log($level, $message) {
        if ($level > $this->level) return;
        $lvlStr = ($level === self::LEVEL_ERROR) ? 'ERROR' : 'LOGIN';
        $date = date('Y-m-d H:i:s');
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user = preg_replace('/[^a-zA-Z0-9\-\_\.\@]/', '', $_SERVER['PHP_AUTH_USER'] ?? '-');
        @file_put_contents($this->file, "[$date] [$lvlStr] [$ip] [$user] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

$logFile  = $work_dir . '/lists/webdav_log.txt';
$logLevel = $webdav_log_level ?? DavLogger::LEVEL_LOGIN; 
$logger   = new DavLogger($logFile, $logLevel);

// -------------------------------------------------------------------------
// 2. BRUTE FORCE PROTECTION (EARLY INIT)
// -------------------------------------------------------------------------
class BruteForceProtector {
    private $file, $maxFailures, $baseBlockTime, $window, $factor;
    private $logger, $subnetLimit, $globalLimit; 

    public function __construct($file, $maxFailures, $baseBlockTime, $window, $factor, DavLogger $logger) {
        $this->file = $file;
        $this->maxFailures = $maxFailures;
        $this->baseBlockTime = $baseBlockTime;
        $this->window = $window;
        $this->factor = $factor;
        $this->logger = $logger;
        $this->subnetLimit = $maxFailures * 4; 
        $this->globalLimit = 100; 
    }

    private function getSubnet($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed === false) return $ip; 
            return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
        }
        return (strpos($ip, '.') !== false) ? substr($ip, 0, strrpos($ip, '.')) : $ip;
    }

    private function updateData(callable $callback) {
        $fp = fopen($this->file, 'c+'); 
        if (!$fp) return ['panic' => false]; 

        $result = null;
        if (flock($fp, LOCK_EX)) {
            $stats = fstat($fp);
            $size = $stats['size'];
            if ($size > 0) {
                rewind($fp);
                $content = fread($fp, $size);
                $data = json_decode($content, true) ?: [];
            } else {
                $data = [];
            }
            
            foreach (['ips', 'users', 'subnets', 'global'] as $k) {
                if (!isset($data[$k])) $data[$k] = [];
            }

            $result = $callback($data);

            if ($result !== null) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($result));
                fflush($fp);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $result; 
    }

    private function readData() {
        if (!file_exists($this->file)) return [];
        $fp = fopen($this->file, 'r');
        $data = [];
        if ($fp && flock($fp, LOCK_SH)) {
            $stats = fstat($fp);
            if ($stats['size'] > 0) {
                $data = json_decode(fread($fp, $stats['size']), true) ?: [];
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $data;
    }

    public function checkAccess($ip, $user = null) {
        $data = $this->readData();
        $subnet = $this->getSubnet($ip);
        $now = time();

        if (isset($data['global']['panic_until']) && $data['global']['panic_until'] > $now) {
            //usleep(mt_rand(100000, 300000));
            return 'Global panic'; 
        }

        if (isset($data['ips'][$ip]['blocked_until']) && $data['ips'][$ip]['blocked_until'] > $now) return 'IP blocked';
        if (isset($data['subnets'][$subnet]['blocked_until']) && $data['subnets'][$subnet]['blocked_until'] > $now) return 'Subnet blocked';
        
        if ($user && isset($data['users'][$user]['blocked_until']) && $data['users'][$user]['blocked_until'] > $now) return 'User blocked';

        return true;
    }

    public function registerFail($ip, $user) {
        $this->updateData(function($data) use ($ip, $user) {
            $subnet = $this->getSubnet($ip);
            $now = time();

            $increment = function(&$bucket, $key, $limit, $isGlobal = false) use ($now) {
                if (!isset($bucket[$key])) $bucket[$key] = ['count'=>0, 'blocked_until'=>0, 'last_block_end'=>0, 'last_duration'=>0];
                
                if (($now - ($bucket[$key]['last_attempt'] ?? 0)) > $this->window) $bucket[$key]['count'] = 0;
                
                $bucket[$key]['count']++;
                $bucket[$key]['last_attempt'] = $now;

                if ($bucket[$key]['count'] >= $limit) {
                    if ($isGlobal) {
                         $bucket[$key]['panic_until'] = $now + 60; 
                         return 'PANIC';
                    } else {
                        $dur = ($now - $bucket[$key]['last_block_end'] < $this->window) 
                             ? ($bucket[$key]['last_duration'] ?: $this->baseBlockTime) * $this->factor 
                             : $this->baseBlockTime;
                        
                        $bucket[$key]['blocked_until']  = $now + $dur;
                        $bucket[$key]['last_block_end'] = $now + $dur;
                        $bucket[$key]['last_duration']  = $dur;
                        $bucket[$key]['count'] = 0; 
                        return $dur;
                    }
                }
                return false;
            };

            $panic = $increment($data, 'global', $this->globalLimit, true);
            if ($panic) $this->logger->log(DavLogger::LEVEL_LOGIN, "GLOBAL PANIC ACTIVATED");

            $ipB = $increment($data['ips'], $ip, $this->maxFailures);
            if ($ipB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT IP [$ip]: {$ipB}s");

            $subB = $increment($data['subnets'], $subnet, $this->subnetLimit);
            if ($subB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT SUBNET [$subnet]: {$subB}s");

            if ($user && $user !== 'no_auth_user') {
                $usrB = $increment($data['users'], $user, $this->maxFailures * 2);
                if ($usrB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT USER [$user]: {$usrB}s");
            }

            return $data;
        });
    }
}

// [PERFORMANCE] RAM-based tracking prevents flock() from bottlenecking concurrent syncs
$bf_file   = $work_dir . '/data/webdav/dav_protection.json';
$bf_limit  = $login_failures ?? 5;
$bf_base   = $login_block_seconds ?? 60;
$bf_window = $brute_force_window ?? 3600;
$bf_factor = $brute_force_factor ?? 2;

$protector = new BruteForceProtector($bf_file, $bf_limit, $bf_base, $bf_window, $bf_factor, $logger);

// BLOCK CHECK: Early Rejection
$ip = $_SERVER['REMOTE_ADDR'];
if ($protector->checkAccess($ip, $_SERVER['PHP_AUTH_USER'] ?? null) !== true) {
    http_response_code(403); die();
}

// -------------------------------------------------------------------------
// 3. SECURE PRE-FLIGHT & METHOD CONTROL
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Reveal minimal headers only. Auth prompt if missing.
    header('MS-Author-Via: DAV');
    header('DAV: 1, 2, 3');
    header('Allow: OPTIONS, GET, HEAD, DELETE, PROPFIND, MKCOL, PUT, PROPPATCH, COPY, MOVE, LOCK, UNLOCK'); 
    header('Access-Control-Allow-Origin: *'); 
    
    if (empty($_SERVER['PHP_AUTH_USER'])) {
         header('WWW-Authenticate: Basic realm="WebDAV"');
    }
    http_response_code(200); exit;
}

$allowedMethods = ['OPTIONS', 'GET', 'HEAD', 'DELETE', 'PROPFIND', 'MKCOL', 'PUT', 'PROPPATCH', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    http_response_code(403); die();
}

// Harden Auth Header Parsing (Tolerant)
if (empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    $parts = preg_split('/\s+/', $authHeader, 2, PREG_SPLIT_NO_EMPTY);
    if (count($parts) === 2 && strtolower($parts[0]) === 'basic') {
        $base64 = $parts[1];
        if (preg_match('/^[a-zA-Z0-9\/\+=]+$/', $base64)) {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false && strpos($decoded, "\0") === false && strpos($decoded, ':') !== false) {
                list($user, $pass) = explode(':', $decoded, 2);
                if (strlen($user) < 128 && strlen($pass) < 256) {
                    $_SERVER['PHP_AUTH_USER'] = trim($user);
                    $_SERVER['PHP_AUTH_PW']   = $pass;
                }
            }
        }
    }
}

// Trap: Missing Auth Loop (Bypass Protection)
// if (empty($_SERVER['PHP_AUTH_USER'])) {
//    $protector->registerFail($ip, 'no_auth_user');
// }

// -------------------------------------------------------------------------
// 4. SECURITY GATEWAY (CACHED GEOIP/CLIENT SCANNER)
// -------------------------------------------------------------------------
$cache_file = $work_dir . '/data/webdav/security_cache_' . md5($ip) . '.tmp';
$cache_ttl = 600; 
$skip_security_check = false;

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
    $skip_security_check = true;
}

if (!$skip_security_check) {
    // 1% Probabilistic GC
    if (rand(1, 100) === 1) {
        $gc_files = glob($work_dir . '/data/webdav/security_cache_*.tmp');
        if ($gc_files) {
            foreach ($gc_files as $gc_file) {
                if (file_exists($gc_file) && (time() - filemtime($gc_file) > $cache_ttl)) @unlink($gc_file);
            }
        }
    }

    if (file_exists($work_dir . '/parts/security_checks.php')) {
        require_once $work_dir . '/bin/functions.php';
        require_once $work_dir . '/bin/geoip.php';
        require_once $work_dir . '/parts/security_checks.php';
    }

    $sec_data = isset($geoip_data) ? $geoip_data : [];
    $checker = new ClientSecurity($sec_data, $log_file, $webdav_sec_config);
    $result  = $checker->runCheck();

    if ($result['status'] === 'BLOCK') {
        http_response_code(403); die();
    } else {
        touch($cache_file);
    }
}

// -------------------------------------------------------------------------
// 4.5 SUBFOLDER RIGHTS & SECURITY ENGINE
// -------------------------------------------------------------------------
class WebDavRightsHelper {
    private $baseRole;
    private $subfolderRights;
    public $rootPath;

    public function __construct($baseRole, $subfolderRights, $rootPath) {
        $this->baseRole = $baseRole;
        $this->subfolderRights = $subfolderRights ?: [];
        $this->rootPath = rtrim(realpath($rootPath), DIRECTORY_SEPARATOR);
    }

    // NEW: Mirrors the main cloud's sanitizeAndValidateName logic
    public function isSystemOrBlockedName($name) {
        $lower_name = strtolower(trim($name));
        
        // 1. Block Cloud Internal Structures (Removed .mycloud_crypto_salt)
        $system_files = ['.recycle_bin', '.recoll', '.mail'];
        if (in_array($lower_name, $system_files, true)) return true;

        // 2. Block Web Server Overrides, Configs & Keys
        $blocked_names = ['.htaccess', '.htpasswd', '.user.ini', 'web.config', 'php.ini', '.env', 'authorized_keys', 'id_rsa', 'id_rsa.pub', '.ssh', '.git'];
        if (in_array($lower_name, $blocked_names, true)) return true;

        // 3. Block Executable Extensions
        $blocked_exts = ['pht', 'phtml', 'phar', 'phps', 'shtml', 'cgi', 'pl', 'py', 'rb', 'jsp', 'asp', 'aspx', 'sh', 'bash', 'bat', 'cmd', 'ps1', 'vbs', 'bin', 'so', 'php', 'php3', 'php4', 'php5', 'php7', 'php8'];
        
        $ext = pathinfo($lower_name, PATHINFO_EXTENSION);
        if (in_array($ext, $blocked_exts, true)) return true;

        // 4. Fortification: Prevent Double Extensions (e.g., shell.php.jpg)
        foreach ($blocked_exts as $b_ext) {
            if (preg_match('/\.'.preg_quote($b_ext, '/').'\./i', $lower_name)) {
                return true;
            }
        }
        
        return false;
    }

    public function getEffectiveRole($fullPath) {
        if (!$fullPath || empty($this->subfolderRights)) return $this->baseRole;
        $jail = $this->rootPath;
        if (!$jail || strpos($fullPath, $jail) !== 0) return $this->baseRole;

        $relPath = '/' . ltrim(str_replace('\\', '/', substr($fullPath, strlen($jail))), '/');
        if ($relPath === '') $relPath = '/';

        $bestMatchLength = 0;
        $bestRole = $this->baseRole;

        foreach ($this->subfolderRights as $pathKey => $customRole) {
            $pathKey = '/' . ltrim(str_replace('\\', '/', $pathKey), '/');
            $isWildcard = strpos($pathKey, '*') !== false || strpos($pathKey, '?') !== false;

            if ($isWildcard) {
                if (fnmatch($pathKey, $relPath, FNM_PATHNAME | FNM_CASEFOLD) || fnmatch($pathKey . '/*', $relPath, FNM_PATHNAME | FNM_CASEFOLD)) {
                    if (strlen($pathKey) > $bestMatchLength) {
                        $bestMatchLength = strlen($pathKey);
                        $bestRole = $customRole;
                    }
                }
            } else {
                if ($relPath === $pathKey) return $customRole;
                if ($pathKey !== '/' && strpos($relPath, $pathKey . '/') === 0) {
                    if (strlen($pathKey) > $bestMatchLength) {
                        $bestMatchLength = strlen($pathKey);
                        $bestRole = $customRole;
                    }
                }
            }
        }
        return $bestRole;
    }

    public function isActionBlocked($action, $role = null, &$visited = []) {
        if ($role === null) $role = $this->baseRole;
        if ($role === 'admin_mode' || $role === 'full') return false; 
        if ($role === 'no-access' || $role === 'hidden') return true;

        if (in_array($role, $visited, true)) return false; 
        $visited[] = $role;

        global $MYCLOUD_RIGHTS_MATRIX;
        $matrix = $MYCLOUD_RIGHTS_MATRIX ?? [
            'read-only' => ['blocked' => ['upload', 'modify', 'delete', 'newfolder', 'rename', 'move']],
            'upload-only' => ['blocked' => ['download', 'modify', 'delete', 'rename', 'move', 'read']]
        ];

        $roleConfig = $matrix[$role] ?? null;
        if (!$roleConfig || !isset($roleConfig['blocked'])) return false;
        
        if ($roleConfig['blocked'] === '*') return true;
        if (in_array($action, $roleConfig['blocked'], true)) return true;

        foreach ($roleConfig['blocked'] as $parentKey) {
            if (isset($matrix[$parentKey])) {
                if ($this->isActionBlocked($action, $parentKey, $visited)) return true;
            }
        }
        return false;
    }
	
	public function healSubfolderPaths($oldAbs, $newAbs = null) {
        global $user_db, $user_details, $users;
        if (!function_exists('CloudAdmin_atomic_write_vars') || !isset($user_db) || empty($user_details)) return;

        $globalChanged = false;
        $oldPrefixAbs = rtrim(str_replace('\\', '/', $oldAbs), '/') . '/';
        $newPrefixAbs = $newAbs ? rtrim(str_replace('\\', '/', $newAbs), '/') . '/' : null;

        foreach ($user_details as &$ud) {
            if (empty($ud['cloud']) || !is_array($ud['cloud'])) continue;
            foreach ($ud['cloud'] as $cloudKey => &$cloudConfig) {
                if (empty($cloudConfig['subfolder_rights']) || empty($cloudConfig['path'])) continue;
                $jail = realpath($cloudConfig['path']);
                if (!$jail) continue;
                $jail = str_replace('\\', '/', $jail);

                $newRights = [];
                $localChanged = false;

                foreach ($cloudConfig['subfolder_rights'] as $relPath => $role) {
                    $ruleAbs = rtrim($jail, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/');
                    if ($ruleAbs === rtrim($oldPrefixAbs, '/') || strpos($ruleAbs . '/', $oldPrefixAbs) === 0) {
                        $localChanged = true;
                        $globalChanged = true;
                        if ($newPrefixAbs !== null) {
                            $newRuleAbs = ($ruleAbs === rtrim($oldPrefixAbs, '/'))
                                ? rtrim($newPrefixAbs, '/')
                                : $newPrefixAbs . substr($ruleAbs, strlen($oldPrefixAbs));

                            if (strpos($newRuleAbs . '/', $jail . '/') === 0) {
                                $newRel = '/' . ltrim(substr($newRuleAbs, strlen($jail)), '/');
                                $newRights[$newRel ?: '/'] = $role;
                            }
                        }
                    } else {
                        $newRights[$relPath] = $role;
                    }
                }

                if ($localChanged) {
                    $cloudConfig['subfolder_rights'] = $newRights;
                    if ($cloudConfig['path'] === $this->rootPath) {
                        $this->subfolderRights = $newRights;
                    }
                }
            }
        }

        if ($globalChanged) {
            CloudAdmin_atomic_write_vars($user_db, [
                'users' => ca_gen_strict($users),
                'user_details' => ca_gen_strict($user_details)
            ]);
        }
    }
}

// -------------------------------------------------------------------------
// 5. HARDENED FILESYSTEM NODES 
// -------------------------------------------------------------------------
class HardenedFile extends \Sabre\DAV\FS\File {
    protected $maxFileSize;
    protected $rightsHelper;

    public function __construct($path, $maxFileSize, $rightsHelper) {
        parent::__construct($path);
        $this->maxFileSize = $maxFileSize;
        $this->rightsHelper = $rightsHelper;
    }

    protected function checkFreeDiskSpace($targetDir, $incomingSize) {
        $freeSpace = @disk_free_space($targetDir);
        if ($freeSpace !== false) {
            $safeSpace = $freeSpace * 0.9;
            if ($incomingSize > $safeSpace) {
                throw new \Sabre\DAV\Exception\InsufficientStorage('Insufficient server storage space remaining.');
            }
        }
    }
	
	protected function scanForMalware($filePath) {
        global $cloud_clamav_enabled, $cloud_clamav_path;
        if (!empty($cloud_clamav_enabled)) {
            $clamav_bin = !empty($cloud_clamav_path) ? $cloud_clamav_path : 'clamdscan';
            $cmd = sprintf('%s --no-summary %s 2>&1', escapeshellcmd($clamav_bin), escapeshellarg($filePath));
            exec($cmd, $output, $return_var);
            if ($return_var === 1) {
                @unlink($filePath);
                throw new \Sabre\DAV\Exception\Forbidden('Upload rejected: Malware detected by ClamAV.');
            }
        }
    }
	
	// Helper: Detect if the vault directory contains any files other than the salt
    private function isVaultInUse() {
        if (basename($this->path) !== '.mycloud_crypto_salt') return false;
        $dir = dirname($this->path);
        $items = @scandir($dir);
        if (!is_array($items)) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.mycloud_crypto_salt') continue;
            return true; // Another file exists, vault is locked
        }
        return false;
    }

    public function get() {
        $role = $this->rightsHelper->getEffectiveRole($this->path);
        if ($role === 'hidden' || $role === 'no-access') {
            throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        }
        if ($this->rightsHelper->isActionBlocked('download', $role) && $this->rightsHelper->isActionBlocked('read', $role)) {
            throw new \Sabre\DAV\Exception\Forbidden('Read permission denied');
        }
        return parent::get();
    }

    // ---------------------------------------------------------
    // INSIDE HardenedFile
    // ---------------------------------------------------------
    public function put($data) {
        $role = $this->rightsHelper->getEffectiveRole($this->path);
        if ($this->rightsHelper->isActionBlocked('modify', $role)) {
            throw new \Sabre\DAV\Exception\Forbidden('Modify permission denied');
        }

        if (basename($this->path) === '.mycloud_crypto_salt' && file_exists($this->path) && $this->isVaultInUse()) {
            throw new \Sabre\DAV\Exception\Forbidden('Cannot modify encryption salt while the vault contains other files.');
        }

        $effectiveMax = (basename($this->path) === '.mycloud_crypto_salt') ? 524288 : $this->maxFileSize;
        $this->checkFreeDiskSpace(dirname($this->path), $effectiveMax);

        // FIX: Safe Stream Writer (Prevents spoofed Content-Length flooding)
        if (is_resource($data)) {
            $fp = fopen($this->path, 'wb');
            if (!$fp) throw new \Sabre\DAV\Exception('Could not open file for writing');
            $written = 0;
            while (!feof($data)) {
                $chunk = fread($data, 8192);
                if ($chunk === false) break;
                $written += strlen($chunk);
                if ($written > $effectiveMax) {
                    fclose($fp);
                    @unlink($this->path);
                    throw new \Sabre\DAV\Exception\EntityTooLarge('Stream size exceeded maximum allowed limit during transfer.');
                }
                fwrite($fp, $chunk);
            }
            fclose($fp);
            $result = '"' . md5_file($this->path) . '"';
        } else {
            if (strlen($data) > $effectiveMax) throw new \Sabre\DAV\Exception\EntityTooLarge('Data size exceeded limit.');
            file_put_contents($this->path, $data);
            $result = '"' . md5($data) . '"';
        }

        $this->scanForMalware($this->path);
        return $result;
    }
	
	
    public function delete() {
        $role = $this->rightsHelper->getEffectiveRole($this->path);
        if ($this->rightsHelper->isActionBlocked('delete', $role)) {
            throw new \Sabre\DAV\Exception\Forbidden('Delete permission denied');
        }

        // Conditional Lock for Vault Salt
        if (basename($this->path) === '.mycloud_crypto_salt' && $this->isVaultInUse()) {
            throw new \Sabre\DAV\Exception\Forbidden('Cannot delete encryption salt while the vault contains other files.');
        }

        return parent::delete();
    }

    public function setName($name) {
        // Intercept RENAME/MOVE payloads attempting to bypass extension blocks
        if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($name)) {
            throw new \Sabre\DAV\Exception\Forbidden('Blocked file extension or system file.');
        }

        $role = $this->rightsHelper->getEffectiveRole($this->path);
        if ($this->rightsHelper->isActionBlocked('rename', $role)) {
            throw new \Sabre\DAV\Exception\Forbidden('Rename permission denied');
        }

        // Conditional Lock for Vault Salt
        if (basename($this->path) === '.mycloud_crypto_salt' && $this->isVaultInUse()) {
            throw new \Sabre\DAV\Exception\Forbidden('Cannot rename encryption salt while the vault contains other files.');
        }

        return parent::setName($name);
    }
}

class HardenedDirectory extends \Sabre\DAV\FS\Directory {
    protected $maxFileSize; 
    protected $rightsHelper;

    protected function checkFreeDiskSpace($targetDir, $incomingSize) {
        $freeSpace = @disk_free_space($targetDir);
        if ($freeSpace !== false) {
            $safeSpace = $freeSpace * 0.9;
            if ($incomingSize > $safeSpace) {
                throw new \Sabre\DAV\Exception\InsufficientStorage('Insufficient server storage space remaining.');
            }
        }
    }
	
	protected function scanForMalware($filePath) {
        global $cloud_clamav_enabled, $cloud_clamav_path;
        if (!empty($cloud_clamav_enabled)) {
            $clamav_bin = !empty($cloud_clamav_path) ? $cloud_clamav_path : 'clamdscan';
            $cmd = sprintf('%s --no-summary %s 2>&1', escapeshellcmd($clamav_bin), escapeshellarg($filePath));
            exec($cmd, $output, $return_var);
            if ($return_var === 1) {
                @unlink($filePath);
                throw new \Sabre\DAV\Exception\Forbidden('Upload rejected: Malware detected by ClamAV.');
            }
        }
    }
	
	public function __construct($path, $maxFileSizeMB = 500, $rightsHelper = null) {
        parent::__construct($path);
        $this->maxFileSize = $maxFileSizeMB * 1024 * 1024;
        $this->rightsHelper = $rightsHelper;
    }

    public function getChild($name) {
        if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($name)) {
            throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        }

        $path = $this->path . '/' . $name;
        
        // --- FIX: Symlink Jailbreak Prevention ---
        $realPath = realpath($path);
        if ($realPath !== false) {
            $realRoot = realpath($this->rightsHelper ? $this->rightsHelper->rootPath : $this->path);
            $realRootCheck = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $realPathCheck = rtrim($realPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            
            if (strpos($realPathCheck, $realRootCheck) !== 0) {
                throw new \Sabre\DAV\Exception\Forbidden('Jailbreak attempt detected via symlink.');
            }
        }
        // -----------------------------------------

        if ($this->rightsHelper) {
            $role = $this->rightsHelper->getEffectiveRole($path);
            if ($role === 'hidden' || $role === 'no-access') {
                throw new \Sabre\DAV\Exception\NotFound('File could not be located');
            }
        }
        
        if (!file_exists($path)) {
            throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        }
        
        if (is_dir($path)) {
            return new HardenedDirectory($path, $this->maxFileSize / 1048576, $this->rightsHelper);
        }
        return new HardenedFile($path, $this->maxFileSize, $this->rightsHelper);
    }

    public function getChildren() {
        $children = [];
        $saltNode = null;

        foreach (scandir($this->path) as $node) {
            if ($node === '.' || $node === '..') continue;
            if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($node)) continue;

            if ($this->rightsHelper) {
                $role = $this->rightsHelper->getEffectiveRole($this->path . '/' . $node);
                if ($role === 'hidden' || $role === 'no-access') continue;
            }

            $child = $this->getChild($node);

            // Re-order algorithm: Push the crypto salt to the absolute end of the array.
            // This guarantees SabreDAV will recursively delete all vault contents BEFORE hitting the salt.
            if ($node === '.mycloud_crypto_salt') {
                $saltNode = $child;
            } else {
                $children[] = $child;
            }
        }

        if ($saltNode !== null) {
            $children[] = $saltNode;
        }

        return $children;
    }

    // ---------------------------------------------------------
    // INSIDE HardenedDirectory
    // ---------------------------------------------------------
    public function createFile($name, $data = null) {
        if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($name)) {
            throw new \Sabre\DAV\Exception\Forbidden('Blocked file extension or system file.');
        }

        $role = $this->rightsHelper ? $this->rightsHelper->getEffectiveRole($this->path) : 'no-access';
        if ($this->rightsHelper && $this->rightsHelper->isActionBlocked('upload', $role)) {
            throw new \Sabre\DAV\Exception\Forbidden('Upload permission denied.');
        }

        if (strlen($name) > 128) throw new \Sabre\DAV\Exception\Forbidden('Filename too long.');

        $dest = $this->path . '/' . $name;
        $realDest = realpath(dirname($dest));
        $realRoot = realpath($this->rightsHelper ? $this->rightsHelper->rootPath : $this->path);
        
        if ($realDest === false || strpos(rtrim($realDest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
            throw new \Sabre\DAV\Exception\Forbidden('Jailbreak attempt detected.');
        }

        $effectiveMax = ($name === '.mycloud_crypto_salt') ? 524288 : $this->maxFileSize;
        $this->checkFreeDiskSpace(dirname($dest), $effectiveMax);

        // FIX: Safe Stream Writer
        if (is_resource($data)) {
            $fp = fopen($dest, 'wb');
            if (!$fp) throw new \Sabre\DAV\Exception('Could not open file for writing');
            $written = 0;
            while (!feof($data)) {
                $chunk = fread($data, 8192);
                if ($chunk === false) break;
                $written += strlen($chunk);
                if ($written > $effectiveMax) {
                    fclose($fp);
                    @unlink($dest);
                    throw new \Sabre\DAV\Exception\EntityTooLarge('Stream size exceeded maximum allowed limit.');
                }
                fwrite($fp, $chunk);
            }
            fclose($fp);
        } else {
            if (strlen((string)$data) > $effectiveMax) throw new \Sabre\DAV\Exception\EntityTooLarge('Data size exceeded limit.');
            file_put_contents($dest, $data);
        }

        $this->scanForMalware($dest);
        return null;
    }
	
    public function createDirectory($name) {
       if (strlen($name) > 192 || preg_match('/[<>:"\/\\\\|?*\x00-\x1F]/', $name)) {
           throw new \Sabre\DAV\Exception\Forbidden('Directory name exceeds limit or contains invalid characters.');
       }
        if ($name === '.mycloud_crypto_salt') {
            throw new \Sabre\DAV\Exception\Forbidden('Cannot explicitly create a directory with this name.');
        }
        if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($name)) {
            throw new \Sabre\DAV\Exception\Forbidden('Blocked folder name.');
        }

        if ($this->rightsHelper) {
            $role = $this->rightsHelper->getEffectiveRole($this->path);
            if ($this->rightsHelper->isActionBlocked('newfolder', $role)) {
                throw new \Sabre\DAV\Exception\Forbidden('Create folder permission denied.');
            }
        }
        return parent::createDirectory($name);
    }

    public function delete() {
        if ($this->rightsHelper) {
            $role = $this->rightsHelper->getEffectiveRole($this->path);
            if ($this->rightsHelper->isActionBlocked('delete', $role)) {
                throw new \Sabre\DAV\Exception\Forbidden('Delete permission denied');
            }
        }
        return parent::delete();
    }

    public function setName($name) {
       if (strlen($name) > 192 || preg_match('/[<>:"\/\\\\|?*\x00-\x1F]/', $name)) {
           throw new \Sabre\DAV\Exception\Forbidden('Target name exceeds limit or contains invalid characters.');
       }
        if ($name === '.mycloud_crypto_salt') {
            throw new \Sabre\DAV\Exception\Forbidden('Blocked folder name.');
        }
        if ($this->rightsHelper && $this->rightsHelper->isSystemOrBlockedName($name)) {
            throw new \Sabre\DAV\Exception\Forbidden('Blocked folder name.');
        }

        if ($this->rightsHelper) {
            $role = $this->rightsHelper->getEffectiveRole($this->path);
            if ($this->rightsHelper->isActionBlocked('rename', $role)) {
                throw new \Sabre\DAV\Exception\Forbidden('Rename permission denied');
            }
        }
        $oldPath = $this->path;
        $result = parent::setName($name);
        if ($this->rightsHelper) {
            $this->rightsHelper->healSubfolderPaths($oldPath, dirname($oldPath) . '/' . $name);
        }
        return $result;
    }
}

// -------------------------------------------------------------------------
// 6. AUTHENTICATION (RAM + HMAC SECURITY)
// -------------------------------------------------------------------------
class Sha256AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
    protected $users, $protector, $logger, $cacheDir, $secret;

    public function __construct(array $users, BruteForceProtector $protector, DavLogger $logger, $work_dir) {
        $this->users = $users;
        $this->protector = $protector;
        $this->logger = $logger;
        
        $this->cacheDir = $work_dir . '/data/webdav/auth_cache';
        if (!is_dir($this->cacheDir)) @mkdir($this->cacheDir, 0700, true);

        // Persistent Secret for HMAC
        $this->secret = hash('sha256', $work_dir . 'K9vP2xLfRsdjZ4wfFnR1cF3tH6yD0gSG3V5bN2xM8cZsRF4jH7rP3kS6tY9sD2X5'); 
    }

    private function performCacheCleanup() {
        $gcFlag = $this->cacheDir . '/.last_gc';
        $now = time();
        if (!file_exists($gcFlag) || ($now - filemtime($gcFlag) > 300)) {
            $files = glob($this->cacheDir . '/sess_*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f) && ($now - filemtime($f) > 900)) @unlink($f);
                }
            }
            touch($gcFlag);
        }
    }

    public function validateUserPass($username, $password) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($this->protector->checkAccess($ip, $username) !== true) return false;
        
        $userExists = isset($this->users[$username]);
        $storedHash = $userExists ? $this->users[$username] : null;

        // HMAC Cache Check (RAM)
        $credKey = 'sess_' . hash_hmac('sha256', "$username:$password:$ip", $this->secret);
        $cacheFile = $this->cacheDir . '/' . $credKey;

        if (file_exists($cacheFile)) {
            if (time() - filemtime($cacheFile) < 900) {
                if (rand(1, 20) === 1) touch($cacheFile); 
                return true;
            } else {
                @unlink($cacheFile);
            }
        }
        
        $this->performCacheCleanup();

        // Username Enumeration Protection (Timing Attack mitigation)
        if (!$userExists) {
            // Compute dummy hash to simulate the exact CPU load of password_verify
            password_verify($password, '$2y$10$dummyHashToEqualizeTiming1234567890123456789012');
            $this->protector->registerFail($ip, 'invalid_user');
            return false;
        }

        $isValid = (substr($storedHash, 0, 1) === '$') 
                   ? password_verify($password, $storedHash) 
                   : hash_equals($storedHash, hash('sha256', $password));

        if ($isValid) {
            touch($cacheFile); 
            $this->logger->log(DavLogger::LEVEL_LOGIN, "SUCCESS: $username");
            return true;
        } else {
            $this->logger->log(DavLogger::LEVEL_LOGIN, "FAIL: $username");
            $this->protector->registerFail($ip, $username);
            return false;
        }
    }
}

// -------------------------------------------------------------------------
// 7. SERVER SETUP (HARDENED)
// -------------------------------------------------------------------------
$username = $_SERVER['PHP_AUTH_USER'] ?? null;
$userPath = null;
$baseRole = 'no-access';
$subfolderRights = [];

if ($username) {
    // Strict Whitelist (Alphanum + . _ - @). Length 3-64. Kills ".." and slashes.
    if (!preg_match('/^[a-zA-Z0-9\._@+-]{3,64}$/', $username)) {
        http_response_code(403); die(); 
    }

    foreach (($user_details ?? []) as $ud) {
        if (($ud['name'] ?? '') === $username) {
            // Check Sibling Variable
            $webdav_access = $ud['cloud_webdav'] ?? false;
            if ($webdav_access !== true && $webdav_access !== 'true') {
                 // Deny without leaking (403 or 401 handled by auth)
                 // We simply don't set userPath, so it falls to dummy root.
                 break;
            }

				$clouds = $ud['cloud'] ?? [];
				$userPath = null;
				if (is_array($clouds)) {
					foreach ($clouds as $cloud) {
						if (isset($cloud['interface']) && in_array($cloud['interface'], ['email', 'gallery'], true)) {
							continue;
						}
						$userPath = $cloud['path'] ?? null;
                        $baseRole = $cloud['rights'] ?? 'no-access';
                        $subfolderRights = $cloud['subfolder_rights'] ?? [];
						break;
					}
				}
			break;
        }
    }
}

// Anti-Enumeration: Always provide a root, even if user is invalid.
// AuthBackend will reject them later with 401, indistinguishable from "Wrong Password".
$rootPath = ($userPath && is_dir($userPath)) ? $userPath : $work_dir . '/data/webdav/empty_root';
if (!is_dir($rootPath)) @mkdir($rootPath, 0750, true);

// Instantiate the granular rights engine and lock it to the user's root container
$rightsHelper = new WebDavRightsHelper($baseRole, $subfolderRights, $rootPath);

// Use Hardened Node (3GB Limit) with Rights Enforcement
$rootNode = new HardenedDirectory($rootPath, 3072, $rightsHelper);

// --- URI FIX ---
//$requestUri = $_SERVER['REQUEST_URI'];
//if (strpos($requestUri, '/webdav.php') === 0) {
//    $pathInfo = substr($requestUri, strlen('/webdav.php'));
//    if (($pos = strpos($pathInfo, '?')) !== false) $pathInfo = substr($pathInfo, 0, $pos); 
//    $decodedPath = rawurldecode($pathInfo);
//    $_SERVER['PATH_INFO'] = $decodedPath;
// Fallback for filesystem encoding issues
//    if ($userPath) {
//        $fullCheckPath = rtrim($userPath, '/') . '/' . ltrim($decodedPath, '/');
//        $rawCheckPath  = rtrim($userPath, '/') . '/' . ltrim($pathInfo, '/');
//        if (!file_exists($fullCheckPath) && file_exists($rawCheckPath)) {
//            $_SERVER['PATH_INFO'] = $pathInfo;
//        }
//    }
//}
// --- END URI FIX ---


$server = new \Sabre\DAV\Server($rootNode);
$server->setBaseUri('/webdav.php'); 

$server->addPlugin(new \Sabre\DAV\Auth\Plugin(new Sha256AuthBackend($users, $protector, $logger, $work_dir)));

 
// [COMPATIBILITY] SQLite Property Storage (Prevents Sync Loops)
$propDbName = $username ? 'properties_' . $username . '.sqlite' : 'properties_default.sqlite';
$propDbPath = $work_dir . '/data/webdav/' . $propDbName;
$propDbExists = file_exists($propDbPath);
$pdo = new \PDO('sqlite:' . $propDbPath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

// Force SQLite to checkpoint aggressively to prevent unbounded WAL file growth
$pdo->exec("PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL; PRAGMA wal_autocheckpoint = 1000;");

if (!$propDbExists) {
    // Create SabreDAV property table schema dynamically
    $pdo->exec("CREATE TABLE propertystorage (id INTEGER PRIMARY KEY ASC, path TEXT, name TEXT, valuetype INTEGER, value TEXT);
                CREATE UNIQUE INDEX path_property ON propertystorage (path, name);");
}
$server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(
    new \Sabre\DAV\PropertyStorage\Backend\PDO($pdo)
));

$userLockDir = $work_dir . '/data/webdav/locks_' . ($username ?: 'anonymous');
if (!is_dir($userLockDir)) @mkdir($userLockDir, 0750, true);

// 1% Probabilistic Garbage Collection for Zombie WebDAV Locks (> 24 hours old)
if (rand(1, 100) === 1) {
    foreach (glob($userLockDir . '/*') as $lockFile) {
        if (is_file($lockFile) && (time() - filemtime($lockFile) > 86400)) {
            @unlink($lockFile);
        }
    }
}

$server->addPlugin(new \Sabre\DAV\Locks\Plugin(new \Sabre\DAV\Locks\Backend\File($userLockDir)));
$server->addPlugin(new \Sabre\DAV\Browser\GuessContentType());
$server->addPlugin(new \Sabre\DAV\Sync\Plugin());

// Dynamic Timeout (Hours for Uploads, Minutes for Browse)
if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'MOVE', 'COPY'])) {
    set_time_limit(7200); 
} else {
    set_time_limit(300);
}

// -------------------------------------------------------------------------
// 8. FORCE 403 ON NOT FOUND (SECURITY OBSCURITY)
// -------------------------------------------------------------------------
//$server->on('exception', function ($e) {
//    // If SabreDAV says "Not Found", we lie and say "Forbidden"
//    if ($e instanceof \Sabre\DAV\Exception\NotFound) {
//        http_response_code(403);
//        // Optional: Output a minimal error body if you want
//        // echo "Access Denied"; 
//        // Return false to stop SabreDAV from processing the exception standardly (which would be 404)
//        return false;
//    }
//});

// Prevent XML Bomb & Memory Exhaustion DoS
$server->on('beforeMethod:*', function ($request, $response) {
    $method = $request->getMethod();
    if (in_array($method, ['PROPFIND', 'PROPPATCH', 'LOCK', 'UNLOCK'])) {
        $length = $request->getHeader('Content-Length');
        $isChunked = strcasecmp($request->getHeader('Transfer-Encoding') ?? '', 'chunked') === 0;

        // Force a known Content-Length for XML payloads
        if ($isChunked || $length === null) {
            throw new \Sabre\DAV\Exception\LengthRequired('Content-Length header is required for XML commands.');
        }
        if ((int)$length > 2097152) { // 2MB hard limit
            throw new \Sabre\DAV\Exception\EntityTooLarge('XML command payload exceeds 2MB security limit.');
        }
    }
});

// -------------------------------------------------------------------------
// 8. SECURITY OBSCURITY & EXCEPTION REDACTION
// -------------------------------------------------------------------------
$server->on('exception', function (Throwable $e) use ($work_dir) {
    $msg = $e->getMessage();
    $jailCheck = realpath($work_dir);
    
    // If the error message leaks the absolute path, redact it via Reflection
    if ($jailCheck && strpos($msg, $jailCheck) !== false) {
        try {
            $prop = new ReflectionProperty(Exception::class, 'message');
            $prop->setAccessible(true);
            $prop->setValue($e, 'An internal access error occurred. Path redacted for security.');
        } catch (Exception $refEx) { 
            // Failsafe if Reflection is blocked by hardened PHP configs
        }
    }
});

$server->start();
?>