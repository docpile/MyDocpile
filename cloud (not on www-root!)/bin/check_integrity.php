<?php
/**
 * File: ../bin/check_integrity.php
 * Asynchronous Code Integrity Checker (Cron Job - Tamper-Proof)
 * Run via CLI: php check_integrity.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied. Run via CLI.");
}

$work_dir = dirname(__DIR__);
require_once $work_dir . '/configuration/config.php';

$secure_code_setting = $SecureCode ?? 'none';
// If the setting is 'none', we still run the check (defaulting to 'all') 
// so the admin can verify the system and prepare the status file BEFORE locking it down.
$effective_setting = ($secure_code_setting === 'none') ? 'all' : $secure_code_setting;

$sig_file = $work_dir . '/configuration/signatures.json';
$status_file = $work_dir . '/configuration/.integrity_status.json';
$pub_key_file = $work_dir . '/configuration/cloud_public_key.pem';

// Helper to write secured status
function write_status($status_file, $api_key, $payload_array) {
    $payload_array['last_run'] = time();
    $json = json_encode($payload_array);
    $final_data = [
        'data' => $payload_array,
        'hmac' => hash_hmac('sha256', $json, $api_key) // Prevents local tampering of the status file
    ];
    file_put_contents($status_file, json_encode($final_data, JSON_PRETTY_PRINT));
}

if (!file_exists($sig_file)) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['Signatures file missing.']]);
    die("Signatures file missing.\n");
}

$sig_data = json_decode(file_get_contents($sig_file), true);

if (!file_exists($pub_key_file)) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['CRITICAL: cloud_public_key.pem is missing.']]);
    die("Public key missing.\n");
}

$public_verification_key = file_get_contents($pub_key_file);
$pubkeyid = openssl_pkey_get_public($public_verification_key);

if (!$pubkeyid) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['CRITICAL: Invalid public key format in cloud_public_key.pem.']]);
    die("Invalid public key.\n");
}

// 1. TAMPER-PROOF ASYMMETRIC VERIFICATION
if (!isset($sig_data['data']) || !isset($sig_data['signature'])) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['CRITICAL: Malformed signatures.json structure.']]);
    die("Malformed signature file.\n");
}

$raw_data = base64_decode($sig_data['data']);
$signature = base64_decode($sig_data['signature']);

$is_valid = openssl_verify($raw_data, $signature, $pubkeyid, OPENSSL_ALGO_SHA256);

if ($is_valid !== 1) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['CRITICAL: RSA Signature Validation Failed. Manifest tampered or signed with wrong key.']]);
    die("Signature Validation Failed.\n");
}

// 2. Decode the now-trusted raw string into our hash map
$signatures = json_decode($raw_data, true);
if (!is_array($signatures)) {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => ['CRITICAL: Valid signature, but corrupted internal JSON payload.']]);
    die("Corrupted payload.\n");
}

$errors = [];

function verify_directory($dir_path, $recursive, $work_dir, &$signatures, &$errors) {
    if (!is_dir($dir_path)) return;

    $iterator = $recursive ? 
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_path, FilesystemIterator::SKIP_DOTS)) : 
        new DirectoryIterator($dir_path);

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $rel_path = str_replace('\\', '/', str_replace(rtrim($work_dir, '/\\') . DIRECTORY_SEPARATOR, '', $file->getRealPath()));
            
            if (!isset($signatures[$rel_path])) {
                $errors[] = "EXTRA_FILE: " . $rel_path;
                continue;
            }
            
            if (!hash_equals($signatures[$rel_path], hash_file('sha256', $file->getRealPath()))) {
                $errors[] = "HASH_MISMATCH: " . $rel_path;
            }
            
            unset($signatures[$rel_path]);
        }
    }
}

// 3. Scan directories based on the effective setting
$dirs_to_check = [];
if ($effective_setting === 'all' || $effective_setting === 'bin') {
    $dirs_to_check[] = ['path' => $work_dir . '/bin', 'recursive' => false];
}
if ($effective_setting === 'all' || $effective_setting === 'cloud') {
    $dirs_to_check[] = ['path' => $work_dir . '/cloud', 'recursive' => true];
}

foreach ($dirs_to_check as $rule) {
    verify_directory($rule['path'], $rule['recursive'], $work_dir, $signatures, $errors);
}

// 4. Check for deleted core files
foreach ($signatures as $missing_file => $hash) {
    $in_scope = false;
    if ($effective_setting === 'all') $in_scope = true;
    elseif ($effective_setting === 'bin' && strpos($missing_file, 'bin/') === 0) $in_scope = true;
    elseif ($effective_setting === 'cloud' && strpos($missing_file, 'cloud/') === 0) $in_scope = true;

    if ($in_scope) {
        $errors[] = "MISSING_FILE: " . $missing_file;
    }
}

// 5. Write resulting state securely
if (empty($errors)) {
    write_status($status_file, $api_key, ['status' => 'ok']);
    if ($secure_code_setting === 'none') {
        echo "Integrity check passed (Dry Run). Ready to enable \$SecureCode.\n";
    } else {
        echo "Integrity check passed. RSA Signature OK.\n";
    }
} else {
    write_status($status_file, $api_key, ['status' => 'failed', 'errors' => $errors]);
    echo "Integrity check failed. Found " . count($errors) . " issues:\n";
    foreach ($errors as $err) {
        echo "- $err\n";
    }
}