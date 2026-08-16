<?php
/**
 * ============================================================================
 * MODULE: Native Admin & Config Management Module
 * ============================================================================
 * Generates the frontend layout, HTML structure, and interactive components 
 * for the administrative dashboard and system management views.
 * Also handles backend requests
 * Strictly scoped. Preserves non-cloud user data and configurations.
 */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}

// ---------------------------------------------------------------------
// 1. SERVER SIDE AJAX HANDLER
// ---------------------------------------------------------------------

// Function wrapper since is is called twice (not to declare functions twice)
if (!function_exists('CloudAdmin_handle_ajax')) {
	
  function CloudAdmin_handle_ajax() {
    global $user_db, $work_dir;

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') return;
    if (!isset($_POST['ca_action'])) return;

    while (ob_get_level()) ob_end_clean(); ob_start();
    error_reporting(0); ini_set('display_errors', 0);

    if (session_status() == PHP_SESSION_NONE) session_start();
    
    $err = null;
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['myCloud_csrf_token']) || !hash_equals($_SESSION['myCloud_csrf_token'], $_POST['csrf_token'])) $err = 'CSRF Fail';
    elseif (!isset($_SESSION['username']) || !function_exists('getUserRole')) $err = 'Auth Error';
    elseif (strtolower(getUserRole($_SESSION['username'])) !== 'admin') $err = 'Access Denied';
    elseif (!isset($user_db) || !file_exists($user_db)) $err = 'DB Missing';

    if ($err) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => $err]); exit; }

    $action = $_POST['ca_action'];
    $configFile = rtrim($work_dir, '/\\') . '/configuration/config.php';

    try { 
        include $user_db; 
    } catch (Throwable $e) { 
        ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'DB/Config Corrupt']); exit; 
    }

    $schema = [
        // 0. SYSTEM
        'maintenance_mode' => ['cat' => 'System', 'label' => 'Maintenance Mode', 'desc' => 'If true, non-admin users cannot access the application.'],
        'language' => ['cat' => 'System', 'label' => 'Default Language', 'desc' => 'Fallback system language (e.g., en, de).'],

        // 1. PATHS
        'work_dir' => ['cat' => 'Paths', 'label' => 'Work Directory', 'desc' => 'Parent directory of this application. MUST NOT be accessible from the outside (e.g. not in the www-root!)'],
        'action_dir' => ['cat' => 'Paths', 'label' => 'Action Directory', 'desc' => 'Directory for actions. Usually within $work_dir.'],
        'list_dir' => ['cat' => 'Paths', 'label' => 'List Directory', 'desc' => 'Directory for lists. Usually within $work_dir.'],
        'cloud_dir' => ['cat' => 'Paths', 'label' => 'Cloud Directory', 'desc' => 'The physical directory where the cloud PHP files are stored. Usually within $work_dir.'],
        'temp_dir' => ['cat' => 'Paths', 'label' => 'Temp Directory', 'desc' => 'The directory for temporary files. Usually within $work_dir.'],
        'cloud_icon_cache' => ['cat' => 'Paths', 'label' => 'Icon Cache Dir', 'desc' => 'Icons: Icon cache directory path.'],
        'cloud_preview_cache' => ['cat' => 'Paths', 'label' => 'Preview Cache Dir', 'desc' => 'Preview: Picture cache directory path.'],
        'cloud_user_profiles' => ['cat' => 'Paths', 'label' => 'User Profiles Path', 'desc' => 'Path for the user profiles directory. Usually within $work_dir.'],

        // 2. FILES
        'log_file' => ['cat' => 'Files', 'label' => 'Log File', 'desc' => 'The main log file path. Do not use /var/log as this would mean you would have to put that into open_basedir'],
        'user_db' => ['cat' => 'Files', 'label' => 'User Database', 'desc' => 'User database file path. MUST NOT be accessible from the outside (e.g. not in the www-root!)'],
        'cloud_share_db' => ['cat' => 'Files', 'label' => 'Share Database', 'desc' => 'The path and file name of the sharing database json.'],
        'cloud_logfile' => ['cat' => 'Files', 'label' => 'Cloud Logfile', 'desc' => 'Logfile for all modifying tasks (copy, move, delete, upload...).'],
        'cloud_public_blocklist' => ['cat' => 'Files', 'label' => 'Public Blocklist', 'desc' => 'Public shares: Blocklist file path for login attempts.'],
        'login_bruteforce_file' => ['cat' => 'Files', 'label' => 'Bruteforce DB', 'desc' => 'Path to login failures JSON database.'],
        'login_stateful_tokens' => ['cat' => 'Files', 'label' => 'Stateful Tokens DB', 'desc' => 'Path to stateful tokens JSON.'],
        'global_login_rate_file' => ['cat' => 'Files', 'label' => 'Global Rate DB', 'desc' => 'Path to global login rate JSON.'],
        'security_max_requests_file' => ['cat' => 'Files', 'label' => 'Security Requests DB', 'desc' => 'Path to security max requests JSON.'],
        'cloud_admin_mode_cloudlist' => ['cat' => 'Files', 'label' => 'Admin Mode DB', 'desc' => 'Admin Mode (SFTP): Optional file to store SFTP account passwords.'],
		'verify_store_file' => ['cat' => 'Files', 'label' => '2FA Verification DB', 'desc' => 'Path to 2FA verifications JSON.'],

        // 3. SECURITY
        'force_argon2_only' => ['cat' => 'Security', 'label' => 'Force Argon2', 'desc' => 'Force the use of Argon2id hashing for passwords.'],
        'login_failures' => ['cat' => 'Security', 'label' => 'Login Failures', 'desc' => 'Max login failures before blocking the IP.'],
        'login_block_seconds' => ['cat' => 'Security', 'label' => 'Login Block Seconds', 'desc' => 'Base block duration in seconds.'],
        'brute_force_window' => ['cat' => 'Security', 'label' => 'Bruteforce Window', 'desc' => 'Window to remember previous lockout (seconds).'],
        'brute_force_factor' => ['cat' => 'Security', 'label' => 'Bruteforce Factor', 'desc' => 'Multiplier for each repeated lockout.'],
        'global_login_max_hits' => ['cat' => 'Security', 'label' => 'Global Max Hits', 'desc' => 'Global maximum login attempts before throttling.'],
        'global_login_window' => ['cat' => 'Security', 'label' => 'Global Window', 'desc' => 'Global login time window (seconds).'],
        'cloud_upload_blocked_exts' => ['cat' => 'Security', 'label' => 'Blocked Upload Exts', 'desc' => 'Upload: Security filter. Extensions not allowed (comma separated array).'],
        'cloud_clamav_enabled' => ['cat' => 'Security', 'label' => 'Enable ClamAV', 'desc' => 'Security: Scan all incoming files for malware.'],
        'cloud_clamav_path' => ['cat' => 'Security', 'label' => 'ClamAV Path', 'desc' => 'Security: Path to clamdscan executable.'],

        // 4. SESSION AND NETWORK
        'session_storage' => ['cat' => 'Session and Network', 'label' => 'Session Storage', 'desc' => 'Directory for storing session files.'],
		'timeout_in_minutes' => ['cat' => 'Session and Network', 'label' => 'Session Timeout', 'desc' => 'Session inactivity limit in minutes.'],
        'cookie_name' => ['cat' => 'Session and Network', 'label' => 'Cookie Name', 'desc' => 'Name of the authentication cookie.'],
        'cookie_valid_duration' => ['cat' => 'Session and Network', 'label' => 'Cookie Duration', 'desc' => 'Cookie validity duration in seconds.'],
        'cookie_secret' => ['cat' => 'Session and Network', 'label' => 'Cookie Secret', 'desc' => 'Secret key for cookie encryption.'],
        'cookie_is_ip_bound' => ['cat' => 'Session and Network', 'label' => 'IP Bound Cookies', 'desc' => 'Bind session cookies strictly to IP address.'],
        'allowed_domain' => ['cat' => 'Session and Network', 'label' => 'Allowed Domains', 'desc' => 'Domains allowed to execute this code (array).'],
        'cloud_only_domains' => ['cat' => 'Session and Network', 'label' => 'Cloud Only Domains', 'desc' => 'Domains restricted to cloud functionality only (array).'],
        'cloud_only_paths' => ['cat' => 'Session and Network', 'label' => 'Cloud Only Paths', 'desc' => 'Paths restricted to cloud functionality only (array).'],
        'enableGeoIP2' => ['cat' => 'Session and Network', 'label' => 'Enable GeoIP2', 'desc' => 'Enable GeoIP2 checking.'],
        'geoip_data' => ['cat' => 'Session and Network', 'label' => 'GeoIP Data Fields', 'desc' => 'Array format for the fields returned by the GeoIP module.'],
		'whitelist_admin_countries' => ['cat' => 'Session and Network', 'label' => 'Admin Countries', 'desc' => 'Allowed countries for administrators (array).'],
		'cloud_mail_only_localhost' => ['cat' => 'Session and Network', 'label' => 'Mail Only Localhost', 'desc' => 'Should only localhost connections be allowed for the mail module?'],

        // 5. CLOUD
        'cloud_max_preview_size' => ['cat' => 'Cloud', 'label' => 'Max Preview Size', 'desc' => 'Warn before preview threshold size (in bytes).'],
        'cloud_autodownload' => ['cat' => 'Cloud', 'label' => 'Auto-Download', 'desc' => 'Automatically download on enter instead of previewing, system level switch.'],
        'cloud_share_url' => ['cat' => 'Cloud', 'label' => 'Share URL', 'desc' => 'The URL under which the shared dirs or files will be reachable from the internet.'],
        'cloud_public_quotas' => ['cat' => 'Cloud', 'label' => 'Public Quotas', 'desc' => 'Public shares: Maximum file size for uploads.'],
        'cloud_public_max_zip_size' => ['cat' => 'Cloud', 'label' => 'Public Max ZIP Size', 'desc' => 'Public shares: Download shared content as a ZIP - maximum uncompressed size.'],
        'cloud_public_max_login_attempts' => ['cat' => 'Cloud', 'label' => 'Public Max Login Attempts', 'desc' => 'Public shares: Maximum login attempts before lockout.'],
        'cloud_icon_maxpixel' => ['cat' => 'Cloud', 'label' => 'Icon Max Pixels', 'desc' => 'Icons: Size in pixels.'],
        'cloud_icon_quality' => ['cat' => 'Cloud', 'label' => 'Icon Quality', 'desc' => 'Icons: JPEG compression quality (0-100).'],
		'cloud_preview_maxpixel' => ['cat' => 'Cloud', 'label' => 'Preview Max Pixels', 'desc' => 'Preview: Size in pixels.'],
        'cloud_preview_quality' => ['cat' => 'Cloud', 'label' => 'Preview Quality', 'desc' => 'Preview: JPEG compression quality (0-100).'],
		'cloud_recycle_bin_retention_days' => ['cat' => 'Cloud', 'label' => 'Recycle Bin Retention', 'desc' => 'Recycler: How long should items be stored in the recycle bin? (days).'],
        'zip_warn_limit' => ['cat' => 'Cloud', 'label' => 'ZIP Warn Limit', 'desc' => 'Download: When to warn before creating a large ZIP file (bytes).'],
        'cloud_enable_admin_mode' => ['cat' => 'Cloud', 'label' => 'Enable Admin Mode', 'desc' => 'Admin Mode (SFTP): Main switch. If false, admin_mode is not possible.'],
        'cloud_onlyoffice_Secret' => ['cat' => 'Cloud', 'label' => 'OnlyOffice Secret', 'desc' => 'OnlyOffice: Shared JWT secret. Leave blank to disable OnlyOffice completely.'],
        'cloud_onlyoffice_URL' => ['cat' => 'Cloud', 'label' => 'OnlyOffice Internal URL', 'desc' => 'OnlyOffice: Internal network URL.'],
        'cloud_onlyoffice_ext_URL' => ['cat' => 'Cloud', 'label' => 'OnlyOffice External URL', 'desc' => 'OnlyOffice: External network URL reachable by clients. Leave blank to disable OnlyOffice completely.'],
        'cloud_rate_limit_enabled' => ['cat' => 'Cloud', 'label' => 'Enable Rate Limiting', 'desc' => 'API: Prevent API abuse.'],
        'cloud_rate_limit_max' => ['cat' => 'Cloud', 'label' => 'Rate Limit Max Hits', 'desc' => 'API: Maximum number of API requests allowed.'],
        'cloud_rate_limit_window' => ['cat' => 'Cloud', 'label' => 'Rate Limit Window', 'desc' => 'API: Time window in seconds.'],
        'cloud_ribbon_threshold' => ['cat' => 'Cloud', 'label' => 'Ribbon Threshold', 'desc' => 'Number of toolbar buttons displayed without stacking into ribbons.']
    ];

    if ($action === 'load') {
        // 1. Process Users
        $fe_users = [];
        $roles_avail = ['admin', 'cloud', 'user'];

        if (isset($users) && is_array($users)) {
            foreach ($users as $uname => $hash) {
                $det = ['name' => $uname, 'email' => '', 'role' => 'user', 'cloud_webdav' => false, 'cloud' => []];
                if (isset($user_details) && is_array($user_details)) {
                    foreach ($user_details as $ud) {
                        if ($ud['name'] === $uname) {
                            $det['email'] = $ud['email'] ?? '';
                            $det['role'] = $ud['role'] ?? 'user';
                            $det['cloud_webdav'] = !empty($ud['cloud_webdav']);
                            $det['cloud'] = $ud['cloud'] ?? [];
                            $det['logviewer'] = !empty($ud['logviewer']);
                            $det['logviewer_filter'] = $ud['logviewer_filter'] ?? '';
                            if (!in_array($det['role'], $roles_avail)) $roles_avail[] = $det['role'];
                            break;
                        }
                    }
                }
                $fe_users[$uname] = $det;
            }
        }

        // 2. Process Configurations Natively (Raw File Parse)
        $fe_config = [];
        $distFile = rtrim($work_dir, '/\\') . '/configuration/config.dist.php';
        if (!file_exists($distFile) && file_exists(rtrim($work_dir, '/\\') . '/configuration/config.php.dist')) {
            $distFile = rtrim($work_dir, '/\\') . '/configuration/config.php.dist';
        }
        $raw_dist_code = file_exists($distFile) ? file_get_contents($distFile) : '';
        $raw_config_code = file_exists($configFile) ? file_get_contents($configFile) : '';
        
        $parse_val = function($raw_code, $key, &$is_bool, &$is_array, &$is_long) {
            $ext = ca_extract_var($raw_code, $key);
            if ($ext === null) return null;
            $val = trim($ext);
            $lower_val = strtolower($val);
            if ($lower_val === 'true' || $lower_val === 'false') {
                $is_bool = true; return ($lower_val === 'true') ? 'true' : 'false';
            } elseif (strpos($val, '[') === 0 || strpos($lower_val, 'array(') === 0) {
                $is_array = true;
                $val = preg_replace('/^array\s*\(\s*|\s*\)$|^\[\s*|\s*\]$/i', '', $val);
                $items = explode(',', $val); $clean = [];
                foreach($items as $i) {
                    $i = trim(trim($i), "'\"");
                    if ($i !== '') $clean[] = $i;
                }
                $val = implode(', ', $clean);
                if (strlen($val) > 40) $is_long = true;
                return $val;
            } else {
                $str_tokens = token_get_all("<?php " . $val . ";");
                if (count($str_tokens) === 3 && $str_tokens[1][0] === T_CONSTANT_ENCAPSED_STRING) $val = substr(trim($val), 1, -1);
                if (strlen($val) > 60) $is_long = true;
                return $val;
            }
        };

        foreach ($schema as $k => $meta) {
            $meta['is_bool'] = false; $meta['is_array'] = false; $meta['is_long'] = false;
            $dist_val = $parse_val($raw_dist_code, $k, $meta['is_bool'], $meta['is_array'], $meta['is_long']);
            if ($dist_val === null) $dist_val = ''; 
            
            $d_b=false; $d_a=false; $d_l=false;
            $custom_val = $parse_val($raw_config_code, $k, $d_b, $d_a, $d_l);

            $fe_config[$k] = ['val' => $custom_val, 'default' => $dist_val, 'meta' => $meta];
        }

        ob_end_clean(); header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'users' => $fe_users, 'roles' => $roles_avail, 'config' => $fe_config, 'work_dir' => rtrim($work_dir, '/\\')]); exit;
    }

    if ($action === 'save_user') {
        $p = json_decode($_POST['payload'], true);
        if (!$p) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Bad Data']); exit; }
        
        $orig = $p['orig_name']; $new = $p['name'];
        
        if ($orig && $orig !== $new) { 
            if (isset($users[$orig])) { $users[$new] = $users[$orig]; unset($users[$orig]); } 
        }
        if (!empty($p['pass'])) $users[$new] = password_hash($p['pass'], PASSWORD_ARGON2ID);
        elseif (empty($orig) && !isset($users[$new])) { 
            ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Password required']); exit; 
        }

        // PATH VALIDATION: Check existence, creation, and open_basedir
        if (!empty($p['clouds']) && is_array($p['clouds'])) {
            $open_basedir = ini_get('open_basedir');
            $basedirs = $open_basedir ? explode(PATH_SEPARATOR, $open_basedir) : [];

            foreach ($p['clouds'] as $cn => $cdata) {
                $path = $cdata['path'] ?? '';
                // Skip SSH formats or remote protocol paths
                if (empty($path) || strpos($path, '@') !== false || strpos($path, '://') !== false) continue;

                if (!file_exists($path)) {
                    if (!@mkdir($path, 0755, true)) {
                        ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Cannot create directory: ' . htmlspecialchars($path)]); exit;
                    }
                }

                if (!empty($basedirs)) {
                    $real_path = realpath($path);
                    $is_within = false;
                    if ($real_path) {
                        foreach ($basedirs as $bd) {
                            $real_bd = realpath($bd);
                            if ($real_bd && strpos($real_path, $real_bd) === 0) { $is_within = true; break; }
                        }
                        if (!$is_within) {
                            ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Path violates open_basedir restriction: ' . htmlspecialchars($path)]); exit;
                        }
                    }
                }
            }
        }


        $found = false;
        if (!isset($user_details) || !is_array($user_details)) $user_details = [];
        foreach ($user_details as $i => $u) { 
            if ($u['name'] === $orig) { 
                $new_det = $u; 
                $new_det['name'] = $new;
                $new_det['email'] = $p['email'];
                $new_det['role'] = $p['role'];
                $new_det['cloud_webdav'] = $p['webdav'];
                $new_det['cloud'] = $p['clouds'] ?? [];
                $new_det['logviewer'] = !empty($p['logviewer']);
                $new_det['logviewer_filter'] = $p['logviewer_filter'] ?? '';
                
                $user_details[$i] = $new_det; 
                $found = true; break; 
            } 
        }
        
        if (!$found) {
            $user_details[] = [
                'name' => $new, 'email' => $p['email'], 'role' => $p['role'], 
                'cloud_webdav' => $p['webdav'], 'cloud' => $p['clouds'] ?? [],
                'logviewer' => !empty($p['logviewer']), 'logviewer_filter' => $p['logviewer_filter'] ?? ''
            ];
        }

        $res = CloudAdmin_atomic_write_vars($user_db, ['users' => ca_gen_strict($users), 'user_details' => ca_gen_strict($user_details)]);
        ob_end_clean(); header('Content-Type: application/json');
        if ($res === true) echo json_encode(['status' => 'ok']); else echo json_encode(['status' => 'error', 'msg' => $res]); exit;
    }

    if ($action === 'delete_user') {
        $p = json_decode($_POST['payload'], true); $n = $p['name'];
        if (isset($users[$n])) unset($users[$n]);
        if (isset($user_details)) {
            foreach ($user_details as $i => $u) { if ($u['name'] === $n) { unset($user_details[$i]); break; } }
            $user_details = array_values($user_details);
        }
        $res = CloudAdmin_atomic_write_vars($user_db, ['users' => ca_gen_strict($users), 'user_details' => ca_gen_strict($user_details)]);
        ob_end_clean(); header('Content-Type: application/json');
        if ($res === true) echo json_encode(['status' => 'ok']); else echo json_encode(['status' => 'error', 'msg' => $res]); exit;
    }

    if ($action === 'get_subdirs') {
        $base = $_POST['base_path'] ?? '';
        $rel = $_POST['rel_path'] ?? '/';
        
        $realBase = realpath($base);
        if (!$realBase || !is_dir($realBase)) {
            ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Base cloud path does not exist on server.']); exit;
        }

        $full = rtrim($realBase, '/\\') . '/' . ltrim(str_replace('\\', '/', $rel), '/');
        $realFull = realpath($full);

        if (!$realFull || strpos($realFull, $realBase) !== 0 || !is_dir($realFull)) {
            ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Subdirectory does not exist or escapes boundary.']); exit;
        }

        $dirs = [];
        $items = @scandir($realFull);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.recycle_bin') continue;
                if (is_dir($realFull . DIRECTORY_SEPARATOR . $item)) $dirs[] = $item;
            }
        }
        natcasesort($dirs);
        
        $currentRel = '/' . ltrim(str_replace('\\', '/', substr($realFull, strlen($realBase))), '/');
        if ($currentRel === '') $currentRel = '/';
        
        ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'ok', 'dirs' => array_values($dirs), 'current' => $currentRel]); exit;
    }

    if ($action === 'save_config') {
        $p = json_decode($_POST['payload'], true);
        if (!$p) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'msg' => 'Bad Data']); exit; }
        
        $configs_to_write = [];
        foreach ($p as $k => $info) {
            if (!array_key_exists($k, $schema)) continue;

            if ($info['val'] === null) {
                $configs_to_write[$k] = null; // Mark for deletion
                continue;
            }
            $val = trim($info['val']);
            $type = $info['type'];
            
            // Expression detector: Starts with variable, constant, or function call.
            $is_expression = preg_match('/^(\$[a-zA-Z_\x7f-\xff]|__DIR__|__FILE__|[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\()/', $val);

            if ($type === 'bool') {
                $configs_to_write[$k] = ($val === 'true' || $val === true) ? 'true' : 'false';
            } elseif ($type === 'array') {
                $parts = explode(',', $val);
                $clean_parts = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        // Skip wrapping quotes if it is a PHP expression OR if it already has quotes
                        if (preg_match('/^(\$[a-zA-Z_\x7f-\xff]|__DIR__|__FILE__|[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\()/', $part) || preg_match('/^[\'"].*[\'"]$/', $part)) {
                            $clean_parts[] = $part; 
                        } else {
                            $clean_parts[] = "'" . addslashes($part) . "'";
                        }
                    }
                }
                $configs_to_write[$k] = "[" . implode(', ', $clean_parts) . "]";
            } else {
                if ($is_expression || (is_numeric($val) && strval(intval($val)) === strval($val))) {
                    $configs_to_write[$k] = $val; 
                } else {
                    $configs_to_write[$k] = "'" . addslashes($val) . "'";
                }
            }
        }

        $res = CloudAdmin_atomic_write_vars($configFile, $configs_to_write);
        ob_end_clean(); header('Content-Type: application/json');
        if ($res === true) echo json_encode(['status' => 'ok']); else echo json_encode(['status' => 'error', 'msg' => $res]); exit;
    }
  }
  // ---------------------------------------------------------------------
  // 2. ATOMIC SURGICAL WRITERS & READERS
  // ---------------------------------------------------------------------
  function ca_extract_var($code, $varName) {
    $tokens = token_get_all($code);
    $state = 'FIND_VAR';
    $val = '';
    $bracketDepth = 0;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        switch ($state) {
            case 'FIND_VAR':
                if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$' . $varName) {
                    $state = 'FIND_EQUALS';
                }
                break;
            case 'FIND_EQUALS':
                if ($text === '=') {
                    $state = 'EXTRACT';
                } elseif (trim($text) !== '' && !in_array((is_array($token) ? $token[0] : null), [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    // Reset if the variable is used instead of assigned
                    $state = 'FIND_VAR';
                }
                break;
            case 'EXTRACT':
                if ($text === '(' || $text === '[' || $text === '{') {
                    $bracketDepth++;
                } elseif ($text === ')' || $text === ']' || $text === '}') {
                    $bracketDepth--;
                } elseif ($text === ';' && $bracketDepth === 0) {
                    return trim($val);
                }
                $val .= $text;
                break;
        }
    }
    return null;
  }

  function ca_gen_strict($data, $level = 0) {
    if (is_array($data)) {
        if (empty($data)) return '[]';
        $tab = str_repeat("    ", $level); $sub = str_repeat("    ", $level + 1);
        $assoc = array_keys($data) !== range(0, count($data) - 1);
        $l = [];
        foreach ($data as $k => $v) {
            $val = is_array($v) ? ca_gen_strict($v, $level + 1) : (is_bool($v) ? ($v?'true':'false') : (is_int($v) ? $v : "'".addslashes($v)."'"));
            $l[] = $sub . ($assoc ? (is_int($k)?$k:"'$k'") . " => " . $val : $val);
        }
        return "[\n" . implode(",\n", $l) . ",\n" . $tab . "]";
    }
    return is_string($data) ? "'".addslashes($data)."'" : $data;
  }

  function ca_reconstruct_var($code, $varName, $newContent) {
    $tokens = token_get_all($code); 
    $newCode = ''; 
    $state = 'FIND_VAR'; 
    $replaced = false; 
    $bracketDepth = 0;
    
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        if ($replaced) { $newCode .= $text; continue; }
        
        switch ($state) {
            case 'FIND_VAR': 
                $newCode .= $text; 
                if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$' . $varName) {
                    $state = 'FIND_EQUALS'; 
                }
                break;
            case 'FIND_EQUALS': 
                $newCode .= $text; 
                if ($text === '=') {
                    $newCode .= ' ' . $newContent;
                    $state = 'SKIP_UNTIL_SEMI'; 
                } elseif (trim($text) !== '' && !in_array((is_array($token) ? $token[0] : null), [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                    // Reset if the variable is used instead of assigned
                    $state = 'FIND_VAR';
                }
                break;
            case 'SKIP_UNTIL_SEMI':
                if ($text === '(' || $text === '[' || $text === '{') {
                    $bracketDepth++;
                } elseif ($text === ')' || $text === ']' || $text === '}') {
                    $bracketDepth--;
                } elseif ($text === ';' && $bracketDepth === 0) {
                    $newCode .= $text;
                    $replaced = true;
                }
                break;
        }
    }
    
    if (!$replaced) {
        $newCode = preg_replace('/(\?>\s*)$/', "\n\$$varName = $newContent;\n$1", $code, 1, $count);
        if (!$count) {
            $newCode = rtrim($code) . "\n\$$varName = $newContent;\n";
        }
    }
    
    return $newCode;
  }


  function CloudAdmin_atomic_write_vars($filepath, $varsToReplace) {
    if (!file_exists($filepath)) return 'File not found';
    $c = file_get_contents($filepath);

    foreach ($varsToReplace as $var => $newContent) {
        if ($newContent === null) {
            $c = preg_replace('/^[ \t]*\$' . preg_quote($var, '/') . '\s*=.*?;[ \t]*\r?\n?/m', '', $c);
        } else {
            $c = ca_reconstruct_var($c, $var, $newContent);
        }
    }

    $tmp = tempnam(dirname($filepath), 'chk_'); file_put_contents($tmp, $c);
    try {
       // Native PHP 7.0+ syntax check using a sandboxed scope
       call_user_func(function() use ($tmp) {
           ob_start();
           include $tmp;
           ob_end_clean();
       });
    } catch (\ParseError $e) { 
       @unlink($tmp); 
       return "Syntax Error"; 
    } catch (\Throwable $e) { 
       @unlink($tmp); 
       return "Validation Error"; 
    }
    unlink($tmp);

    $fp = fopen($filepath, 'r+');
    if (!$fp) return 'Lock Fail';
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp); fwrite($fp, $c); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
        if (function_exists('opcache_invalidate')) opcache_invalidate($filepath, true);
        return true;
    }
    fclose($fp); return 'Write Lock Failed';
  }


  function CloudAdmin_render_html() {
    $logviewerHtml = '';
    if (file_exists(__DIR__ . '/modules.admin_logviewer.php')) {
        $logviewerHtml = '
        <div class="ca-form-group" style="margin:0; border:none; padding:0; grid-column: 1 / -1; display:flex; gap:15px; align-items:center; border-top: 1px solid var(--ca-border-subtle); padding-top: 15px;">
            <label class="ca-label ca-hover-tooltip" data-tooltip="Enable Logviewer for this user." style="margin:0; display:flex; align-items:center; gap:6px;">
                Enable Logviewer
                <div class="ca-toggle-switch"><input type="checkbox" id="ca_inp_logviewer" onchange="ca_mark_dirty(this)"><span class="ca-slider"></span></div>
            </label>
            <div style="flex:1;">
                <input type="text" id="ca_inp_logviewer_filter" class="ca-input" placeholder="Fixed Filter (leave empty for all)" oninput="ca_mark_dirty(this)" autocomplete="off">
            </div>
        </div>';
    }
    return '
    <div class="ca-layout">
        <div class="ca-tabs">
            <button class="ca-tab-btn active" id="ca_tab_users" onclick="ca_switch_tab(\'users\')">User Management</button>
            <button class="ca-tab-btn" id="ca_tab_config" onclick="ca_switch_tab(\'config\')">Cloud Configuration</button>
        </div>

        <div id="ca_area_users" class="ca-area">
            <button id="ca_floating_save_user" class="ca-btn ca-btn-primary ca-floating-save" type="button" onclick="ca_action_save_user()">Save User</button>
            
            <div class="ca-sidebar-panel">
                <div style="padding: 10px; border-bottom: 1px solid var(--ca-border-subtle); background: var(--ca-bg-sidebar);">
                    <label style="display:none; align-items:center; justify-content:space-between; margin-bottom:10px; cursor:pointer; ">
                        <span style="font-size:12px; font-weight:600; color:var(--ca-text-sidebar); margin:0;">Users with Clouds only</span>
                        <div class="ca-toggle-switch"><input type="checkbox" id="ca_filter_clouds_only" onchange="ca_filter_users(document.getElementById(\'ca_user_search\').value)"><span class="ca-slider"></span></div>
                    </label>
                    <input type="text" id="ca_user_search" class="ca-input" placeholder="🔍 Search users..." oninput="ca_filter_users(this.value)" style="border-radius: 16px; padding: 6px 12px; font-family: inherit;">
                </div>
                <ul class="ca-user-list-ul" id="ca_user_list_ul"></ul>
                <div class="ca-sidebar-footer">
                    <button class="ca-btn ca-btn-outline" style="width:100%" onclick="ca_init_new()">+ New User</button>
                </div>
            </div>
            
            <div class="ca-editor-panel" id="ca_editor_area">
                <form id="ca_main_form" onsubmit="event.preventDefault();" autocomplete="off" oninput="ca_mark_dirty(event.target)" onchange="ca_mark_dirty(event.target)">
                    <input type="text" style="display:none" name="fake_u">
                    <input type="password" style="display:none" name="fake_p">
                    <input type="hidden" id="ca_inp_original_name" value="">
                    
                    <div class="ca-section-heading" style="margin-top:0;">User Data</div>
                    <div class="ca-config-card" style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="ca-form-group" style="margin:0; border:none; padding:0;">
                            <label class="ca-label ca-hover-tooltip" data-name="Username" data-tooltip="The unique identifier for the user account.">Username</label>
                            <input type="text" id="ca_inp_name" class="ca-input" data-name="Username" placeholder="e.g. jdoe" required autocomplete="off">
                        </div>
                        <div class="ca-form-group" style="margin:0; border:none; padding:0;">
                            <label class="ca-label ca-hover-tooltip" data-name="Email" data-tooltip="User\'s email address for notifications and security.">Email</label>
                            <input type="email" id="ca_inp_email" class="ca-input" data-name="Email" placeholder="user@example.com" required autocomplete="off">
                        </div>
                        <div class="ca-form-group" style="margin:0; border:none; padding:0;">
                            <label class="ca-label ca-hover-tooltip" data-name="Password" data-tooltip="Leave empty to keep current password. Needs 8+ chars, a digit, and a special char.">Password <small style="font-weight:400; color:inherit">(Empty = Unchanged)</small></label>
                            <div style="position:relative; display:flex; align-items:center;">
                                <input type="password" id="ca_inp_password" class="ca-input" data-name="Password" placeholder="••••••••" autocomplete="new-password" style="padding-right:32px;">
                                <button type="button" tabindex="-1" onclick="ca_toggle_pwd(this)" style="position:absolute; right:4px; background:none; border:none; cursor:pointer; color:var(--ca-text-muted); padding:4px; display:flex; align-items:center; justify-content:center; transition:color 0.2s;" title="Toggle visibility">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px;">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="ca-form-group" style="margin:0; border:none; padding:0;">
                            <label class="ca-label ca-hover-tooltip" data-name="Global Role" data-tooltip="Base system privileges. \'Admin\' grants full configuration access.">Global Role</label>
                            <select id="ca_inp_role" class="ca-select" data-name="Global Role"></select>
                        </div>
                        ' . $logviewerHtml . '
                    </div>

                    <div class="ca-section-heading">
                        <span>Cloud Mounts</span>
                        <div style="display:flex; gap:10px; align-items:center; text-transform:none; letter-spacing:normal;">
                            <label class="ca-label ca-hover-tooltip" data-tooltip="Allow user to connect their storage via WebDAV protocol." data-name="Enable WebDAV" style="display:flex; align-items:center; gap:6px; font-size:12px; cursor:pointer; color:var(--ca-text-dark); font-weight:600; margin:0;">
							Enable WebDAV
                                <div class="ca-toggle-switch"><input type="checkbox" id="ca_inp_webdav" onchange="ca_mark_dirty(this)"><span class="ca-slider"></span></div>
                            </label>
                            <button class="ca-btn ca-btn-outline ca-btn-sm" type="button" onclick="ca_add_cloud_row(); ca_mark_dirty({dataset:{name:\'Cloud Mounts\'}});" style="background:var(--ca-bg-card);">+ Add Mount</button>
                        </div>
                    </div>
                    <div class="ca-dynamic-box" id="ca_cloud_container" ondragover="ca_drag_over(event)"></div>

                    <div class="ca-actions-footer">
                        <button class="ca-btn ca-btn-danger" id="ca_btn_delete" type="button" onclick="ca_action_delete()">Delete User</button>
                        <div id="ca_unsaved_users" class="ca-unsaved-indicator"></div>
                    </div>
                </form>
            </div>
            <div class="ca-loading-spinner" id="ca_loading_users" style="display:none;">Loading...</div>
        </div>

        <div id="ca_area_config" class="ca-area" style="display:none; flex-direction:column;">
            <button id="ca_floating_save_config" class="ca-btn ca-btn-primary ca-floating-save" type="button" onclick="ca_action_save_config()">Save Configuration</button>
            
            <div class="ca-editor-panel" style="width:100%; max-width:800px; margin:0 auto; border-left:1px solid var(--ca-border-normal); border-right:1px solid var(--ca-border-normal);">
                <div class="ca-section-heading" style="margin-top:0; cursor:default;">Global System Configuration</div>
                <div style="font-size:12px; color:var(--ca-text-muted); margin-bottom:15px; line-height:1.5;">Edit raw variables natively stored in your <code>config.php</code> file. Values are saved exactly as entered (as raw PHP expressions). Arrays can be edited as multiline text.</div>
                
                <div style="margin-bottom: 20px;">
                    <input type="text" id="ca_config_search" class="ca-input" placeholder="🔍 Search variables, labels, categories, descriptions or values..." oninput="ca_filter_config(this.value)" style="border-radius: 20px; padding: 8px 16px;">
                </div>

                <form id="ca_config_form" onsubmit="event.preventDefault();" oninput="ca_mark_dirty_cfg(event.target)" onchange="ca_mark_dirty_cfg(event.target)"></form>
                
                <div class="ca-actions-footer">
                    <div id="ca_unsaved_config" class="ca-unsaved-indicator"></div>
                </div>
            </div>
            <div class="ca-loading-spinner" id="ca_loading_config" style="display:none;">Loading...</div>
        </div>
    </div>';
  }

}

// ---------------------------------------------------------------------
// 3. DYNAMIC JAVASCRIPT OUTPUT
// ---------------------------------------------------------------------

// Only output the following if requested by the dynamic JS router
if (isset($_GET['myCloud_dynamic_js'])):
?>
<script>
    let ca_State = { users: {}, roles: [], config: {} };
    let ca_Selected_User = null;
    let ca_Is_Dirty = false;
    let ca_Is_Dirty_Cfg = false;
    let ca_Dirty_Users = new Set();
    let ca_Dirty_Cfg = new Set();
    let ca_Drag_El = null;
	
    window.ca_add_subfolder_row = function(cloudRow, path = '', right = 'read-only') {
        const list = cloudRow.querySelector('.ca-subfolders-list');
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        const row = document.createElement('div');
        row.className = 'ca-subfolder-row';
        row.innerHTML = 
            '<div style="display:flex; flex:2; min-width:120px;">' +
                '<input type="text" class="ca-input ca-sf-path" placeholder="/relative/path" value="' + path + '" oninput="ca_mark_dirty(this)" style="border-top-right-radius:0; border-bottom-right-radius:0; border-right:none; margin:0; width:100%;">' +
                '<button class="ca-btn ca-btn-outline" type="button" title="' + (L.select_folder || 'Select Folder') + '" onclick="ca_open_dir_picker(this)" style="border-top-left-radius:0; border-bottom-left-radius:0; padding:0 10px; background:var(--ca-bg-app); margin:0;">' +
                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6 12v-3h-4v-4h4V8l5 5-5 5z"/></svg>' +
                '</button>' +
            '</div>' +
            '<select class="ca-select ca-sf-rights" onchange="ca_mark_dirty(this)" style="flex: 1; min-width: 120px; margin:0;">' +
                '<option value="full">' + (L.full_access || 'Full Access') + '</option>' +
                '<option value="modify">' + (L.modify || 'Modify') + '</option>' +
                '<option value="edit-print">' + (L.edit_print || 'Edit & Print') + '</option>' +
                '<option value="edit-only">' + (L.edit_only || 'Edit Only') + '</option>' +
                '<option value="read-only">' + (L.read_only || 'Read Only') + '</option>' +
                '<option value="hidden">' + (L.hidden || 'Hidden') + '</option>' +
            '</select>' +
            '<button class="ca-btn ca-btn-danger ca-btn-sm" type="button" title="' + (L.remove || 'Remove') + '" onclick="this.closest(\'.ca-subfolder-row\').remove(); ca_mark_dirty(this);" style="flex-shrink: 0; margin:0;"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 17.59 17.59 13.41 12 19 6.41z"/></svg></button>';
        row.querySelector('.ca-sf-rights').value = right;
        list.appendChild(row);
    };

    window.ca_open_dir_picker = function(btn) {
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        const row = btn.closest('.ca-dynamic-row');
        const basePath = row.querySelector('.ca-c-path').value.trim();
        if (!basePath) {
            myCloudShowAlert(L.error_prefix || 'Error', L.err_missing_basepath || 'Please enter an absolute Cloud Path first before browsing subfolders.');
            return;
        }
        const pathInput = btn.previousElementSibling;
        
        ca_show_dir_picker(basePath, pathInput.value.trim() || '/', function(selectedPath) {
            pathInput.value = selectedPath;
            ca_mark_dirty(pathInput);
        });
    };

    window.ca_show_dir_picker = function(basePath, startPath, onSelect) {
        const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        
        const existing = document.getElementById('ca_dir_picker_overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'ca_dir_picker_overlay';
        overlay.className = 'myCloudOverlay'; 
        overlay.style.display = 'flex';
        overlay.style.zIndex = '150000';

        const modal = document.createElement('div');
        modal.className = 'myCloudModal tree-selector';
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        const loadDir = (relPath) => {
            modal.innerHTML = '<div class="myCloudModalHeader">' + (L.select_folder || 'Select Folder') + '</div><div class="myCloudModalBody" style="padding:20px;text-align:center;">' + (L.loading || 'Loading...') + '</div>';
            
            const fd = new URLSearchParams({ ca_action: 'get_subdirs', csrf_token: window.myCloudCsrfToken, base_path: basePath, rel_path: relPath });
            fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json()).then(res => {
                if (res.status === 'ok') renderDir(res.current, res.dirs);
                else modal.innerHTML = '<div class="myCloudModalHeader">' + (L.select_folder || 'Select Folder') + '<span class="myCloudClose" onclick="this.closest(\'.myCloudOverlay\').remove()">✕</span></div><div class="myCloudModalBody" style="padding:20px;color:var(--ca-danger);">' + (L.error_prefix || 'Error') + ': ' + res.msg + '</div>';
            }).catch(e => {
                modal.innerHTML = '<div class="myCloudModalHeader">' + (L.select_folder || 'Select Folder') + '<span class="myCloudClose" onclick="this.closest(\'.myCloudOverlay\').remove()">✕</span></div><div class="myCloudModalBody" style="padding:20px;color:var(--ca-danger);">' + (L.network_error || 'Network Error') + '</div>';
            });
        };

        const renderDir = (currentPath, dirs) => {
            let listHtml = '<ul style="list-style:none; padding:0; margin:0;">';
            if (currentPath !== '/' && currentPath !== '') listHtml += '<li class="ca-picker-item" data-path=".." style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--ca-border-subtle); display:flex; align-items:center; gap:8px;"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6 12v-3h-4v-4h4V8l5 5-5 5z"/></svg> <b>.. (' + (L.up || 'Up') + ')</b></li>';
            if (dirs.length === 0) listHtml += '<li style="padding:15px; text-align:center; color:var(--ca-text-muted);">' + (L.empty_lbl || 'Empty') + '</li>';
            else dirs.forEach(d => { listHtml += '<li class="ca-picker-item" data-path="' + d + '" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--ca-border-subtle); display:flex; align-items:center; gap:8px;"><svg viewBox="0 0 24 24" width="16" height="16" fill="#ffc800"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg> ' + d + '</li>'; });
            listHtml += '</ul>';

            // Robust translation fallback
            let btnText = L.ok;
            if (!btnText || btnText.trim() === '') btnText = L.save || 'OK';

            // Bound to global window to avoid DOM query detachment issues
            window._ca_picker_confirm = function() {
                onSelect(currentPath);
                overlay.remove();
            };

            let html = '<div class="myCloudModalHeader" style="justify-content:space-between; align-items:center;"><span>' + (L.select_folder || 'Select Folder') + '</span><button onclick="this.closest(\'.myCloudOverlay\').remove()" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:inherit; line-height:1;">✕</button></div>' +
                       '<div class="myCloudModalBody" style="display:flex; flex-direction:column; padding: 15px; height: 350px;">' +
                         '<div style="font-family:monospace; background:var(--ca-bg-app); padding:8px; border-radius:4px; margin-bottom:10px; word-break:break-all; font-size:12px; border:1px solid var(--ca-border-subtle); flex-shrink:0;">' + currentPath + '</div>' +
                         '<div style="flex:1; overflow-y:auto; border:1px solid var(--ca-border-normal); border-radius:4px; background:var(--ca-bg-card);">' + listHtml + '</div>' +
                         '<div class="myCloudButtons" style="margin-top:15px; justify-content:flex-end; gap:10px; flex-shrink:0;">' +
                             '<button onclick="this.closest(\'.myCloudOverlay\').remove()" style="padding:8px 16px;">' + (L.cancel || 'Cancel') + '</button>' +
                             '<button onclick="window._ca_picker_confirm()" style="background:var(--accent-primary); color:#fff; border:none; padding:8px 16px; border-radius:4px;">' + btnText + '</button>' +
                         '</div>' +
                       '</div>';
            
            modal.innerHTML = html;

            if (typeof myCloudApplyTheme === 'function') myCloudApplyTheme();

            modal.querySelectorAll('.ca-picker-item').forEach(li => {
                li.onmouseenter = () => li.style.background = 'var(--ca-bg-sidebar-hover)';
                li.onmouseleave = () => li.style.background = 'transparent';
                li.onclick = () => { loadDir(li.dataset.path === '..' ? '/' + currentPath.split('/').filter(Boolean).slice(0, -1).join('/') : (currentPath === '/' ? '/' + li.dataset.path : currentPath + '/' + li.dataset.path)); };
            });
        };
        loadDir(startPath);
    };

    // --- PROTECTION AGAINST ACCIDENTAL CLOSING ---
    if (!window.ca_CloseProtectionBound) {
        window.ca_CloseProtectionBound = true;
        
                window.addEventListener('beforeunload', (e) => { if (ca_Is_Dirty || ca_Is_Dirty_Cfg) { e.preventDefault(); e.returnValue = ''; } });
    }

    function ca_mark_dirty(el) { 
        ca_Is_Dirty = true; 
        if (el && el.dataset && el.dataset.name) {
            ca_Dirty_Users.add(el.dataset.name);
            const unsavedInd = document.getElementById('ca_unsaved_users');
            if (unsavedInd) unsavedInd.innerText = 'Unsaved: ' + Array.from(ca_Dirty_Users).join(', ');
        }
        const saveBtn = document.getElementById('ca_floating_save_user');
        if (saveBtn) saveBtn.classList.add('visible');
    }
    
    function ca_clean_dirty() { 
        ca_Is_Dirty = false; 
        ca_Dirty_Users.clear(); 
        const unsavedInd = document.getElementById('ca_unsaved_users');
        if (unsavedInd) unsavedInd.innerText = ''; 
        const saveBtn = document.getElementById('ca_floating_save_user');
        if (saveBtn) saveBtn.classList.remove('visible');
    }
    
    function ca_interface_changed(sel) {
        ca_mark_dirty(sel);
        if (sel.value === 'email') {
            const row = sel.closest('.ca-dynamic-row');
            const pathInput = row.querySelector('.ca-c-path');
            const rightsSelect = row.querySelector('.ca-c-rights');
            if (pathInput && pathInput.value.trim() === '') {
                pathInput.value = '/home/mydocpile/dummy';
                ca_mark_dirty(pathInput);
            }
            if (rightsSelect && rightsSelect.value !== 'mail-default' && rightsSelect.value !== 'full'  && rightsSelect.value !== 'mail-read-only' && rightsSelect.value !== 'mail-full') {
                rightsSelect.value = 'mail-default';
                ca_mark_dirty(rightsSelect);
            }
        }
    }

	function ca_mark_dirty_cfg(el) { 
        if(!el) return;
        const key = el.dataset.key;
        if(!key) return;
        
        const isBool = el.dataset.type === 'bool';
        const currentVal = isBool ? el.checked.toString() : el.value;
        const origVal = el.dataset.orig;
        
        if (currentVal !== origVal) {
            ca_Dirty_Cfg.add(key);
            if (!isBool) el.classList.add('ca-dirty-border');
            if(el.closest('.ca-form-group')) el.closest('.ca-form-group').querySelector('.ca-label').classList.add('ca-dirty-text');
        } else {
            ca_Dirty_Cfg.delete(key);
            if (!isBool) el.classList.remove('ca-dirty-border');
            if(el.closest('.ca-form-group')) el.closest('.ca-form-group').querySelector('.ca-label').classList.remove('ca-dirty-text');
        }
        
        ca_Is_Dirty_Cfg = ca_Dirty_Cfg.size > 0;
        document.getElementById('ca_unsaved_config').innerText = ca_Is_Dirty_Cfg ? 'Unsaved: ' + Array.from(ca_Dirty_Cfg).join(', ') : '';
        if (ca_Is_Dirty_Cfg) document.getElementById('ca_floating_save_config').classList.add('visible');
        else document.getElementById('ca_floating_save_config').classList.remove('visible');
    }
    
    function ca_clean_dirty_cfg() { 
        ca_Is_Dirty_Cfg = false; 
        ca_Dirty_Cfg.clear();
        document.getElementById('ca_unsaved_config').innerText = ''; 
		document.getElementById('ca_floating_save_config').classList.remove('visible');
    }

    function ca_switch_tab(tab) {
        const executeSwitch = () => {
            if (tab === 'users') { 
                ca_clean_dirty_cfg();
                const su = document.getElementById('ca_user_search'); 
                if (su) { su.value = ''; ca_filter_users(''); }
            }
            if (tab === 'config') { 
                ca_clean_dirty(); 
                const s = document.getElementById('ca_config_search'); if (s) s.value = '';
                ca_render_config(); 
            }

            document.getElementById('ca_tab_users').classList.toggle('active', tab === 'users');
            document.getElementById('ca_tab_config').classList.toggle('active', tab === 'config');
            document.getElementById('ca_area_users').style.display = tab === 'users' ? 'flex' : 'none';
            document.getElementById('ca_area_config').style.display = tab === 'config' ? 'flex' : 'none';
        };

        if (tab === 'users' && ca_Is_Dirty_Cfg) {
            myCloudShowAlert('Unsaved Changes', "Discard unsaved configuration changes in:<br><br><b>" + Array.from(ca_Dirty_Cfg).join(', ') + "</b>?", executeSwitch);
        } else if (tab === 'config' && ca_Is_Dirty) {
            myCloudShowAlert('Unsaved Changes', "Discard unsaved user changes for <b>" + (ca_Selected_User || 'New User') + "</b>?", executeSwitch);
        } else {
            executeSwitch();
        }
    }

    function ca_init() {
        if (!document.getElementById('ca_editor_area')) return;
		document.getElementById('ca_editor_area').style.display = 'none';
        document.getElementById('ca_loading_users').style.display = 'flex';
        
        const fd = new URLSearchParams({ ca_action: 'load', csrf_token: window.myCloudCsrfToken });
        fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json()).then(data => {
				if (!document.getElementById('ca_loading_users')) return;
                document.getElementById('ca_loading_users').style.display = 'none';
                document.getElementById('ca_loading_config').style.display = 'none';
                if(data.status === 'ok') {
                    ca_State.users = data.users; ca_State.roles = data.roles; ca_State.config = data.config;
                    ca_render_sidebar(); ca_render_roles_dropdown(); ca_render_config();
                    
                    const keys = Object.keys(data.users).sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
                    if(keys.length === 0) ca_init_new();
                    else {
                        if (ca_Selected_User && ca_State.users[ca_Selected_User]) ca_load_user(ca_Selected_User);
                        else ca_load_user(keys[0]);
                    }
                } else myCloudShowAlert('Error', data.msg);
            }).catch(e => { 
                if (!document.getElementById('ca_loading_users')) return;
				console.error(e); 
                myCloudShowAlert('Network Error', 'Failed to load configuration data.'); 
                document.getElementById('ca_loading_users').style.display = 'none';
                document.getElementById('ca_loading_config').style.display = 'none';
            });
    }

    function ca_render_sidebar() {
        const ul = document.getElementById('ca_user_list_ul'); ul.innerHTML = '';
        const sortedKeys = Object.keys(ca_State.users).sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
        sortedKeys.forEach(uname => {
            const u = ca_State.users[uname];
            const hasCloud = u.cloud && Object.keys(u.cloud).length > 0;
            const isAdmin = u.role === 'admin';

            const li = document.createElement('li'); 
            li.className = 'ca-user-li';
            if (hasCloud) li.classList.add('has-cloud');
            li.dataset.hasCloud = hasCloud ? 'true' : 'false';

            let innerHTML = '<span>' + uname + '</span>';
            if (isAdmin) {
                innerHTML += '<div class="ca-admin-tag">Admin</div>';
            }
            li.innerHTML = innerHTML;

            li.onclick = () => {
                if(ca_Selected_User === uname) return;
                
                const doLoad = () => {  
                    ca_load_user(uname); 
                };
                
                if(ca_Is_Dirty) myCloudShowAlert('Unsaved Changes', "Discard unsaved changes for <b>" + (ca_Selected_User || 'New User') + "</b>?", doLoad);
                else doLoad();
            };
            ul.appendChild(li);
        });

        // Re-apply filter if active after render
        const searchInput = document.getElementById('ca_user_search');
        if (searchInput) ca_filter_users(searchInput.value);
    }

    function ca_render_roles_dropdown() {
        const sel = document.getElementById('ca_inp_role'); sel.innerHTML = '';
        ca_State.roles.forEach(r => { const o = document.createElement('option'); o.value = r; o.innerText = r; sel.appendChild(o); });
    }

    function ca_init_new() {
        const doInit = () => {
            ca_Selected_User = null;
            document.getElementById('ca_main_form').reset();
            document.getElementById('ca_inp_original_name').value = '';
            document.getElementById('ca_cloud_container').innerHTML = '';
            document.getElementById('ca_btn_delete').style.display = 'none';
            document.getElementById('ca_inp_role').value = 'user';
            const lv = document.getElementById('ca_inp_logviewer'); if(lv) lv.checked = false;
            const lf = document.getElementById('ca_inp_logviewer_filter'); if(lf) lf.value = '';
            
            document.getElementById('ca_editor_area').style.display = 'block';
            document.querySelectorAll('.ca-user-li').forEach(el => el.classList.remove('active'));
            ca_clean_dirty();
        };
        if(ca_Is_Dirty) myCloudShowAlert('Unsaved Changes', "Discard unsaved changes for <b>" + (ca_Selected_User || 'New User') + "</b>?", doInit);
        else doInit();
    }

    function ca_load_user(uname) {
        const u = ca_State.users[uname]; if(!u) return;
        ca_Selected_User = uname;
        document.getElementById('ca_inp_original_name').value = u.name;
        document.getElementById('ca_inp_name').value = u.name;
        document.getElementById('ca_inp_password').value = '';
        document.getElementById('ca_inp_email').value = u.email;
        document.getElementById('ca_inp_role').value = u.role;
        document.getElementById('ca_inp_webdav').checked = !!u.cloud_webdav;
        document.getElementById('ca_btn_delete').style.display = 'inline-block';
        const lv = document.getElementById('ca_inp_logviewer'); if(lv) lv.checked = !!u.logviewer;
        const lf = document.getElementById('ca_inp_logviewer_filter'); if(lf) lf.value = u.logviewer_filter || '';
        
        const cb = document.getElementById('ca_cloud_container'); cb.innerHTML = '';
        if(u.cloud) Object.keys(u.cloud).forEach(k => ca_add_cloud_row(k, u.cloud[k]));
        
        document.getElementById('ca_editor_area').style.display = 'block';
        document.querySelectorAll('.ca-user-li').forEach(el => { el.classList.toggle('active', el.querySelector('span:first-child').innerText === uname); });
        ca_clean_dirty();
    }

    function ca_add_cloud_row(n='', d=null) {
        const div = document.createElement('div'); div.className = 'ca-dynamic-row';
        div.style.flexDirection = 'column'; div.style.alignItems = 'stretch';
		const L = typeof myCloud_LANG !== 'undefined' ? myCloud_LANG : {};
        
        div.addEventListener('dragstart', ca_drag_start);
        div.addEventListener('dragend', ca_drag_end);
        
        const p_path = d ? d.path : '';
        div.innerHTML = 
            '<div style="display: flex; gap: 6px; align-items: center; width: 100%; flex-wrap: wrap;">' +
                '<span class="ca-drag-handle" onmousedown="this.closest(\'.ca-dynamic-row\').draggable=true" onmouseup="this.closest(\'.ca-dynamic-row\').draggable=false" onmouseleave="this.closest(\'.ca-dynamic-row\').draggable=false">☰</span>' +
                '<div class="ca-hover-tooltip ca-hover-tooltip-center" data-tooltip="A friendly alias for this cloud storage mount." style="flex: 1; min-width: 130px; display: flex;">' +
                    '<input type="text" placeholder="Alias (e.g. Storage1)" class="ca-input ca-c-name" data-name="Cloud Mount Name" oninput="ca_mark_dirty(this)" value="' + n + '" style="width: 100%;">' +
                '</div>' +
                '<div class="ca-hover-tooltip ca-hover-tooltip-center" data-tooltip="Permission level for this specific mount." style="flex: 1; min-width: 110px; display: flex;">' +
                    '<select class="ca-select ca-c-rights" data-name="Cloud Rights" onchange="ca_mark_dirty(this)" style="width: 100%;">' +
                        '<option value="full">Full Access</option>' +
                        '<option value="modify">Modify</option>' +
                        '<option value="edit-print">Edit & Print</option>' +
                        '<option value="edit-only">Edit Only</option>' +
                        '<option value="read-only">Read Only</option>' +
                        '<option value="mail-read-only">Mail (Read only)</option>' +
                        '<option value="mail-reduced">Mail (Reduced Rights)</option>' +
                        '<option value="mail-default">Mail (Default Rights)</option>' +
                        '<option value="mail-full">Mail (Full Rights)</option>' +
                        '<option value="admin_mode">Admin (Full + SSH)</option>' +
                    '</select>' +
                '</div>' +
                '<div class="ca-hover-tooltip ca-hover-tooltip-center" data-tooltip="Default user interface view mode." style="flex: 1; min-width: 90px; display: flex;">' +
                    '<select class="ca-select ca-c-interface" data-name="Cloud Interface" onchange="ca_interface_changed(this)" style="width: 100%;">' +
                        '<option value="default">Default</option>' +
                        '<option value="gallery">Gallery</option>' +
                        '<option value="symbol">Symbol</option>' +
                        '<option value="symbol-dark">Symbol Dark</option>' +
						'<option value="hidden">Hidden (Mail Attachments only)</option>' +
						'<option value="email">Email</option>' +
                    '</select>' +
                '</div>' +
                '<button class="ca-btn ca-btn-danger ca-btn-sm" type="button" title="Remove Mount" onclick="this.closest(\'.ca-dynamic-row\').remove();ca_mark_dirty({dataset:{name:\'Cloud Mount Removed\'}});" style="flex-shrink: 0;">✕</button>' +
            '</div>' +
            '<div class="ca-hover-tooltip" data-tooltip="Absolute server path. Must be within allowed directories (open_basedir). Use user@ip:port for Admin SSH." style="width: 100%; margin-top: 4px; display: flex;">' +
                '<input type="text" placeholder="/absolute/path (NOT within www-root!) | For Admin Mode (SFTP): user@ip:port" class="ca-input ca-c-path" data-name="Cloud Path" oninput="ca_mark_dirty(this)" value="' + p_path + '" style="width: 100%;">' +
            '</div>' +
            '<div class="ca-subfolder-box" >' +
                '<div class="ca-subfolder-header">' +
                    '<span>' + (L.subfolder_rights || 'Subfolder Permissions (Overrides base rights)') + '</span>' +
                    '<button class="ca-btn ca-btn-outline ca-btn-sm" type="button" onclick="ca_add_subfolder_row(this.closest(\'.ca-dynamic-row\'))" style="padding:2px 8px; font-size:11px; background:var(--ca-bg-card);">+ ' + (L.add_path || 'Add Path') + '</button>' +
                '</div>' +
                '<div class="ca-subfolders-list"></div>' +
            '</div>';
        
        if(d) div.querySelector('.ca-c-rights').value = d.rights;
        if(d && d.interface) div.querySelector('.ca-c-interface').value = d.interface;
        document.getElementById('ca_cloud_container').appendChild(div);

        if (d && d.subfolder_rights) {
            Object.keys(d.subfolder_rights).forEach(subPath => {
                ca_add_subfolder_row(div, subPath, d.subfolder_rights[subPath]);
            });
        }

        if (n === '') {
            const nameInput = div.querySelector('.ca-c-name');
            if (nameInput) {
                setTimeout(() => nameInput.focus(), 50);
            }
        }
    }

    function ca_drag_start(e) {
        ca_Drag_El = e.target.closest('.ca-dynamic-row');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', ca_Drag_El.innerHTML);
        ca_Drag_El.classList.add('dragging');
    }

    function ca_drag_over(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const container = e.currentTarget;
        if (!ca_Drag_El || ca_Drag_El.parentNode !== container) return;
        const afterElement = ca_get_drag_after_element(container, e.clientY);
        if (afterElement == null) container.appendChild(ca_Drag_El); 
        else container.insertBefore(ca_Drag_El, afterElement);
        ca_mark_dirty({dataset:{name:'Cloud Mount Reordered'}});
    }

    function ca_drag_end(e) {
        const row = e.target.closest('.ca-dynamic-row');
        row.classList.remove('dragging');
        row.draggable = false;
        ca_Drag_El = null;
    }

    function ca_get_drag_after_element(container, y) {
        const draggableElements = [...container.querySelectorAll('.ca-dynamic-row:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset: offset, element: child }; else return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
	
    function ca_toggle_pwd(btn) {
        const p = document.getElementById('ca_inp_password');
        const svg = btn.querySelector('svg');
        if (p.type === 'password') {
            p.type = 'text';
            btn.style.color = 'var(--ca-accent)';
            svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        } else {
            p.type = 'password';
            btn.style.color = 'var(--ca-text-muted)';
            svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
        }
    }

    const CA_PWD_MIN_LENGTH = 8;

    function ca_validate_password(password) {
        const errors = [];
        if (password.length < CA_PWD_MIN_LENGTH) errors.push(`- Must be at least ${CA_PWD_MIN_LENGTH} characters long.`);
        if (!/[-_.:,;()/+#!§%&]/.test(password)) errors.push('- Must contain at least one special character (e.g., -_.:,;()#!)');
        if (/[$"']/.test(password)) errors.push('- Must not contain the characters $, ", or \'');
        if (!/\d/.test(password)) errors.push('- Must contain at least one digit.');
        return errors;
    }

    async function ca_sha1(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-1', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').toUpperCase();
    }

    async function ca_is_pwned(password) {
        try {
            const hash = await ca_sha1(password);
            const prefix = hash.substring(0, 5);
            const suffix = hash.substring(5);
            const response = await fetch('https://api.pwnedpasswords.com/range/' + prefix);
            if (!response.ok) return false;

            const text = await response.text();
            const lines = text.split('\n');
            for (const line of lines) {
                if (line.split(':')[0].trim() === suffix) return true;
            }
            return false;
        } catch (e) {
            console.error("HIBP Check Error: ", e);
            return false; 
        }
    }

    async function ca_action_save_user() {
	const p = {
            orig_name: document.getElementById('ca_inp_original_name').value,
            name: document.getElementById('ca_inp_name').value.trim(),
            pass: document.getElementById('ca_inp_password').value,
            email: document.getElementById('ca_inp_email').value,
            role: document.getElementById('ca_inp_role').value,
            webdav: document.getElementById('ca_inp_webdav').checked,
            clouds: {}
        };

        const lv = document.getElementById('ca_inp_logviewer'); if(lv) p.logviewer = lv.checked;
        const lf = document.getElementById('ca_inp_logviewer_filter'); if(lf) p.logviewer_filter = lf.value;

        if(!p.name) {
            myCloudShowAlert('Validation Error', 'Username is required.');
            return;
        }
        
        if (p.pass !== '') {
            const validationErrors = ca_validate_password(p.pass);
            if (validationErrors.length > 0) {
                myCloudShowAlert('Security Policy', 'Password policy not met:<br>' + validationErrors.join('<br>'));
				return;
            }
            
            // Temporarily disable save buttons to indicate working state
            const btnFloating = document.getElementById('ca_floating_save_user');
			const origText = btnFloating ? btnFloating.innerText : 'Save User';
            if (btnFloating) { btnFloating.disabled = true; btnFloating.innerText = "Checking..."; }

            const isCompromised = await ca_is_pwned(p.pass);

            if (btnFloating) { btnFloating.disabled = false; btnFloating.innerText = origText; }

            if (isCompromised) {
                myCloudShowAlert('Security Warning', 'This password has been exposed in a data breach.<br><br>Please choose a different, more secure password.');
                return;
            }
        }

        ca_Selected_User = p.name;
        
        document.querySelectorAll('#ca_cloud_container .ca-dynamic-row').forEach(r => {
            const cn = r.querySelector('.ca-c-name').value.trim();
            if(cn) {
                const subfolders = {};
                r.querySelectorAll('.ca-subfolder-row').forEach(subRow => {
                    let subPath = subRow.querySelector('.ca-sf-path').value.trim();
                    const subRight = subRow.querySelector('.ca-sf-rights').value;
                    if (subPath) {
                        if (!subPath.startsWith('/')) subPath = '/' + subPath;
                        subfolders[subPath] = subRight;
                    }
                });

                p.clouds[cn] = {
                    path: r.querySelector('.ca-c-path').value,
                    rights: r.querySelector('.ca-c-rights').value,
                    interface: r.querySelector('.ca-c-interface').value,
                    subfolder_rights: subfolders
                };
            }
        });
        
        ca_send_ajax('save_user', p);
    }

    function ca_action_delete() {
        myCloudShowAlert('Delete User', "Are you sure you want to delete this user?<br><br><b>" + ca_Selected_User + "</b>", () => {
            
            const n = document.getElementById('ca_inp_original_name').value;
            ca_Selected_User = null;
            ca_send_ajax('delete_user', {name:n});
        });
    }

function ca_render_config() {
        const form = document.getElementById('ca_config_form'); form.innerHTML = '';
        ca_clean_dirty_cfg();
        
        let currentCat = '';
        let catContainer = null;
        
        Object.keys(ca_State.config).forEach(k => {
            const item = ca_State.config[k];
            const hasCustom = (item.val !== null && item.val !== undefined);
            const effectiveVal = hasCustom ? item.val : item.default;
            const defaultVal = item.default;
            const meta = item.meta;
            
            if (meta.cat !== currentCat) {
                const header = document.createElement('div');
                header.className = 'ca-section-heading';
                header.style.marginTop = currentCat ? '30px' : '0';
                header.style.cursor = 'pointer';
                header.innerHTML = '<div style="display:flex; align-items:center; gap:8px;">' + meta.cat + '</div><div class="ca-fold-icon" style="transition:transform 0.2s; transform:rotate(-90deg);">▼</div>';
                
                catContainer = document.createElement('div');
                catContainer.className = 'ca-config-card';
                catContainer.style.display = 'none';
                
                const targetCard = catContainer;
                
                header.onclick = function() {
                    const isHidden = targetCard.style.display === 'none';
                    targetCard.style.display = isHidden ? 'block' : 'none';
                    this.querySelector('.ca-fold-icon').style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
                };
                
                form.appendChild(header);
                form.appendChild(catContainer);
                currentCat = meta.cat;
            }

            const div = document.createElement('div'); div.className = 'ca-form-group';
            div.style.marginBottom = '10px';
            div.style.paddingBottom = '10px';
            div.style.borderBottom = '1px solid var(--border-subtle)';

            // Embed search data for high-performance filtering
            div.dataset.searchKey = k.toLowerCase();
            div.dataset.searchLabel = meta.label.toLowerCase();
            div.dataset.searchCat = meta.cat.toLowerCase();
            div.dataset.searchDesc = meta.desc.toLowerCase();
            div.classList.add('ca-cfg-row');
            
            const tooltipHtml = '<span class="ca-tooltip-icon" data-tooltip="' + meta.desc.replace(/"/g, '&quot;') + '">i</span>';
            
            let displayLabel = meta.label;
            if (k !== 'work_dir' && effectiveVal.toString().startsWith(ca_State.work_dir)) {
                displayLabel += '<span class="ca-workdir-badge" title="Dynamically linked to root path">🔗 $work_dir</span>';
            }
            if (hasCustom) {
                displayLabel += ' <span style="color:var(--ca-accent);font-size:0.9em;margin-left:4px;" title="Custom Override in config.php">*</span>';
            }

            if (meta.is_bool) {
                const isChecked = (effectiveVal === true || effectiveVal === 'true');
                const isDefaultChecked = (defaultVal === true || defaultVal === 'true');
                const origState = isChecked ? 'true' : 'false';
                div.style.display = 'grid';
                div.style.gridTemplateColumns = '250px 1fr';
                div.style.alignItems = 'center';
                div.style.gap = '15px';
                div.innerHTML = '<div class="ca-label" style="display:flex; align-items:center; margin:0;">' + displayLabel + ' ' + tooltipHtml + '</div>' +
                                 '<label class="ca-toggle-switch">' +
                                     '<input type="checkbox" class="ca-cfg-val" data-key="' + k + '" data-type="bool" data-orig="' + origState + '" data-default="' + (isDefaultChecked?'true':'false') + '" ' + (isChecked?'checked':'') + '>' +
                                     '<span class="ca-slider"></span>' +
                                 '</label>';
            } else if (meta.is_long) {
                const displayVal = hasCustom ? item.val.toString().replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
                const placeholderVal = defaultVal.toString().replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                div.style.display = 'flex';
                div.style.flexDirection = 'column';
                div.innerHTML = '<label class="ca-label" style="display:flex; align-items:center; margin-bottom:6px;">' + displayLabel + ' ' + tooltipHtml + '</label>' +
                                '<input type="text" class="ca-input ca-cfg-val" data-key="' + k + '" data-orig="' + displayVal + '" data-default="' + placeholderVal + '" data-type="' + (meta.is_array ? 'array' : 'scalar') + '" placeholder="' + placeholderVal + '" value="' + displayVal + '">';
            } else {
                const displayVal = hasCustom ? item.val.toString().replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
                const placeholderVal = defaultVal.toString().replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                div.style.display = 'grid';
                div.style.gridTemplateColumns = '250px 1fr';
                div.style.alignItems = 'center';
                div.style.gap = '15px';
                div.innerHTML = '<div class="ca-label" style="display:flex; align-items:center; margin:0;">' + displayLabel + ' ' + tooltipHtml + '</div>' +
                                 '<input type="text" class="ca-input ca-cfg-val" data-key="' + k + '" data-orig="' + displayVal + '" data-default="' + placeholderVal + '" data-type="' + (meta.is_array ? 'array' : 'scalar') + '" placeholder="' + placeholderVal + '" value="' + displayVal + '" style="width:100%;">';
            }
            catContainer.appendChild(div);
        });
        
        // Remove the border-bottom from the last element in every card
        document.querySelectorAll('.ca-config-card .ca-form-group:last-child').forEach(el => el.style.borderBottom = 'none');
    }	
	

    function ca_filter_config(q) {
        q = q.toLowerCase().trim();
        
        document.querySelectorAll('.ca-config-card').forEach(card => {
            let hasVisible = false;
            
            card.querySelectorAll('.ca-cfg-row').forEach(row => {
                const input = row.querySelector('.ca-cfg-val');
                const val = (input.dataset.type === 'bool' ? input.checked.toString() : input.value).toLowerCase();
                
                const match = !q || 
                              row.dataset.searchKey.includes(q) || 
                              row.dataset.searchLabel.includes(q) || 
                              row.dataset.searchCat.includes(q) || 
                              row.dataset.searchDesc.includes(q) || 
                              val.includes(q);
                
                row.classList.toggle('ca-d-none', !match);
                if (match) hasVisible = true;
            });

            const header = card.previousElementSibling;
            
            if (!q) {
                card.classList.remove('ca-d-none');
                header.classList.remove('ca-d-none');
            } else {
                card.classList.toggle('ca-d-none', !hasVisible);
                header.classList.toggle('ca-d-none', !hasVisible);
                // Auto-expand folded categories if they contain matching results
                if (hasVisible) {
                    card.style.display = 'block';
                    header.querySelector('.ca-fold-icon').style.transform = 'rotate(0deg)';
                }
            }
        });
    }
	
    function ca_filter_users(q) {
        q = q.toLowerCase().trim();
        const cloudsOnlyBox = document.getElementById('ca_filter_clouds_only');
        const cloudsOnly = cloudsOnlyBox ? cloudsOnlyBox.checked : false;

        document.querySelectorAll('#ca_user_list_ul .ca-user-li').forEach(li => {
            // Using span:first-child ensures we don't grab the "Admin" tag text
            const unameSpan = li.querySelector('span:first-child');
            const uname = unameSpan ? unameSpan.textContent.toLowerCase() : '';
            const matchSearch = uname.includes(q);
            const matchCloud = !cloudsOnly || li.dataset.hasCloud === 'true';
            li.classList.toggle('ca-d-none', !(matchSearch && matchCloud));
        });
    }

    function ca_action_save_config() {
        const payload = {};
        document.querySelectorAll('.ca-cfg-val').forEach(el => {
            let val = null;
            if (el.dataset.type === 'bool') {
                const currentBool = el.checked ? 'true' : 'false';
                if (currentBool !== el.dataset.default) val = currentBool;
            } else {
                const currentStr = el.value.trim();
                if (currentStr !== '' && currentStr !== el.dataset.default) val = currentStr;
            }
            
            payload[el.dataset.key] = {
                val: val,
                type: el.dataset.type
            };
        });
        ca_send_ajax('save_config', payload, true);
    }

    function ca_send_ajax(a, d, isCfg = false) {
        if (!document.getElementById('ca_loading_' + (isCfg?'config':'users'))) return;
		document.getElementById('ca_loading_' + (isCfg?'config':'users')).style.display = 'flex';
        const fd = new URLSearchParams({ ca_action: a, csrf_token: window.myCloudCsrfToken, payload: JSON.stringify(d) });
        fetch(window.location.href, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json()).then(res => {
                if (!document.getElementById('ca_loading_' + (isCfg?'config':'users'))) return;
				if(res.status === 'ok') {
                    if (isCfg) ca_clean_dirty_cfg(); else ca_clean_dirty();
                    ca_init();
                } else { 
                    myCloudShowAlert('Operation Failed', res.msg); 
                    document.getElementById('ca_loading_' + (isCfg?'config':'users')).style.display = 'none'; 
                }
            }).catch(e => { 
                if (!document.getElementById('ca_loading_' + (isCfg?'config':'users'))) return;
				console.error(e); 
                myCloudShowAlert('Network Error', 'A communication error occurred.'); 
                document.getElementById('ca_loading_' + (isCfg?'config':'users')).style.display = 'none'; 
            });
    }
</script>
<?php endif; ?>