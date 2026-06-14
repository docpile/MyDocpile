<?php
/**
 * Secure WebDAV Server - Production Hardened Level 6 (FINAL)
 * ---------------------------------------------------------
 * - SECURITY: Brute-Force Protection (Early Block & Auth-Gap Trap)
 * - SECURITY: Anti-Enumeration (Uniform 401 Responses)
 * - SECURITY: Strict Input Sanitization (Username Whitelist)
 * - HARDENING: Custom Filesystem Node (Upload Quotas & Filename Limits)
 * - HARDENING: Realpath Jailing (Anti-Traversal)
 * - PERFORMANCE: RAM-Based HMAC Credential Cache (/dev/shm)
 * - PERFORMANCE: Probabilistic Garbage Collection
 * - PERFORMANCE: Dynamic Execution Timeouts (Upload vs. Browse)
 */

// -------------------------------------------------------------------------
// 0. RUNTIME GOVERNANCE
// -------------------------------------------------------------------------
//ini_set('memory_limit', '256M'); 

// -------------------------------------------------------------------------
// 1. CONFIGURATION & LOGGER PRE-LOAD
// -------------------------------------------------------------------------
$work_dir = __dir__ . '/..';

require_once $work_dir . '/vendor/autoload.php';
require_once $work_dir . '/configuration/config.dist.php'; 
require_once $work_dir . '/configuration/config.php'; 
require_once $work_dir . '/configuration/users.php'; 


$webdav_sec_config = [
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
	'work_dir'      => $work_dir
];
$webdav_sec_config['http_checks']['allow_methods'] = ['OPTIONS', 'GET', 'HEAD', 'DELETE', 'PROPFIND', 'MKCOL', 'PUT', 'PROPPATCH', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
$webdav_sec_config['rate_limit']['max_requests'] = 10000;
$webdav_sec_config['rate_limit']['window'] = 60*2;


class DavLogger {
    const LEVEL_OFF = 0;
    const LEVEL_ERROR = 1;
    const LEVEL_LOGIN = 2;

    private $file, $level;

    public function __construct($file, $level) {
        $this->file = $file;
        $this->level = $level;
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0750, true);
    }

    public function log($level, $message) {
        if ($level > $this->level) return;
        $lvlStr = ($level === self::LEVEL_ERROR) ? 'ERROR' : 'LOGIN';
        $date = date('Y-m-d H:i:s');
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user = preg_replace('/[^a-zA-Z0-9\-\_\.\@]/', '', $_SERVER['PHP_AUTH_USER'] ?? '-');
        @file_put_contents($this->file, "[$date] [$lvlStr] [$ip] [$user] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

$logFile  = $work_dir . '/lists/webdav_log.txt';
$logLevel = $webdav_log_level ?? DavLogger::LEVEL_LOGIN; 
$logger   = new DavLogger($logFile, $logLevel);

// -------------------------------------------------------------------------
// 2. BRUTE FORCE PROTECTION (EARLY INIT)
// -------------------------------------------------------------------------
class BruteForceProtector {
    private $file, $maxFailures, $baseBlockTime, $window, $factor;
    private $logger, $subnetLimit, $globalLimit; 

    public function __construct($file, $maxFailures, $baseBlockTime, $window, $factor, DavLogger $logger) {
        $this->file = $file;
        $this->maxFailures = $maxFailures;
        $this->baseBlockTime = $baseBlockTime;
        $this->window = $window;
        $this->factor = $factor;
        $this->logger = $logger;
        $this->subnetLimit = $maxFailures * 4; 
        $this->globalLimit = 100; 
    }

    private function getSubnet($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed === false) return $ip; 
            return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
        }
        return (strpos($ip, '.') !== false) ? substr($ip, 0, strrpos($ip, '.')) : $ip;
    }

    private function updateData(callable $callback) {
        $fp = fopen($this->file, 'c+'); 
        if (!$fp) return ['panic' => false]; 

        $result = null;
        if (flock($fp, LOCK_EX)) {
            $stats = fstat($fp);
            $size = $stats['size'];
            if ($size > 0) {
                rewind($fp);
                $content = fread($fp, $size);
                $data = json_decode($content, true) ?: [];
            } else {
                $data = [];
            }
            
            foreach (['ips', 'users', 'subnets', 'global'] as $k) {
                if (!isset($data[$k])) $data[$k] = [];
            }

            $result = $callback($data);

            if ($result !== null) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($result));
                fflush($fp);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $result; 
    }

    private function readData() {
        if (!file_exists($this->file)) return [];
        $fp = fopen($this->file, 'r');
        $data = [];
        if ($fp && flock($fp, LOCK_SH)) {
            $stats = fstat($fp);
            if ($stats['size'] > 0) {
                $data = json_decode(fread($fp, $stats['size']), true) ?: [];
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $data;
    }

    public function checkAccess($ip, $user = null) {
        $data = $this->readData();
        $subnet = $this->getSubnet($ip);
        $now = time();

        if (isset($data['global']['panic_until']) && $data['global']['panic_until'] > $now) {
            usleep(mt_rand(100000, 300000));
            return 'Global panic'; 
        }

        if (isset($data['ips'][$ip]['blocked_until']) && $data['ips'][$ip]['blocked_until'] > $now) return 'IP blocked';
        if (isset($data['subnets'][$subnet]['blocked_until']) && $data['subnets'][$subnet]['blocked_until'] > $now) return 'Subnet blocked';
        
        if ($user && isset($data['users'][$user]['blocked_until']) && $data['users'][$user]['blocked_until'] > $now) return 'User blocked';

        return true;
    }

    public function registerFail($ip, $user) {
        $this->updateData(function($data) use ($ip, $user) {
            $subnet = $this->getSubnet($ip);
            $now = time();

            $increment = function(&$bucket, $key, $limit, $isGlobal = false) use ($now) {
                if (!isset($bucket[$key])) $bucket[$key] = ['count'=>0, 'blocked_until'=>0, 'last_block_end'=>0, 'last_duration'=>0];
                
                if (($now - ($bucket[$key]['last_attempt'] ?? 0)) > $this->window) $bucket[$key]['count'] = 0;
                
                $bucket[$key]['count']++;
                $bucket[$key]['last_attempt'] = $now;

                if ($bucket[$key]['count'] >= $limit) {
                    if ($isGlobal) {
                         $bucket[$key]['panic_until'] = $now + 60; 
                         return 'PANIC';
                    } else {
                        $dur = ($now - $bucket[$key]['last_block_end'] < $this->window) 
                             ? ($bucket[$key]['last_duration'] ?: $this->baseBlockTime) * $this->factor 
                             : $this->baseBlockTime;
                        
                        $bucket[$key]['blocked_until']  = $now + $dur;
                        $bucket[$key]['last_block_end'] = $now + $dur;
                        $bucket[$key]['last_duration']  = $dur;
                        $bucket[$key]['count'] = 0; 
                        return $dur;
                    }
                }
                return false;
            };

            $panic = $increment($data, 'global', $this->globalLimit, true);
            if ($panic) $this->logger->log(DavLogger::LEVEL_LOGIN, "GLOBAL PANIC ACTIVATED");

            $ipB = $increment($data['ips'], $ip, $this->maxFailures);
            if ($ipB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT IP [$ip]: {$ipB}s");

            $subB = $increment($data['subnets'], $subnet, $this->subnetLimit);
            if ($subB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT SUBNET [$subnet]: {$subB}s");

            if ($user && $user !== 'no_auth_user') {
                $usrB = $increment($data['users'], $user, $this->maxFailures * 2);
                if ($usrB) $this->logger->log(DavLogger::LEVEL_LOGIN, "LOCKOUT USER [$user]: {$usrB}s");
            }

            return $data;
        });
    }
}

// [PERFORMANCE] RAM-based tracking prevents flock() from bottlenecking concurrent syncs
$bf_file   = '/dev/shm/dav_protection_' . substr(md5($work_dir), 0, 8) . '.json';
$bf_limit  = $login_failures ?? 5;
$bf_base   = $login_block_seconds ?? 60;
$bf_window = $brute_force_window ?? 3600;
$bf_factor = $brute_force_factor ?? 2;

$protector = new BruteForceProtector($bf_file, $bf_limit, $bf_base, $bf_window, $bf_factor, $logger);

// BLOCK CHECK: Early Rejection
$ip = $_SERVER['REMOTE_ADDR'];
if ($protector->checkAccess($ip, $_SERVER['PHP_AUTH_USER'] ?? null) !== true) {
    http_response_code(403); die();
}

// -------------------------------------------------------------------------
// 3. SECURE PRE-FLIGHT & METHOD CONTROL
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Reveal minimal headers only. Auth prompt if missing.
    header('MS-Author-Via: DAV');
    header('DAV: 1, 2, 3');
    header('Allow: OPTIONS, GET, HEAD, DELETE, PROPFIND, MKCOL, PUT, PROPPATCH, COPY, MOVE, LOCK, UNLOCK'); 
    header('Access-Control-Allow-Origin: *'); 
    
    if (empty($_SERVER['PHP_AUTH_USER'])) {
         header('WWW-Authenticate: Basic realm="WebDAV"');
    }
    http_response_code(200); exit;
}

$allowedMethods = ['OPTIONS', 'GET', 'HEAD', 'DELETE', 'PROPFIND', 'MKCOL', 'PUT', 'PROPPATCH', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    http_response_code(403); die();
}

// Harden Auth Header Parsing (Tolerant)
if (empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    $parts = preg_split('/\s+/', $authHeader, 2, PREG_SPLIT_NO_EMPTY);
    if (count($parts) === 2 && strtolower($parts[0]) === 'basic') {
        $base64 = $parts[1];
        if (preg_match('/^[a-zA-Z0-9\/\+=]+$/', $base64)) {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false && strpos($decoded, "\0") === false && strpos($decoded, ':') !== false) {
                list($user, $pass) = explode(':', $decoded, 2);
                if (strlen($user) < 128 && strlen($pass) < 256) {
                    $_SERVER['PHP_AUTH_USER'] = trim($user);
                    $_SERVER['PHP_AUTH_PW']   = $pass;
                }
            }
        }
    }
}

// Trap: Missing Auth Loop (Bypass Protection)
if (empty($_SERVER['PHP_AUTH_USER'])) {
    $protector->registerFail($ip, 'no_auth_user');
}

// -------------------------------------------------------------------------
// 4. SECURITY GATEWAY (CACHED GEOIP/CLIENT SCANNER)
// -------------------------------------------------------------------------
$cache_file = $work_dir . '/data/webdav/security_cache_' . md5($ip) . '.tmp';
$cache_ttl = 600; 
$skip_security_check = false;

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
    $skip_security_check = true;
}

if (!$skip_security_check) {
    // 1% Probabilistic GC
    if (rand(1, 100) === 1) {
        $gc_files = glob($work_dir . '/data/webdav/security_cache_*.tmp');
        if ($gc_files) {
            foreach ($gc_files as $gc_file) {
                if (file_exists($gc_file) && (time() - filemtime($gc_file) > $cache_ttl)) @unlink($gc_file);
            }
        }
    }

    if (file_exists($work_dir . '/parts/security_checks.php')) {
        require_once $work_dir . '/bin/functions.php';
        require_once $work_dir . '/bin/geoip.php';
        require_once $work_dir . '/parts/security_checks.php';
    }

    $sec_data = isset($geoip_data) ? $geoip_data : [];
    $checker = new ClientSecurity($sec_data, $log_file, $webdav_sec_config);
    $result  = $checker->runCheck();

    if ($result['status'] === 'BLOCK') {
        http_response_code(403); die();
    } else {
        touch($cache_file);
    }
}

// -------------------------------------------------------------------------
// 5. HARDENED FILESYSTEM NODE (AUTHENTICATED ABUSE PROTECTION)
// -------------------------------------------------------------------------
class HardenedDirectory extends \Sabre\DAV\FS\Directory {
    protected $maxFileSize; 

    public function __construct($path, $maxFileSizeMB = 500) {
        parent::__construct($path);
        $this->maxFileSize = $maxFileSizeMB * 1024 * 1024;
    }

    public function createFile($name, $data = null) {
        // 1. Filename Length Check (128 chars max)
        if (strlen($name) > 128) {
            throw new \Sabre\DAV\Exception\Forbidden('Filename too long.');
        }

        // 2. PUT Size Restriction (Quota/DoS Protection)
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $isChunked = isset($_SERVER['HTTP_TRANSFER_ENCODING']) && strcasecmp($_SERVER['HTTP_TRANSFER_ENCODING'], 'chunked') === 0;

        // If the request is chunked, we cannot trust Content-Length pre-flight. 
        // Reject chunked encoding to strictly enforce the quota firewall.
        if ($contentLength > $this->maxFileSize || $isChunked) {
            throw new \Sabre\DAV\Exception\EntityTooLarge('File exceeds maximum upload limit or uses unsupported chunked encoding.');
        }

        // 3. Path Vindicator (Double-check path within jail)
        $dest = $this->path . '/' . $name;
        $realDest = realpath(dirname($dest));
        $realRoot = realpath($this->path);

        // Verify resolved path is still strictly inside the root (prevents partial matches)
        $realDestCheck = rtrim($realDest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realRootCheck = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        if ($realDest === false || strpos($realDestCheck, $realRootCheck) !== 0) {
			throw new \Sabre\DAV\Exception\Forbidden('Jailbreak attempt detected.');
        }

        return parent::createFile($name, $data);
    }
}

// -------------------------------------------------------------------------
// 6. AUTHENTICATION (RAM + HMAC SECURITY)
// -------------------------------------------------------------------------
class Sha256AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
    protected $users, $protector, $logger, $cacheDir, $secret;

    public function __construct(array $users, BruteForceProtector $protector, DavLogger $logger, $work_dir) {
        $this->users = $users;
        $this->protector = $protector;
        $this->logger = $logger;
        
        // Point to RAM Disk (/dev/shm) for speed and disk protection
        $this->cacheDir = '/dev/shm/webdav_auth_' . substr(md5($work_dir), 0, 8);
        if (!is_dir($this->cacheDir)) @mkdir($this->cacheDir, 0700, true);

        // Persistent Secret for HMAC
        $this->secret = hash('sha256', $work_dir . 'K9vP2xLfRsdjZ4wfFnR1cF3tH6yD0gSG3V5bN2xM8cZsRF4jH7rP3kS6tY9sD2X5'); 
    }

    private function performCacheCleanup() {
        $gcFlag = $this->cacheDir . '/.last_gc';
        $now = time();
        if (!file_exists($gcFlag) || ($now - filemtime($gcFlag) > 300)) {
            $files = glob($this->cacheDir . '/sess_*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f) && ($now - filemtime($f) > 900)) @unlink($f);
                }
            }
            touch($gcFlag);
        }
    }

    public function validateUserPass($username, $password) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if ($this->protector->checkAccess($ip, $username) !== true) return false;
        
        // If user missing, fail uniformly (no 404 leakage)
        if (!isset($this->users[$username])) {
            $this->protector->registerFail($ip, 'invalid_user');
            return false;
        }

        // HMAC Cache Check (RAM)
        $credKey = 'sess_' . hash_hmac('sha256', "$username:$password:$ip", $this->secret);
        $cacheFile = $this->cacheDir . '/' . $credKey;

        if (file_exists($cacheFile)) {
            if (time() - filemtime($cacheFile) < 900) {
                if (rand(1, 20) === 1) touch($cacheFile); 
                return true;
            } else {
                @unlink($cacheFile);
            }
        }
        
        $this->performCacheCleanup();

        $storedHash = $this->users[$username];
        $isValid = (substr($storedHash, 0, 1) === '$') 
                   ? password_verify($password, $storedHash) 
                   : hash_equals($storedHash, hash('sha256', $password));

        if ($isValid) {
            touch($cacheFile); 
            $this->logger->log(DavLogger::LEVEL_LOGIN, "SUCCESS: $username");
            return true;
        } else {
            $this->logger->log(DavLogger::LEVEL_LOGIN, "FAIL: $username");
            $this->protector->registerFail($ip, $username);
            return false;
        }
    }
}

// -------------------------------------------------------------------------
// 7. SERVER SETUP (HARDENED)
// -------------------------------------------------------------------------
$username = $_SERVER['PHP_AUTH_USER'] ?? null;
$userPath = null;

if ($username) {
    // Strict Whitelist (Alphanum + . _ - @). Length 3-64. Kills ".." and slashes.
    if (!preg_match('/^[a-zA-Z0-9\._@+-]{3,64}$/', $username)) {
        http_response_code(403); die(); 
    }

    foreach (($user_details ?? []) as $ud) {
        if (($ud['name'] ?? '') === $username) {
            // Check Sibling Variable
            $webdav_access = $ud['cloud_webdav'] ?? false;
            if ($webdav_access !== true && $webdav_access !== 'true') {
                 // Deny without leaking (403 or 401 handled by auth)
                 // We simply don't set userPath, so it falls to dummy root.
                 break;
            }

				$clouds = $ud['cloud'] ?? [];
				$userPath = null;
				if (is_array($clouds)) {
					foreach ($clouds as $cloud) {
						if (isset($cloud['interface']) && in_array($cloud['interface'], ['email', 'gallery'], true)) {
							continue;
						}
						$userPath = $cloud['path'] ?? null;
						break;
					}
				}
			break;
        }
    }
}

// Anti-Enumeration: Always provide a root, even if user is invalid.
// AuthBackend will reject them later with 401, indistinguishable from "Wrong Password".
$rootPath = ($userPath && is_dir($userPath)) ? $userPath : $work_dir . '/data/webdav/empty_root';
if (!is_dir($rootPath)) @mkdir($rootPath, 0750, true);

// Use Hardened Node (3GB Limit)
$rootNode = new HardenedDirectory($rootPath, 3072);

// --- URI FIX ---
//$requestUri = $_SERVER['REQUEST_URI'];
//if (strpos($requestUri, '/webdav.php') === 0) {
//    $pathInfo = substr($requestUri, strlen('/webdav.php'));
//    if (($pos = strpos($pathInfo, '?')) !== false) $pathInfo = substr($pathInfo, 0, $pos); 
//    $decodedPath = rawurldecode($pathInfo);
//    $_SERVER['PATH_INFO'] = $decodedPath;
// Fallback for filesystem encoding issues
//    if ($userPath) {
//        $fullCheckPath = rtrim($userPath, '/') . '/' . ltrim($decodedPath, '/');
//        $rawCheckPath  = rtrim($userPath, '/') . '/' . ltrim($pathInfo, '/');
//        if (!file_exists($fullCheckPath) && file_exists($rawCheckPath)) {
//            $_SERVER['PATH_INFO'] = $pathInfo;
//        }
//    }
//}
// --- END URI FIX ---


$server = new \Sabre\DAV\Server($rootNode);
$server->setBaseUri('/webdav.php'); 

$server->addPlugin(new \Sabre\DAV\Auth\Plugin(new Sha256AuthBackend($users, $protector, $logger, $work_dir)));

 
// [COMPATIBILITY] SQLite Property Storage (Prevents Sync Loops)
$propDbPath = $work_dir . '/data/webdav/properties.sqlite';
$propDbExists = file_exists($propDbPath);
$pdo = new \PDO('sqlite:' . $propDbPath);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
if (!$propDbExists) {
    // Create SabreDAV property table schema dynamically
    $pdo->exec("CREATE TABLE propertystorage (id INTEGER PRIMARY KEY ASC, path TEXT, name TEXT, valuetype INTEGER, value TEXT);
                CREATE UNIQUE INDEX path_property ON propertystorage (path, name);");
}
$server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(
    new \Sabre\DAV\PropertyStorage\Backend\PDO($pdo)
));

$lockDir = '/dev/shm/webdav_locks_' . substr(md5($work_dir), 0, 8);
if (!is_dir(dirname($lockDir))) @mkdir(dirname($lockDir), 0750, true);
$server->addPlugin(new \Sabre\DAV\Locks\Plugin(new \Sabre\DAV\Locks\Backend\File($lockDir)));
$server->addPlugin(new \Sabre\DAV\Browser\GuessContentType());
$server->addPlugin(new \Sabre\DAV\Sync\Plugin());

// Dynamic Timeout (Hours for Uploads, Minutes for Browse)
if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'MOVE', 'COPY'])) {
    set_time_limit(7200); 
} else {
    set_time_limit(300);
}

// -------------------------------------------------------------------------
// 8. FORCE 403 ON NOT FOUND (SECURITY OBSCURITY)
// -------------------------------------------------------------------------
//$server->on('exception', function ($e) {
//    // If SabreDAV says "Not Found", we lie and say "Forbidden"
//    if ($e instanceof \Sabre\DAV\Exception\NotFound) {
//        http_response_code(403);
//        // Optional: Output a minimal error body if you want
//        // echo "Access Denied"; 
//        // Return false to stop SabreDAV from processing the exception standardly (which would be 404)
//        return false;
//    }
//});

$server->start();
?>