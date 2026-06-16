<?php
/**
 * ============================================================================
 * MODULE: Global Environment Bootstrap
 * ============================================================================
 * Handles global variable initialization, dependency injection, and fundamental 
 * environment setup required before any routing or rendering occurs.
 * PHP code should be included here and not later, if any possible
 */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
 die('Direct access not permitted');
}

// --- OAUTH2 POPUP INTERCEPTOR (BYPASS LOGIN SCREEN & SAMESITE COOKIE DROPS) ---
if (isset($_GET['code']) && isset($_GET['state'])) {
    $stateDecoded = @json_decode(base64_decode($_GET['state']), true);
    if (is_array($stateDecoded) && isset($stateDecoded['myCloud_action']) && $stateDecoded['myCloud_action'] === 'oauth_callback') {
        echo '<!DOCTYPE html><html><head><title>Authenticating...</title></head><body style="display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; background:#f4f4f4;">';
        echo '<h3>Finalizing authentication...</h3>';
        echo '<script>';
        echo 'if (window.opener) { window.opener.postMessage({ type: "oauth_code", code: "'.htmlspecialchars($_GET['code']).'", state: '.json_encode($stateDecoded).' }, "*"); window.close(); }';
        echo 'else { document.body.innerHTML = "<h3 style=\'color:red;\'>Error: Parent window connection lost.</h3>"; }';
        echo '</script>';
        echo '</body></html>';
        exit;
    }
}


global $cloud_max_preview_size, $__ex_role, $cloud_dir, $__ex_interface, $isCloudOnly, $L, $cloud_mail_safe_mail_domains, $cloud_oauth_my_domain;

// Global role calculated in server.php
global $__ex_role;
$role = $__ex_role ?? 'no-access';

global $cloud_max_preview_size, $zip_warn_limit;
$js_zip_limit = isset($zip_warn_limit) ? $zip_warn_limit : (300 * 1024 * 1024);

$GLOBALS['mycloud_svg_logo'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -200 6800 1750" style="height:1.7em; width:auto; vertical-align:middle;">
    <g transform="scale(1.34) translate(120,-140)" filter="brightness(0.4)"><path fill="currentColor" stroke="currentColor" stroke-width="20" stroke-linejoin="round" stroke-linecap="round" d="M766 915q-33 0 -44.5 -21.5t-11.5 -49.5q0 -42 16.5 -98.5t41 -117t50.5 -115t45 -92.5q-29 36 -59.5 71.5t-62.5 70.5q-19 22 -38.5 44.5t-40.5 42.5q-17 17 -44 39.5t-57 39t-56 16.5q-27 0 -39 -18t-15.5 -42.5t-3.5 -44.5q0 -65 9.5 -132t22.5 -130q-27 58 -55 116 t-59 115q-13 25 -36 62t-53 78t-63 77t-67.5 58.5t-67.5 22.5q-29 0 -46.5 -19t-26.5 -47.5t-12 -58t-3 -50.5q0 -20 3 -54t10 -67t18 -50q2 -2 5 -5.5t7 -3.5q5 0 5 7q0 3 -2 7q-23 75 -23 156q0 15 2.5 38.5t10 46.5t21 38.5t34.5 15.5q31 0 69.5 -32.5t81 -86t84 -117 t77.5 -126t63 -114t39 -79.5q2 -5 8.5 -16t13.5 -11q3 0 3 5q0 7 -7 22t-11 23q13 -5 25 -5q10 0 10 8q0 7 -3.5 18t-5.5 18q-17 62 -24 131t-7 134q0 18 6.5 38.5t29.5 20.5t56.5 -22t73 -58.5t79.5 -79.5t76 -86.5t63 -79t40 -55.5q6 -9 11 -18t10 -19q-1 -2 -1 -7 q0 -6 10 -23t21 -35t15 -25q9 -8 22.5 -12t25.5 -4q5 0 14 1.5t9 9.5v3l-34 50t-33 51q-34 55 -70 119.5t-67 133.5t-53 138.5t-28 134.5q-1 7 -1 14v14q0 8 1.5 22.5t6.5 26t17 11.5q8 0 16 -2.5t16 -2.5h5t4 2q0 4 -15.5 9t-33 8.5t-23.5 3.5zM281 425l-7 -1 q-19 -3 -51 -10.5t-64.5 -21t-54.5 -33.5t-22 -47q0 -26 19.5 -43t48 -26t57.5 -12.5t47 -3.5q21 0 42.5 2t41.5 5q55 8 110.5 15t110.5 7q15 0 39.5 -2t36.5 -13l3.5 -3.5t4.5 -1.5q2 0 2 2q0 3 -3 6q-11 14 -35.5 21.5t-50.5 9.5t-43 2q-46 0 -92.5 -5.5t-92.5 -12.5 q-13 -2 -27 -3.5t-28 -1.5q-21 0 -50.5 6t-51.5 21.5t-22 44.5q0 22 15 38.5t35.5 27t38.5 16.5q11 3 22 4.5t22 3.5q2 1 5 2t3 3t-3.5 3t-5.5 1z" /></g>
    <g transform=" scale(2.088, 1.44) skewX(10) translate(550, -66)" filter="brightness(0.4)"><path fill="currentColor" stroke="currentColor" stroke-width="10" stroke-linejoin="round" stroke-linecap="round" d="M-128 1134q-27 0 -56 -9.5t-49 -30t-20 -52.5q0 -29 16.5 -53t42 -43.5t53.5 -34.5t50 -24q56 -23 122.5 -35t126.5 -16q18 -46 36.5 -92t28.5 -95l4 -19q-13 17 -35.5 46t-49 59t-53.5 50.5t-48 20.5q-17 0 -23 -14.5t-6 -28.5q0 -38 17 -72.5t34 -66.5l-3 1q-6 0 -6 -6 q0 -1 2 -5q13 -24 22 -41t24 -26t46 -9q6 0 6 5q0 8 -11 27t-24 38.5t-18 27.5q-8 12 -19 35t-19 45.5t-8 36.5q0 5 2 9.5t8 4.5q13 0 33 -15.5t42 -39.5t43 -50.5t36.5 -49t22.5 -35.5q8 -14 11 -29.5t19 -25.5q7 -4 18 -8.5t18 -4.5q10 0 10 10q0 14 -9.5 28.5t-16.5 26.5 q-9 17 -16.5 40.5t-13.5 43.5q-16 44 -30.5 88.5t-34.5 88.5h20q8 0 15.5 1t7.5 6t-10.5 6t-22 0.5t-15.5 -0.5q-20 48 -50.5 98.5t-71 93t-90 68.5t-108.5 26zM-132 1113q49 0 92.5 -26.5t80 -67.5t65 -86.5t46.5 -83.5q-56 4 -120 17.5t-114 39.5q-25 12 -55.5 33 t-53 48.5t-22.5 59.5q0 34 25.5 50t55.5 16z" /></g>
    <g transform="scale(1.1) translate(680,120)" filter="brightness(1)">
        <g transform="translate(1262,0) scale(1.17,1)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M551 450q-1 167 -92.5 258.5t-252.5 91.5h-136v-700h136q161 0 252.5 91.5t92.5 258.5zM218 712q111 0 170.5 -73.5t61.5 -188.5q-2 -115 -61.5 -188.5t-170.5 -73.5h-54v524h54z"/><path fill="currentColor" d="M551 450q-1 167 -92.5 258.5t-252.5 91.5h-136v-700h136q161 0 252.5 91.5t92.5 258.5zM218 712q111 0 170.5 -73.5t61.5 -188.5q-2 -115 -61.5 -188.5t-170.5 -73.5h-54v524h54z"/></g>
        <g transform="translate(1950,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M115 383q70 -73 175 -73q51 0 95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-53 0 -97.5 -19.5t-77.5 -53.5t-51.5 -79.5t-18.5 -97.5q0 -106 70 -177zM290 394q-70 0 -112.5 46t-42.5 120t42.5 120t112.5 46q71 0 113 -46.5t42 -119.5t-42 -119.5t-113 -46.5z"/><path fill="currentColor" d="M115 383q70 -73 175 -73q51 0 95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-53 0 -97.5 -19.5t-77.5 -53.5t-51.5 -79.5t-18.5 -97.5q0 -106 70 -177zM290 394q-70 0 -112.5 46t-42.5 120t42.5 120t112.5 46q71 0 113 -46.5t42 -119.5t-42 -119.5t-113 -46.5z"/></g>
        <g transform="translate(2520,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M388 695l60 59q-69 56 -158 56q-111 0 -178 -71.5t-67 -178.5q0 -105 70.5 -177.5t174.5 -72.5q89 0 158 56l-59 60q-42 -32 -99 -32q-69 0 -109.5 45.5t-40.5 120.5t40.5 120.5t109.5 45.5q58 0 98 -31z"/><path fill="currentColor" d="M388 695l60 59q-69 56 -158 56q-111 0 -178 -71.5t-67 -178.5q0 -105 70.5 -177.5t174.5 -72.5q89 0 158 56l-59 60q-42 -32 -99 -32q-69 0 -109.5 45.5t-40.5 120.5t40.5 120.5t109.5 45.5q58 0 98 -31z"/></g>
        <g transform="translate(2980,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M60 1028v-468q0-52 19-98t52-79.5t78-53t96-19.5t95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-64 0-119-30v-90q49 36 116 36q36 0 65.5-12.5t50-34.5t31.5-52.5t11-66.5t-11-66.5t-31-52.5t-48.5-34.5t-64.5-12.5q-35 0-63.5 12t-49 34t-31.5 52.5t-11 67.5v468h-90z"/><path fill="currentColor" d="M60 1028v-468q0-52 19-98t52-79.5t78-53t96-19.5t95.5 19.5t78 53.5t52.5 79.5t19 97.5t-18.5 97.5t-51.5 79.5t-78 53.5t-97 19.5q-64 0-119-30v-90q49 36 116 36q36 0 65.5-12.5t50-34.5t31.5-52.5t11-66.5t-11-66.5t-31-52.5t-48.5-34.5t-64.5-12.5q-35 0-63.5 12t-49 34t-31.5 52.5t-11 67.5v468h-90z"/></g>
        <g transform="translate(3560,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M190 800h-88v-396h-82l40 -84h130v480zM98 220q-13 -17 -13 -37t13 -36.5t40 -16.5t40 16.5t13 36.5t-13 36.5t-40 16.5t-40 -16z"/><path fill="currentColor" d="M190 800h-88v-396h-82l40 -84h130v480zM98 220q-13 -17 -13 -37t13 -36.5t40 -16.5t40 16.5t13 36.5t-13 36.5t-40 16.5t-40 -16z"/></g>
        <g transform="translate(3820,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M60 610v-510h90v510q0 36 8.5 58.5t26.5 32t35 12.5t45 3v84q-112 0 -158.5 -43t-46.5 -147z"/><path fill="currentColor" d="M60 610v-510h90v510q0 36 8.5 58.5t26.5 32t35 12.5t45 3v84q-112 0 -158.5 -43t-46.5 -147z"/></g>
        <g transform="translate(4080,0)"><path fill="none" stroke="currentColor" stroke-width="40" stroke-linejoin="round" stroke-linecap="round" d="M295 310q170 7 170 141q0 167 -219 167h-41v-77h48q67 0 93 -20.5t26 -68.5q0 -31 -23 -46.5t-59 -15.5q-71 0 -111.5 50t-40.5 120q0 74 43.5 120t113.5 46q63 0 103 -31l60 59q-69 56 -163 56q-114 0 -182 -67t-68 -173q0 -118 67 -191t183 -69z"/><path fill="currentColor" d="M295 310q170 7 170 141q0 167 -219 167h-41v-77h48q67 0 93 -20.5t26 -68.5q0 -31 -23 -46.5t-59 -15.5q-71 0 -111.5 50t-40.5 120q0 74 43.5 120t113.5 46q63 0 103 -31l60 59q-69 56 -163 56q-114 0 -182 -67t-68 -173q0 -118 67 -191t183 -69z"/></g>
    </g>
    </svg>';

 // Calculate User Keys & Cloud Data (Moved from index.php)
 $currentUser = $_SESSION['username'] ?? '';
 $userKeys = []; 
 $userCloudData = [];
 
 if (isset($GLOBALS['user_details']) && is_array($GLOBALS['user_details'])) {
     foreach ($GLOBALS['user_details'] as $ud) {
         if (isset($ud['name']) && $ud['name'] === $currentUser && isset($ud['cloud'])) {

             $userKeys = array_keys($ud['cloud']);
             if (!empty($ud['cloud'])) {
                 foreach ($ud['cloud'] as $k => $c) {
                     if (($c['rights'] ?? '') === 'admin_mode' && empty($GLOBALS['cloud_enable_admin_mode'])) {
                         unset($ud['cloud'][$k]);
                     }
                 }
             }
             
             foreach ($ud['cloud'] as $k => $c) {
                 $r = $c['rights'] ?? 'read-only';
                 // Virtual apps bypass the physical read-only limits
                 if (($c['interface'] ?? 'default') === 'email' && !isset($c['rights'])) $r = 'full';
                 $userCloudData[$k] = [
                     'interface' => $c['interface'] ?? 'default',
                     'rights' => $r
                 ];
            }
             break;
         }
     }
 }

 // Only output the JS payload when requested by the router
 if (isset($_GET['myCloud_dynamic_js'])):
?>

<script>
if ('serviceWorker' in navigator) {
	navigator.serviceWorker.register('/cloud/service-worker.js');
}
// App Install handler preparation
window.myCloudDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
	e.preventDefault(); 
	window.myCloudDeferredPrompt = e; 
});

// ============================================================================
// PHP DATA INJECTION & GLOBAL CONSTANTS
// ============================================================================

 // [CSRF SECURITY] Global Token
 window.myCloudCsrfToken = "<?php echo $_SESSION['myCloud_csrf_token'] ?? ''; ?>";

 // Stabilize PHP session fingerprinting for background AJAX calls
 window.myCloudAcceptLang = <?php echo json_encode($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''); ?>;

 // [CONFIG] Max Preview Size (Bytes)
 const myCloudMaxPreviewSize = <?php echo (int)($GLOBALS['cloud_max_preview_size'] ?? 0); ?>;

 // [CONFIG] IS only localhost allowed in mail module?
 window.myCloudMailOnlyLocalhost = <?php echo $GLOBALS['cloud_mail_only_localhost'] ? 'true' : 'false'; ?>;
 
 // [CONFIG] Is mail picture proxy present?
 window.myCloudEmailProxyEnabled = <?php echo (file_exists(__DIR__ . '/controller.server.email.img_proxy.php')) ? 'true' : 'false'; ?>;

 // [CONFIG] Safe domains for cross-domain email links (Threat Intelligence)
 window.$cloud_mail_safe_mail_domains = <?php echo !empty($GLOBALS['cloud_mail_safe_mail_domains']) ? json_encode($GLOBALS['cloud_mail_safe_mail_domains']) : 'null'; ?>;

 // Inject User Keys for Multi-Cloud Switcher
 const myCloudUserKeys = <?php echo json_encode($userKeys); ?>;
 
 // [CONFIG] OAuth Fixed Redirect URI override
 window.myCloudOAuthDomain = <?php echo !empty($GLOBALS['cloud_oauth_my_domain']) ? json_encode($GLOBALS['cloud_oauth_my_domain']) : 'null'; ?>;

 // [CONFIG] Mail Only mode flag
 window.myCloudIsMailOnly = <?php echo (!empty($isMailOnly) || !empty($GLOBALS['isMailOnly'])) ? 'true' : 'false'; ?>;

// Inject Full Config map
 const myCloudCloudConfig = <?php echo json_encode($userCloudData); ?>;

 // Global click listener to hide context menu
 document.addEventListener('click', (e) => {
     const menu = document.getElementById('myCloudContextMenu');
     if (menu && !menu.contains(e.target)) {
         menu.remove();
     }
 });

// User permissions injected from PHP session/config.
// Determines UI visibility for edit/delete actions.
let myCloudUserRole = <?php echo json_encode($role); ?>;

const myCloudIsBeta = <?php echo !empty($GLOBALS['cloud_beta']) ? 'true' : 'false'; ?>;
const myCloudHasOnlyOffice = <?php echo (!empty($GLOBALS['cloud_onlyoffice_URL']) && !empty($GLOBALS['cloud_onlyoffice_Secret'])) ? 'true' : 'false'; ?>;
const myCloudHasAdvancedPwd = <?php echo (file_exists(__DIR__ . '/controller.server.change_password.php') && file_exists(__DIR__ . '/modules.change_password.php')) ? 'true' : 'false'; ?>;

const ribbonThreshold = <?php echo isset($GLOBALS['cloud_ribbon_threshold']) ? (int)$GLOBALS['cloud_ribbon_threshold'] : 9; ?>;

const MYCLOUD_RIGHTS_MATRIX = <?php echo json_encode($GLOBALS['MYCLOUD_RIGHTS_MATRIX'] ?? ['no-access'=>['blocked'=>'*']]); ?>;

const myCloudSvgLogo = <?php echo json_encode($GLOBALS['mycloud_svg_logo'] ?? ''); ?>.replace(/height:[^;]+;/, 'height:1.2em;');

window.myCloudVersionInfo = <?php echo json_encode($GLOBALS['versioninfo'] ?? "Version info could not be loaded."); ?>;

// Admin Module Bridge
// Safely passes the user's admin status to the cached JS bundle.
// If the user is an admin, it also securely pre-renders and passes the Admin HTML payload.
// This prevents non-admins from receiving sensitive DOM structures in the cached JS.
window.myCloudIsGlobalAdmin = <?php $isAdmin = (isset($_SESSION['username']) && function_exists('getUserRole') && strtolower(getUserRole($_SESSION['username'])) === 'admin'); echo $isAdmin ? 'true' : 'false'; ?>;

window.myCloudAdminHtml = <?php 
    if ($isAdmin) {
        $temp_get = $_GET['myCloud_dynamic_js'] ?? null;
        unset($_GET['myCloud_dynamic_js']);
        include_once __DIR__ . '/modules.admin_ui.php';
        if ($temp_get !== null) $_GET['myCloud_dynamic_js'] = $temp_get;
    }
    echo ($isAdmin && function_exists('CloudAdmin_render_html')) ? json_encode(CloudAdmin_render_html()) : 'null'; 
?>;


// [NEW] Full Language Dictionary
window.myCloud_LANG = <?php echo json_encode($GLOBALS['L'] ?? []); ?>;

const myCloudDetectedLang = <?php echo json_encode($GLOBALS['language'] ?? 'en'); ?>;

window.zip_warn_limit = <?php echo $js_zip_limit ?? (300 * 1024 * 1024); ?>;

window.myCloudAutoDownload = <?php echo !empty($GLOBALS['cloud_autodownload']) ? 'true' : 'false'; ?>;

window.myCloudPendingShares = <?php echo isset($_SESSION['myCloud_shared_stash']) ? count($_SESSION['myCloud_shared_stash']) : 0; ?>;
	
window.myCloudHelpLangs = <?php 
    $hDir = isset($GLOBALS['cloud_dir']) ? $GLOBALS['cloud_dir'] : __DIR__ . '/';
    $helpFiles = glob($hDir . 'help/*.json');
    $validHelpLangs = [];
    if ($helpFiles) {
        foreach ($helpFiles as $f) { $validHelpLangs[] = basename($f, '.json'); }
    }
    echo json_encode(empty($validHelpLangs) ? ['en'] : $validHelpLangs); 	
?>;


// Final Initialization Sequence
// This fires only after ALL scripts and "further inits" are processed by the browser.
document.addEventListener('DOMContentLoaded', function() {
    if (typeof myCloudStartExplorer === 'function') {
        // Automatically start the explorer with the 'all' key.
        // This replaces the inline <script> in your main PHP file.
        myCloudStartExplorer('all');
    }
});

</script>
<?php endif; ?>