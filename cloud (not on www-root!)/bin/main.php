<?php
if(!defined('IPS_Token')) { 
	header("Connection: close");
	die();}

// If $work_dir is not set, assign the parent directory of this file (equivalent to "../"). If it is set, do nothing.
$work_dir ??= dirname(__DIR__);


require_once $work_dir.'/configuration/config.dist.php';      
include_once $work_dir.'/configuration/config.php';      
require_once $work_dir.'/bin/functions.php'; 
require_once $user_db;     

// --- FAST INTEGRITY STATUS CHECK (HALTS EXECUTION ON ERROR) ---
$secure_code_setting = $SecureCode ?? 'none';
$req_uri = $_SERVER['REQUEST_URI'] ?? '';

// Only enforce if setting is valid AND we are not in the auth_adm_php admin panel
if (in_array($secure_code_setting, ['all', 'cloud', 'bin'], true) && strpos($req_uri, '/auth_adm_php') === false) {
    $integrity_file = $work_dir . '/configuration/.integrity_status.json';
    
    // Helper function to output a user-friendly error or clean JSON
    $die_friendly = function($dev_reason) {
        http_response_code(500);
        $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['myCloud_action']);
        if ($is_ajax) {
            header('Content-Type: application/json');
            die(json_encode(['status' => 'ERR', 'code' => 'SECURITY_HALT', 'msg' => 'Security Halt: ' . $dev_reason]));
        }
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Security Alert</title><style>body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #fcfcfd; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; } .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 450px; border-top: 5px solid #e81123; } h2 { color: #e81123; margin-top: 0; font-size: 22px; } p { color: #444; line-height: 1.5; margin-bottom: 20px; font-size: 15px; } .tech { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #666; text-align: left; word-break: break-all; }</style></head><body><div class="container"><h2>🛡️ Security Alert</h2><p>The system has halted execution to protect your data. A strict code integrity check failed, meaning the system files are either incomplete or modified.</p><div class="tech"><strong>Details:</strong> ' . htmlspecialchars($dev_reason) . '</div></div></body></html>';
        die($html);
    };

    if (!file_exists($integrity_file)) {
        $die_friendly("Code sealing is enabled but the integrity status file is missing. Please run the background integrity checker.");
    }

    $raw_status = json_decode(file_get_contents($integrity_file), true);
    
    // Verify the status file was written by our cron job and not tampered with locally
    if (!isset($raw_status['data'], $raw_status['hmac']) || !hash_equals(hash_hmac('sha256', json_encode($raw_status['data']), $api_key), $raw_status['hmac'])) {
        $die_friendly("Integrity status file tampered or invalid. Execution halted.");
    }

    // If the cron job found tampered files, halt execution immediately
    if (isset($raw_status['data']['status']) && $raw_status['data']['status'] === 'failed') {
        $die_friendly("Code integrity check failed. The system may have been tampered with.");
    }
}
// --------------------------------------------------------------

// INIT GEOIP INSTANCE, SIMPLY DELETE THE geoip.php TO REMOVE IT
if (file_exists($work_dir.'/bin/geoip.php')) {
	include_once $work_dir.'/bin/geoip.php';
}



if (!in_array($_SERVER['HTTP_HOST'], $allowed_domain)) {
	WriteLogLine($log_file, "error", "Wrong domain name: ".$_SERVER['HTTP_HOST']);
    http_response_code(404);
    die();
}

// Force PHP's native session cache limiter to completely disable itself 
// so it cannot overwrite custom Cache-Control headers later.
session_cache_limiter('');


// --- API CALLS WITHOUT SESSION OR LOGIN ---
if (file_exists($work_dir.'/parts/direct_api.php')) {
	require_once $work_dir.'/parts/direct_api.php';  
}
if (file_exists($work_dir.'/cloud/modules.share_public_ui.php')) {
	include_once $work_dir.'/cloud/modules.share_public_ui.php';    
}



// ----------------------------------------------------------------------
// Determine if operating securely inside a Home NAS environment
$is_proxy = isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_FORWARDED']) || isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_FORWARDED_FOR']) || isset($_SERVER['HTTP_FORWARDED']);
$is_home_nas = (!$is_proxy && isset($_SERVER['REMOTE_ADDR']) && function_exists('isPrivateIp') && isPrivateIp($_SERVER['REMOTE_ADDR']) && !empty($GLOBALS['home_NAS_network']));

// Content Security Policy (balanced, production-safe)
// Bulletproof scope and type check for the NAS flag
$csp_scheme = $is_home_nas ? "http: https:" : "https:";
$csp_ws     = $is_home_nas ? "ws: wss:" : "wss:";

header(
    "Content-Security-Policy: ".
    "default-src 'self'; ".
    "script-src 'self' $csp_scheme 'unsafe-inline' 'unsafe-eval'; ".
    "worker-src 'self' blob:; ".
    "style-src 'self' $csp_scheme 'unsafe-inline' blob:; ".
    "font-src 'self' $csp_scheme data: blob:; ".
    "img-src 'self' $csp_scheme data: blob:; ".
    "connect-src 'self' $csp_scheme $csp_ws blob:; ".
    "frame-src 'self' $csp_scheme blob:; ".
    "frame-ancestors 'self'; ".
    "object-src 'none'; ".
    "base-uri 'self'; ".
    "form-action 'self' $csp_scheme;"
);

// ----------------------------------------------------------------------
// Additional security headers
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");
// ----------------------------------------------------------------------


// Catch OnlyOffice stateless callbacks & fetches here before main_login.php blocks them
$req_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($req_path, '/myCloudOfficeFetch/') !== false || 
    strpos($req_path, '/myCloudOfficeCallback') !== false) {
	define('MYCLOUD_OFFICE_BRIDGE', true);

	// !!!!!!!!!  ------------------------------------------------------------------------------  !!!!!!!!!!!
	// !!!!!!!!!  THIS PATH IS HARD CODED BECAUSE WE CANNOT FIND OUT WHICH BRANCH WE ARE IN HERE  !!!!!!!!!!!
    require_once $work_dir.'/cloud/controller.server.php';
	// !!!!!!!!!  ------------------------------------------------------------------------------  !!!!!!!!!!!

    $officeServer = new MyCloudServer();
    $officeServer->handleRequests(); // handles the request and exits automatically
}
 
$timeout_duration = $timeout_in_minutes * 60; 

// --- LOGGED IN, SESSION AND ROLE CHECKS ---
session_set_cookie_params([
    'lifetime' => $timeout_duration,  
    'path'     => '/',
    'domain'     => '',
    'secure'   => !$is_home_nas,   // true if using HTTPS, false on private Home NAS
    'httponly' => true,
    'samesite' => 'Lax',
]);
//    'samesite' => 'None'        // 'None' lets the cookie be sent in cross-site contexts (requires secure = true)


ini_set('session.gc_maxlifetime', $timeout_duration);
// attacker provided IDs are rejected
ini_set('session.use_strict_mode', 1);
// avoid session IDs in URLs
ini_set('session.use_trans_sid', 0);

// Increase entropy length (in bytes)
ini_set('session.sid_length', 64);  // default is 26 (~128 bits)
// Force strong hash function
ini_set('session.sid_bits_per_character', 6); // 6 = more characters per bit

// Make sure PHP uses only cookies (no URL IDs)
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

// Set different session storage directory
if (!is_dir($session_storage)) {
    mkdir($session_storage, 0700, true);
}

ini_set('session.save_handler', 'files');
session_save_path($session_storage);

// Start Session Now ------------------------------------------------------
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session fixation hardening for unauthenticated users
// Regenerate session ID before showing login form
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (!isset($_SESSION['_prelogin_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['_prelogin_regenerated'] = true;
    }
}

if (file_exists($work_dir.'/parts/server_heartbeat.php')) {
	include_once $work_dir.'/parts/server_heartbeat.php';    
} 

// Cloud only check, to be done already before the login logic (for 2fa ena/disa)

$isCloudOnly = false;
if (in_array($_SERVER['HTTP_HOST'], $cloud_only_domains)) {
	$isCloudOnly = true;
}
	
if (!$isCloudOnly) {
	foreach ($cloud_only_paths as $path) {
		if (strpos($_SERVER['REQUEST_URI'], $path) === 0) {
			$isCloudOnly = true;
			break;
		}
	}
}

// Check for cloud path with beta suffix
if (!$isCloudOnly && isset($cloud_beta) && $cloud_beta !== '') {
	if (strpos($_SERVER['REQUEST_URI'], '/cloud' . $cloud_beta) === 0) {
		$isCloudOnly = true;
	}
}
	
	
$maintenance_file = $work_dir . '/configuration/.maintenance_mode';
$is_maintenance_active = (isset($maintenance_mode) && $maintenance_mode === true) || file_exists($maintenance_file);
$maintenance_reason = file_exists($maintenance_file) ? 'file exists' : 'variable is set';
$is_admin_panel = (strpos($_SERVER['REQUEST_URI'], '/auth_adm_php') !== false);

// Safe save actions during maintenance to prevent data loss
$is_safe_save = false;
$req_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($req_path, '/myCloudOfficeCallback') !== false || strpos($req_path, '/myCloudOfficeFetch') !== false) {
	$is_safe_save = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myCloud_action'])) {
	$action = $_POST['myCloud_action'];
	if (in_array($action, ['edit-save', 'check_office_state', 'upload', 'delete', 'get_download_token'])) {
		$is_safe_save = true;
	}
}

// If maintenance is active and it's NOT the admin panel, block immediately.
if ($is_maintenance_active && !$is_admin_panel && !$is_safe_save) {
	if (empty($_SESSION['maintenance_logged'])) {
		WriteLogLine($log_file, "warning", "Maintenance: Login screen blocked because $maintenance_reason.");
		$_SESSION['maintenance_logged'] = true;
	}
	if (file_exists($work_dir . '/bin/security.php') || file_exists('security.php') || file_exists($work_dir . '/security.php')) {
		header("Location: processing.php?maintenance=true");
		exit;
	} else {
		http_response_code(503);
		die("Service is under maintenance.");
	}
}


require_once $work_dir.'/bin/main_login.php';   

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    if (!isset($_SESSION['username'])) {
		echo "<p>Access denied. You do not have sufficient rights to view this content.</p>";
        exit;
    }

	$loginRole = $work_dir . '/main_menu/' . getUserRole($_SESSION['username']) . '.php';
	
    // Post-login check: We already know it's the admin panel if maintenance is active (others blocked above).
 	// Now just verify if the logged-in user is actually an admin.
	if ($is_maintenance_active && !$is_safe_save) {
		if (getUserRole($_SESSION['username']) !== "admin") {
			if (empty($_SESSION['maintenance_logged_post'])) {
				WriteLogLine($log_file, "warning", "Maintenance: Access blocked for user " . $_SESSION['username'] . " because $maintenance_reason.");
				$_SESSION['maintenance_logged_post'] = true;
			}
			if (file_exists($work_dir . '/bin/security.php') || file_exists('security.php') || file_exists($work_dir . '/security.php')) {
				header("Location: processing.php?maintenance=true");
				exit;
			} else {
				http_response_code(503);
				die("Service is under maintenance.");
			}
		}
	}
	
	
	// Final decision of the template to load
	if ($isCloudOnly) {
		$loginRole = $work_dir . '/cloud/index.php';
		if (!empty($cloud_beta)) { 
			$loginRole = $work_dir . '/cloud.beta/index.php';
		}
	}
	

	if (file_exists($loginRole) && is_readable($loginRole)) {
		require_once $loginRole;
	} else {
		require_once $work_dir . '/main_menu/notfound.php';
	}
// --- LOGGED IN ---
}
