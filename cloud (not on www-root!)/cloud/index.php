<?php
/**
 * ============================================================================
 * MODULE: Primary Application Entry Point
 * ============================================================================
 * Acts as the main public-facing gateway. Bootstraps the environment, checks 
 * basic access constraints, and routes incoming requests to the appropriate handlers.
 *
 * AJAX-Only File Explorer with full Windows-style layout and colorful SVG icons.
 *
 * USAGE:
 * require 'index.php';
 * myCloudHandleRequests(); // Call before any output
 * ...
 * myCloudInitExplorer(); // In <body>
 * <button onclick="myCloudStartExplorer()">Open Explorer</button>
 *
 */

$cloud_dir = __DIR__ . "/";
$versionFile = $cloud_dir . 'versioninfo.txt';
include_once __DIR__ . '/core.version.php';
// error_log("Debug: " . print_r( $cloud_dir, true));
	
require_once $GLOBALS['work_dir'] . '/vendor/autoload.php';
use MatthiasMullie\Minify;

/**========================================
 * 1) SERVER-SIDE DIRECT FILESYSTEM HANDLER
 *========================================*/
 
 /* 3) INTEGRATED TEXT EDITOR (Conditionally Included) */
// We must calculate the role here because $role is local to functions above
$__ex_role = 'no-access';

// Get the specific key from the request, just like in myCloudHandleRequests
$__key = $_REQUEST['myCloud_key'] ?? '';
$__ex_interface = 'default';

$__userConfig = null;

require_once __DIR__  . '/core.i18n.php'; 
require_once __DIR__  . '/controller.server.php'; 

myCloudHandleRequests();

if (isset($_SESSION['username']) && function_exists('getUserRole') && strtolower(getUserRole($_SESSION['username'])) === 'admin') {
    include_once __DIR__ . '/modules.admin_ui.php';
    CloudAdmin_handle_ajax(); // Intercepts 'ca_action' POST requests
}

// --- Email Image Proxy Intercept ---
if (!empty($_GET['myCloud_email_proxy_img'])) {
    $proxyPath = __DIR__ . '/controller.server.email.img_proxy.php';
    if (file_exists($proxyPath)) {
        require_once $proxyPath;
        MyCloudEmailImageProxy::handleRequest();
    }
	exit;
}



//header_remove('Content-Security-Policy');
//header(
//    "Content-Security-Policy: ".
//    "default-src 'self'; ".
//    "script-src 'self' https: 'unsafe-eval'; ".
//    "worker-src 'self' blob:; ".
//    "style-src 'self' https: 'unsafe-inline' blob:; ".
//    "font-src 'self' https: data: blob:; ".
//    "img-src 'self' https: data: blob:; ".
//    "connect-src 'self' https: wss: blob:; ".
//    "frame-src 'self' https: blob:; ".
//    "frame-ancestors 'self'; ".
//    "object-src 'none'; ".
//    "base-uri 'self'; ".
//    "form-action 'self' https:;",
//    true
//);


// +.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+
// Start caching for minimizing
ob_start();

 ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="manifest" href="/cloud/manifest.php">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="/images/cloud-logo-512-square.png">
<link rel="icon" href="/images/favicon-default.ico" />
<meta name="theme-color" content="#f0f0f0">
<title>My Document Pile<?php if (!empty($cloud_beta)) { ?> - Beta<?php } ?></title>
</head>
<body  lang=DE  >
<div style="min-height:97vh; display:flex; flex-direction:column; justify-content:flex-start;">
<?php 	

$hasEmailInterface = false;
if (isset($__userConfig['cloud'])) {
	foreach ($__userConfig['cloud'] as $c) {
		if (($c['interface'] ?? '') === 'email') { $hasEmailInterface = true; break; }
	}
}


// ============================================================================
// DYNAMIC MODULE REGISTRY & BOOTSTRAPPER
// ============================================================================
$moduleRegistry = [
	'core' => [
		'css' => ['css.core.theme.php', 'css.core.styles.php', 'css.ui.views.icon_view.php', 'css.ui.modules.settings.php', 'css.ui.modules.help_ui.php', 'css.ui.modules.multi_rename.php', 'css.ui.modules.search.php', 'css.ui.modules.preview.php'],
		'js'  => ['assets.js.core_engine.php', 'assets.js.crypto_engine.php', 'core.ui.main_explorer_ui.php', 'assets.js.ui_helper_functions.php', 'ui.views.icon_view.php', 'core.ui.toolbar_menues.php', 'ui.modules.settings.php', 'ui.modules.multi_rename.php', 'ui.modules.preview.php', 'ui.modules.search.php', 'ui.modules.first_run_assistant.php']
	],
	'editor' => [
		'css' => ['css.ui.modules.editor.php'],
		'js'  => ['ui.modules.editor.php']
	],
	'office' => [
		'css' => ['css.ui.views.office_view.php'],
		'js'  => ['ui.views.office_view.php', 'ui.modules.onlyoffice.php']
	],
	'email' => [
		'css' => ['css.modules.email.php'],
		'js'  => ['modules.email.ui.php', 'modules.email.composer.php', 'modules.email.settings.php', 'modules.email.contacts.php']
	],
	'admin' => [
		'css' => ['css.modules.admin_ui.php'],
		'js'  => []
	],
	'share' => [
		'css' => ['css.modules.share_ui.php'],
		'js'  => []
	]
];
// ============================================================================

$activeModules = ['core', 'editor', 'office', 'admin', 'share'];

if ($hasEmailInterface) {
	$activeModules[] = 'email';
	if (file_exists(__DIR__ . '/modules.email.alias_admin.php')) {
		$moduleRegistry['email']['js'][] = 'modules.email.alias_admin.php';
	}
}

// ============================================================================
$cssFiles = [];
$jsBundleFiles = [];

foreach ($activeModules as $mod) {
	if (isset($moduleRegistry[$mod])) {
		$cssFiles = array_merge($cssFiles, $moduleRegistry[$mod]['css'] ?? []);
		$jsBundleFiles = array_merge($jsBundleFiles, $moduleRegistry[$mod]['js'] ?? []);
	}
}
// ============================================================================


$cssMtimes = array_map(function($f) use ($cloud_dir) { return file_exists($cloud_dir . $f) ? filemtime($cloud_dir . $f) : 0; }, $cssFiles);
$cssMtime = max($cssMtimes);
if ($cssMtime === 0) $cssMtime = time();
echo '<link rel="stylesheet" href="?myCloud_css=1&v=' . $cssMtime . '">';
echo '<script src="?myCloud_dynamic_js=core.bootstrap.php&t=' . microtime(true) . '"></script>';
// ============================================================================
$jsMtimes = array_map(function($f) use ($cloud_dir) { return file_exists($cloud_dir . $f) ? filemtime($cloud_dir . $f) : 0; }, $jsBundleFiles);
$jsMtime = max($jsMtimes);
echo '<script src="?myCloud_js=1&v=' . $jsMtime . '"></script>';
echo '<script src="?myCloud_dynamic_js=core.heartbeat.php&t=' . microtime(true) . '"></script>';
// ============================================================================


    // [NEW] RTL Detection
    $rtl_langs = ['ar', 'fa', 'he', 'ur'];
    $dirAttr = in_array($language, $rtl_langs) ? 'dir="rtl"' : 'dir="ltr"';
?>
<div id="myCloudContainer" class="myCloudContainer" <?php echo $dirAttr; ?>>
    
  <?php if (!empty($isCloudOnly)): ?>
      <div class="myCloud-floating-logout" onclick="myCloudDoLogout()" title="<?php echo $L['logout']; ?>">
          <svg viewBox="0 0 24 24">
              <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zM20 3H4c-1.1 0-2 .9-2 2v4h2V5h16v14H4v-4H2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
          </svg>
          <span class="myCloud-logout-text"><?php echo $L['logout']; ?></span>
      </div>

    <?php else: ?>
      <div class="myCloud-floating-logout" onclick="myCloudCloseExplorer()" title="<?php echo $L['close']; ?>">
          <svg viewBox="0 0 24 24">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 17.59 17.59 13.41 12 19 6.41z"/>
          </svg>
          <span class="myCloud-logout-text"><?php echo $L['close']; ?></span>
      </div>
  <?php endif; ?>
  <div id="myCloudCloudSwitcher" class="myCloudCloudSwitcher" style="display:none;"></div>
  <div id="myCloudToolbar" class="myCloudToolbar"></div>
  
  <div class="myCloudBody">
    <div class="myCloudTree"></div>
    <div class="myCloudResizer"></div>
    <div class="myCloudDetails"></div>
  </div>
  <div class="myCloudVersionBadge" onclick="myCloudVerShowInfo()">
    <?php
        $logo = $GLOBALS['mycloud_svg_logo'];
        $logo = preg_replace('/height:[^;]+;/', 'height:1.15em;', $logo);
        echo $logo . ' ' . $version . ' &copy; 2025–' . date("Y");
    ?>
  </div>
    <?php if (!empty($cloud_beta)) { ?>
		<div class="myCloudBetaBadge">
			Beta
		</div>
	<?php } ?>

</div>

<div id="myCloudModalOverlay" class="myCloudOverlay">
  <div id="myCloudModal" class="myCloudModal"></div>
</div>
<?php 
    // [CSRF SECURITY] Ensure token exists for the View
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Generate cryptographically strong CSRF token with multiple entropy sources
    if (empty($_SESSION['myCloud_csrf_token']) || 
        empty($_SESSION['csrf_timestamp']) || 
        (time() - $_SESSION['csrf_timestamp']) > 3600) { // Refresh every hour
        
        // Combine multiple entropy sources for maximum security
        $entropy_sources = [
            random_bytes(32),                    // Primary cryptographic random
            hash('sha256', uniqid('', true)),    // Microsecond timestamp
            hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
            hash('sha256', session_id()),
            hash('sha256', microtime(true) . getmypid())
        ];
        
        $combined_entropy = hash('sha256', implode('', $entropy_sources));
        $_SESSION['myCloud_csrf_token'] = bin2hex(random_bytes(32)) . $combined_entropy;
        $_SESSION['csrf_timestamp'] = time();
        
    }

    global $cloud_max_preview_size, $__ex_role, $cloud_dir, $__ex_interface;
    
	echo '<script src="?myCloud_dynamic_js=modules.share_ui.php&t=' . microtime(true) . '"></script>';
	echo '<script src="?myCloud_dynamic_js=modules.stfp_admin_mode.js.php&t=' . microtime(true) . '"></script>';
	


	if (function_exists('CloudAdmin_render_html')) {
		echo '<script src="?myCloud_dynamic_js=modules.admin_ui.php&t=' . microtime(true) . '"></script>';
	}
 ?>
</body>
</html>
<?php 	
// +.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+
echo myCloudMinifySafe_Html(myCloudMinifyHtmlBlocks(ob_get_clean()));
//echo ob_get_clean();
// +.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+
