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
// Content Security Policy (balanced, production-safe)
header(
    "Content-Security-Policy: ".
    "default-src 'self'; ".
    "script-src 'self' https: 'unsafe-inline' 'unsafe-eval'; ".
    "worker-src 'self' blob:; ".
    "style-src 'self' https: 'unsafe-inline' blob:; ".
    "font-src 'self' https: data: blob:; ".
    "img-src 'self' https: data: blob:; ".
    "connect-src 'self' https: wss: blob:; ".
    "frame-src 'self' https: blob:; ".
    "frame-ancestors 'self'; ".
    "object-src 'none'; ".
    "base-uri 'self'; ".
    "form-action 'self' https:;"
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
if (strpos($_SERVER['REQUEST_URI'], '/myCloudOfficeFetch/') !== false || 
    strpos($_SERVER['REQUEST_URI'], '/myCloudOfficeCallback') !== false) {
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
    'secure'   => true,         // if you are using HTTPS
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

	
require_once $work_dir.'/bin/main_login.php';   

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    if (!isset($_SESSION['username'])) {
		echo "<p>Access denied. You do not have sufficient rights to view this content.</p>";
        exit;
    }

	$loginRole = $work_dir . '/main_menu/' . getUserRole($_SESSION['username']) . '.php';
	
	if ($maintenance_mode === true){ 
		if (getUserRole($_SESSION['username']) !== "admin") { 
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
		require_once $work_dir . '/main_menu/notfound.php';;
	}
// --- LOGGED IN ---
}
