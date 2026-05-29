<?php
// Ensure this file is not accessed directly if IPS_Token is defined
if(!defined('IPS_Token')) {
	header("Connection: close");
	die();
}




$GLOBALS['mycloud_svg_logo'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -200 6000 1750" style="height:1.7em; width:auto; vertical-align:middle;">
    <g transform="scale(1.34) translate(120,-140)" filter="brightness(0.4)"><path fill="currentColor" stroke="currentColor" stroke-width="20" stroke-linejoin="round" stroke-linecap="round" d="M766 915q-33 0 -44.5 -21.5t-11.5 -49.5q0 -42 16.5 -98.5t41 -117t50.5 -115t45 -92.5q-29 36 -59.5 71.5t-62.5 70.5q-19 22 -38.5 44.5t-40.5 42.5q-17 17 -44 39.5t-57 39t-56 16.5q-27 0 -39 -18t-15.5 -42.5t-3.5 -44.5q0 -65 9.5 -132t22.5 -130q-27 58 -55 116 t-59 115q-13 25 -36 62t-53 78t-63 77t-67.5 58.5t-67.5 22.5q-29 0 -46.5 -19t-26.5 -47.5t-12 -58t-3 -50.5q0 -20 3 -54t10 -67t18 -50q2 -2 5 -5.5t7 -3.5q5 0 5 7q0 3 -2 7q-23 75 -23 156q0 15 2.5 38.5t10 46.5t21 38.5t34.5 15.5q31 0 69.5 -32.5t81 -86t84 -117 t77.5 -126t63 -114t39 -79.5q2 -5 8.5 -16t13.5 -11q3 0 3 5q0 7 -7 22t-11 23q13 -5 25 -5q10 0 10 8q0 7 -3.5 18t-5.5 18q-17 62 -24 131t-7 134q0 18 6.5 38.5t29.5 20.5t56.5 -22t73 -58.5t79.5 -79.5t76 -86.5t63 -79t40 -55.5q6 -9 11 -18t10 -19q-1 -2 -1 -7 q0 -6 10 -23t21 -35t15 -25q9 -8 22.5 -12t25.5 -4q5 0 14 1.5t9 9.5v3l-34 50t-33 51q-34 55 -70 119.5t-67 133.5t-53 138.5t-28 134.5q-1 7 -1 14v14q0 8 1.5 22.5t6.5 26t17 11.5q8 0 16 -2.5t16 -2.5h5t4 2q0 4 -15.5 9t-33 8.5t-23.5 3.5zM281 425l-7 -1 q-19 -3 -51 -10.5t-64.5 -21t-54.5 -33.5t-22 -47q0 -26 19.5 -43t48 -26t57.5 -12.5t47 -3.5q21 0 42.5 2t41.5 5q55 8 110.5 15t110.5 7q15 0 39.5 -2t36.5 -13l3.5 -3.5t4.5 -1.5q2 0 2 2q0 3 -3 6q-11 14 -35.5 21.5t-50.5 9.5t-43 2q-46 0 -92.5 -5.5t-92.5 -12.5 q-13 -2 -27 -3.5t-28 -1.5q-21 0 -50.5 6t-51.5 21.5t-22 44.5q0 22 15 38.5t35.5 27t38.5 16.5q11 3 22 4.5t22 3.5q2 1 5 2t3 3t-3.5 3t-5.5 1z" /></g>
    <g transform=" scale(2.088, 1.44) skewX(10) translate(550, -66)" filter="brightness(0.4)"><path fill="currentColor" stroke="currentColor" stroke-width="10" stroke-linejoin="round" stroke-linecap="round" d="M-128 1134q-27 0 -56 -9.5t-49 -30t-20 -52.5q0 -29 16.5 -53t42 -43.5t53.5 -34.5t50 -24q56 -23 122.5 -35t126.5 -16q18 -46 36.5 -92t28.5 -95l4 -19q-13 17 -35.5 46t-49 59t-53.5 50.5t-48 20.5q-17 0 -23 -14.5t-6 -28.5q0 -38 17 -72.5t34 -66.5l-3 1q-6 0 -6 -6 q0 -1 2 -5q13 -24 22 -41t24 -26t46 -9q6 0 6 5q0 8 -11 27t-24 38.5t-18 27.5q-8 12 -19 35t-19 45.5t-8 36.5q0 5 2 9.5t8 4.5q13 0 33 -15.5t42 -39.5t43 -50.5t36.5 -49t22.5 -35.5q8 -14 11 -29.5t19 -25.5q7 -4 18 -8.5t18 -4.5q10 0 10 10q0 14 -9.5 28.5t-16.5 26.5 q-9 17 -16.5 40.5t-13.5 43.5q-16 44 -30.5 88.5t-34.5 88.5h20q8 0 15.5 1t7.5 6t-10.5 6t-22 0.5t-15.5 -0.5q-20 48 -50.5 98.5t-71 93t-90 68.5t-108.5 26zM-132 1113q49 0 92.5 -26.5t80 -67.5t65 -86.5t46.5 -83.5q-56 4 -120 17.5t-114 39.5q-25 12 -55.5 33 t-53 48.5t-22.5 59.5q0 34 25.5 50t55.5 16z" /></g>
    <g transform="scale(1.1) translate(820,120)" filter="brightness(1)">
        <g transform="translate(1262,0) scale(1.17,1)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M551 450q-1 167 -92.5 258.5t-252.5 91.5h-136v-700h136q161 0 252.5 91.5t92.5 258.5zM218 712q111 0 170.5 -73.5t61.5 -188.5q-2 -115 -61.5 -188.5t-170.5 -73.5h-54v524h54z"/><path fill="currentColor" d="M551 450q-1 167 -92.5 258.5t-252.5 91.5h-136v-700h136q161 0 252.5 91.5t92.5 258.5zM218 712q111 0 170.5 -73.5t61.5 -188.5q-2 -115 -61.5 -188.5t-170.5 -73.5h-54v524h54z"/></g>
        <g transform="translate(1950,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M115 383q70 -73 175 -73q51 0 95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-53 0 -97.5 -19.5t-77.5 -53.5t-51.5 -79.5t-18.5 -97.5q0 -106 70 -177zM290 394q-70 0 -112.5 46t-42.5 120t42.5 120t112.5 46q71 0 113 -46.5t42 -119.5t-42 -119.5t-113 -46.5z"/><path fill="currentColor" d="M115 383q70 -73 175 -73q51 0 95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-53 0 -97.5 -19.5t-77.5 -53.5t-51.5 -79.5t-18.5 -97.5q0 -106 70 -177zM290 394q-70 0 -112.5 46t-42.5 120t42.5 120t112.5 46q71 0 113 -46.5t42 -119.5t-42 -119.5t-113 -46.5z"/></g>
        <g transform="translate(2520,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M388 695l60 59q-69 56 -158 56q-111 0 -178 -71.5t-67 -178.5q0 -105 70.5 -177.5t174.5 -72.5q89 0 158 56l-59 60q-42 -32 -99 -32q-69 0 -109.5 45.5t-40.5 120.5t40.5 120.5t109.5 45.5q58 0 98 -31z"/><path fill="currentColor" d="M388 695l60 59q-69 56 -158 56q-111 0 -178 -71.5t-67 -178.5q0 -105 70.5 -177.5t174.5 -72.5q89 0 158 56l-59 60q-42 -32 -99 -32q-69 0 -109.5 45.5t-40.5 120.5t40.5 120.5t109.5 45.5q58 0 98 -31z"/></g>
        <g transform="translate(2980,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M60 1028v-468q0-52 19-98t52-79.5t78-53t96-19.5t95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-64 0-119-30v-90q49 36 116 36q36 0 65.5-12.5t50-34.5t31.5-52.5t11-66.5t-11-66.5t-31-52.5t-48.5-34.5t-64.5-12.5q-35 0-63.5 12t-49 34t-31.5 52.5t-11 67.5v468h-90z"/><path fill="currentColor" d="M60 1028v-468q0-52 19-98t52-79.5t78-53t96-19.5t95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-64 0-119-30v-90q49 36 116 36q36 0 65.5-12.5t50-34.5t31.5-52.5t11-66.5t-11-66.5t-31-52.5t-48.5-34.5t-64.5-12.5q-35 0-63.5 12t-49 34t-31.5 52.5t-11 67.5v468h-90z"/></g>
        <g transform="translate(3560,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M190 800h-88v-396h-82l40 -84h130v480zM98 220q-13 -17 -13 -37t13 -36.5t40 -16.5t40 16.5t13 36.5t-13 36.5t-40 16.5t-40 -16z"/><path fill="currentColor" d="M190 800h-88v-396h-82l40 -84h130v480zM98 220q-13 -17 -13 -37t13 -36.5t40 -16.5t40 16.5t13 36.5t-13 36.5t-40 16.5t-40 -16z"/></g>
        <g transform="translate(3820,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M60 610v-510h90v510q0 36 8.5 58.5t26.5 32t35 12.5t45 3v84q-112 0 -158.5 -43t-46.5 -147z"/><path fill="currentColor" d="M60 610v-510h90v510q0 36 8.5 58.5t26.5 32t35 12.5t45 3v84q-112 0 -158.5 -43t-46.5 -147z"/></g>
        <g transform="translate(4080,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M295 310q170 7 170 141q0 167 -219 167h-41v-77h48q67 0 93 -20.5t26 -68.5q0 -31 -23 -46.5t-59 -15.5q-71 0 -111.5 50t-40.5 120q0 74 43.5 120t113.5 46q63 0 103 -31l60 59q-69 56 -163 56q-114 0 -182 -67t-68 -173q0 -118 67 -191t183 -69z"/><path fill="currentColor" d="M295 310q170 7 170 141q0 167 -219 167h-41v-77h48q67 0 93 -20.5t26 -68.5q0 -31 -23 -46.5t-59 -15.5q-71 0 -111.5 50t-40.5 120q0 74 43.5 120t113.5 46q63 0 103 -31l60 59q-69 56 -163 56q-114 0 -182 -67t-68 -173q0 -118 67 -191t183 -69z"/></g>
    </g>
    <g id="pencil"   transform="scale(3.1) translate(930, -30) rotate(128)">
        <rect x="0" y="-35" width="420" height="70" rx="12" fill="#dbb" stroke="#444" stroke-width="12"/>
        <polygon points="420,-35 480,0 420,35" fill="#aaa" stroke="#444" stroke-width="12"/>
        <polygon points="480,0 525,0 500,0" fill="#111"/>
    </g>
    </svg>';

// Logo switcher
if (isset($GLOBALS['domain_logo_map'][$_SERVER['HTTP_HOST']])) {
	$logo_key = $GLOBALS['domain_logo_map'][$_SERVER['HTTP_HOST']];
	if (isset($GLOBALS['svg_library'][$logo_key])) {
		$GLOBALS['mycloud_svg_logo'] = $GLOBALS['svg_library'][$logo_key];
	}
} elseif (isset($GLOBALS['domain_svg_logos'][$_SERVER['HTTP_HOST']])) {
	$GLOBALS['mycloud_svg_logo'] = $GLOBALS['domain_svg_logos'][$_SERVER['HTTP_HOST']];
}


$GLOBALS['mycloud_svg_icon'] = '
<svg xmlns="http://www.w3.org/2000/svg" width="250" height="250" viewBox="0 0 300 300">
  <g>
    <path fill="currentColor"
      d="M190 200c28 0 50-22 50-48c0-23-14-41-33-47c-5-30-32-54-65-54c-25 0-47 14-58 35c-4-1-9-2-14-2c-27 0-49 22-49 49c0 27 22 49 49 49h120z"/>
  </g>
  <g>
    <rect x="25" y="90" width="130" height="90" rx="10" ry="10" fill="currentColor"/>
    <path fill="#ffffff" d="M35 100l55 40c3 2 7 2 10 0l55-40H35z"/>
    <path fill="#ffffff" d="M35 170l40-30l-40-32v62zM155 170l-40-30l40-32v62z"/>
  </g>
  <g>
    <rect x="185" y="120" width="80" height="80" rx="12" ry="12" fill="currentColor"/>
    <circle cx="225" cy="160" r="10" fill="#ffffff"/>
    <rect x="222" y="160" width="6" height="18" rx="3" ry="3" fill="#ffffff"/>
    <path fill="currentColor"
      d="M200 120v-12c0-15 12-27 27-27s27 12 27 27v12h-12v-12c0-9-7-15-15-15s-15 6-15 15v12h-12z"/>
  </g>
</svg>';




class Login {

	// ################################################################
	// PUBLIC PROPERTIES (Configuration)
	// ################################################################
	public $work_dir;
	public $login_stateful_tokens;
	public $login_bruteforce_file;
	public $verify_store_file;
	public $api_key;
	public $language;
	public $log_file;
	public $cookie_name;
	public $cookie_valid_duration;
	public $isCloudOnly;
	public $users;
	public $user_db;
	public $user_details;
	public $email_sender_address;
	public $force_argon2_only;

	public $login_failures;
	public $login_block_seconds;
	public $brute_force_window;
	public $brute_force_factor;
	
	public $global_login_rate_file;
	public $global_login_max_hits;
	public $global_login_window;

	// State Variables (Shared across methods)
	private $login_error = null;
	private $gatekeeper_check_needed = false;
	private $hibp_breached = false;
	private $security_check_passed = false;
	private $skip_2fa = false;

	// ################################################################
	// CONSTRUCTOR
	// ################################################################
	public function __construct() {
		global $work_dir, $login_stateful_tokens, $login_bruteforce_file, $verify_store_file, 
			   $api_key, $language, $log_file, $cookie_name, $cookie_valid_duration,
			   $isCloudOnly, $users, $user_db, $user_details, $email_sender_address, $force_argon2_only,
			   $login_failures, $login_block_seconds, $brute_force_window, $brute_force_factor,
			   $global_login_rate_file, $global_login_max_hits, $global_login_window,
			   $cookie_is_ip_bound, $cloud_beta;

		$this->work_dir = $work_dir;
		
		if (!isset($login_stateful_tokens)) {
			$login_stateful_tokens = $work_dir . '/stateful_tokens.json';
		}
		$this->login_stateful_tokens = $login_stateful_tokens;
		$this->login_bruteforce_file = $login_bruteforce_file ?? null;
		$this->verify_store_file = $verify_store_file ?? null;
		$this->api_key = $api_key ?? null;
		$this->language = $language ?? 'en';
		$this->log_file = $log_file ?? '';
		$this->cookie_name = $cookie_name ?? 'auth_cookie';
		$this->cookie_valid_duration = $cookie_valid_duration ?? 86400;
		$this->users = $users ?? [];
		$this->user_db = $user_db ?? '';
		$this->user_details = $user_details ?? [];
		$this->email_sender_address = $email_sender_address ?? '';
		$this->force_argon2_only = $force_argon2_only ?? false;
		$this->login_failures = $login_failures ?? 5;
		$this->login_block_seconds = $login_block_seconds ?? 30;
		$this->brute_force_window = $brute_force_window ?? 300;
		$this->brute_force_factor = $brute_force_factor ?? 2;
		$this->global_login_rate_file = $global_login_rate_file ?? '';
		$this->global_login_max_hits = $global_login_max_hits ?? 100;
		$this->global_login_window = $global_login_window ?? 60;


		$cloud_path = '/cloud' . ($cloud_beta ?? '');
		$is_cloud_uri = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $cloud_path) !== false);
		$this->isCloudOnly = $isCloudOnly ?? $is_cloud_uri;
		
		// Initialize Files
		if (!file_exists($this->login_bruteforce_file)) file_put_contents($this->login_bruteforce_file, json_encode([]));
		if (!file_exists($this->verify_store_file)) file_put_contents($this->verify_store_file, json_encode([]));
	}

	// ################################################################
	// HELPER METHODS
	// ################################################################
	public function sanitize_username($username) {
		if (empty($username) || !is_string($username)) return 'Unknown';
		$safe = filter_var($username, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
		$safe = preg_replace('/[^a-zA-Z0-9!#$%&+\-=?^_{}|~.@]/', '', $safe);
		return $safe ?: 'Unknown';
	}

	public function get_subnet_mask($ip) {
		if (strpos($ip, ':') !== false) {
			$packed = @inet_pton($ip);
			if (false === $packed) return $ip; 
			$subnet = inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
			return $subnet . '/64';
		} else {
			$parts = explode('.', $ip);
			if(count($parts) === 4) { array_pop($parts); return implode('.', $parts) . '.0/24'; }
			return $ip;
		}
	}

	public function is_ajax_request() {
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
		if (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && strtolower($_SERVER['HTTP_SEC_FETCH_MODE']) === 'cors') return true;if (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false) return true;
		return false;
	}

	public function get_secure_processing_url($action) {
		$script_dir = dirname($_SERVER['SCRIPT_FILENAME']);
		$processing_exists = file_exists(__DIR__ . '/security.php') || file_exists($script_dir . '/security.php') || file_exists($this->work_dir . '/security.php');
		$timestamp = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		$payload = $ip . '|' . $timestamp;
		$sig = hash_hmac('sha256', $payload, $this->api_key);

		// Fallback: Simulate security.php secure return URI to bypass gatekeeper
		if (!$processing_exists) {
			// Hard stop for blocks to prevent redirect loops
			if ($action === 'blocked') {
				http_response_code(403);
				die("<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'><h2>⛔ Access Denied</h2><p>Your connection was rejected for security reasons.</p></body></html>");
			}
			
			// Map actions to safe return states so they don't re-trigger their own handlers
			$safe_action = $action;
			if ($action === 'logout') { $safe_action = 'loggedout'; }
			if ($action === 'login')  { $safe_action = 'logindone'; }
			
			$separator = (strpos($_SERVER['PHP_SELF'], '?') !== false) ? '&' : '?';
			return $_SERVER['PHP_SELF'] . $separator . $safe_action . "=1&st=" . $timestamp . "&sig=" . $sig;
		}

		return "processing.php?" . $action . "&st=" . $timestamp . "&sig=" . $sig;
	}

	public function verify_inbound_signature() {
		if (!isset($_GET['st'], $_GET['sig'])) return false;
		$provided_time = (int)$_GET['st'];
		$provided_sig  = $_GET['sig'];
		$current_ip    = $_SERVER['REMOTE_ADDR'];
		if (time() - $provided_time <= 60 && time() >= $provided_time) {
			$expected_sig = hash_hmac('sha256', $current_ip . '|' . $provided_time, $this->api_key);
			if (hash_equals($expected_sig, $provided_sig)) return true;
		}
		return false;
	}

	public function manage_auth_tokens($file, $action, $selector = null, $data_payload = null, $soft_fail = false) {
		if (!file_exists($file)) file_put_contents($file, json_encode([]));
		$fp = fopen($file, 'c+');
		if (!$fp) return false;
		$locked = false; $retries = 20;
		while ($retries > 0) {
			if (flock($fp, LOCK_EX | LOCK_NB)) { $locked = true; break; }
			usleep(100000); $retries--;
		}
		if (!$locked) {
			if ($soft_fail) { fclose($fp); return false; }
			http_response_code(503);
			if ($this->language === 'de') die("<h1>Server ausgelastet</h1><p>Der Anmelde-Server ist momentan ausgelastet. Bitte versuchen Sie es in wenigen Sekunden erneut.</p>");
			else die("<h1>Server Busy</h1><p>The login server is currently busy. Please try again in a few seconds.</p>");
		}
		$fstat = fstat($fp);
		$contents = ($fstat['size'] > 0) ? fread($fp, $fstat['size']) : '[]';
		$tokens = json_decode($contents, true) ?: [];
		$now = time(); $dirty = false;
		if ($action === 'write' || $action === 'delete') {
			foreach ($tokens as $key => $val) {
				if (isset($val['expires']) && $val['expires'] < $now) { unset($tokens[$key]); $dirty = true; }
			}
		}
		$return_val = null;
		switch ($action) {
			case 'read': if (isset($tokens[$selector]) && $tokens[$selector]['expires'] > $now) $return_val = $tokens[$selector]; break;
			case 'write': if ($selector && $data_payload) { $tokens[$selector] = $data_payload; $dirty = true; } break;
			case 'delete': if ($selector && isset($tokens[$selector])) { unset($tokens[$selector]); $dirty = true; } break;
		}
		if ($dirty) { ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($tokens)); }
		flock($fp, LOCK_UN); fclose($fp);
		return $return_val;
	}

	public function load_brute_force_data($file) {
		if (!file_exists($file)) return [];
		$fp = @fopen($file, 'r'); if (!$fp) return [];
		flock($fp, LOCK_SH); $contents = stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp);
		return json_decode($contents, true) ?: [];
	}

	public function save_brute_force_data($file, $data) {
		$fp = fopen($file, 'c+'); if (!$fp) return;
		flock($fp, LOCK_EX); ftruncate($fp, 0); fwrite($fp, json_encode($data, JSON_THROW_ON_ERROR)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
	}

	public function register_login_failure($ip, $file, $limit, $block_time, $window, $factor, $username) {
		$subnet = $this->get_subnet_mask($ip);
		$fp = @fopen($file, 'c+'); if (!$fp) return;
		flock($fp, LOCK_EX);
		$fstat = fstat($fp); $contents = ($fstat['size'] > 0) ? fread($fp, $fstat['size']) : '[]'; $data = json_decode($contents, true) ?: [];
		foreach ($data as $key => $val) {
			$level = $val['level'] ?? 0; $current_block_duration = $block_time * pow($factor, $level);
			if ($val['last_attempt'] + $current_block_duration + $window < time()) unset($data[$key]);
		}
		if (!isset($data[$subnet])) $data[$subnet] = ['count' => 0, 'last_attempt' => time(), 'level' => 0, 'usernames' => [], 'user_agent' => ''];
		if ($data[$subnet]['count'] < $limit) {
			$data[$subnet]['count']++; $data[$subnet]['last_attempt'] = time();
			$raw_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'; $data[$subnet]['user_agent'] = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $raw_ua), 0, 255);      
			$safe_user = substr($username, 0, 64); if (!in_array($safe_user, $data[$subnet]['usernames'])) $data[$subnet]['usernames'][] = $safe_user;
		}
		ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($data, JSON_THROW_ON_ERROR)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
	}

	public function reset_login_failures($ip, $file) {
		$subnet = $this->get_subnet_mask($ip);
		$data = $this->load_brute_force_data($file);
		if (isset($data[$subnet])) { unset($data[$subnet]); $this->save_brute_force_data($file, $data); }
	}
	
	public function load_verifications($file) { return json_decode(file_get_contents($file), true) ?: []; }
	public function save_verifications($file, $data) { file_put_contents($file, json_encode($data)); }


	// ################################################################
	// LOGIC METHODS
	// ################################################################

	private function checkGlobalSecurity() {
		$is_cloud_upload = (isset($_REQUEST['myCloud_drag']) || !empty($_FILES));
		$is_ajax_post = ($this->is_ajax_request() && $_SERVER['REQUEST_METHOD'] === 'POST');


		if (!$is_ajax_post && !$is_cloud_upload && file_exists($this->work_dir . '/parts/security_checks.php') && !$this->isFirewallCheckBypassed()) {
			require_once $this->work_dir . '/parts/security_checks.php';

			$input_sec_config = [
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
				'work_dir'      => $this->work_dir
			];

			$local_log   = isset($this->log_file) ? $this->log_file : '';
			$sec_checker = new ClientSecurity([], $local_log, $input_sec_config);
			$sec_result  = $sec_checker->runCheck();

			if ($sec_result['status'] === 'BLOCK') {
				if (function_exists('WriteLogLine') && !empty($local_log)) {
					WriteLogLine($local_log, "error", "MainLogin: 📛️ WAF 1 block - Score: " . $sec_result['score'] . "  Reasons: " . implode(", ", $sec_result['reasons']));
				}
				$target = $this->get_secure_processing_url('blocked');
				echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
				exit;
			}
		}

		// IP Binding Check
		if (isset($GLOBALS['cookie_is_ip_bound']) && $GLOBALS['cookie_is_ip_bound'] === true) {
			if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
				session_unset(); session_destroy(); header("Location: " . $_SERVER['PHP_SELF']); exit();
			}
		}

		// Session Fingerprinting
		if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['fingerprint'])) {
			$current_fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
//			if (!hash_equals($_SESSION['fingerprint'], $current_fingerprint)) {
//				session_unset(); session_destroy(); header("Location: " . $_SERVER['PHP_SELF']); exit();
//			}
		}

		// Brute Force Check
		$client_ip = $_SERVER['REMOTE_ADDR'];
		$client_subnet = $this->get_subnet_mask($client_ip);
		$bf_data = $this->load_brute_force_data($this->login_bruteforce_file);

		if (isset($bf_data[$client_subnet])) {
			$bf_count = $bf_data[$client_subnet]['count'];
			$bf_last  = $bf_data[$client_subnet]['last_attempt'];
			$bf_level = $bf_data[$client_subnet]['level'] ?? 0;
			$dynamic_block_time = $this->login_block_seconds * pow($this->brute_force_factor, $bf_level);

			if ($bf_count >= $this->login_failures) {
				if ($bf_last + $dynamic_block_time > time()) {
					WriteLogLine($this->log_file, "warning", "MainLogin: 🛑 Brute force blocked Subnet: " . $client_subnet . " (Level $bf_level)");
					$target = $this->get_secure_processing_url('blocked');
					echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
					exit;
				} else {
					$penalty_end_time = $bf_last + $dynamic_block_time;
					if (time() < ($penalty_end_time + $this->brute_force_window)) {
						$bf_data[$client_subnet]['count'] = 0;
						$bf_data[$client_subnet]['level'] = $bf_level + 1;
						$bf_data[$client_subnet]['last_attempt'] = time(); 
					} else {
						$bf_data[$client_subnet]['count'] = 0;
						$bf_data[$client_subnet]['level'] = 0;
						$bf_data[$client_subnet]['last_attempt'] = time();
						$bf_data[$client_subnet]['usernames'] = []; 
					}
					$this->save_brute_force_data($this->login_bruteforce_file, $bf_data);
				}
			}
		}

		// Verify Inbound Return
		if ($this->is_ajax_request()) {
			$this->security_check_passed = true;
		} elseif (isset($_GET['st'], $_GET['sig'])) {
			if ($this->verify_inbound_signature()) {
				$this->security_check_passed = true;
			}
		}

        if (!file_exists($this->work_dir . '/bin/processing.php') && !file_exists('processing.php') && !file_exists($this->work_dir . '/processing.php')) {
			$this->security_check_passed = true;
		}
	}

	private function checkRememberMe() {
		global $cloud_admin_mode_cloudlist;
		if (isset($_COOKIE[$this->cookie_name])) {
			$parts = explode(':', $_COOKIE[$this->cookie_name]);
			if (count($parts) === 2) {
				$selector = $parts[0];
				$validator = $parts[1];
				$token_data = $this->manage_auth_tokens($this->login_stateful_tokens, 'read', $selector);

				if ($token_data) {
					$valid_token = false;
					$in_grace_period = false;
					if (password_verify($validator, $token_data['hash'])) {
						$valid_token = true;
					} elseif (isset($token_data['prev_hash'], $token_data['prev_time']) && (time() - $token_data['prev_time'] <= 60)) {
						if (password_verify($validator, $token_data['prev_hash'])) {
							$valid_token = true;
							$in_grace_period = true;
						}
					}

					// FIX: Privilege Escalation via Cookie Token Manipulation
					// Verify HMAC to prevent payload tampering
					if ($valid_token && isset($token_data['username'], $token_data['expires'])) {
						$expected_hmac = hash_hmac('sha256', $token_data['username'] . $token_data['expires'], $this->api_key);
						if (!isset($token_data['hmac']) || !hash_equals($expected_hmac, $token_data['hmac'])) {
							$valid_token = false;
							WriteLogLine($this->log_file, "warning", "MainLogin:  ️ Token HMAC mismatch. Potential tampering.");
						}
					}

					if ($valid_token) {
						// Session Fixation
						if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
							if (session_status() === PHP_SESSION_ACTIVE) {
								session_regenerate_id(true);
							}
						}

						if (isset($token_data['username'])) {
							if (isset($_POST['username']) && $_POST['username'] === $token_data['username']) {
								$this->skip_2fa = true;
							} elseif (!isset($_POST['username']) && !isset($_SESSION['loggedin']) && !empty($this->isCloudOnly)) {
								if ($this->security_check_passed) {

										// Prevent AJAX requests from triggering UI-based App Mode auto-login to avoid race conditions
										if ($this->is_ajax_request()) {
											$this->gatekeeper_check_needed = true;
											return;
										}
									
										// Authentication Bypass via App Mode
										// Cryptographically bind app mode to the token, IP, and User-Agent
										$client_env = $_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? '');
										$expected_app_mode = hash_hmac('sha256', $selector . 'app_mode' . $client_env, $this->api_key);
									if (isset($_GET['app_mode_confirmed']) && hash_equals($expected_app_mode, $_GET['app_mode_confirmed'])) {
										$username = $token_data['username'];

										// Check if user has an admin_mode cloud
										$is_admin_user = false;
										if (isset($this->user_details) && is_array($this->user_details)) {
											foreach ($this->user_details as $ud) {
												if (isset($ud['name']) && $ud['name'] === $username && isset($ud['cloud'])) {
													foreach ($ud['cloud'] as $cloud_cfg) {
														if (($cloud_cfg['rights'] ?? '') === 'admin_mode') {
															$is_admin_user = true;
															break 2;
														}
													}
												}
											}
										}
										if (isset($cloud_admin_mode_cloudlist) && !file_exists($cloud_admin_mode_cloudlist)) {
											$is_admin_user = true;
										}
										
										if (!$is_admin_user) {										
											if (!$in_grace_period) {
												$new_validator = bin2hex(random_bytes(32));
												$token_data['prev_hash'] = $token_data['hash'];
												$token_data['prev_time'] = time();
												$token_data['hash'] = password_hash($new_validator, PASSWORD_DEFAULT);
												$token_data['expires'] = time() + (30 * 86400);
												// Update HMAC for new payload
												$token_data['hmac'] = hash_hmac('sha256', $token_data['username'] . $token_data['expires'], $this->api_key);
												$this->manage_auth_tokens($this->login_stateful_tokens, 'write', $selector, $token_data);
												setcookie($this->cookie_name, $selector . ':' . $new_validator, [
													'expires' => $token_data['expires'], 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'
												]);
											}
											session_regenerate_id(true);
											$_SESSION = []; $_SESSION['loggedin'] = true; $_SESSION['username'] = $username;
											if (isset($GLOBALS['cookie_is_ip_bound']) && $GLOBALS['cookie_is_ip_bound'] === true) $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
											$_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
											$this->reset_login_failures($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file);
											$_SESSION['app_mode'] = true;
											WriteLogLine($this->log_file, "success", "MainLogin: ✅ Auto-Login (App Mode) for $username");
											$target = $this->get_secure_processing_url('login');
											echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
											exit();
										} else {
											// User is an Admin: Force manual login by ignoring App Mode auto-login
											$this->gatekeeper_check_needed = false; 
										}										
									} else {
										$this->gatekeeper_check_needed = true;
									}
								}
							}
						}
					} else {
						WriteLogLine($this->log_file, "warning", "MainLogin:  ️ Invalid validator for selector. Potential token theft attempt.");
						$this->manage_auth_tokens($this->login_stateful_tokens, 'delete', $selector);
						setcookie($this->cookie_name, '', ['expires' => time()-3600, 'path' => '/']);
					}
				}
			}
		}
	}
	
	
	private function handleLogout() {
		if (isset($_GET['logout'])) {
			$do_logout = false;
			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				$do_logout = true;
			} else {
				$referer = $_SERVER['HTTP_REFERER'] ?? '';
				if (!empty($referer)) {
					$ref_host = parse_url($referer, PHP_URL_HOST);
					$ref_scheme = parse_url($referer, PHP_URL_SCHEME);
					if ($ref_host === $_SERVER['HTTP_HOST'] && $ref_scheme === 'https') {
						$do_logout = true;
					}
				}
			}

			if (!$do_logout) {
				echo '
		<!DOCTYPE html>
		<html style="font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f0f0;">
		<head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
		<body>
			<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center;">
				<h2 style="margin-top: 0; color: #333;">Confirm Logout</h2>
				<p style="color: #666; margin-bottom: 20px;">Do you want to sign out?</p>
				<form method="post" action="?logout=true">
					<button type="button" onclick="history.back()" style="padding: 10px 20px; border: 1px solid #ccc; background: white; cursor: pointer; margin-right: 10px; border-radius: 4px;">Cancel</button>
					<button type="submit" style="padding: 10px 20px; border: none; background: #e81123; color: white; cursor: pointer; border-radius: 4px;">Sign Out</button>
				</form>
			</div>
		</body>
		</html>';
				exit();
			}

			WriteLogLine($this->log_file, "success", "MainLogin: ✅ Logout for user ".$_SESSION['username']);
			if (isset($_GET['forget'])) {
				if (isset($_COOKIE[$this->cookie_name])) {
					$parts = explode(':', $_COOKIE[$this->cookie_name]);
					if (count($parts) === 2) {
						$this->manage_auth_tokens($this->login_stateful_tokens, 'delete', $parts[0], null, true);
					}
					setcookie($this->cookie_name, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
				}
			}
			session_regenerate_id(true); session_unset(); session_destroy();
			$target = $this->get_secure_processing_url('logout');
			echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
			exit();
		}
	}

	private function handleCheck2FA() {
		if (isset($_GET['check2fa'])) {
			header('Content-Type: application/json');
			$approved = false;
			if (isset($_SESSION['2fa_pending'], $_SESSION['2fa_token'])) {
				$verifications = $this->load_verifications($this->verify_store_file);
				$token = $_SESSION['2fa_token'];
				if (isset($verifications[$token]) && !empty($verifications[$token]['approved'])) {
					$approved = true;
				}
			}
			echo json_encode(['approved' => $approved]);
			exit();
		}
	}

	private function handleVerificationLink() {
		if (isset($_GET['verify'])) {
			$token = $_GET['verify'];
			$verifications = $this->load_verifications($this->verify_store_file);
			if (isset($verifications[$token])) {
				$entry = $verifications[$token];
				if (time() <= $entry['expires']) {
					$verifications[$token]['approved'] = true;
					$this->save_verifications($this->verify_store_file, $verifications);
					$_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
					$this->reset_login_failures($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file);
					?>
				<!DOCTYPE html>
				<html>
				<head><meta charset="utf-8"><title>Opening verification...</title></head>
				<body>
			<?php  if ($this->language === 'de') {  ?>
				<h2 style='font-family: Arial, sans-serif;'>✅ Verifikation erfolgreich.</h2>
				<p  style='font-family: Arial, sans-serif; color: #666;'>Du kannnst diesen Tab oder dieses Fenster jetzt schließen und zu deinem ursprünglichen Fenster zurückkehren.</p>
			<?php  } else {  ?>
				<h2 style='font-family: Arial, sans-serif;'>✅ Verification has been done.</h2>
				<p  style='font-family: Arial, sans-serif; color: #666;'>You may safely close this tab or window and go back to your original session.</p>
			<?php  } ?>
				<script>
					setTimeout(function() { window.close(); }, 100);
				</script>
				</body>
				</html>
			<?php
					WriteLogLine($this->log_file, "success", "MainLogin: ✅ 2FA verification via link");
					exit();
				} else {
					if ($this->language === 'de') { echo "<h2 style='font-family: Arial, sans-serif;'>🛑 Verifikation nicht erfolgreich.</h2><p>Verifikation abgelaufen.</p>"; } 
					else { echo "<h2 style='font-family: Arial, sans-serif;'>🛑 Verification unsuccessful.</h2><p>Verification link expired.</p>"; }
					exit();
				}
			} else {
				if ($this->language === 'de') { echo "<h2 style='font-family: Arial, sans-serif;'>🛑 Verifikation nicht erfolgreich.</h2><p>Diese Verifikation ist unbekannt</p>"; } 
				else { echo "<h2 style='font-family: Arial, sans-serif;'>🛑 Verification unsuccessful.</h2><p>Unknown or invalid verification link</p>"; }
				exit();
			}
		}
	}

	private function checkPendingSession() {
		if (isset($_SESSION['2fa_pending'], $_SESSION['2fa_token'])) {
			$verifications = $this->load_verifications($this->verify_store_file);
			$token = $_SESSION['2fa_token'];
			if (isset($verifications[$token]) && !empty($verifications[$token]['approved'])) {
				
				$username = $_SESSION['2fa_user'];
				$remember_me = !empty($_SESSION['2fa_remember_me']);

				session_regenerate_id(true);
				$_SESSION = []; 
				
				$_SESSION['loggedin'] = true;
				$_SESSION['username'] = $username;
				if (isset($GLOBALS['cookie_is_ip_bound']) && $GLOBALS['cookie_is_ip_bound'] === true) {
					$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
				}
				
				$_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
				$this->reset_login_failures($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file);

				if ($remember_me) {
					$selector = bin2hex(random_bytes(16));
					$validator = bin2hex(random_bytes(32));
					$expires = time() + $this->cookie_valid_duration;
					
					$payload = [
						'username' => $username,
						'hash' => password_hash($validator, PASSWORD_DEFAULT),
						'expires' => $expires,
						'created' => time(),
						'hmac' => hash_hmac('sha256', $username . $expires, $this->api_key)
					];
					$this->manage_auth_tokens($this->login_stateful_tokens, 'write', $selector, $payload);
					
					setcookie($this->cookie_name, $selector . ':' . $validator, [
						'expires' => $expires,
						'path' => '/',
						'secure' => true,
						'httponly' => true,
						'samesite' => 'Lax'
					]);
				}

				WriteLogLine($this->log_file, "success", "MainLogin: ✅ Successful login for user ".$_SESSION['username']);
				session_write_close();
				$target = $this->get_secure_processing_url('login');
				echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
				exit();
			}
		}
	}

	private function process2FAPost() {
		if (isset($_SESSION['2fa_pending']) && isset($_POST['code'])) {

			if (!isset($_SESSION['2fa_attempts'])) {
				$_SESSION['2fa_attempts'] = 0;
				$_SESSION['2fa_attempt_time'] = time();
			}
			
			if ($_SESSION['2fa_attempts'] >= 5) {
				$this->login_error = "Too many attempts. Please login again.";
				session_unset(); session_destroy();
				header("Location: " . $_SERVER['PHP_SELF']);
				exit;
			}
			$_SESSION['2fa_attempts']++;

			if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
				if ($this->language === 'de') { $this->login_error = "Sitzung abgelaufen. Bitte die Seite neu laden."; } 
				else { $this->login_error = "Expired session token. Please refresh the page and try again."; }
				WriteLogLine($this->log_file, "error", "MainLogin: ❌ CSRF token mismatch on 2FA verify. Username: " . $this->sanitize_username($_SESSION['2fa_user'] ?? 'Unknown') . "Session token: " . $_SESSION['csrf_token'] . " Post token: " . $_POST['csrf_token'] );
				usleep(random_int(1500000, 2700000));
			} else {
				if (time() > $_SESSION['2fa_expires']) {
					if ($this->language === 'de') { $this->login_error = "Code abgelaufen. Bitte neu einloggen."; } 
					else { $this->login_error = "Code expired. Please login again."; }
					session_unset(); session_destroy();
					WriteLogLine($this->log_file, "error", "MainLogin: ❌ 2FA authentication failed, time expired");
					usleep(random_int(1500000, 2700000));
					header("Location: " . $_SERVER['PHP_SELF']);
					exit();
				} elseif (hash_equals((string)$_SESSION['2fa_code'], (string)$_POST['code'])) {
					
					$username = $_SESSION['2fa_user'];
					$remember_me = !empty($_SESSION['2fa_remember_me']);

					session_regenerate_id(true);
					$_SESSION = [];
					
					$_SESSION['loggedin'] = true;
					$_SESSION['username'] = $username;
					if (isset($GLOBALS['cookie_is_ip_bound']) && $GLOBALS['cookie_is_ip_bound'] === true) {
						$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
					}

					$_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
					$this->reset_login_failures($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file);

					if ($remember_me) {
						$selector = bin2hex(random_bytes(16));
						$validator = bin2hex(random_bytes(32));
						$expires = time() + $this->cookie_valid_duration;
						
						$payload = [
							'username' => $username, 
							'hash' => password_hash($validator, PASSWORD_DEFAULT), 
							'expires' => $expires, 
							'created' => time(),
							'hmac' => hash_hmac('sha256', $username . $expires, $this->api_key)
						];

						$this->manage_auth_tokens($this->login_stateful_tokens, 'write', $selector, $payload);
						
						setcookie($this->cookie_name, $selector . ':' . $validator, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
					}

					WriteLogLine($this->log_file, "success", "MainLogin: ✅ 2FA Login for ".$_SESSION['username']);
					session_write_close();
					$target = $this->get_secure_processing_url('login');
					echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
					exit();
				} else {
					$this->register_login_failure($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file, $this->login_failures, $this->login_block_seconds, $this->brute_force_window, $this->brute_force_factor, $_SESSION['2fa_user']);
					WriteLogLine($this->log_file, "error", "MainLogin: ❌ 2FA authentication failed, invalid code");
					if ($this->language === 'de') { $this->login_error = "Ungültiger Code"; } else { $this->login_error = "Invalid Code."; }
					usleep(random_int(1500000, 2700000));
				}
			}
		}
	}

	private function processLoginPost() {
		// Returns TRUE if we should skip to rendering (simulate goto skip_login_check)
		if (isset($_POST['username']) && isset($_POST['password']) && !isset($_SESSION['2fa_pending'])) {

			// ANTI-BOT SECURITY: Ensure human interaction occurred
			if (!isset($_POST['hi_token']) || $_POST['hi_token'] !== 'unlocked') {
				WriteLogLine($this->log_file, "error", "MainLogin: 🤖 Bot detected - Form submitted without human interaction.");
				if ($this->language === 'de') { $this->login_error = "Automatisierung blockiert. Bitte Seite neu laden."; } 
				else { $this->login_error = "Automated request blocked. Please reload the page."; }
				usleep(random_int(1500000, 2700000));
				return true; // Simulate goto skip_login_check
			}

			// SECURITY CHECK (WAF)
			if (file_exists($this->work_dir . '/parts/security_checks.php')) {
				require_once $this->work_dir . '/parts/security_checks.php';
				$input_sec_config = [
					'flood_control' => ['enabled' => true],
					'http_checks'   => ['enabled' => true],
					'referrer_check'=> ['enabled' => true],
					'geo_ip'        => ['enabled' => true],
					'user_agents'   => ['enabled' => true],
					'rate_limit'    => ['enabled' => true],
					'asn_check'     => ['enabled' => true],
					'keyword_check' => ['enabled' => true],
					'blocklists'    => ['enabled' => true],
					'waf_checks'    => ['enabled' => true],
					'work_dir'      => $this->work_dir
				];

				$local_log = isset($this->log_file) ? $this->log_file : '';
				$sec_checker = new ClientSecurity([], $local_log, $input_sec_config);
				$sec_result  = $sec_checker->runCheck();

				if ($sec_result['status'] === 'BLOCK') {
					WriteLogLine($local_log, "error", "MainLogin: 📛️ WAF 2 block - Score: " . $sec_result['score'] . "  Reasons: " . implode(", ", $sec_result['reasons']));
					if ($this->language === 'de') { $this->login_error = "Benutzername und/oder Passwort falsch"; } 
					else { $this->login_error = "Invalid username/password or account not configured"; }
					usleep(random_int(1500000, 2700000));
					return true; // Simulate goto skip_login_check
				}
			}

			global_login_rate_limit($this->global_login_rate_file, $this->global_login_max_hits, $this->global_login_window);

			$csrf_present = isset($_POST['csrf_token']);
			$csrf_valid = $csrf_present && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
			
			$is_first_login_attempt = empty($_SESSION['login_attempts']);
			$_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

			// SECURITY: To allow a stale tab to login without opening a "Login CSRF" vulnerability,
			// we strictly verify the Origin/Referer matches our host if the token is invalid.
			$host = $_SERVER['HTTP_HOST'];
			$is_same_origin = (strpos($_SERVER['HTTP_ORIGIN'] ?? '', $host) !== false) || (strpos($_SERVER['HTTP_REFERER'] ?? '', $host) !== false);

			if (!$csrf_present || (!$csrf_valid && (!$is_first_login_attempt || !$is_same_origin))) {
				if ($this->language === 'de') { $this->login_error = "Sitzung abgelaufen. Bitte die Seite neu laden."; } 
				else { $this->login_error = "Expired session token. Please refresh the page and try again."; }
				WriteLogLine($this->log_file, "error", "MainLogin: ❌ CSRF token mismatch/missing on login. Username: " . $this->sanitize_username($_POST['username'] ?? 'Unknown') . " Session token: " . $_SESSION['csrf_token'] . " Post token: " . ($_POST['csrf_token'] ?? 'none') );
				usleep(random_int(1500000, 2700000));
			} else {
				$username = filter_var( $_POST['username'], FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ); 
				$username = preg_replace( '/[^a-zA-Z0-9!#$%&+\-=?^_{}|~.@]/', '', $username );
				$username = strtolower($username);
				
				$password = $_POST['password'];
				$remember_me_flag = !empty($_POST['remember_me']);
				$stored_hash = $this->users[$username] ?? null;
				$authenticated = false;
				
				// Dummy hash for timing attack mitigation (Standard Bcrypt format)
				$dummy_hash = '$2y$10$vI8aWBnTxT.M2L6t.4tB2.2B9uY.Yg2eU/VwH9c0/hQn5hU0nU.mG';

				if ($stored_hash) {
					if (strpos($stored_hash, '$') === 0) {
						if (password_verify($password, $stored_hash)) {
							$authenticated = true;
							if (password_needs_rehash($stored_hash, PASSWORD_ARGON2ID)) {}
						}
					} elseif (!$this->force_argon2_only && hash_equals($stored_hash, hash('sha256', $password))) {
						$authenticated = true;
						// --- 🟢 AUTOMATIC MIGRATION START ---
						if (defined('PASSWORD_ARGON2ID') && is_writable($this->user_db)) {
							$new_secure_hash = password_hash($password, PASSWORD_ARGON2ID);
							$handle = @fopen($this->user_db, 'r+');
							if ($handle && flock($handle, LOCK_EX)) {
								$content = stream_get_contents($handle);
								$pattern = "/(['\"]" . preg_quote($username, '/') . "['\"]\s*=>\s*)(['\"][^'\"]+['\"])/";
								if (preg_match($pattern, $content)) {
									$new_content = preg_replace($pattern, "$1'$new_secure_hash'", $content);
									if ($new_content && $new_content !== $content) {
										rewind($handle); fwrite($handle, $new_content); ftruncate($handle, ftell($handle));
										WriteLogLine($this->log_file, "info", "MainLogin: 🔒 Automatically migrated password to Argon2id for user: $username");
									}
								}
								flock($handle, LOCK_UN); fclose($handle);
							}
						}
						// --- 🟢 AUTOMATIC MIGRATION END ---
					}
				} else {
					// Timing attack mitigation: Perform verification against dummy hash
					// to ensure equal CPU consumption regardless of user existence.
					password_verify($password, $dummy_hash);
				}
				
				if ($authenticated) {
					if (!$csrf_valid) {
						WriteLogLine($this->log_file, "warning", "MainLogin:  ️ Allowed first-try login with expired CSRF token for user: $username");
					}
					unset($_SESSION['login_attempts']);
					$sha1_pwd = strtoupper(sha1($password));
					$hibp_prefix = substr($sha1_pwd, 0, 5);
					$hibp_suffix = substr($sha1_pwd, 5);
					
					$opts = ['http' => ['method' => 'GET', 'header' => "User-Agent: SecurePHPLogin\r\n", 'timeout' => 3]];
					$context = stream_context_create($opts);
					$hibp_response = @file_get_contents('https://api.pwnedpasswords.com/range/' . $hibp_prefix, false, $context);

					if ($hibp_response && strpos($hibp_response, $hibp_suffix) !== false) {
						$this->hibp_breached = true;
						WriteLogLine($this->log_file, "warning", "MainLogin:  ️ HIBP breach detected for user $username. Forcing 2FA.");
					}
					
					+					$target_email = null;
					$has_admin_mode = false;
					if (isset($this->user_details) && is_array($this->user_details)) {
						foreach ($this->user_details as $ud) {
							if (isset($ud['name']) && $ud['name'] === $username) { 
								$target_email = $ud['email'] ?? null; 
								if (isset($ud['cloud']) && is_array($ud['cloud'])) {
									foreach ($ud['cloud'] as $cloud_cfg) {
										if (($cloud_cfg['rights'] ?? '') === 'admin_mode') {
											$has_admin_mode = true;
											break;
										}
									}
								}
								break; 
							}
						}
					}
					global $cloud_admin_mode_cloudlist;
					if (!empty($cloud_admin_mode_cloudlist) && !file_exists($cloud_admin_mode_cloudlist)) {
						$has_admin_mode = false;
					}


					$config_skip = (isset($this->isCloudOnly) && $this->isCloudOnly === true);

					if ($has_admin_mode) {
						$config_skip = false;
					}

					if ($this->hibp_breached) { 
						$this->skip_2fa = false; 						
					}

					if ($this->skip_2fa || $config_skip) {
							session_regenerate_id(true);
							$_SESSION = [];
							$_SESSION['loggedin'] = true; $_SESSION['username'] = $username;
							if (isset($GLOBALS['cookie_is_ip_bound']) && $GLOBALS['cookie_is_ip_bound'] === true) $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
							$_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? ''));
							$this->reset_login_failures($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file);

							// Respect the checkbox for users bypassing 2FA or renewing an existing token
							if ($remember_me_flag) {
								// Clean up the old token if they used one to skip 2FA
								if ($this->skip_2fa && isset($_COOKIE[$this->cookie_name])) {
									$parts = explode(':', $_COOKIE[$this->cookie_name]);
									if (count($parts) === 2) {
										$this->manage_auth_tokens($this->login_stateful_tokens, 'delete', $parts[0]);
									}
								}								$selector = bin2hex(random_bytes(16));
								$validator = bin2hex(random_bytes(32));
								$expires = time() + $this->cookie_valid_duration;
								$payload = [
									'username' => $username, 
									'hash' => password_hash($validator, PASSWORD_DEFAULT), 
									'expires' => $expires, 
									'created' => time(),
									'hmac' => hash_hmac('sha256', $username . $expires, $this->api_key)
								];
								$this->manage_auth_tokens($this->login_stateful_tokens, 'write', $selector, $payload);
								setcookie($this->cookie_name, $selector . ':' . $validator, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
							} elseif (!$remember_me_flag && $this->skip_2fa) {
								// User explicitly unchecked the box but bypassed 2FA via an old token -> Revoke it
								if (isset($_COOKIE[$this->cookie_name])) {
									$parts = explode(':', $_COOKIE[$this->cookie_name]);
									if (count($parts) === 2) {
										$this->manage_auth_tokens($this->login_stateful_tokens, 'delete', $parts[0]);
									}
									setcookie($this->cookie_name, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
								}							}

							$log_reason = $this->skip_2fa ? "via remembered browser" : "cloud only";
							WriteLogLine($this->log_file, "success", "MainLogin: ✅ Login without 2FA for $username $log_reason");                  
							session_write_close();
							$target = $this->get_secure_processing_url('login');

							if ($this->hibp_breached && $config_skip) {
								echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Security Warning</title></head><body style="font-family: sans-serif; background: #f0f0f0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;">';
								echo '<div style="background: #fff; padding: 30px; border: 4px solid #cc0000; border-radius: 8px; min-width: 300px; max-width: 600px; box-shadow: 0 0 20px rgba(0,0,0,0.5); text-align: left;">';
								if ($this->language === 'de') {
									echo '<h2 style="color: #cc0000; margin-top: 0;"> ️ SICHERHEITSWARNUNG</h2>';
									echo '<p style="font-size: 1.1rem; line-height: 1.5;">Dieses Passwort wurde in einem bekannten Datenleck gefunden, wenn auch <i><b>nicht</b></i> direkt verbunden mit deinem Account hier.<br><br><b>Hinweis: Bitte ändere dein Passwort umgehend nach dem Einloggen!</b></p>';
									echo '<div style="text-align: right; margin-top: 20px;"><button onclick="window.location.replace(\'' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '\')" style="padding: 10px 20px; cursor: pointer; font-size: 1rem; background: #cc0000; color: white; border: none; border-radius: 4px;">Weiter</button></div>';
								} else {
									echo '<h2 style="color: #cc0000; margin-top: 0;"> ️ SECURITY WARNING</h2>';
									echo '<p style="font-size: 1.1rem; line-height: 1.5;">This password was found in a public data breach, even though <i><b>not</b></i> directly connected to your account here.<br><br><b>Remark: Please change your password immediately after logging in!</b></p>';
									echo '<div style="text-align: right; margin-top: 20px;"><button onclick="window.location.replace(\'' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '\')" style="padding: 10px 20px; cursor: pointer; font-size: 1rem; background: #cc0000; color: white; border: none; border-radius: 4px;">Continue</button></div>';
								}
								echo '</div></body></html>';
								exit();
							}

							echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
							exit();
						}

						$target_email = null;
						if (isset($this->user_details) && is_array($this->user_details)) {
							foreach ($this->user_details as $ud) {
								if (isset($ud['name']) && $ud['name'] === $username) { $target_email = $ud['email'] ?? null; break; }
							}
						}
						
						if (!filter_var($target_email, FILTER_VALIDATE_EMAIL) ||  preg_match('/[\r\n]/', $target_email)) {
							$this->login_error = $L['invalid_email']; // Assumes $L is available or logic fails same as original
							WriteLogLine($this->log_file, "error", "MainLogin: Email validation failed");
							usleep(random_int(1500000, 2700000));
							$target = $this->get_secure_processing_url('blocked');
							echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
							exit;
						}
			
						if (!$target_email) {
							$this->register_login_failure($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file, $this->login_failures, $this->login_block_seconds, $this->brute_force_window, $this->brute_force_factor, $this->sanitize_username($_POST['username'] ?? ''));
							if ($this->language === 'de') { $this->login_error = "Benutzername und/oder Passwort falsch"; } 
							else { $this->login_error = "Invalid username/password or account not configured"; }
							WriteLogLine($this->log_file, "error", "MainLogin: ❌ authentication failed (no email address) for user ".$this->sanitize_username($_POST['username'] ?? ''));
							usleep(random_int(1500000, 2700000));
					} else {
							$code = generateFriendlyRandomString(8);
							$token = bin2hex(random_bytes(64));
							$_SESSION['2fa_pending'] = true;
							$_SESSION['2fa_user'] = $username;
							$_SESSION['2fa_code'] = $code;
							$_SESSION['2fa_token'] = $token;
							$_SESSION['2fa_expires'] = time() + 180;
							$_SESSION['2fa_remember_me'] = $remember_me_flag;

							$verifications = $this->load_verifications($this->verify_store_file);
							$verifications[$token] = [ 'session_id' => session_id(), 'expires' => $_SESSION['2fa_expires'], 'approved' => false ];
							$this->save_verifications($this->verify_store_file, $verifications);

							$to = $target_email;
							if ($this->language === 'de') { $subject = "Dein 2FA Code für das ". strtoupper($_SERVER['HTTP_HOST']) . " Administrations-Programm"; } 
							else { $subject = "Your 2FA Code for the ". strtoupper($_SERVER['HTTP_HOST']) . " Administration Board"; } 
							$link = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?verify=" . urlencode($token);
							if ($this->language === 'de') { 
							$message = '
								<html>
								<head>
									<title>Dein 2FA Code</title>
								</head>
								<body>
									<div style="font-family: Arial, sans-serif; line-height: 1.6;">
										<p>Der Verifikationscode für das <b>' . strtoupper($_SERVER['HTTP_HOST']) . ' Administrations-Programm </b> lautet:</p>
										<p style="font-size: 24px; font-weight: bold; color: #007bff; text-align: center;"></br>' . $code . '</p>
										<p>&nbsp;</p>
										<p>Oder, noch einfacher: Um dein einloggen zu genehmigen, <b>klicke einfach das untenstehende Schaltfeld: </b> <br></p>
										<p>&nbsp;</p>
										<div style="text-align: center; padding: 20px; background-color: #007bff;"><a href="' . $link . '" style="display: inline-block;  font-size: 16px; color: #fff; background-color: #007bff; text-decoration: none; border-radius: 5px;">
											&nbsp;<br>Genehmige das Einloggen</br>&nbsp;
										</a></div>
										<p>&nbsp;</p>
										<hr style="margin: 20px 0;">
										<p style="color: #888;"><i>Hinweise:</i><br>Wenn du diese <b>NICHT</b> selbst ausgelöst hast, dann <b>IGNORIERE</b> bitte diese E-Mail.<br>Dieser Code ist für drei Minuten gültig.
										</p>
									</div>
								</body>
								</html>
								';
							} else {
							  $message = '
								<html>
								<head>
									<title>Your 2FA Code</title>
								</head>
								<body>
									<div style="font-family: Arial, sans-serif; line-height: 1.6;">
										<p>The verification code for the <b>' . strtoupper($_SERVER['HTTP_HOST']) . ' Administration Board </b> is:</p>
										<p style="font-size: 24px; font-weight: bold; color: #007bff; text-align: center;"></br>' . $code . '</p>
										<p>&nbsp;</p>
										<p>Or, even easier: To approve your login, <b>simply click the button below: </b> <br></p>
										<p>&nbsp;</p>
										<div style="text-align: center; padding: 20px; background-color: #007bff;"><a href="' . $link . '" style="display: inline-block;  font-size: 16px; color: #fff; background-color: #007bff; text-decoration: none; border-radius: 5px;">
											Approve Login
										</a></div>
										<p>&nbsp;</p>
										<hr style="margin: 20px 0;">
										<p style="color: #888;"><i>Remarks:</i><br>If you did <b>NOT</b> initiate this action, please <b>IGNORE</b> this email.<br>This code is valid for three minutes.
										</p>
									</div>
								</body>
								</html>
								';
							} 
							$headers = "MIME-Version: 1.0" . "\r\n";
							$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
							$headers .= "From: " . $this->email_sender_address . "\r\n";
							$safe_sender = filter_var($this->email_sender_address, FILTER_VALIDATE_EMAIL);
							if ($safe_sender) {
								$envelope_sender = "-f" . escapeshellarg($safe_sender);
								mail($to, $subject, $message, $headers, $envelope_sender);
							}
						}
					
				} else {
					if (!$csrf_valid) {
						if ($this->language === 'de') { $this->login_error = "Sitzung abgelaufen. Bitte die Seite neu laden."; } 
						else { $this->login_error = "Expired session token. Please refresh the page and try again."; }
						WriteLogLine($this->log_file, "error", "MainLogin: ❌ CSRF token mismatch on failed login. Username: " . $this->sanitize_username($_POST['username'] ?? 'Unknown') );
						usleep(random_int(1500000, 2700000));
					} else {
						$this->register_login_failure($_SERVER['REMOTE_ADDR'], $this->login_bruteforce_file, $this->login_failures, $this->login_block_seconds, $this->brute_force_window, $this->brute_force_factor, $this->sanitize_username($_POST['username'] ?? ''));
						if ($this->language === 'de') { $this->login_error = "Benutzername und/oder Passwort falsch"; } 
						else { $this->login_error = "Invalid username/password or account not configured"; }
						WriteLogLine($this->log_file, "error", "MainLogin: ❌ authentication failed for user ".$this->sanitize_username($_POST['username'] ?? ''));
						usleep(random_int(1500000, 2700000));
					}
				}
			}
		}
		return false;
	}

	private function renderPage() {
		global $cloud_beta;
		// AJAX Interception (Identical logic to original, now in method)
		if ($this->is_ajax_request()) {
			header('Content-Type: application/json');
			http_response_code(401);
			$ajax_response = ['authenticated' => false, 'error' => $this->login_error ?? 'Unauthorized access. Please log in.'];
			if (isset($_SESSION['2fa_pending'])) {
				$ajax_response['2fa_pending'] = true;
				$ajax_response['error'] = '2FA verification required.';
			}
			echo json_encode($ajax_response);
			exit();
		}

		if (!isset($_SESSION['2fa_pending'])) {
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_regenerate_id(true);
			}
		}

		if (isset($_GET['back'])) {
			unset($_SESSION['2fa_pending'], $_SESSION['2fa_user'], $_SESSION['2fa_code'], $_SESSION['2fa_token'], $_SESSION['2fa_expires'], $_SESSION['2fa_remember_me']);
			header("Location: " . $_SERVER['PHP_SELF']);
			exit();
		}
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['2fa_pending']) && !isset($_GET['back'])) {}

				?>
				<!DOCTYPE html>
				<html lang="en">
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<link rel="icon" href="/images/favicon-default.ico" />
					<meta name="theme-color" content="#ffffff">
				<?php if ($this->isCloudOnly) { ?>
					<link rel="manifest" href="/cloud/manifest.php">
				<?php } else { ?>
					<link rel="manifest" href="/auth_adm_php/manifest.json">
				<?php } ?>
					<meta name="apple-mobile-web-app-capable" content="yes">
					<meta name="apple-mobile-web-app-status-bar-style" content="default">
					<link rel="apple-touch-icon" href="/images/background-logo-192.png">
				<script>
						if ('serviceWorker' in navigator) {
							<?php if ($this->isCloudOnly) { ?>
								navigator.serviceWorker.register('/cloud/service-worker.js');
							<?php } else { ?>
								navigator.serviceWorker.register('/auth_adm_php/service-worker.js');
							<?php } ?>
						}
					</script>
					<script src="/script/info.js"></script>
					<title>Login</title>
				<link rel="stylesheet" href="/styles-login/styles.css">
				</head>
			<body lang=DE class="<?php echo ($this->gatekeeper_check_needed ? 'checking-app-mode' : ''); ?> <?php echo ($this->isCloudOnly ? 'cloud-login' : ''); ?>">
			<?php if ($this->gatekeeper_check_needed): ?>
			<style>
				/* Hide login form while verifying environment */
				body.checking-app-mode .login-container { display: none !important; }
				body.checking-app-mode::before {
					content: "🔒 Verifying App Environment...";
					display: block;
					font-family: sans-serif;
					color: #666;
					margin-top: 25vh;
					text-align: center;
					font-size: 1.2rem;
				}
			</style>
			<?php
			$app_mode_sig = '1';
			if (isset($_COOKIE[$this->cookie_name])) {
				$parts = explode(':', $_COOKIE[$this->cookie_name]);
				if (count($parts) === 2) {
					$client_env = $_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? '');
					$app_mode_sig = hash_hmac('sha256', $parts[0] . 'app_mode' . $client_env, $this->api_key);
				}
			}
			?>
			<script>
			(function() {
				// STRICT detection: Is this running as an installed App?
				const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
									window.navigator.standalone === true || 
									document.referrer.includes('android-app://');
			
				const url = new URL(window.location.href);
				// If we just logged out or are in the middle of a security check, 
				// do NOT trigger auto-login.
				const isManualContext = url.searchParams.has('logout') || url.searchParams.has('loggedout') || url.searchParams.has('st');

				if (isStandalone && !isManualContext) {
					// APP DETECTED: Reload with secret flag to allow Auto-Login
					console.log("App Mode Detected. Authenticating...");
					url.searchParams.set('app_mode_confirmed', '<?php echo $app_mode_sig; ?>');
					window.location.replace(url.toString());
				} else {
					// BROWSER DETECTED: Show login form. Auto-Login denied.
					console.log("Browser Mode Detected. Auto-login blocked.");
					document.body.classList.remove('checking-app-mode');
				}
			})();
			const betaBadgeSvg = `
			<svg xmlns="http://www.w3.org/2000/svg" width="80" height="24" viewBox="0 0 80 24" role="img" aria-label="Beta">
			<defs>
				<linearGradient id="betaGradient" x1="0%" y1="0%" x2="100%" y2="0%">
				<stop offset="0%" stop-color="#6366F1"/>
				<stop offset="100%" stop-color="#EC4899"/>
				</linearGradient>
			</defs>
			<rect x="0" y="0" width="80" height="24" rx="12" fill="url(#betaGradient)"/>
			<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
					font-family="system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
					font-size="11" font-weight="700" fill="#FFFFFF" letter-spacing="2">
				BETA
			</text>
			</svg>
			`;
			</script>
			<?php endif; ?>

			
			
			<?php
			// Password breached modal
			if (isset($this->hibp_breached) && $this->hibp_breached === true) { ?>
				<div id="hibpModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; justify-content: center; align-items: center;">
					<div style="background: #fff; padding: 20px; border: 4px solid #cc0000; border-radius: 8px; min-width: 350px; max-width: 600px; resize: both; overflow: auto; box-shadow: 0 0 20px rgba(0,0,0,0.5);">
						<?php if ($this->language === 'de') { ?>
							<h2 style="color: #cc0000; margin-top: 0;"> ️ SICHERHEITSWARNUNG</h2>
							<p style="font-size: 1.1rem; line-height: 1.5;">
								Dieses Passwort wurde in einem bekannten Datenleck gefunden, wenn auch <i><b>nicht</b></i> direkt verbunden mit deinem Account hier.<br><br>
								Deshalb ist 2-Faktor Authentifizierung zwingend erforderlich. Bitte sehe jetzt in deine Mailbox!<br><br>
								<b>Hinweis: Bitte ändere das Passwort umgehend!</b> 
							</p>
							<div style="text-align: right; margin-top: 20px;"><button onclick="document.getElementById('hibpModal').style.display='none'" style="padding: 8px 16px; cursor: pointer; font-size: 1rem;">Schließen</button></div>
						<?php } else { ?>
							<h2 style="color: #cc0000; margin-top: 0;"> ️ SECURITY WARNING</h2>
							<p style="font-size: 1.1rem; line-height: 1.5;">
								This password was found in a public data breach, even though <i><b>not</b></i> directly connected to your account here.<br>
								Therefore, 2-factor-authentication is mandatory for this login. Please go now to your mailbox!<br><br>
								<b>Remark: Please change your password immediately!</b> 
							</p>
							<div style="text-align: right; margin-top: 20px;"><button onclick="document.getElementById('hibpModal').style.display='none'" style="padding: 8px 16px; cursor: pointer; font-size: 1rem;">Close</button></div>
						<?php } ?>
					</div>
				</div>
			<?php } 

			// ANTI-BOT & INTERACTION WRAPPER STYLES
			?>
			<style>
				#interaction-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 80vh; transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
				#interaction-wrapper .logos-header { z-index: 10; position: relative; text-align: center; background: transparent; transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(50px); }
 				#interaction-wrapper .login-container { opacity: 0; transform: translateY(-30px); pointer-events: none; transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1; position: relative; margin-top: -100px; }
 				.interaction-prompt { margin-top: 20px; font-family: sans-serif; color: #444; font-weight: bold; font-size: 1.15em; text-align: center; transition: opacity 0.4s ease; animation: pulseText 2s infinite; background: rgba(255,255,255,0.8); padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); position: relative; z-index: 20; }
				@keyframes pulseText { 0% { opacity: 0.7; transform: translateY(50px) scale(1); } 50% { opacity: 1; transform: translateY(50px) scale(1.03); } 100% { opacity: 0.7; transform: translateY(50px) scale(1); } }
 				
 				/* Unlocked State */
				#interaction-wrapper.unlocked .logos-header { transform: translateY(-40px); }
 				#interaction-wrapper.unlocked .login-container { opacity: 1; transform: translateY(0); pointer-events: auto; margin-top: 20px; }
				#interaction-wrapper.unlocked .interaction-prompt { opacity: 0; pointer-events: none; position: absolute; animation: none; }
 			</style>

			<div id="interaction-wrapper" class="<?php echo isset($_SESSION['2fa_pending']) ? 'unlocked' : ''; ?>">
				<div class="logos-header">
			
			<?php
			$config_skip = (isset($this->isCloudOnly) && $this->isCloudOnly === true);
			if ($config_skip) { ?>
				<div style="margin-bottom: 0px;  padding: 0px !important;">
					<p style="margin-top: 30px;"><img src="/cloud/images/cloud-logo-512-square.png" alt="Logo" class="logolarge"></p>
					<?php echo (!empty($cloud_beta) ? '<span style="position:relative;"><span style="position:absolute;top:-2.7em;left:50%;transform:translateX(-60%);color:#6d632c;font-size:46px;opacity:0.5;">Beta</span></span>' : ''); ?>
					<h2 style="color: #6d632c; font-size: 32pt; margin-top: -37px; margin-bottom: -20px; padding: 0px !important;">
						<?php echo $GLOBALS['mycloud_svg_logo'] ; ?>
					</h2>
				</div>
			
			<?php		} else { ?>
				<div style="margin-bottom: 0px;  padding: 0px !important;">
					<p style="margin-bottom: 0px;"><img src="/cloud/images/cloud-logo-512-square.png" alt="Logo" class="logolarge"></p>
					<h2 style="font-size: 26pt; margin-top: 0px; margin-bottom: 0px; padding: 0px !important;">
						<?php echo ($this->language === 'de') ? 'Konfiguration' : 'Configuration'; ?>
					</h2>
				</div>
			
			<?php		} ?>
				</div> <?php if (!isset($_SESSION['2fa_pending'])) { ?>
					<div class="interaction-prompt">
						<?php echo ($this->language === 'de') ? 'Zum Anmelden klicken oder tippen' : 'Click or tap anywhere to log in'; ?>
					</div>
				<?php } ?>
			
				<div class="login-container">
					<?php
					if (isset($this->login_error)) {
						echo "<p class='error-message'>" . htmlspecialchars($this->login_error) . "</p>";
					}
			
					if (isset($_SESSION['2fa_pending'])) {
						?>
						<form method="post">
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
							<?php  if ($this->language === 'de') {  ?>
								<label for="code">Code<br>(wie per E-Mail erhalten):</label>
							<?php  } else {  ?>
								<label for="code">Code<br>(as received via E-Mail):</label>
							<?php  } ?>
							<input type="text" id="code" name="code" required>
							<p>&nbsp;</p>
							<?php  if ($this->language === 'de') {  ?>
							<input type="submit" value="Bestätigen">
							<?php  } else {  ?>
							<input type="submit" value="Verify code">
							<?php  } ?>
						</form>
							<?php  if ($this->language === 'de') {  ?>
								<p>&nbsp;</p><p><a href="?back=1">⬅ Zurück zu Login</a></p>
							<?php  } else {  ?>
								<p>&nbsp;</p><p><a href="?back=1">⬅ Back to login</a></p>
							<?php  } ?>
						<?php
					} else {
						if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
							!(isset($_POST['username'], $_POST['password'])) &&
							!isset($_POST['code']) ) {
							$url = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
							echo '<script>
									window.location.replace("'.$url.'");
									</script>';
							echo '<noscript>';
							echo '  <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" />';
							echo '</noscript>';
							exit;
						}
						?>
						<form method="post">
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
							<input type="hidden" name="hi_token" id="hi_token" value="">
							<?php  if ($this->language === 'de') {  ?>
								<label for="username">Benutzername:</label>
								<input type="text" id="username" name="username" required>
								<label for="password">Passwort:</label>
								<div style="position: relative;">
									<input type="password" id="password" name="password">
									<button type="button" id="togglePassword" style="position: absolute; top: 35%; right: 5px; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; font-size: 16px; line-height: 1;">👁</button>
								</div>
								<p style="align-items: center; text-align: left; font-size: 10pt;"> 
									<input type="checkbox" id="remember_me" name="remember_me" value="1" style="margin-right: 5px;" checked>&nbsp;Auf diesem Gerät erinnern?
								</p>
							<?php  } else {  ?>
								<label for="username">User ID:</label>
								<input type="text" id="username" name="username" required>
								<label for="password">Password:</label>
								<div style="position: relative;">
									<input type="password" id="password" name="password">
									<button type="button" id="togglePassword" style="position: absolute; top: 35%; right: 5px; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; font-size: 16px; line-height: 1;">👁</button>
								</div>
								<p style="align-items: center; text-align: left; font-size: 10pt;"> 
									<input type="checkbox" id="remember_me" name="remember_me" value="1" style="margin-right: 5px;" checked>&nbsp;Remember on this device?
								</p>
						<?php  } ?>
							<input type="submit" value="<?php echo ($this->language === 'de') ? 'Anmelden' : 'Login'; ?>">
						</form>
						<?php
					}
					?>
				</div> </div> <?php if (!isset($_SESSION['2fa_pending'])) { ?>
				<?php
					$hide_about = false;
					if (isset($GLOBALS['no_about_page']) && is_array($GLOBALS['no_about_page']) && !empty($_SERVER['HTTP_HOST'])) {
						// Clean the host: lowercase, trim, and strip any port numbers (e.g. domain.com:8080 -> domain.com)
						$current_host = strtolower(trim(explode(':', $_SERVER['HTTP_HOST'])[0]));
						$no_about_hosts = array_map('strtolower', array_map('trim', $GLOBALS['no_about_page']));
						
						$hide_about = in_array($current_host, $no_about_hosts, true); // Strict type comparison
					}
					if (!$hide_about) {
				?>
					<div style="position: fixed; bottom: 15px; right: 15px; z-index: 1000; background: rgba(255,255,255,0.8); padding: 5px 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
						<?php if ($this->language === 'de') { ?>
							<a href="/impressum" style="color: #666; text-decoration: none; font-family: sans-serif; font-size: 0.85em;">Impressum</a>
						<?php } else { ?>
							<a href="/about" style="color: #666; text-decoration: none; font-family: sans-serif; font-size: 0.85em;">About</a>
						<?php } ?>
					</div>
				<?php } ?>
				<script>
					document.addEventListener("DOMContentLoaded", function() {
						const togglePassword = document.getElementById("togglePassword");
						const passwordField = document.getElementById("password");
						if (togglePassword && passwordField) {
							togglePassword.addEventListener("click", function() {
								const currentType = passwordField.getAttribute("type");
								const newType = currentType === "password" ? "text" : "password";
								passwordField.setAttribute("type", newType);
								togglePassword.innerText = newType === "password" ? "👁" : "🙈";
							});
						}

						// Interaction Unlock Logic
						const wrapper = document.getElementById("interaction-wrapper");
						const hiToken = document.getElementById("hi_token");
						let idleTimer;
						const idleTimeout = 15000; // 45 seconds of inactivity to re-lock

						function resetIdleTimer() {
							clearTimeout(idleTimer);
							idleTimer = setTimeout(lockLogin, idleTimeout);
						}

						function lockLogin() {
							if (wrapper.classList.contains("unlocked")) {
								wrapper.classList.remove("unlocked");
								if (hiToken) hiToken.value = ""; // Remove token
								
								// Stop listening for idle resets
								document.removeEventListener("mousemove", resetIdleTimer);
								document.removeEventListener("touchstart", resetIdleTimer);
								document.removeEventListener("keydown", resetIdleTimer);
								
								// Re-attach unlock listeners after a brief delay
								setTimeout(() => {
									document.addEventListener("mousemove", unlockLogin, { passive: true });
									document.addEventListener("touchstart", unlockLogin, { passive: true });
									document.addEventListener("mousedown", unlockLogin, { passive: true });
									document.addEventListener("keydown", unlockLogin, { passive: true });
								}, 300);
							}
						}
						
						function unlockLogin() {
							if (!wrapper.classList.contains("unlocked")) {
								wrapper.classList.add("unlocked");
								if (hiToken) hiToken.value = "unlocked"; // Populate hidden token
								// Remove listeners to save memory once unlocked
								document.removeEventListener("mousemove", unlockLogin);
								document.removeEventListener("touchstart", unlockLogin);
								document.removeEventListener("mousedown", unlockLogin);
								document.removeEventListener("keydown", unlockLogin);

								// Start idle timer and listen for activity to keep it awake
								resetIdleTimer();
								document.addEventListener("mousemove", resetIdleTimer, { passive: true });
								document.addEventListener("touchstart", resetIdleTimer, { passive: true });
								document.addEventListener("keydown", resetIdleTimer, { passive: true });
							}
						}

						// Wait briefly before allowing unlock to prevent accidental triggers on page load
						setTimeout(() => {
							document.addEventListener("mousemove", unlockLogin, { passive: true });
							document.addEventListener("touchstart", unlockLogin, { passive: true });
							document.addEventListener("mousedown", unlockLogin, { passive: true });
							document.addEventListener("keydown", unlockLogin, { passive: true });
						}, 400);
					});
				</script>
				<?php } ?>
				<?php if (isset($_SESSION['2fa_pending'])): ?>
			<script>
			document.addEventListener("DOMContentLoaded", function() {
				const checkInterval = 3000; // 3 seconds
				setInterval(function() {
					fetch("<?php echo $_SERVER['PHP_SELF']; ?>?check2fa=1", {cache: "no-store"})
						.then(res => res.json())
						.then(data => {
							if (data.approved) {
								window.location.href = "<?php echo $_SERVER['PHP_SELF']; ?>";
							}
						})
						.catch(err => console.error("2FA check failed:", err));
				}, checkInterval);
			});
			</script>
			<?php 
		endif; 
	}

	// ################################################################
	// MAIN RUN METHOD
	// ################################################################
	public function run() {
		function_exists('checkAndProcessHeartbeat') && checkAndProcessHeartbeat();

		$this->checkGlobalSecurity();
		$this->checkRememberMe();
		$this->handleLogout();
		$this->handleCheck2FA();
		$this->handleVerificationLink();
		$this->checkPendingSession();
		$this->process2FAPost();

		if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
			// Gatekeeper logic
			if (!isset($_SESSION['2fa_pending'])
				&& !$this->security_check_passed            
				&& !isset($_GET['loggedout'])
				&& !isset($_GET['myCloud_token']) 
				&& !isset($_GET['myCloud_drag'])  
				&& empty($this->gatekeeper_check_needed)      
				&& !$this->is_ajax_request()
				&& $_SERVER['REQUEST_METHOD'] !== 'POST') {
				
				$target = $this->get_secure_processing_url('securitycheck');
				echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body><script>window.location.replace("' . $target . '");</script></body></html>';
				exit();
			}

			$skip_rendering = $this->processLoginPost();
			
			// If processLoginPost returns true, it means we hit the "goto skip_login_check" condition (security failure).
			// We effectively fall through to renderPage().
			
			$this->renderPage();
		}
	}
	
	private function isFirewallCheckBypassed() {
        global $firewall_check_bypass_post_get;
        if (isset($firewall_check_bypass_post_get) && is_array($firewall_check_bypass_post_get)) {
            foreach ($firewall_check_bypass_post_get as $key) {
                if (isset($_POST[$key]) || isset($_GET[$key])) {
                    return true;
                }
            }
        }
        return false;
    }
}

// Instantiate and Run
$loginApp = new Login();
$loginApp->run();