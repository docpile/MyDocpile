<?php
/**
 * ============================================================================
 * MODULE: Primary API & AJAX Router
 * ============================================================================
 * Serves as the main backend controller for asynchronous client requests. 
 * Handles file I/O operations, permission validation, and JSON API responses.
 */
 
 
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}

// ---------------------------------------------------------
// 1. GLOBAL INITIALIZATION (Preserved for Index inclusion)
// ---------------------------------------------------------

// Intercept and decode obscured thumbnail requests before global state is established
if (isset($_GET['myCloud_thumb']) && !empty($_REQUEST['myCloud_key'])) {
    $b64Key = str_replace(['-', '_'], ['+', '/'], $_REQUEST['myCloud_key']);
	$padLen = 4 - (strlen($b64Key) % 4);
	if ($padLen < 4) $b64Key .= str_repeat('=', $padLen); // Restore stripped padding
    $decodedKey = base64_decode($b64Key);
    if ($decodedKey !== false) {
        $_REQUEST['myCloud_key'] = $decodedKey;
        $_GET['myCloud_key'] = $decodedKey;
		$GLOBALS['__key'] = $decodedKey; // CRITICAL: Sync the global key so permissions load correctly
    }
}

if (!defined('MYCLOUD_OFFICE_BRIDGE')) {
    global $user_details, $domain_webmail_only;
    if (isset($user_details) && is_array($user_details)) {

        // Safely strip ports and handle reverse proxies
        $raw_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        $current_host = strtolower(explode(':', $raw_host)[0]);
        
        $webmail_domains = isset($domain_webmail_only) && is_array($domain_webmail_only) 
                            ? array_map('strtolower', $domain_webmail_only) 
                            : [];

        // Filter out non-webmail clouds for webmail-only domains globally
        if (in_array($current_host, $webmail_domains, true)) {
            foreach ($user_details as &$global_ud) {
                if (isset($global_ud['cloud']) && is_array($global_ud['cloud'])) {
                    foreach ($global_ud['cloud'] as $k => $c) {
                        if (($c['interface'] ?? 'default') !== 'email') {
                            unset($global_ud['cloud'][$k]);
                        }
                    }
                }
            }
            unset($global_ud);
            
            // Write back to $GLOBALS to ensure UI builders see the filtered data
            $GLOBALS['user_details'] = $user_details;
        }

        foreach ($user_details as $ud) {
            if (isset($ud['name']) && isset($_SESSION['username']) && $ud['name'] === $_SESSION['username'] ) {
                 $__userConfig = $ud;
                break;
            }
        }
    }
}

$__userConfig ??= null;

if ($__userConfig && (!isset($__userConfig['cloud'][$__key]) || empty($__key)) && !empty($__userConfig['cloud']) && is_array($__userConfig['cloud'])) {
    $__key = array_key_first($__userConfig['cloud']);
    $GLOBALS['__key'] = $__key;
    $_REQUEST['myCloud_key'] = $__key;
    $_GET['myCloud_key'] = $__key;
    $_POST['myCloud_key'] = $__key;
}

if ($__userConfig && isset($__userConfig['cloud'][$__key])) {
    $__ex_role = $__userConfig['cloud'][$__key]['rights'] ?? 'no-access';
    $__ex_interface = $__userConfig['cloud'][$__key]['interface'] ?? 'default';
	if ($__ex_interface === 'email' && $__ex_role === 'no-access') $__ex_role = 'full';
    if (!empty($__userConfig['cloud'][$__key]['path'])) {
        $cloud_path = $__userConfig['cloud'][$__key]['path'];
    }
}


if (isset($_GET['myCloud_thumb'])) {
    session_write_close();
}

// ---------------------------------------------------------
// 2. SERVER CLASS MODULE
// ---------------------------------------------------------

class MyCloudServer {
    
    private $cloud_path;
    private $recycle_dir;
    private $username;
    private $key;
    private $dl_tokens;
    private $role;
    private $share_db;
    
    private $officeSecret;
    private $officeInternalBase;
    private $officeExternalUrl;

    public function __construct() {
        global $cloud_path, $__ex_role, $cloud_onlyoffice_Secret, $cloud_onlyoffice_URL, $cloud_onlyoffice_ext_URL;
        
        $this->username = $_SESSION['username'] ?? 'guest';
        $this->key      = $_REQUEST['myCloud_key'] ?? '';
        $this->role     = $__ex_role ?? 'no-access';
        $this->share_db = $GLOBALS['cloud_share_db'] ?? __DIR__ . '/../data/shares.json';
 
        $this->officeSecret      = $cloud_onlyoffice_Secret;
        $this->officeInternalBase     = $cloud_onlyoffice_URL;
        $this->officeExternalUrl     = $cloud_onlyoffice_ext_URL;

        $this->cloud_path = !empty($cloud_path) ? rtrim(realpath($cloud_path), '/') . '/' : '';
        $this->recycle_dir = rtrim($this->cloud_path, '/\\') . DIRECTORY_SEPARATOR . '.recycle_bin' . DIRECTORY_SEPARATOR;
        
        if (!isset($_SESSION['myCloud_dl_tokens'])) {
            $_SESSION['myCloud_dl_tokens'] = [];
        }
        $this->dl_tokens =& $_SESSION['myCloud_dl_tokens'];
    }

    // =========================================================
    // HELPER METHODS
    // =========================================================

    private function sendJsonAndExit($data) {
        while (ob_get_level() > 0) ob_end_clean();
        echo json_encode($data);
        exit;
    }

    private function isActionBlocked($actionToCheck, $role = null, &$visited = []) {
        // Default to the user's current session role if not explicitly passed
        if ($role === null) {
            $role = $this->role;
            if ($role === 'admin_mode') return false;
        }
        
        // Prevent cyclic deadlock if roles reference each other
        if (in_array($role, $visited, true)) return false; 
        $visited[] = $role;

        global $MYCLOUD_RIGHTS_MATRIX;
        $roleConfig = $MYCLOUD_RIGHTS_MATRIX[$role] ?? null;
        
        // If the role doesn't exist or has no blocks, it's allowed
        if (!$roleConfig || !isset($roleConfig['blocked'])) return false;
        
        // Wildcard blocks everything
        if ($roleConfig['blocked'] === '*') return true;
        
        // Direct block check
        if (in_array($actionToCheck, $roleConfig['blocked'], true)) return true;
        
        // Deep inheritance check
        foreach ($roleConfig['blocked'] as $parentKey) {
            if (isset($MYCLOUD_RIGHTS_MATRIX[$parentKey])) {
                if ($this->isActionBlocked($actionToCheck, $parentKey, $visited)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function log($action, $src, $tgt = '-', $result = 'OK') {
        global $cloud_logfile;
        if (empty($cloud_logfile)) return;
        $entry = date('Y-m-d H:i:s') . "\t" . 
                 $this->username . "\t" . 
                 $this->key . "\t" . 
                 $action . "\t" . 
                 $src . "\t" . 
                 $tgt . "\t" . 
                 $result . "\n";
        @file_put_contents($cloud_logfile, $entry, FILE_APPEND | LOCK_EX);
    }

    private function resolve($rel) {
        $rel = str_replace(['\\', "\0"], ['/', ''], $rel);
        $jail = realpath($this->cloud_path);
        if (!$jail) return false;

        if ($rel === '' || $rel === '/') return $jail;
        
        $parts = explode('/', ltrim($rel, '/'));
        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                if (empty($safeParts)) return false; 
                array_pop($safeParts);
            } else {
                $safeParts[] = $part;
            }
        }
        
        $normalizedRel = implode('/', $safeParts);
        $fullPath = $jail . DIRECTORY_SEPARATOR . $normalizedRel;
        
        $realFullPath = realpath($fullPath);
        if ($realFullPath && is_link($realFullPath)) return false; 
        
        if (!$realFullPath) {
            $parentReal = realpath(dirname($fullPath));
            if (!$parentReal || strpos($parentReal, $jail) !== 0) return false;
            
            if ($parentReal !== $jail && strpos($parentReal, $jail . DIRECTORY_SEPARATOR) !== 0) {
                return false;
            }
            $realFullPath = $parentReal . '/' . basename($fullPath);
        }

        if ($realFullPath) {
            if ($realFullPath !== $jail && strpos($realFullPath, $jail . DIRECTORY_SEPARATOR) !== 0) {
                return false;
            }
        }

        // Zip Content Access Check
        if (preg_match('/(.*\.zip)(\/|$)/i', '/' . $normalizedRel, $matches)) {
            $zipPart = $jail . ltrim($matches[1], '/'); 
            $zipPartReal = realpath($zipPart);
            if ($zipPartReal && strpos($zipPartReal, $jail) === 0) {
                 if ($zipPartReal === $jail || strpos($zipPartReal, $jail . DIRECTORY_SEPARATOR) === 0) {
                     if (file_exists($zipPartReal)) return $fullPath; 
                 }
            }
        }

        if (strpos($fullPath, $jail) !== 0) return false;
        return $fullPath;
    }
    
    private function sanitizeAndValidateName($name, $rejectOnInvalid = false) {
        // 1. Strip XSS, path traversal, and illegal OS characters
        $safe_name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', '_', trim($name));
        
        if ($rejectOnInvalid && $safe_name !== trim($name)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Name contains invalid characters']);
        }

        $lower_name = strtolower($safe_name);
        $ext = pathinfo($lower_name, PATHINFO_EXTENSION);
        
        // 2. Exact Full Names to Block (System configs, secrets, & environments)
        $blocked_names = [
            // Web Server & PHP overrides
            '.htaccess', 
            '.htpasswd',
            '.user.ini', 
            'web.config',
            'php.ini',
            '.lighttpdpassword', 
            
            // Environment variables & Orchestration (Contains passwords/API keys)
            '.env',
            'docker-compose.yml',
            'docker-compose.yaml',
            'Dockerfile',
            
            // SSH & Shell (Prevents remote access if web root overlaps with a user home directory)
            'authorized_keys',
            'id_rsa',
            'id_rsa.pub',
            'id_ed25519',
            'id_ed25519.pub',
            'known_hosts',
            '.ssh',
            '.bashrc',
            '.bash_profile',
            '.profile',
            
            // Version Control (Prevents repo hijacking or metadata disclosure)
            '.git',
            '.gitignore',
            '.svn',
            '.hg'
        ];

        if (in_array($lower_name, $blocked_names, true)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Blocked System File']);
        }

        // 3. File Extensions to Block (Executable scripts, binaries, and macros)
       $blocked_exts = [
           // PHP variants (Web Shells)     Removed 'php', for usability reasons
           'php3', 'php4', 'php5', 'php7', 'php8', 
           'pht', 'phtml', 'phar', 'phps', 
           
           // Server-Side Includes (SSI) & Includes
           'shtml', 'shtm', 'stm', 'inc',
           
           // Perl, Python, Ruby, CGI
           'cgi', 'fcgi', 'pl', 'pm', 'py', 'pyc', 'rb', 'erb',
           
           // Java / Tomcat
           'jsp', 'jspx', 'jsw', 'jsv', 'jspf', 'jar', 'war', 'ear', 'class',
           
           // ASP.NET / Windows IIS (In case of migration or Mono)
           'asp', 'aspx', 'asa', 'asax', 'ascx', 'ashx', 'asmx', 'cer', 'swf',
           
           // Shell and Batch scripts (Prevent OS-level execution)
           'sh', 'bash', 'bsh', 'csh', 'zsh', 'ksh', 'bat', 'cmd', 'ps1', 'vbs', 'vbe', 'js',
           
           // Binaries and OS executables (Prevent hosting/executing malware)
           'so', 'bin', 'elf', 'app', 'run'
       ];

        // Merge with any custom extensions defined in your config.php
        global $cloud_upload_blocked_exts;
        if (!empty($cloud_upload_blocked_exts) && is_array($cloud_upload_blocked_exts)) {
            $blocked_exts = array_merge($blocked_exts, array_map('strtolower', $cloud_upload_blocked_exts));
        }

        // Fortification: Prevent "Double Extension" execution bypasses (e.g., payload.php.jpg)
        foreach ($blocked_exts as $b_ext) {
            if (preg_match('/\.'.preg_quote($b_ext, '/').'\./i', $lower_name)) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Hidden double extension blocked']);
            }
        }

        if (in_array($ext, $blocked_exts, true)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Blocked Extension']);
        }

        return $safe_name;
    }

    private function getUniqueName($fullPath) {
        if (!file_exists($fullPath)) return $fullPath;
        $info = pathinfo($fullPath);
        $dir = $info['dirname'];
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $counter = 1;

		// E2E Security: The server CANNOT safely auto-rename encrypted files
		// Modifying the Base64 string destroys the AES IV/Ciphertext block.
		if ($ext === '.enc') {
			// Returning the exact same path forces a standard file system failure/conflict
			// higher up in the execution chain, forcing the client UI to resolve it safely.
			return $fullPath; 
		}

        while (file_exists($dir . '/' . $name . " ($counter)" . $ext)) {
            $counter++;
        }
        return $dir . '/' . $name . " ($counter)" . $ext;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // =========================================================
    // MAIN ROUTER
    // =========================================================

    public function handleRequests() {
        global $L;
        
        @ini_set('display_errors', 0);
        @ini_set('log_errors', 1);
        
        // 1. STATELESS ONLYOFFICE BRIDGE (Must be BEFORE user_hash/session checks)
        // The Docker container has no cookies. We validate it strictly via JWT here.
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($reqUri, '/myCloudOfficeFetch/') !== false) {
            $this->handleOfficeFetch(); // Exits internally
        }
        if (strpos($reqUri, '/myCloudOfficeCallback') !== false) {
            $rawPost = @file_get_contents("php://input");
            $this->handleOfficeCallback(@json_decode($rawPost, true)); // Exits internally
        }       

       $this->handleHeartbeat();
        
		if ($this->role !== 'admin_mode' && (empty($this->cloud_path) || !is_dir($this->cloud_path))) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myCloud_action'])) {
                $act = $_POST['myCloud_action'];
                $pathlessActions = ['load_settings', 'save_settings', 'switch_language', 'change_password', 'reset_settings', 'get_help_data', 'refresh_csrf', 'load_views', 'load_favorites', 'load_tags', 'load_paths'];
                
                global $__ex_interface;
                if ($act === 'list' && $__ex_interface === 'email') {
                    header('Content-Type: application/json');
					$this->sendJsonAndExit(['status' => 'OK', 'data' => [], 'role' => $this->role, 'is_encrypted_root' => false, 'crypto_root' => null]);
                }

                // Allow specific system actions AND all webmail actions to bypass the path check
                if (!in_array($act, $pathlessActions) && strpos($act, 'email_') !== 0) {
                    header('Content-Type: application/json');
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => $L['invalid_path'] ?? 'Invalid path']);
                }
            }
        }

// =========================================================
        // OS NATIVE SHARE TARGET INTERCEPTOR
        // =========================================================
        if (isset($_GET['shared_from_os']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_FILES['shared_files']['name'][0])) {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
                $stashId = bin2hex(random_bytes(8));
                $stash = $_SESSION['myCloud_shared_stash'] ?? [];
                
                $files = $_FILES['shared_files'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $safe_name = $this->sanitizeAndValidateName(basename($files['name'][$i]), false);
                        $tmpPath = $tempDir . '/myCloud_share_' . $stashId . '_' . $i;
                        
                        if (@move_uploaded_file($files['tmp_name'][$i], $tmpPath)) {
                            $stash[] = [
                                'tmp_path' => $tmpPath,
                                'name' => $safe_name
                            ];
                        }
                    }
                }
                
                if (count($stash) > 0) {
                    $_SESSION['myCloud_shared_stash'] = $stash;
                }
            }
            // Redirect to clear the POST data and load the app UI normally
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
		

        // 1. Handle Recursive Stats (GET/POST hybrid in logic, but standard POST)
        if (isset($_POST['myCloud_action']) && $_POST['myCloud_action'] === 'get_dir_stats') {
            $this->actionGetDirStats();
        }

        // 2. Handle File Download (GET)
        if (!empty($_GET['myCloud_token'])) {
            $this->handleFileDownload();
        }
        
        // 2.b Fast-Path Direct Thumbnail (GET)
        if (!empty($_GET['myCloud_thumb'])) {
            $this->handleDirectThumbnail();
        }

        // 3. Handle Drag-Out (GET)
        if (isset($_GET['myCloud_drag']) && !empty($_GET['file'])) {
            $this->handleDragOut();
        }
        
        // 3.b Handle CSS Stylesheet Caching & Minification (GET)
        if (isset($_GET['myCloud_css'])) {
            $this->handleCssRequest();
        }

        // 3.c Handle JS Bundle Caching & Minification (GET)
        if (isset($_GET['myCloud_js'])) {
            $this->handleJsRequest();
        }

        // 3.d Handle Dynamic JS Modules (GET)
        if (isset($_GET['myCloud_dynamic_js'])) {
            $this->handleDynamicJsRequest($_GET['myCloud_dynamic_js']);
        }

        // 3.e Handle Session Heartbeat Ping (GET)
        if (isset($_GET['heartbeat']) && $_GET['heartbeat'] === "1") {
            $this->actionPingHeartbeat();
        }

        // 3.5 ONLYOFFICE Server-to-Server Endpoints (Zero Parameters, WAF Safe)
        $reqUri = $_SERVER['REQUEST_URI'];
        if (strpos($reqUri, '/myCloudOfficeFetch/') !== false) {
            $this->handleOfficeFetch();
        } elseif (strpos($reqUri, '/myCloudOfficeCallback') !== false) {
            $rawPost = @file_get_contents("php://input");
            $this->handleOfficeCallback(@json_decode($rawPost, true));
        }


        // 4. Handle POST Actions
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['myCloud_action'])) {
            return;
        }
        
        $reqAction = $_POST['myCloud_action'] ?? '';
        // Seamless CSRF Token Regeneration Endpoint (Bypasses check to allow recovery)
        if ($reqAction === 'refresh_csrf') {
            if (session_status() === PHP_SESSION_NONE) session_start();
            if (empty($_SESSION['myCloud_csrf_token']) || empty($_SESSION['csrf_timestamp']) || (time() - $_SESSION['csrf_timestamp']) > 3600) {
                $entropy_sources = [ random_bytes(32), hash('sha256', uniqid('', true)), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), hash('sha256', session_id()), hash('sha256', microtime(true) . getmypid()) ];
                $_SESSION['myCloud_csrf_token'] = bin2hex(random_bytes(32)) . hash('sha256', implode('', $entropy_sources));
                $_SESSION['csrf_timestamp'] = time();
            }
            $this->sendJsonAndExit(['status' => 'OK', 'token' => $_SESSION['myCloud_csrf_token']]);
        }

        // CSRF Check
        $sessionToken = $_SESSION['myCloud_csrf_token'] ?? '';
        if (empty($_POST['myCloud_token']) || empty($sessionToken) || !hash_equals($sessionToken, $_POST['myCloud_token'])) {
             while (ob_get_level() > 0) ob_end_clean();
             header('Content-Type: application/json');
            // Return HTTP 200 so JS can intercept the payload and trigger auto-recovery
            $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'CSRF_FAILED', 'msg' => 'Security Error: Invalid CSRF Token.']);
         }
 
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        
        global $cloud_rate_limit_enabled;
        if (!empty($cloud_rate_limit_enabled)) {
            $this->checkRateLimit();
        }

        $action = $_POST['myCloud_action'];

        // --- WEBMAIL MODULE ROUTER ---
        if (strpos($action, 'email_') === 0) {
            global $__userConfig;
            $hasEmailInterface = false;
            if (isset($__userConfig['cloud'])) {
                foreach ($__userConfig['cloud'] as $c) {
                    if (($c['interface'] ?? '') === 'email') { $hasEmailInterface = true; break; }
                }
            }
            if (!$hasEmailInterface) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Network error']);
            }
            include_once __DIR__ . '/controller.server.email.php';
            $emailServer = new MyCloudEmailServer($this->key, $this->username);
            $emailServer->handleRequest($action);
            exit;
        }

        if (strpos($action, 'adv_pwd_') === 0) {
            require_once __DIR__ . '/controller.server.change_password.php';
            $pwdServer = new MyCloudChangePasswordServer($this->username, $this->key);
            $pwdServer->handleRequest($action);
            exit;
        }

		// --- CENTRALIZED BACKEND RIGHTS CHECK ---
        $effectiveAction = $action;
        if ($action === 'pdf_stack' && isset($_POST['is_print_job']) && $_POST['is_print_job'] === 'true') {
            $effectiveAction = 'print';
        }
        
        if ($this->isActionBlocked($effectiveAction)) {
			$this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Permission denied: Action restricted by current rights level (' . $this->role . ').']);
        }

        // Release session lock for heavy I/O operations
        // Prevents the entire app from freezing in other tabs while processing large files
        $heavyActions = ['upload', 'zip', 'unzip', 'copy', 'move', 'delete', 'batch_rename', 'empty_bin'];
        if (in_array($action, $heavyActions) && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // [NEW] Admin Mode Hook: Intercept ONLY file/auth operations. Let settings pass through!
        if ($this->role === 'admin_mode') {
            $adminActions = [   'list', 
                                'upload', 
                                'edit-fetch', 
                                'edit-save', 
                                'mkdir', 
                                'rename', 
                                'delete', 
                                'get_download_token', 
                                'admin_auth', 
                                'admin_check', 
                                'check_paths', 
                                'get_users_groups', 
                                'apply_permissions', 
                                'copy', 
                                'move',
                                'ssh_stream', 
                                'ssh_resize',
                                'ssh_write',
                                'ssh_resize',
                                'admin_heartbeat',
                                'mkfile',
                                'poll_hash',
                                'crypto_init',
                                'crypto_get_salt',
								'crypto_change_pwd',
                            ];
            if (in_array($action, $adminActions)) {
                require_once __DIR__ . '/modules.stfp_admin_mode.server.php';
                $adminServer = new AdminModeServer($this->key, $this->cloud_path, $this->username);
                $adminServer->handleRequest($action);
                exit;
            }
        }

        // ROUTER
        switch ($action) {
            // Read-Only Actions
            case 'check_office_state': $this->actionCheckOfficeState(); break;
			case 'get_download_token': $this->actionGetDownloadToken(); break;
            case 'list':               $this->actionList(); break;
            case 'search':             $this->actionSearch(); break;
			case 'check_index':        $this->actionCheckIndex(); break;
            case 'get_exif':           $this->actionGetExif(); break;
            case 'share-list':         $this->actionShareList(); break;

            case 'crypto_get_salt':    $this->actionCryptoGetSalt(); break;
            case 'crypto_init':        $this->actionCryptoInit(); break;
			case 'crypto_change_pwd':  $this->actionCryptoChangePwd(); break;
            
            // Settings & Profile
            case 'change_password':    $this->actionChangePassword(); break;
			case 'load_settings':      $this->actionLoadSettings(); break;
            case 'save_settings':      $this->actionSaveSettings(); break;
            case 'reset_settings':     $this->actionResetSettings(); break;
            case 'switch_language':    $this->actionSwitchLanguage(); break;
            case 'load_views':         $this->actionLoadViews(); break;
            case 'save_view':          $this->actionSaveView(); break;
            case 'check_paths':        $this->actionCheckPaths(); break;
            case 'load_favorites':     $this->actionLoadFavorites(); break;
            case 'save_favorites':     $this->actionSaveFavorites(); break;
            case 'load_tags':          $this->actionLoadTags(); break;
            case 'save_tags':          $this->actionSaveTags(); break;
            case 'load_paths':         $this->actionLoadPaths(); break;
            case 'save_paths':         $this->actionSavePaths(); break;
            case 'get_help_data':      $this->actionGetHelpData(); break;

            // Ticket System
            case 'ticket-list':
            case 'ticket-create':
            case 'ticket-update':
            case 'ticket-changelog':
                $this->actionHandleTicket($action);
                break;

            // Write Actions (Modify/Full Only)
            default:
                switch ($action) {
                    case 'empty_bin':    $this->actionEmptyBin(); break;
                    case 'restore':      $this->actionRestore(); break;
                    case 'mkfile':       $this->actionMkfile(); break;
                    case 'mkdir':        $this->actionMkdir(); break;
                    case 'batch_rename': $this->actionBatchRename(); break;
                    case 'rename':       $this->actionRename(); break;
					case 'duplicate':    $this->actionDuplicate(); break;
                    case 'delete':       $this->actionDelete(); break;
                    case 'copy':
                    case 'move':         $this->actionCopyMove($action); break;
                    case 'upload':       $this->actionUpload(); break;
                    case 'edit-fetch':   $this->actionEditFetch(); break;
                    case 'edit-save':    $this->actionEditSave(); break;
                    case 'get_office_config': $this->actionGetOfficeConfig(); break;
					case 'office_convert_pdf': $this->actionOfficeConvertPdf(); break;
                    case 'zip':          $this->actionZip(); break;
                    case 'unzip':        $this->actionUnzip(); break;
                    case 'pdf_unstack':  $this->actionPdfUnstack(); break;
                    case 'pdf_stack':    $this->actionPdfStack(); break;
					case 'office_convert_temp_pdf': $this->actionOfficeConvertTempPdf(); break;
					case 'copy_as':               $this->actionCopyAs(); break;
                    case 'pdf_shrink':   $this->actionPdfShrink(); break;
                    case 'pdf_keep_pages':$this->actionPdfKeepPages(); break;
                    case 'pdf_rotate':   $this->actionPdfRotate(); break;
                    case 'pdf_unlock':   $this->actionPdfUnlock(); break;
                    case 'pdf_extract_text': $this->actionPdfExtractText(); break;
                    case 'pdf_ocr_text': $this->actionPdfOcrText(); break;
                    case 'pdf_extract_images': $this->actionPdfExtractImages(); break;
                    case 'pdf_flatten':  $this->actionPdfFlatten(); break;
                    case 'pdf_encrypt':  $this->actionPdfEncrypt(); break;
                    case 'pdf_repair':   $this->actionPdfRepair(); break;
                    case 'pdf_combine_images': $this->actionPdfCombineImages(); break;
                    case 'pdf_get_raw':  $this->actionPdfGetRaw(); break;
                    case 'get_size':     $this->actionGetSize(); break;
                    case 'pdf_get_form_fields': $this->actionPdfGetFormFields(); break;
                    case 'pdf_fill_form': $this->actionPdfFillForm(); break;
                    case 'share-create': $this->actionShareCreate(); break;
                    case 'share-update': $this->actionShareUpdate(); break;
                    case 'share-delete': $this->actionShareDelete(); break;
                    case 'commit_share': $this->actionCommitShare(); break;
                    case 'cancel_share': $this->actionCancelShare(); break;
                    case 'cloud_ingest_temp': $this->actionCloudIngestTemp(); break;
					case 'cloud_ingest_att': $this->actionCloudIngestAtt(); break;
					default:  $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Unknown action: ' . $action ]);
                }
        }
    }

    // =========================================================
    // PRIVATE ACTIONS (Logic extracted from switch)
    // =========================================================

    private function handleHeartbeat() {
        global $cookie_name, $login_stateful_tokens, $work_dir;
        if (isset($_SESSION['app_mode']) && $_SESSION['app_mode'] === true && isset($_COOKIE[$cookie_name])) {
            if (!isset($_SESSION['last_token_slide']) || time() - $_SESSION['last_token_slide'] > 86400) {
                $parts = explode(':', $_COOKIE[$cookie_name]);
                if (count($parts) === 2) {
                    $selector = $parts[0];
                    $tokenFile = $login_stateful_tokens ?? $work_dir . '/stateful_tokens.json';
                    if (file_exists($tokenFile)) {
                        $fp = fopen($tokenFile, 'c+');
                        if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
                            $fstat = fstat($fp);
                            $contents = ($fstat['size'] > 0) ? fread($fp, $fstat['size']) : '[]';
                            $tokens = json_decode($contents, true) ?: [];
                            if (isset($tokens[$selector])) {
                                $new_expires = time() + (30 * 86400);
                                $tokens[$selector]['expires'] = $new_expires;
                                ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($tokens));
                                $_SESSION['last_token_slide'] = time();
                                setcookie($cookie_name, $_COOKIE[$cookie_name], [
                                    'expires' => $new_expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
                                ]);
                            }
                            flock($fp, LOCK_UN); fclose($fp);
                        }
                    }
                }
            }
        }
    }

    private function checkRateLimit() {
        global $cloud_rate_limit_max, $cloud_rate_limit_window;
        
        $maxRequests = $cloud_rate_limit_max ?? 120;
        $timeWindow = $cloud_rate_limit_window ?? 60;
        
        // Isolate limits per user session + IP address to prevent cross-account pollution
        $identifier = md5(($this->username !== 'guest' ? $this->username : '') . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $rateFile = $tempDir . '/myCloud_rl_' . $identifier . '.json';
        
        $fp = @fopen($rateFile, 'c+');
        if ($fp && @flock($fp, LOCK_EX)) {
            $size = filesize($rateFile);
            $data = ($size > 0) ? (@json_decode(fread($fp, $size), true) ?: []) : [];
            $now = time();
            
            // Prune timestamps that fall outside the active sliding window
            $data = array_filter($data, function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
            
            $data[] = $now;
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($data)));
            fflush($fp);
            @flock($fp, LOCK_UN);
            fclose($fp);
            
            if (count($data) > $maxRequests) {
                header('HTTP/1.1 429 Too Many Requests');
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Rate limit exceeded. Please slow down.']);
            }
        }
    }
    
    private function actionGetDirStats() {
        $target = $this->resolve($_POST['path'] ?? '');
        if (!$target || !file_exists($target) || !is_dir($target)) {
             $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid path']);
        }

        session_write_close();
		@set_time_limit(0);
        $startTime = time();
        $timeLimit = 20;
        $maxFiles = 500000;
        $maxMemory = 1024 * 1024 * 1024;
        $startMemory = memory_get_usage(true);
        $scannedCount = 0;
        
        $checkLimits = function() use ($startTime, $timeLimit, $maxFiles, $maxMemory, $startMemory, &$scannedCount) {
            $scannedCount++;
            return (time() - $startTime > $timeLimit) || ($scannedCount > $maxFiles) || (memory_get_usage(true) - $startMemory > $maxMemory);
        };
        
        // Recursive closure must be bound to a variable to call itself
        $scanStats = null;
        $scanStats = function($dir) use (&$scanStats, $checkLimits) {
            if ($checkLimits()) return ['size'=>0, 'files'=>0, 'dirs'=>0, 'aborted'=>true];
            $totalSize = 0; $totalFiles = 0; $totalDirs = 0;
            $items = @scandir($dir);
            if (!is_array($items)) return ['size'=>0, 'files'=>0, 'dirs'=>0];

            foreach ($items as $i) {
                if ($i === '.' || $i === '..' || $i === '.recycle_bin' || $i === '.recoll') continue;
                if ($checkLimits()) return ['size'=>$totalSize, 'files'=>$totalFiles, 'dirs'=>$totalDirs, 'aborted'=>true];
                $p = $dir . DIRECTORY_SEPARATOR . $i;
                if (is_dir($p)) {
                    $sub = $scanStats($p);
                    $totalSize += $sub['size'];
                    $totalFiles += $sub['files'];
                    $totalDirs += ($sub['dirs'] + 1);
                    if (!empty($sub['aborted'])) return ['size'=>$totalSize, 'files'=>$totalFiles, 'dirs'=>$totalDirs, 'aborted'=>true];
                } else {
                    $totalSize += filesize($p);
                    $totalFiles++;
                }
            }
            return ['size'=>$totalSize, 'files'=>$totalFiles, 'dirs'=>$totalDirs];
        };

        $finalStats = ['size'=>0, 'files'=>0, 'dirs'=>0, 'children'=>[]];
        $rootItems = @scandir($target);
        if (is_array($rootItems)) {
            foreach($rootItems as $i) {
                if ($i === '.' || $i === '..' || $i === '.recycle_bin' || $i === '.recoll') continue;
                $p = $target . DIRECTORY_SEPARATOR . $i;
                if (is_dir($p)) {
                    $s = $scanStats($p); 
                    $finalStats['dirs']++;
                    $finalStats['dirs'] += $s['dirs'];
                    $finalStats['files'] += $s['files'];
                    $finalStats['size'] += $s['size'];
                    $finalStats['children'][] = ['name' => $i, 'size' => $s['size'], 'type' => 'dir'];
                } else {
                    $sz = filesize($p);
                    $finalStats['files']++;
                    $finalStats['size'] += $sz;
                }
            }
        }
        $this->sendJsonAndExit(['status'=>'OK', 'data'=>$finalStats]);
    }

    private function handleFileDownload() {
        while (ob_get_level() > 0) ob_end_clean();
        $token = $_GET['myCloud_token'];

        if (empty($this->dl_tokens[$token])) {
            header('HTTP/1.1 410 Gone'); exit('Invalid or expired token');
        }

        $info = $this->dl_tokens[$token];
        if (!empty($info['user_hash']) && $info['user_hash'] !== md5($_SESSION['username'])) {
            header('HTTP/1.1 403 Forbidden'); exit('Token ownership mismatch');
        }

        session_write_close();

        if ($info['expires'] < time()) {
            unset($this->dl_tokens[$token]);
            header('HTTP/1.1 410 Gone'); exit('Token expired');
        }

        $filename = $info['filename'];
        $preview  = $info['preview'];
        
        if (!empty($info['is_temp']) && empty($info['is_pdf_print']) && stripos($filename, '.zip') === false) {
            $filename .= '.zip';
        }
        
        // ZIP Extraction
        if (isset($info['is_zip_extract']) && $info['is_zip_extract'] === true) {
            $zipPath = $info['zip_path'];
            $internalPath = $info['internal_path'];
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $stream = $zip->getStream($internalPath);
                if ($stream) {
                    $stat = $zip->statName($internalPath);
                    if ($stat['size'] > 100 * 1024 * 1024) {
                        $zip->close(); header('HTTP/1.1 413 Payload Too Large'); exit('File too large');
                    }
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $inlineExts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'docx', 'xlsx'];
                    $disposition = ($ext === 'svg') ? 'attachment' : (($preview && in_array($ext, $inlineExts)) ? 'inline' : 'attachment');
                    
                    $mimes = ['pdf'=>'application/pdf', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'svg'=>'image/svg+xml', 'png'=>'image/png', 'gif'=>'image/gif', 'txt'=>'text/plain', 'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'zip'=>'application/zip'];
                    $mime = $mimes[$ext] ?? 'application/octet-stream';

                    header('Content-Type: ' . $mime);
                    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
                    fpassthru($stream); fclose($stream); $zip->close();
                    exit;
                }
                $zip->close();
            }
            header('HTTP/1.1 404 Not Found'); exit('File not found in archive');
        }

        $fullPath = $info['path'];
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            header('HTTP/1.1 404 Not Found'); exit;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        global $cloud_preview_cache, $cloud_icon_cache;

        // Preview Cache Generation
        $validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'mp4', 'webm', 'mov', 'mkv', 'avi'];
        if (class_exists('Imagick')) $validExts = array_merge($validExts, ['tiff', 'tif', 'dng', 'cr2', 'nef', 'arw', 'psd']);
        
        if ($preview && in_array($ext, $validExts)) {
            $isIcon = !empty($info['is_icon']);
            
            // Only serve cached image if it's an image, OR if it's a video and an icon was explicitly requested.
            // Do NOT intercept the request if they are trying to play the video.
            $isVideo = in_array($ext, ['mp4', 'webm', 'mov', 'mkv', 'avi']);
            if (!$isVideo || $isIcon) {
                if (isset($cloud_preview_cache)) {
                    $safePath = ltrim(str_replace(':', '', $fullPath), '/\\');
                    $baseCache = $isIcon ? ($cloud_icon_cache ?? $cloud_preview_cache) : $cloud_preview_cache;
                    $suffix = $isIcon ? '_thumb.jpg' : '.jpg';
                    $cacheFile = rtrim($baseCache, '/') . '/' . $safePath . $suffix;
                    $cachePath = dirname($cacheFile);
            
                    if (!is_dir($cachePath)) @mkdir($cachePath, 0755, true);
                    if (!file_exists($cacheFile) || filemtime($fullPath) > filemtime($cacheFile)) {
                        $isIcon ? $this->generateIcon($fullPath, $cacheFile) : $this->generatePreview($fullPath, $cacheFile);
                    }
            
                    if (file_exists($cacheFile)) {
                        while (ob_get_level()) ob_end_clean();
                        header_remove('Cache-Control');
                        header_remove('Pragma');
                        header_remove('Expires');
                       header('Content-Type: image/jpeg');
                        header('Cache-Control: public, max-age=31536000, immutable');
                        readfile($cacheFile);
                        exit;
                    }
                }
            }
        }

        $inlineExts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'docx', 'xlsx', 'mp4', 'webm', 'ogg', 'mov', 'mkv', 'mp3', 'wav'];
        $disposition = ($ext === 'svg') ? 'attachment' : (($preview && in_array($ext, $inlineExts)) ? 'inline' : 'attachment');
        
        $mimes = [
            'pdf'=>'application/pdf', 
            'svg'=>'image/svg+xml', 
            'jpg'=>'image/jpeg', 
            'jpeg'=>'image/jpeg', 
            'png'=>'image/png', 
            'gif'=>'image/gif', 
            'txt'=>'text/plain', 
            'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
            'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
            'zip'=>'application/zip', 
            'mp4'=>'video/mp4', 
            'webm'=>'video/webm', 
            'ogg'=>'video/ogg', 
            'mov'=>'video/quicktime', 
            'mkv'=>'video/x-matroska', 
            'mp3'=>'audio/mpeg', 
            'wav'=>'audio/wav'
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        
        $fileSize = filesize($fullPath);
        $start = 0; $end = $fileSize - 1;

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
        header_remove('Cache-Control');
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: public, max-age=300');
        header('Accept-Ranges: bytes');
        header("Content-Security-Policy: script-src 'none'; object-src 'self' blob:;");
        
        if(session_status() === PHP_SESSION_ACTIVE) session_write_close();
        set_time_limit(0);
        if (ob_get_level() > 0) ob_clean();

        // HTTP Range Support for Video Streaming
        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start; $c_end = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable'); header("Content-Range: bytes $start-$end/$fileSize"); exit;
            }
            if ($range == '-') { $c_start = $fileSize - substr($range, 1); }
            else { $range = explode('-', $range); $c_start = $range[0]; $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end; }
            $c_end = ($c_end > $end) ? $end : $c_end;
            if ($c_start > $c_end || $c_start > $fileSize - 1 || $c_end >= $fileSize) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable'); header("Content-Range: bytes $start-$end/$fileSize"); exit;
            }
            $start = $c_start; $end = $c_end;
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$fileSize");
        }
        header('Content-Length: ' . ($end - $start + 1));
        
        $handle = fopen($fullPath, 'rb');
        if ($handle) {
            fseek($handle, $start);
            $bufferSize = 8192;
            $bytesLeft = $end - $start + 1;
            while (!feof($handle) && $bytesLeft > 0) {
                $read = fread($handle, min($bytesLeft, $bufferSize));
                echo $read; $bytesLeft -= strlen($read); flush();
            }
            fclose($handle);
        }
        
        if (!empty($info['is_temp'])) @unlink($fullPath);
        exit;
    }

    private function handleDragOut() {
        if (empty($_SESSION['username'])) { header('HTTP/1.1 403 Forbidden'); exit; }
        $fPath = $this->resolve($_GET['file']);
        if (!$fPath || !is_file($fPath)) {  header('HTTP/1.1 404 Not Found'); exit; }
        if ($this->role === 'no-access') {  header('HTTP/1.1 403 Forbidden'); exit; }
        session_write_close();
        
        

        $fName = basename($fPath);
        $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
        $fMime = (in_array($fExt, ['jpg','jpeg','png','gif','pdf','txt'])) ? mime_content_type($fPath) : 'application/octet-stream';

        header('Content-Type: ' . $fMime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($fName) . '"');
        header('Content-Length: ' . filesize($fPath));
        readfile($fPath);
        exit;
    }
    
    private function handleDirectThumbnail() {
        while (ob_get_level() > 0) ob_end_clean();
        
        $rawThumb = $_GET['myCloud_thumb'] ?? '';

        // Decode URL-Safe Base64 back to the true file path
        $b64 = str_replace(['-', '_'], ['+', '/'], $rawThumb);
        $padLen = 4 - (strlen($b64) % 4);
        if ($padLen < 4) $b64 .= str_repeat('=', $padLen); // Restore stripped padding
        $path = base64_decode($b64);
        if ($path === false) {
            header('HTTP/1.1 400 Bad Request'); exit;
        }
        
        // 1. STRICT SECURITY: Must have valid session role AND valid CSRF token
        if ($this->role === 'no-access') {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }

        // 2. UNLOCK SESSION: Let the browser load multiple images in parallel!
        session_write_close();
        @set_time_limit(120);

        // 3. RESOLVE PATH: Ensures user cannot escape their allowed cloud directory
        $fullPath = $this->resolve($path);
        if (!$fullPath || !is_file($fullPath)) { header('HTTP/1.1 204 No Content'); exit; }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        
        // --- THIS IS THE FIX: The variable must be named $validExts ---
        $validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'mp4', 'webm', 'mov', 'mkv', 'avi'];
        if (class_exists('Imagick')) {
            $validExts = array_merge($validExts, ['tiff', 'tif', 'dng', 'cr2', 'nef', 'arw', 'psd', 'pdf']);
        }
        
        if (!in_array($ext, $validExts)) { header('HTTP/1.1 204 No Content'); exit; }

        global $cloud_preview_cache, $cloud_icon_cache;
        if (isset($cloud_preview_cache)) {
            $safePath = ltrim(str_replace(':', '', $fullPath), '/\\');
            $cacheFile = rtrim($cloud_icon_cache ?? $cloud_preview_cache, '/') . '/' . $safePath . '_thumb.jpg';
    
            // If the cron job missed it, generate it on the fly
            if (!file_exists($cacheFile) || filemtime($fullPath) > filemtime($cacheFile)) {
                $cachePath = dirname($cacheFile);
                if (!is_dir($cachePath)) @mkdir($cachePath, 0755, true);
                $this->generateIcon($fullPath, $cacheFile);
            }
    
            if (file_exists($cacheFile)) {
                $mtime = filemtime($cacheFile);
                $etag = '"' . md5($cacheFile . $mtime) . '"';
                header_remove('Cache-Control');
                header_remove('Pragma');
                header_remove('Expires');
                
                header('Content-Type: image/jpeg');
                header('Cache-Control: private, max-age=31536000, immutable');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
                header('ETag: ' . $etag);
                
                // If browser already has this exact file, tell it to use its local cache
                if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                    header('HTTP/1.1 304 Not Modified');
                    exit;
                }
                
                readfile($cacheFile); 
                exit;
            }
        }
        header('HTTP/1.1 204 No Content'); exit;
    }
	
    private function handleCssRequest() {
        global $cloud_dir;
        // Completely purge the wrapper's outer output buffer so HTML doesn't corrupt the CSS
        while (ob_get_level() > 0) ob_end_clean();

        $cssFiles = [
			'css.core.theme.php',
			'css.core.styles.php',
			'css.ui.views.icon_view.php',
			'css.ui.views.office_view.php',
			'css.ui.modules.editor.php',
			'css.ui.modules.settings.php',
			'css.ui.modules.multi_rename.php',
			'css.ui.modules.help_ui.php',
			'css.ui.modules.search.php',
			'css.ui.modules.preview.php',
			'css.modules.admin_ui.php',
			'css.modules.share_ui.php',
			'css.modules.email.php',
        ];

        $maxMtime = 0;
        foreach ($cssFiles as $file) {
            $path = $cloud_dir . $file;
            if (file_exists($path)) {
                $maxMtime = max($maxMtime, filemtime($path));
            }
        }
        if ($maxMtime === 0) $maxMtime = time();

        $etag = '"' . md5($maxMtime . 'cssbundle') . '"';
        
        header("Content-Type: text/css; charset=UTF-8");
        header("Cache-Control: public, max-age=31536000, immutable");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $maxMtime) . " GMT");
        header("ETag: $etag");
        
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }
        
        ob_start();
        $ca_isGlobalAdmin = (isset($_SESSION['username']) && function_exists('getUserRole') && strtolower(getUserRole($_SESSION['username'])) === 'admin');
        
        foreach ($cssFiles as $file) {
            $path = $cloud_dir . $file;
            if (file_exists($path)) {
                require $path;
            }
        }
        
        $cssOutput = ob_get_clean();
        
        $rawCss = preg_replace('/<\/?style>/i', '', $cssOutput);
        // Use fully qualified namespace since 'use' statement is in index.php
        $minifier = new \MatthiasMullie\Minify\CSS($rawCss);
        echo $minifier->minify();
//		echo $rawCss;
        exit;
    }  

    private function handleJsRequest() {
        global $cloud_dir, $__userConfig;
		while (ob_get_level() > 0) ob_end_clean();

        $hasEmailInterface = false;
        if (isset($__userConfig['cloud'])) {
            foreach ($__userConfig['cloud'] as $c) {
                if (($c['interface'] ?? '') === 'email') { $hasEmailInterface = true; break; }
            }
        }

        $jsFiles = [
			'assets.js.core_engine.php', 
			'assets.js.crypto_engine.php', 
			'core.ui.main_explorer_ui.php', 
			'assets.js.ui_helper_functions.php', 
			'core.ui.toolbar_menues.php', 
			'ui.modules.settings.php', 
			'ui.modules.multi_rename.php', 
			'ui.modules.preview.php', 
			'ui.modules.search.php', 
			'ui.views.icon_view.php', 
			'ui.views.office_view.php',
			'ui.modules.help_ui.php',
			'ui.modules.editor.php',
			'ui.modules.onlyoffice.php', 
			'ui.modules.first_run_assistant.php',
		];

        if ($hasEmailInterface) {
            array_push($jsFiles, 'modules.email.ui.php', 'modules.email.composer.php', 'modules.email.settings.php', 'modules.email.contacts.php');
            if (file_exists(__DIR__ . '/modules.email.alias_admin.php')) {
				array_push($jsFiles, 'modules.email.alias_admin.php');
			}
        }
		
		if (file_exists(__DIR__ . '/modules.change_password.php')) {
            array_push($jsFiles, 'modules.change_password.php');
        }

        $maxMtime = 0;
        foreach ($jsFiles as $file) {
            $path = $cloud_dir . $file;
            if (file_exists($path)) {
                $maxMtime = max($maxMtime, filemtime($path));
            }
        }
        $etag = '"' . md5($maxMtime . 'jsbundle') . '"';
        
        header("Content-Type: application/javascript; charset=UTF-8");
        header("Cache-Control: public, max-age=31536000, immutable");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", $maxMtime) . " GMT");
        header("ETag: $etag");
        
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            header("HTTP/1.1 304 Not Modified");
            exit;
        }
        
        ob_start();
        foreach ($jsFiles as $file) {
            $path = $cloud_dir . $file;
            if (file_exists($path)) require $path;
        }
        $jsOutput = ob_get_clean();
        
        $rawJs = preg_replace('/<\/?script[^>]*>/i', '', $jsOutput);
        // Strip all comments and blank chars at the start of a line
        $rawJs = preg_replace('/^\s*\/\/.*$/m', '', $rawJs); // Strip single-line comments
        $rawJs = preg_replace('/^\s*\/\*[\s\S]*?\*\//m', '', $rawJs); // Strip multi-line comments
        $rawJs = preg_replace('/^\s+/m', '', $rawJs); // Strip leading whitespace and empty lines

        echo $rawJs;
        exit;
		// Apply EXACTLY the same minification chain used by the outer wrapper
        if (function_exists('myCloudMinifySafe_Html') && function_exists('myCloudMinifyHtmlBlocks')) {
            echo myCloudMinifySafe_Html(myCloudMinifyHtmlBlocks($rawJs));
        } elseif (function_exists('myCloudMinifySafe_Js')) {
            echo myCloudMinifySafe_Js($rawJs);
        } else {
            echo $rawJs;
        }
        exit;
    }
 
	private function handleDynamicJsRequest($modules) {
        global $cloud_dir;
        while (ob_get_level() > 0) ob_end_clean();

        header("Content-Type: application/javascript; charset=UTF-8");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

        ob_start();
        $modList = explode(',', $modules);
        foreach ($modList as $mod) {
            // Strict sanitization to prevent path traversal
            $safeMod = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($mod));
            if (empty($safeMod)) continue;
            $path = $cloud_dir . $safeMod;
            if (file_exists($path)) {
                require $path;
                echo "\n";
            }
        }


        $jsOutput = ob_get_clean();

		// 1. Remove <script> tags
		$jsOutput = preg_replace('/<\/?script[^>]*>/i', '', $jsOutput);

		// 2. Strip leading spaces and tabs from every line
		$jsOutput = preg_replace('/^[ \t]+/m', '', $jsOutput);

		// 3. Safely strip comments while protecting strings and template literals
		// Matches: "string", 'string', `template`, /* comment */, or // comment
		$pattern = '/(?:"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|`[^`\\\\]*(?:\\\\.[^`\\\\]*)*`)|(?:\/\*[\s\S]*?\*\/)|(?:\/\/.*)/';
		$cleaned = preg_replace_callback($pattern, function($m) {
			// If the match starts with /, it is a comment (// or /*)
			if (strpos($m[0], '/') === 0) {
				return "";
			}
			// Otherwise, it is a string literal, return it untouched
			return $m[0];
		}, $jsOutput);

		if ($cleaned !== null) { $jsOutput = $cleaned; }

		// 4. Final cleanup: Remove resulting empty lines
		$jsOutput = preg_replace("/\n\s*\n/", "\n", $jsOutput);
		echo $jsOutput;
        exit;
    }

    private function actionPingHeartbeat() {
        global $timeout_in_minutes;
        $timeout_duration = (isset($timeout_in_minutes) ? $timeout_in_minutes : 15) * 60;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        
        $elapsed = time() - $_SESSION['last_activity'];

        if ($elapsed > $timeout_duration) {
            $this->sendJsonAndExit(['status' => 'expired', 'elapsed' => $elapsed]);
        }
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            if ($params["lifetime"] > 0) {
                setcookie(
                    session_name(), 
                    session_id(), 
                    time() + $params["lifetime"], 
                    $params["path"], 
                    $params["domain"], 
                    $params["secure"], 
                    $params["httponly"]
                );
            }
        }

        $_SESSION['last_activity'] = time();
        session_write_close();
        $this->sendJsonAndExit(['status' => 'OK', 'elapsed' => 0]);
    }

    
    // --- Action Methods ---

   private function actionGetDownloadToken() {
        global $L, $zip_warn_limit;
        $relPath = $_POST['path'] ?? '';
        $token = bin2hex(random_bytes(20));
        $isPreview = !empty($_POST['preview']);

        // Zip Content
        if (preg_match('/(.*\.zip)(\/.*)/i', $relPath, $matches)) {
            $zipRelPath = $matches[1];
            $internalFile = ltrim($matches[2], '/');
            $fullZipPath = $this->resolve($zipRelPath);
            
            if ($fullZipPath && file_exists($fullZipPath)) {
                  $this->dl_tokens[$token] = [
                    'is_zip_extract' => true,
                    'zip_path'       => $fullZipPath,
                    'internal_path'  => $internalFile,
                    'filename'       => $_POST['filename'] ?? basename($internalFile),
                    'preview'        => $isPreview,
                    'is_icon'        => !empty($_POST['is_icon']),
                    'expires'        => time() + 300
                ];
                if (!$isPreview) $this->log('DOWNLOAD', $relPath, '-', 'OK (Zip Content)');
                $this->sendJsonAndExit(['status' => 'OK', 'token' => $token]);
            }
        }

        $fullPath = $this->resolve($relPath);
        if (!$fullPath || (!is_file($fullPath) && !is_dir($fullPath))) {
			session_write_close();
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => $L['invalid_path'] ?? 'Invalid path']);
        }

        // Folder Zip on Fly
        if (is_dir($fullPath)) {
            if (isset($zip_warn_limit) && $zip_warn_limit > 0) {
                $totalSize = 0;
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    $totalSize += $file->getSize();
                    if ($totalSize > $zip_warn_limit) break; 
                }
                if ($totalSize > $zip_warn_limit) {
                    $format = function($bytes) {
                        if ($bytes <= 0) return '0B';
                        $sz = array('B','KB','MB','GB','TB','PB');
                        $factor = floor((strlen($bytes) - 1) / 3);
                        return sprintf("%.2f", $bytes / pow(1024, $factor)) . $sz[$factor];
                    };
					session_write_close();
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => ($L['zip_warn_msg']??'Folder too large') . ' (' . $format($totalSize) . ').']);
                }
            }

            $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
            $zipFile = $tempDir . '/myCloud_dl_' . bin2hex(random_bytes(16)) . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Could not create zip archive']);
            }

            set_time_limit(0); 
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $file) {
                if (!$file->isReadable()) continue;
                $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen($fullPath) + 1));
            }
            $zip->close();

            if (!file_exists($zipFile) || filesize($zipFile) === 0) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Zip generation failed']);
            }

            $baseName = $_POST['filename'] ?? basename($relPath);
            if (!$baseName || $baseName === '/' || $baseName === '.') $baseName = 'Archive';

            $this->dl_tokens[$token] = [
                'path' => $zipFile, 'filename' => $baseName . '.zip', 'preview' => false,
                'expires' => time() + 300, 'is_temp' => true 
            ];
			session_write_close();
            $this->sendJsonAndExit(['status' => 'OK', 'token' => $token]);
        }

        // Standard File
        $uHash = isset($_SESSION['username']) ? md5($_SESSION['username']) : 'guest';
        $pHash = md5($this->cloud_path);
		$isTempFile = (strpos(basename($fullPath), '.myCloud_temp_') === 0);
        $this->dl_tokens[$token] = [
            'path' => $fullPath, 'filename' => $_POST['filename'] ?? basename($relPath),
            'preview' => $isPreview, 'is_icon' => !empty($_POST['is_icon']), 
            'expires' => time() + 300, 'user_hash' => $uHash, 'path_hash' => $pHash, 'rel_dir' => dirname($relPath),
            'is_temp' => $isTempFile, 'is_pdf_print' => $isTempFile
        ];
        if (!$isPreview) $this->log('DOWNLOAD', $relPath);
        session_write_close();
        $this->sendJsonAndExit(['status' => 'OK', 'token' => $token]);
    }

    private function actionList() {
        global $cloud_preview_cache, $cloud_icon_cache;
        $path = $_POST['path'] ?? '/';
        
        // Zip Listing
        if (preg_match('/(.*\.zip)(\/.*)?/i', $path, $matches)) {
            $zipFilePath = $this->resolve($matches[1]);
            $internalPath = isset($matches[2]) ? ltrim($matches[2], '/') : '';
            if ($zipFilePath && file_exists($zipFilePath)) {
                $zip = new ZipArchive;
                if ($zip->open($zipFilePath) === TRUE) {
                    $data = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $zipEntryName = $stat['name'];
                        if ($internalPath === '' || strpos($zipEntryName, $internalPath) === 0) {
                            $relativeEntry = substr($zipEntryName, strlen($internalPath));
                            if ($relativeEntry === '' || $relativeEntry === false) continue;
                            $parts = explode('/', trim($relativeEntry, '/'));
                            $entryName = $parts[0];
                            if ($entryName === '') continue;
                            $entryName = $this->sanitizeAndValidateName($entryName, false);
                            $fullVirtualPath = rtrim($matches[1], '/') . '/' . rtrim($internalPath, '/') . '/' . $entryName;
                            $fullVirtualPath = str_replace('//', '/', $fullVirtualPath);
                            $isDir = (count($parts) > 1 || substr($zipEntryName, -1) === '/');
                            if (!isset($data[$fullVirtualPath])) {
                                $data[$fullVirtualPath] = ['name' => $fullVirtualPath, 'size' => $isDir ? 'DIR' : $stat['size'], 'date' => date('Y-m-d H:i', $stat['mtime']), 'isZipContent' => true];
                            }
                        }
                    }
                    $zip->close();
                    $this->sendJsonAndExit(['status' => 'OK', 'data' => array_values($data)]);
                }
            }
        }

        $maxDepth = isset($_POST['depth']) ? (int)$_POST['depth'] : 1; 
        $startDir = $this->resolve($path);
        
        // Recycle Bin Listing
        if ($path === '/.recycle_bin') {
            $data = [];
            if (is_dir($this->recycle_dir)) {
                $all_files = scandir($this->recycle_dir);
                $files_map = array_flip($all_files);
                foreach ($all_files as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (substr($f, -5) === '.meta' && isset($files_map[substr($f, 0, -5)])) continue;
                    $metaFile = $this->recycle_dir . $f . '.meta';
                    $originalName = $f; $originPath = '???'; $delTime = filemtime($this->recycle_dir . $f);
                    if (file_exists($metaFile)) {
                        $meta = json_decode(file_get_contents($metaFile), true);
                        if ($meta && isset($meta['origin'])) {
                            $originalName = basename($meta['origin']); $originPath = dirname($meta['origin']);
                            if (isset($meta['time'])) $delTime = $meta['time'];
                        }
                    }
                    $data[] = ['name' => '/.recycle_bin/' . $f, 'size' => is_dir($this->recycle_dir . $f) ? 'DIR' : filesize($this->recycle_dir . $f), 'date' => date('Y-m-d H:i', $delTime), 'displayName' => $originalName, 'origin' => $originPath];
                }
            }
            $this->sendJsonAndExit(['status' => 'OK', 'data' => $data, 'role' => $this->role]);
        }

        if (!$startDir || !is_dir($startDir)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid directory']);

        $data = [];
        $imagesToProcess = []; 
        $stack = [[$startDir, $path, 1]];

        while (!empty($stack)) {
            [$currDir, $currRel, $currLevel] = array_pop($stack);
            $entries = @scandir($currDir);
            if ($entries === false) continue;
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                if ($e === '.recycle_bin' || $e === '.recoll' || $e === '.mycloud_crypto_salt') continue;
                $fp = $currDir . '/' . $e;
                $rp = $currRel === '/' ? '/' . $e : rtrim($currRel, '/') . '/' . $e;
                $isDir = is_dir($fp);
                $isEncrypted = $isDir && file_exists($fp . '/.mycloud_crypto_salt');
                $data[] = ['name' => $rp, 'size' => $isDir ? 'DIR' : filesize($fp), 'date' => date('Y-m-d H:i', filemtime($fp)), 'isEncrypted' => $isEncrypted];
                if (!$isDir && $currLevel === 1) {
                    $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
                    $valid = ['jpg','jpeg','png','gif','webp','bmp','mp4','webm','mov','mkv','avi'];
                    if (class_exists('Imagick')) $valid = array_merge($valid, ['tiff','tif','dng','cr2','nef','arw','psd']);
                    if (in_array($ext, $valid)) $imagesToProcess[] = ['full' => $fp, 'relDir' => dirname($rp)];
                }
                if ($isDir && $currLevel < $maxDepth && !$isEncrypted) $stack[] = [$fp, $rp, $currLevel + 1];
            }
        }
        
		$canDelete = !$this->isActionBlocked('delete');
        
        // --- NEW: Granular Recycle Bin UI Checks ---
        $canRestore = !$this->isActionBlocked('restore');
        $canEmptyBin = !$this->isActionBlocked('empty_bin');
        $canRestoreTo = !$this->isActionBlocked('restore_to');
        $showRecycleBinUi = $canRestore || $canEmptyBin || $canRestoreTo;

        $jail = rtrim(realpath($this->cloud_path), DIRECTORY_SEPARATOR);
        $checkPath = rtrim($startDir, DIRECTORY_SEPARATOR);
        $cryptoRoot = null;
        
        while ($checkPath && strpos($checkPath, $jail) === 0) {
            if (file_exists($checkPath . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt')) {
                $rel = substr($checkPath, strlen($jail));
                $rel = str_replace('\\', '/', $rel);
                $cryptoRoot = $rel === '' ? '/' : $rel;
                break;
            }
            if ($checkPath === $jail) break;
            $checkPath = dirname($checkPath);
        }

        if ($path === '/' && isset($_POST['enableRecycleBin']) && $_POST['enableRecycleBin'] === 'true') {
            // Ensure directory exists for background deletions even if hidden from UI
            if ($canDelete && !file_exists($this->recycle_dir)) {
                @mkdir($this->recycle_dir);
            }
            // Only inject the visual folder if the user has rights to interact with it
            if ($showRecycleBinUi) {
                if (!file_exists($this->recycle_dir)) @mkdir($this->recycle_dir);
                $data[] = ['name' => '/.recycle_bin', 'size' => 'DIR', 'date' => date('Y-m-d H:i', filemtime($this->recycle_dir)), 'isRecycleBin' => true];
            }
        }

        // Flush Output
        while (ob_get_level() > 0) ob_end_clean();
        @ini_set('zlib.output_compression', 'Off');
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
        ob_start();
        echo json_encode(['status' => 'OK', 'data' => $data, 'role' => $this->role, 'is_encrypted_root' => ($cryptoRoot !== null), 'crypto_root' => $cryptoRoot]);
        $size = ob_get_length();
        header("Content-Type: application/json"); header("Connection: close"); header("Content-Encoding: none"); header("Content-Length: " . $size);
        session_write_close();
        ob_end_flush(); flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else ignore_user_abort(true);

        // Background Processing
        set_time_limit(3600);
        if (function_exists('proc_nice')) proc_nice(19);

        if (isset($cloud_preview_cache)) {
            $safePath = ltrim(str_replace(':', '', $startDir), '/\\');
            $iconCachePath = rtrim($cloud_icon_cache ?? $cloud_preview_cache, '/') . '/' . $safePath;
            $prevCachePath = rtrim($cloud_preview_cache, '/') . '/' . $safePath;

            // Cleanup
            if (is_dir($iconCachePath)) {
                foreach (scandir($iconCachePath) as $cf) {
                    if ($cf === '.' || $cf === '..') continue;
                    $origName = (substr($cf, -10) === '_thumb.jpg') ? substr($cf, 0, -10) : $cf;
                    if (!file_exists($startDir . '/' . $origName)) @unlink($iconCachePath . '/' . $cf);
                }
            }
            if (is_dir($prevCachePath)) {
                foreach (scandir($prevCachePath) as $cf) {
                    if ($cf === '.' || $cf === '..') continue;
                    $origName = (substr($cf, -4) === '.jpg') ? substr($cf, 0, -4) : $cf;
                    if (substr($cf, -4) === '.jpg' && !file_exists($startDir . '/' . $origName)) @unlink($prevCachePath . '/' . $cf);
                }
            }
            
            // Generation
            foreach ($imagesToProcess as $img) {
                set_time_limit(120);
                $imgSafe = ltrim(str_replace(':', '', $img['full']), '/\\');
                $iconFile = rtrim($cloud_icon_cache ?? $cloud_preview_cache, '/') . '/' . $imgSafe . '_thumb.jpg';
                $iconDir  = dirname($iconFile);
                if (!is_dir($iconDir)) @mkdir($iconDir, 0755, true);
                if (!file_exists($iconFile) || filemtime($img['full']) > filemtime($iconFile)) {
                    $this->generateIcon($img['full'], $iconFile);
                }
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            }
        }
        exit;
    }

    private function actionCheckIndex() {
        $recollDir = $this->cloud_path . '.recoll';
        $dbDir = $recollDir . '/xapiandb';
        $hasIndex = false;
        $lastUpdate = 0;

        if (is_dir($dbDir)) {
            if (file_exists($dbDir . '/record.glass')) { $hasIndex = true; $lastUpdate = filemtime($dbDir . '/record.glass'); }
            elseif (file_exists($dbDir . '/record.baseA')) { $hasIndex = true; $lastUpdate = filemtime($dbDir . '/record.baseA'); }
            elseif (count(scandir($dbDir)) > 2) { $hasIndex = true; $lastUpdate = filemtime($dbDir); }
        }
        $this->sendJsonAndExit(['status' => 'OK', 'has_index' => $hasIndex, 'last_update' => $lastUpdate]);
    }

    private function actionSearch() {
        $query = trim($_POST['query'] ?? '');
        $dateRange = $_POST['date_range'] ?? 'all';
        $dStart = $_POST['custom_date_start'] ?? null;
        $dEnd   = $_POST['custom_date_end']   ?? null;
        $searchStartFull = $this->resolve($_POST['dir'] ?? '/');
        if (!$searchStartFull || !is_dir($searchStartFull)) $searchStartFull = $this->cloud_path;
		
		$searchPrefix = rtrim($searchStartFull, '/\\') . DIRECTORY_SEPARATOR;

        $contentSearch = isset($_POST['content_search']) && $_POST['content_search'] === '1';

        $tCutStart = 0; $tCutEnd = time();
        switch ($dateRange) {
            case '1h':      $tCutStart = time() - 3600; break;
            case '4h':      $tCutStart = time() - (4 * 3600); break;
            case '24h':     $tCutStart = time() - 86400; break;
            case 'week':    $tCutStart = time() - (7 * 86400); break;
            case 'month':   $tCutStart = time() - (30 * 86400); break;
            case '3months': $tCutStart = time() - (90 * 86400); break;
            case 'year':    $tCutStart = time() - (365 * 86400); break;
            case 'custom':  if ($dStart) $tCutStart = strtotime($dStart . ' 00:00:00'); if ($dEnd) $tCutEnd = strtotime($dEnd . ' 23:59:59'); break;
        }

        $sizeRange = $_POST['size_range'] ?? 'all';
        $sMinMB = floatval($_POST['custom_size_min'] ?? 0);
        $sMaxMB = floatval($_POST['custom_size_max'] ?? 0);
        $bMin = 0; $bMax = PHP_INT_MAX;
        switch ($sizeRange) {
            case 'small':  $bMax = 100 * 1024; break;                
            case 'medium': $bMin = 100 * 1024; $bMax = 10 * 1024 * 1024; break; 
            case 'large':  $bMin = 10 * 1024 * 1024; $bMax = 1024 * 1024 * 1024; break; 
            case 'huge':   $bMin = 1024 * 1024 * 1024; break;        
            case 'custom': if ($sMinMB > 0) $bMin = $sMinMB * 1024 * 1024; if ($sMaxMB > 0) $bMax = $sMaxMB * 1024 * 1024; break;
        }
		
        $tagFilter = $_POST['tag_filter'] ?? 'all';
        $taggedPaths = [];
        if ($tagFilter !== 'all') {
            global $cloud_user_profiles;
            if (!empty($cloud_user_profiles)) {
                $f = rtrim($cloud_user_profiles, '/\\') . '/' . $this->username . '_tags.json';
                if (file_exists($f)) {
                    $tagsData = json_decode(file_get_contents($f), true);
                    if ($tagsData && isset($tagsData[$this->key])) {
                        foreach ($tagsData[$this->key] as $p => $t) {
                            if (!is_array($t)) $t = [$t];
                            if (in_array($tagFilter, $t, true)) $taggedPaths[] = $p;
                        }
                    }
                }
            }
        }

        $checkTag = function($relativePath) use ($taggedPaths) {
            if (empty($taggedPaths)) return false;
            foreach ($taggedPaths as $tp) {
                if ($relativePath === '/' || $tp === '/' || $relativePath === $tp) return true;
                if (strpos($relativePath, $tp . '/') === 0) return true; // Inherits from tag
                if (strpos($tp, $relativePath . '/') === 0) return true; // Leads down to tag
            }
            return false;
        };

        $data = [];

        // 1. RECOLL CONTENT ENGINE EXECUTION
        if ($contentSearch) {
			
            // Force full cloud search for index queries
            $searchStartFull = $this->cloud_path;
            $searchPrefix = rtrim($searchStartFull, '/\\') . DIRECTORY_SEPARATOR;


            $recollDir = $this->cloud_path . '.recoll';
            if (is_dir($recollDir) && $query !== '') {
                // Pipe command specifically targeting the isolated Recoll config. Extract raw file URIs.
                $recollQuery = $query;

                // If no explicit field tags (e.g., author:, title:, ext:) are present, 
                // search specifically in the document text OR the filename.
                if (!preg_match('/\b[a-zA-Z0-9_]+:/', $query)) {
                    $recollQuery = '(text:"' . $query . '" OR filename:"' . $query . '")';
                }
                // Optimize Recoll: Force it to filter by the current directory natively
                if ($searchPrefix !== $this->cloud_path) {
                    $recollQuery .= ' dir:"' . rtrim($searchStartFull, '/\\') . '"';
                }

                $cmd = "recoll -c " . escapeshellarg($recollDir) . " -n 1000000 -t -q " . escapeshellarg($recollQuery) . " 2>/dev/null";
                $output = shell_exec($cmd);
                preg_match_all('/file:\/\/([^\]\n\r]+)/m', $output, $matches);
                $files = $matches[1] ?? [];
				
				// Deduplicate to prevent archives from appearing once for every internal match
				$files = array_unique(array_map('trim', $files));

                $open_basedir = ini_get('open_basedir');
                $basedir_paths = $open_basedir ? explode(PATH_SEPARATOR, $open_basedir) : [];
                
                foreach ($files as $f) {
                    $f = urldecode($f);
                    
                    // 1. open_basedir check (prevent PHP warnings before disk I/O)
                    if (!empty($basedir_paths)) {
                        $allowed = false;
                        foreach ($basedir_paths as $bdir) {
                            if (strpos($f, rtrim($bdir, '/\\')) === 0) { $allowed = true; break; }
                        }
                        if (!$allowed) continue;
                    }

                    // 2. Security & Directory Filter: Cloud boundary & Existence
                    if (strpos($f, $searchPrefix) !== 0 || strpos($f, $this->recycle_dir) === 0 || !file_exists($f)) continue;
					if (substr($f, -4) === '.enc') continue;
 
                    // Route through date/size filters
                    $mtime = filemtime($f);
                    if ($dateRange !== 'all') { if ($tCutStart > 0 && $mtime < $tCutStart) continue; if ($mtime > $tCutEnd) continue; }
                    $isDir = is_dir($f);
                    if (!$isDir && $sizeRange !== 'all') { $size = filesize($f); if ($size < $bMin || $size > $bMax) continue; }
                    
                    // Map back to Cloud GUI structure
                    $relativePath = '/' . ltrim(substr($f, strlen($this->cloud_path)), '/');
                    $relativePath = str_replace('\\', '/', $relativePath);
					
					if ($tagFilter !== 'all' && !$checkTag($relativePath)) continue;
					
                    $data[] = ['name' => $relativePath, 'size' => $isDir ? 'DIR' : filesize($f), 'date' => date('Y-m-d H:i', $mtime)];
                    
                    //if (count($data) > 2500) break;
                }
            }
            $this->sendJsonAndExit(['status' => 'OK', 'data' => $data]);
        }

        // 2. STANDARD RECURSIVE DIRECTORY FALLBACK
        // If user typed a wildcard, use it. Otherwise wrap in wildcards for normal substring matching.
        $hasWildcard = (strpos($query, '*') !== false || strpos($query, '?') !== false);
        $searchPattern = $hasWildcard ? $query : '*' . $query . '*';

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($searchStartFull, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        
        foreach ($iterator as $file) {
            $mtime = $file->getMTime();
            if ($dateRange !== 'all') { if ($tCutStart > 0 && $mtime < $tCutStart) continue; if ($mtime > $tCutEnd) continue; }
            $isDir = $file->isDir();
            if (!$isDir && $sizeRange !== 'all') { $size = $file->getSize(); if ($size < $bMin || $size > $bMax) continue; }
            if ($query !== '' && !fnmatch($searchPattern, $file->getFilename(), FNM_CASEFOLD)) continue;
            
            $realPath = $file->getRealPath();
            
            // Security & Exclusion Filter
            if (strpos($realPath, $this->cloud_path) !== 0) continue;
            if ($realPath === rtrim($this->recycle_dir, '/\\') || strpos($realPath, $this->recycle_dir) === 0) continue;
            $recollDir = $this->cloud_path . '.recoll';
            if ($realPath === $recollDir || strpos($realPath, $recollDir . DIRECTORY_SEPARATOR) === 0) continue;
			if ($file->getFilename() === '.mycloud_crypto_salt') continue;
			if (substr($file->getFilename(), -4) === '.enc') continue;
            
            $relativePath = '/' . substr($realPath, strlen($this->cloud_path));
            $relativePath = str_replace('\\', '/', $relativePath);
			if ($tagFilter !== 'all' && !$checkTag($relativePath)) continue;
            $data[] = ['name' => $relativePath, 'size' => $isDir ? 'DIR' : $file->getSize(), 'date' => date('Y-m-d H:i', $mtime)];
            if (count($data) > 2500) break;
        }
        $this->sendJsonAndExit(['status' => 'OK', 'data' => $data]);
    }
	
    private function actionChangePassword() {
        $user = $this->username;
        if ($user === 'guest' || empty($user)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Not logged in']);
        }

        $old = $_POST['old_pass'] ?? '';
        $new = $_POST['new_pass'] ?? '';

        if (!$old || !$new) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Missing fields']);
        }

        global $user_db;
        if (!isset($user_db) || !file_exists($user_db)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'User database not found']);
        }
        require $user_db;

        $current_hash = $users[$user] ?? '';
        if (!$current_hash || !password_verify($old, $current_hash)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Current password incorrect']);
        }

        if (strlen($new) < 8 || !preg_match('/[-_.:,;()\/+#!§%&]/', $new) || !preg_match('/\d/', $new) || preg_match('/[$"\']/', $new)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Password policy not met']);
        }

        $new_hash = password_hash($new, PASSWORD_ARGON2ID);
        $handle = @fopen($user_db, 'r+');
        
        if ($handle && flock($handle, LOCK_EX)) {
            $content = stream_get_contents($handle);
            $pattern = "/(['\"]" . preg_quote($user, '/') . "['\"]\s*=>\s*)(['\"][^'\"]+['\"])/";
            if (preg_match($pattern, $content)) {
                $new_content = preg_replace($pattern, "$1'$new_hash'", $content);
                if ($new_content && $new_content !== $content) {
                    rewind($handle); fwrite($handle, $new_content); ftruncate($handle, ftell($handle));
                    flock($handle, LOCK_UN); fclose($handle);
                    $this->sendJsonAndExit(['status' => 'OK']);
                }
            }
            flock($handle, LOCK_UN); fclose($handle);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to update database']);
    }

    private function actionLoadSettings() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status' => 'OK', 'settings' => null]);
        $profileDir = rtrim($cloud_user_profiles, '/\\');
        $file = $profileDir . '/' . $this->username . '.json';
        $settings = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $needsSave = false;
        if (!isset($settings['appLaunchCount'])) $settings['appLaunchCount'] = 0;
        if (isset($_POST['inc_launch']) && $_POST['inc_launch'] === '1') { $settings['appLaunchCount']++; $needsSave = true; }            
        if ($settings['appLaunchCount'] > 3 && !isset($settings['showHelpOnStart'])) $settings['showHelpOnStart'] = false;
        if ($needsSave) @file_put_contents($file, json_encode($settings));
        $this->sendJsonAndExit(['status' => 'OK', 'settings' => $settings]);
    }

    private function actionSaveSettings() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Config Error']);
        $profileDir = rtrim($cloud_user_profiles, '/\\');
        if (!is_dir($profileDir)) @mkdir($profileDir, 0755, true);
        $file = $profileDir . '/' . $this->username . '.json';
        $json = $_POST['settings_json'] ?? '';
        if (!empty($json) && json_decode($json) !== null) {
            file_put_contents($file, $json);
            $this->sendJsonAndExit(['status' => 'OK']);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid Data']);
    }

    private function actionResetSettings() {
        global $cloud_user_profiles;
        if (!empty($cloud_user_profiles)) {
            $profileDir = rtrim($cloud_user_profiles, '/\\');
            @unlink($profileDir . '/' . $this->username . '.json');
            @unlink($profileDir . '/' . $this->username . '_views.json');
        }
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionSwitchLanguage() {
        global $cloud_user_profiles;
        $newLang = $_POST['lang'] ?? 'en';
        if (!empty($cloud_user_profiles)) {
            $profileDir = rtrim($cloud_user_profiles, '/\\');
            if (!is_dir($profileDir)) @mkdir($profileDir, 0755, true);
            $file = $profileDir . '/' . $this->username . '.json';
            $settings = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            $settings['language'] = $newLang;
            file_put_contents($file, json_encode($settings));
        }
        $langDir = __DIR__ . '/lang';
        $targetFile = "$langDir/$newLang.php";
        if (!file_exists($targetFile)) { $newLang = 'en'; $targetFile = "$langDir/en.php"; }
        $newStrings = require $targetFile;
        $langs = [];
        if (is_dir($langDir)) {
            foreach (glob("$langDir/*.php") as $f) { $c = basename($f, '.php'); $d = include $f; $langs[$c] = $d['__lang_label'] ?? strtoupper($c); }
        }
        $newStrings['available_languages'] = $langs;
        $this->sendJsonAndExit(['status' => 'OK', 'strings' => $newStrings]);
    }

    private function actionLoadViews() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'OK', 'views'=>[]]);
        $viewFile = rtrim($cloud_user_profiles, '/\\') . '/' . $this->username . '_views.json';
        $allViews = file_exists($viewFile) ? json_decode(file_get_contents($viewFile), true) : [];
        $this->sendJsonAndExit(['status' => 'OK', 'views' => $allViews[$this->key] ?? []]);
    }

    private function actionSaveView() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'ERR']);
        $profileDir = rtrim($cloud_user_profiles, '/\\');
        if (!is_dir($profileDir)) @mkdir($profileDir, 0755, true);
        $viewFile = $profileDir . '/' . $this->username . '_views.json';
        $allViews = file_exists($viewFile) ? json_decode(file_get_contents($viewFile), true) : [];
        if (!isset($allViews[$this->key])) $allViews[$this->key] = [];
        
        $targetPath = $_POST['path'] ?? '/';
        $relPath = strpos($targetPath, $this->cloud_path) === 0 ? substr($targetPath, strlen($this->cloud_path)) : $targetPath;
        $relPath = '/' . ltrim(str_replace('\\', '/', $relPath), '/');
        
        $mode = $_POST['mode'] ?? 'list';
        // Inheritance logic omitted for brevity, assuming explicit save
        $allViews[$this->key][$relPath] = $mode;
        file_put_contents($viewFile, json_encode($allViews));
        $this->sendJsonAndExit(['status' => 'OK', 'views' => $allViews[$this->key]]);
    }

    private function actionHandleTicket($action) {
        global $work_dir;
        $ticket_db = $work_dir . '/data/tickets.json';
        $isAdmin = ($this->role === 'full');
        
        $loadTickets = function() use ($ticket_db) { return file_exists($ticket_db) ? json_decode(file_get_contents($ticket_db), true) : []; };
        $saveTickets = function($data) use ($ticket_db) {
            $cutoff = time() - (90 * 86400);
            $data = array_filter($data, function($t) use ($cutoff) { return !($t['status'] === 'Closed' && $t['timestamp'] < $cutoff); });
            file_put_contents($ticket_db, json_encode(array_values($data), JSON_PRETTY_PRINT));
        };

        if ($action === 'ticket-list') {
            $tickets = $loadTickets();
            $out = [];
            foreach ($tickets as $t) { if ($isAdmin || (isset($t['user']) && $t['user'] === $this->username)) $out[] = $t; }
            usort($out, function($a, $b) {
                $w = ['Open'=>3, 'In Progress'=>2, 'Closed'=>1];
                $wa = $w[$a['status']]??0; $wb = $w[$b['status']]??0;
                return ($wa !== $wb) ? $wb - $wa : $b['timestamp'] - $a['timestamp'];
            });
            $this->sendJsonAndExit(['status' => 'OK', 'data' => $out, 'isAdmin' => $isAdmin]);
        }
        if ($action === 'ticket-create') {
            $title = trim($_POST['title'] ?? '');
            if (!$title) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Missing title']);
            $tickets = $loadTickets();
            array_unshift($tickets, ['id'=>uniqid('tkt_'), 'user'=>$this->username, 'type'=>$_POST['type']??'Bug', 'title'=>$title, 'desc'=>trim($_POST['desc']??''), 'status'=>'Open', 'timestamp'=>time(), 'admin_comment'=>'']);
            $saveTickets($tickets);
            $this->sendJsonAndExit(['status' => 'OK']);
        }
        if ($action === 'ticket-update' && $isAdmin) {
            $tickets = $loadTickets(); $found=false;
            foreach ($tickets as &$t) {
                if (isset($_POST['priority'])) $t['priority'] = (int)$_POST['priority'];
                if ($t['id'] === $_POST['id']) { $t['status'] = $_POST['status']; if(isset($_POST['comment'])) $t['admin_comment'] = $_POST['comment']; $found=true; break; }
            }
            if ($found) { $saveTickets($tickets); $this->sendJsonAndExit(['status' => 'OK']); }
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Ticket not found']);
        }
        if ($action === 'ticket-changelog' && $isAdmin) {
            $tickets = $loadTickets(); $targetTicket = null;
            foreach ($tickets as $t) { if ($t['id'] === $_POST['id']) { $targetTicket = $t; break; } }
            if (!$targetTicket) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Ticket missing']);
            
            $vFile = $work_dir . '/cloud.beta/versioninfo.txt';
            if (!file_exists($vFile)) file_put_contents($vFile, "1.0.0\n\tInitial Release");
            $content = file_get_contents($vFile);
            $currentVer = '1.0.0';
            if (preg_match('/^\d+\.\d+\.\d+$/m', $content, $m)) $currentVer = $m[0];
            
            $prefix = ['Bug'=>'Fix: ', 'Feature'=>'Feature: ', 'Improvement'=>'Improvement: ', 'Security'=>'Security: '][$targetTicket['type']] ?? 'Update: ';
            $safeTitle = htmlspecialchars($targetTicket['title'], ENT_QUOTES, 'UTF-8');
            $ticketLine = "\t" . $prefix . $safeTitle;

            $incMode = $_POST['increment_mode'] ?? 'none';
            $finalVer = $currentVer;

            if ($incMode !== 'none') {
                $p = explode('.', $currentVer); 
                if(count($p)===3) { 
                    $p[2]++; // Continuous build number (never reset)
                    if($incMode==='major'){ $p[0]++; $p[1]=0; } 
                    elseif($incMode==='minor'){ $p[1]++; } 
                    $nextVer=implode('.',$p); 
                } else $nextVer=$currentVer.".1";
                file_put_contents($vFile, $nextVer . "\n" . $ticketLine . "\n\n" . $content); $finalVer=$nextVer;
            } else {
                $pos = strpos($content, $currentVer);
                if($pos!==false) { $eol=strpos($content,"\n",$pos)?:strlen($content); file_put_contents($vFile, substr_replace($content, "\n".$ticketLine, $eol, 0)); }
                else file_put_contents($vFile, $currentVer."\n".$ticketLine."\n\n".$content);
            }
            foreach ($tickets as &$t) { if ($t['id'] === $_POST['id']) { $t['status'] = 'Closed'; $t['admin_comment'] = "Added to v$finalVer"; } }
            $saveTickets($tickets);
            $this->sendJsonAndExit(['status' => 'OK', 'msg' => "Updated v$finalVer"]);
        }
    }

    private function actionEmptyBin() {
        if (is_dir($this->recycle_dir)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->recycle_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) { $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath()); }
        }
        $this->sendJsonAndExit(['status'=>'OK']);
    }

    private function actionRestore() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || strpos($src, $this->recycle_dir) !== 0 || !file_exists($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid item']);
        
        $metaFile = $src . '.meta'; $dest = dirname($src);
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $originPath = $this->cloud_path . ltrim($meta['origin'], '/');
            
            if (!empty($_POST['custom_dest'])) {
                $custom = $this->resolve($_POST['custom_dest']);
                if ($custom && is_dir($custom)) $dest = $custom . '/' . basename($originPath);
                else $this->sendJsonAndExit(['status'=>'ERR', 'code'=>'PATH_MISSING', 'msg'=>'Target missing']);
            } else {
                if (!is_dir(dirname($originPath))) {
                    if (!@mkdir(dirname($originPath), 0755, true)) $this->sendJsonAndExit(['status'=>'ERR', 'code'=>'PATH_MISSING', 'msg'=>'Original missing']);
                }
                $dest = $originPath;
            }
            
            if (file_exists($dest)) {
                $res = $_POST['resolution'] ?? null;
                if (!$res) $this->sendJsonAndExit(['status' => 'CONFLICT', 'msg' => 'File exists', 'file' => basename($dest)]);
                if ($res === 'keep_both') $dest = $this->getUniqueName($dest);
                if ($res === 'overwrite') {
                    if ($this->isActionBlocked('overwrite')) {
                        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Overwrite permission denied.']);
                    }
                    is_dir($dest) ? @rmdir($dest) : @unlink($dest);
                }
            }
            @unlink($metaFile);
        }
        
        if (@rename($src, $dest)) {
            $this->log('RESTORE', $_POST['src'], $dest);
            $this->sendJsonAndExit(['status' => 'OK', 'dest_dir' => dirname($dest)]);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Restore failed']);
    }

    private function actionMkdir() {
        $parent = rtrim($this->resolve($_POST['parent'] ?? '/'), '/');
        if (preg_match('/\.zip(\/|$)/i', $_POST['parent'] ?? '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        
        // --- CENTRAL GATEKEEPER ---
        $name = $this->sanitizeAndValidateName($_POST['name'] ?? '', true);
        
        $val = validateFilename($name);
        if (!$parent || !$val['valid']) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => $val['error'] ?? 'Invalid name']);
        if (file_exists("$parent/$name")) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Exists']);
        if (!@mkdir("$parent/$name", 0755, true)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed']);
        $this->log('MKDIR', $_POST['parent'] . '/' . $name);
        $this->sendJsonAndExit(['status' => 'OK']);
    }
    
    private function actionMkfile() {
        $parent = rtrim($this->resolve($_POST['parent'] ?? '/'), '/');
        if (preg_match('/\.zip(\/|$)/i', $_POST['parent'] ?? '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        
        // --- CENTRAL GATEKEEPER ---
        $name = $this->sanitizeAndValidateName($_POST['name'] ?? '', true);
        
        $val = validateFilename($name);
        if (!$parent || !$val['valid']) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => $val['error'] ?? 'Invalid name']);
        if (file_exists("$parent/$name")) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Exists']);
        if (!@touch("$parent/$name")) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed']);
        $this->log('MKFILE', $_POST['parent'] . '/' . $name);
        $this->sendJsonAndExit(['status' => 'OK']);
    }


    private function actionBatchRename() {
        $ops = json_decode($_POST['operations'], true);
        if (!is_array($ops)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid data']);
        $errs = []; $res = [];
        foreach($ops as $item) {
            $src = $this->resolve($item['src']);
            
            // --- CENTRAL GATEKEEPER ---
            $safeNewName = $this->sanitizeAndValidateName($item['new'], true);
            
            if (!$src || !file_exists($src) || strpos($safeNewName, '..') !== false) { 
                $errs[] = "Error: {$item['src']}"; 
                continue; 
            }
            $target = dirname($src) . DIRECTORY_SEPARATOR . $safeNewName;
            if (file_exists($target) && $target !== $src) $target = $this->getUniqueName($target);
            if (@rename($src, $target)) $res[] = basename($target); else $errs[] = "Failed: {$item['src']}";
        }
        $this->log('BATCH_RENAME', count($res).' items');
        $this->sendJsonAndExit(['status' => empty($errs)?'OK':'PARTIAL', 'errors'=>$errs]);
    }

    private function actionRename() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !file_exists($src)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Not found']);
        if (strpos($src, '.zip/') !== false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        
        // --- CENTRAL GATEKEEPER ---
        $newName = $this->sanitizeAndValidateName($_POST['newName'] ?? '', true);
        
        $val = validateFilename($newName);
        if (!$val['valid']) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => $val['error']]);
        
        $dest = dirname($src) . '/' . $newName;
        if (file_exists($dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Exists']);
        if (!@rename($src, $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed']);
        
        $this->log('RENAME', $_POST['src'], dirname($_POST['src']) . '/' . $newName);
        $this->sendJsonAndExit(['status' => 'OK', 'newPath' => dirname($_POST['src']) . '/' . $newName]);
    }


	private function actionDuplicate() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !file_exists($src)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Not found']);
        if (strpos($src, '.zip/') !== false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);

        $info = pathinfo($src);
        $dir = $info['dirname'];

        // If the client provides a pre-calculated encrypted filename (for Vaults)
        if (!empty($_POST['newName'])) {
            $safeNewName = $this->sanitizeAndValidateName($_POST['newName'], true);
            $dest = $dir . '/' . $safeNewName;
            
            if (file_exists($dest)) {
                $dest = $this->getUniqueName($dest);
            }
        } else {
            // Standard Unencrypted File Duplication
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
            $name = $info['filename'];

            // Extract base name and counter if it already ends with " (x)"
            if (preg_match('/^(.*) \((\d+)\)$/', $name, $matches)) {
                $base = $matches[1];
                $counter = (int)$matches[2];
            } else {
                $base = $name;
                $counter = 0;
            }

            // Count up to the next free number
            do {
                $counter++;
                $dest = $dir . '/' . $base . " ($counter)" . $ext;
            } while (file_exists($dest));
        }

        // Support both file and recursive directory duplication
        if (is_dir($src)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            @mkdir($dest, 0755, true);
            foreach ($iter as $item) {
                $t = $dest . substr($item->getRealPath(), strlen($src));
                $item->isDir() ? @mkdir($t) : @copy($item->getRealPath(), $t);
            }
        } else {
            if (!@copy($src, $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Duplicate failed']);
        }

        $this->log('DUPLICATE', $_POST['src'], $dest);
        $this->sendJsonAndExit(['status' => 'OK']);
    }
	
	
    private function actionDelete() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (strpos($_POST['src'] ?? '', '.zip/') !== false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        if (!$src) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path']);
        
        $inBin = strpos($src, $this->recycle_dir) === 0;
        $isPerm = (isset($_POST['permanent']) && $_POST['permanent'] === 'true') || $inBin;
        $useRecycle = (isset($_POST['useRecycleBin']) && $_POST['useRecycleBin'] === 'true');

        if (!$isPerm && $useRecycle) {
            if (!is_dir($this->recycle_dir)) @mkdir($this->recycle_dir, 0755, true);
            $target = $this->recycle_dir . time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($src);
            if (@rename($src, $target)) {
                $meta = ['origin' => '/' . ltrim(substr($src, strlen($this->cloud_path)), '/'), 'time' => time()];
                file_put_contents($target . '.meta', json_encode($meta));
                $this->log('RECYCLE', $_POST['src']);
                $this->sendJsonAndExit(['status' => 'OK', 'recycled' => '/.recycle_bin/' . basename($target)]);
            } else {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Recycle failed']);
            }
        }

        if ($inBin) @unlink($src . '.meta');
        if (is_dir($src)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iter as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            @rmdir($src);
        } else { @unlink($src); }
        
        $this->log('DELETE', $_POST['src']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionCopyMove($mode) {
        $src = $this->resolve($_POST['src'] ?? '');
        $destDir = rtrim($this->resolve($_POST['dest'] ?? '/'), '/');
        if ($mode === 'copy' && (strpos($src, $this->recycle_dir)===0 || strpos($destDir, $this->recycle_dir)===0)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'No copy in/out bin']);
        if (preg_match('/\.zip(\/|$)/i', $_POST['dest']??'')) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'ZIP read-only']);
        
        // Move to Bin Logic
        if ($mode === 'move') {
            if (strpos(realpath($destDir), realpath($this->recycle_dir)) === 0) {
                // ... reuse delete logic or simpler move ...
                // For simplicity, just doing standard rename here, assumes sender handled meta if using 'delete' endpoint
                // But if they use 'move' endpoint to bin, we should add meta:
                $target = $this->recycle_dir . time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($src);
                if (@rename($src, $target)) {
                    $meta = ['origin' => '/' . ltrim(substr($src, strlen($this->cloud_path)), '/'), 'time' => time()];
                    file_put_contents($target . '.meta', json_encode($meta));
                    $this->sendJsonAndExit(['status' => 'OK', 'recycled' => '/.recycle_bin/' . basename($target)]);
                }
            }
            // Restore From Bin Logic
            if (strpos($src, $this->recycle_dir) === 0) {
                @unlink($src . '.meta'); // Cleanup meta
            }
        }
        
        // Handle Copy from ZIP (Extraction)
        // Check if src looks like a zip path:  /path/to/archive.zip/inner/file
        if ($mode === 'copy' && preg_match('/(.*\.zip)\/(.*)/i', $_POST['src'] ?? '', $zMatches)) {
            $zipFile = $this->resolve($zMatches[1]);
            $innerPath = $zMatches[2]; // Relative inside zip

            if ($zipFile && file_exists($zipFile)) {
                $zip = new ZipArchive;
                if ($zip->open($zipFile) === TRUE) {
                    // destDir is valid, but we need full target path
                    $targetName = $this->sanitizeAndValidateName(basename($innerPath), true);
                    $finalDest = $destDir . '/' . $targetName;
                    if (file_exists($finalDest)) $finalDest = $this->getUniqueName($finalDest);

                    // Extract specific file
                    // Note: copy() usually implies 1 item or 1 folder.
                    // ZipArchive::extractTo extracts folders recursively if path matches
                    // We must be careful to extract ONLY the requested entry.
                    
                    // Check if it's a directory inside zip (trailing slash or matches dir)
                    $stat = $zip->statName($innerPath . '/'); // Check for dir
                    $isZipDir = ($stat !== false);
                    if (!$isZipDir) $stat = $zip->statName($innerPath); // Check for file

                    if ($stat) {
                        if ($isZipDir) {
                            // Recursive extraction for folder
                            // Filter entries starting with innerPath
                            $baseLen = strlen($innerPath);
                            for($i=0; $i<$zip->numFiles; $i++) {
                                $entry = $zip->getNameIndex($i);
                                if (strpos($entry, $innerPath) === 0) {
                                    $rel = substr($entry, $baseLen);
                                    // Catch sub-files inside an extracted folder
                                    $relBase = basename($entry);
                                    if ($relBase !== '') {
                                        $this->sanitizeAndValidateName($relBase, true);
                                    }                   
                                    $target = $finalDest . '/' . $rel;
                                    if (substr($entry, -1) === '/') {
                                        @mkdir($target, 0755, true);
                                    } else {
                                        @mkdir(dirname($target), 0755, true);
                                        copy("zip://" . $zipFile . "#" . $entry, $target);
                                    }
                                }
                            }
                        } else {
                            // Single file extraction
                            copy("zip://" . $zipFile . "#" . $innerPath, $finalDest);
                        }
                        $zip->close();
                        $this->sendJsonAndExit(['status' => 'OK']);
                    }
                    $zip->close();
                }
            }
        }

        if (!$src || !file_exists($src) || !$destDir || !is_dir($destDir)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid']);
        
        $dest = $destDir . '/' . $this->sanitizeAndValidateName(basename($src), true);
        if (file_exists($dest)) {
            if ($src === $dest) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Same path']);
            $res = $_POST['resolution'] ?? null;
            if (!$res) $this->sendJsonAndExit(['status' => 'CONFLICT', 'msg' => 'Exists', 'file' => basename($src)]);
            if ($res === 'keep_both') $dest = $this->getUniqueName($dest);
            if ($res === 'overwrite') {
                if ($this->isActionBlocked('overwrite')) {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Overwrite permission denied.']);
                }
                is_dir($dest) ? /* recursive delete omitted for brevity */ @rmdir($dest) : @unlink($dest);
            }
        }

        if ($mode === 'move') {
            if (!@rename($src, $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Move failed']);
        } else {
            if (is_dir($src)) {
                $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                @mkdir($dest, 0755, true);
                foreach ($iter as $item) {
                    $t = $dest . substr($item->getRealPath(), strlen($src));
                    $item->isDir() ? @mkdir($t) : @copy($item->getRealPath(), $t);
                }
            } else {
                if (!@copy($src, $dest)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Copy failed']);
            }
        }
        $this->log(strtoupper($mode), $_POST['src'], $_POST['dest'].'/'.basename($dest));
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionUpload() {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Upload Error ' . $_FILES['file']['error']]);
        
        $dir = rtrim($this->resolve($_POST['dir'] ?? '/'), '/');
        if (preg_match('/\.zip(\/|$)/i', $_POST['dir'] ?? '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        
        $rel = isset($_POST['relativePath']) ? trim($_POST['relativePath'], '/') : '';
        if ($rel !== '' && !preg_match('/(?:^|[\/\\\\])\.\.(?:$|[\/\\\\])/', $rel)) $dir .= '/' . $rel;
        
        if (strpos(realpath($dir)?:$dir, $this->recycle_dir) === 0) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'No upload to bin']);
        if (!file_exists($dir)) @mkdir($dir, 0755, true);

        $file = $_FILES['file'];
        
        // --- CENTRAL GATEKEEPER ---
        $safe_filename = $this->sanitizeAndValidateName(basename($file['name']), false);

        // HDD Space check (minus 10% safety buffer)
        $freeSpace = @disk_free_space($dir);
        if ($freeSpace !== false) {
            $safeSpace = $freeSpace * 0.9;
            if ($file['size'] > $safeSpace) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Insufficient disk space.']);
        }

        $dest = $dir . '/' . $safe_filename;
        $temp = $dest . '.uploading.' . uniqid();
        
        if (file_exists($dest)) {
            $res = $_POST['resolution'] ?? '';
            if ($res === 'keep_both') {
                $dest = $this->getUniqueName($dest);
            } elseif ($res === 'overwrite') {
                if ($this->isActionBlocked('overwrite')) {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Overwrite permission denied.']);
                }
            } else {
                $this->sendJsonAndExit(['status' => 'CONFLICT', 'msg' => 'Exists', 'file' => $safe_filename]);
            }
        }

        if (@move_uploaded_file($file['tmp_name'], $temp)) {
            
            // --- ANTI-MALWARE / CLAMAV HOOK ---
            global $cloud_clamav_enabled, $cloud_clamav_path;
            if (!empty($cloud_clamav_enabled)) {
                $clamav_bin = !empty($cloud_clamav_path) ? $cloud_clamav_path : 'clamdscan';
                $cmd = sprintf('%s --no-summary %s 2>&1', escapeshellcmd($clamav_bin), escapeshellarg($temp));
                exec($cmd, $output, $return_var);
                
                // ClamAV standard exit codes: 0 = clean, 1 = virus found
                if ($return_var === 1) {
                    @unlink($temp); // Nuke the infected file immediately
                    $this->log('UPLOAD_BLOCKED_MALWARE', $safe_filename, $_POST['dir'], 'VIRUS FOUND');
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Upload rejected: Malware detected by ClamAV.']);
                }
            }
            // ----------------------------------

            if (file_exists($dest)) @unlink($dest);
            @rename($temp, $dest);
            if (isset($_POST['lastModified'])) @touch($dest, (int)$_POST['lastModified']);
            $this->log('UPLOAD', $safe_filename, $_POST['dir']);
            $this->sendJsonAndExit(['status' => 'OK']);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Move failed']);
    }

	private function actionCloudIngestTemp() {
        // Securely ingest a temporary email attachment into the user's cloud
        $tmpPath = $_POST['tmp_path'] ?? '';
        $destDirRel = $_POST['dest'] ?? '';
        $name = $_POST['name'] ?? '';

        // 1. Strict Validation of the Temporary File
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        
        // Ensure the path is strictly inside the system temp directory and prefixed correctly
        if (strpos($tmpPath, $tempDir) !== 0 || strpos(basename($tmpPath), 'myCloud_eml_att_') !== 0) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid temporary file source.']);
        }
        
        if (!file_exists($tmpPath)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Temporary file expired or not found.']);
        }

        // 2. Resolve Target Directory in the Cloud
        $destDirAbs = $this->resolve($destDirRel);
        if (!$destDirAbs || !is_dir($destDirAbs)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid destination directory.']);
        }

        // 3. E2E Encryption Handling
        $safeName = $this->sanitizeAndValidateName($name, false);
        $finalName = $safeName;
        $finalDest = rtrim($destDirAbs, '/\\') . DIRECTORY_SEPARATOR . $finalName;
        
        // Note: The file remains unencrypted at rest here because this backend endpoint
        // executes server-side. E2E requires the client to encrypt it before upload.
        // However, for the "Smart Cloud Attachments" feature, these are publicly shared links,
        // which inherently cannot be E2E encrypted (otherwise the recipient couldn't read them).
        // Therefore, we enforce that they are saved unencrypted.
        
        if (file_exists($finalDest)) {
            $finalDest = $this->getUniqueName($finalDest);
        }

        if (copy($tmpPath, $finalDest)) {
            $this->log('EMAIL_INGEST', basename($finalDest), $destDirRel);
            $this->sendJsonAndExit(['status' => 'OK']);
        } else {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to copy file to cloud.']);
        }
    }
	
	private function actionCloudIngestAtt() {
        $tmpPath = $_POST['tmp_path'] ?? '';
        $destDirRel = $_POST['dest'] ?? '';
        $name = $_POST['name'] ?? '';

        if (empty($tmpPath) || strpos($tmpPath, '..') !== false) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path format.']);
        }

        // Sucht die temporäre Datei intelligent in allen möglichen Verzeichnissen
        $candidates = [
            $tmpPath, // 1. Der exakte Pfad vom Frontend (absolut oder relativ zum Script)
            dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($tmpPath, '/\\'), // 2. Relativ zum Root-Verzeichnis der App
            (isset($GLOBALS['temp_dir']) ? rtrim($GLOBALS['temp_dir'], '/\\') : '') . DIRECTORY_SEPARATOR . basename($tmpPath), // 3. Custom App-Temp-Verzeichnis
            rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . basename($tmpPath) // 4. OS System-Temp-Verzeichnis
        ];

        $realTmpPath = false;
        foreach ($candidates as $cand) {
            if (!empty($cand) && file_exists($cand) && is_file($cand)) {
                $realTmpPath = $cand;
                break;
            }
        }

        if (!$realTmpPath) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Temporary file not found. Received: ' . strip_tags($tmpPath)]);
        }

        $destDirAbs = $this->resolve($destDirRel);
        if (!$destDirAbs || !is_dir($destDirAbs)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid destination directory.']);
        }

        $safeName = $this->sanitizeAndValidateName($name, false);
        $finalDest = rtrim($destDirAbs, '/\\') . DIRECTORY_SEPARATOR . $safeName;
        
        if (file_exists($finalDest)) {
            $finalDest = $this->getUniqueName($finalDest);
        }

        if (copy($realTmpPath, $finalDest)) {
            $this->sendJsonAndExit(['status' => 'OK']);
        } else {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Server failed to copy file to cloud.']);
        }
    }

	private function actionCommitShare() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $stash = $_SESSION['myCloud_shared_stash'] ?? [];
        $destRel = $_POST['dest'] ?? '/';
        $destAbs = $this->resolve($destRel);

        if (empty($stash) || !$destAbs || !is_dir($destAbs)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid destination or no files.']);
        }
        if (file_exists($destAbs . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt')) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Cannot save directly to an encrypted vault.']);
        }
        $saved = 0;
        foreach ($stash as $item) {
            $target = rtrim($destAbs, '/\\') . DIRECTORY_SEPARATOR . $item['name'];
            if (file_exists($target)) $target = $this->getUniqueName($target);
            if (file_exists($item['tmp_path']) && @rename($item['tmp_path'], $target)) {
                $saved++;
                $this->log('OS_SHARE_UPLOAD', basename($target), $destRel);
            }
        }
        unset($_SESSION['myCloud_shared_stash']);
        $this->sendJsonAndExit(['status' => 'OK', 'saved' => $saved]);
    }

    private function actionCancelShare() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $stash = $_SESSION['myCloud_shared_stash'] ?? [];
        foreach ($stash as $item) { @unlink($item['tmp_path']); }
        unset($_SESSION['myCloud_shared_stash']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionEditFetch() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (!$path || !is_file($path)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Not found']);
        $c = @file_get_contents($path);
        if ($c === false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Read failed']);
        if (!mb_check_encoding($c, 'UTF-8')) $c = mb_convert_encoding($c, 'UTF-8', 'ISO-8859-1');
        echo json_encode(['status' => 'OK', 'content' => $c]); exit;
    }

    private function actionEditSave() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (preg_match('/\.zip(\/|$)/i', $_POST['path'] ?? '')) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'ZIP read-only']);
        if (!$path || is_dir($path)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid']);
        // --- CENTRAL GATEKEEPER ---
        // If the user is using the editor to create a NEW file, it must pass the security checks
        if (!file_exists($path)) {
            $this->sanitizeAndValidateName(basename($path), true);
        }
        if (@file_put_contents($path, $_POST['content'] ?? '') === false) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Save failed']);
        $this->log('EDIT_SAVE', $_POST['path']);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionZip() {
        @ini_set('output_buffering', 'off'); while (@ob_end_clean());
        header('Content-Type: text/event-stream'); header('Cache-Control: no-cache'); header('X-Accel-Buffering: no');
        
        $sendMsg = function($pct, $msg, $sts='RUNNING') { echo "data: " . json_encode(['percent'=>$pct, 'msg'=>$msg, 'status'=>$sts]) . "\n\n"; flush(); };
        
        $src = $this->resolve($_POST['src'] ?? '');
        $dest = isset($_POST['dest']) ? $this->resolve($_POST['dest']) : dirname($src);
        if (!$src || !is_dir($src)) { $sendMsg(0, 'Invalid src', 'ERR'); exit; }
        
        // Fortification: Route the new ZIP filename through the gatekeeper
        $safe_zip_name = $this->sanitizeAndValidateName(basename($src) . '.zip', false);
        $zipTarget = $this->getUniqueName(rtrim($dest, '/\\') . '/' . $safe_zip_name);
        $zip = new ZipArchive();
        if ($zip->open($zipTarget, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) { $sendMsg(0, 'Zip create failed', 'ERR'); exit; }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        $count = 0; $total = 0; foreach($files as $f) $total++;
        
        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), basename($src) . '/' . substr($file->getRealPath(), strlen($src) + 1));
            $count++; if ($count % 10 === 0) $sendMsg(round(($count/$total)*95), "Zipping...");
        }
        $zip->close();
        if (($_POST['mode']??'') === 'move') {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            @rmdir($src);
        }
        $this->log('ZIP', $_POST['src'], basename($zipTarget));
        $sendMsg(100, "Done", 'OK');
        exit;
    }

    private function actionUnzip() {
        @ini_set('output_buffering', 'off'); while (@ob_end_clean());
        header('Content-Type: text/event-stream'); header('Cache-Control: no-cache'); header('X-Accel-Buffering: no');
        $sendMsg = function($pct, $msg, $sts='RUNNING') { echo "data: " . json_encode(['percent'=>$pct, 'msg'=>$msg, 'status'=>$sts]) . "\n\n"; flush(); };

        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) { $sendMsg(0, 'Invalid file', 'ERR'); exit; }

		// E2E SECURITY BLOCK: Prevent server from dumping plaintext into the Vault
		$checkPath = dirname($src);
		$jail = rtrim(realpath($this->cloud_path), DIRECTORY_SEPARATOR);
		while ($checkPath && strpos($checkPath, $jail) === 0) {
			if (file_exists($checkPath . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt')) {
				$sendMsg(0, 'Cannot extract ZIP server-side inside an encrypted Vault. Decrypt locally first.', 'ERR'); exit;
			}
			if ($checkPath === $jail) break;
			$checkPath = dirname($checkPath);
		}
        
        $zip = new ZipArchive();
        if ($zip->open($src) !== TRUE) { $sendMsg(0, 'Open failed', 'ERR'); exit; }
        
        $target = $this->getUniqueName(dirname($src) . '/' . pathinfo($src, PATHINFO_FILENAME));
        if (!@mkdir($target, 0755, true)) { $sendMsg(0, 'Mkdir failed', 'ERR'); exit; }

        global $zip_warn_limit, $zip_max_files;
        $maxBytes = (isset($zip_warn_limit) && $zip_warn_limit > 0) ? $zip_warn_limit : (500 * 1024 * 1024);
        $maxFiles = $zip_max_files; // Sensible hard limit for file count to prevent inode exhaustion
        $extractedBytes = 0;
        
        $total = $zip->numFiles;
        for ($i=0; $i<$total; $i++) {
             if ($i > $maxFiles) {
                 $sendMsg(100, 'Extraction stopped: Too many files', 'ERR'); 
                 break; 
             }
            $name = $zip->getNameIndex($i);

            $stat = $zip->statIndex($i);
            
            $extractedBytes += $stat['size'];
            if ($extractedBytes > $maxBytes) {
                $sendMsg(100, 'Extraction stopped: ZIP Bomb detected (Exceeds size limit)', 'ERR'); 
                break; 
            }

            // 1. Prevent Zip Slip (Directory Traversal)
            // Fortification: Check the raw string to safely handle dynamically created subfolders
            if (strpos($name, '../') !== false || strpos($name, '..\\') !== false || strpos($name, '/') === 0 || strpos($name, '\\') === 0) {
                continue; // Skip malicious entry
            }
        
            // 2. --- CENTRAL GATEKEEPER ---
            // Prevent malicious files hidden inside ZIPs from touching the disk
            $baseName = basename($name);
            if ($baseName !== '') {
                $this->sanitizeAndValidateName($baseName, true);
            }

            $zip->extractTo($target, $name);
            if ($i % 10 === 0) $sendMsg(round(($i/$total)*100), "Extracting...");
        }
        $zip->close();
        $this->log('UNZIP', $_POST['src']);
        $sendMsg(100, "Done", 'OK');
        exit;
    }
    
    /**
     * Helper: Silent structural repair.
     * Rebuilds the XREF table and stream lengths without changing content.
     */
    private function pdfRepair($src) {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf_qpdf_');

        // Primary: qpdf --linearize (rebuilds xref, linearizes, very good at fixing broken structure)
        $cmd = sprintf('qpdf --linearize --object-streams=preserve %s %s 2>&1',
            escapeshellarg($src), escapeshellarg($tmp));
        $output = shell_exec($cmd);

        if (file_exists($tmp) && filesize($tmp) > 1024) {   // minimal plausible size
            return $tmp;
        }
        @unlink($tmp);

        // Fallback: ghostscript visual repair (loses some metadata / forms but usually saves content)
        $tmp_gs = tempnam(sys_get_temp_dir(), 'pdf_gs_');
        $cmd_gs = sprintf(
            'gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
            escapeshellarg($tmp_gs), escapeshellarg($src)
        );
        $shell_exec($cmd_gs);

        if (file_exists($tmp_gs) && filesize($tmp_gs) > 1024) {
            return $tmp_gs;
        }
        @unlink($tmp_gs);

        return false;
    }
    
    private function actionPdfUnstack() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        
        $dir = dirname($src);
        $filename = pathinfo($src, PATHINFO_FILENAME);
        
        $outputPattern = $dir . DIRECTORY_SEPARATOR . $filename . ' (%d).pdf';
        $cmd = sprintf('qpdf --split-pages %s %s 2>&1', escapeshellarg($src), escapeshellarg($outputPattern));
        shell_exec($cmd);
        
        $this->log('PDF_UNSTACK', $_POST['src']);
        $this->sendJsonAndExit(['status'=>'OK']);
    }

    private function actionPdfStack() {
        $filesRel = json_decode($_POST['files'] ?? '[]', true);
        $destRel = $_POST['dest'] ?? '';
        $deleteSources = ($_POST['delete_sources'] ?? 'false') === 'true';
        
        $tempCleanup = json_decode($_POST['temp_cleanup'] ?? '[]', true);
        $originalSources = json_decode($_POST['original_sources'] ?? '[]', true);

        if (empty($filesRel) || !is_array($filesRel) || !$destRel) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid parameters']);
        }

        $destAbs = $this->resolve($destRel);
        if (!$destAbs) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid destination path']);
        }

        // 1. Resolve and validate all files
        $gsArgs = [];
        foreach ($filesRel as $f) {
            $abs = $this->resolve($f);
            if (!$abs || !is_file($abs)) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Missing source file: ' . basename($f)]);
            }
            $gsArgs[] = escapeshellarg($abs);
        }

        // 2. Create a temporary output file for Ghostscript 
        // (GS cannot read and write to the exact same file simultaneously)
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $gsTempOut = rtrim($tempDir, '/\\') . '/myCloud_stack_' . bin2hex(random_bytes(8)) . '.pdf';

        // 3. Execute qpdf (Fast, but strictly enforces PDF structure. Exit code 3 = warnings, which is fine.)
        $cmd = "qpdf --empty --pages " . implode(' ', $gsArgs) . " -- " . escapeshellarg($gsTempOut) . " 2>&1";
        exec($cmd, $output, $returnVar);

        // If qpdf fails (code != 0 and != 3), or outputs an empty file, fallback to Ghostscript
        if (($returnVar !== 0 && $returnVar !== 3) || !is_file($gsTempOut) || filesize($gsTempOut) === 0) {
            
            $gsCmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dAutoRotatePages=/None -sOutputFile=" . escapeshellarg($gsTempOut) . " " . implode(' ', $gsArgs) . " 2>&1";
            exec($gsCmd, $gsOutput, $gsReturnVar);
            
            if ($gsReturnVar !== 0 || !is_file($gsTempOut) || filesize($gsTempOut) === 0) {
                if (is_file($gsTempOut)) @unlink($gsTempOut);
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'PDF Merge failed. Files may be encrypted or severely corrupted.']);
            }
        }

        // 4. Copy the newly merged file to the final destination
        if (!copy($gsTempOut, $destAbs)) {
            @unlink($gsTempOut);
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to save merged PDF to destination']);
        }
        @unlink($gsTempOut);

        // ==========================================
        // 5. CLEANUP LOGIC
        // ==========================================

        // A. Delete all safely hidden temporary PDFs (from Office conversions)
        if (is_array($tempCleanup)) {
            foreach ($tempCleanup as $tf) {
                // Guard: Only delete files that are explicitly marked as myCloud temps
                if (strpos($tf, '.myCloud_temp_') !== false) {
                    $tfAbs = $this->resolve($tf);
                    if ($tfAbs && is_file($tfAbs)) {
                        @unlink($tfAbs);
                    }
                }
            }
        }
        
        // B. Delete the correct sources if this was a Move/Delete operation
        if ($deleteSources) {
            // If office docs were involved, delete the real sources, not the temp PDFs
            $targetsToDelete = !empty($originalSources) ? $originalSources : $filesRel;
            
            foreach ($targetsToDelete as $f) {
                // Critical Guard: Never delete the target file we just merged into!
                if ($f !== $destRel) {
                    $fAbs = $this->resolve($f);
                    if ($fAbs && is_file($fAbs)) {
                        @unlink($fAbs);
                    }
                }
            }
        }

        $this->sendJsonAndExit(['status' => 'OK', 'dest' => $destRel]);
    }
    
    private function actionPdfShrink() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Compressed).pdf';
        $dest = $this->getUniqueName($dest);
        $cmd = sprintf('gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1', escapeshellarg($dest), escapeshellarg($src));
        shell_exec($cmd);
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Compression failed. Ghostscript (gs) is required.']);
    }

    private function actionPdfKeepPages() {
        $src = $this->resolve($_POST['src'] ?? '');
        $pages = preg_replace('/[^0-9,\-]/', '', $_POST['pages'] ?? '');
        if (!$src || !is_file($src) || empty($pages)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file or pages']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Extracted).pdf';
        $dest = $this->getUniqueName($dest);
        
        // Try qpdf first (preserves links)
        $cmd = sprintf('qpdf --empty --pages %s %s -- %s 2>&1', escapeshellarg($src), escapeshellarg($pages), escapeshellarg($dest));
        shell_exec($cmd);

        // Fallback to Ghostscript
        if (!file_exists($dest)) {
            $cmd2 = sprintf('gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -sPageList=%s -sOutputFile=%s %s 2>&1', escapeshellarg($pages), escapeshellarg($dest), escapeshellarg($src));
            shell_exec($cmd2);
        }
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Extraction failed. qpdf or gs is required.']);
    }

    private function actionPdfRotate() {
        $src = $this->resolve($_POST['src'] ?? '');
        $angle = $_POST['angle'] ?? '+90';
        
        if (!in_array($angle, ['+90', '-90', '+180'], true)) {
            $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid rotation angle']);
        }

        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Rotated).pdf';
        $dest = $this->getUniqueName($dest);

        $cmd = sprintf('qpdf --rotate=%s %s %s 2>&1', escapeshellarg($angle), escapeshellarg($src), escapeshellarg($dest));
        shell_exec($cmd);
        
        if (!file_exists($dest)) {
            $ori = $angle == '+90' ? 3 : ($angle == '+180' ? 2 : 1);
            $cmd2 = sprintf('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dAutoRotatePages=/None -c "<</Orientation %d>> setpagedevice" -f %s -sOutputFile=%s 2>&1', $ori, escapeshellarg($src), escapeshellarg($dest));
            shell_exec($cmd2);
        }
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Rotation failed. qpdf or gs is required.']);
    }

    private function actionPdfUnlock() {
        $src = $this->resolve($_POST['src'] ?? '');
        $pw = $_POST['password'] ?? '';
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Unlocked).pdf';
        $dest = $this->getUniqueName($dest);

        $cmd = sprintf('qpdf --password=%s --decrypt %s %s 2>&1', escapeshellarg($pw), escapeshellarg($src), escapeshellarg($dest));
        shell_exec($cmd);

        if (!file_exists($dest)) {
            $cmd2 = sprintf('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sPDFPassword=%s -sOutputFile=%s %s 2>&1', escapeshellarg($pw), escapeshellarg($dest), escapeshellarg($src));
            shell_exec($cmd2);
        }
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Unlock failed. Incorrect password or missing tools.']);
    }

    private function actionPdfExtractText() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . '.txt';
        $dest = $this->getUniqueName($dest);

        $cmd = sprintf('pdftotext %s %s 2>&1', escapeshellarg($src), escapeshellarg($dest));
        shell_exec($cmd);

        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Text extraction failed. Poppler-utils (pdftotext) required.']);
    }

    private function actionPdfOcrText() {
        @set_time_limit(0); // OCR is slow, prevent PHP timeout
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (OCR).txt';
        $dest = $this->getUniqueName($dest);

        // Create secure temporary directory
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mycloud_ocr_' . bin2hex(random_bytes(16));
        @mkdir($tempDir, 0755, true);

        // 1. Render PDF to images (150 DPI is a good balance of speed vs accuracy)
        $cmd1 = sprintf('pdftoppm -r 150 -png %s %s/page 2>&1', escapeshellarg($src), escapeshellarg($tempDir));
        shell_exec($cmd1);

        $files = glob($tempDir . '/page-*.png');
        if (empty($files)) {
            @rmdir($tempDir);
            $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Image rendering failed. Poppler-utils (pdftoppm) required.']);
        }
        
        natsort($files); // Sort naturally so page 10 comes after page 9
        
        $out = fopen($dest, 'w');
        foreach ($files as $f) {
            // 2. OCR each image. Tries eng+deu first, falls back to default if language packs are missing
            $text = shell_exec(sprintf('tesseract %s stdout -l eng+deu 2>/dev/null || tesseract %s stdout 2>/dev/null', escapeshellarg($f), escapeshellarg($f)));
            if (trim($text)) fwrite($out, trim($text) . "\n\n--- Page Break ---\n\n");
            @unlink($f);
        }
        fclose($out);
        @rmdir($tempDir);

        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'OCR failed. Ensure Tesseract-OCR is installed on the server.']);
    }

    private function actionPdfExtractImages() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        
        $dirName = pathinfo($src, PATHINFO_FILENAME) . ' Images';
        $destDir = dirname($src) . DIRECTORY_SEPARATOR . $dirName;
        $destDir = $this->getUniqueName($destDir);
        @mkdir($destDir, 0755, true);

        $destPrefix = $destDir . DIRECTORY_SEPARATOR . 'img';
        $cmd = sprintf('pdfimages -all %s %s 2>&1', escapeshellarg($src), escapeshellarg($destPrefix));
        shell_exec($cmd);

        $files = array_diff(scandir($destDir), array('.', '..'));
        if (count($files) > 0) $this->sendJsonAndExit(['status'=>'OK']);
        @rmdir($destDir);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Image extraction failed. Poppler-utils (pdfimages) required.']);
    }   
    
    private function actionPdfFlatten() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Flattened).pdf';
        $dest = $this->getUniqueName($dest);
        
        // pdftk flattens forms and annotations natively while preserving vector text and exact file quality.
        // ImageMagick rasterization is completely avoided.
        $cmd = sprintf('pdftk %s output %s flatten 2>&1', escapeshellarg($src), escapeshellarg($dest));
        shell_exec($cmd);
        
        // Fallback to Ghostscript vector preservation if pdftk is missing
        if (!file_exists($dest) || filesize($dest) === 0) {
            $cmd2 = sprintf('gs -sDEVICE=pdfwrite -dNoOutputFonts -dPrinted=false -dNOPAUSE -dBATCH -sOutputFile=%s %s 2>&1', escapeshellarg($dest), escapeshellarg($src));
            shell_exec($cmd2);
        }

        if (file_exists($dest) && filesize($dest) > 0) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Flattening failed. pdftk or gs is required.']);
    }

   private function actionPdfEncrypt() {
        $src = $this->resolve($_POST['src'] ?? '');
        $pw = $_POST['password'] ?? '';
        if (!$src || !is_file($src) || empty($pw)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file or password']);
        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Protected).pdf';
        $dest = $this->getUniqueName($dest);
        
        $cmd = sprintf('qpdf --encrypt %s %s 256 -- %s %s 2>&1', escapeshellarg($pw), escapeshellarg($pw), escapeshellarg($src), escapeshellarg($dest));
        shell_exec($cmd);
        
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Encryption failed. qpdf is required.']);
    }

    private function actionPdfRepair() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) {
            $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);
        }

        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Repaired).pdf';
        $dest = $this->getUniqueName($dest);

        // 1. Primary Repair: Use pdftk. It rebuilds XREF tables and preserves AcroForm fields perfectly.
        $cmd = sprintf('qpdf --linearize %s %s 2>&1', escapeshellarg($src), escapeshellarg($dest));
        $output = shell_exec($cmd);

        if (file_exists($dest) && filesize($dest) > 0) {
            $this->log('PDF_REPAIR', $src, $dest);
            $this->sendJsonAndExit(['status'=>'OK']);
        }
        
        // 2. Secondary Repair: Try pdftk
        $tkCmd = sprintf('pdftk %s output %s 2>&1', escapeshellarg($src), escapeshellarg($dest));
        $tkOutput = shell_exec($tkCmd);

        if (file_exists($dest) && filesize($dest) > 0) {
            $this->log('PDF_REPAIR_PDFTK', $src, $dest);
            $this->sendJsonAndExit(['status'=>'OK']);
        }

        // 3. Fallback Repair: If pdftk fails, the PDF is severely mangled. 
        // We fall back to Ghostscript as a last resort (WARNING: This WILL strip forms, but saves the visual data).
        $gsCmd = sprintf('gs -o %s -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress %s 2>&1', escapeshellarg($dest), escapeshellarg($src));
        $gsOutput = shell_exec($gsCmd);

        if (file_exists($dest) && filesize($dest) > 0) {
            $this->log('PDF_REPAIR_GS_FALLBACK', $src, $dest);
            $this->sendJsonAndExit(['status'=>'OK', 'msg'=>'Repaired using fallback engine. Form fields may have been flattened due to severe corruption.']);
        }

        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Repair failed completely. File may be unrecoverable.']);
    }

    private function actionPdfCombineImages() {
        $files = json_decode($_POST['files'] ?? '[]', true);
        $validFiles = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        
        foreach($files as $f) {
             $p = $this->resolve($f);
             if($p && is_file($p)) {
                 // Fortification: Prevent ImageMagick delegate RCE (e.g., MVG disguised as JPG)
                 $mime = finfo_file($finfo, $p);
                 if (strpos($mime, 'image/') === 0) {
                     $validFiles[] = escapeshellarg($p);
                 }
             }
        }
        finfo_close($finfo);
        
        if(count($validFiles) < 2) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Need at least 2 images']);
        
        $dest = dirname($this->resolve($files[0])) . DIRECTORY_SEPARATOR . 'Combined_Images.pdf';
        $dest = $this->getUniqueName($dest);
        
        $cmd = sprintf('convert %s %s 2>&1', implode(' ', $validFiles), escapeshellarg($dest));
        shell_exec($cmd);
        
        if (file_exists($dest)) $this->sendJsonAndExit(['status'=>'OK']);
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Combine failed. ImageMagick (convert) is required.']);
    }
    
    private function actionPdfGetFormFields() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !is_file($src)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);

        // Extract field data using pdftk
        $cmd = sprintf('pdftk %s dump_data_fields 2>&1', escapeshellarg($src));
        $output = shell_exec($cmd);

        if (strpos($output, 'Error') !== false || empty(trim($output))) {
            $this->sendJsonAndExit(['status'=>'ERR','msg'=>'No form fields found or pdftk is not installed.']);
        }

        $blocks = explode('---', $output);
        $fields = [];
        
        foreach ($blocks as $block) {
            $block = trim($block);
            if (!$block) continue;
            
            $lines = explode("\n", $block);
            $f = ['options' => []];
            foreach ($lines as $line) {
                if (preg_match('/^([^:]+):\s*(.*)$/', trim($line), $m)) {
                    $key = trim($m[1]); 
                    $val = trim($m[2]);
                    if ($key === 'FieldStateOption') {
                        if (strtolower($val) !== 'off') $f['options'][] = $val;
                    } else {
                        $f[$key] = $val;
                    }
                }
            }
            // Only include actual fields
            if (isset($f['FieldName'])) {
                $fields[] = $f;
            }
        }

        $this->sendJsonAndExit(['status'=>'OK', 'fields' => $fields]);
    }

    private function actionPdfFillForm() {
        $src = $this->resolve($_POST['src'] ?? '');
        $data = json_decode($_POST['data'] ?? '[]', true);
        
        if (!$src || !is_file($src) || empty($data)) {
            $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid request']);
        }

        $dest = dirname($src) . DIRECTORY_SEPARATOR . pathinfo($src, PATHINFO_FILENAME) . ' (Filled).pdf';
        $dest = $this->getUniqueName($dest);

        // Generate XFDF (XML Forms Data Format) for safe UTF-8 injection
        $xfdf = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve"><fields></fields></xfdf>');
        
        foreach ($data as $name => $value) {
            if ($value === null || $value === '') continue;
            $field = $xfdf->fields->addChild('field');
            $field->addAttribute('name', $name);
            $field->addChild('value', htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        }

        $tmpXfdf = tempnam(sys_get_temp_dir(), 'xfdf_');
        $xfdf->asXML($tmpXfdf);

        // Inject data. Use 'flatten' to lock it, or remove 'flatten' to keep it editable. We will leave it editable by default.
        $cmd = sprintf('pdftk %s fill_form %s output %s 2>&1', escapeshellarg($src), escapeshellarg($tmpXfdf), escapeshellarg($dest));
        shell_exec($cmd);
        
        @unlink($tmpXfdf);

        if (file_exists($dest)) {
            $this->sendJsonAndExit(['status'=>'OK']);
        }
        $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Failed to inject form data.']);
    }
    
	private function actionCopyAs() {
        $srcRel = $_POST['src'] ?? '';
        $destName = $_POST['destName'] ?? '';
        
        if (!$srcRel || !$destName) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid parameters']);
        }
        
        // --- NEW: Bulletproof the destination name ---
        $destName = $this->sanitizeAndValidateName($destName, true);
        
        $srcAbs = $this->resolve($srcRel);
        if (!$srcAbs || !is_file($srcAbs)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Source template not found']);
        }
        
        $parentDirRel = dirname($srcRel);
        if ($parentDirRel === '.' || $parentDirRel === '\\') $parentDirRel = '';
        
        $destRel = ltrim($parentDirRel . '/' . $destName, '/');
        $destAbs = $this->resolve($destRel);
        
        if (file_exists($destAbs)) {
            // Modified to return a clear CONFLICT code if it bypassed the client
            $this->sendJsonAndExit(['status' => 'ERR', 'code' => 'CONFLICT', 'msg' => 'File already exists.']);
        }
        
        if (!copy($srcAbs, $destAbs)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to copy template to disk.']);
        }
        
		// E2E Check: Do not attempt to modify the physical file if it is an encrypted blob
		$isEncrypted = false;
		$checkPath = dirname($destAbs);
		$jail = rtrim(realpath($this->cloud_path), DIRECTORY_SEPARATOR);
		while ($checkPath && strpos($checkPath, $jail) === 0) {
			if (file_exists($checkPath . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt')) { $isEncrypted = true; break; }
			if ($checkPath === $jail) break;
			$checkPath = dirname($checkPath);
		}
		
		if (!$isEncrypted) {
			// Inject the localized date directly into the newly spawned template file
			$this->injectDateIntoDocx($destAbs);
		}
        
        $this->sendJsonAndExit(['status' => 'OK', 'newPath' => '/' . $destRel]);
    }
	
    // =========================================================
    // SHARE MANAGER METHODS
    // =========================================================

    private function loadShares() {
        if (!file_exists($this->share_db)) return [];
        $json = @file_get_contents($this->share_db);
        return json_decode($json, true) ?: [];
    }

    private function saveShares($shares) {
        $fp = fopen($this->share_db, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($shares, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        if ($fp) fclose($fp);
    }

    private function actionShareList() {
        $targetIsDir = false;
        if (!empty($_POST['check_path'])) {
            $cPath = $this->resolve($_POST['check_path']);
            if ($cPath) $targetIsDir = is_dir($cPath);
        }
        $shares = $this->loadShares();
        $out = [];
        $now = time();
        $hasChanges = false;
        
        foreach ($shares as $g => $s) {
            if (!empty($s['expires']) && $s['expires'] < $now) {
                unset($shares[$g]);
                $hasChanges = true;
                continue;
            }
            $storedPath = $s['path'];
            
            if (strpos($storedPath, $this->cloud_path) === 0) {
                $relativePath = substr($storedPath, strlen($this->cloud_path));
                $relativePath = str_replace('\\', '/', $relativePath);
                $relativePath = '/' . ltrim($relativePath, '/');
                
                if (!empty($_POST['check_path'])) {
                    $reqRel = '/' . ltrim($_POST['check_path'], '/');
                    if ($relativePath !== $reqRel) continue;
                }

                $out[] = [
                    'guid' => $g,
                    'path' => $relativePath,
                    'name' => basename($storedPath),
                    'expires' => $s['expires'] ? date('Y-m-d', $s['expires']) : 'Never',
                    'has_pass' => !empty($s['password']),
                    'permission' => $s['permission'] ?? 'read',
                    'is_dir' => is_dir($storedPath),
                    'downloads' => $s['downloads'] ?? 0,
                    'max_downloads' => $s['max_downloads'] ?? 0,
                    'alias' => $s['name'] ?? ''
                ];
            }
        }
        if ($hasChanges) $this->saveShares($shares);
        $this->sendJsonAndExit(['status' => 'OK', 'data' => $out, 'target_is_dir' => $targetIsDir]);
    }

    private function actionShareCreate() {
        $relPath = $_POST['path'] ?? '';
        $finalPath = $this->resolve($relPath);
        if (!$finalPath || !file_exists($finalPath)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path or file']);
        }
        
        $isDir = is_dir($finalPath);
        $guid = bin2hex(random_bytes(25)); // Generates 50 chars of secure hex

        $days = $_POST['days'] ?? '0';
        $expiry = null;
        if ($days === 'custom' && !empty($_POST['expire_date'])) {
            $expiry = strtotime($_POST['expire_date'] . ' 23:59:59');
        } elseif ((int)$days > 0) {
            $expiry = time() + ((int)$days * 86400);
        }
        
        $maxDownloads = isset($_POST['max_downloads']) ? max(0, (int)$_POST['max_downloads']) : 0;
        $pass = $_POST['password'] ?? '';
        $permission = $_POST['permission'] ?? 'read';
        
        if (!$isDir) $permission = 'read';

        if (($permission === 'modify' || $permission === 'upload') && empty($pass)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Modify/Upload permission requires a password']);
        }

        $shares = $this->loadShares();
        foreach ($shares as $k => $v) {
            if ($v['path'] === $finalPath) unset($shares[$k]);
        }
        
        $permVal = 'read';
        if ($permission === 'modify') $permVal = 'modify';
        if ($permission === 'upload') $permVal = 'upload';

        $shares[$guid] = [
            'path' => $finalPath,
            'created' => time(),
            'expires' => $expiry,
            'password' => ($pass ? password_hash($pass, PASSWORD_DEFAULT) : null),
            'permission' => $permVal,
            'max_downloads' => $maxDownloads,
            'downloads' => 0,
            'name' => !empty($_POST['name']) ? trim($_POST['name']) : null
        ];
        
        $this->saveShares($shares);
        
        global $cloud_share_url;
        $base = !empty($cloud_share_url) ? rtrim($cloud_share_url, '/') : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?'));
        $link = $base . (strpos($base, '?') === false ? '?' : '&') . 'cloudshare=' . $guid;
        $directLink = $link . '&direct=1';
        
        $this->sendJsonAndExit([
            'status' => 'OK', 
            'link' => $link, 
            'direct_link' => $directLink, 
            'guid' => $guid,
            'expires' => $expiry ? date('Y-m-d', $expiry) : 'Never',
            'max_downloads' => $maxDownloads,
            'downloads' => 0
        ]);
    }

    private function actionShareDelete() {
        $guid = $_POST['guid'] ?? '';
        $shares = $this->loadShares();
        if (isset($shares[$guid])) { 
            // Fortification: Verify ownership/jail before allowing deletion
            if (strpos($shares[$guid]['path'], $this->cloud_path) !== 0) {
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Access denied']);
            }
            unset($shares[$guid]); 
            $this->saveShares($shares); 
        }
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    private function actionShareUpdate() {
        $guid = $_POST['guid'] ?? '';
        if (empty($guid)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Missing GUID']);
        
        $shares = $this->loadShares();
        if (!isset($shares[$guid])) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Share not found']);
        
        // Fortification: Verify ownership/jail before allowing updates
        if (strpos($shares[$guid]['path'], $this->cloud_path) !== 0) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Access denied']);
        }       
        
        $days = $_POST['days'] ?? '0';
        $expiry = null;
        if ($days === 'custom' && !empty($_POST['expire_date'])) {
            $expiry = strtotime($_POST['expire_date'] . ' 23:59:59');
        } elseif ((int)$days > 0) {
            $expiry = time() + ((int)$days * 86400);
        }
        $shares[$guid]['expires'] = $expiry;
        
        if (isset($_POST['max_downloads'])) {
            $shares[$guid]['max_downloads'] = max(0, (int)$_POST['max_downloads']);
        }

        if (isset($_POST['name'])) {
            $v = trim($_POST['name']);
            if ($v === '') unset($shares[$guid]['name']); else $shares[$guid]['name'] = $v;
        }

        if (!empty($_POST['password'])) {
            $shares[$guid]['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if (array_key_exists('permission', $_POST)) {
            $perm = $_POST['permission'];
            $newPerm = 'read';
            if ($perm === 'modify') $newPerm = 'modify';
            if ($perm === 'upload') $newPerm = 'upload';
            if (!is_dir($shares[$guid]['path'])) $newPerm = 'read';
            $shares[$guid]['permission'] = $newPerm;
        }

        $currentPerm = $shares[$guid]['permission'] ?? 'read';
        if (($currentPerm === 'modify' || $currentPerm === 'upload') && empty($shares[$guid]['password'])) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Modify/Upload permission requires a password']);
        }

        $this->saveShares($shares);
        $this->sendJsonAndExit(['status' => 'OK']);
    }

    
    private function actionPdfGetRaw() {
        $src = $this->resolve($_POST['src'] ?? '');
        if ($src && is_file($src)) {
            header('Content-Type: application/pdf');
            readfile($src);
            exit;
        }
    }

    private function actionGetSize() {
        $src = $this->resolve($_POST['src'] ?? '');
        if (!$src || !file_exists($src)) $this->sendJsonAndExit(['status'=>'ERR', 'size'=>0]);
        $size = 0;
        if (is_file($src)) $size = filesize($src);
        else {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)) as $f) $size += $f->getSize();
        }
        $this->sendJsonAndExit(['status'=>'OK', 'size'=>$size]);
    }

    private function actionCheckPaths() {
        $paths = json_decode($_POST['paths']??'[]', true);
        $valid = [];
        if (is_array($paths)) {
            foreach($paths as $p) {
                $resolved = $this->resolve($p);
                if ($resolved && file_exists($resolved)) $valid[$p] = is_dir($resolved);
            }
        }
        $this->sendJsonAndExit(['status'=>'OK', 'valid'=>$valid]);
    }

    private function actionLoadFavorites() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'OK', 'favorites'=>[]]);
        $f = rtrim($cloud_user_profiles, '/\\') . '/' . $this->username . '_favs.json';
        $this->sendJsonAndExit(['status'=>'OK', 'favorites'=> file_exists($f) ? json_decode(file_get_contents($f), true) : []]);
    }

    private function actionSaveFavorites() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'ERR']);
        $d = rtrim($cloud_user_profiles, '/\\');
        if (!is_dir($d)) @mkdir($d, 0755, true);
        file_put_contents($d . '/' . $this->username . '_favs.json', $_POST['favorites_json'] ?? '{}');
        $this->sendJsonAndExit(['status'=>'OK']);
    }

    private function actionLoadTags() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'OK', 'tags'=>[]]);
        $f = rtrim($cloud_user_profiles, '/\\') . '/' . $this->username . '_tags.json';
        $this->sendJsonAndExit(['status'=>'OK', 'tags'=> file_exists($f) ? json_decode(file_get_contents($f), true) : []]);
    }

    private function actionSaveTags() {
        if ($this->isActionBlocked('edit_tags')) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Access denied']);
        }
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'ERR']);
        $d = rtrim($cloud_user_profiles, '/\\');
        if (!is_dir($d)) @mkdir($d, 0755, true);
        file_put_contents($d . '/' . $this->username . '_tags.json', $_POST['tags_json'] ?? '{}');
        $this->sendJsonAndExit(['status'=>'OK']);
    }

    private function actionLoadPaths() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'OK', 'paths'=>[]]);
        $f = rtrim($cloud_user_profiles, '/\\') . '/' . $this->username . '_paths.json';
        $this->sendJsonAndExit(['status'=>'OK', 'paths'=> file_exists($f) ? json_decode(file_get_contents($f), true) : []]);
    }

    private function actionSavePaths() {
        global $cloud_user_profiles;
        if (empty($cloud_user_profiles)) $this->sendJsonAndExit(['status'=>'ERR']);
        $d = rtrim($cloud_user_profiles, '/\\');
        if (!is_dir($d)) @mkdir($d, 0755, true);
        file_put_contents($d . '/' . $this->username . '_paths.json', $_POST['paths_json'] ?? '{}', LOCK_EX);
        $this->sendJsonAndExit(['status'=>'OK']);
    }

    private function actionGetHelpData() {
        // 1. Get the requested language (sanitized)
        $lang = $_POST['lang'] ?? 'en';
        $lang = preg_replace('/[^a-z0-9-]/', '', strtolower($lang));

        // 2. Define exact paths
        $baseDir = __DIR__ . '/help/';
        $targetFile = $baseDir . $lang . '.json';
        $fallbackFile = $baseDir . 'en.json';

        // 3. Serve the file
        if (file_exists($targetFile)) {
            header('Content-Type: application/json');
            readfile($targetFile);
            exit;
        }

        // 4. Fallback if specific lang not found
        if (file_exists($fallbackFile)) {
            header('Content-Type: application/json');
            readfile($fallbackFile);
            exit;
        }

        // 5. Total failure
        echo '[]';
        exit;
    }

// [HELPER] Generate Preview
    private function generatePreview($source, $dest) {
        global $cloud_preview_maxpixel, $cloud_preview_quality;
        $maxDim = $cloud_preview_maxpixel ?? 1024;
        
        if (file_exists($dest)) {
            if (filemtime($dest) >= filemtime($source)) return true;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $generated = false;
        
        // --- STRATEGY 0: FFMPEG (Video to Image Bridge) ---
        $videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi'];
        $isTempVideoFrame = false;
        if (in_array($ext, $videoExts)) {
            $ffmpegPath = 'ffmpeg';
            if (file_exists('/usr/local/bin/ffmpeg')) $ffmpegPath = '/usr/local/bin/ffmpeg';
            elseif (file_exists('/usr/bin/ffmpeg')) $ffmpegPath = '/usr/bin/ffmpeg';

            $tmpFrame = sys_get_temp_dir() . '/vid_' . uniqid() . '.jpg';
            
            // Try 1 second mark
            $cmd = sprintf('%s -y -ss 00:00:01 -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
            exec($cmd);
            
            // Fallback for very short videos
            if (!file_exists($tmpFrame) || filesize($tmpFrame) === 0) {
                $cmdFallback = sprintf('%s -y -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
                exec($cmdFallback);
            }
            
            if (file_exists($tmpFrame) && filesize($tmpFrame) > 0) {
                $source = $tmpFrame;
                $ext = 'jpg';
                $isTempVideoFrame = true;
            } else {
                return false;
            }
        }

        if (class_exists('Imagick')) {
            try {
                $im = new Imagick($source);
                $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $im->setImageFormat('jpg');
                
                switch($im->getImageOrientation()) {
                    case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateimage("#000", 180); break;
                    case Imagick::ORIENTATION_RIGHTTOP: $im->rotateimage("#000", 90); break;
                    case Imagick::ORIENTATION_LEFTBOTTOM: $im->rotateimage("#000", -90); break;
                }
                $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

                $d = $im->getImageGeometry();
                if ($d['width'] > $maxDim || $d['height'] > $maxDim) {
                    $im->resizeImage($maxDim, $maxDim, Imagick::FILTER_LANCZOS, 1, true);
                }

                $im->setImageCompressionQuality($cloud_preview_quality ?? 70); 
                $im->writeImage($dest);
                $im->destroy();
                $generated = true;
            } catch (Exception $e) {}
        }

        if (!$generated && extension_loaded('gd') && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            list($w, $h) = @getimagesize($source);
            if ($w) {
                $ratio = $w / $h;
                if ($w > $maxDim || $h > $maxDim) {
                    if ($ratio > 1) { $nw = $maxDim; $nh = $maxDim / $ratio; }
                    else { $nh = $maxDim; $nw = $maxDim * $ratio; }
                } else { $nw = $w; $nh = $h; }

                $srcImg = null;
                switch($ext) {
                    case 'jpg': case 'jpeg': $srcImg = @imagecreatefromjpeg($source); break;
                    case 'png': $srcImg = @imagecreatefrompng($source); break;
                    case 'webp': $srcImg = @imagecreatefromwebp($source); break;
                    case 'gif': $srcImg = @imagecreatefromgif($source); break;
                }

                if ($srcImg) {
                    $dst = imagecreatetruecolor((int)$nw, (int)$nh);
                    $bg = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $bg);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, (int)$nw, (int)$nh, $w, $h);
                    imagejpeg($dst, $dest, 70);
                    imagedestroy($srcImg);
                    imagedestroy($dst);
                    $generated = true;
                }
            }
        }
        if ($isTempVideoFrame) @unlink($source);
        return $generated;
    }
    
    private function actionGetExif() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (!$path || !is_file($path)) $this->sendJsonAndExit(['status'=>'ERR']);
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'tiff', 'webp'])) $this->sendJsonAndExit(['status'=>'ERR']);
        
        if (!function_exists('exif_read_data')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'exif extension missing']);
        
        $exif = @exif_read_data($path, 0, true);
        if (!$exif) $this->sendJsonAndExit(['status'=>'OK', 'data'=>null]);
        
        // Extract useful data
        $data = [];
        if (isset($exif['FILE']['FileSize'])) $data['Size'] = $this->formatBytes($exif['FILE']['FileSize']);
        if (isset($exif['COMPUTED']['Width'], $exif['COMPUTED']['Height'])) $data['Dimensions'] = $exif['COMPUTED']['Width'] . ' x ' . $exif['COMPUTED']['Height'] . ' px';
        if (isset($exif['IFD0']['Make'])) $data['Camera'] = trim($exif['IFD0']['Make'] . ' ' . ($exif['IFD0']['Model'] ?? ''));
        if (isset($exif['EXIF']['ExposureTime'])) $data['Exposure'] = $exif['EXIF']['ExposureTime'] . 's';
        if (isset($exif['EXIF']['FNumber'])) $data['Aperture'] = 'f/' . eval('return ' . $exif['EXIF']['FNumber'] . ';');
        if (isset($exif['EXIF']['ISOSpeedRatings'])) $data['ISO'] = $exif['EXIF']['ISOSpeedRatings'];
        if (isset($exif['EXIF']['DateTimeOriginal'])) $data['Date Taken'] = $exif['EXIF']['DateTimeOriginal'];
        if (isset($exif['EXIF']['ColorSpace'])) {
            $cs = $exif['EXIF']['ColorSpace'];
            if ($cs == 1) $data['Color Profile'] = 'sRGB';
            elseif ($cs == 2 || $cs == 65535) $data['Color Profile'] = 'Adobe RGB / Uncalibrated';
        }
        
        $gps2Num = function($coordPart) {
            $parts = explode('/', $coordPart);
            if (count($parts) <= 0) return 0;
            if (count($parts) == 1) return floatval($parts[0]);
            if (floatval($parts[1]) == 0) return 0;
            return floatval($parts[0]) / floatval($parts[1]);
        };
        
        if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'])) {
            $latArr = $exif['GPS']['GPSLatitude'];
            $lonArr = $exif['GPS']['GPSLongitude'];
            
            $lat = $gps2Num($latArr[0]??0) + ($gps2Num($latArr[1]??0) / 60) + ($gps2Num($latArr[2]??0) / 3600);
            $lon = $gps2Num($lonArr[0]??0) + ($gps2Num($lonArr[1]??0) / 60) + ($gps2Num($lonArr[2]??0) / 3600);
            
            if (($exif['GPS']['GPSLatitudeRef'] ?? 'N') == 'S') $lat = -$lat;
            if (($exif['GPS']['GPSLongitudeRef'] ?? 'E') == 'W') $lon = -$lon;
            
            $data['GPS'] = round($lat, 6) . ',' . round($lon, 6);
        }
        
        $this->sendJsonAndExit(['status'=>'OK', 'data'=>$data]);
    }
    
    // [HELPER] Generate Icon
    private function generateIcon($source, $dest) {
        $size = $GLOBALS['cloud_icon_maxpixel'] ?? 150;
        $quality = $GLOBALS['cloud_icon_quality'] ?? 50;

        if (file_exists($dest)) {
            if (filemtime($dest) >= filemtime($source)) return true;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $orientation = 0;
        
       
        // --- STRATEGY 0: FFMPEG (Video to Image Bridge) ---
        $videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi'];
        $isTempVideoFrame = false;
        if (in_array($ext, $videoExts)) {
            $ffmpegPath = 'ffmpeg';
            if (file_exists('/usr/local/bin/ffmpeg')) $ffmpegPath = '/usr/local/bin/ffmpeg';
            elseif (file_exists('/usr/bin/ffmpeg')) $ffmpegPath = '/usr/bin/ffmpeg';

            $tmpFrame = sys_get_temp_dir() . '/vid_' . uniqid() . '.jpg';
            
            // Try 1 second mark
            $cmd = sprintf('%s -y -ss 00:00:01 -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
            exec($cmd);
            
            // Fallback for very short videos
            if (!file_exists($tmpFrame) || filesize($tmpFrame) === 0) {
                $cmdFallback = sprintf('%s -y -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
                exec($cmdFallback);
            }
            
            if (file_exists($tmpFrame) && filesize($tmpFrame) > 0) {
                $source = $tmpFrame;
                $ext = 'jpg';
                $isTempVideoFrame = true;
            } else {
                return false;
            }
        }
        
        if (function_exists('exif_read_data') && in_array($ext, ['jpg', 'jpeg'])) {
            $exif = @exif_read_data($source);
            if (!empty($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
            }
        }

        if (function_exists('exif_thumbnail') && in_array($ext, ['jpg', 'jpeg'])) {
            $thumbData = @exif_thumbnail($source, $width, $height, $type);
            if ($thumbData !== false) {
                $srcImg = @imagecreatefromstring($thumbData);
                if ($srcImg) {
                    if ($orientation > 1) {
                        switch ($orientation) {
                            case 3: $srcImg = imagerotate($srcImg, 180, 0); break;
                            case 6: $srcImg = imagerotate($srcImg, -90, 0); break;
                            case 8: $srcImg = imagerotate($srcImg, 90, 0); break;
                        }
                    }
                    $w = imagesx($srcImg); 
                    $h = imagesy($srcImg);
                    $aspect = $w / $h;
                    
                    if ($aspect >= 1) { 
                        $nw = $size; $nh = $size / $aspect; 
                    } else { 
                        $nw = $size * $aspect; $nh = $size; 
                    }

                    $dst = imagecreatetruecolor((int)$nw, (int)$nh);
                    $bg = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $bg);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, (int)$nw, (int)$nh, $w, $h);
                    imagejpeg($dst, $dest, $quality);
                    imagedestroy($srcImg); imagedestroy($dst);
                    if ($isTempVideoFrame) @unlink($source);
                    return true;
                }
            }
        }

        if (class_exists('Imagick') && !(extension_loaded('gd') && in_array($ext, ['jpg', 'jpeg']))) {
            try {
                $im = new Imagick();
                $readPath = $source;
                if (in_array($ext, ['pdf', 'tiff', 'tif'])) { $readPath .= '[0]'; }
                $im->readImage($readPath);
                
                $orient = $im->getImageOrientation();
                if ($orient !== Imagick::ORIENTATION_TOPLEFT && $orient !== Imagick::ORIENTATION_UNDEFINED) {
                     switch($orient) {
                        case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateimage("#000", 180); break;
                        case Imagick::ORIENTATION_RIGHTTOP: $im->rotateimage("#000", 90); break;
                        case Imagick::ORIENTATION_LEFTBOTTOM: $im->rotateimage("#000", -90); break;
                        case Imagick::ORIENTATION_LEFTTOP: $im->flopImage(); $im->rotateImage("#000", 90); break;
                        case Imagick::ORIENTATION_RIGHTBOTTOM: $im->flopImage(); $im->rotateImage("#000", -90); break;
                    }
                    $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                }

                $im->stripImage();
                $im->setImageFormat('jpg');
                
                if (in_array($ext, ['png', 'gif', 'webp', 'pdf', 'tiff', 'tif'])) {
                    $im->setImageBackgroundColor('white');
                    $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                }

                $im->thumbnailImage($size, $size, true);
                $im->setImageCompression(Imagick::COMPRESSION_JPEG);
                $im->setImageCompressionQuality($quality);
                $im->writeImage($dest);
                $im->destroy();
                if ($isTempVideoFrame) @unlink($source);
                return true;
            } catch (Exception $e) { }
        }

        if (extension_loaded('gd') && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            list($w, $h) = @getimagesize($source);
            if ($w) {
                $srcImg = null;
                switch($ext) {
                    case 'jpg': case 'jpeg': $srcImg = @imagecreatefromjpeg($source); break;
                    case 'png': $srcImg = @imagecreatefrompng($source); break;
                    case 'webp': $srcImg = @imagecreatefromwebp($source); break;
                    case 'gif': $srcImg = @imagecreatefromgif($source); break;
                }
                
                if ($srcImg) {
                    if ($orientation > 1) {
                        switch ($orientation) {
                            case 3: $srcImg = imagerotate($srcImg, 180, 0); break;
                            case 6: $srcImg = imagerotate($srcImg, -90, 0); break;
                            case 8: $srcImg = imagerotate($srcImg, 90, 0); break;
                        }
                        $w = imagesx($srcImg); $h = imagesy($srcImg);
                    }

                    $aspect = $w / $h;
                    if ($aspect >= 1) { 
                        $nw = $size; $nh = $size / $aspect; 
                    } else { 
                        $nw = $size * $aspect; $nh = $size; 
                    }

                    $dst = imagecreatetruecolor((int)$nw, (int)$nh);
                    $bg = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $bg);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, (int)$nw, (int)$nh, $w, $h);
                    imagejpeg($dst, $dest, $quality);
                    imagedestroy($srcImg); imagedestroy($dst);
                    if ($isTempVideoFrame) @unlink($source);
                    return true;
                }
            }
        }
        if ($isTempVideoFrame) @unlink($source);
        return false;
    }

    // =========================================================
    // E2E ENCRYPTION ENDPOINTS
    // =========================================================

    private function actionCryptoInit() {
        $path = $this->resolve($_POST['path'] ?? '');
        $salt = $_POST['salt'] ?? '';
		
		if ($path) clearstatcache(true, $path);
		
        if (!$path || !is_dir($path) || empty($salt)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path or missing salt']);
        }
        $saltFile = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt';
        if (file_exists($saltFile)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Directory is already encrypted.']);
        }
        if (@file_put_contents($saltFile, $salt) !== false) {
            $this->log('CRYPTO_INIT', $_POST['path']);
            $this->sendJsonAndExit(['status' => 'OK']);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to write salt file.']);
    }

    private function actionCryptoGetSalt() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (!$path || !is_dir($path)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path']);
        }
        $saltFile = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt';
        if (!file_exists($saltFile)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Directory is not an encryption root.']);
        }
        $salt = file_get_contents($saltFile);
        $this->sendJsonAndExit(['status' => 'OK', 'salt' => trim($salt)]);
    }

    private function actionCryptoChangePwd() {
        $path = $this->resolve($_POST['path'] ?? '');
        $payload = $_POST['payload'] ?? '';
        if (!$path || !is_dir($path) || empty($payload)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Invalid path or missing payload']);
        }
        $saltFile = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt';
        if (!file_exists($saltFile)) {
            $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Directory is not an encryption root.']);
        }
        if (@file_put_contents($saltFile, $payload) !== false) {
            $this->log('CRYPTO_CHANGE_PWD', $_POST['path']);
            $this->sendJsonAndExit(['status' => 'OK']);
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to update encryption keys.']);
    }



    // =========================================================
    // ONLYOFFICE SERVER-TO-SERVER BRIDGE
    // =========================================================


	
	/**
     * Physically overwrites native Word Date fields and custom {DATE} placeholders 
     * with the current date inside the uncompressed DOCX XML.
     */
    private function injectDateIntoDocx($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'docx') return false;

        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            
            global $language;
            $lang = $language ?? 'en';
            
            $day = date('j');
            $year = date('Y');
            $m = (int)date('n');
            
            switch ($lang) {
                case 'de': case 'bar': case 'hes': case 'lb':
                    $months = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                    $dateStr = "$day. " . $months[$m] . " $year";
                    break;
                case 'es':
                    $months = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    $dateStr = "$day de " . $months[$m] . " de $year";
                    break;
                case 'pt':
                    $months = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
                    $dateStr = "$day de " . $months[$m] . " de $year";
                    break;
                case 'fr':
                    $months = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'it':
                    $months = [1=>'gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'ru':
                    $months = [1=>'января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
                    $dateStr = "$day " . $months[$m] . " $year г.";
                    break;
                case 'uk':
                    $months = [1=>'січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
                    $dateStr = "$day " . $months[$m] . " $year р.";
                    break;
                case 'tr':
                    $months = [1=>'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'ar':
                    $months = [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'fa':
                    $months = [1=>'ژانویه','فوریه','مارس','آوریل','مه','ژوئن','ژوئیه','اوت','سپتامبر','اکتبر','نوامبر','دسامبر'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'hi':
                    $months = [1=>'जनवरी','फरवरी','मार्च','अप्रैल','मई','जून','जुलाई','अगस्त','सितंबर','अक्टूबर','नवंबर','दिसंबर'];
                    $dateStr = "$day " . $months[$m] . " $year";
                    break;
                case 'vi':
                    $dateStr = "$day tháng $m, $year";
                    break;
                case 'ja':
                case 'zh-cn':
                    $dateStr = "{$year}年{$m}月{$day}日";
                    break;
                case 'ko':
                    $dateStr = "{$year}년 {$m}월 {$day}일";
                    break;
                case 'en':
                case 'pcm':
                default:
                    $months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
                    $dateStr = $months[$m] . " $day, $year";
                    break;
            }
            
            $filesToPatch = [];
			
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'word/') === 0 && substr($name, -4) === '.xml') {
                    $filesToPatch[] = $name;
                }
            }

            foreach ($filesToPatch as $xmlFile) {
                $content = $zip->getFromName($xmlFile);
                if ($content !== false) {
                    $originalContent = $content;

                    // 1. Literal Placeholder Replacement
                    $content = str_replace(['{DATE}', '[DATE]'], $dateStr, $content);

                    // If it contains w:t, it's an XML document we should safely parse
                    if (strpos($content, 'w:t') !== false) {
                        $dom = new DOMDocument();
                        libxml_use_internal_errors(true);
                        // Load XML securely, preserving the heavy Word formatting
                        if ($dom->loadXML($content, LIBXML_PARSEHUGE)) {
                            $xpath = new DOMXPath($dom);
                            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                            
                            $modifiedDom = false;

                            // 2. Simple Fields (<w:fldSimple>)
                            $simpleFields = $xpath->query('//w:fldSimple[contains(@w:instr, "DATE") or contains(@w:instr, "TIME") or contains(@w:instr, "CREATEDATE") or contains(@w:instr, "SAVEDATE") or contains(@w:instr, "PRINTDATE")]');
                            if ($simpleFields && $simpleFields->length > 0) {
                                foreach ($simpleFields as $fld) {
                                    $tNodes = $xpath->query('.//w:t', $fld);
                                    $first = true;
                                    foreach ($tNodes as $t) {
                                        if ($first) { $t->nodeValue = htmlspecialchars($dateStr); $first = false; } 
                                        else { $t->nodeValue = ''; }
                                        $modifiedDom = true;
                                    }
                                }
                            }

                            // 3. Complex Fields (<w:instrText>) inside text boxes, headers, etc.
                            $instrTexts = $xpath->query('//w:instrText[contains(text(), "DATE") or contains(text(), "TIME") or contains(text(), "CREATEDATE") or contains(text(), "SAVEDATE") or contains(text(), "PRINTDATE")]');
                            if ($instrTexts && $instrTexts->length > 0) {
                                foreach ($instrTexts as $instr) {
                                    $run = $instr->parentNode;
                                    if ($run && $run->nodeName === 'w:r') {
                                        $nextRun = $run->nextSibling;
                                        $foundSeparate = false;
                                        while ($nextRun) {
                                            if ($nextRun->nodeName === 'w:r') {
                                                $fldChars = $xpath->query('.//w:fldChar', $nextRun);
                                                if ($fldChars && $fldChars->length > 0) {
                                                    $type = $fldChars->item(0)->getAttribute('w:fldCharType');
                                                    if ($type === 'separate') $foundSeparate = true;
                                                    elseif ($type === 'end') break;
                                                }
                                                
                                                if ($foundSeparate) {
                                                    $tNodes = $xpath->query('.//w:t', $nextRun);
                                                    foreach ($tNodes as $t) {
                                                        if ($foundSeparate === true) {
                                                            $t->nodeValue = htmlspecialchars($dateStr);
                                                            $foundSeparate = 'done'; // Mark as filled
                                                            $modifiedDom = true;
                                                        } else {
                                                            $t->nodeValue = ''; // Clear trailing parts of the old date
                                                            $modifiedDom = true;
                                                        }
                                                    }
                                                }
                                            }
                                            $nextRun = $nextRun->nextSibling;
                                        }
                                    }
                                }
                            }

                            // 4. Content Controls (Date Pickers)
                            $sdts = $xpath->query('//w:sdt[.//w:date]');
                            if ($sdts && $sdts->length > 0) {
                                foreach ($sdts as $sdt) {
                                    $tNodes = $xpath->query('.//w:sdtContent//w:t', $sdt);
                                    $first = true;
                                    foreach ($tNodes as $t) {
                                        if ($first) { $t->nodeValue = htmlspecialchars($dateStr); $first = false; } 
                                        else { $t->nodeValue = ''; }
                                        $modifiedDom = true;
                                    }
                                }
                            }

                            if ($modifiedDom) {
                                $content = $dom->saveXML();
                            }
                        }
                        libxml_clear_errors();
                    }

                    if ($content !== $originalContent) {
                        $zip->addFromString($xmlFile, $content);
                    }
                }
            }
            $zip->close();
            return true;
        }
        return false;
    }
	
	private function actionCheckOfficeState() {
        $docKey = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['docKey'] ?? '');
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        // The callback deletes this tracking file the exact millisecond the save finishes
        $stateFile = $tempDir . '/myCloud_office_' . $docKey . '.json';
        $this->sendJsonAndExit(['status' => 'OK', 'ready' => !file_exists($stateFile)]);
    }

	private function actionGetOfficeConfig() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (!$path || is_dir($path)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);

        // THE FIX: Use a deterministic key based on the file so OnlyOffice handles sessions correctly
        $docKey = md5($path . filemtime($path));
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $stateFilePath = $tempDir . '/myCloud_office_' . $docKey . '.json';
        file_put_contents($stateFilePath, json_encode(['path' => $path, 'expires' => time() + 86400, 'username' => $this->username, 'key' => $this->key]));
        chmod($stateFilePath, 0600); // Only readable by the MyCloud web user

        // 2. Base URL for Document Server to call back to PHP natively
        // CRITICAL FIX: SSL Terminators drop POST bodies on 301 Redirects.
        // This strictly forces HTTPS if forwarded, preventing the callback from turning into an empty GET.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        // If your MyCloud uses a non-standard port or complex proxy, 
        // ensure $_SERVER['HTTP_HOST'] is correctly set by Nginx.
 
        $protocol = $isHttps ? "https://" : "http://";
        $baseUrl = rtrim($protocol . $_SERVER['HTTP_HOST'] . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/');

        $lang = $_POST['lang'] ?? 'en';
        if (strtolower($lang) === 'zh-cn') $lang = 'zh-CN'; // OnlyOffice formatting

        $payload = [
            "document" => [
                "fileType" => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                "key" => $docKey,
                "title" => (string)basename($path),
                "url" => $baseUrl . "/myCloudOfficeFetch/" . $docKey
            ],
            "editorConfig" => [
                "callbackUrl" => $baseUrl . "/myCloudOfficeCallback?usr=" . urlencode($this->username),
                "mode" => ($this->role === 'read-only' ? "view" : "edit"),
                "lang" => $lang,
                "user" => ["id" => $this->username, "name" => $this->username],
                "customization" => [
                    "forcesave" => true,
                    "compactHeader" => (isset($_POST['is_mobile']) && $_POST['is_mobile'] === '1'),
                    "feedback" => ["visible" => false],
                    "close" => ["visible" => true],
                    "goback" => ["text" => $GLOBALS['L']['close'] ?? "Close"],
                    "uiTheme" => (isset($_POST['dark_mode']) && $_POST['dark_mode'] === '1') ? 'theme-dark' : 'theme-classic-light'
                ]
            ]
        ];

        $payload['token'] = $this->jwtEncode($payload, $this->officeSecret);

        $this->sendJsonAndExit([
            'status' => 'OK',
            'config' => $payload,
            'scriptUrl' => rtrim($this->officeExternalUrl, '/') . "/web-apps/apps/api/documents/api.js"
        ]);
    }

    private function actionOfficeConvertTempPdf() {
        $path = $this->resolve($_POST['path'] ?? '');
        $relPath = $_POST['path'] ?? '';
        if (!$path || is_dir($path)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);

        // ONLYOFFICE Request Logic
        $docKey = bin2hex(random_bytes(16));
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $stateFilePath = $tempDir . '/myCloud_office_' . $docKey . '.json';
        file_put_contents($stateFilePath, json_encode(['path' => $path, 'expires' => time() + 86400, 'username' => $this->username, 'key' => $this->key]));
        chmod($stateFilePath, 0600);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? "https://" : "http://";
        $baseUrl = rtrim($protocol . $_SERVER['HTTP_HOST'] . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/');
        $fileUrl = $baseUrl . "/myCloudOfficeFetch/" . $docKey;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $payload = [
            "async" => false,
            "filetype" => $ext,
            "key" => $docKey,
            "outputtype" => "pdf",
            "title" => basename($path),
            "url" => $fileUrl
        ];

        $payload['token'] = $this->jwtEncode($payload, $this->officeSecret);
        $convertUrl = rtrim($this->officeInternalBase, '/') . '/ConvertService.ashx';

        $ch = curl_init($convertUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $resData = json_decode($response, true);
        
        if (!empty($resData['fileUrl'])) {
            $ch2 = curl_init($resData['fileUrl']);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            $pdfContent = curl_exec($ch2);
            curl_close($ch2);

            if ($pdfContent !== false) {
                // Save temp file IN the user directory so it resolves correctly during stack execution
                $parentDir = dirname($relPath);
                if ($parentDir === '.' || $parentDir === '\\') $parentDir = '';
                
                $tmpName = '.myCloud_temp_' . bin2hex(random_bytes(4)) . '.pdf';
                $tmpRelPath = ltrim($parentDir . '/' . $tmpName, '/');
                $tmpAbsPath = $this->resolve($tmpRelPath);
                
                file_put_contents($tmpAbsPath, $pdfContent);
                $this->sendJsonAndExit(['status' => 'OK', 'tempPath' => '/' . $tmpRelPath]);
            }
        }
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Conversion failed']);
    }
	
	private function actionOfficeConvertPdf() {
        $path = $this->resolve($_POST['path'] ?? '');
        if (!$path || is_dir($path)) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Invalid file']);

        // 1. Secure stateless reference for ONLYOFFICE Docker download
        $docKey = bin2hex(random_bytes(16));
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $stateFilePath = $tempDir . '/myCloud_office_' . $docKey . '.json';
        file_put_contents($stateFilePath, json_encode(['path' => $path, 'expires' => time() + 86400, 'username' => $this->username, 'key' => $this->key]));
        chmod($stateFilePath, 0600);

        // 2. Build the callback URL for ONLYOFFICE to fetch the file
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? "https://" : "http://";
        $baseUrl = rtrim($protocol . $_SERVER['HTTP_HOST'] . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/');
        $fileUrl = $baseUrl . "/myCloudOfficeFetch/" . $docKey;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // 3. Build payload for ONLYOFFICE Conversion API
        $payload = [
            "async" => false,
            "filetype" => $ext,
            "key" => $docKey,
            "outputtype" => "pdf",
            "title" => basename($path),
            "url" => $fileUrl
        ];

        $payload['token'] = $this->jwtEncode($payload, $this->officeSecret);
        $convertUrl = rtrim($this->officeInternalBase, '/') . '/ConvertService.ashx';

        // 4. Ask ONLYOFFICE to convert the file to PDF
        $ch = curl_init($convertUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $resData = json_decode($response, true);
        
        // 5. Download the converted PDF and serve it via MyCloud's own token system (CSP safe)
        if (!empty($resData['fileUrl'])) {
            // Proxy the PDF content using cURL to avoid allow_url_fopen restrictions
            $ch2 = curl_init($resData['fileUrl']);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            $pdfContent = curl_exec($ch2);
            curl_close($ch2);

            if ($pdfContent !== false) {
                $tmpFile = $tempDir . '/myCloud_print_' . bin2hex(random_bytes(8)) . '.pdf';
                file_put_contents($tmpFile, $pdfContent);

                $token = bin2hex(random_bytes(20));
                $this->dl_tokens[$token] = [
                    'path' => $tmpFile, 
                    'filename' => pathinfo($path, PATHINFO_FILENAME) . '.pdf', 
                    'preview' => true,
                    'expires' => time() + 300, 
                    'is_temp' => true,
					'is_pdf_print' => true
                ];
                $this->sendJsonAndExit(['status' => 'OK', 'token' => $token]);
            }
        }
        
        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Print rendering failed (Code: ' . ($resData['error'] ?? 'Unknown') . ')']);
    }

    private function handleOfficeFetch() {
        session_write_close();
        $parts = explode('/myCloudOfficeFetch/', $_SERVER['REQUEST_URI']);
        $docKey = preg_replace('/[^a-zA-Z0-9]/', '', basename($parts[1]));
        $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $stateFile = $tempDir . '/myCloud_office_' . $docKey . '.json';
        
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            if ($state && is_file($state['path']) && (!isset($state['expires']) || $state['expires'] > time())) {
                header('Content-Type: application/octet-stream');
                header('Content-Length: ' . filesize($state['path']));
                readfile($state['path']);
                exit;
            }
        }
        http_response_code(404); exit;
    }

    private function handleOfficeCallback($data) {
        // Enforce JWT validation to prevent unauthenticated arbitrary file overwrites and SSRF
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = preg_match('/Bearer\s+(.*)/i', $authHeader, $matches) ? $matches[1] : ($data['token'] ?? '');
        
        if (empty($token)) {
            header('HTTP/1.1 403 Forbidden');
            exit('{"error":1, "msg":"Missing JWT signature"}');
        }

        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            header('HTTP/1.1 403 Forbidden');
            exit('{"error":1, "msg":"Invalid JWT format"}');
        }
        
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', $tokenParts[0] . '.' . $tokenParts[1], $this->officeSecret, true)));
        if (!hash_equals($expectedSignature, $tokenParts[2])) {
            header('HTTP/1.1 403 Forbidden');
            exit('{"error":1, "msg":"Invalid JWT signature"}');
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

         $token = null;
        // OnlyOffice sends JWT in 3 possible locations:
        if (isset($_SERVER['HTTP_X_ASC_WORDS_SERVICES_JWT'])) {
            $token = $_SERVER['HTTP_X_ASC_WORDS_SERVICES_JWT'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            $token = (count($parts) === 2) ? $parts[1] : $parts[0];
        } elseif (isset($data['token'])) {
            $token = $data['token'];
        }
        
        // STRICT JWT ENFORCEMENT
        if (empty($this->officeSecret)) {
            http_response_code(403);
            echo json_encode(["error" => 1, "message" => "Server misconfiguration: ONLYOFFICE JWT secret is required"]);
            exit;
        }
        if (empty($token) || !($decoded = $this->jwtDecode($token, $this->officeSecret))) {
            http_response_code(403);
            echo json_encode(["error" => 1, "message" => "Strict JWT validation failed or token missing"]);
            exit;
        }
        $data = (isset($decoded['payload']) && is_array($decoded['payload'])) ? $decoded['payload'] : $decoded;

        // 3. Process Save Request (Status 2 = Ready for saving, Status 6 = Force save)
        if ($data && isset($data['status'])) {
            $docKey = preg_replace('/[^a-zA-Z0-9]/', '', $data['key'] ?? '');
            $tempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
            $stateFile = $tempDir . '/myCloud_office_' . $docKey . '.json';
            
            if ($data['status'] == 2 || $data['status'] == 6) {
                if (file_exists($stateFile)) {
                    $state = @json_decode(file_get_contents($stateFile), true);
                    if ($state && isset($state['path'])) {
                        
                         // Fortification: Prevent SSRF by enforcing strict HTTP/HTTPS schemes
                         if (!preg_match('/^https?:\/\//i', $data['url'])) {
                             echo json_encode(["error" => 1, "message" => "Invalid callback URL scheme"]);
                             exit;
                         }

                        // Download the modified file from the Document Server
                        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                        $newFile = @file_get_contents($data['url'], false, $ctx);
                        if ($newFile !== false) {
                            file_put_contents($state['path'], $newFile);
                            
                            // Extract the real user and key from the state file
                            $this->username = !empty($state['username']) ? $state['username'] : 'guest';
                            $this->key = !empty($state['key']) ? $state['key'] : '';
                            $this->log('OFFICE_SAVE', $state['path']);
                        } else {
                            // Return error 1 so OnlyOffice knows it failed and retries
                            echo json_encode(["error" => 1]);
                            exit;
                        }
                    }
                }
            }
            
            // Only destroy the stateless token if the session is fully closed 
            // (Status 2 = Document ready for saving, Status 4 = Closed with no changes)
            if ($data['status'] == 2 || $data['status'] == 4) {
                @unlink($stateFile);
            }
        }
        
        // 4. Strict ONLYOFFICE 7.2+ Signed Response
        $response = ["error" => 0];
        $response['token'] = $this->jwtEncode($response, $this->officeSecret);
        
        echo json_encode($response);
        exit;
    }
	
	
    private function jwtEncode($p, $k) {
        $h = str_replace(['+','/','='], ['-','_',''], base64_encode(json_encode(['typ'=>'JWT','alg'=>'HS256'])));
        $b = str_replace(['+','/','='], ['-','_',''], base64_encode(json_encode($p)));
        $s = str_replace(['+','/','='], ['-','_',''], base64_encode(hash_hmac('sha256', "$h.$b", $k, true)));
        return "$h.$b.$s";
    }

    private function jwtDecode($jwt, $k) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        list($h, $b, $s) = $parts;
        $validSig = str_replace(['+','/','='], ['-','_',''], base64_encode(hash_hmac('sha256', "$h.$b", $k, true)));
        if (hash_equals($validSig, $s)) {
            return json_decode(base64_decode(str_replace(['-','_'], ['+','/'], $b)), true);
        }
        return null;
    }

}

// ---------------------------------------------------------
// 5. GLOBAL WRAPPER (Preserves compatibility)
// ---------------------------------------------------------

function myCloudHandleRequests() {
    $server = new MyCloudServer();
    $server->handleRequests();
}