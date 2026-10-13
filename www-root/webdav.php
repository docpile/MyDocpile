<?php
/**
 * Public WebDAV Entry Point
 * * This stub strictly bridges the public web request to the protected
 * application logic residing outside the document root.
 */

// Define the security token
define('IPS_Token', 'WEB_DAV_ACCESS'); 

include __DIR__ . '/cloud/config.php';
$app_dir = $work_dir;

if (!$app_dir || !file_exists($app_dir . '/dav_server.php')) {
    http_response_code(500);
    die("Configuration Error: Cloud application not found.");
}

require_once $app_dir . '/dav_server.php';