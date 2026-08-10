<?php
/**
 * ============================================================================
 * MODULE: Webmail Backend Controller (Active IMAP & Native SMTP)
 * ============================================================================
 * Manages live IMAP connections with strict UTF-8 enforcement to prevent
 * JSON encoding failures, alongside attachment extraction and native SMTP.
 */
 

if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}


use Webklex\PHPIMAP\ClientManager;

/**
 * ============================================================================
 * WEBKLEX VENDOR BUG WORKAROUND
 * ============================================================================
 * Intercept and suppress the known "Attempt to read property on string" warning 
 * triggered internally by Webklex\PHPIMAP\Header when parsing malformed addresses.
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (strpos($errfile, 'Header.php') !== false && (
        strpos($errstr, 'Attempt to read property "mailbox" on string') !== false || 
        strpos($errstr, 'Attempt to read property "host" on string') !== false
    )) {
        return true; // Silently drop this specific vendor warning
    }
    return false; // Pass all other errors to the default application error handler
}, E_WARNING | E_NOTICE);


$easModulePath = __DIR__ . '/controller.server.email.eas.php';
if (file_exists($easModulePath)) {
    require_once $easModulePath;
}


class MyCloudEmailServer {
    private $key;
    private $username;
    private $config_file;
    private $contacts_file;
	private $auto_contacts_file;
    private $cache_file_cache = [];
    private $body_cache_file_cache = [];
	private $dirty_caches = [];

    public function __construct($key, $username) {
        $this->key = $key;
        // Preserve all valid email characters (including dots).
        // Prevent path traversal by strictly neutralizing directory separators.
        $safe_user = preg_replace('/[^a-zA-Z0-9!#$%&\'*+\-=?^_`{|}~.@]/', '', $username);
        $this->username = str_replace(['/', '\\'], '_', $safe_user);
        
        global $cloud_user_profiles;
        if (!empty($cloud_user_profiles)) {
            $profileDir = rtrim($cloud_user_profiles, '/\\');
            if (!is_dir($profileDir)) @mkdir($profileDir, 0770, true);
            $this->config_file = $profileDir . '/' . $this->username . '_email.json';
            $this->contacts_file = $profileDir . '/' . $this->username . '_contacts.json';
            $this->auto_contacts_file = $profileDir . '/' . $this->username . '_auto_contacts.json';
            $this->cache_dir = $profileDir . '/' . $this->username . '_mailcache';
        } else {
            $temp = $this->user_temp_dir;
            $this->config_file = $temp . '/' . $this->username . '_email.json';
            $this->contacts_file = $temp . '/' . $this->username . '_contacts.json';
            $this->auto_contacts_file = $temp . '/' . $this->username . '_auto_contacts.json';
            $this->cache_dir = $temp . '/' . $this->username . '_mailcache';
        }
        
        if (!is_dir($this->cache_dir)) @mkdir($this->cache_dir, 0770, true);
        $this->body_cache_dir = $this->cache_dir . '/bodies';
        if (!is_dir($this->body_cache_dir)) @mkdir($this->body_cache_dir, 0770, true);
        
        // Create a user-isolated temp directory for file uploads
        $baseTemp = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $this->user_temp_dir = $baseTemp . '/' . $this->username . '_tmp';
        if (!is_dir($this->user_temp_dir)) @mkdir($this->user_temp_dir, 0700, true);

        $baseDir = isset($profileDir) ? $profileDir : $temp;
        $this->tree_favs_file = $baseDir . '/' . $this->username . '_email_tree_favs.json';

        // Automatically trigger background migration for legacy ciphers
        $this->migrateLegacyEncryption();
    }

    private function migrateLegacyEncryption() {
        if (!file_exists($this->config_file)) return;
        
        $configs = $this->loadConfigs(true);
        if (empty($configs)) return;
        
        $changed = false;
        foreach ($configs as &$acc) {
            $keysToMigrate = ['password', 'oauth_token', 'oauth_refresh_token'];
            foreach ($keysToMigrate as $k) {
                if (!empty($acc[$k]) && strpos($acc[$k], 'v3:') !== 0 && $acc[$k] !== '********') {
                    $plain = $this->decryptPassword($acc[$k]);
                    if (!empty($plain)) {
                        $acc[$k] = $this->encryptPassword($plain);
                        $changed = true;
                    }
                }
            }
        }
        unset($acc);

        if ($changed) {
            $this->saveConfigs($configs);
        }
    }
	
    private function sendJsonAndExit($data) {
        global $cloud_beta, $eas_debug_log;
        if (!empty($cloud_beta) && !empty($eas_debug_log)) {
            $data['eas_debug'] = $eas_debug_log;
        }
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            echo json_encode(['status' => 'ERR', 'msg' => 'Server encoding error: ' . json_last_error_msg()]);
        } else {
            echo $json;
        }
       exit;
    }
	
    // Instantly releases the browser UI while keeping the PHP script alive in the background
    private function sendJsonAndContinue($data) {
        global $cloud_beta, $eas_debug_log;
        if (!empty($cloud_beta) && !empty($eas_debug_log)) {
            $data['eas_debug'] = $eas_debug_log;
        }
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        header('Connection: close');
        ob_start();
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        echo $json !== false ? $json : json_encode(['status' => 'ERR', 'msg' => 'Encoding error']);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush();
        @ob_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else ignore_user_abort(true);
    }

	// --- SERVER-SIDE RIGHTS EVALUATOR ---
    private function actionAllowed($action) {
        global $__ex_role, $MYCLOUD_RIGHTS_MATRIX;
        $role = $__ex_role ?? 'read-only';
        
        if ($role === 'admin_mode') return true;
        if (!isset($MYCLOUD_RIGHTS_MATRIX) || !isset($MYCLOUD_RIGHTS_MATRIX[$role])) return false;

        $visited = [];
        $isBlocked = function($r) use (&$isBlocked, &$visited, $action, $MYCLOUD_RIGHTS_MATRIX) {
            if (isset($visited[$r])) return false;
            $visited[$r] = true;
            
            $config = $MYCLOUD_RIGHTS_MATRIX[$r] ?? [];
            if (!isset($config['blocked'])) return false;
            
            // Wildcard blocks everything
            if ($config['blocked'] === '*') return true;
            
            // Direct block
            if (in_array($action, $config['blocked'])) return true;
            
            // Deep inheritance check
            foreach ($config['blocked'] as $parentKey) {
                if (isset($MYCLOUD_RIGHTS_MATRIX[$parentKey]) && $isBlocked($parentKey)) {
                    return true;
                }
            }
            return false;
        };

        return !$isBlocked($role);
    }

    // --- ENCRYPTION HELPERS ---
	
    private function getPbkdf2Key($salt, $iterations = 20000) {
        // Support versioned iteration caps for legacy compatibility
        return hash_pbkdf2('sha256', $this->key, $salt, $iterations, 32, true);
    }

    private function encryptPassword($plain) {
        if (empty($plain) || $plain === '********') return $plain;
        $salt = openssl_random_pseudo_bytes(16);
        // V3 Encryption Enforces 600,000 Iterations
        $key = $this->getPbkdf2Key($salt, 600000);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-GCM'));
        $tag = '';
        $encrypted = openssl_encrypt($plain, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'v3:' . base64_encode($salt) . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted);
    }

    private function decryptPassword($cipher) {
        if (empty($cipher) || $cipher === '********') return '';

        // V3 Encryption (Modern NIST Standard - 600,000 iterations)
        if (strpos($cipher, 'v3:') === 0) {
            $parts = explode(':', substr($cipher, 3));
            if (count($parts) === 4) {
                $key = $this->getPbkdf2Key(base64_decode($parts[0]), 600000);
                $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
                return $decrypted !== false ? $decrypted : null;
            }
        }

        // V2 Encryption (Legacy Compatibility - 20,000 iterations)
        if (strpos($cipher, 'v2:') === 0) {
            $parts = explode(':', substr($cipher, 3));
            if (count($parts) === 4) {
                $key = $this->getPbkdf2Key(base64_decode($parts[0]), 20000);
                $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
                return $decrypted !== false ? $decrypted : null;
            }
        }
        
        $key = hash('sha256', $this->key, true);
        
        // Legacy Fallback 1 (Randomized CBC)
        if (strpos($cipher, ':') !== false && strpos($cipher, 'v2:') === false && strpos($cipher, 'v3:') === false) {
            list($b64_iv, $b64_cipher) = explode(':', $cipher, 2);
            $decrypted = openssl_decrypt(base64_decode($b64_cipher), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, base64_decode($b64_iv));
            return $decrypted !== false ? $decrypted : null;
        }
        
        // --- LEGACY FALLBACK (Static IV) ---
        $static_iv = substr(hash('sha256', 'mycloud_mail_iv' . $this->username, true), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($cipher), 'AES-256-CBC', $key, 0, $static_iv);
        
        // ZERO TRUST: Fail closed. If cryptographic decryption fails, never assume the input is safe plaintext.
        return $decrypted !== false ? $decrypted : null;
    }

// --- PGP WKD HASHING HELPERS ---
    private function zbase32_encode($bytes) {
        $alphabet = 'ybndrfg8ejkmcpqxot1uwisza345h769';
        $result = '';
        $buffer = 0;
        $bufferSize = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bufferSize += 8;
            while ($bufferSize >= 5) {
                $bufferSize -= 5;
                $result .= $alphabet[($buffer >> $bufferSize) & 31];
            }
        }
        if ($bufferSize > 0) {
            $result .= $alphabet[($buffer << (5 - $bufferSize)) & 31];
        }
        return $result;
    }

    private function getWkdHash($email) {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) return false;
        return $this->zbase32_encode(sha1($parts[0], true));
    }

    // --- CONFIG I/O WITH LOCALHOST ENFORCEMENT ---
    private function loadConfigs($raw = false) {
        if (!file_exists($this->config_file)) return [];
        $data = json_decode(file_get_contents($this->config_file), true);
        $configs = is_array($data) ? $data : [];

        // Strictly translate 'localhost' to the loopback IP for legacy configs
        foreach ($configs as &$acc) {
            if (strtolower($acc['imap_host'] ?? '') === 'localhost') $acc['imap_host'] = '127.0.0.1';
            if (strtolower($acc['smtp_host'] ?? '') === 'localhost') $acc['smtp_host'] = '127.0.0.1';
            if (strtolower($acc['eas_host'] ?? '') === 'localhost') $acc['eas_host'] = '127.0.0.1';
        }
        unset($acc);

        if ($raw) return $configs;

        global $cloud_mail_only_localhost;
        if ($cloud_mail_only_localhost === true) {
            $filtered = [];
            foreach ($configs as $id => $acc) {
                $iHost = strtolower($acc['imap_host'] ?? '');
                $sHost = strtolower($acc['smtp_host'] ?? '');
                if (in_array($iHost, ['127.0.0.1', 'localhost', '::1']) && in_array($sHost, ['127.0.0.1', 'localhost', '::1'])) {
                    $filtered[$id] = $acc;
                }
            }
            return $filtered;
        }
        return $configs;
    }


    private function saveConfigs($configs) {
        global $cloud_mail_only_localhost;
        if ($cloud_mail_only_localhost === true) {
            $rawConfigs = $this->loadConfigs(true);
            $merged = [];
            
            // 1. Preserve hidden non-localhost accounts
            foreach ($rawConfigs as $id => $acc) {
                $iHost = strtolower($acc['imap_host'] ?? '');
                $sHost = strtolower($acc['smtp_host'] ?? '');
                if (!in_array($iHost, ['127.0.0.1', 'localhost', '::1']) || !in_array($sHost, ['127.0.0.1', 'localhost', '::1'])) {
                    $merged[$id] = $acc;
                }
            }
            
            // 2. Append the updated/allowed localhost accounts from the current session
            foreach ($configs as $id => $acc) { 
                $merged[$id] = $acc; 
            }
            $configs = $merged;
        }
        file_put_contents($this->config_file, json_encode($configs, JSON_PRETTY_PRINT));
    }

    private function loadContacts() {
        if (!file_exists($this->contacts_file)) return [];
		$raw = file_get_contents($this->contacts_file);
        if (strpos($raw, 'v3:') === 0) {
            $parts = explode(':', substr($raw, 3));
            $key = $this->getPbkdf2Key(base64_decode($parts[0]), 600000);
            $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
            $data = $decrypted ? json_decode($decrypted, true) : [];
            return is_array($data) ? $data : [];
        }        
        if (strpos($raw, 'v2:') === 0) {
            $parts = explode(':', substr($raw, 3));
            $key = $this->getPbkdf2Key(base64_decode($parts[0]), 20000);
            $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
            $data = $decrypted ? json_decode($decrypted, true) : [];
            return is_array($data) ? $data : [];
        }
        $parts = explode(':', $raw, 2);
        if (count($parts) !== 2) return [];
        
        $key = hash('sha256', $this->key, true);
        $decrypted = openssl_decrypt(base64_decode($parts[1]), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, base64_decode($parts[0]));
        if ($decrypted === false) return [];
        
        $data = json_decode($decrypted, true);
        return is_array($data) ? $data : [];
    }

    private function saveContacts($contacts) {
        $json = json_encode($contacts);
        $salt = openssl_random_pseudo_bytes(16);
        $key = $this->getPbkdf2Key($salt, 600000);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-GCM'));
        $tag = '';
        $encrypted = openssl_encrypt($json, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        file_put_contents($this->contacts_file, 'v3:' . base64_encode($salt) . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted));
    }

    private function loadAutoContacts() {
        if (!file_exists($this->auto_contacts_file)) return [];
        $raw = file_get_contents($this->auto_contacts_file);
        if (strpos($raw, 'v3:') === 0) {
            $parts = explode(':', substr($raw, 3));
            $key = $this->getPbkdf2Key(base64_decode($parts[0]), 600000);
            $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
            $data = $decrypted ? json_decode($decrypted, true) : [];
            return is_array($data) ? $data : [];
        }
        if (strpos($raw, 'v2:') === 0) {
            $parts = explode(':', substr($raw, 3));
            $key = $this->getPbkdf2Key(base64_decode($parts[0]), 20000);
            $decrypted = openssl_decrypt(base64_decode($parts[3]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[1]), base64_decode($parts[2]));
            $data = $decrypted ? json_decode($decrypted, true) : [];
            return is_array($data) ? $data : [];
        }
        $parts = explode(':', $raw, 2);
        if (count($parts) !== 2) return [];
        $key = hash('sha256', $this->key, true);
        $decrypted = openssl_decrypt(base64_decode($parts[1]), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, base64_decode($parts[0]));
        if ($decrypted === false) return [];
        $data = json_decode($decrypted, true);
        return is_array($data) ? $data : [];
    }

    private function saveAutoContacts($contacts) {
        $json = json_encode($contacts);
        $salt = openssl_random_pseudo_bytes(16);
        $key = $this->getPbkdf2Key($salt, 600000);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-GCM'));
        $tag = '';
        $encrypted = openssl_encrypt($json, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        file_put_contents($this->auto_contacts_file, 'v3:' . base64_encode($salt) . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted));
    }

    private function autoCollectContacts($to, $cc, $bcc) {
        $all = str_replace(';', ',', $to . ',' . $cc . ',' . $bcc);
        if (empty(trim($all))) return;
        
        $main = $this->loadContacts();
        $auto = $this->loadAutoContacts();
        
        $existingEmails = [];
        foreach (array_merge($main, $auto) as $c) {
            if (!empty($c['emails'])) {
                foreach ($c['emails'] as $e) { $existingEmails[] = strtolower(trim($e['val'])); }
            }
        }
        
        $changed = false;
        preg_match_all('/(?:([^<,]+)\s*<([^>]+)>|([^\s,<>"\'|]+@[^\s,<>"\'|]+))/', $all, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $m) {
            $name = !empty($m[1]) ? trim(str_replace(['"', "'"], '', $m[1])) : '';
            $email = !empty($m[2]) ? trim($m[2]) : (!empty($m[3]) ? trim($m[3]) : '');

            
            $email = strtolower(trim($email));
            if (strpos($email, '@') !== false) {
                list($local, $domain) = explode('@', $email, 2);
                if (preg_match('/[^\x20-\x7E]/', $domain) && function_exists('idn_to_ascii')) {
                    $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46) ?: $domain;
                    $email = $local . '@' . $domain;
                }
            }
            $email = preg_replace('/[^\p{L}\p{N}!#$%&\'*+\-\/=?^_`{|}~.@]/u', '', $email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL, defined('FILTER_FLAG_EMAIL_UNICODE') ? FILTER_FLAG_EMAIL_UNICODE : 0) && !in_array($email, $existingEmails)) {
                 	$auto[] = [
                    'id' => uniqid('auto_'), 'name' => $name ?: explode('@', $email)[0],
                    'first_name' => '', 'last_name' => '', 'emails' => [['type' => 'Collected', 'val' => $email]],
                    'phones' => [], 'company' => '', 'job_title' => '', 'address' => '', 'website' => '', 'labels' => 'Auto-Collected', 'notes' => ''
                ];
                $existingEmails[] = $email;
                $changed = true;
            }
        }
        if ($changed) $this->saveAutoContacts($auto);
    }

    // --- SAFE ENCODING HELPERS ---
    private function decodeFolderName($name) {
        $converted = @mb_convert_encoding($name, "UTF-8", "UTF7-IMAP");
        if ($converted && $converted !== $name) return $converted;
        return $name;
    }

    private function safeUtf8($text, $charset) {
        if (empty($text)) return '';
		// Hard cap charset length to prevent buffer manipulation in iconv/mbstring
        $charset = strtoupper(substr(trim((string)$charset), 0, 50));
        if ($charset === 'UTF-8' || $charset === 'DEFAULT' || $charset === 'UNKNOWN-8BIT') {
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
        if ($converted !== false) return $converted;
        
        $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
        return ($converted !== false) ? $converted : utf8_encode($text);
    }

    private function decodeImapHeader($str) {
        if (empty($str)) return '';
        $decoded = @iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $decoded !== false ? $decoded : $this->safeUtf8($str, 'UTF-8');
    }

    // --- AUTOMATED OAUTH2 TOKEN REFRESH ENGINE ---
    private function refreshOauthTokenIfNeeded(&$acc, $accId) {
        global $MYCLOUD_O365_CLIENT_ID, $MYCLOUD_O365_CLIENT_SECRET;
		if (($acc['auth_type'] ?? '') !== 'oauth2' || empty($acc['oauth_refresh_token'])) return;
        $expires = $acc['oauth_token_expires'] ?? 0;
        if (time() > ($expires - 300)) { // Refresh 5 mins before expiry
            $ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'client_id' => $MYCLOUD_O365_CLIENT_ID,
                'client_secret' => $MYCLOUD_O365_CLIENT_SECRET,
                'refresh_token' => $this->decryptPassword($acc['oauth_refresh_token']),
                'grant_type' => 'refresh_token',
                'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access'
            ]));
            $res = json_decode(curl_exec($ch), true);
            curl_close($ch);
            if (!empty($res['access_token'])) {
                $configs = $this->loadConfigs(true);
                if (isset($configs[$accId])) {
                    $configs[$accId]['oauth_token'] = $this->encryptPassword($res['access_token']);
                    if (!empty($res['refresh_token'])) $configs[$accId]['oauth_refresh_token'] = $this->encryptPassword($res['refresh_token']);
                    $configs[$accId]['oauth_token_expires'] = time() + ($res['expires_in'] ?? 3600);
                    $this->saveConfigs($configs);
                    $acc['oauth_token'] = $configs[$accId]['oauth_token'];
                    $acc['oauth_token_expires'] = $configs[$accId]['oauth_token_expires'];
                }
            }
        }
    }

    // --- IMAP CONNECTOR (WEBKLEX) ---
    private function connectImap($acc, $folder = '', $readonly = false) {
        $host = preg_replace('/[^a-zA-Z0-9.:\[\]-]/', '', $acc['imap_host']);
        $port = !empty($acc['imap_port']) ? (int)$acc['imap_port'] : 143;
        
        $ip = gethostbyname($host);
        if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
            $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            if (($is_private && !in_array($ip, ['127.0.0.1', '::1'])) || $ip === '0.0.0.0') {
                return [null, null, 'Connection to internal networks is forbidden.'];
            }
        } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
            $is_private = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            if (($is_private && !in_array($host, ['127.0.0.1', '::1'])) || $host === '0.0.0.0') {
                return [null, null, 'Connection to internal networks is forbidden.'];
            }
        }

        global $cloud_mail_only_localhost;
        if ($cloud_mail_only_localhost === true && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'])) {
            return [null, null, 'External mail servers are disabled by administrator.'];
        }

        $accId = $acc['id'] ?? null;
        if ($accId) $this->refreshOauthTokenIfNeeded($acc, $accId);

        $cm = new ClientManager();
        
        $auth_type = $acc['auth_type'] ?? 'basic';
        $oauth_token = $this->decryptPassword($acc['oauth_token'] ?? '');
        $pass = $this->decryptPassword($acc['password'] ?? '');
        $enc = strtolower($acc['imap_enc'] ?? 'ssl');
        if ($enc === 'none') $enc = false;

        $options = [
            'host'          => $host,
            'port'          => $port,
            'encryption'    => $enc,
            'validate_cert' => false,
            'username'      => !empty($acc['login_user']) ? $acc['login_user'] : $acc['email'],
            'password'      => $auth_type === 'oauth2' ? $oauth_token : $pass,
            'protocol'      => 'imap'
        ];

        if ($auth_type === 'oauth2') {
            $options['authentication'] = "oauth";
        }

        try {
            $client = $cm->make($options);
            $client->connect();
            
            $folderObj = null;
            if ($folder !== '') {
                $folderObj = $client->getFolderByPath($folder);
                if ($folderObj) {
                    try { $client->openFolder($folderObj, $readonly); } catch (\Throwable $e) {}
                }
            }
            
            return [$client, $folderObj, null];
        } catch (\Throwable $e) {
            return [null, null, "IMAP Connection failed: " . $e->getMessage()];
        }
    }

    // --- NATIVE SMTP SOCKET CLIENT ---
    // --- NATIVE SMTP SOCKET CLIENT ---
    private function sendSmtpMail($acc, $to, $subject, $body, $fromAlias = null, $cc = '', $bcc = '', $attachments = [], $dryRunMimeOnly = false, $requestReceipt = false) {
        // Strict Anti-CRLF Helper
        $stripCRLF = function($str) {
            // ZERO TRUST: Vigorously strip ALL ASCII control characters to prevent header injection via double-encoding
            return trim(preg_replace('/[\x00-\x1F\x7F]/', '', urldecode($str)));
        };

        // Sanitize all user-controlled inputs going into headers
        $to = $stripCRLF($to);
        $subject = $stripCRLF($subject);
        $cc = $stripCRLF($cc);
        $bcc = $stripCRLF($bcc);
        if ($fromAlias) $fromAlias = $stripCRLF($fromAlias);

        $host = $acc['smtp_host'];

        global $cloud_mail_only_localhost;
        if ($cloud_mail_only_localhost === true && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1']) && !$dryRunMimeOnly) {
            return "External mail servers are disabled by administrator.";
        }

        $accId = $acc['id'] ?? null;
        if ($accId) $this->refreshOauthTokenIfNeeded($acc, $accId);

        $port = !empty($acc['smtp_port']) ? $acc['smtp_port'] : 25;

        // ALIAS HANDLING & AUTH MASKING
        $isLocalhost = in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1']);
        $sender_email = $stripCRLF($acc['email']);
        $auth_user = !empty($acc['login_user']) ? $acc['login_user'] : $acc['email'];
        $login_user = !empty($acc['login_user']) ? $acc['login_user'] : $acc['email'];

        $matchedAlias = null;
        if (!empty($fromAlias)) {
            foreach (($acc['aliases'] ?? []) as $al) {
                $alEmail = is_array($al) ? $al['email'] : $al;
                if ($alEmail === $fromAlias) {
                    $matchedAlias = is_array($al) ? $al : ['email' => $al];
                    break;
                }
            }
            if ($matchedAlias || $fromAlias === $acc['email']) {
                $sender_email = $fromAlias;
                // If localhost, use alias for headers to mask the primary account
                if ($isLocalhost) {
                    $auth_user = $fromAlias;
                }
            }
        }
        $auth_type = $acc['auth_type'] ?? 'basic';
        $oauth_token = $this->decryptPassword($acc['oauth_token'] ?? '');
        $pass = $this->decryptPassword($acc['password'] ?? '');
        $enc  = $acc['smtp_enc'] ?? 'none'; 

        // Prevent SSRF mapping of internal infrastructure via SMTP
        $ip = gethostbyname($host);
        if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
            $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            if (($is_private && !in_array($ip, ['127.0.0.1', '::1'])) || $ip === '0.0.0.0') {
                return "Connection to internal metadata or private IP spaces is forbidden.";
            }
        } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
            $is_private = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            if (($is_private && !in_array($host, ['127.0.0.1', '::1'])) || $host === '0.0.0.0') {
                return "Connection to internal metadata or private IP spaces is forbidden.";
            }
        }

        $socket = null;
        if (!$dryRunMimeOnly) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'min_version' => \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    'allow_self_signed' => false,
                    'SNI_enabled' => true,
                    'peer_name' => $host,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                ]
            ]);
            
            $transport = ($enc === 'ssl') ? "ssl://$ip" : "tcp://$ip";
            $socket = @stream_socket_client("$transport:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
            stream_set_timeout($socket, 30);

            if (!$socket) return "SMTP Socket failed ($transport:$port): $errstr ($errno)";
            
            // Bulletproof SMTP read loop: safely breaks on EOF, stream timeouts, or correctly formatted SMTP line endings
            $readRes = function($s) {
                $d = '';
                while (!feof($s)) {
                    $str = fgets($s, 1024);
                    if ($str === false) break;
                    $d .= $str;
                    if (preg_match('/^\d{3}(?: |$)/', $str)) {
                        break;
                    }
                }
                return $d;
            };
            $writeRes = function($s, $c) { fputs($s, $c . "\r\n"); fflush($s); };

            $ehloHost = gethostname();
            if (empty($ehloHost) || $ehloHost === 'localhost') {
                if (!empty($_SERVER['SERVER_NAME'])) {
                    $ehloHost = $_SERVER['SERVER_NAME'];
                } else {
                    $ehloHost = php_uname('n');
                }
            }
            $ehloHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $ehloHost);
            $writeRes($socket, "EHLO $ehloHost");

            $ehloResponse = $readRes($socket);

            // Fallback to HELO if EHLO not supported
            if (substr($ehloResponse, 0, 3) !== '250') {
                $writeRes($socket, "HELO $ehloHost");
                $heloRes = $readRes($socket);
                if (substr($heloRes, 0, 3) !== '250') {
                    fclose($socket);
                    return "EHLO/HELO failed: $heloRes";
                }
            }

            $supports8bit = (stripos($ehloResponse, '8BITMIME') !== false);

            $readRes($socket);

            if ($enc === 'tls') {
                $writeRes($socket, "STARTTLS");
                $res = $readRes($socket);
                if (substr($res, 0, 3) !== '220') return "STARTTLS failed: $res";
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $writeRes($socket, "EHLO [127.0.0.1]");
                $readRes($socket);
            }

             if ($auth_type === 'oauth2' && !empty($oauth_token)) {
                 $xoauth2 = base64_encode("user=" . $login_user . "\x01auth=Bearer " . $oauth_token . "\x01\x01");
                 $writeRes($socket, "AUTH XOAUTH2 " . $xoauth2); $authRes = $readRes($socket);
                 if (substr($authRes, 0, 3) !== '235') { fclose($socket); return "SMTP OAuth2 rejected: $authRes"; }
             } elseif (!empty($pass)) {
                $writeRes($socket, "AUTH LOGIN"); $readRes($socket);
                $writeRes($socket, base64_encode($login_user)); $readRes($socket);
                $writeRes($socket, base64_encode($pass)); $authRes = $readRes($socket);
                if (substr($authRes, 0, 3) !== '235') { fclose($socket); return "SMTP Auth rejected: $authRes"; }
            }

            $writeRes($socket, "MAIL FROM: <$sender_email>"); $readRes($socket);
            
            $all_rcpts = $to . ',' . $cc . ',' . $bcc;
            preg_match_all('/(?:<([^>]+)>|([^\s,<>"\'|]+@[^\s,<>"\'|]+))/', $all_rcpts, $matches);
            $recipients = [];
            foreach ($matches[0] as $i => $fullMatch) {
                $clean_email = !empty($matches[1][$i]) ? $matches[1][$i] : $matches[2][$i];
                $clean_email = trim($clean_email);
                if (strpos($clean_email, '@') !== false) {
                    list($local, $domain) = explode('@', $clean_email, 2);
                    if (preg_match('/[^\x20-\x7E]/', $domain) && function_exists('idn_to_ascii')) {
                        $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46) ?: $domain;
                        $clean_email = $local . '@' . $domain;
                    }
                }
                $clean_email = preg_replace('/[^\p{L}\p{N}!#$%&\'*+\-\/=?^_`{|}~.@]/u', '', $clean_email);
                if (filter_var($clean_email, FILTER_VALIDATE_EMAIL, defined('FILTER_FLAG_EMAIL_UNICODE') ? FILTER_FLAG_EMAIL_UNICODE : 0)) {
                    $recipients[] = $clean_email;
                }
            }
            $recipients = array_unique($recipients);

            $accepted_rcpts = 0;
            $last_rcpt_err = '';
            foreach($recipients as $clean_email) {
                $writeRes($socket, "RCPT TO: <" . $clean_email . ">");
                $rcptRes = $readRes($socket);
                if (substr($rcptRes, 0, 3) !== '250' && substr($rcptRes, 0, 3) !== '251') {
                    $last_rcpt_err = "Recipient rejected ($clean_email): $rcptRes";
                } else {
                    $accepted_rcpts++;
                }
            }
            if ($accepted_rcpts === 0) return $last_rcpt_err ?: "No valid recipients provided.";

            $writeRes($socket, "DATA"); $readRes($socket);
        }

        // Only Base64/UTF-8 encode headers if they contain non-ASCII characters
        $encodeName = function($str) use ($stripCRLF) {
            $str = $stripCRLF($str); // Enforce safety inside encoder
            if (preg_match('/[^\x20-\x7E]/', $str)) {
                return '=?UTF-8?B?' . base64_encode($str) . '?=';
            }
            return (strpos($str, ' ') !== false) ? '"' . $str . '"' : $str;
        };

        // SECURITY FIX 1: Enforce strict separation and encoding on display name
        $public_name = '';
        if ($matchedAlias && isset($matchedAlias['sender_name']) && $matchedAlias['sender_name'] !== '') {
            $public_name = $stripCRLF(trim($matchedAlias['sender_name']));
        } elseif (!empty($acc['sender_name'])) {
            $public_name = $stripCRLF(trim($acc['sender_name']));
        }
        $from_header = !empty($public_name) ? $encodeName($public_name) . " <$sender_email>" : "<$sender_email>";
        
        // Generate RFC Compliant Headers & Originating IP
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        // Strip all non-IP characters to prevent CRLF Header Injection via spoofed proxy headers
        $client_ip = preg_replace('/[^a-zA-Z0-9.:]/', '', explode(',', $client_ip)[0]); 
        $domain = substr(strrchr($sender_email, "@"), 1);
        if (empty($domain)) $domain = 'localhost';
        
        $msg_id = sprintf("<%s.%s@%s>", base_convert(microtime(false), 10, 36), bin2hex(random_bytes(4)), $domain);
        $date = gmdate('D, d M Y H:i:s O'); // RFC 2822 standard date format

        $headers  = "Date: $date\r\n";
        $headers .= "Message-ID: $msg_id\r\n";
        $headers .= "From: $from_header\r\n";
        $headers .= "To: $to\r\n"; // Addresses themselves must not be encoded
        if (!empty($cc)) $headers .= "Cc: $cc\r\n";
        $headers .= "Subject: " . (preg_match('/[^\x20-\x7E]/', $subject) ? '=?UTF-8?B?' . base64_encode($subject) . '?=' : $subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Auto-Submitted: no\r\n"; // Explicitly state this is human-generated

        // REQUEST READ RECEIPT (RFC 3798 Message Disposition Notification)
        if ($requestReceipt) {
            $headers .= "Disposition-Notification-To: <$sender_email>\r\n";
            $headers .= "Return-Receipt-To: <$sender_email>\r\n";
        }

        // CONDITIONAL SENDER HEADER (RFC 5322)
        if (!$isLocalhost && $sender_email !== $acc['email']) {
            $headers .= "Sender: <{$acc['email']}>\r\n";
        }
        
        $headers .= "Reply-To: <$sender_email>\r\n";

        // Encode Subject only
        $headers .= "Priority: normal\r\n";
        $headers .= "Importance: normal\r\n";
        $headers .= "X-Originating-IP: [$client_ip]\r\n";
        $headers .= "X-Mailer: MyDocpile Webmail\r\n";

        // 1. Strip residual HTML (like <p>) if the editor wrapped the PGP block
        $cleanBody = trim(strip_tags($body));
        $isPGPMessage = (strpos($cleanBody, '-----BEGIN PGP MESSAGE-----') === 0);
        $isPGPPubKey = (strpos($cleanBody, '-----BEGIN PGP PUBLIC KEY BLOCK-----') === 0);
        $isPGP = $isPGPMessage || $isPGPPubKey;
        
        if ($isPGP) {
            $body = $cleanBody;
        }
        
        // 2. STRICT RFC 5321 COMPLIANCE: Normalize all line endings to \r\n. 
        // Bare \n causes strict SMTP servers to silently drop the email!
        $body = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $body);

        $hasUnicode = preg_match('/[^\x00-\x7F]/', $body);
		$cType = $isPGPPubKey ? 'text/plain' : 'text/html';
        
        if ($isPGP) {
            $tEnc = '7bit';
            $encBody = $body;
        } else if ($hasUnicode) {
            $tEnc = 'base64';
            $encBody = trim(chunk_split(base64_encode($body)));
        } else {
            $tEnc = 'quoted-printable';
            $encBody = quoted_printable_encode($body);
        }

        // --- PGP/MIME WRAPPER (RFC 3156 Compliant) ---
        if (!empty($attachments)) {
            $boundary = "----=_Part_"  . bin2hex(random_bytes(16));
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $payload = "--$boundary\r\n";
            
            if ($isPGPMessage) {
                $innerBoundary = "----=_PGP_Part_" . bin2hex(random_bytes(16));
                $payload .= "Content-Type: multipart/encrypted; protocol=\"application/pgp-encrypted\"; boundary=\"$innerBoundary\"\r\n\r\n";
                $payload .= "--$innerBoundary\r\n";
                $payload .= "Content-Type: application/pgp-encrypted\r\n\r\n";
                $payload .= "Version: 1\r\n\r\n";
                $payload .= "--$innerBoundary\r\n";
                $payload .= "Content-Type: application/octet-stream; name=\"encrypted.asc\"\r\n";
                $payload .= "Content-Description: OpenPGP encrypted message\r\n";
                $payload .= "Content-Disposition: inline; filename=\"encrypted.asc\"\r\n\r\n";
                $payload .= $body . "\r\n";
                $payload .= "--$innerBoundary--\r\n";
            } else {
                $payload .= "Content-Type: $cType; charset=UTF-8\r\n";
                $payload .= "Content-Transfer-Encoding: $tEnc\r\n\r\n";
                $payload .= $encBody . "\r\n";
            }

            foreach ($attachments as $att) {
                if (file_exists($att['tmp_name'])) {
                    $maxSize = 30 * 1024 * 1024; // 30MB
                    if (filesize($att['tmp_name']) > $maxSize) {
                        if ($socket) fclose($socket);
                        return "Attachment too large (max 30MB)";
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mType = finfo_file($finfo, $att['tmp_name']) ?: 'application/octet-stream';
                    finfo_close($finfo);

                    $content = chunk_split(base64_encode(file_get_contents($att['tmp_name'])));
                    $rawName = $stripCRLF($att['name']);
                    $urlEncodedName = rawurlencode($rawName);
                    $payload .= "--$boundary\r\n";
                    $payload .= "Content-Type: {$mType}; name=\"{$rawName}\"\r\n";
                    $payload .= "Content-Disposition: attachment; filename=\"{$rawName}\"; filename*=UTF-8''{$urlEncodedName}\r\n";
                    if (isset($att['cid'])) {
                        $payload .= "Content-ID: <{$att['cid']}>\r\n";
                    }
                    $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $payload .= $content . "\r\n";
                }
            }
            $payload .= "--$boundary--\r\n";
        } else {
            if ($isPGPMessage) {
                $boundary = "----=_PGP_Part_" . bin2hex(random_bytes(16));
                $headers .= "Content-Type: multipart/encrypted; protocol=\"application/pgp-encrypted\"; boundary=\"$boundary\"\r\n";
                $payload = "--$boundary\r\n";
                $payload .= "Content-Type: application/pgp-encrypted\r\n\r\n";
                $payload .= "Version: 1\r\n\r\n";
                $payload .= "--$boundary\r\n";
                $payload .= "Content-Type: application/octet-stream; name=\"encrypted.asc\"\r\n";
                $payload .= "Content-Description: OpenPGP encrypted message\r\n";
                $payload .= "Content-Disposition: inline; filename=\"encrypted.asc\"\r\n\r\n";
                $payload .= $body . "\r\n";
                $payload .= "--$boundary--\r\n";
            } else {
                $headers .= "Content-Type: $cType; charset=UTF-8\r\n";
                $headers .= "Content-Transfer-Encoding: $tEnc\r\n";
                $payload = $encBody;
            }
        }

        $rawMail = $headers . "\r\n" . $payload;

        // PROTOCOL FIX: Dot-stuffing (RFC 5321)
        // Lines starting with a dot must be prefixed with another dot to prevent premature truncation.
        $rawMail = preg_replace('/^\./m', '..', $rawMail);
        
        // STRICT RFC 5321 COMPLIANCE: Fix "I can break rules, too" Postfix error.
        // Normalizes any stray bare \n (LF) from the WYSIWYG editor into \r\n (CRLF).
        // Defeat Regex DoS. Use highly efficient core C functions instead of Regex on multi-megabyte payloads.
        $rawMail = str_replace("\r\n", "\n", $rawMail);
        $rawMail = str_replace("\n", "\r\n", $rawMail);
        
        if ($dryRunMimeOnly) {
            return ['status' => 'OK', 'raw' => $rawMail];
        }

        fputs($socket, $rawMail . "\r\n.\r\n");
        $dataRes = $readRes($socket);
        $writeRes($socket, "QUIT"); fclose($socket);

        if (substr($dataRes, 0, 3) !== '250') return "SMTP Send failed: $dataRes";
        
        return ['status' => 'OK', 'raw' => $rawMail];
    }

	
    // --- IMAP FOLDER DISCOVERY ---
    private function getTrashFolder($client) {
        try {
            $folders = $client->getFolders(false);
            foreach ($folders as $box) {
                $lowerName = strtolower($box->name);
                if (in_array($lowerName, ['trash', 'deleted', 'deleted items', 'deleted messages', 'bin', 'papelera', 'corbeille', 'papierkorb', 'prullenbak', 'inbox.trash', 'inbox/trash'])) {
                    return $box->path;
                }
            }
        } catch (\Throwable $e) {}
        return null;
    }
    
    private function getSentFolder($client) {
        $found = null;
        try {
            $folders = $client->getFolders(false);
            foreach ($folders as $box) {
                $lowerName = strtolower($box->name);
                if (in_array($lowerName, ['sent', 'sent items', 'sent messages', 'sent mail', '[gmail]/sent mail', 'inbox.sent', 'inbox/sent'])) {
                    return $box->path;
                }
                if (!$found && in_array($lowerName, ['gesendet', 'envoyés', 'enviados'])) {
                    $found = $box->path;
                }
            }
        } catch (\Throwable $e) {}
        return $found ?: 'Sent';
    }

    private function getDraftsFolder($client) {
        $found = null;
        try {
            $folders = $client->getFolders(false);
            foreach ($folders as $box) {
                $lowerName = strtolower($box->name);
                if (in_array($lowerName, ['drafts', 'draft', 'inbox.drafts', 'inbox/drafts'])) {
                    return $box->path;
                }
                if (!$found && in_array($lowerName, ['entwürfe', 'brouillons', 'borradores', 'bozaci'])) {
                    $found = $box->path;
                }
            }
        } catch (\Throwable $e) {}
        return $found ?: 'Drafts';
    }

    private function loadCacheData($filename) {
        if (array_key_exists($filename, $this->cache_file_cache)) {
            return $this->cache_file_cache[$filename];
        }

        if (!file_exists($filename)) {
            $this->cache_file_cache[$filename] = [];
            return [];
        }

        $raw = @file_get_contents($filename);
        if ($raw === false) {
            $this->cache_file_cache[$filename] = [];
            return [];
        }

        $parts = explode(':', $raw);
        // ZERO TRUST / PERFORMANCE FIX: Use Fast Cache (FC) mechanism.
        if (count($parts) === 5 && $parts[0] === 'FC') {
            $key = hash_hmac('sha256', base64_decode($parts[1]), $this->key, true);
            $decrypted = openssl_decrypt(base64_decode($parts[4]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[2]), base64_decode($parts[3]));
            if ($decrypted !== false) {
                $inflated = @gzinflate($decrypted, 52428800); // 50MB limit to prevent Zip Bomb
                if ($inflated !== false) {
                    $data = [];
                    foreach (explode("\n", trim($inflated)) as $line) {
                        if (empty($line)) continue;
                        $msg = json_decode($line, true);
                        if (isset($msg['id'])) $data[$msg['id']] = $msg;
                        elseif (isset($msg['__FOLDER_STATE__'])) $data['__FOLDER_STATE__'] = $msg['__FOLDER_STATE__'];
                    }
                    $this->cache_file_cache[$filename] = $data;
                    return $data;
                }
            }
        } else {
            // Automatically purge legacy slow-hashed cache files so they rebuild instantly
            @unlink($filename);
        }
        $this->cache_file_cache[$filename] = [];
        return [];
    }

    public function __destruct() {
        foreach ($this->dirty_caches as $filename => $data) {
            $this->writeCacheToDisk($filename, $data);
        }
        // Probabilistic Garbage Collection for orphaned body caches (runs ~2% of the time).
        // Automatically expires body caches older than 30 days and prevents disk exhaustion from orphaned files.
        if (rand(1, 100) <= 2) {
            $this->runGarbageCollection();
        }
    }

    private function runGarbageCollection() {
        if (!is_dir($this->body_cache_dir)) return;
        $files = glob($this->body_cache_dir . '/*.enc*');
        $now = time();
        $maxAge = 30 * 86400; // 30 days
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > $maxAge)) {
                @unlink($file);
            }
        }
    }

    private function saveCacheData($filename, $data) {
        if (array_key_exists($filename, $this->cache_file_cache) && $this->cache_file_cache[$filename] === $data && file_exists($filename)) {
            return;
        }
        $this->cache_file_cache[$filename] = $data;
        $this->dirty_caches[$filename] = $data;
    }
 
    private function writeCacheToDisk($filename, $data) {
        $lines = [];
        foreach ($data as $k => $v) {
            $encoded = json_encode($k === '__FOLDER_STATE__' ? ['__FOLDER_STATE__' => $v] : $v, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $lines[] = $encoded;
            }
        }
        $deflated = gzdeflate(implode("\n", $lines), 6);
        
        $salt = openssl_random_pseudo_bytes(16);
        // ZERO TRUST / PERFORMANCE: Use blazing-fast HMAC for ephemeral caches instead of 600k rounds
        $key = hash_hmac('sha256', $salt, $this->key, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-GCM'));
        $tag = '';
        $encrypted = openssl_encrypt($deflated, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        $payload = 'FC:' . base64_encode($salt) . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted);
        $tmpFile = $filename . '.' . bin2hex(random_bytes(16)) . '.tmp';
        file_put_contents($tmpFile, $payload);
        rename($tmpFile, $filename); // REFINEMENT 1: Atomic File Swapping
    }

    private function getBodyCachePath($accId, $folder, $msgId) {
        // Include Account ID and Folder in the filename so they can be securely globbed and wiped when a folder/account is deleted.
        $safeAcc = preg_replace('/[^a-zA-Z0-9_-]/', '_', $accId);
        $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
        return $this->body_cache_dir . '/' . $safeAcc . '_' . $safeFld . '_' . hash('sha256', $msgId) . '.enc';
    }

     private function loadBodyCacheData($filename) {
         if (array_key_exists($filename, $this->body_cache_file_cache)) {
             return $this->body_cache_file_cache[$filename];
         }

         if (!file_exists($filename)) {
             $this->body_cache_file_cache[$filename] = null;
             return null;
         }

         $raw = @file_get_contents($filename);
         if ($raw === false) {
             $this->body_cache_file_cache[$filename] = null;
             return null;
         }

         $parts = explode(':', $raw);
         if (count($parts) === 5 && $parts[0] === 'FCB') {
             $key = hash_hmac('sha256', base64_decode($parts[1]), $this->key, true);
             $decrypted = openssl_decrypt(base64_decode($parts[4]), 'AES-256-GCM', $key, OPENSSL_RAW_DATA, base64_decode($parts[2]), base64_decode($parts[3]));
             if ($decrypted !== false) {
                 $inflated = @gzinflate($decrypted, 52428800); // 50MB limit to prevent Zip Bomb
                 if ($inflated !== false) {
                     $data = json_decode($inflated, true);
                     $this->body_cache_file_cache[$filename] = $data;
                     return $data;
                 }
             }
         }
         @unlink($filename);
         $this->body_cache_file_cache[$filename] = null;
         return null;
     }

     private function saveBodyCacheData($filename, $data) {
         if (array_key_exists($filename, $this->body_cache_file_cache) && $this->body_cache_file_cache[$filename] === $data && file_exists($filename)) {
             return;
         }

         $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
         if ($encoded === false) return;
         $deflated = gzdeflate($encoded, 6);
         $salt = openssl_random_pseudo_bytes(16);
         $key = hash_hmac('sha256', $salt, $this->key, true);
         $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-GCM'));
         $tag = '';
		 $encrypted = openssl_encrypt($deflated, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
         $payload = 'FCB:' . base64_encode($salt) . ':' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted);
         $tmpFile = $filename . '.' . bin2hex(random_bytes(16)) . '.tmp';
         file_put_contents($tmpFile, $payload);
         rename($tmpFile, $filename);
         $this->body_cache_file_cache[$filename] = $data;
     }

    // --- CENTRALIZED HELPER: BACKGROUND UI RELEASE & SYNC ---
    private function releaseUiAndPrewarmCache($accId, $folderToSync) {
        // 1. Flush output buffer and close the HTTP connection to the frontend instantly
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        header('Connection: close');
        ob_start();
        echo json_encode(['status' => 'OK']);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush();
        @ob_flush();
        flush();
        
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else ignore_user_abort(true);

        if (empty($accId) || empty($folderToSync)) exit;

        // 2. Perform a silent background loopback to pre-warm the IMAP cache
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        $host = preg_replace('/[^a-zA-Z0-9.-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        $ch = curl_init("$scheme://$host$uri");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'email_get_messages', 'account_id' => $accId, 'folder' => $folderToSync, 'page' => 1, 'force_sync' => 1]));
        if (isset($_SERVER['HTTP_COOKIE'])) curl_setopt($ch, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    // --- TRANSACTIONAL OUTBOX PROCESSOR ---
   private function processOutboxQueue() {
        $outboxDir = dirname($this->config_file) . '/' . $this->username . '_outbox';
        if (!is_dir($outboxDir)) return;
        
        $hasPendingFutureJobs = true;
        $maxLoops = 35; // Maximum daemon lifetime of ~70 seconds to prevent zombie processes
        $loopCount = 0;

        while ($hasPendingFutureJobs && $loopCount < $maxLoops) {
            $hasPendingFutureJobs = false;
            $jobs = glob($outboxDir . '/*.job');
            
            foreach ($jobs as $job) {
                if (!is_file($job) || is_link($job)) continue;
                
                $jobDataRaw = @file_get_contents($job);
                if (!$jobDataRaw) continue;
                $jobData = json_decode($jobDataRaw, true);
                
                // If the undo timer hasn't expired, stay alive and wait for it
                if (!empty($jobData['execute_after']) && time() < $jobData['execute_after']) {
                    $hasPendingFutureJobs = true;
                    continue;
                }
                
                // SECURITY FIX: Use atomic rename instead of flock to prevent permanent deadlocks on script crash
                $processing = $job . '.processing';
                if (file_exists($processing)) continue;
                
                if (@rename($job, $processing)) {
                    // Strip the '.job.processing' (15 chars) to get the clean task ID base path for the status checker
                    $baseFile = substr($processing, 0, -15); 
                    
                    try {
                        $configs = $this->loadConfigs();
                        $acc = $configs[$jobData['account_id']] ?? null;
                        
                        if ($acc) {
                            $p = $jobData['payload'];
                            if ($jobData['action'] === 'email_send') {
                                $safeAttachments = [];
                                foreach (($p['attachments'] ?? []) as $att) {
                                    if (isset($att['tmp_name']) && isset($att['name'])) {
                                        $base = basename($att['tmp_name']);
                                        $safePath = $outboxDir . '/' . $base;
                                        if (file_exists($safePath) && realpath($safePath) === realpath($att['tmp_name'])) {
                                            $att['tmp_name'] = $safePath;
                                            $safeAttachments[] = $att;
                                        }
                                    }
                                }

                                $res = $this->sendSmtpMail($acc, $p['to'], $p['subject'], $p['body'], $p['from'], $p['cc'], $p['bcc'], $safeAttachments, false, !empty($p['read_receipt']));
                                if (is_array($res) && $res['status'] === 'OK') {
                                    list($client, $folderObj, $err) = $this->connectImap($acc, '', false);
                                    if ($client) {
                                        $rawMimeForAppend = $res['raw'];
                                        if (!empty($p['bcc'])) {
                                            $rawMimeForAppend = preg_replace('/(To: .*?\r\n|Cc: .*?\r\n)/i', "$1Bcc: " . $p['bcc'] . "\r\n", $rawMimeForAppend, 1);
                                            if (strpos($rawMimeForAppend, "Bcc:") === false) {
                                                $rawMimeForAppend = "Bcc: " . $p['bcc'] . "\r\n" . $rawMimeForAppend;
                                            }
                                        }
                                        try {
                                            $sentFld = $client->getFolderByPath($this->getSentFolder($client));
                                            if ($sentFld) $sentFld->appendMessage($rawMimeForAppend, ['\Seen']);
                                        } catch (\Throwable $e) {}
                                        $client->disconnect();
                                    }
                                    file_put_contents($baseFile . '.success', json_encode(['msg' => 'Sent successfully']));
                                } else {
                                    file_put_contents($baseFile . '.error', json_encode(['msg' => is_string($res) ? $res : 'Send failed']));
                                }
                                foreach ($safeAttachments as $att) { @unlink($att['tmp_name']); }

 
							} elseif ($jobData['action'] === 'email_delete_msg') {
                                list($client, $folderObj, $err) = $this->connectImap($acc, (string)($p['folder'] ?? ''), false);
                                if ($client && $folderObj) {
                                    $isTrash = preg_match('/trash|deleted|bin|papelera|corbeille/i', (string)($p['folder'] ?? ''));
                                    $trashFolder = $isTrash ? null : $this->getTrashFolder($client);
                                    
                                    foreach (explode(',', $p['message_id']) as $uid) {
                                        if (empty($uid)) continue;
                                        try {
                                            $msg = $folderObj->query()->getMessageByUid($uid);
                                            if ($msg) {
                                                if ($isTrash) { 
                                                    $msg->delete(); 
                                                } else {
                                                    if ($trashFolder) $msg->move($trashFolder);
                                                    else $msg->delete();
                                                }
                                            }
                                        } catch (\Throwable $e) {}
                                    }
                                    try { if (method_exists($folderObj, 'expunge')) $folderObj->expunge(); } catch (\Throwable $e) {}
                                    $client->disconnect();
                                }
                            } elseif ($jobData['action'] === 'email_move_msg' || $jobData['action'] === 'email_copy_msg') {
                                $isMove = ($jobData['action'] === 'email_move_msg');
                                if ($p['account_id'] === $p['dest_account_id']) {
                                    list($client, $folderObj, $err) = $this->connectImap($acc, $p['folder'], false);
                                    if ($client && $folderObj) {
                                        foreach (explode(',', $p['message_id']) as $uid) {
                                            if (empty($uid)) continue;
                                            try {
                                                $msg = $folderObj->query()->getMessageByUid($uid);
                                                if ($msg) {
                                                    if ($isMove) $msg->move($p['dest_folder']);
                                                    else $msg->copy($p['dest_folder']);
                                                }
                                            } catch (\Throwable $e) {}
                                        }
                                        try { if ($isMove && method_exists($folderObj, 'expunge')) $folderObj->expunge(); } catch (\Throwable $e) {}
                                        $client->disconnect();
                                    }
                                } else {
                                    list($srcClient, $srcFolderObj, $srcErr) = $this->connectImap($configs[$p['account_id']], $p['folder'], false);
                                    list($destClient, $destFolderObj, $destErr) = $this->connectImap($configs[$p['dest_account_id']], $p['dest_folder'], false);
                                    if ($srcClient && $destClient && $srcFolderObj && $destFolderObj) {
                                        foreach (explode(',', $p['message_id']) as $uid) {
                                            if (empty($uid)) continue;
                                            try {
                                                $msg = $srcFolderObj->query()->getMessageByUid($uid);
                                                if ($msg) {
                                                    $rawMsg = $msg->getHeader()->raw . "\r\n" . $msg->getRawBody();
                                                    $destFolderObj->appendMessage($rawMsg);
                                                    if ($isMove) $msg->delete();
                                                }
                                            } catch (\Throwable $e) {}
                                        }
                                        try { if ($isMove && method_exists($srcFolderObj, 'expunge')) $srcFolderObj->expunge(); } catch (\Throwable $e) {}
                                    }
                                    if ($srcClient) $srcClient->disconnect();
                                    if ($destClient) $destClient->disconnect();
                                }
                                
                                // Cross-folder dirty flags to ensure the UI updates correctly on next view
                                $safeDestFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['dest_folder']);
                                touch($this->cache_dir . "/{$p['dest_account_id']}_{$safeDestFld}.dirty");
                            }
                            @unlink($processing);
                        }
                    } catch (\Throwable $e) {
                        file_put_contents($baseFile . '.error', json_encode(['msg' => 'Fatal error: ' . $e->getMessage()]));
                        @unlink($processing);
                    }
                }
            }
            
            // Sleep for 2 seconds before checking the queue again
            if ($hasPendingFutureJobs) {
                sleep(2);
                $loopCount++;
            }
        }
    }
	
	// --- CENTRALIZED HELPER: BULLETPROOF TIMESTAMP EXTRACTION ---
    private function extractMessageTimestamp($msg) {
        $ts = 0;
        try {
            $dAttr = $msg->getDate();
            if (is_object($dAttr) && method_exists($dAttr, 'first')) {
                $carbon = $dAttr->first();
                $ts = $carbon instanceof \DateTimeInterface ? $carbon->getTimestamp() : strtotime((string)$carbon);
            } elseif ($dAttr instanceof \DateTimeInterface) {
                $ts = $dAttr->getTimestamp();
            } else {
                $ts = strtotime((string)$dAttr);
            }
        } catch (\Throwable $e) {}

        if (!$ts || $ts <= 0) {
            try {
                $attrs = $msg->getAttributes();
                if (!empty($attrs['internaldate'])) {
                    $iDate = is_array($attrs['internaldate']) ? reset($attrs['internaldate']) : $attrs['internaldate'];
                    $ts = $iDate instanceof \DateTimeInterface ? $iDate->getTimestamp() : strtotime((string)$iDate);
                }
            } catch (\Throwable $e) {}
        }

        if (!$ts || $ts <= 0) {
            try {
                $rawHeader = (string)$msg->getHeader()->raw;
                if (preg_match('/^Date:\s*([^\r\n]+)/mi', $rawHeader, $m)) {
                    $parsedTs = strtotime(trim($m[1]));
                    if ($parsedTs > 0) $ts = $parsedTs;
                }
            } catch (\Throwable $e) {}
        }

        return ($ts && $ts > 0) ? $ts : time();
    }

    // --- CENTRALIZED HELPER: ADDRESS EXTRACTION ---
    private function extractMessageAddresses($msg) {
        $format = function($addresses) {
            $arr = [];
            if (!empty($addresses) && is_iterable($addresses)) {
                foreach ($addresses as $a) {
                    if (!is_object($a)) continue; // Defensive check for malformed addresses
                    $personal = $a->personal ?? '';
                    $mail = $a->mail ?? '';
                    $arr[] = ($personal && strtolower($personal) !== strtolower($mail)) 
                        ? "{$personal} <{$mail}>" 
                        : $mail;
                }
            }
            return $arr;
        };

        // Suppress underlying webklex/php-imap warnings for malformed headers
        $fromArr = @$msg->getFrom();
        $fromObj = (!empty($fromArr) && is_object($fromArr[0])) ? $fromArr[0] : null;

        $toStr = implode(', ', $format(@$msg->getTo()));
        $ccStr = implode(', ', $format(@$msg->getCc()));
        $bccStr = implode(', ', $format(@$msg->getBcc()));

        if (empty($toStr) || empty($ccStr)) {
            try {
                $rawHeader = (string)@$msg->getHeader()->raw;
                if (empty($toStr) && preg_match('/^To:\s*([^\r\n]+)/mi', $rawHeader, $m)) {
                    $toStr = trim($this->decodeImapHeader($m[1]));
                }
                if (empty($ccStr) && preg_match('/^Cc:\s*([^\r\n]+)/mi', $rawHeader, $m)) {
                    $ccStr = trim($this->decodeImapHeader($m[1]));
                }
            } catch (\Throwable $e) {}
        }

        //  Correct MTA rewrites where BCC addresses are injected into the To field
        if (preg_match('/^bcc:\s*(.*)$/i', $toStr, $m)) {
            $extractedBcc = trim($m[1]);
            if ($extractedBcc === ';' || $extractedBcc === '') {
                $toStr = '';
            } else {
                if (empty($bccStr)) {
                    $bccStr = $extractedBcc;
                } else {
                    $merged = array_unique(array_merge(
                        array_map('trim', explode(',', $bccStr)), 
                        array_map('trim', explode(',', $extractedBcc))
                    ));
                    $bccStr = implode(', ', array_filter($merged));
                }
                $toStr = '';
            }
        } elseif (preg_match('/^undisclosed-recipients:\s*;?/i', $toStr)) {
            $toStr = '';
        }

        return [
            'from_name' => $fromObj ? ($fromObj->personal ?: ($fromObj->mail ?? 'Unknown')) : 'Unknown',
            'from_email' => $fromObj ? ($fromObj->mail ?? '') : '',
            'from_formatted' => $fromObj ? ($fromObj->personal ? "{$fromObj->personal} <{$fromObj->mail}>" : ($fromObj->mail ?? '')) : '',
            'to' => $toStr,
            'cc' => $ccStr,
            'bcc' => $bccStr,
            'reply_to' => implode(', ', $format(@$msg->getReplyTo()))
        ];
    }
	
    // --- CENTRALIZED HELPER: LOCAL CACHE PURGING ---
    private function purgeLocalCacheUids($accId, $folder, $uids) {
        $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
        if (!is_array($uids)) {
            $uids = array_filter(explode(',', preg_replace('/[^0-9,]/', '', $uids)));
        }
        
        $blocksChanged = [];
        foreach ($uids as $uid) {
            if (empty($uid)) continue;
            $blockId = floor($uid / 1000) * 1000;
            $blocksChanged[$blockId][] = $uid;
        }
        
        foreach ($blocksChanged as $blockId => $blockUids) {
            $cacheFile = $this->cache_dir . "/{$accId}_{$safeFld}_{$blockId}.json.enc";
            $cache = $this->loadCacheData($cacheFile);
            $changed = false;
            
            foreach ($blockUids as $uid) {
                if (isset($cache[$uid])) {
                    unset($cache[$uid]);
                    $changed = true;
                }
                @unlink($this->getBodyCachePath($accId, $folder, $uid));
            }
            
            if ($changed) {
                $this->saveCacheData($cacheFile, $cache);
            }
        }
    }

	public function handleRequest($action) {
        global $MYCLOUD_O365_CLIENT_ID, $MYCLOUD_O365_CLIENT_SECRET;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (isset($_POST['account_id'])) {
            $_POST['account_id'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['account_id']);
        }
        if (isset($_POST['folder'])) {
            // Allow only alphanumeric, space, dot, dash, underscore, and forward slash
            $_POST['folder'] = preg_replace('/[^a-zA-Z0-9.\-_\/ ]/', '', $_POST['folder']);
        }

        switch ($action) {
            // --- ALIAS MANAGER MODULE INTERCEPT ---
            case (preg_match('/^email_alias_/', $action) ? true : false):
                $aliasModulePath = __DIR__ . '/controller.server.email.alias_admin.php';
                if (file_exists($aliasModulePath) && $this->actionAllowed('mailbox_administration')) {
                    require_once $aliasModulePath;
                    $aliasServer = new MyCloudEmailAliasServer($this->key, $this->username);
                    $aliasServer->handleAliasRequest($action);
                }
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Alias manager module unavailable or access denied.']);
                break;


            // --- FIRE-AND-FORGET OUTBOX TRIGGER ---
            case 'email_process_outbox':
                session_write_close();
                ignore_user_abort(true);
                set_time_limit(120);
                $this->processOutboxQueue();
                $this->sendJsonAndExit(['status' => 'OK']);
                break;


            // --- ULTRA-FAST FPM-SAFE MAILBOX STATE CHECKER ---
            case 'email_quick_check':
                session_write_close(); 
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status' => 'ERR']);
                
                if (($configs[$accId]['server_type'] ?? 'imap') === 'eas') {
                    $this->sendJsonAndExit(['status' => 'EAS_SKIP']);
                }
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, true);
                if (!$client || !$folderObj) $this->sendJsonAndExit(['status' => 'ERR']);
               
                try {
                    $status = $folderObj->getStatus(['UIDNEXT', 'UNSEEN']);
                    
                    // CRITICAL FIX: Extract safely regardless of Webklex Object/Array union return types
                    $uidnext = is_object($status) ? ($status->uidnext ?? 0) : (is_array($status) ? ($status['uidnext'] ?? $status['UIDNEXT'] ?? 0) : 0);
                    $unseen = is_object($status) ? ($status->unseen ?? 0) : (is_array($status) ? ($status['unseen'] ?? $status['UNSEEN'] ?? 0) : 0);
                    
                    $hash = $uidnext . '-' . $unseen;
                    $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'OK', 'hash' => $hash]);
                } catch (\Throwable $e) {
                    $this->sendJsonAndExit(['status' => 'ERR']);
                }
                break;

			case 'email_get_accounts':
                $configs = $this->loadConfigs(); // Already filters out remote configs if restricted
                $safeConfigs = [];
                foreach ($configs as $id => $acc) { 
                    $acc['password'] = '********'; 
                    if (isset($acc['oauth_token'])) $acc['oauth_token'] = '********';
                    if (isset($acc['oauth_refresh_token'])) $acc['oauth_refresh_token'] = '********';
                    $safeConfigs[$id] = $acc; 
                }
                $this->sendJsonAndExit(['status' => 'OK', 'accounts' => $safeConfigs]);
                break;

            case 'email_test_connection':
                if (!$this->actionAllowed('email_settings')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                $accId = $_POST['account_id'] ?? '';
                $configs = $this->loadConfigs();
                $acc = $configs[$accId] ?? [];
                
                $acc['email'] = trim($_POST['email'] ?? '');
                $acc['login_user'] = trim($_POST['login_user'] ?? '');
                $acc['auth_type'] = trim($_POST['auth_type'] ?? 'basic');
                $acc['server_type'] = trim($_POST['server_type'] ?? 'imap');
                $acc['eas_host'] = trim($_POST['eas_host'] ?? '');
                $acc['imap_host'] = trim($_POST['imap_host'] ?? '');
                $acc['imap_port'] = trim($_POST['imap_port'] ?? '993');
                $acc['imap_enc']  = trim($_POST['imap_enc'] ?? 'ssl');
                
                if ($acc['auth_type'] === 'basic') {
                    $testPass = $_POST['password'] ?? '';
                    if ($testPass !== '********' && $testPass !== '') $acc['password'] = $this->encryptPassword($testPass);
                } else if ($acc['auth_type'] === 'oauth2') {
                    if (empty($acc['oauth_token'])) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'No OAuth token found. Please authorize with Microsoft first.']);
                    $this->refreshOauthTokenIfNeeded($acc, $accId);
                }

                if ($acc['server_type'] === 'eas') {
                    try {
                        $eas = new MyCloudEASClient($acc, $this->decryptPassword($acc['password'] ?? ''), $this->decryptPassword($acc['oauth_token'] ?? ''));
                        $folders = $eas->getFolders();
                        $this->sendJsonAndExit(['status' => 'OK', 'msg' => 'Connection successful! Found ' . count($folders) . ' folders.']);
                    } catch (Throwable $e) { $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Connection Error: ' . $e->getMessage()]); }
                } else {
                    list($client, $folderObj, $err) = $this->connectImap($acc, '', true);
                    if (!$client) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Connection Error: ' . $err]);
                    $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'OK', 'msg' => 'Connection successful!']);
                }
                break;

            case 'email_oauth_init':
                $redirectUri = $_POST['redirect_uri'] ?? '';
                global $cloud_oauth_my_domain;
                // ZERO TRUST: Never trust client-provided URIs for OAuth redirection
                $redirectUri = !empty($cloud_oauth_my_domain) ? $cloud_oauth_my_domain : rtrim(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . '://' . preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/');
                // NON ZERO TRUST:
                // if (!empty($cloud_oauth_my_domain)) $redirectUri = $cloud_oauth_my_domain;
                $accId = $_POST['account_id'] ?? '';
                $stateObj = ['myCloud_action' => 'oauth_callback', 'acc_id' => $accId, 'key' => $this->key, 'uri' => $redirectUri];
                $stateStr = base64_encode(json_encode($stateObj));
                $scope = rawurlencode('https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access');
                $url = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=" . $MYCLOUD_O365_CLIENT_ID . "&response_type=code&redirect_uri=".rawurlencode($redirectUri)."&response_mode=query&scope={$scope}&state={$stateStr}";
                $this->sendJsonAndExit(['status' => 'OK', 'url' => $url]);
                break;

            case 'email_oauth_callback':
                $code = $_POST['code'] ?? '';
                $accId = $_POST['account_id'] ?? '';
                $redirectUri = $_POST['redirect_uri'] ?? '';
                global $cloud_oauth_my_domain;
                if (!empty($cloud_oauth_my_domain)) $redirectUri = $cloud_oauth_my_domain;
                $configs = $this->loadConfigs(true);
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Account not found. Please save the account first before authorizing OAuth.']);
                
                $ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/token');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'client_id' => $MYCLOUD_O365_CLIENT_ID,
                    'client_secret' => $MYCLOUD_O365_CLIENT_SECRET,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access'
                ]));
                
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);
                
                if (!empty($res['access_token'])) {
                   $configs[$accId]['auth_type'] = 'oauth2';
                    $configs[$accId]['oauth_token'] = $this->encryptPassword($res['access_token']);
                    if (!empty($res['refresh_token'])) $configs[$accId]['oauth_refresh_token'] = $this->encryptPassword($res['refresh_token']);
                    $configs[$accId]['oauth_token_expires'] = time() + ($res['expires_in'] ?? 3600);
                    $this->saveConfigs($configs);
                    $this->sendJsonAndExit(['status' => 'OK']);
                } else {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'OAuth Error: ' . ($res['error_description'] ?? 'Unknown error')]);
                }
                break;

            case 'email_sync_password':
                $configs = $this->loadConfigs();
                $user = trim($_POST['login_user'] ?? '');
                $newPass = $_POST['new_password'] ?? '';
                if (empty($user) || empty($newPass)) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Missing data.']);
                
                $updated = false;
                foreach ($configs as $id => &$acc) {
                    $accLogin = $acc['login_user'] ?? '';
                    $accEmail = $acc['email'] ?? '';
                    if ($accLogin === $user || $accEmail === $user) {
                        $acc['password'] = $this->encryptPassword($newPass);
                        $updated = true;
                    }
                }
                unset($acc);
                
                if ($updated) $this->saveConfigs($configs);
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_save_account':
                if (!$this->actionAllowed('email_settings')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Account settings are locked.']);
                $configs = $this->loadConfigs();
                // Cryptographically secure uniqueness for resource identifiers
                $id = !empty($_POST['account_id']) ? trim($_POST['account_id']) : 'mail_' . bin2hex(random_bytes(12));
                if (!isset($configs[$id])) $configs[$id] = [];
                
                $configs[$id]['id'] = $id;
                // Eradicate CRLF characters at the point of storage to prevent permanent header injection
                $configs[$id]['name'] = preg_replace('/[\r\n\0]/', '', trim($_POST['name'] ?? ''));
                $configs[$id]['sender_name'] = preg_replace('/[\r\n\0]/', '', trim($_POST['sender_name'] ?? ''));
                $configs[$id]['email'] = preg_replace('/[\r\n\0]/', '', trim($_POST['email'] ?? ''));
                $configs[$id]['login_user'] = preg_replace('/[\r\n\0]/', '', trim($_POST['login_user'] ?? ''));
				$configs[$id]['auth_type'] = in_array(trim($_POST['auth_type'] ?? ''), ['basic', 'oauth2']) ? trim($_POST['auth_type']) : 'basic';
                $configs[$id]['signature'] = substr(trim($_POST['signature'] ?? ''), 0, 10240); // 10KB

                // PGP Keyring
                $configs[$id]['pgp_public_key'] = substr(trim($_POST['pgp_public_key'] ?? ''), 0, 16384); // 16KB
                $configs[$id]['pgp_private_key'] = substr(trim($_POST['pgp_private_key'] ?? ''), 0, 16384); // 16KB                


                $aliasesRaw = trim($_POST['aliases'] ?? '');
                $aliases = [];
                if (!empty($aliasesRaw)) {
                    $decoded = json_decode($aliasesRaw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $al) {
                            if (is_array($al) && !empty($al['email']) && filter_var(trim($al['email']), FILTER_VALIDATE_EMAIL)) {
                                $aliases[] = ['email' => trim($al['email']), 'sender_name' => trim($al['sender_name'] ?? ''), 'signature' => trim($al['signature'] ?? '')];
                            } elseif (is_string($al) && filter_var(trim($al), FILTER_VALIDATE_EMAIL)) {
                                $aliases[] = ['email' => trim($al)];
                            }
                        }
                    } else {
                        foreach (explode(',', $aliasesRaw) as $p) {
                            $p = trim($p);
                            if (filter_var($p, FILTER_VALIDATE_EMAIL)) $aliases[] = ['email' => $p];
                        }
                    }
                }
                $configs[$id]['aliases'] = $aliases;
                
                global $cloud_mail_only_localhost;
                if ($cloud_mail_only_localhost === true) {
                    $configs[$id]['server_type'] = 'imap';
                    $configs[$id]['imap_host'] = '127.0.0.1';
                    $configs[$id]['imap_port'] = '143';
                    $configs[$id]['imap_enc']  = 'none';
                    $configs[$id]['smtp_host'] = '127.0.0.1';
                    $configs[$id]['smtp_port'] = '25';
                    $configs[$id]['smtp_enc']  = 'none';
                } else {
                    $configs[$id]['server_type'] = trim($_POST['server_type'] ?? 'imap');
                    $easHost = trim($_POST['eas_host'] ?? '');
                    $imapHost = trim($_POST['imap_host'] ?? '');
                    $smtpHost = trim($_POST['smtp_host'] ?? '');

                    // ZERO TRUST: Prevent DNS lookups by forcing loopback IP
                    if (strtolower($easHost) === 'localhost') $easHost = '127.0.0.1';
                    if (strtolower($imapHost) === 'localhost') $imapHost = '127.0.0.1';
                    if (strtolower($smtpHost) === 'localhost') $smtpHost = '127.0.0.1';

                    $configs[$id]['eas_host'] = $easHost;
                    $configs[$id]['imap_host'] = $imapHost;
                    $configs[$id]['imap_port'] = trim($_POST['imap_port'] ?? '993');
                    $configs[$id]['imap_enc']  = trim($_POST['imap_enc'] ?? 'ssl');
                    $configs[$id]['smtp_host'] = $smtpHost;
                    $configs[$id]['smtp_port'] = trim($_POST['smtp_port'] ?? '465');
                    $configs[$id]['smtp_enc']  = trim($_POST['smtp_enc'] ?? 'ssl');
                }

                if (!empty($_POST['password']) && $_POST['password'] !== '********') { 
                    $configs[$id]['password'] = $this->encryptPassword($_POST['password']); 
                }

                // Auto-publish to Local WKD Directory if configured
                global $cloud_gpg_dir;
                $pubKey = $configs[$id]['pgp_public_key'] ?? '';
                if (!empty($pubKey) && !empty($cloud_gpg_dir)) {
                    if (!is_dir($cloud_gpg_dir)) {
                        @mkdir($cloud_gpg_dir, 0755, true);
                    }
                    if (is_dir($cloud_gpg_dir)) {
                        $wkdHash = $this->getWkdHash($configs[$id]['email']);
                        if ($wkdHash) {
                            file_put_contents(rtrim($cloud_gpg_dir, '/\\') . '/' . $wkdHash, $pubKey);
                        }
                        foreach ($configs[$id]['aliases'] as $al) {
                            $alWkdHash = $this->getWkdHash($al['email']);
                            if ($alWkdHash) file_put_contents(rtrim($cloud_gpg_dir, '/\\') . '/' . $alWkdHash, $pubKey);
                        }
                    }
                }

                $this->saveConfigs($configs);
                $isNew = empty($_POST['account_id']);
                $response = ['status' => 'OK', 'account_id' => $id, 'is_new' => $isNew];
                
                if ($isNew && $configs[$id]['auth_type'] === 'oauth2') {
                    global $cloud_oauth_my_domain;
                    $redirectUri = !empty($cloud_oauth_my_domain) ? $cloud_oauth_my_domain : (rtrim(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/'));
                    $stateObj = ['myCloud_action' => 'oauth_callback', 'acc_id' => $id, 'key' => $this->key, 'uri' => $redirectUri];
                    $stateStr = base64_encode(json_encode($stateObj));
                    $scope = rawurlencode('https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access');
                    $response['oauth_url'] = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=" . $MYCLOUD_O365_CLIENT_ID . "&response_type=code&redirect_uri=".rawurlencode($redirectUri)."&response_mode=query&scope={$scope}&state={$stateStr}";
                }
                $this->sendJsonAndExit($response);
                break;

            case 'email_delete_account':
                if (!$this->actionAllowed('email_settings')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Account settings are locked.']);
				$configs = $this->loadConfigs();
                $id = $_POST['account_id'] ?? '';
                if (isset($configs[$id])) { 
                    unset($configs[$id]); 
                    $this->saveConfigs($configs); 

                    // Completely eradicate all physical caches linked to the deleted account
                    $safeAcc = preg_replace('/[^a-zA-Z0-9_-]/', '_', $id);
                    foreach (glob($this->cache_dir . "/{$safeAcc}_*") as $file) {
                        @unlink($file);
                    }
                    foreach (glob($this->body_cache_dir . "/{$safeAcc}_*") as $file) {
                        @unlink($file);
                    }

                }
                $this->sendJsonAndExit(['status' => 'OK']);
                break;
				
            case 'email_toggle_account_active':
                if (!$this->actionAllowed('email_settings')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Account settings are locked.']);
                $configs = $this->loadConfigs();
                $id = $_POST['account_id'] ?? '';
                if (isset($configs[$id])) {
                    $configs[$id]['is_inactive'] = !empty($_POST['is_inactive']);
                    $this->saveConfigs($configs);
                }
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

			case 'email_reorder_accounts':
                if (!$this->actionAllowed('email_settings')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Account settings are locked.']);
                $configs = $this->loadConfigs();
                $newOrder = json_decode($_POST['order'] ?? '[]', true);
                // ZERO TRUST: Validate the structure is strictly a sequential list
                if (is_array($newOrder) && !empty($newOrder) && array_is_list($newOrder)) {
                    $reordered = [];
                    foreach ($newOrder as $id) {
                        $id = (string)$id;
						if (isset($configs[$id])) {
                            $reordered[$id] = $configs[$id];
                            unset($configs[$id]);
                        }
                    }
                    // Append any remaining (just in case)
                    foreach ($configs as $id => $acc) {
                        $reordered[$id] = $acc;
                    }
                    $this->saveConfigs($reordered);
                }
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

			case 'email_publish_keyserver':
                $pubKey = $_POST['pgp_public_key'] ?? '';
                $accId = $_POST['account_id'] ?? '';
                
                if (empty($pubKey)) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'No key provided.']);
                
                $ch = curl_init('https://keys.openpgp.org/vks/v1/upload');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['keytext' => $pubKey]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    $respData = json_decode($response, true);
                    if (is_array($respData) && !empty($respData['token'])) {
                        $addresses = [];
                        if (!empty($respData['status']) && is_array($respData['status'])) {
                            foreach ($respData['status'] as $email => $state) {
                                // ZERO TRUST: Validate that the keyserver is only verifying emails you actually own
                                $validEmails = array_map('strtolower', array_column($configs[$accId]['aliases'] ?? [], 'email'));
                                $validEmails[] = strtolower($configs[$accId]['email'] ?? '');
                                if ($state === 'unpublished' && in_array(strtolower($email), $validEmails)) {
                                    $addresses[] = $email;
                                }
                            }
                        }
                        
                        // SERVER-SIDE TRUST: Look up the email from the verified config
                        if (empty($addresses) && !empty($accId)) {
                            $configs = $this->loadConfigs();
                            if (!empty($configs[$accId]['email'])) {
                                $addresses[] = $configs[$accId]['email'];
                            }
                        }

                        if (!empty($addresses)) {
                            $chVerify = curl_init('https://keys.openpgp.org/vks/v1/request-verify');
                            curl_setopt($chVerify, CURLOPT_POST, true);
                            curl_setopt($chVerify, CURLOPT_POSTFIELDS, json_encode([
                                'token' => (string)$respData['token'],
                                'addresses' => array_values($addresses)
                            ]));
                            curl_setopt($chVerify, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Accept: application/json'
                            ]);
                            curl_setopt($chVerify, CURLOPT_RETURNTRANSFER, true);
                            curl_exec($chVerify);
                            curl_close($chVerify);
                        }
                    }
                    $this->sendJsonAndExit(['status'=>'OK']);
                } else {
                    // Try to extract the actual error message from the keyserver
                    $err = json_decode($response, true);
                    $msg = $err['error'] ?? 'Keyserver rejected the key. HTTP ' . $httpCode;
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg' => $msg]);
                }
                break;
				
			case 'email_publish_local_keyserver':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found. Please save the account first.']);

                global $cloud_gpg_dir;
                if (empty($cloud_gpg_dir)) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Local storage directory not configured in global settings.']);
                }
                if (!is_dir($cloud_gpg_dir)) {
                    @mkdir($cloud_gpg_dir, 0755, true);
                }

                $pubKey = trim($_POST['pgp_public_key'] ?? '');
                if (empty($pubKey)) $pubKey = $configs[$accId]['pgp_public_key'] ?? '';
                
                if (empty($pubKey)) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'No public key provided or saved in account.']);
                }

                $selectedEmails = json_decode($_POST['selected_emails'] ?? '[]', true);
                if (!is_array($selectedEmails) || empty($selectedEmails)) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'No email addresses selected.']);
                }

                // Validate against account configuration
                $validEmails = [strtolower(trim($configs[$accId]['email'] ?? ''))];
                if (!empty($configs[$accId]['aliases'])) {
                    foreach ($configs[$accId]['aliases'] as $al) {
                        $validEmails[] = strtolower(trim(is_array($al) ? ($al['email'] ?? '') : $al));
                    }
                }

                $published = 0;
                $processedEmails = []; // Track by full email to allow identical local-parts on different domains
                
                foreach ($selectedEmails as $se) {
                    $se = strtolower(trim($se));
                    if (in_array($se, $validEmails) && !isset($processedEmails[$se])) {
                        $wkdHash = $this->getWkdHash($se);
                        $domain = explode('@', $se)[1] ?? 'localhost';
                        $domainDir = rtrim($cloud_gpg_dir, '/\\') . '/' . preg_replace('/[^a-z0-9.-]/', '', $domain);
                        
                        if ($wkdHash) {
                            if (!is_dir($domainDir)) @mkdir($domainDir, 0755, true);
                            if (file_put_contents($domainDir . '/' . $wkdHash, $pubKey) !== false) {
                                $published++;
                                $processedEmails[$se] = true;
                            }
                        }
                    }
                }
                $this->sendJsonAndExit(['status'=>'OK', 'msg'=>"Saved $published key(s) to local storage."]);
                break;

            case 'email_unpublish_local_keyserver':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);

                global $cloud_gpg_dir;
                if (empty($cloud_gpg_dir) || !is_dir($cloud_gpg_dir)) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Local storage directory not configured.']);
                }

                $selectedEmails = json_decode($_POST['selected_emails'] ?? '[]', true);
                if (!is_array($selectedEmails) || empty($selectedEmails)) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'No email addresses selected.']);
                }

                // Validate against account configuration
                $validEmails = [strtolower(trim($configs[$accId]['email'] ?? ''))];
                if (!empty($configs[$accId]['aliases'])) {
                    foreach ($configs[$accId]['aliases'] as $al) {
                        $validEmails[] = strtolower(trim(is_array($al) ? ($al['email'] ?? '') : $al));
                    }
                }

                $deleted = 0;
                $processedEmails = []; // Track by full email
                
                foreach ($selectedEmails as $se) {
                    $se = strtolower(trim($se));
                    if (in_array($se, $validEmails) && !isset($processedEmails[$se])) {
                        $wkdHash = $this->getWkdHash($se);
                        $domain = explode('@', $se)[1] ?? 'localhost';
                        $domainDir = rtrim($cloud_gpg_dir, '/\\') . '/' . preg_replace('/[^a-z0-9.-]/', '', $domain);
                        
                        if ($wkdHash) {
                            $path = $domainDir . '/' . $wkdHash;
                            if (file_exists($path) && @unlink($path)) {
                                $deleted++;
                                $processedEmails[$se] = true;
                            }
                        }
                    }
                }

                $this->sendJsonAndExit(['status'=>'OK', 'msg'=>"Removed $deleted key(s) from local storage."]);
                break;

            case 'email_lookup_pubkey':
                $email = strtolower(trim($_POST['email'] ?? ''));
                if (!$email) $this->sendJsonAndExit(['status'=>'ERR']);
                
                $pubKey = null;
                $source = '';
                $is_binary = false;

                $hash = $this->getWkdHash($email);
                $domain = explode('@', $email)[1] ?? '';

                if (filter_var($domain, FILTER_VALIDATE_IP) || preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|169\.254\.|::1)/i', $domain)) {
                    $domain = ''; 
                }
				
                // Prevent DNS Rebinding SSRF by resolving the domain and re-verifying the IP before connecting
                if ($domain) {
                    $resolvedIp = gethostbyname($domain);
                    if (filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                        $domain = ''; // Abort if it resolves to an internal/private IP
                    }
                }

                // 1. Check Local WKD first (Zero HTTP overhead)
                global $cloud_gpg_dir;
                if (!empty($cloud_gpg_dir) && is_dir($cloud_gpg_dir)) {
                    $path = rtrim($cloud_gpg_dir, '/\\') . '/' . $hash;
                    if (file_exists($path)) {
                        $pubKey = file_get_contents($path);
                        $source = 'Local Server Directory';
                    }
                }

                // 2. Check User's Domain WKD (Direct HTTP Fetch bypasses CORS)
                if (!$pubKey && $domain) {
                    $wkdUrl = "https://{$domain}/.well-known/openpgpkey/hu/{$hash}";
                    $ch = curl_init($wkdUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
					curl_setopt($ch, CURLOPT_RESOLVE, ["{$domain}:443:{$resolvedIp}"]);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
					curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                    curl_setopt($ch, CURLOPT_MAXFILESIZE, 102400); // 100KB Max limit to prevent hanging
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
                    curl_close($ch);
                    
                    // Reject HTML/JSON (e.g., custom 404 pages returning 200 OK)
                    if ($httpCode === 200 && !empty($res) && strpos($contentType, 'html') === false && strpos($contentType, 'json') === false) {
                        $pubKey = $res;
                        $source = "WKD ($domain)";
                    }
                }

                // 3. Check global keys.openpgp.org as fallback
                if (!$pubKey) {
                    $ch = curl_init('https://keys.openpgp.org/vks/v1/by-email/' . urlencode($email));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
                    curl_setopt($ch, CURLOPT_MAXFILESIZE, 102400);
					curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
                    curl_close($ch);
                    
                    if ($httpCode === 200 && !empty($res) && strpos($contentType, 'html') === false && strpos($contentType, 'json') === false) {
                        $pubKey = $res;
                        $source = 'keys.openpgp.org';
                    }
                }

                if ($pubKey) {
                    // Check if it's an ASCII armored key or raw binary
                    $is_binary = (strpos($pubKey, '-----BEGIN PGP') === false);
                    // Crucial Fix: Binary data will crash json_encode. We MUST base64 encode it.
                    $payload = $is_binary ? base64_encode($pubKey) : $pubKey;
                    
                    $this->sendJsonAndExit([
                        'status' => 'OK', 
                        'pubkey' => $payload, 
                        'is_binary' => $is_binary, 
                        'source' => $source
                    ]);
                } else {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Key not found.']);
                }
                break;

            case 'email_get_folders':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                if (($configs[$accId]['server_type'] ?? 'imap') === 'eas') {
                    try {
                        $this->refreshOauthTokenIfNeeded($configs[$accId], $accId);
                        $eas = new MyCloudEASClient($configs[$accId], $this->decryptPassword($configs[$accId]['password'] ?? ''), $this->decryptPassword($configs[$accId]['oauth_token'] ?? ''));
                        $this->sendJsonAndExit(['status' => 'OK', 'folders' => $eas->getFolders()]);
                    } catch (\Throwable $e) { $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$e->getMessage()]); }
                }

                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], '', true);
                if (!$client) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err]);
                
                try {
                    $mailboxes = $client->getFolders(false);
                    $folders = [];
                    foreach ($mailboxes as $box) {
                        $unseen = 0;
                        try {
                            if (strtoupper($box->name) === 'INBOX' || strtoupper($box->path) === 'INBOX') {
                                try { $client->openFolder($box); } catch (\Throwable $e) {}
                                $rUnread = $client->getConnection()->search(['UNSEEN'], true);
                                $raw = is_object($rUnread) && method_exists($rUnread, 'validatedData') ? $rUnread->validatedData() : (isset($rUnread->data) ? (array)$rUnread->data : (is_array($rUnread) ? $rUnread : []));
                                if (is_iterable($raw)) {
                                    array_walk_recursive($raw, function($v) use (&$unseen) {
                                        if (is_numeric($v)) $unseen++;
                                        elseif (is_string($v)) foreach(explode(' ', $v) as $p) if (is_numeric($p)) $unseen++;
                                        elseif (is_object($v) && method_exists($v, 'getUid')) $unseen++;
                                    });
                                }
                            } else {
                                $status = $box->getStatus(['UNSEEN']);
                                $unseen = is_object($status) ? ($status->unseen ?? 0) : (is_array($status) ? ($status['unseen'] ?? $status['UNSEEN'] ?? 0) : 0);
                            }
                        } catch (\Throwable $e) {}
                        
                        $folders[] = [
                            'id' => $box->path, 
                            'name' => $box->name, 
                            'unread' => $unseen, 
                            'delimiter' => $box->delimiter
                        ];
                    }
                    $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'OK', 'folders' => $folders]);
                } catch (\Throwable $e) {
                    if ($client) $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to retrieve folders: ' . $e->getMessage()]);
                }
                break;
				
				
			case 'email_get_messages':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $page = max(1, (int)($_POST['page'] ?? 1));
                $perPage = 50;

                $searchQuery = trim($_POST['search_query'] ?? '');
                $searchScope = $_POST['search_scope'] ?? 'folder';
                $forceSync = !empty($_POST['force_sync']);
                $isStreamingSearch = ($searchQuery !== '');
                $clientStateHash = $_POST['folder_state_hash'] ?? '';
                $rebuildCache = !empty($_POST['rebuild_cache']);

                if ($rebuildCache) {
                    $forceSync = true;
                    $clientStateHash = '';
                }

                $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                $dirtyFile = $this->cache_dir . "/{$accId}_{$safeFld}.dirty";
                if (file_exists($dirtyFile)) {
                    $forceSync = true;
                    @unlink($dirtyFile);
                }

                if (($configs[$accId]['server_type'] ?? 'imap') === 'eas' && $accId !== 'smartbox') {
                    try {
                        $this->refreshOauthTokenIfNeeded($configs[$accId], $accId);
                        $eas = new MyCloudEASClient($configs[$accId], $this->decryptPassword($configs[$accId]['password'] ?? ''), $this->decryptPassword($configs[$accId]['oauth_token'] ?? ''));
                        $messages = $eas->getMessages($folder, $page, $perPage);
                        $this->sendJsonAndExit(['status' => 'OK', 'messages' => $messages, 'has_more' => count($messages) === $perPage, 'page' => $page, 'folder_state_hash' => uniqid()]);
                    } catch (\Throwable $e) { $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$e->getMessage()]); }
                }

                if (!$forceSync && !$isStreamingSearch) {
                    $messages = [];
                    $startIdx = ($page - 1) * $perPage;

                    if ($accId === 'smartbox') {
                        foreach ($configs as $id => $acc) {
                            if (!empty($acc['is_inactive'])) continue;

                                // Seamlessly inject cached EAS messages into the Smartbox view
                                if (($acc['server_type'] ?? 'imap') === 'eas') {
                                    $deviceId = strtoupper(md5('mycloud_eas_v3_' . ($acc['login_user'] ?: $acc['email']) . '_' . rtrim($acc['eas_host'] ?? '', '/')));
                                    $easFile = $this->cache_dir . '/eas_state_' . $deviceId . '.json';
                                    if (file_exists($easFile)) {
                                        $state = json_decode(file_get_contents($easFile), true);
                                        if ($state) {
                                            foreach ($state as $k => $v) {
                                                if (strpos($k, 'emails_') === 0 && is_array($v)) foreach ($v as $m) $messages[] = $m;
                                            }
                                        }
                                    }
                                    continue;
                                }

                            $files = glob($this->cache_dir . "/{$id}_*_[0-9]*.json.enc");
                            $validFolders = ['inbox', 'sent', 'sent_items', 'sent_messages', 'inbox_sent', 'gesendet', 'envoy_s', 'enviados'];
                            foreach ($files as $file) {
                                if (preg_match('/^' . preg_quote($id, '/') . '_(.*)_([0-9]+)\.json\.enc$/', basename($file), $m)) {
                                    $fld = strtolower($m[1]);
                                    if (in_array($fld, $validFolders)) {
                                        $cache = $this->loadCacheData($file);
                                        foreach ($cache as $uid => $msg) {
                                            if ($uid !== '__FOLDER_STATE__') {
                                                $messages[] = $msg;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        usort($messages, function($a, $b) { return $b['ts'] <=> $a['ts']; });
                        $pagedMessages = array_slice($messages, $startIdx, $perPage);
                        $has_more = ($startIdx + $perPage < count($messages));
                    } else {
                        $files = glob($this->cache_dir . "/{$accId}_{$safeFld}_*.json.enc");
                        $blocks = [];
                        foreach ($files as $file) {
                            if (preg_match('/_(\d+)\.json\.enc$/', $file, $m)) $blocks[$m[1]] = $file;
                        }
                        krsort($blocks, SORT_NUMERIC);

                        $collected = 0;
                        foreach ($blocks as $file) {
                            $cache = $this->loadCacheData($file);
                            $uids = array_keys($cache);
                            $uids = array_filter($uids, function($k) { return $k !== '__FOLDER_STATE__'; });
                            rsort($uids, SORT_NUMERIC);
                            foreach ($uids as $uid) {
                                $messages[] = $cache[$uid];
                                $collected++;
                            }
                            if ($collected >= $startIdx + $perPage + 1) break;
                        }
                        $pagedMessages = array_slice($messages, $startIdx, $perPage);
                        $has_more = ($startIdx + $perPage < count($messages));
                    }

                    $resp = ['status' => 'OK', 'messages' => $pagedMessages, 'has_more' => $has_more, 'page' => $page];
                    $this->sendJsonAndExit($resp);
                }

                $targetsByAccount = [];
                if ($accId === 'smartbox') {
                    foreach ($configs as $id => $acc) {
                        if (!empty($acc['is_inactive'])) continue;
                        if (($acc['server_type'] ?? 'imap') === 'eas') continue; // Prevent IMAP connections for EAS accounts
                        $targetsByAccount[$id] = ['INBOX', '__DISCOVER_SENT__'];
                    }
                } else {
                    if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                    if ($searchQuery !== '' && $searchScope === 'all') {
                        list($tmpClient, $tmpFolderObj, $tmpErr) = $this->connectImap($configs[$accId], '', true);
                        if ($tmpClient) {
                            try {
                                $mailboxes = $tmpClient->getFolders(false); 
                                if ($mailboxes) {
                                    foreach ($mailboxes as $box) {
                                        $targetsByAccount[$accId][] = $box->path;
                                    }
                                }
                            } catch (\Throwable $e) {}
                            $tmpClient->disconnect();
                        } else {
                            $targetsByAccount[$accId][] = $folder;
                        }
                    } else {
                        $targetsByAccount[$accId][] = $folder;
                    }
                }

                $messages = [];
                $has_more = false;
                $totalTargetCount = 0;
                foreach($targetsByAccount as $flds) $totalTargetCount += count($flds);
                $isMultiFolder = ($totalTargetCount > 1);
                $isOffline = false;
                
                $currentStateHash = '';
                
                if ($isStreamingSearch) {
                    while (ob_get_level() > 0) ob_end_clean();
                    header('Content-Type: application/x-ndjson');
                    header('Cache-Control: no-cache');
                }

                // BULLETPROOF PARSER: Recursively extracts UIDs regardless of Webklex formatting 
                $flattenUids = function($response) {
                    $flat = [];
                    $raw = [];
                    if (is_array($response)) {
                        $raw = $response;
                    } elseif (is_object($response)) {
                        if (method_exists($response, 'validatedData')) {
                            $raw = $response->validatedData();
                        } elseif (isset($response->data)) {
                            $raw = (array)$response->data;
                        } elseif (method_exists($response, 'get')) {
                            $raw = $response->get();
                        }
                    }
                    if (is_iterable($raw)) {
                        array_walk_recursive($raw, function($v) use (&$flat) {
                            if (is_numeric($v)) {
                                $flat[] = (int)$v;
                            } elseif (is_string($v)) {
                                foreach (explode(' ', $v) as $part) {
                                    if (is_numeric($part)) $flat[] = (int)$part;
                                }
                            } elseif (is_object($v) && method_exists($v, 'getUid')) {
                                $flat[] = (int)$v->getUid();
                            }
                        });
                    }
                    return array_unique($flat);
                };

                foreach ($targetsByAccount as $id => $folders) {
                    $acc = $configs[$id];
                    if (!empty($acc['is_inactive'])) continue;
                    list($client, $firstFolderObj, $err) = $this->connectImap($acc, $folders[0], true);
                    if (!$client) { $isOffline = true; continue; }
                    
                    $resolvedFolders = [];
                    foreach ($folders as $f) {
                        if ($f === '__DISCOVER_SENT__') {
                            $sf = $this->getSentFolder($client);
                            if ($sf) $resolvedFolders[] = $sf;
                        } else {
                            $resolvedFolders[] = $f;
                        }
                    }
                    $resolvedFolders = array_unique($resolvedFolders);

                    foreach ($resolvedFolders as $idx => $fld) {
                        if ($rebuildCache) {
                            $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fld);
                            foreach (glob($this->cache_dir . "/{$id}_{$safeFld}_*.json.enc") as $file) {
                                if (is_file($file)) {
                                    @unlink($file);
                                }
                            }
                            // Also purge the associated body caches to prevent orphaning during a rebuild
                            foreach (glob($this->body_cache_dir . "/{$id}_{$safeFld}_*.enc*") as $file) {
                                if (is_file($file)) {
                                    @unlink($file);
                                }
                            }
                        }
                        try {
                            $folderObj = $client->getFolderByPath($fld);
                            if (!$folderObj) continue;
                            
                            // CRITICAL FIX: Ensure folder is selected without triggering a TypeError
                            if (method_exists($folderObj, 'select')) {
                                $folderObj->select();
                            } else {
                                $folderObj->query()->all()->limit(1)->count(); // Implicit selection
                            }
                             // CRITICAL FIX: Eradicate "ghost" messages left \Deleted but unexpunged by external mail clients
                             if (method_exists($folderObj, 'expunge')) {
                                 $folderObj->expunge();
                             }
                        } catch (\Throwable $e) { continue; }

                        // 1. FETCH STATUS FIRST FOR FAILSAFES
                        $unreadUids = []; $flaggedUids = []; $currentStateHash = ''; $s_messages = -1;
                        $statusFailed = false;
                        try {
                            $status = $folderObj->getStatus(['UIDVALIDITY', 'UIDNEXT', 'MESSAGES', 'UNSEEN']);
                            $s_uidvalidity = is_object($status) ? ($status->uidvalidity ?? 0) : (is_array($status) ? ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? 0) : 0);
                            $s_uidnext = is_object($status) ? ($status->uidnext ?? 0) : (is_array($status) ? ($status['uidnext'] ?? $status['UIDNEXT'] ?? 0) : 0);
                            $s_messages = is_object($status) ? ($status->messages ?? -1) : (is_array($status) ? ($status['messages'] ?? $status['MESSAGES'] ?? -1) : -1);
                            $s_unseen = is_object($status) ? ($status->unseen ?? 0) : (is_array($status) ? ($status['unseen'] ?? $status['UNSEEN'] ?? 0) : 0);
                            
                            if ($s_uidvalidity == 0 && $s_messages <= 0) $statusFailed = true;

                            $currentStateHash = $s_uidvalidity . '-' . $s_uidnext . '-' . $s_messages . '-' . $s_unseen;

                            $rUnread = $client->getConnection()->search(['UNSEEN'], true);
                            $unreadUids = $flattenUids($rUnread);

                            $rFlagged = $client->getConnection()->search(['FLAGGED'], true);
                            $flaggedUids = $flattenUids($rFlagged);
                        } catch (\Throwable $e) { $statusFailed = true; }

                        // 2. MAIN UID FETCH
                        $uids = [];
                        $searchFailed = false;
                        if ($searchQuery !== '') {
                            $q = trim(str_replace(['"', '\\'], '', $searchQuery));
                            $imapQuery = [];
                            $sanitizeQuery = function($str) { return str_replace(['"', '\\'], '', $str); };

                            if (preg_match('/from:\s*(\S+)/i', $q, $m)) { array_push($imapQuery, 'FROM', $sanitizeQuery($m[1])); $q = str_replace($m[0], '', $q); }
                            if (preg_match('/to:\s*(\S+)/i', $q, $m)) { array_push($imapQuery, 'TO', $sanitizeQuery($m[1])); $q = str_replace($m[0], '', $q); }
                            if (preg_match('/subject:\s*(\S+)/i', $q, $m)) { array_push($imapQuery, 'SUBJECT', $sanitizeQuery($m[1])); $q = str_replace($m[0], '', $q); }
                            if (preg_match('/has:attachment/i', $q, $m)) { array_push($imapQuery, 'HEADER', 'Content-Type', 'multipart/mixed'); $q = str_replace($m[0], '', $q); }
                            if (preg_match('/is:unread/i', $q, $m)) { $imapQuery[] = 'UNSEEN'; $q = str_replace($m[0], '', $q); }
                            if (preg_match('/is:flagged/i', $q, $m)) { $imapQuery[] = 'FLAGGED'; $q = str_replace($m[0], '', $q); }

                            $q = trim($q);
                            if (!empty($q)) { array_push($imapQuery, 'TEXT', $q); }
                            if (empty($imapQuery)) $imapQuery = ['ALL'];

                            try {
                                $resp = $client->getConnection()->search($imapQuery, true);
                                $uids = $flattenUids($resp);
                            } catch (\Throwable $e) { $searchFailed = true; }
                        } else {
                            $isTrashFolder = preg_match('/trash|deleted|bin|papelera|corbeille/i', $fld);
                            try {
                                $resp = $client->getConnection()->search([$isTrashFolder ? 'ALL' : 'UNDELETED'], true);
                                $uids = $flattenUids($resp);
                            } catch (\Throwable $e) { $searchFailed = true; }
                        }
                        
                        $skipDeletion = false;
                        
                        // FAILSAFE: If the folder isn't completely empty, but UIDs failed to fetch, NEVER wipe the cache.
                        if (($searchFailed || $statusFailed || (empty($uids) && $s_messages > 0)) && $searchQuery === '') {
                            $skipDeletion = true; // Protect historical cache
                            $isOffline = true;
                            
                            // Immediately load UIDs from disk so the UI doesn't blank out
                            $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fld);
                            foreach (glob($this->cache_dir . "/{$id}_{$safeFld}_*.json.enc") as $file) {
                                $cData = $this->loadCacheData($file);
                                foreach (array_keys($cData) as $k) {
                                    if ($k !== '__FOLDER_STATE__') {
                                        $uids[] = (int)$k;
                                        if (isset($cData[$k]['is_read']) && !$cData[$k]['is_read']) {
                                            $unreadUids[] = (int)$k;
                                        }
                                    }
                                }
                            }
                        }

                        if ($uids) rsort($uids, SORT_NUMERIC);

                        $totalMatches = count($uids);
                        $startIdx = ($page - 1) * $perPage;
                        if ($isStreamingSearch) $uids = array_slice($uids, 0, 250);

                        if ($forceSync && !$isStreamingSearch) {
                            $pagedUids = array_slice($uids, 0, $startIdx + $perPage);
                        } else {
                            $pagedUids = $isStreamingSearch ? $uids : array_slice($uids, $startIdx, $perPage);
                        }

                        // ALWAYS load unread mails no matter when they were sent (Force inject on Page 1)
                        if (!empty($unreadUids) && is_array($unreadUids) && $page === 1) {
                            $pagedUids = array_values(array_unique(array_merge($pagedUids, $unreadUids)));
                            rsort($pagedUids, SORT_NUMERIC);
                        }

                        if (!$isStreamingSearch && $startIdx + $perPage < $totalMatches) $has_more = true;

                        $blocksToLoad = [];
                        foreach ($pagedUids as $uid) {
                            $blockId = floor($uid / 1000) * 1000;
                            $blocksToLoad[$blockId][] = $uid;
                        }

                        $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fld);
                        $pageMessages = [];

                        $allBlockIds = [];
                        $existingFiles = glob($this->cache_dir . "/{$id}_{$safeFld}_*.json.enc");
                        foreach ($existingFiles as $file) {
                            if (preg_match('/_(\d+)\.json\.enc$/', $file, $m)) $allBlockIds[$m[1]] = $file;
                        }
                        foreach ($blocksToLoad as $b => $v) $allBlockIds[$b] = true;
                        $allBlockIds = array_keys($allBlockIds);
                        rsort($allBlockIds, SORT_NUMERIC);

                        $globalCacheChanged = false;

                        if (!$isMultiFolder && $forceSync && !$isStreamingSearch && $clientStateHash === $currentStateHash) {
                            $allPresent = true;
                            foreach ($blocksToLoad as $blockId => $blockUids) {
                                $cacheFile = $this->cache_dir . "/{$id}_{$safeFld}_{$blockId}.json.enc";
                                $cache = $this->loadCacheData($cacheFile);
                                foreach ($blockUids as $uid) {
                                    if (!isset($cache[$uid])) { $allPresent = false; break 2; }
                                }
                            }
                            if ($allPresent) {
                                $this->sendJsonAndExit(['status' => 'NOT_MODIFIED']);
                            }
                        }

                        // --- BULLETPROOF MESSAGE EXTRACTION ---
                        $processMsg = function($msg) use (&$cache, &$pageMessages, &$cacheChanged, &$globalCacheChanged, $id, $acc, $fld, $unreadUids, $flaggedUids) {
                            $addrs = $this->extractMessageAddresses($msg);
                            $ts = $this->extractMessageTimestamp($msg);
                            $dateStr = (date('Y-m-d', $ts) === date('Y-m-d')) ? date('H:i', $ts) : date('d M Y H:i', $ts);

                            $subject = '(No Subject)';
                            try { 
                                $s = (string)$msg->getSubject();
                                if ($s !== '') $subject = strip_tags($s);
                            } catch (\Throwable $e) {}

                            $msgIdHdr = '';
                            try { $msgIdHdr = htmlspecialchars((string)$msg->getMessageId() ?: '', ENT_QUOTES, 'UTF-8'); } catch (\Throwable $e) {}

                            $inReplyTo = '';
                            try { $inReplyTo = htmlspecialchars((string)$msg->getInReplyTo() ?: '', ENT_QUOTES, 'UTF-8'); } catch (\Throwable $e) {}

                            $hasAttachments = false;
                            try { 
                                $hasAttachments = $msg->hasAttachments(); 
                            } catch (\Throwable $e) {
                                try {
                                    $rawHeader = (string)$msg->getHeader()->raw;
                                    $hasAttachments = (stripos($rawHeader, 'multipart/mixed') !== false || stripos($rawHeader, 'multipart/encrypted') !== false);
                                } catch (\Throwable $e2) {}
                            }

                            $parsedMsg = [
                                'id' => $msg->getUid(),
                                'account_id' => $id,
                                'account_name' => $acc['name'] ?: $acc['email'],
                                'folder' => $fld,
                                'ts' => $ts,
                                'subject' => $subject,
                                'message_id_hdr' => $msgIdHdr,
                                'in_reply_to' => $inReplyTo,
                                'fromName' => $addrs['from_name'],
                                'fromEmail' => $addrs['from_email'],
                                'to' => $addrs['to'],
                                'cc' => $addrs['cc'],
                                'bcc' => $addrs['bcc'],
                                'date' => $dateStr,
                                'is_read' => !in_array($msg->getUid(), $unreadUids),
                                'is_flagged' => in_array($msg->getUid(), $flaggedUids),
                                'has_attachments' => $hasAttachments
                            ];
                            $cache[$msg->getUid()] = $parsedMsg;
                            $pageMessages[$msg->getUid()] = $parsedMsg;
                            $cacheChanged = true;
                            $globalCacheChanged = true;
                        };

                        foreach ($allBlockIds as $blockId) {
                            $cacheFile = $this->cache_dir . "/{$id}_{$safeFld}_{$blockId}.json.enc";
                            $cache = $this->loadCacheData($cacheFile);
                            
                            $isUnchanged = isset($cache['__FOLDER_STATE__']) && $cache['__FOLDER_STATE__'] === $currentStateHash;
                            $blockUids = $blocksToLoad[$blockId] ?? [];
                            $cacheChanged = false;

                            if ($searchQuery === '' && !$skipDeletion) {
                                $cachedUids = array_filter(array_keys($cache), function($k) { return $k !== '__FOLDER_STATE__'; });
                                $deletedFromBlock = array_diff($cachedUids, $uids);
                                if (!empty($deletedFromBlock)) {
                                    foreach ($deletedFromBlock as $duid) {
                                        unset($cache[$duid]);
                                        $cacheChanged = true;
                                        $globalCacheChanged = true;
                                        @unlink($this->getBodyCachePath($id, $fld, $duid));
                                    }
                                }
                            }

                            if (!$isUnchanged) {
                                foreach ($cache as $cuid => $cdata) {
                                    if ($cuid === '__FOLDER_STATE__') continue;
                                    $isRead = !in_array($cuid, $unreadUids);
                                    $isFlagged = in_array($cuid, $flaggedUids);
                                    if (($cdata['is_read'] ?? true) !== $isRead || ($cdata['is_flagged'] ?? false) !== $isFlagged) {
                                        $cache[$cuid]['is_read'] = $isRead;
                                        $cache[$cuid]['is_flagged'] = $isFlagged;
                                        $cacheChanged = true;
                                        $globalCacheChanged = true;
                                    }
                                }
                            }

                            $missingUids = [];
                            $missingAttCheckUids = [];
                            foreach ($blockUids as $uid) {
                                if (!isset($cache[$uid])) {
                                    $missingUids[] = $uid;
                                } else {
                                    if (!isset($cache[$uid]['has_attachments']) || $cache[$uid]['has_attachments'] === null) {
                                        $missingAttCheckUids[] = $uid;
                                    }
                                    $pageMessages[$uid] = $cache[$uid];
                                }
                            }
                            
                            if (!empty($missingAttCheckUids)) {
                                $chunks = array_chunk($missingAttCheckUids, 50);
                                foreach ($chunks as $chunk) {
                                    try {
                                        $range = implode(',', $chunk);
                                        $attMsgs = $folderObj->query()->whereUid($range)->leaveUnread()->get();
                                        foreach ($attMsgs as $msg) {
                                            $mUid = $msg->getUid();
                                            if (isset($cache[$mUid])) {
                                                $hasAttachments = false;
                                                try { 
                                                    $hasAttachments = $msg->hasAttachments(); 
                                                } catch (\Throwable $e) {
                                                    try {
                                                        $rawHeader = (string)$msg->getHeader()->raw;
                                                        $hasAttachments = (stripos($rawHeader, 'multipart/mixed') !== false || stripos($rawHeader, 'multipart/encrypted') !== false);
                                                    } catch (\Throwable $e2) {}
                                                }
                                                $cache[$mUid]['has_attachments'] = $hasAttachments;
                                                $pageMessages[$mUid]['has_attachments'] = $hasAttachments;
                                                $cacheChanged = true;
                                                $globalCacheChanged = true;
                                            }
                                        }
                                    } catch (\Throwable $e) {}
                                }
                                
                                foreach ($missingAttCheckUids as $mUid) {
                                    if (isset($cache[$mUid]) && (!isset($cache[$mUid]['has_attachments']) || $cache[$mUid]['has_attachments'] === null)) {
                                        $cache[$mUid]['has_attachments'] = false;
                                        $pageMessages[$mUid]['has_attachments'] = false;
                                        $cacheChanged = true;
                                        $globalCacheChanged = true;
                                    }
                                }
                            }
                            

                            if (!empty($missingUids)) {
                                $chunks = array_chunk($missingUids, 50);
                                foreach ($chunks as $chunk) {
                                    try {
                                        $range = implode(',', $chunk);
                                        $overviewMsgs = $folderObj->query()->whereUid($range)->leaveUnread()->get();
                                        if ($overviewMsgs->count() === 0 && count($chunk) > 0) throw new \Exception("Fallback");
                                        foreach ($overviewMsgs as $msg) $processMsg($msg);
                                    } catch (\Throwable $e) {
                                        // Ultimate Safe Fallback Iterator
                                        foreach ($chunk as $uid) {
                                            try {
                                                $msg = $folderObj->query()->getMessageByUid($uid);
                                                if ($msg) $processMsg($msg);
                                            } catch (\Throwable $e2) {}
                                        }
                                    }
                                }
                            }

                            if ($cacheChanged || $isUnchanged === false) {
                                $cache['__FOLDER_STATE__'] = $currentStateHash;
                                if (count($cache) <= 1 && isset($cache['__FOLDER_STATE__'])) {
                                    @unlink($cacheFile);
                                } else {
                                    $this->saveCacheData($cacheFile, $cache);
                                }
                            }

                            if (!$isStreamingSearch && !empty($blockUids)) {
                                foreach ($blockUids as $uid) {
                                    if (isset($pageMessages[$uid])) $messages[] = $pageMessages[$uid];
                                }
                            } else if ($isStreamingSearch && !empty($blockUids)) {
                                foreach ($blockUids as $uid) {
                                    if (isset($pageMessages[$uid])) {
                                       echo json_encode($pageMessages[$uid], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n";
                                        @ob_flush(); flush();
                                    }
                                }
                            }
                        }
                    }
                    if ($client) $client->disconnect();
                }
                if ($isStreamingSearch) exit;

                if ($isMultiFolder || $searchQuery !== '') {
                    usort($messages, function($a, $b) { return $b['ts'] <=> $a['ts']; });
                }
                
                $this->sendJsonAndExit(['status' => 'OK', 'messages' => $messages, 'has_more' => $has_more, 'page' => $page, 'offline_mode' => $isOffline, 'folder_state_hash' => $currentStateHash]);
                break;


            case 'email_get_body':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                $bodyPath = $this->getBodyCachePath($accId, $folder, $msgId);
                $cachedBody = $this->loadBodyCacheData($bodyPath);

                // ==========================================
                // SQUARE ONE CACHE FIX: Delete corrupted empty cache
                // ==========================================
                if ($cachedBody !== null && trim($cachedBody['body'] ?? '') === '') {
                    $cachedBody = null;
                    @unlink($bodyPath);
                }

                if (($configs[$accId]['server_type'] ?? 'imap') === 'eas') {
                    try {
                         if ($cachedBody !== null) {
                             $this->sendJsonAndExit(array_merge(['status' => 'OK', 'raw_message' => ''], $cachedBody));
                         }
                        $this->refreshOauthTokenIfNeeded($configs[$accId], $accId);
                        $eas = new MyCloudEASClient($configs[$accId], $this->decryptPassword($configs[$accId]['password'] ?? ''), $this->decryptPassword($configs[$accId]['oauth_token'] ?? ''));
                        $bodyData = $eas->getMessageBody($folder, $msgId);
                        $this->saveBodyCacheData($bodyPath, $bodyData);
                        $this->sendJsonAndExit(array_merge(['status' => 'OK', 'raw_message' => ''], $bodyData));
                    } catch (Throwable $e) { $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$e->getMessage()]); }
                }

                if ($cachedBody !== null) {
                    $this->sendJsonAndExit(array_merge(['status' => 'OK'], $cachedBody));
                }

                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, true);
                if (!$client || !$folderObj) {
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err ?: 'Failed to open folder.']);
                }
                
                try {
                    $msg = $folderObj->query()->getMessageByUid($msgId);
                    if (!$msg) {
                        $client->disconnect();
                        $this->purgeLocalCacheUids($accId, $folder, [$msgId]);
                        $this->sendJsonAndExit(['status'=>'ERR', 'code'=>'MSG_NOT_FOUND', 'msg'=>"Message UID [$msgId] not found. It may have been moved or deleted externally."]);
                    }
                    
                    $rawHeader = $msg->getHeader()->raw;
                    $rawBody = $msg->getRawBody();
                    $rawMsg = $rawHeader . "\r\n" . $rawBody;
                    
                    if ($msg->hasHTMLBody()) {
                        $htmlContent = $msg->getHTMLBody();
                    } else {
                        $plainText = htmlspecialchars((string)$msg->getTextBody() ?: '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        
                        // Safely parse URLs and emails into clickable links for plain text emails.
                        // Because this runs AFTER htmlspecialchars, it is immune to attribute breakout XSS.
                        // The resulting HTML tags will natively pass through the frontend DOMPurify and inherit the exact same "ask before open" warning logic.
                        $plainText = preg_replace_callback('/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]\'"&])|([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', function($m) {
                            if (!empty($m[1])) return '<a href="' . $m[1] . '">' . $m[1] . '</a>';
                            if (!empty($m[2])) return '<a href="mailto:' . $m[2] . '">' . $m[2] . '</a>';
                            return $m[0];
                        }, $plainText);
                        
                        $htmlContent = nl2br($plainText);
                    }
                    
                    // --- FALLBACK PGP/MIME DETECTION ---
                    if (stripos($rawHeader, 'multipart/encrypted') !== false || stripos($msg->getContentType() ?? '', 'multipart/encrypted') !== false) {
                        if (strpos($htmlContent, '-----BEGIN PGP MESSAGE-----') === false && preg_match('/-----BEGIN PGP MESSAGE-----.*?-----END PGP MESSAGE-----/s', $rawBody, $m)) {
                            $htmlContent = "<pre>\n" . trim($m[0]) . "\n</pre>";
	                    }
                    }

                    $trustScore = 'unknown';
                    if (preg_match('/Authentication-Results:.*?bimi=pass/si', $rawHeader)) {
                        $trustScore = 'bimi';
                    } elseif (preg_match('/Authentication-Results:.*?dmarc=pass/si', $rawHeader) && preg_match('/Authentication-Results:.*?spf=pass/si', $rawHeader)) {
                        $trustScore = 'perfect';
                    } elseif (preg_match('/Authentication-Results:.*?(dmarc=pass|spf=pass|dkim=pass)/si', $rawHeader)) {
                        $trustScore = 'good';
                    } elseif (preg_match('/Authentication-Results:.*?(dmarc=fail|spf=fail|dkim=fail)/si', $rawHeader)) {
                        $trustScore = 'fail';
                    }

                     $spamScore = null;
                     $isPhishing = false;
                     
                     if (preg_match('/X-Spam-Score:\s*([-0-9.]+)/i', $rawHeader, $m)) $spamScore = (float)$m[1];
                     elseif (preg_match('/X-Rspamd-Score:\s*([-0-9.]+)/i', $rawHeader, $m)) $spamScore = (float)$m[1];
                     
                     if (preg_match('/X-Spam-Report:.*?(PHISH|FRAUD|SPOOF|DECEPTIVE)/si', $rawHeader)) $isPhishing = true;
                     elseif (preg_match('/X-Rspamd-Report:.*?(PHISH|FRAUD|SPOOF|DECEPTIVE)/si', $rawHeader)) $isPhishing = true;

                     $rawHeaderClean = str_replace(["\r\n", "\r"], "\n", $rawHeader);
                     $transportSec = 'internal';
                     $isDane = false;
                     $hasUnencryptedHop = false;
                     $hasExternalEncryptedHop = false;

                     // Verify the entire chain by evaluating every Received header independently
                     if (preg_match_all('/^Received:\s*(.*?)(?=\n[A-Z0-9a-z\-]+:|\n\n|$)/ms', $rawHeaderClean, $receivedMatches)) {
                         foreach ($receivedMatches[0] as $recvHeader) {
                             if (preg_match('/Received:\s*from\s+(localhost|\[?127\.0\.0\.1\]?|\[?::1\]?)\b/i', $recvHeader)) continue;
                             $isEncryptedHop = preg_match('/with\s+(ESMTPS|ESMTPSA|SMTPS|SMTPSA|ESMTPS-TLS)\b/i', $recvHeader) || preg_match('/\(.*?TLS.*?\)/i', $recvHeader);

                             if ($isEncryptedHop) {
                                 $hasExternalEncryptedHop = true;
                                 if (preg_match('/verified DANE/i', $recvHeader)) $isDane = true;
                             } elseif (preg_match('/with\s+(SMTP|Microsoft SMTP)\b/i', $recvHeader)) {
                                 $hasUnencryptedHop = true;
                             }
                         }
                     }

                     if ($hasUnencryptedHop) {
                         $transportSec = 'none';
                     } elseif ($hasExternalEncryptedHop) {
                         $transportSec = $isDane ? 'dane' : 'tls';
                     }

                    $addrs = $this->extractMessageAddresses($msg);
                    
                    $attachmentsData = [];
                    $temp = $this->user_temp_dir;
                    $extractDraftAtts = (!empty($_POST['extract_draft_attachments']) && $_POST['extract_draft_attachments'] === '1');
                    
                    $msgAttachments = [];
                    try {
                        $msgAttachments = $msg->getAttachments();
                    } catch (\Throwable $e) {}

                    foreach ($msgAttachments as $att) {
                        try {
                            $attContent = $att->getContent() ?? '';
                            
                            // --- AGGRESSIVE PGP/MIME ATTACHMENT EXTRACTION ---
                            if (strpos($attContent, '-----BEGIN PGP MESSAGE-----') !== false) {
                                // Elevate the payload back into the body so the frontend decrypter sees it
                                $htmlContent = "<pre>\n" . htmlspecialchars(trim($attContent), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n</pre>";
                                continue; // Hide from downloadable attachments
                            }
                            if (trim($attContent) === 'Version: 1' || stripos($att->content_type ?? '', 'application/pgp-encrypted') !== false) {
                                continue; // Hide the protocol metadata attachment
                            }

                            // ZERO TRUST: Safely embed bounce reports and nested RFC822 messages directly into the body.
                            // The library often assigns dummy names to missing filenames. We rely strictly on the validated MIME type.
                            $mimeType = '';
                            if (method_exists($att, 'getMimeType')) $mimeType = $att->getMimeType();
                            if (empty($mimeType)) $mimeType = $att->content_type ?? $att->mime ?? '';
                            $mimeType = strtolower(trim(explode(';', (string)$mimeType)[0]));

                            if (strpos($mimeType, 'message/') === 0 || $mimeType === 'text/rfc822') {
                                $originalName = $att->name ?? '';
                                $dispName = empty($originalName) || $originalName === 'unknown' ? 'Attached Message / Delivery Report' : $originalName;
                                $textContent = $attContent;
                                if (stripos($textContent, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                                    $textContent = quoted_printable_decode($textContent);
                                }
                                
                                $bounceText = htmlspecialchars(trim($textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                
                                $htmlContent .= "<br><br><br><br><div class=\"ce-bounce-report\" dir=\"auto\" style=\"border: 1px solid var(--border-medium); border-radius: 6px; background: var(--gray-05); padding: 15px; margin-block-start: 50px; clear: both;\">";
                                $htmlContent .= "<div style=\"font-weight: bold; font-family: sans-serif; font-size: 13px; color: var(--text-primary); margin-block-end: 10px; padding-block-end: 8px; border-block-end: 1px solid var(--border-default); display: flex; align-items: center; gap: 6px;\">📄 " . htmlspecialchars($dispName, ENT_QUOTES, 'UTF-8') . "</div>";
                                $htmlContent .= "<pre style=\"white-space:pre-wrap; font-family:monospace; font-size:12px; color:var(--text-secondary); word-break: break-all; margin: 0;\">" . $bounceText . "</pre></div>";
                                continue;
                            }

                            $attId = $att->id ?? $att->part_number ?? uniqid();
                            $safeName = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '_', $att->name ?? '');
                            if (empty($safeName)) $safeName = 'attachment_' . uniqid();

                            $attItem = [
                                'part' => $attId,
                                'filename' => $safeName,
                                'size' => $att->size ?? 0,
                                'cid' => str_replace(['<', '>'], '', $att->content_id ?? '')
                            ];
                            
                            if ($extractDraftAtts) {
                                $dest = $temp . '/myCloud_eml_att_' . bin2hex(random_bytes(8)) . '_' . $safeName;
                                if ($attContent !== null) {
                                    file_put_contents($dest, $attContent);
                                    $attItem['tmp_name'] = $dest;
                                    $attItem['is_inline'] = !empty($attItem['cid']);
                                }
                            }
                            $attachmentsData[] = $attItem;
                        } catch (\Throwable $e) { continue; }
                    }

                    $client->disconnect();

                    // ==========================================
                    // SQUARE ONE EXTRACTION
                    // ==========================================
                    if (class_exists('HTMLPurifier')) {
                        $config = \HTMLPurifier_Config::createDefault();
                        $config->set('HTML.TargetBlank', true);
                        $config->set('URI.DisableExternalResources', false); 
                        $config->set('CSS.AllowTricky', true);

                        $purifierCache = $this->cache_dir . '/htmlpurifier';
                        if (!is_dir($purifierCache)) @mkdir($purifierCache, 0770, true);
                        $config->set('Cache.SerializerPath', $purifierCache);

                        $config->set('HTML.TidyLevel', 'none');
                        $config->set('Core.EscapeInvalidTags', false);

                        $purifier = new \HTMLPurifier($config);
                        $cleanHtml = $purifier->purify($htmlContent);
                        
                        if (trim($cleanHtml) === '' && trim((string)$htmlContent) !== '') {
                            $cleanHtml = '<pre style="white-space:pre-wrap; font-family:inherit;">' . htmlspecialchars((string)$htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</pre>';
                        }
                    } else {
                        $cleanHtml = '<pre style="white-space:pre-wrap; font-family:inherit;">' . htmlspecialchars((string)$htmlContent, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</pre>';
                    }

                    $listUnsubscribe = '';
                    if (preg_match('/(?:^|[\r\n])List-Unsubscribe:\s*(.*?)(?=[\r\n][A-Za-z0-9\-]+:|$)/is', $rawHeader, $m)) {
                        $flat = preg_replace('/[\r\n\t]+/', ' ', $m[1]);
                        $flat = preg_replace('/\?=\s+=\?/', '?==?', $flat);
                        $listUnsubscribe = preg_replace('/[\x00-\x1F\x7F]/', '', trim($this->decodeImapHeader($flat)));
                        if (strpos($listUnsubscribe, '=?') !== false && function_exists('mb_decode_mimeheader')) {
                            $listUnsubscribe = mb_decode_mimeheader($listUnsubscribe);
                        }
                    }
                    
                    $bodyData = [
                        'body' => $cleanHtml, 
                        'attachments' => $attachmentsData, 
                        'raw_message' => $rawMsg,
                        'unsubscribe' => $listUnsubscribe,
                        'to' => $addrs['to'],
                        'cc' => $addrs['cc'],
                        'bcc' => $addrs['bcc'],
                        'reply_to' => $addrs['reply_to'],
                        'trust_score' => $trustScore,
                        'transport_sec' => $transportSec,
                        'spam_score' => $spamScore,
                        'is_phishing' => $isPhishing
                    ];

                    $this->saveBodyCacheData($bodyPath, $bodyData);
                    $this->sendJsonAndExit(array_merge(['status' => 'OK'], $bodyData));

                } catch (\Throwable $e) {
                    if ($client) $client->disconnect();
                    $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Failed to fetch body: ' . $e->getMessage()]);
                }
                break;
				
            case 'email_mark_read':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if ($client && $folderObj) {
                    $ids = explode(',', $msgId);
                    foreach ($ids as $uid) {
                        if (empty($uid)) continue;
                        try {
                            $msg = $folderObj->query()->getMessageByUid($uid);
                            if ($msg) $msg->setFlag('Seen');
                        } catch (\Throwable $e) {}
                    }
                    $client->disconnect();
                }
                
                $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                $ids = explode(',', $msgId);
                foreach ($ids as $uid) {
                    if (empty($uid)) continue;
                    $blockId = floor($uid / 1000) * 1000;
                    $cacheFile = $this->cache_dir . "/{$accId}_{$safeFld}_{$blockId}.json.enc";
                    $cache = $this->loadCacheData($cacheFile);
                    if (isset($cache[$uid])) {
                        $cache[$uid]['is_read'] = true;
                        $this->saveCacheData($cacheFile, $cache);
                    }
                }

                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_mark_unread':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if ($client && $folderObj) {
                    $ids = explode(',', $msgId);
                    foreach ($ids as $uid) {
                        if (empty($uid)) continue;
                        try {
                            $msg = $folderObj->query()->getMessageByUid($uid);
                            if ($msg) $msg->unsetFlag('Seen');
                        } catch (\Throwable $e) {}
                    }
                    $client->disconnect();
                }
                
                $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                $ids = explode(',', $msgId);
                foreach ($ids as $uid) {
                    if (empty($uid)) continue;
                    $blockId = floor($uid / 1000) * 1000;
                    $cacheFile = $this->cache_dir . "/{$accId}_{$safeFld}_{$blockId}.json.enc";
                    $cache = $this->loadCacheData($cacheFile);
                    if (isset($cache[$uid])) {
                        $cache[$uid]['is_read'] = false;
                        $this->saveCacheData($cacheFile, $cache);
                    }
                }

                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_mark_flagged':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if ($client && $folderObj) {
                    $ids = explode(',', $msgId);
                    foreach ($ids as $uid) {
                        if (empty($uid)) continue;
                        try {
                            $msg = $folderObj->query()->getMessageByUid($uid);
                            if ($msg) $msg->setFlag('Flagged');
                        } catch (\Throwable $e) {}
                    }
                    $client->disconnect();
                }

                $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                $ids = explode(',', $msgId);
                foreach ($ids as $uid) {
                    if (empty($uid)) continue;
                    $blockId = floor($uid / 1000) * 1000;
                    $cacheFile = $this->cache_dir . "/{$accId}_{$safeFld}_{$blockId}.json.enc";
                    $cache = $this->loadCacheData($cacheFile);
                    if (isset($cache[$uid])) {
                        $cache[$uid]['is_flagged'] = true;
                        $this->saveCacheData($cacheFile, $cache);
                    }
                }

                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_unmark_flagged':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if ($client && $folderObj) {
                    $ids = explode(',', $msgId);
                    foreach ($ids as $uid) {
                        if (empty($uid)) continue;
                        try {
                            $msg = $folderObj->query()->getMessageByUid($uid);
                            if ($msg) $msg->unsetFlag('Flagged');
                        } catch (\Throwable $e) {}
                    }
                    $client->disconnect();
                }

                $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                $ids = explode(',', $msgId);
                foreach ($ids as $uid) {
                    if (empty($uid)) continue;
                    $blockId = floor($uid / 1000) * 1000;
                    $cacheFile = $this->cache_dir . "/{$accId}_{$safeFld}_{$blockId}.json.enc";
                    $cache = $this->loadCacheData($cacheFile);
                    if (isset($cache[$uid])) {
                        $cache[$uid]['is_flagged'] = false;
                        $this->saveCacheData($cacheFile, $cache);
                    }
                }

                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_create_folder':
                if (!$this->actionAllowed('email_newfolder')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = str_replace(["\r", "\n", "*", "%"], '', $_POST['folder'] ?? '');
                $name = str_replace(["\r", "\n"], '', $_POST['name'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], '', false);
                if (!$client) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err]);
                
                try {
                    $delimiter = '.';
                    if ($folder && $folder !== '__ROOT__') {
                        $parentFolder = $client->getFolderByPath($folder);
                        if ($parentFolder) $delimiter = $parentFolder->delimiter ?: $delimiter;
                        $newPath = $folder . $delimiter . $name;
                    } else {
                        $folders = $client->getFolders(false);
                        if ($folders->count() > 0) $delimiter = $folders->first()->delimiter ?: $delimiter;
                        $newPath = $name;
                    }
                    
                    $client->createFolder($newPath);
                    $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'OK']);
                } catch (\Exception $e) {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to create folder: ' . $e->getMessage()]);
                }
                break;

            case 'email_rename_folder':
                if (!$this->actionAllowed('email_rename')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = str_replace(["\r", "\n", "*", "%"], '', $_POST['folder'] ?? '');
                $name = str_replace(["\r", "\n"], '', $_POST['name'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if (!$client || !$folderObj) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err ?: 'Folder not found']);
                
                try {
                    $delimiter = $folderObj->delimiter ?: '.';
                    $parts = explode($delimiter, $folder);
                    array_pop($parts);
                    if (count($parts) > 0) {
                        $newPath = implode($delimiter, $parts) . $delimiter . $name;
                    } else {
                        $newPath = $name;
                    }
                    
                    $client->getConnection()->rename($folder, mb_convert_encoding($newPath, "UTF7-IMAP", "UTF-8"));
                    $client->disconnect();
                    
                    // CACHE PURGE
                    $safeOld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                    $safeNew = preg_replace('/[^a-zA-Z0-9_-]/', '_', $newPath);
                    foreach (glob($this->cache_dir . "/{$accId}_{$safeOld}_*.json.enc*") as $file) {
                        @rename($file, str_replace("_{$safeOld}_", "_{$safeNew}_", $file));
                    }
                    foreach (glob($this->body_cache_dir . "/{$accId}_{$safeOld}_*.enc*") as $file) {
                        @rename($file, str_replace("_{$safeOld}_", "_{$safeNew}_", $file));
                    }
                    $this->sendJsonAndExit(['status' => 'OK']);
                } catch (\Exception $e) {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to rename folder: ' . $e->getMessage()]);
                }
                break;

            case 'email_delete_folder':
                if (!$this->actionAllowed('email_delete')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = str_replace(["\r", "\n", "*", "%"], '', $_POST['folder'] ?? '');
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if (!$client || !$folderObj) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err ?: 'Folder not found']);
                
                try {
                    $folderObj->delete();
                    $client->disconnect();
                    
                    // CACHE PURGE
                    $safeFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                    foreach (glob($this->cache_dir . "/{$accId}_{$safeFld}_*.json.enc*") as $file) {
                        @unlink($file);
                    }
                    foreach (glob($this->body_cache_dir . "/{$accId}_{$safeFld}_*.enc*") as $file) {
                        @unlink($file);
                    }
                    $this->sendJsonAndExit(['status' => 'OK']);
                } catch (\Exception $e) {
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to delete folder: ' . $e->getMessage()]);
                }
                break;

            case 'email_dl_attach':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                $partNum = preg_replace('/[^0-9.]/', '', $_POST['part'] ?? '');
                $raw_filename = $_POST['filename'] ?? 'attachment';
                $filename = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '_', $raw_filename);

                if (!isset($configs[$accId])) exit('Account not found');
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, true);
                if (!$client || !$folderObj) { header('Content-Type: text/plain'); exit('Connection failed'); }

                try {
                    $msg = $folderObj->query()->getMessageByUid($msgId);
                    if (!$msg) { header('Content-Type: text/plain'); exit('Message not found'); }
                    
                    if (isset($_POST['cloud_save']) && $_POST['cloud_save'] === '1') {
                        $subject = strip_tags((string)$msg->getSubject() ?: 'Email');
                        
                        $ts = $this->extractMessageTimestamp($msg);
                        $dateStr = date('Y-m-d H-i-s', $ts);
                        $safeSubj = preg_replace('/[\/\\\\:*?"<>|]/', '_', $subject);
                        if (empty(trim($safeSubj))) $safeSubj = "Email";
                        $filename = $dateStr . ' ' . trim($safeSubj) . ' @ ' . $filename;
                    }

                    $targetAtt = null;
                    $attachments = $msg->getAttachments();
                    foreach ($attachments as $att) {
                        if ((string)$att->id === $partNum || $att->name === $raw_filename) {
                            $targetAtt = $att; break;
                        }
                    }

                    if (!$targetAtt) { header('Content-Type: text/plain'); exit('Attachment not found'); }

                    $tmpFile = tempnam(sys_get_temp_dir(), 'mail_dl_');
                    // ZERO TRUST: Guarantee cleanup even if the user aborts the download stream
                    register_shutdown_function(function() use ($tmpFile) { @unlink($tmpFile); });
                    file_put_contents($tmpFile, $targetAtt->getContent());
                    $client->disconnect();

                    while (ob_get_level() > 0) ob_end_clean();
                    header('Content-Type: application/octet-stream');
                    $safeFilenameASCII = preg_replace('/[^\x20-\x7E]/', '_', $filename);
                    $encodedFilename = rawurlencode($filename);
                    header('Content-Disposition: attachment; filename="' . str_replace('"', '_', $safeFilenameASCII) . '"; filename*=UTF-8\'\'' . $encodedFilename);
                    
                    readfile($tmpFile);
                    exit;
                } catch (\Throwable $e) {
                    header('Content-Type: text/plain'); exit('Fetch failed: ' . $e->getMessage());
                }

            case 'email_dl_eml':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');

                if (!isset($configs[$accId])) exit('Account not found');
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, true);
                if (!$client || !$folderObj) { header('Content-Type: text/plain'); exit('Connection failed: ' . htmlspecialchars($err)); }

                try {
                    $msg = $folderObj->query()->getMessageByUid($msgId);
                    if (!$msg) { header('Content-Type: text/plain'); exit('Message not found'); }

                    $rawMsg = $msg->getHeader()->raw . "\r\n" . $msg->getRawBody();
                    $subject = strip_tags((string)$msg->getSubject() ?: 'Email');
                    
                    $ts = $this->extractMessageTimestamp($msg); // <-- Uses new helper
                    
                    $dateStr = date('Y-m-d H-i-s', $ts);
                    $safeSubj = preg_replace('/[\/\\\\:*?"<>|]/', '_', $subject);
                    if (empty(trim($safeSubj))) $safeSubj = "Email";
                    $filename = $dateStr . ' ' . trim($safeSubj) . '.eml';

                    $client->disconnect();

                    while (ob_get_level() > 0) ob_end_clean();
                    header('Content-Type: message/rfc822');
                    $safeFilenameASCII = preg_replace('/[^\x20-\x7E]/', '_', $filename);
                    $encodedFilename = rawurlencode($filename);
                    header('Content-Disposition: attachment; filename="' . str_replace('"', '_', $safeFilenameASCII) . '"; filename*=UTF-8\'\'' . $encodedFilename);
                    header('Content-Length: ' . strlen($rawMsg));
                    echo $rawMsg;
                    exit;
                } catch (\Throwable $e) {
                    header('Content-Type: text/plain'); exit('Fetch failed: ' . $e->getMessage());
                }

            case 'email_dl_pdf':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^a-zA-Z0-9,:-]/', '', $_POST['message_id'] ?? '');

                if (!isset($configs[$accId])) exit('Account not found');
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, true);
                if (!$client || !$folderObj) { header('Content-Type: text/plain'); exit('Connection failed: ' . htmlspecialchars($err)); } 

                try {
                    $msg = $folderObj->query()->getMessageByUid($msgId);
                    if (!$msg) { header('Content-Type: text/plain'); exit('Message not found'); }

                    $subject = (string)$msg->getSubject() ?: 'No Subject';

                    $addrs = $this->extractMessageAddresses($msg);
                    $ts = $this->extractMessageTimestamp($msg);
                    
                    $date = date('D, d M Y H:i:s', $ts);
                    $dateFormatted = date('Y-m-d H-i-s', $ts);

                    if ($msg->hasHTMLBody()) {
                        $mailBody = $msg->getHTMLBody();
                    } else {
                        $plainText = htmlspecialchars((string)$msg->getTextBody() ?: '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        
                        // ZERO TRUST: Auto-linkify plain text before PDF generation so links remain clickable in the resulting PDF.
                        $plainText = preg_replace_callback('/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]\'"&])|([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', function($m) {
                            if (!empty($m[1])) return '<a href="' . $m[1] . '">' . $m[1] . '</a>';
                            if (!empty($m[2])) return '<a href="mailto:' . $m[2] . '">' . $m[2] . '</a>';
                            return $m[0];
                        }, $plainText);
                        
                        $mailBody = nl2br($plainText);
                    }

                    // ZERO TRUST: Safely extract bounce reports before PDF assembly
                    $bounceHtml = '';
                    $filteredAttachments = [];
                    $attachments = $msg->getAttachments();
                    foreach ($attachments as $att) {
                        $mimeType = '';
                        if (method_exists($att, 'getMimeType')) $mimeType = $att->getMimeType();
                        if (empty($mimeType)) $mimeType = $att->content_type ?? $att->mime ?? '';
                        $mimeType = strtolower(trim(explode(';', (string)$mimeType)[0]));

                        if (strpos($mimeType, 'message/') === 0 || $mimeType === 'text/rfc822') {
                            $originalName = $att->name ?? '';
                            $dispName = empty($originalName) || $originalName === 'unknown' ? 'Attached Message / Delivery Report' : $originalName;
                            
                            $textContent = $att->getContent() ?? '';
                            if (stripos($textContent, 'Content-Transfer-Encoding: quoted-printable') !== false) {
                                $textContent = quoted_printable_decode($textContent);
                            }
                            
                            $bounceText = htmlspecialchars(trim($textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            
                            $bounceHtml .= "<div style=\"border: 1px solid #ccc; background-color: #fcfcfc; padding: 15px; margin-top: 50px; clear: both; border-radius: 4px;\">";
                            $bounceHtml .= "<div style=\"font-weight: bold; font-family: sans-serif; font-size: 13px; color: #333; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #ddd;\">&#128196; " . htmlspecialchars($dispName, ENT_QUOTES, 'UTF-8') . "</div>";
                            $bounceHtml .= "<pre style=\"white-space:pre-wrap; font-family:monospace; font-size:12px; color:#555; word-break: break-all; margin: 0;\">" . $bounceText . "</pre></div>";
                        } else {
                            $filteredAttachments[] = $att;
                        }
                    }
                    $mailBody .= $bounceHtml;

                    $rawHeader = (string)$msg->getHeader()->raw;
                    $reqReceipt = preg_match('/^Disposition-Notification-To:/mi', $rawHeader) || preg_match('/^Return-Receipt-To:/mi', $rawHeader);
                    $isFlagged = false;
                    if (method_exists($msg, 'hasFlag')) {
                        $isFlagged = $msg->hasFlag('Flagged');
                    } elseif (method_exists($msg, 'getFlags')) {
                        $flags = $msg->getFlags();
                        if (is_iterable($flags)) {
                            foreach($flags as $f) {
                                if (stripos($f, 'flagged') !== false) { $isFlagged = true; break; }
                            }
                        }
                    }

                    $rawTo = preg_match('/^To:\s*([^\r\n]+)/mi', $rawHeader, $m) ? trim($this->decodeImapHeader($m[1])) : '';
                    $rawCc = preg_match('/^Cc:\s*([^\r\n]+)/mi', $rawHeader, $m) ? trim($this->decodeImapHeader($m[1])) : '';
                    $rawBcc = preg_match('/^Bcc:\s*([^\r\n]+)/mi', $rawHeader, $m) ? trim($this->decodeImapHeader($m[1])) : '';
                    $finalTo = !empty($addrs['to']) ? $addrs['to'] : $rawTo;
                    $finalCc = !empty($addrs['cc']) ? $addrs['cc'] : $rawCc;
                    $finalBcc = !empty($addrs['bcc']) ? $addrs['bcc'] : $rawBcc;

                    $headerHtml = "<div class=\"email-header\">";
                    $headerHtml .= "<b>From:</b> " . htmlspecialchars($addrs['from_formatted']) . "<br>";
                    $headerHtml .= "<b>Sent:</b> " . htmlspecialchars($date) . "<br>";
                    if (!empty($finalTo)) $headerHtml .= "<b>To:</b> " . htmlspecialchars($finalTo) . "<br>";
                    if (!empty($finalCc)) $headerHtml .= "<b>Cc:</b> " . htmlspecialchars($finalCc) . "<br>";
                    if (!empty($finalBcc)) $headerHtml .= "<b>Bcc:</b> " . htmlspecialchars($finalBcc) . "<br>";
                    $headerHtml .= "<b>Subject:</b> " . htmlspecialchars($subject) . "<br>";

                    $extras = [];
                    if ($isFlagged) $extras[] = "&#128681; Flagged";
                    if ($reqReceipt) $extras[] = "&#128065; Read Receipt Requested";
                    if (!empty($extras)) {
                        $headerHtml .= "<b>Status:</b> " . implode(', ', $extras) . "<br>";
                    }
                    $headerHtml .= "</div>";

                    $mailBody = str_replace("\0", "", $mailBody);

                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $dom->loadHTML('<?xml encoding="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $mailBody, LIBXML_NONET | LIBXML_NOXMLDECL | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    
                    $badTags = ['script', 'link', 'iframe', 'object', 'embed', 'applet', 'meta', 'base', 'video', 'audio', 'source', 'track', 'picture', 'form', 'math', 'frameset', 'frame'];
                    foreach ($badTags as $tag) {
                        $nodes = $dom->getElementsByTagName($tag);
                        for ($i = $nodes->length - 1; $i >= 0; $i--) {
                            $node = $nodes->item($i);
                            $node->parentNode->removeChild($node);
                        }
                    }

                    $loadImages = isset($_POST['load_images']) && $_POST['load_images'] === '1';
                    $styleTags = $dom->getElementsByTagName('style');
                    for ($i = 0; $i < $styleTags->length; $i++) {
                        $node = $styleTags->item($i);
                        $css = $node->nodeValue;
                        $css = preg_replace('/@import\s+(?:url\()?[\'"]?[^\'")]*[\'"]?\)?\s*;/i', '', $css);
                        if (!$loadImages) {
                            $css = preg_replace('/url\([\'"]?(?!data:|cid:)[^)\'"]+[\'"]?\)/i', 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)', $css);
                        } else {
                            $css = preg_replace('/url\([\'"]?(file|ftp|gopher|phar):\/\/[^)\'"]+[\'"]?\)/i', 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)', $css);
                            $css = preg_replace('/url\([\'"]?https?:\/\/(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|169\.254\.|::1)[^)\'"]*[\'"]?\)/i', 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)', $css);
                        }
                        $node->nodeValue = $css;
                    }                

                    $xpath = new DOMXPath($dom);
                    $nodes = $xpath->query('//*[@style]');
                    foreach ($nodes as $node) {
                        $style = $node->getAttribute('style');
                        $styleLower = strtolower($style);
                        if (strpos($styleLower, 'expression(') !== false || strpos($styleLower, 'javascript:') !== false || strpos($styleLower, 'behavior:') !== false) {
                            $node->removeAttribute('style');
                        } elseif (!$loadImages && (strpos($styleLower, 'url(') !== false || strpos($styleLower, '@import') !== false)) {
                            $safeStyle = preg_replace('/url\([\'"]?[^)\'"]+[\'"]?\)/i', 'url(data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=)', $style);
                            $node->setAttribute('style', $safeStyle);
                        }
                    }
                    
                    $nodes = $xpath->query('//*[@background]');
                    foreach ($nodes as $node) {
                        if (!$loadImages) $node->removeAttribute('background');
                    }

                    $nodes = $dom->getElementsByTagName('img');
                    for ($i = $nodes->length - 1; $i >= 0; $i--) {
                        $node = $nodes->item($i);
                        $src = $node->getAttribute('src');
                        if ($src && !preg_match('/^data:/i', $src) && !preg_match('/^cid:/i', $src)) {
                            $isSafeUrl = false;
                            if ($loadImages) {
                                $parsed = parse_url($src);
                                $scheme = strtolower($parsed['scheme'] ?? '');
                                $host = strtolower($parsed['host'] ?? '');
                                if (in_array($scheme, ['http', 'https']) && !preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|169\.254\.|::1)/i', $host)) {
                                    $isSafeUrl = true;
                                }
                            }
                            if (!$isSafeUrl) {
                                 $node->setAttribute('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=');
                                 $node->setAttribute('style', $node->getAttribute('style') . '; border: 1px dashed #ccc; height: 24px !important; width: 24px !important; min-height: 0 !important; display: inline-block;');
                                 $node->removeAttribute('height');
                            }
                        }
                    }
                    
                    $nodes = $dom->getElementsByTagName('a');
                    for ($i = $nodes->length - 1; $i >= 0; $i--) {
                        $node = $nodes->item($i);
                        $href = $node->getAttribute('href');
                        if (preg_match('/^(javascript|vbscript|data):/i', trim($href))) {
                            $node->removeAttribute('href');
                        }
                    }
                    
                    $styleHtml = '';
                    foreach ($dom->getElementsByTagName('style') as $stNode) {
                        $styleHtml .= $dom->saveHTML($stNode);
                    }

                    $mailBody = '';
                    $bodyNode = $dom->getElementsByTagName('body')->item(0);
                    if ($bodyNode) {
                        foreach ($bodyNode->childNodes as $child) { $mailBody .= $dom->saveHTML($child); }
                    } else {
                        $mailBody = $dom->saveHTML();
                    }
                    
                    $mailBody = $styleHtml . $mailBody;
                    $mailBody = str_replace(['<?xml encoding="UTF-8">', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'], '', $mailBody);
                    libxml_clear_errors();

                    $printCss = "body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; line-height: 1.5; padding: 20px; margin: 0; background: #fff; } .email-header { border-bottom: 1px solid #e1e1e1; padding-bottom: 15px; margin-bottom: 20px; font-size: 13px; } .email-header b { display: inline-block; min-width: 80px; color: #555; } .email-header div { margin-bottom: 4px; } img { max-width: 100%; height: auto; page-break-inside: avoid; } table { border-collapse: collapse; } * { word-wrap: break-word; overflow-wrap: break-word; } p { margin-top: 0; margin-bottom: 1em; }";
                    
                    $mailBody = preg_replace('/<(script|iframe|object|embed|applet|meta|base|link).*?>.*?<\/\1>/is', '', $mailBody);
                    $mailBody = preg_replace('/<(script|iframe|object|embed|applet|meta|base|link)[^>]*>/is', '', $mailBody);
                    
                    $html = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>" . htmlspecialchars($subject) . "</title><style>" . $printCss . "</style></head><body>" . $headerHtml . "<div class=\"ce-email-body-content\">" . $mailBody . "</div></body></html>";

                    $globalTemp = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
                    $tmpDir = $globalTemp . '/eml_pdf_' . bin2hex(random_bytes(16));
                    if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
                        header('Content-Type: text/plain'); exit('Failed to allocate secure temporary directory.');
                    }
                
                    $mainHtml = $tmpDir . '/mail.html';
                    $mainPdf = $tmpDir . '/mail.pdf';
                    file_put_contents($mainHtml, $html);

                    $usedOnlyOffice = false;
                    global $cloud_onlyoffice_URL, $cloud_onlyoffice_Secret, $cloud_system_url;
                    if (!empty($cloud_onlyoffice_URL) && !empty($cloud_onlyoffice_Secret)) {
                        $docKey = bin2hex(random_bytes(16));
                        $stateFile = $globalTemp . '/myCloud_office_' . $docKey . '.json';
                        file_put_contents($stateFile, json_encode(['path' => $mainHtml, 'expires' => time() + 300, 'username' => $this->username, 'key' => $this->key]));
                        chmod($stateFile, 0600);
                        
                        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                                 || $_SERVER['SERVER_PORT'] == 443
                                 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                        $protocol = $isHttps ? "https://" : "http://";
                        
                        // ZERO TRUST: Avoid Host header reflection to prevent SSRF callback hijacking
                        if (!empty($cloud_system_url)) {
                            $baseUrl = rtrim($cloud_system_url, '/');
                        } else {
                            $safeHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
                            $baseUrl = rtrim($protocol . $safeHost . parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH), '/');
                        }
                        
                        $payload = ["async" => false, "filetype" => "html", "key" => $docKey, "outputtype" => "pdf", "title" => "mail.html", "url" => $baseUrl . "/myCloudOfficeFetch/" . $docKey];
                        
                        $k = $cloud_onlyoffice_Secret;
                        $h = str_replace(['+','/','='], ['-','_',''], base64_encode(json_encode(['typ'=>'JWT','alg'=>'HS256'])));
                        $b = str_replace(['+','/','='], ['-','_',''], base64_encode(json_encode($payload)));
                        $s = str_replace(['+','/','='], ['-','_',''], base64_encode(hash_hmac('sha256', "$h.$b", $k, true)));
                        $payload['token'] = "$h.$b.$s";
                        
                        $ch = curl_init(rtrim($cloud_onlyoffice_URL, '/') . '/ConvertService.ashx');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        $response = curl_exec($ch);
                        curl_close($ch);
                        
                        @unlink($stateFile);
                        
                        $resData = json_decode($response, true);
                        if (!empty($resData['fileUrl']) && strpos((string)$resData['fileUrl'], 'http') === 0) {
                            $callbackHost = parse_url($resData['fileUrl'], PHP_URL_HOST);
                            $callbackIp = gethostbyname($callbackHost);
                            $ooHost = parse_url($cloud_onlyoffice_URL, PHP_URL_HOST);
                            $isTrustedOnlyOffice = ($callbackHost === $ooHost || $callbackIp === gethostbyname($ooHost));

                            if ($isTrustedOnlyOffice || filter_var($callbackIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false || in_array($callbackIp, ['127.0.0.1', '::1'])) {
                                $chDl = curl_init($resData['fileUrl']);
                                curl_setopt($chDl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($chDl, CURLOPT_FOLLOWLOCATION, false);
                                $callbackPort = parse_url($resData['fileUrl'], PHP_URL_SCHEME) === 'https' ? 443 : 80;
                                curl_setopt($chDl, CURLOPT_RESOLVE, ["{$callbackHost}:{$callbackPort}:{$callbackIp}"]);
                                curl_setopt($chDl, CURLOPT_SSL_VERIFYPEER, false);
                                curl_setopt($chDl, CURLOPT_SSL_VERIFYHOST, 0);
                                $pdfContent = curl_exec($chDl);
                                if (curl_getinfo($chDl, CURLINFO_HTTP_CODE) === 200 && $pdfContent !== false) { 
                                    file_put_contents($mainPdf, $pdfContent); $usedOnlyOffice = true; 
                                }
                                curl_close($chDl);
                            }
                        }
                    }

                    if (!$usedOnlyOffice || !file_exists($mainPdf) || filesize($mainPdf) == 0) {
                        $wkPaths = ['wkhtmltopdf --disable-smart-shrinking', '/usr/bin/wkhtmltopdf --disable-smart-shrinking', '/usr/local/bin/wkhtmltopdf --disable-smart-shrinking'];
                        foreach ($wkPaths as $wk) {
                            @exec($wk . " --encoding utf-8 " . escapeshellarg($mainHtml) . " " . escapeshellarg($mainPdf) . " 2>&1");
                            if (file_exists($mainPdf) && filesize($mainPdf) > 0) break;
                        }
                    }
                    
                    if (!file_exists($mainPdf) || filesize($mainPdf) == 0) {
                        $cleanBody = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $mailBody);
                        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</td>'], "\n", $cleanBody));
                        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $plainText = preg_replace("/\n\s*\n/", "\n\n", trim($plainText));
                        $plainText = wordwrap($plainText, 95, "\n", true);
                        $toLine = !empty($finalTo) ? "To: " . $finalTo . "\n" : "";
                        $ccLine = !empty($finalCc) ? "Cc: " . $finalCc . "\n" : "";
                        $bccLine = !empty($finalBcc) ? "Bcc: " . $finalBcc . "\n" : "";
                        $statusLine = !empty($extras) ? "Status: " . str_replace(['&#128681;', '&#128065;'], ['[Flagged]', '[Read Receipt]'], implode(', ', $extras)) . "\n" : "";
                        $headerText = "From: " . $addrs['from_formatted'] . "\nSent: $date\n" . $toLine . $ccLine . $bccLine . "Subject: $subject\n" . $statusLine . str_repeat("-", 80) . "\n\n";
                        $txtFile = $tmpDir . '/mail.txt';
                        file_put_contents($txtFile, $headerText . $plainText);
                        
                        $imPaths = ['convert', '/usr/bin/convert', '/usr/local/bin/convert'];
                        foreach ($imPaths as $im) {
                            @exec($im . " -background white -fill black -pointsize 12 text:" . escapeshellarg($txtFile) . " " . escapeshellarg($mainPdf) . " 2>&1");
                            if (file_exists($mainPdf) && filesize($mainPdf) > 0) break;
                        }
                    }

                    if (!file_exists($mainPdf) || filesize($mainPdf) == 0) {
                        $safeTitle = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '_', $subject);
                        @exec("convert -size 595x842 xc:white -pointsize 14 -fill black -annotate +50+50 'Email: " . escapeshellarg($safeTitle) . "\n\n(HTML to PDF conversion tools missing.\nAttachments appended below.)' " . escapeshellarg($mainPdf) . " 2>&1");
                    }

                    if (!file_exists($mainPdf) || filesize($mainPdf) == 0) {
                        header('Content-Type: text/plain'); 
                        exit('Failed to generate base PDF.');
                    }

                    $mergePdfs = [$mainPdf];
                    $attachFiles = [];

                    foreach ($filteredAttachments as $att) {
                        $attName = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '_', $att->name);
                        if (empty($attName)) $attName = 'attachment_' . uniqid();
                        $attExt = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
                        
                        $tmpPath = $tmpDir . '/' . $attName;
                        file_put_contents($tmpPath, $att->getContent());

                        if ($attExt === 'pdf') {
                            @exec("qpdf --requires-password " . escapeshellarg($tmpPath), $qOut, $qRet);
                            if ($qRet === 0) $attachFiles[] = $tmpPath; 
                            else $mergePdfs[] = $tmpPath;                
                        } elseif (in_array($attExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $realMime = finfo_file($finfo, $tmpPath);
                            finfo_close($finfo);
                            if (!in_array($realMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'])) {
                                $attachFiles[] = $tmpPath;
                                continue;
                            }

                            // Force ImageMagick to treat the file exactly as the verified MIME type
                            // Prevents ImageTragick style delegates bypass via polyglot files
                            $imFormatMap = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp'];
                            $imFormat = $imFormatMap[$realMime] ?? 'jpeg';

                            $imgPdf = $tmpPath . '.pdf';
                            @exec("magick convert " . escapeshellarg($imFormat . ':' . $tmpPath) . " " . escapeshellarg($imgPdf) . " 2>&1", $mOut, $mRet);
                            if ($mRet !== 0) @exec("convert " . escapeshellarg($imFormat . ':' . $tmpPath) . " " . escapeshellarg($imgPdf) . " 2>&1");
                            
                            if (file_exists($imgPdf)) $mergePdfs[] = $imgPdf; 
                            else $attachFiles[] = $tmpPath;                    
                        } else {
                            $attachFiles[] = $tmpPath; 
                        }
                    }
                    $client->disconnect();

                    $mergedPdf = $tmpDir . '/merged.pdf';
                    if (count($mergePdfs) > 1) {
                        @exec("pdftk " . implode(' ', array_map('escapeshellarg', $mergePdfs)) . " cat output " . escapeshellarg($mergedPdf) . " 2>&1");
                        if (!file_exists($mergedPdf) || filesize($mergedPdf) == 0) {
                            $qArgs = []; foreach($mergePdfs as $m) { $qArgs[] = escapeshellarg($m) . " 1-z"; }
                            @exec("qpdf --empty --pages " . implode(' ', $qArgs) . " -- " . escapeshellarg($mergedPdf) . " 2>&1");
                        }
                        if (!file_exists($mergedPdf) || filesize($mergedPdf) == 0) {
                            @exec("pdfunite " . implode(' ', array_map('escapeshellarg', $mergePdfs)) . " " . escapeshellarg($mergedPdf) . " 2>&1");
                        }
                        if (!file_exists($mergedPdf) || filesize($mergedPdf) == 0) {
                            @exec("gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dAutoRotatePages=/None -sOutputFile=" . escapeshellarg($mergedPdf) . " " . implode(' ', array_map('escapeshellarg', $mergePdfs)));
                        }
                        $basePdfForAttachment = file_exists($mergedPdf) ? $mergedPdf : $mainPdf;
                    } else {
                        $basePdfForAttachment = $mainPdf;
                    }

                    $finalPdf = $tmpDir . '/final.pdf';
                    if (count($attachFiles) > 0) {
                        @exec("pdftk " . escapeshellarg($basePdfForAttachment) . " attach_files " . implode(' ', array_map('escapeshellarg', $attachFiles)) . " output " . escapeshellarg($finalPdf));
                        if (!file_exists($finalPdf)) $finalPdf = $basePdfForAttachment;
                    } else {
                        $finalPdf = $basePdfForAttachment;
                    }

                    if (file_exists($finalPdf)) {
                        $outData = file_get_contents($finalPdf);
                        $safeSubject = preg_replace('/[\/\\\\:*?"<>|]/', '_', $subject);
                        $filename = $dateFormatted . ' ' . (empty(trim($safeSubject)) ? 'Email' : trim($safeSubject)) . '.pdf';
                        
                        $safeFilenameASCII = preg_replace('/[^\x20-\x7E]/', '_', $filename);
                        $encodedFilename = rawurlencode($filename);
                        
                        while (ob_get_level() > 0) ob_end_clean();
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . str_replace('"', '_', $safeFilenameASCII) . '"; filename*=UTF-8\'\'' . $encodedFilename);
                        header('Content-Length: ' . strlen($outData));
                        echo $outData;
                    } else {
                        echo "Error generating final PDF.";
                    }

                    foreach (glob($tmpDir . '/*') as $f) { if (is_file($f)) @unlink($f); }
                    @rmdir($tmpDir);
                    exit;
                } catch (\Throwable $e) {
                    header('Content-Type: text/plain'); exit('PDF Fetch failed: ' . $e->getMessage());
                }

            case 'email_delete_msg':
                 if (!$this->actionAllowed('email_delete')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                 $configs = $this->loadConfigs();
                 $accId = $_POST['account_id'] ?? '';
                 $folder = $_POST['folder'] ?? 'INBOX';
                 $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                 
                 if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Account not found']);

                 // --- OUTBOX QUEUE INTERCEPT ---
                 $outboxDir = dirname($this->config_file) . '/' . $this->username . '_outbox';
                 if (!is_dir($outboxDir)) @mkdir($outboxDir, 0755, true);
                 
                 $jobId = uniqid('del_');
                 $jobData = [
                     'action' => 'email_delete_msg',
                     'account_id' => $accId,
                     'timestamp' => time(),
                     'payload' => $_POST
                 ];
                 file_put_contents("$outboxDir/$jobId.job", json_encode($jobData));
 
                 $this->purgeLocalCacheUids($accId, $folder, $msgId);
                 $this->sendJsonAndContinue(['status' => 'OK', 'task_id' => $jobId]);
                 $this->processOutboxQueue();
                 break;

            case 'email_move_msg':
            case 'email_copy_msg':
                $isMove = ($action === 'email_move_msg');
                if (!$this->actionAllowed($isMove ? 'email_move' : 'email_copy')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $srcFolder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                $destAccId = $_POST['dest_account_id'] ?? $accId;
                $destFolder = str_replace(["\r", "\n"], '', $_POST['dest_folder'] ?? '');
                
                if (!isset($configs[$accId]) || !isset($configs[$destAccId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);

                $outboxDir = dirname($this->config_file) . '/' . $this->username . '_outbox';
                if (!is_dir($outboxDir)) @mkdir($outboxDir, 0755, true);
                
                $jobId = uniqid('job_');
                $jobData = [
                    'action' => $action,
                    'account_id' => $accId,
                    'timestamp' => time(),
                    'payload' => $_POST
                ];
                file_put_contents("$outboxDir/$jobId.job", json_encode($jobData));
                $this->sendJsonAndContinue(['status' => 'OK', 'task_id' => $jobId]);
                $this->processOutboxQueue();

                if ($isMove) {
                    $this->purgeLocalCacheUids($accId, $srcFolder, $msgId);
                }
//				$this->sendJsonAndExit(['status' => 'OK']);
                break;
				
            case 'email_restore_msg':
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $folder = $_POST['folder'] ?? 'INBOX';
                $msgId = preg_replace('/[^0-9,]/', '', $_POST['message_id'] ?? '');
                
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Account not found']);
                
                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], $folder, false);
                if (!$client || !$folderObj) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>$err ?: 'Folder not found']);
                
                try {
                    $msg = $folderObj->query()->getMessageByUid($msgId);
                    if ($msg) {
                        $msg->move('INBOX');
                        $client->disconnect();

                        // CRITICAL FIX: Drop a dirty flag on the destination folder to force the UI cache to update instantly
                        $safeDestFld = preg_replace('/[^a-zA-Z0-9_-]/', '_', 'INBOX');
                        touch($this->cache_dir . "/{$accId}_{$safeDestFld}.dirty");

                        $this->purgeLocalCacheUids($accId, $folder, $msgId);
						$this->releaseUiAndPrewarmCache($accId, 'INBOX');

                        $this->sendJsonAndExit(['status' => 'OK']);
                    } else {
                        $client->disconnect();
                        $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Message not found.']);
                    }
                } catch (\Throwable $e) {
                    if ($client) $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to restore message: ' . $e->getMessage()]);
                }
                break;

            case 'email_save_draft':
                if (!$this->actionAllowed('email_send')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Drafts disabled.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
                
                $acc = $configs[$accId];
                $to = str_replace(["\r", "\n"], '', $_POST['to'] ?? '');
                $cc = str_replace(["\r", "\n"], '', $_POST['cc'] ?? '');
                $bcc = str_replace(["\r", "\n"], '', $_POST['bcc'] ?? '');
                $subject = str_replace(["\r", "\n"], '', $_POST['subject'] ?? '');
                $body = $_POST['body'] ?? '';
                $fromAlias = str_replace(["\r", "\n"], '', $_POST['fromAlias'] ?? '');
                
                $attCount = (int)($_POST['att_count'] ?? 0);
                $attachments = [];
                $tempDir = $this->user_temp_dir;
                for ($i = 0; $i < $attCount; $i++) {
                    $name = $_POST['att_name_' . $i] ?? '';
                    $rawPath = $_POST['att_path_' . $i] ?? '';
                    if ($name !== '' && $rawPath !== '') {
                        $base = basename($rawPath);
                        $enforcedPath = $tempDir . '/' . $base; 
                        if ((strpos($base, 'myCloud_eml_att_') === 0 || strpos($base, 'inline_') === 0) && file_exists($enforcedPath)) {
                            $attachments[] = ['name' => $name, 'tmp_name' => $enforcedPath];
                        }
                    }
                }

                $res = $this->sendSmtpMail($acc, $to, $subject, $body, $fromAlias, $cc, $bcc, $attachments, true);
                $rawMail = is_array($res) ? $res['raw'] : (strpos($res, 'From:') !== false ? $res : null);

                if (!$rawMail && is_string($res)) {
                    $cleanBody = trim(strip_tags($body));
                    $isPGP = (strpos($cleanBody, '-----BEGIN PGP MESSAGE-----') === 0 || strpos($cleanBody, '-----BEGIN PGP PUBLIC KEY BLOCK-----') === 0);
                    $cType = $isPGP ? 'text/plain' : 'text/html';

                    $hasUnicode = preg_match('/[^\x00-\x7F]/', $body);
                    if ($isPGP) {
                        $tEnc = '7bit';
                        $encBody = $cleanBody;
                    } else if ($hasUnicode) {
                        $tEnc = 'base64';
                        $encBody = trim(chunk_split(base64_encode($body)));
                    } else {
                        $tEnc = 'quoted-printable';
                        $encBody = quoted_printable_encode($body);
                    }

                    $rawMail = "Date: " . date('r') . "\r\nFrom: <{$acc['email']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: $cType; charset=UTF-8\r\nContent-Transfer-Encoding: $tEnc\r\n\r\n" . $encBody;
                }

                list($client, $folderObj, $err) = $this->connectImap($configs[$accId], '', false);
                if (!$client) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>"IMAP connection failed: $err"]);
                
                try {
                    $draftsFolderName = $this->getDraftsFolder($client);
                    $draftsFolder = $client->getFolderByPath($draftsFolderName);
                    
                    $appendRes = false;
                    if ($draftsFolder) {
                        $appendRes = $draftsFolder->appendMessage($rawMail, ['\Draft']);
                    }
                    
                    $draftUid = $_POST['draft_uid'] ?? '';
                    $draftFolderStr = str_replace(["\r", "\n"], '', $_POST['draft_folder'] ?? '');
                    if ($draftUid && $draftFolderStr && $appendRes) {
                        $oldDraftFolder = $client->getFolderByPath($draftFolderStr);
                        if ($oldDraftFolder) {
                            $oldMsg = $oldDraftFolder->query()->getMessageByUid($draftUid);
                            if ($oldMsg) { $oldMsg->delete(); $oldDraftFolder->expunge(); }
                        }
                    }

                    $newUid = '';
                    if ($appendRes && $rawMail) {
                        preg_match('/Message-ID:\s*(<[^>]+>)/i', $rawMail, $m);
                        $newMsgId = $m[1] ?? '';
                        if ($newMsgId) {
                            $cleanMsgId = str_replace(['<', '>', '"'], '', $newMsgId);
                            $searchRes = $client->getConnection()->search(['HEADER', 'Message-ID', $cleanMsgId], true);
                            $searchUids = [];
                            if ($searchRes) {
                                $searchUids = method_exists($searchRes, 'validatedData') ? $searchRes->validatedData() : (isset($searchRes->data) ? (array)$searchRes->data : []);
                            }
                            if (!empty($searchUids) && is_array($searchUids)) {
                                $newUid = max($searchUids);
                            }
                        }
                    }
                    
                    $client->disconnect();
                    if ($appendRes) $this->sendJsonAndExit(['status' => 'OK', 'msg' => 'Draft saved.', 'new_draft_uid' => $newUid, 'new_draft_folder' => $draftsFolderName]);
                    else $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Failed to save draft.']);
                } catch (\Throwable $e) {
                    if ($client) $client->disconnect();
                    $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Draft Exception: ' . $e->getMessage()]);
                }
                break;

			// Receive attachments before sending the email so they don't block the UI
            case 'email_upload_temp_attach':
                $temp = $this->user_temp_dir;
                // Mathematically prove the file arrived via HTTP POST to prevent LFI
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // Use native tempnam() to allocate file descriptors safely without race conditions
                    $dest = tempnam($temp, 'myCloud_eml_att_');
                    if ($dest !== false && move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                        // Append extension safely after the OS has locked the base file
                        if (rename($dest, $dest . '.tmp')) {
                            $dest .= '.tmp';
                            $this->sendJsonAndExit(['status' => 'OK', 'tmp_path' => $dest, 'name' => $_FILES['file']['name']]);
                        }
                     }
                }
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Upload failed']);
                break;

            case 'email_cleanup_temp':
                $paths = json_decode($_POST['paths'] ?? '[]', true);
                $temp = rtrim($this->user_temp_dir, '/\\');
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        $base = basename($path);
                        // Security: Only allow deletion of our specific temp file prefixes
                        $safePath = $temp . '/' . $base;
                        // SECURITY FIX: Reconstruct absolute path to trap deletion inside temp directory
                        if ((strpos($base, 'myCloud_eml_att_') === 0 || strpos($base, 'inline_') === 0) && file_exists($safePath)) {
                            @unlink($safePath);
                        }
                    }
                }
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_undo_send':
                $taskId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['task_id'] ?? '');
                $jobFile = dirname($this->config_file) . '/' . $this->username . '_outbox/' . $taskId . '.job';
                if (file_exists($jobFile)) {

                   // 1. Recover the payload before destruction
                   $jobData = json_decode(file_get_contents($jobFile), true);
                   if (is_array($jobData) && !empty($jobData['payload'])) {
                        $p = $jobData['payload'];
                        $configs = $this->loadConfigs();
                        $acc = $configs[$jobData['account_id']] ?? null;
                        
                        if ($acc) {
                            // 2. Reconstruct a basic MIME payload for the Drafts folder
                            $draftMime = "From: " . ($p['from'] ?: $acc['email']) . "\r\n";
                            if (!empty($p['to'])) $draftMime .= "To: " . $p['to'] . "\r\n";
                            if (!empty($p['cc'])) $draftMime .= "Cc: " . $p['cc'] . "\r\n";
                            if (!empty($p['bcc'])) $draftMime .= "Bcc: " . $p['bcc'] . "\r\n";
                            $draftMime .= "Subject: " . $p['subject'] . "\r\n";
                            $draftMime .= "MIME-Version: 1.0\r\n";
                            $draftMime .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
                            $draftMime .= $p['body'];
                            
                            // 3. Resurrect the draft safely on the IMAP server
                            list($client, $folderObj, $err) = $this->connectImap($acc, '', false);
                            if ($client) {
                                try {
                                    $draftsFolderName = $this->getDraftsFolder($client);
                                    $draftsFolder = $client->getFolderByPath($draftsFolderName);
                                    if ($draftsFolder) {
                                        $draftsFolder->appendMessage($draftMime, ['\Draft']);
                                    }
                                } catch (\Exception $e) {}
                                $client->disconnect();
                            }
                        }
                    }

                    @unlink($jobFile); // Destroy the payload before the background worker processes it
                    $this->sendJsonAndExit(['status' => 'OK']);
                }
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Message already sent or not found.']);
                break;

            // Background polling endpoint for the send status
			case 'email_check_send_status':
                 $taskId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['task_id'] ?? '');
                $outboxDir = dirname($this->config_file) . '/' . $this->username . '_outbox';
                $baseFile = $outboxDir . '/' . $taskId;
                
                if (file_exists($baseFile . '.success')) {
                    @unlink($baseFile . '.success');
                    $this->sendJsonAndExit(['status' => 'success']);
                } else if (file_exists($baseFile . '.error')) {
                    $errData = json_decode(file_get_contents($baseFile . '.error'), true);
                    @unlink($baseFile . '.error');
                    $this->sendJsonAndExit(['status' => 'error', 'msg' => $errData['msg'] ?? 'Operation failed']);
                } else if (file_exists($baseFile . '.job') || file_exists($baseFile . '.job.processing')) {
                    $this->sendJsonAndExit(['status' => 'pending']);
                }
                 $this->sendJsonAndExit(['status' => 'pending']);
                 break;
 
            case 'email_send':
                if (!$this->actionAllowed('email_send')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Sending emails is disabled on this account.']);
                $configs = $this->loadConfigs();
                $accId = $_POST['account_id'] ?? '';
                $fromEmail = trim($_POST['from'] ?? '');

                // Auto-Collect Recipients (Moved up to prevent exit bypass)
                if (isset($_POST['auto_collect']) && $_POST['auto_collect'] === '1') {
                    $this->autoCollectContacts($_POST['to'] ?? '', $_POST['cc'] ?? '', $_POST['bcc'] ?? '');
                }

                // --- OUTBOX QUEUE INTERCEPT ---
                if (empty($_POST['is_draft'])) {
                    $outboxDir = dirname($this->config_file) . '/' . $this->username . '_outbox';
                    if (!is_dir($outboxDir)) @mkdir($outboxDir, 0755, true);
                    
                    $attCount = (int)($_POST['att_count'] ?? 0);
                    $attachments = [];
                    $tempDir = $this->user_temp_dir;
                    for ($i = 0; $i < $attCount; $i++) {
                        $name = $_POST['att_name_' . $i] ?? '';
                        $rawPath = $_POST['att_path_' . $i] ?? '';
                        if ($name !== '' && $rawPath !== '') {
                            $base = basename($rawPath);
                            // Ignore client-supplied directory paths, confine strictly to server temp dir
                            $enforcedTmpPath = $tempDir . '/' . $base;

                            if ((strpos($base, 'myCloud_eml_att_') === 0 || strpos($base, 'inline_') === 0) && file_exists($enforcedTmpPath)) {
                                $persPath = $outboxDir . '/' . $base;
                                rename($enforcedTmpPath, $persPath);
                                $attachments[] = ['name' => $name, 'tmp_name' => $persPath];
                            }
                        }
                    }

                    // Cryptographically secure task IDs prevent queue enumeration
                    $jobId = 'send_' . bin2hex(random_bytes(16));

                    // Strip CRLF and Null bytes from header fields to prevent SMTP Header Injection & Queue Poisoning.
                    $cleanHeader = function($str) {
                        return trim(str_replace(["\r", "\n", "\0", "%0a", "%0d"], '', $str));
                    };

                    // Whitelist payload data to prevent writing malicious keys to the .job file
                    $payload = [
                        'to'      => str_replace(';', ',', $cleanHeader($_POST['to'] ?? '')),
                        'cc'      => str_replace(';', ',', $cleanHeader($_POST['cc'] ?? '')),
                        'bcc'     => str_replace(';', ',', $cleanHeader($_POST['bcc'] ?? '')),
                        'subject' => $cleanHeader($_POST['subject'] ?? ''),
                        'body'    => $_POST['body'] ?? '', // Body must retain formatting/newlines; neutralized by JSON encoding on disk.
                        'from'    => $cleanHeader($_POST['from'] ?? ''),
                        'read_receipt' => !empty($_POST['read_receipt'])
                    ];
                    $payload['attachments'] = $attachments;
                    
                    $jobData = [
                        'action' => 'email_send',
                        'account_id' => $accId,
                        'timestamp' => time(),
                        'execute_after' => time() + (int)($_POST['undo_buffer'] ?? 0),
                        'payload' => $payload
                    ];
                    file_put_contents("$outboxDir/$jobId.job", json_encode($jobData));
                    $this->sendJsonAndContinue(['status' => 'OK', 'task_id' => $jobId]);
                    $this->processOutboxQueue();
					exit;
                }
                // --- END INTERCEPT ---

                // --- FIX: SMARTBOX & ALIAS ROUTING ---
                // If sent from virtual inbox ('smartbox') or an alias, resolve the true parent account.
                if ($fromEmail !== '') {
                    $trueAccId = null;
                    foreach ($configs as $id => $acc) {
                        if (!empty($acc['is_inactive'])) continue;
                        if (strtolower(trim($acc['email'])) === strtolower($fromEmail)) {
                            $trueAccId = $id;
                            break;
                        }
                        if (!empty($acc['aliases'])) {
                            // Support both encoded and array alias formats
                            $aliases = is_string($acc['aliases']) ? json_decode($acc['aliases'], true) : $acc['aliases'];
                            if (is_array($aliases)) {
                                foreach ($aliases as $al) {
                                    $alEmail = is_array($al) ? ($al['email'] ?? '') : $al;
                                    if (strtolower(trim($alEmail)) === strtolower($fromEmail)) {
                                        $trueAccId = $id;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    if ($trueAccId) $accId = $trueAccId;
                }

                if (!isset($configs[$accId])) $this->sendJsonAndExit(['status'=>'ERR','msg'=>'Account not found.']);
               
                // STRICT FLAT PARSING: No arrays. Read att_name_0, att_path_0, etc.
                $attCount = (int)($_POST['att_count'] ?? 0);
                $attachments = [];
                $tempDir = $this->user_temp_dir;
                for ($i = 0; $i < $attCount; $i++) {
                    $name = $_POST['att_name_' . $i] ?? '';
                    $rawPath = $_POST['att_path_' . $i] ?? '';
                    if ($name !== '' && $rawPath !== '') {
                        $base = basename($rawPath);
                        
                        // ZERO TRUST: Confine strictly to the local temp directory
                        $enforcedTmpPath = $tempDir . '/' . $base;

                        if ((strpos($base, 'myCloud_eml_att_') === 0 || strpos($base, 'inline_') === 0) && file_exists($enforcedTmpPath)) {
                            $attachments[] = ['name' => $name, 'tmp_name' => $enforcedTmpPath];
                        }
                    }
                }

                // Create background task ID
                $taskId = 'send_' . bin2hex(random_bytes(16));
                $temp = $this->user_temp_dir;
                $statusFile = $temp . '/' . $taskId . '.status';
                file_put_contents($statusFile, json_encode(['status' => 'pending']));


                // Unlock Session and Close Output to release Frontend UI Instantly
                while (ob_get_level() > 0) ob_end_clean();
                header('Content-Type: application/json');
                header('Connection: close');
                ob_start();
                echo json_encode(['status' => 'OK', 'task_id' => $taskId]);
                $size = ob_get_length();
                header("Content-Length: $size");
                ob_end_flush();
                @ob_flush();
                flush();
                

                // Standard PHP-FPM connection drop (Execution continues below)
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                else ignore_user_abort(true);

 
                // ----- BACKGROUND PROCESS EXECUTES BELOW -----

 

                $cc = $_POST['cc'] ?? '';
                $bcc = $_POST['bcc'] ?? '';
                
                $body = $_POST['body'] ?? '';
                $inlineAttachments = [];
                $body = preg_replace_callback('/src="data:image\/(.*?);base64,(.*?)"/i', function($matches) use (&$inlineAttachments) {
                    $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                    $data = base64_decode($matches[2]);
                    $cid = md5(uniqid('', true)) . '@mycloud.local';
                    // ZERO TRUST: Use cryptographically secure randomness for temp files
                    $tmpFile = sys_get_temp_dir() . '/inline_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    file_put_contents($tmpFile, $data);
                    $inlineAttachments[] = [
                        'name' => 'image_' . uniqid() . '.' . $ext,
                        'tmp_name' => $tmpFile,
                        'cid' => $cid
                    ];
                    return 'src="cid:' . $cid . '"';
                }, $body);
                $attachments = array_merge($attachments, $inlineAttachments);

                $res = $this->sendSmtpMail($configs[$accId], $_POST['to'] ?? '', $_POST['subject'] ?? '', $body, $_POST['from'] ?? null, $cc, $bcc, $attachments);
                
                if (is_array($res) && $res['status'] === 'OK') {
                    list($client, $folderObj, $err) = $this->connectImap($configs[$accId], '', false);
                    if ($client) {
                        // Route to Sent folder or Drafts depending on trigger
                        $isDraftRequested = (isset($_POST['is_draft']) && $_POST['is_draft'] === 'true');
                        $targetFolderName = $isDraftRequested ? $this->getDraftsFolder($client) : $this->getSentFolder($client);
                        
                        try {
                            $targetFld = $client->getFolderByPath($targetFolderName);
                            if ($targetFld) {
                                $targetFld->appendMessage($res['raw'], [$isDraftRequested ? '\Draft' : '\Seen']);
                            }
                            $draftUid = $_POST['draft_uid'] ?? '';
                            $draftFolderStr = $_POST['draft_folder'] ?? '';
                            if ($draftUid && $draftFolderStr) {
                                $oldFld = $client->getFolderByPath($draftFolderStr);
                                if ($oldFld) {
                                    $oldMsg = $oldFld->query()->getMessageByUid($draftUid);
                                    if ($oldMsg) { $oldMsg->delete(); $oldFld->expunge(); }
                                }
                            }
                        } catch (\Exception $ex) {}
                        $client->disconnect();
                    }
                    file_put_contents($statusFile, json_encode(['status' => 'success', 'msg' => 'Email sent successfully.']));
                } else {
                    list($client, $folderObj, $err) = $this->connectImap($configs[$accId], '', false);
                    if ($client) {
                        $sender_email = $_POST['from'] ?? $configs[$accId]['email'];
                        $bodyStr = $_POST['body'] ?? '';
                        $cleanBodyStr = trim(strip_tags($bodyStr));
                        $isPGP = (strpos($cleanBodyStr, '-----BEGIN PGP MESSAGE-----') === 0 || strpos($cleanBodyStr, '-----BEGIN PGP PUBLIC KEY BLOCK-----') === 0);
                        if ($isPGP) $bodyStr = $cleanBodyStr;
                        $bodyStr = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $bodyStr);
                        $cType = $isPGP ? 'text/plain' : 'text/html';
                        
                        $hasUnicode = preg_match('/[^\x00-\x7F]/', $bodyStr);
                        if ($isPGP) {
                            $tEnc = '7bit';
                            $encBody = $cleanBodyStr;
                        } else if ($hasUnicode) {
                            $tEnc = 'base64';
                            $encBody = trim(chunk_split(base64_encode($bodyStr)));
                        } else {
                            $tEnc = 'quoted-printable';
                            $encBody = quoted_printable_encode($bodyStr);
                        }
                        
                        $rawMail = "Date: " . gmdate('D, d M Y H:i:s O') . "\r\nFrom: <{$sender_email}>\r\nTo: " . ($_POST['to'] ?? '') . "\r\nSubject: =?UTF-8?B?" . base64_encode($_POST['subject'] ?? '') . "?=\r\nMIME-Version: 1.0\r\nContent-Type: $cType; charset=UTF-8\r\nContent-Transfer-Encoding: $tEnc\r\n\r\n" . $encBody;
                        
                        // Dynamically route the IMAP upload based on the requested action
                        $isDraft = (isset($_POST['is_draft']) && $_POST['is_draft'] === 'true');
                        $targetFolderName = $isDraft ? $this->getDraftsFolder($client) : $this->getSentFolder($client);
                        
                        try {
                            $targetFld = $client->getFolderByPath($targetFolderName);
                            if ($targetFld) $targetFld->appendMessage($rawMail, [$isDraft ? '\Draft' : '\Seen']);
                        } catch (\Exception $ex) {}
                        $client->disconnect();
                    }
                    file_put_contents($statusFile, json_encode(['status' => 'error', 'msg' => is_string($res) ? $res : 'Send failed. Check SMTP configuration. Message saved to Drafts.']));
                }
                
                // Garbage Collection for temp attachments
                foreach ($attachments as $att) { @unlink($att['tmp_name']); }
                exit;
                break;
 
			// --- CONTACTS (ADDRESS BOOK) ACTIONS ---
            case 'email_get_contacts':
                $contacts = $this->loadContacts();
				$auto = $this->loadAutoContacts();
                usort($contacts, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
				usort($auto, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
                $this->sendJsonAndExit(['status' => 'OK', 'contacts' => $contacts, 'auto_contacts' => $auto]);
                break;

            case 'email_save_contact':
                if (!$this->actionAllowed('email_contacts')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Contacts disabled.']);
				$bookType = $_POST['book_type'] ?? 'main';
                $contacts = $bookType === 'auto' ? $this->loadAutoContacts() : $this->loadContacts();
                $id = !empty($_POST['contact_id']) ? trim($_POST['contact_id']) : 'cnt_' . bin2hex(random_bytes(12));
                $idx = array_search($id, array_column($contacts, 'id'));
                
                // Decode the flexible JSON arrays sent from the frontend UI
                $emailsRaw = json_decode($_POST['emails'] ?? '[]', true) ?: [];
                $phonesRaw = json_decode($_POST['phones'] ?? '[]', true) ?: [];
                
                $emails = [];
                foreach ($emailsRaw as $e) { 
                    if (trim($e['val'])) $emails[] = ['type' => mb_convert_encoding(trim($e['type'] ?: 'Work'), 'UTF-8', 'UTF-8'), 'val' => strtolower(trim($e['val']))]; 
                }
                $phones = [];
                foreach ($phonesRaw as $p) { 
                    if (trim($p['val'])) $phones[] = ['type' => mb_convert_encoding(trim($p['type'] ?: 'Mobile'), 'UTF-8', 'UTF-8'), 'val' => trim($p['val'])]; 
                }

                $contact = [
                    'id' => $id,
                    'name' => trim($_POST['name'] ?? ''),
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'emails' => $emails,
                    'phones' => $phones,
                    'company' => trim($_POST['company'] ?? ''),
                    'job_title' => trim($_POST['job_title'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'website' => trim($_POST['website'] ?? ''),
                    'labels' => trim($_POST['labels'] ?? ''),
                    'notes' => trim($_POST['notes'] ?? ''),
					'pgp_public_key' => trim($_POST['pgp_public_key'] ?? '')
                ];
                
                if (empty($contact['name'])) {
                    $parts = array_filter([$contact['first_name'], $contact['last_name']]);
                    if (!empty($parts)) $contact['name'] = implode(' ', $parts);
                    elseif (!empty($contact['company'])) $contact['name'] = $contact['company'];
                    elseif (!empty($contact['emails'])) $contact['name'] = explode('@', $contact['emails'][0]['val'])[0];
                }

                if ($idx !== false) $contacts[$idx] = $contact;
                else $contacts[] = $contact;
                
                if ($bookType === 'auto') $this->saveAutoContacts($contacts);
                else $this->saveContacts($contacts);
                $this->sendJsonAndExit(['status' => 'OK', 'contact' => $contact]);
                break;

            case 'email_delete_contact':
                if (!$this->actionAllowed('email_contacts')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Contacts disabled.']);
				$bookType = $_POST['book_type'] ?? 'main';
                $contacts = $bookType === 'auto' ? $this->loadAutoContacts() : $this->loadContacts();
                $id = $_POST['contact_id'] ?? '';
                $contacts = array_values(array_filter($contacts, function($c) use ($id) { return $c['id'] !== $id; }));
                if ($bookType === 'auto') $this->saveAutoContacts($contacts);
                else $this->saveContacts($contacts);
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_clear_auto_contacts':
                $this->saveAutoContacts([]);
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_import_contacts':
                if (!$this->actionAllowed('email_import_contacts')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Importing contacts is disabled.']);
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Upload failed.']);
                
                $fileContent = file_get_contents($_FILES['file']['tmp_name']);
                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $contacts = $this->loadContacts();
                $importedCount = 0;

                // --- UNIVERSAL ADDRESS BUILDER (Extracted strictly from original CSV logic) ---
                $buildAddress = function($street, $city, $region, $zip, $country, $fmtAddr = '') {
                    if ($street || $city || $zip || $country) {
                        $lines = [];
                        $countryLower = strtolower($country);
                        if (empty($countryLower) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                            $primaryLang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0];
                            if (preg_match('/^[a-zA-Z]{2}-([a-zA-Z]{2})/', $primaryLang, $m)) $countryLower = strtolower($m[1]);
                            elseif (preg_match('/^([a-zA-Z]{2})/', $primaryLang, $m)) $countryLower = strtolower($m[1]);
                        }

                        $isNumberFirst = in_array($countryLower, ['us', 'usa', 'united states', 'united states of america', 'ca', 'canada', 'uk', 'united kingdom', 'gb', 'great britain', 'au', 'australia', 'fr', 'france', 'nz', 'new zealand', 'ie', 'ireland']);
                        if ($street && !$isNumberFirst) {
                            if (preg_match('/^(\d+[a-zA-Z]?(?:-\d+[a-zA-Z]?)?)\s+(.+)$/u', $street, $matches)) {
                                $num = $matches[1]; $str = $matches[2];
                                if (!preg_match('/^(st|nd|rd|th)\b/i', $str)) $street = $str . ' ' . $num;
                            }
                        }

                        if ($street) $lines[] = $street;
                        $isUSFormat = in_array($countryLower, ['us', 'usa', 'united states', 'united states of america', 'ca', 'canada', 'uk', 'united kingdom', 'gb', 'great britain', 'au', 'australia']);
                        if ($isUSFormat) {
                            $cityLine = $city . ($city && ($region || $zip) ? ',' : '');
                            if ($region) $cityLine .= ' ' . $region;
                            if ($zip) $cityLine .= ' ' . $zip;
                            if (trim($cityLine)) $lines[] = trim($cityLine);
                        } else {
                            $cityLine = trim($zip . ' ' . $city);
                            if ($region && $cityLine) $cityLine .= ' (' . $region . ')';
                            elseif ($region) $cityLine = $region;
                            if ($cityLine) $lines[] = $cityLine;
                        }
                        if ($country) $lines[] = $country;
                        return implode("\n", $lines);
                    } elseif ($fmtAddr) {
                        return str_replace(['\n', '\r', ' ::: ', ':::'], "\n", $fmtAddr);
                    }
                    return '';
                };

                // --- UNIVERSAL CONTACT INJECTOR & DEDUPLICATOR ---
                $addParsedContact = function($c) use (&$contacts, &$importedCount) {
                    if (empty($c['name']) && empty($c['emails']) && empty($c['phones']) && empty($c['company'])) return;
                    
                    if (empty($c['name'])) {
                        $parts = array_filter([$c['first_name'], $c['last_name']]);
                        if (!empty($parts)) $c['name'] = implode(' ', $parts);
                        elseif (!empty($c['company'])) $c['name'] = $c['company'];
                        elseif (!empty($c['emails'])) $c['name'] = explode('@', $c['emails'][0]['val'])[0];
                    }

                    $exists = false;
                    foreach ($contacts as $existing) {
                        $eList = isset($existing['emails']) && is_array($existing['emails']) ? $existing['emails'] : [];
                        if (!empty($c['emails']) && !empty($eList) && $eList[0]['val'] === $c['emails'][0]['val']) {
                            $exists = true; break;
                        }
                        $pList = isset($existing['phones']) && is_array($existing['phones']) ? $existing['phones'] : [];
                        if (empty($c['emails']) && !empty($c['phones']) && !empty($pList) && $existing['name'] === $c['name'] && $pList[0]['val'] === $c['phones'][0]['val']) {
                            $exists = true; break;
                        }
                    }
                    
                    if (!$exists) {
                        $c['id'] = uniqid('cnt_');
                        foreach (['name', 'first_name', 'last_name', 'company', 'job_title', 'address', 'website', 'notes', 'labels'] as $k) {
                            $c[$k] = mb_convert_encoding($c[$k] ?? '', 'UTF-8', 'UTF-8');
                        }
                        $contacts[] = $c;
                        $importedCount++;
                    }
                };

                // Normalize structural line-folding (CRLF + Space/Tab) standard in VCF and LDIF
                $fileContentUnfolded = preg_replace('/(\r?\n)[ \t]+/', '', $fileContent);

                // --- 1. VCARD PARSER (.vcf / .vcard) ---
                if (in_array($ext, ['vcf', 'vcard']) || stripos($fileContent, 'BEGIN:VCARD') !== false) {
                    $blocks = preg_split('/BEGIN:VCARD/i', $fileContentUnfolded);
                    foreach ($blocks as $block) {
                        if (empty(trim($block))) continue;
                        $c = ['name'=>'', 'first_name'=>'', 'last_name'=>'', 'emails'=>[], 'phones'=>[], 'company'=>'', 'job_title'=>'', 'address'=>'', 'website'=>'', 'labels'=>'', 'notes'=>''];
                        
                        $decodeVal = function($val, $params) {
                            if (stripos($params, 'QUOTED-PRINTABLE') !== false) $val = quoted_printable_decode($val);
                            return trim($val);
                        };

                        $lines = explode("\n", str_replace("\r", "", $block));
                        foreach ($lines as $line) {
                            if (!preg_match('/^([A-Z0-9\-]+)((?:;[^:]*)?):(.*)$/i', $line, $m)) continue;
                            $key = strtoupper($m[1]); $params = $m[2]; $val = $decodeVal($m[3], $params);
                            
                            if ($key === 'FN') $c['name'] = $val;
                            elseif ($key === 'N') {
                                $p = explode(';', $val);
                                $c['last_name'] = $p[0] ?? ''; $c['first_name'] = $p[1] ?? '';
                            }
                            elseif ($key === 'ORG') $c['company'] = str_replace(';', ' ', $val);
                            elseif ($key === 'TITLE') $c['job_title'] = $val;
                            elseif ($key === 'NOTE') $c['notes'] = str_replace(['\n', '\N'], "\n", $val);
                            elseif ($key === 'URL') $c['website'] = $val;
                            elseif ($key === 'CATEGORIES') $c['labels'] = $val;
                            elseif ($key === 'EMAIL') {
                                $type = (preg_match('/TYPE=([^;:]+)/i', $params, $tm) || preg_match('/;(HOME|WORK|CELL|PREF)[;:]/i', $params, $tm)) ? ucfirst(strtolower(trim($tm[1], '"\''))) : 'Work';
                                if (filter_var($val, FILTER_VALIDATE_EMAIL)) $c['emails'][] = ['type' => $type, 'val' => strtolower($val)];
                            }
                            elseif ($key === 'TEL') {
                                $type = (preg_match('/TYPE=([^;:]+)/i', $params, $tm) || preg_match('/;(HOME|WORK|CELL|VOICE|FAX)[;:]/i', $params, $tm)) ? ucfirst(strtolower(trim($tm[1], '"\''))) : 'Mobile';
                                $c['phones'][] = ['type' => $type, 'val' => $val];
                            }
                            elseif ($key === 'ADR') {
                                // vCard ADR format: PO Box; Extended; Street; Locality(City); Region(State); PostalCode; Country
                                $p = explode(';', $val);
                                $c['address'] = $buildAddress($p[2] ?? '', $p[3] ?? '', $p[4] ?? '', $p[5] ?? '', $p[6] ?? '');
                            }
                        }
                        $addParsedContact($c);
                    }
                    $this->saveContacts($contacts);
                    $this->sendJsonAndExit(['status' => 'OK', 'imported' => $importedCount]);
                }
                
                // --- 2. LDIF PARSER (.ldif) ---
                if ($ext === 'ldif' || stripos($fileContent, 'dn: ') !== false) {
                    $blocks = preg_split('/(?:\r?\n){2,}/', $fileContentUnfolded);
                    
                    $getLdifVals = function($key, $block) {
                        // Defeat ReDoS. Eliminate unbounded whitespace lookaheads.
                        preg_match_all('/^' . $key . '::[ \t]*(.*)$/im', $block, $mB64);
                        preg_match_all('/^' . $key . ':[ \t]*(.*)$/im', $block, $mStr);
                        return array_merge(array_map(function($v){ return base64_decode(trim($v)); }, $mB64[1]), array_map('trim', $mStr[1]));
                    };
                    $getLdifVal = function($key, $block) use ($getLdifVals) {
                        $res = $getLdifVals($key, $block);
                        return !empty($res) ? $res[0] : '';
                    };

                    foreach ($blocks as $block) {
                        if (empty(trim($block))) continue;
                        $c = ['name'=>'', 'first_name'=>'', 'last_name'=>'', 'emails'=>[], 'phones'=>[], 'company'=>'', 'job_title'=>'', 'address'=>'', 'website'=>'', 'labels'=>'', 'notes'=>''];
                        
                        $c['name'] = $getLdifVal('cn', $block);
                        $c['first_name'] = $getLdifVal('givenName', $block);
                        $c['last_name'] = $getLdifVal('sn', $block);
                        $c['company'] = $getLdifVal('o', $block) ?: $getLdifVal('company', $block);
                        $c['job_title'] = $getLdifVal('title', $block);
                        $c['notes'] = $getLdifVal('description', $block);
                        $c['website'] = $getLdifVal('labeledURI', $block) ?: $getLdifVal('website', $block);
                        
                        $c['address'] = $buildAddress($getLdifVal('street', $block), $getLdifVal('l', $block), $getLdifVal('st', $block), $getLdifVal('postalCode', $block), $getLdifVal('c', $block));
                        
                        foreach ($getLdifVals('mail', $block) as $em) {
                            $em = strtolower($em);
                            if (filter_var($em, FILTER_VALIDATE_EMAIL)) $c['emails'][] = ['type' => 'Work', 'val' => $em];
                        }
                        foreach ($getLdifVals('mobile', $block) as $ph) $c['phones'][] = ['type' => 'Mobile', 'val' => $ph];
                        foreach ($getLdifVals('telephoneNumber', $block) as $ph) $c['phones'][] = ['type' => 'Work', 'val' => $ph];
                        foreach ($getLdifVals('homePhone', $block) as $ph) $c['phones'][] = ['type' => 'Home', 'val' => $ph];
                        
                        $addParsedContact($c);
                    }
                    $this->saveContacts($contacts);
                    $this->sendJsonAndExit(['status' => 'OK', 'imported' => $importedCount]);
                }

                // --- 3. ORIGINAL CSV PARSER (Untouched structural extraction logic) ---
                ini_set('auto_detect_line_endings', true);
                $handle = fopen($_FILES['file']['tmp_name'], "r");
                if (!$handle) $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Could not read file.']);
                
                $header = fgetcsv($handle, 10000, ",");
                $delimiter = ",";
                if (count($header) <= 1) {
                    rewind($handle);
                    $header = fgetcsv($handle, 10000, ";");
                    $delimiter = ";";
                }
                
                $hMap = [
                    'name' => -1, 'first' => -1, 'middle' => -1, 'last' => -1, 'prefix' => -1, 'suffix' => -1,
                    'company' => -1, 'job' => -1, 'notes' => -1, 'website' => -1, 'labels' => -1,
                    'addr_fmt' => -1, 'addr_street' => -1, 'addr_city' => -1, 'addr_region' => -1, 'addr_zip' => -1, 'addr_country' => -1
                ];
                
                $emailCols = [];
                $phoneCols = [];

                foreach ($header as $i => $col) {
                    $c = strtolower(trim(str_replace("\xef\xbb\xbf", '', $col)));
                    
                    if (in_array($c, ['name', 'display name', 'full name'])) $hMap['name'] = $i;
                    if (in_array($c, ['first name', 'given name'])) $hMap['first'] = $i;
                    if (in_array($c, ['middle name', 'additional name'])) $hMap['middle'] = $i;
                    if (in_array($c, ['last name', 'family name'])) $hMap['last'] = $i;
                    if (in_array($c, ['name prefix', 'title'])) $hMap['prefix'] = $i;
                    if (in_array($c, ['name suffix', 'suffix'])) $hMap['suffix'] = $i;
                    
                    if (preg_match('/e-mail (\d+) - value/', $c, $m) || preg_match('/email (\d+)/', $c, $m)) {
                        $emailCols[$m[1]]['val_idx'] = $i;
                    } elseif (preg_match('/e-mail (\d+) - type/', $c, $m)) {
                        $emailCols[$m[1]]['type_idx'] = $i;
                    } elseif (in_array($c, ['email', 'primary email', 'e-mail-adresse', 'correo electrónico'])) {
                        $emailCols['primary']['val_idx'] = $i;
                    }
                    
                    if (preg_match('/phone (\d+) - value/', $c, $m) || preg_match('/phone (\d+)/', $c, $m)) {
                        $phoneCols[$m[1]]['val_idx'] = $i;
                    } elseif (preg_match('/phone (\d+) - type/', $c, $m)) {
                        $phoneCols[$m[1]]['type_idx'] = $i;
                    } elseif (in_array($c, ['mobile phone', 'primary phone', 'phone', 'telefon', 'mobiltelefon', 'teléfono'])) {
                        $phoneCols['primary']['val_idx'] = $i;
                    } elseif (in_array($c, ['business phone', 'home phone', 'telefon (geschäftlich)'])) {
                        $phoneCols['secondary']['val_idx'] = $i;
                    }
                    
                    if (in_array($c, ['organization 1 - name', 'company', 'firma'])) $hMap['company'] = $i;
                    if (in_array($c, ['organization 1 - title', 'job title', 'position'])) $hMap['job'] = $i;
                    if (in_array($c, ['notes', 'description', 'notizen'])) $hMap['notes'] = $i;
                    if (in_array($c, ['website 1 - value', 'website'])) $hMap['website'] = $i;
                    if (in_array($c, ['group membership', 'labels', 'categories'])) $hMap['labels'] = $i;
                    
                    if (in_array($c, ['address 1 - formatted', 'business address', 'home address', 'address'])) $hMap['addr_fmt'] = $i;
                    if (in_array($c, ['address 1 - street', 'business street'])) $hMap['addr_street'] = $i;
                    if (in_array($c, ['address 1 - city', 'business city'])) $hMap['addr_city'] = $i;
                    if (in_array($c, ['address 1 - region', 'business state'])) $hMap['addr_region'] = $i;
                    if (in_array($c, ['address 1 - postal code', 'business postal code', 'plz'])) $hMap['addr_zip'] = $i;
                    if (in_array($c, ['address 1 - country', 'business country', 'land'])) $hMap['addr_country'] = $i;
                }
                
                while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
                    if (empty($row) || (count($row) === 1 && empty($row[0]))) continue;

                    $getArr = function($key) use ($row, $hMap) {
                        $idx = $hMap[$key];
                        if ($idx === -1 || !isset($row[$idx])) return [];
                        $val = trim($row[$idx]);
                        if ($val === '') return [];
                        return array_filter(array_map('trim', explode(':::', $val)));
                    };

                    $first = $getArr('first')[0] ?? '';
                    $middle = $getArr('middle')[0] ?? '';
                    $last = $getArr('last')[0] ?? '';
                    $prefix = $getArr('prefix')[0] ?? '';
                    $suffix = $getArr('suffix')[0] ?? '';
                    $company = $getArr('company')[0] ?? '';

                    $emails = [];
                    foreach ($emailCols as $ec) {
                        $valIdx = $ec['val_idx'] ?? -1;
                        $typeIdx = $ec['type_idx'] ?? -1;
                        if ($valIdx !== -1 && !empty($row[$valIdx])) {
                            $typeStr = ($typeIdx !== -1 && !empty($row[$typeIdx])) ? preg_replace('/^\*\s*/', '', trim($row[$typeIdx])) : 'Work';
                            $vals = array_filter(array_map('trim', explode(':::', $row[$valIdx])));
                            foreach ($vals as $v) {
                                $v = strtolower($v);
                                if (filter_var($v, FILTER_VALIDATE_EMAIL)) $emails[] = ['type' => $typeStr, 'val' => $v];
                            }
                        }
                    }
                    $uniqueEmails = []; $seenE = [];
                    foreach ($emails as $e) { if (!in_array($e['val'], $seenE)) { $seenE[] = $e['val']; $uniqueEmails[] = $e; } }

                    $phones = [];
                    foreach ($phoneCols as $pc) {
                        $valIdx = $pc['val_idx'] ?? -1;
                        $typeIdx = $pc['type_idx'] ?? -1;
                        if ($valIdx !== -1 && !empty($row[$valIdx])) {
                            $typeStr = ($typeIdx !== -1 && !empty($row[$typeIdx])) ? preg_replace('/^\*\s*/', '', trim($row[$typeIdx])) : 'Mobile';
                            $vals = array_filter(array_map('trim', explode(':::', $row[$valIdx])));
                            foreach ($vals as $v) { $phones[] = ['type' => $typeStr, 'val' => $v]; }
                        }
                    }
                    $uniquePhones = []; $seenP = [];
                    foreach ($phones as $p) { if (!in_array($p['val'], $seenP)) { $seenP[] = $p['val']; $uniquePhones[] = $p; } }

                    $labelsArr = [];
                    foreach ($getArr('labels') as $lbl) { if ($lbl && $lbl !== '* myContacts') $labelsArr[] = $lbl; }

                    $addParsedContact([
                        'name' => $getArr('name')[0] ?? '', 'first_name' => $first, 'last_name' => $last,
                        'emails' => $uniqueEmails, 'phones' => $uniquePhones, 'company' => $company,
                        'job_title' => $getArr('job')[0] ?? '', 
                        'address' => $buildAddress($getArr('addr_street')[0] ?? '', $getArr('addr_city')[0] ?? '', $getArr('addr_region')[0] ?? '', $getArr('addr_zip')[0] ?? '', $getArr('addr_country')[0] ?? '', $getArr('addr_fmt')[0] ?? ''), 
                        'website' => $getArr('website')[0] ?? '', 'labels' => implode(', ', $labelsArr), 'notes' => $getArr('notes')[0] ?? ''
                    ]);
                }
                
                fclose($handle);
                $this->saveContacts($contacts);
                $this->sendJsonAndExit(['status' => 'OK', 'imported' => $importedCount]);
                break;

            case 'email_get_tree_favs':
                $favs = [];
                if (file_exists($this->tree_favs_file)) {
                    $favs = json_decode(file_get_contents($this->tree_favs_file), true) ?: [];
                }
                $this->sendJsonAndExit(['status' => 'OK', 'favorites' => $favs]);
                break;

            case 'email_save_tree_favs':
                $favs = json_decode($_POST['favorites'] ?? '[]', true);
                if (is_array($favs)) {
                    file_put_contents($this->tree_favs_file, json_encode($favs, JSON_PRETTY_PRINT));
                }
                $this->sendJsonAndExit(['status' => 'OK']);
                break;

            case 'email_export_contacts':
                if (!$this->actionAllowed('email_import_contacts')) $this->sendJsonAndExit(['status'=>'ERR', 'msg'=>'Action denied: Importing contacts is disabled.']);
                $contacts = $this->loadContacts();
                
                $maxE = 1; $maxP = 1;
                foreach ($contacts as $c) {
                    $eC = isset($c['emails']) && is_array($c['emails']) ? count($c['emails']) : 0;
                    $pC = isset($c['phones']) && is_array($c['phones']) ? count($c['phones']) : 0;
                    if ($eC > $maxE) $maxE = $eC;
                    if ($pC > $maxP) $maxP = $pC;
                }
                
                $headerCols = ["Name", "Given Name", "Family Name"];
                for ($i=1; $i<=$maxE; $i++) { $headerCols[] = "E-mail $i - Type"; $headerCols[] = "E-mail $i - Value"; }
                for ($i=1; $i<=$maxP; $i++) { $headerCols[] = "Phone $i - Type"; $headerCols[] = "Phone $i - Value"; }
                $headerCols = array_merge($headerCols, ["Organization 1 - Name", "Organization 1 - Title", "Address 1 - Formatted", "Website 1 - Value", "Group Membership", "Notes"]);
                
                $csv = implode(',', array_map(function($v) { return '"' . str_replace('"', '""', $v) . '"'; }, $headerCols)) . "\n";
                
                foreach ($contacts as $c) {
                    $row = [ $c['name'] ?? '', $c['first_name'] ?? '', $c['last_name'] ?? '' ];
                    
                    $eList = isset($c['emails']) && is_array($c['emails']) ? $c['emails'] : [];
                    for ($i=0; $i<$maxE; $i++) {
                        if (isset($eList[$i])) { $row[] = $eList[$i]['type']; $row[] = $eList[$i]['val']; } 
                        else { $row[] = ''; $row[] = ''; }
                    }
                    
                    $pList = isset($c['phones']) && is_array($c['phones']) ? $c['phones'] : [];
                    for ($i=0; $i<$maxP; $i++) {
                        if (isset($pList[$i])) { $row[] = $pList[$i]['type']; $row[] = $pList[$i]['val']; } 
                        else { $row[] = ''; $row[] = ''; }
                    }
                    
                    $row[] = $c['company'] ?? '';
                    $row[] = $c['job_title'] ?? '';
                    $row[] = $c['address'] ?? '';
                    $row[] = $c['website'] ?? '';
                    $row[] = $c['labels'] ?? '';
                    $row[] = $c['notes'] ?? '';
                    
                    $escaped = array_map(function($v) {
                        $v = (string)$v;
                        // Security #3: Prevent CSV Injection (Macrovirus execution)
                        if (preg_match('/^[=\-+@\t\r]/', $v)) {
                            $v = "'" . $v;
                        }
                        return '"' . str_replace('"', '""', $v) . '"';
                    }, $row);
                    $csv .= implode(',', $escaped) . "\n";
                }
                $this->sendJsonAndExit(['status' => 'OK', 'csv' => $csv]);
                break;
            
            default:
                $this->sendJsonAndExit(['status' => 'ERR', 'msg' => 'Unknown action']);
        }
    }
}

// --- ALLOW CLI WORKER EXECUTION ---
// If the background daemon or cronjob spawned this process, override the session username
if (php_sapi_name() === 'cli' && isset($_SERVER['argv'][1])) {
    parse_str($_SERVER['argv'][1], $cliArgs);
    if (!empty($cliArgs['myCloud_cli_user']) && !empty($cliArgs['myCloud_action'])) {
        $username = $cliArgs['myCloud_cli_user'];
        $action = $cliArgs['myCloud_action'];
        $key = hash('sha256', 'CLI_BACKGROUND_WORKER', true); // Bypass auth for internal isolated cron
        
        $emailServer = new MyCloudEmailServer($key, $username);
        $emailServer->handleRequest($action);
        exit(0);
    }
}