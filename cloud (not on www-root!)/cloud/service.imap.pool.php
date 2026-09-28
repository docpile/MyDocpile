<?php
/**
 * MyDocpile Zero-Trust Persistent IMAP Connection Pooler
 * Usage: php service.imap.pool.php
 */

if (php_sapi_name() !== 'cli') die("Must run in CLI environment.");

// --- SYSTEMD SERVICE MANAGER ---
if (in_array('--install', $argv) || in_array('--remove', $argv)) {
    if (posix_getuid() !== 0) die("Error: --install and --remove require root privileges (sudo).\n");
    
    $serviceName = 'mydocpile-imap-pool.service';
    $servicePath = "/etc/systemd/system/{$serviceName}";
    
    if (in_array('--remove', $argv)) {
        echo "Stopping and removing service...\n";
        exec("systemctl stop {$serviceName} 2>/dev/null");
        exec("systemctl disable {$serviceName} 2>/dev/null");
        if (file_exists($servicePath)) unlink($servicePath);
        exec("systemctl daemon-reload");
        echo "Service removed successfully.\n";
        exit(0);
    }
    
    if (in_array('--install', $argv)) {
        $phpBin = PHP_BINARY;
        $scriptPath = realpath(__FILE__);
        
        // Auto-detect web server user to ensure the 0600 token file is readable by PHP-FPM
        $runAsUser = 'www-data';
        foreach (['www-data', 'apache', 'nginx', 'nobody'] as $u) {
            if (posix_getpwnam($u)) { $runAsUser = $u; break; }
        }
        foreach ($argv as $arg) { if (strpos($arg, '--user=') === 0) $runAsUser = substr($arg, 7); }
        
        echo "Installing Systemd service (Running strictly as: $runAsUser)...\n";
        
        $serviceConfig = "[Unit]\nDescription=MyDocpile Zero-Trust IMAP Connection Pooler\nAfter=network.target\n\n"
                       . "[Service]\nType=simple\nUser={$runAsUser}\nExecStart={$phpBin} {$scriptPath}\n"
                       . "Restart=always\nRestartSec=3\n\n[Install]\nWantedBy=multi-user.target\n";
                       
        file_put_contents($servicePath, $serviceConfig);
        exec("systemctl daemon-reload");
        exec("systemctl enable {$serviceName}");
        exec("systemctl start {$serviceName}");
        
        echo "Service installed and started successfully from current filepath.\nCheck status with: systemctl status {$serviceName}\n";
        exit(0);
    }
}

$port = 21143;
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if (!$server) die("Failed to bind proxy server: $errstr\n");
stream_set_blocking($server, false);

// 1. Generate Zero-Trust Cryptographic Token
$masterToken = bin2hex(random_bytes(32));

// FIX: Move token into the shared data directory to bypass PrivateTmp isolation
$tokenDir = __DIR__ . '/../data';
if (!is_dir($tokenDir)) @mkdir($tokenDir, 0770, true);
$tokenFile = $tokenDir . '/.imap_pool_' . getmyuid() . '.token';

if (file_put_contents($tokenFile, $masterToken) === false) {
    die("Failed to write security token.\n");
}
// Ensure only the webserver user can read the token
chmod($tokenFile, 0600);

// Cleanup token file on exit
register_shutdown_function(function() use ($tokenFile) { @unlink($tokenFile); });
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() { exit; });
    pcntl_signal(SIGTERM, function() { exit; });
}

echo "MyDocpile IMAP Pooler running securely on 127.0.0.1:$port\n";

$clients = [];
$upstreams = [];
$idlePool = [];

while (true) {
    $now = time();
    static $lastSweep = 0;
    
    // --- GARBAGE COLLECTION (TTL) ---
    if ($now - $lastSweep > 10) { // Sweep every 10 seconds
        $lastSweep = $now;
        foreach ($idlePool as $key => &$pool) {
            foreach ($pool as $idx => $uData) {
                if ($now - $uData['time'] > 240) { // 4 minutes idle TTL
                    echo "[" . date('Y-m-d H:i:s') . "] CLOSED CONNECTION: Idle TTL expired (4 min).\n";
                    if (is_resource($uData['res'])) {
                        @fwrite($uData['res'], "P999 LOGOUT\r\n");
                        @fclose($uData['res']);
                    }
                    unset($pool[$idx]);
                }
            }
            $pool = array_values($pool);
        }
        unset($pool);
    }

    $read = [$server];
    foreach ($clients as $id => $c) {
        if (is_resource($c['res'])) $read[] = $c['res'];
        else unset($clients[$id]);
    }
    foreach ($upstreams as $id => $u) {
        if (is_resource($u['res'])) $read[] = $u['res'];
        else unset($upstreams[$id]);
    }
    
    $write = null;
    $except = null;
    
    // FIX: Bulletproof suppression of the EINTR OS signal warning
    set_error_handler(function() {});
    $sel = stream_select($read, $write, $except, 1);
    restore_error_handler();
    
    if ($sel === false) {
        continue;
    }
    
    // Accept internal connections
    if (in_array($server, $read, true)) {
        $client = @stream_socket_accept($server);
        if ($client) {
            stream_set_blocking($client, false);
            // Instantly bypass standard IMAP Greeting
            fwrite($client, "* OK [CAPABILITY IMAP4rev1] PHP Pooler Ready\r\n");
            $clients[(int)$client] = ['res' => $client, 'state' => 'PRE_LOGIN', 'upstream' => null];
        }
    }
    
    // Process Client -> Upstream Traffic
    foreach ($clients as $id => &$c) {
        if (is_resource($c['res']) && in_array($c['res'], $read, true)) {
            $data = @fread($c['res'], 8192);
            if ($data === false || $data === '') {
                // If the frontend drops the connection prematurely, stash the upstream
                if ($c['upstream']) {
                    if (!isset($idlePool[$c['poolKey']])) $idlePool[$c['poolKey']] = [];
                    if (count($idlePool[$c['poolKey']]) < 3) { // Max 3 idle connections per account
                        $idlePool[$c['poolKey']][] = ['res' => $c['upstream'], 'time' => time()];
                    } else {
                        echo "[" . date('Y-m-d H:i:s') . "] CLOSED CONNECTION: Pool capacity full on client drop.\n";
                        if (is_resource($c['upstream'])) {
                            @fwrite($c['upstream'], "P999 LOGOUT\r\n");
                            @fclose($c['upstream']);
                        }
                    }
                    unset($upstreams[(int)$c['upstream']]);
                }
                @fclose($c['res']);
                unset($clients[$id]);
                continue;
            }
            
            if ($c['state'] === 'PRE_LOGIN') {
                $lines = explode("\r\n", trim($data));
                foreach ($lines as $line) {
                    if (preg_match('/^([A-Za-z0-9]+)\s+CAPABILITY/i', $line, $m)) {
                        fwrite($c['res'], "* CAPABILITY IMAP4rev1 LITERAL+ IDLE\r\n{$m[1]} OK CAPABILITY completed\r\n");
                    } elseif (preg_match('/^([A-Za-z0-9]+)\s+LOGIN\s+"([^"]+)"/i', $line, $m)) {
                        $tag = $m[1];
                        $payload = json_decode(base64_decode($m[2]), true);
                        
                        // ZERO TRUST: Validate Cryptographic Token instantly
                        if (!$payload || !isset($payload['t']) || !hash_equals($masterToken, $payload['t'])) {
                            fwrite($c['res'], "{$tag} NO Invalid Security Token\r\n");
                            @fclose($c['res']);
                            unset($clients[$id]);
                            continue 2;
                        }

                        // Strictly isolate connection pools by exact upstream credentials
                        $c['poolKey'] = hash('sha256', $payload['h'] . $payload['pt'] . $payload['u'] . $payload['p']);
                        $upstream = null;
                        
                        // Attempt to resurrect an idle connection
                        if (!empty($idlePool[$c['poolKey']])) {
                            while ($uData = array_pop($idlePool[$c['poolKey']])) {
                                if (is_resource($uData['res']) && !feof($uData['res'])) { 
                                    $upstream = $uData['res']; 
                                    echo "[" . date('Y-m-d H:i:s') . "] REUSED CONNECTION: " . $payload['u'] . " (from idle pool)\n";
                                    break; 
                                }
                                if (is_resource($uData['res'])) @fclose($uData['res']);
                            }
                        }
                        
                        // Negotiate a fresh connection if pool is empty
                        if (!$upstream) {
                            $trans = $payload['e'] === 'ssl' ? 'ssl://' : 'tcp://';
                            $target = $trans . $payload['h'] . ':' . $payload['pt'];
                            $upstream = @stream_socket_client($target, $ue, $us, 5);
                            
                            if ($upstream) {
                                stream_set_blocking($upstream, true);
                                fgets($upstream); // Absorb real greeting
                                $pwdEsc = str_replace('"', '\"', $payload['p']);
                                fwrite($upstream, "A001 LOGIN \"{$payload['u']}\" \"{$pwdEsc}\"\r\n");
                                while ($resp = fgets($upstream)) {
                                    if (preg_match('/^A001\s+(OK|NO|BAD)/i', $resp, $rm)) {
                                        if (strtoupper($rm[1]) !== 'OK') {
                                            @fclose($upstream);
                                            $upstream = null;
                                        }
                                        break;
                                    }
                                }
                                if (is_resource($upstream)) stream_set_blocking($upstream, false);
                                
                                if ($upstream) {
                                    echo "[" . date('Y-m-d H:i:s') . "] NEW CONNECTION: Authenticated " . $payload['u'] . " to " . $target . "\n";
                                }
                            }
                        }
                        
                        if ($upstream) {
                            $c['upstream'] = $upstream;
                            $c['state'] = 'PROXYING';
                            $upstreams[(int)$upstream] = ['res' => $upstream, 'client' => $c['res']];
                            fwrite($c['res'], "{$tag} OK LOGIN completed\r\n");
                        } else {
                            fwrite($c['res'], "{$tag} NO Upstream LOGIN failed\r\n");
                        }
                    }
                }
            } elseif ($c['state'] === 'PROXYING') {
                // Intercept the teardown to hoard the active connection
                if (stripos($data, 'LOGOUT') !== false && preg_match('/([A-Za-z0-9]+)\s+LOGOUT/i', $data, $m)) {
                    fwrite($c['res'], "* BYE IMAP4rev1 Proxy disconnecting\r\n{$m[1]} OK LOGOUT completed\r\n");
                    
                    if (!isset($idlePool[$c['poolKey']])) $idlePool[$c['poolKey']] = [];
                    if (count($idlePool[$c['poolKey']]) < 3) { // Max 3 idle connections per account
                        $idlePool[$c['poolKey']][] = ['res' => $c['upstream'], 'time' => time()];
                    } else {
                        echo "[" . date('Y-m-d H:i:s') . "] CLOSED CONNECTION: Pool capacity full on explicit LOGOUT.\n";
                        if (is_resource($c['upstream'])) {
                            @fwrite($c['upstream'], "P999 LOGOUT\r\n");
                            @fclose($c['upstream']);
                        }
                    }
                    
                    unset($upstreams[(int)$c['upstream']]);
                    @fclose($c['res']);
                    unset($clients[$id]);
                } else {
                    if (is_resource($c['upstream'])) fwrite($c['upstream'], $data);
                }
            }
        }
    }
    
    // Process Upstream -> Client Traffic
    foreach ($upstreams as $id => $u) {
        if (is_resource($u['res']) && in_array($u['res'], $read, true)) {
            $data = @fread($u['res'], 8192);
            if ($data === false || $data === '') {
                echo "[" . date('Y-m-d H:i:s') . "] CLOSED CONNECTION: Upstream IMAP server closed the socket.\n";
                @fclose($u['res']);
                if (isset($u['client']) && is_resource($u['client'])) @fclose($u['client']);
                unset($clients[(int)$u['client']]);
                unset($upstreams[$id]);
            } else {
                if (isset($u['client']) && is_resource($u['client'])) fwrite($u['client'], $data);
            }
        }
    }
}