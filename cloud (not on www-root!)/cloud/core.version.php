<?php
/**
 * ============================================================================
 * MODULE: Application Version State
 * ============================================================================
 * Provides the current application version number, for UI display.
 */
 
$version = '0.0.0'; // Default fallback
$versioninfo = '';

if (file_exists($versionFile)) {
    // Read file into an array
    $lines = file($versionFile);
    
    // 1. Get current version from that first row
    if (isset($lines[0])) {
        $version = trim($lines[0]);
        // NOTE: We do NOT remove line 1 from the array
    }
    
    // 2. Take the REST (actually the whole file including line 1) for the modal
    $versioninfo = implode("", $lines);
}

// Ensure it is available globally
$GLOBALS['versioninfo'] = $versioninfo;