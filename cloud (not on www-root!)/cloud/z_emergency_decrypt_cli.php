#!/usr/bin/env php
<?php
/**
 * myCloud Emergency Vault Decryptor
 * Recursively decrypts a V2 AES-GCM vault offline.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the terminal.\n");
}

if ($argc < 2) {
    echo "Usage: ./emergency_decrypt.php <path_to_vault> [output_directory]\n";
    echo "Example: ./emergency_decrypt.php /var/www/cloud/data/vault /tmp/recovered_files\n";
    exit(1);
}

$vaultDir = rtrim($argv[1], DIRECTORY_SEPARATOR);
$outDir = isset($argv[2]) ? rtrim($argv[2], DIRECTORY_SEPARATOR) : $vaultDir . '_decrypted';

$saltFile = $vaultDir . DIRECTORY_SEPARATOR . '.mycloud_crypto_salt';

if (!is_dir($vaultDir)) die("❌ Error: Vault directory not found.\n");
if (!file_exists($saltFile)) die("❌ Error: .mycloud_crypto_salt not found in the specified directory.\n");

echo "Enter Vault Password: ";
system('stty -echo');
$password = trim(fgets(STDIN));
system('stty echo');
echo "\n";

if (empty($password)) {
    die("❌ Error: Password cannot be empty.\n");
}

echo "Reading vault configuration...\n";
$payload = file_get_contents($saltFile);
$decodedPayload = base64_decode($payload, true);
if ($decodedPayload === false || !json_decode($decodedPayload)) {
    $decodedPayload = $payload; // Fallback in case it wasn't double-base64 wrapped
}

$data = json_decode($decodedPayload, true);
if (!$data || !isset($data['version']) || $data['version'] !== 2) {
    die("❌ Error: Invalid vault configuration or unsupported V1 vault.\n");
}

$salt = base64_decode($data['salt']);
$iv = base64_decode($data['iv']);
$wrappedDEK = base64_decode($data['wrappedDEK']);

echo "Deriving Key Encryption Key (KEK)... (This takes a moment)\n";
// PBKDF2 / SHA-512 / 600k Iterations / 32 Bytes
$kek = hash_pbkdf2('sha512', $password, $salt, 600000, 32, true);

echo "Unwrapping Data Encryption Key (DEK)...\n";
// AES-GCM appends a 16-byte Auth Tag to the end of the ciphertext
$dekCipher = substr($wrappedDEK, 0, -16);
$dekTag = substr($wrappedDEK, -16);

$dek = openssl_decrypt($dekCipher, 'aes-256-gcm', $kek, OPENSSL_RAW_DATA, $iv, $dekTag);

if ($dek === false) {
    die("❌ Error: Incorrect password or corrupted vault salt.\n");
}

echo "✅ Vault unlocked successfully! DEK obtained.\n";
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

echo "Starting decryption to: $outDir\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vaultDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$successCount = 0;
$failCount = 0;

foreach ($iterator as $file) {
    if ($file->getFilename() === '.mycloud_crypto_salt') continue;

    $relPath = substr($file->getPathname(), strlen($vaultDir) + 1);
    
    // --- 1. Decrypt Filenames & Directories ---
    $pathParts = explode(DIRECTORY_SEPARATOR, $relPath);
    $decryptedParts = [];
    
    foreach ($pathParts as $part) {
        if (preg_match('/\.enc$/', $part)) {
            // Base64Url to Standard Base64
            $b64 = substr($part, 0, -4);
            $b64 = str_replace(['-', '_'], ['+', '/'], $b64);
            $padLen = 4 - (strlen($b64) % 4);
            if ($padLen < 4) $b64 .= str_repeat('=', $padLen);
            
            $raw = base64_decode($b64);
            if ($raw !== false && strlen($raw) > 28) {
                $nameIv = substr($raw, 0, 12);
                $nameCipher = substr($raw, 12, -16);
                $nameTag = substr($raw, -16);
                
                $clearName = openssl_decrypt($nameCipher, 'aes-256-gcm', $dek, OPENSSL_RAW_DATA, $nameIv, $nameTag);
                $decryptedParts[] = $clearName !== false ? $clearName : $part;
            } else {
                $decryptedParts[] = $part;
            }
        } else {
            $decryptedParts[] = $part;
        }
    }
    
    $decryptedRelPath = implode(DIRECTORY_SEPARATOR, $decryptedParts);
    $targetPath = $outDir . DIRECTORY_SEPARATOR . $decryptedRelPath;
    
    if ($file->isDir()) {
        if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
        continue;
    }

    // --- 2. Decrypt File Contents ---
    $buffer = file_get_contents($file->getPathname());
    
    // Size check: 16B Empty Pad + 12B IV + min 16B Tag = 44 Bytes
    if (strlen($buffer) < 44) {
        echo " ️ Skipping invalid/corrupted file (too small): $relPath\n";
        $failCount++;
        continue;
    }

    $fileIv = substr($buffer, 16, 12);
    $fileCipher = substr($buffer, 28, -16);
    $fileTag = substr($buffer, -16);

    $clearData = openssl_decrypt($fileCipher, 'aes-256-gcm', $dek, OPENSSL_RAW_DATA, $fileIv, $fileTag);

    if ($clearData === false) {
        echo "❌ Failed to decrypt file data: $relPath\n";
        $failCount++;
    } else {
        if (!is_dir(dirname($targetPath))) mkdir(dirname($targetPath), 0755, true);
        file_put_contents($targetPath, $clearData);
        echo "✓ Decrypted: $decryptedRelPath\n";
        $successCount++;
    }
}

echo "\n🎉 Decryption complete! $successCount files recovered, $failCount failed.\n";