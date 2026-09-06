<?php 
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { 
	header("Location: https://".$_SERVER['HTTP_HOST']); 
	header("Connection: close");
	die();}
if(!defined('IPS_Token')) { 
	header("Location: https://".$_SERVER['HTTP_HOST']); 
	header("Connection: close");
	die();}

// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW
// Functions
// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMW



// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// Write to log. If not writable, put the info into another file.
function WriteLog ($file, $line) {
    // Replace < with [ and > with ] to neutralize HTML tags 
    // while keeping their content visible.
    $cleanLine = str_replace(array('<', '>'), array('[', ']'), $line);

    if (is_writable($file)) {
        file_put_contents($file, $cleanLine, FILE_APPEND);
    } else {
        if (file_exists($file)) {
            // Writes to .error if the main file exists but isn't writable
            file_put_contents($file . ".error", $cleanLine, FILE_APPEND);
        } else {
            // Attempts to create the file if it doesn't exist
            file_put_contents($file, $cleanLine, FILE_APPEND);
        }
    }
}

function WriteLogLine($file, $result, $description) {
	
	global $geoip_data;
	
	$domain = $_SERVER['HTTP_HOST'];
	$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$pathParts = explode('/', trim($requestPath, '/'));
	$subdir = isset($pathParts[0]) ? $pathParts[0] : '';
	if ($subdir == 'auth_adm_php') {
        $app = "Config";
    } elseif ($subdir == 'cloud') {
        $app = "Cloud";
    } elseif ($subdir == 'cloud.beta') {
        $app = "βCloud";
    } else {
        $app = $subdir; // Fallback: logs the actual subdir if it's not one of the specific apps
    }
    
	$search      = array("\r\n", "\r", "\n", "\t");
    $replace     = array('', '', '', ' ');
	
	$result      = str_replace($search, $replace, $result);
	$description = str_replace($search, $replace, $description);
	// Replace the FIRST column only with a tab
	$description = preg_replace('/: /', "\t", $description, 1);

    $domain      = str_replace($search, $replace, $domain);
    $app         = str_replace($search, $replace, $app);
    $safe_ip     = str_replace($search, $replace, get_real_ip_address());
    $safe_cc     = str_replace($search, $replace, $geoip_data['country_code'] ?? '');
 
	$log_line_full =  date("Y-m-d H:i:s")."\t".
					$result."\t".
					$safe_ip."\t".
					$safe_cc."\t".
					$app."\t".      // Added: App Name
                    $domain."\t".   // Added: Domain Name
					$description."\t".
					PHP_EOL;
	 WriteLog($file, $log_line_full); 
}


function generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function removeEmojis($text) {
    //$textWithoutEmojis = preg_replace('/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u', '', $text);
	$textWithoutEmojis = preg_replace('/[^\x00-\x7F]/u', '', $text);
    return trim($textWithoutEmojis);
}


// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns the real IP address (needed for the "lock file" at the very beginning, 
//    which is done before the "My->ip_address" variable is instanciated
function get_real_ip_address() {
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip_address = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
	elseif(isset($_SERVER['HTTP_X_FORWARDED'])) $ip_address = $_SERVER['HTTP_X_FORWARDED'];
	elseif(isset($_SERVER['HTTP_FORWARDED_FOR'])) $ip_address = $_SERVER['HTTP_FORWARDED_FOR'];
	elseif(isset($_SERVER['HTTP_FORWARDED'])) $ip_address = $_SERVER['HTTP_FORWARDED'];
	elseif(isset($_SERVER['HTTP_CLIENT_IP'])) $ip_address = $_SERVER['HTTP_CLIENT_IP'];
	elseif(isset($_SERVER['REMOTE_ADDR'])) $ip_address = $_SERVER['REMOTE_ADDR'];
	else $ip_address = 'UNKNOWN_IP';
	return trim($ip_address);
}


function isPrivateIp($ip) {
    $ipLong = ip2long($ip);
    if ($ipLong === false) return false;

    $private_ranges = [
        ['127.0.0.0', '127.255.255.255'], // Loopback
        ['10.0.0.0',  '10.255.255.255'],  // Class A
        ['172.16.0.0', '172.31.255.255'], // Class B (Docker default)
        ['192.168.0.0', '192.168.255.255'], // Class C
        ['169.254.0.0', '169.254.255.255']  // Link-local (APIPA)
    ];

    foreach ($private_ranges as $range) {
        $start = ip2long($range[0]);
        $end = ip2long($range[1]);
        if ($ipLong >= $start && $ipLong <= $end) {
            return true;
        }
    }

    return false;
}


// Check if $path is a filename/relative path or an absolute path
function isAbsolutePath($path): bool {
    if (!is_string($path) || trim($path) === '') {
        return false;
    }

    // Heal 1: Null byte injection removal before processing (Highly dangerous in Linux file systems)
    if (strpos($path, "\0") !== false) {
        $path = str_replace("\0", '', $path);
    }

    $result = (isset($path[0]) && $path[0] === '/');

    // =========================================================
    // BULLETPROOF SELF-TEST & HEAL
    // Coverage: Ensure it properly handles Linux absolute path detection
    // =========================================================
    if ($result === true) {
        // Heal 2: Reject Windows path spoofing masquerading as absolute Linux paths
        // We cannot safely 'heal' C:\ into a Linux path, so we heal the OUTPUT state to false.
        if (preg_match('/^\/?[a-zA-Z]:[\\\\\/]/', $path)) {
            $result = false; 
        }
        
        // Heal 3: Double enforce the starting character directly on the cleansed path
        if (!str_starts_with($path, '/')) {
            $result = false;
        }
    }

    return (bool)$result;
}

// ``````````````````````````````````````````````````````````````````````````````````````
//  Timer in microseconds
// `````````````````````````````````````````````````````````````````````````````````````
//
//    First call starts timer, second call stops it and returns the difference
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns the difference (at 2nd call)
function Timer( & $ms ) {
	if (\floatval( $ms ) == 0) {
		$ms = microtime( true );
	}
	else {
		$originalMs = $ms;
		$ms = 0;
		return microtime( true ) - $originalMs;
	}
}





// ``````````````````````````````````````````````````````````````````````````````````````
//  Get domain out of URL, without subdomains
// `````````````````````````````````````````````````````````````````````````````````````
//
//    1st parameter: The URL to convert
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns the domain without subdomains
function getDomain($url) {
    if (!is_string($url) || trim($url) === '') {
        return null;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        $host = parse_url('http://' . $url, PHP_URL_HOST);
    }
    
    $result = null;
    if ($host) {
        if(filter_var($host, FILTER_VALIDATE_IP)) {
            $result = $host; 
        } else {
            $domain_array = explode(".", str_replace('www.', '', $host));
            $count = count($domain_array);
            if( $count>=3 && strlen($domain_array[$count-2])==2 ) {
                $result = implode('.', array_splice($domain_array, $count-3, 3));
            } else if( $count>=2 ) {
                $result = implode('.', array_splice($domain_array, $count-2, 2));
            } else {
                $result = $host;
            }
        }
    }

    // =========================================================
    // BULLETPROOF SELF-TEST & HEAL
    // Coverage: Extracting domains from various formats of URLs, SLDs, and TLDs
    // =========================================================
    if ($result !== null) {
        // Heal 1: Strip out any leaked URL paths, queries, or ports that parse_url failed to catch
        if (strpos($result, '/') !== false) $result = strtok($result, '/');
        if (strpos($result, '?') !== false) $result = strtok($result, '?');
        // Strip ports, but only if it's not an IPv6 address
        if (strpos($result, ':') !== false && !filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $result = strtok($result, ':');
        }

        $result = trim($result);
        
        $isIp = filter_var($result, FILTER_VALIDATE_IP);
        if (!$isIp) {
            // Heal 2: Standardize domains to lowercase
            $result = strtolower($result);
            
            // Validate structural integrity of the healed string
            if (strpos($result, '.') === false && $result !== 'localhost') {
                $result = null; // Unhealable - not a domain
            } elseif (!preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $result) && $result !== 'localhost') {
                $result = null; // Unhealable - structurally invalid
            }
        }
    }

    return $result;
}


// ``````````````````````````````````````````````````````````````````````````````````````
//  Insert a line into a text file as first line 
// `````````````````````````````````````````````````````````````````````````````````````
//
//    1st parameter: The file name
//    2nd parameter: The line to write
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns nothing
function prependToFile($filePath, $newLine) {
    // Ensure the new line ends with a single newline character
    if (!str_ends_with($newLine, "\n")) {
        $newLine .= "\n";
    }

	$fp = fopen($filePath, 'c+');
	if ($fp) {
		flock($fp, LOCK_EX);
		$fileContents = stream_get_contents($fp);
		rewind($fp);
		fwrite($fp, $newLine . $fileContents);
		fflush($fp);
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

// ``````````````````````````````````````````````````````````````````````````````````````
//  Get number of lines in a file (can serve large files!)
// `````````````````````````````````````````````````````````````````````````````````````
//
//    1st parameter: The file name
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns the number of lines
function getLines($file)
{
    $f = fopen($file, 'rb');
    $lines = 0; $buffer = '';

    while (!feof($f)) {
        $buffer = fread($f, 65536);
        $lines += substr_count($buffer, "\n");
    }

    fclose($f);

    if (strlen($buffer) > 0 && $buffer[-1] != "\n") {
        ++$lines;
    }
    return $lines;
}



// ``````````````````````````````````````````````````````````````````````````````````````
//  Get the host name from DNS (since gethostbyaddr has some trouble with timeouts
// `````````````````````````````````````````````````````````````````````````````````````
//
//    1st parameter: IP address to resolve
//    2nd parameter: DNS Server, defaults to Google
//    3rd parameter: Timeout, defaults to 1000 miliseconds
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns either the host name or if that fails, the IP address
function gethostbyaddr_timeout($ip, $dns = "8.8.8.8", $timeout = 1000)
{
    // random transaction number (for routers etc to get the reply back)
    $data = random_bytes(2);
    // request header
    $data .= "\1\0\0\1\0\0\0\0\0\0";
    // split IP up
    $bits = explode(".", $ip);
    // error checking
    if (count($bits) != 4) return "ERROR";
    // there is probably a better way to do this bit...
    // loop through each segment
    for ($x=3; $x>=0; $x--)
    {
        // needs a byte to indicate the length of each segment of the request
        switch (strlen($bits[$x]))
        {
            case 1: // 1 byte long segment
                $data .= "\1"; break;
            case 2: // 2 byte long segment
                $data .= "\2"; break;
            case 3: // 3 byte long segment
                $data .= "\3"; break;
            default: // segment is too big, invalid IP
                return "INVALID";
        }
        // and the segment itself
        $data .= $bits[$x];
    }
    // and the final bit of the request
    $data .= "\7in-addr\4arpa\0\0\x0C\0\1";
    // create UDP socket
    $handle = @fsockopen("udp://$dns", 53);
    // send our request (and store request size so we can cheat later)
    $requestsize = @fwrite($handle, $data);
 
    @socket_set_timeout($handle, $timeout - $timeout%1000, $timeout%1000);
    // hope we get a reply
    $response = @fread($handle, 1000);
    @fclose($handle);
    if ($response == "" || $response == null)
        return $ip;
    // find the response type
    $type = @unpack("s", substr($response, $requestsize+2));
    if (empty($type))
        return $ip;
    if ($type[1] == 0x0C00)  // answer
    {
        // set up our variables
        $host = "";
        $len = 0;
        // set our pointer at the beginning of the hostname
        // uses the request size from earlier rather than work it out
        $position = $requestsize+12;
	// handle errors
	if (is_bool(unpack("c", substr($response, $position))) == True) return $ip;
        // reconstruct hostname
       do
        {
	    // get segment size
            $len = @unpack("c", substr($response, $position));
            // null terminated string, so length 0 = finished
            if ($len[1] == 0)
                // return the hostname, without the trailing .
                return substr($host, 0, strlen($host) -1);
            // add segment to our host
            $host .= substr($response, $position+1, $len[1]) . ".";
            // move pointer on to the next segment
            $position += $len[1] + 1;
        }
        while ($len != 0);
        // error - return the hostname we constructed (without the . on the end)
        return $ip;
    }
    return $ip;
}

// There should be no HTML or commented-out HTML structure after this point in this file.
// The file ends here or with a simple closing PHP tag if you prefer.
// ``````````````````````````````````````````````````````````````````````````````````````
//  See if an IP address in reality is a CIDR
// `````````````````````````````````````````````````````````````````````````````````````
//
//    1st parameter: IP address to resolve
//
// ``````````````````````````````````````````````````````````````````````````````````````
//    Returns true if the IP supplied is a valid CIDR, otherwise false
function ipban_is_valid_ip_or_cidr($input): bool {
    if (!is_string($input) || trim($input) === '') {
        return false;
    }

    $result = false;
    if (filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($input, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $result = true;
    } elseif (preg_match('/^([\d.:a-fA-F]+)\/(\d{1,3})$/', $input, $matches)) {
        $ip = $matches[1];
        $mask = (int)$matches[2];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (strpos($ip, '.') !== false) {
                $result = ($mask >= 0 && $mask <= 32);
            } else {
                $result = ($mask >= 0 && $mask <= 128);
            }
        }
    }

    // =========================================================
    // BULLETPROOF SELF-TEST & HEAL
    // Coverage: Strict regex mapping of IP masks and valid IPv4/IPv6 boundaries
    // =========================================================
    if ($result === true) {
        $parts = explode('/', $input);
        $testIp = $parts[0];
        
        $isV4 = filter_var($testIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $isV6 = filter_var($testIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        
        // Heal 1: If base IP violates standards despite previous logic, safely force return to false.
        if (!$isV4 && !$isV6) {
            $result = false;
        }
        
        // Heal 2: If mask is structurally present but out of bounds, override to false to prevent DB/Routing errors.
        if (isset($parts[1]) && $result === true) {
            $maskInt = (int)$parts[1];
            if ($isV4 && ($maskInt < 0 || $maskInt > 32)) $result = false;
            if ($isV6 && ($maskInt < 0 || $maskInt > 128)) $result = false;
        }
    }

    return (bool)$result; // Strictly cast to boolean to heal type-leakage
}



function checkAccess(array $allowedRoles): bool {

    // Ensure the session is active.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // If there's no logged-in user, we consider access not granted.
    if (!isset($_SESSION['username'])) {
        return false;
    }

    $username = $_SESSION['username'];
     // Look up the user's role using the helper function; default to 'user' if not found.
     $role = getUserRole($username) ?? 'user';    // Look up the user's role from the global array; default to 'user' if not found.


    // Return whether the user's role is among the allowed roles.
    return in_array($role, $allowedRoles, true);
}

function getUserRole($username) {
     global $user_details;
 
     if (isset($user_details) && is_array($user_details)) {
         foreach ($user_details as $ud) {
             if (isset($ud['name']) && strcasecmp($ud['name'], $username) === 0) {
                 return $ud['role'] ?? "notfound";
             }
         }
     }
     return null;
}

/**
 * Retrieves the service-specific user name for a given administrative user.
 *
 * @param string $userID The administrative user identifier.
 * @param string $service The name of the service.
 * @return string|null Returns the service-specific user name if it exists, otherwise null.
 
	Example usages:
	echo "Admin 'admin1' has Nextcloud account: " . getServiceUserID('admin1', 'nextcloud') . "\n"; // Outputs: nextcloud_user1
	echo "Admin 'admin1' has Plesk account: " . getServiceUserID('admin1', 'plesk') . "\n";         // Outputs: plesk_user1
	echo "Admin 'admin1' has a Nonexistent service: " . var_export(getServiceUserID('admin1', 'nonexistent'), true) . "\n";
 */
function getServiceUserID($userID, $service) {
    // Access the global mapping array named $user_details.
	global $user_details;
	
	if (isset($user_details) && is_array($user_details)) {
		foreach ($user_details as $ud) {
			if (isset($ud['name']) && strcasecmp($ud['name'], $userID) === 0) {
				if (isset($ud['other_users']) && isset($ud['other_users'][$service])) {
					return $ud['other_users'][$service];
				}
				break;
			}
		}
	}
    return null;
}


function generateFriendlyRandomString(int $length = 6): string {
    $chars = '34679abcdefghijkmnopqrstwxyzACDEFGHJKLMNPQRTWXY';
    $max = strlen($chars) - 1;
    $result = '';

    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $max)];
    }

    // =========================================================
    // BULLETPROOF SELF-TEST & HEAL
    // Coverage: String length guarantees and proper random character usage
    // =========================================================
    $attempts = 0;
    while ((strlen($result) !== $length || preg_match('/[^34679abcdefghijkmnopqrstwxyzACDEFGHJKLMNPQRTWXY]/', $result)) && $attempts < 5) {
        // Heal 1: Strip out any illegal characters that somehow ended up in the string
        $result = preg_replace('/[^34679abcdefghijkmnopqrstwxyzACDEFGHJKLMNPQRTWXY]/', '', $result);
        
        // Heal 2: If the string is too short after stripping (or initial generation failed), fill it back up
        while (strlen($result) < $length) {
            $result .= $chars[random_int(0, $max)];
        }
        
        // Heal 3: If the string is too long, strictly truncate it
        if (strlen($result) > $length) {
            $result = substr($result, 0, $length);
        }
        $attempts++;
    }

    // Failsafe: If the loop couldn't heal it after 5 tries, brute-force a safe string.
    if (strlen($result) !== $length || preg_match('/[^34679abcdefghijkmnopqrstwxyzACDEFGHJKLMNPQRTWXY]/', $result)) {
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }

    return $result;
}

function generateSecureString(int $length = 32): string {
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;

    for ($i = 0; $i < $length; ++$i) {
        $pieces[] = $keyspace[random_int(0, $max)];
    }

    return implode('', $pieces);
}

// ----------------------------------------------------------------------
// Global login rate limiter
function global_login_rate_limit($file, $max, $window) {
    $now = time();

    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }

    $fp = fopen($file, 'c+');
    if (!$fp) return;

    flock($fp, LOCK_EX);
    $data = json_decode(stream_get_contents($fp), true) ?: [];

    // Cleanup old entries
    foreach ($data as $ts => $count) {
        if ($ts + $window < $now) {
            unset($data[$ts]);
        }
    }

    $data[$now] = ($data[$now] ?? 0) + 1;

    $total = array_sum($data);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($total > $max) {
        http_response_code(429);
        die("<h2>⛔ Too many login attempts. Please wait.</h2>");
    }
}



// Comprehensive filename validation
function validateFilename($name) {
    // Check length
    if (strlen($name) > 255) {
        return ['valid' => false, 'error' => 'Name too long (max 255 chars)'];
    }
    
    // Check for null bytes and control characters
    if (preg_match('/[\x00-\x1f\x7f]/', $name)) {
        return ['valid' => false, 'error' => 'Contains invalid control characters'];
    }
    
    // Check for Windows reserved names
    $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 
                'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 
                'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
    
    $base_name = trim(explode('.', $name)[0]);
    if (in_array(strtoupper($base_name), $reserved)) {
		return ['valid' => false, 'error' => 'Reserved system name'];
    }
    
    // Check for dangerous characters
    if (preg_match('/[<>:"|?*\\\\\/]/', $name)) {
        return ['valid' => false, 'error' => 'Contains forbidden characters'];
    }
    
    return ['valid' => true];
}



// ----------------------------------------------------------------------


use MatthiasMullie\Minify; 

function myCloudMinifyHtmlBlocks($htmlContent) {
    // Increase limits just in case
    ini_set('pcre.backtrack_limit', '10000000');

    return preg_replace_callback('#<(script|style)([^>]*)>(.*?)</\1>#is', function($matches) {
        $tag = strtolower($matches[1]);
        $attributes = $matches[2];
        $content = $matches[3];
        $trimmed = trim($content);

        if ($trimmed === '') return $matches[0];
        
        // Skip src scripts with no body
        if ($tag === 'script' && stripos($attributes, 'src=') !== false && $trimmed === '') {
             return $matches[0];
        }

        // --- CSS: Use Library (Always Safe) ---
        if ($tag === 'style') {
            try {
                $minifier = new \MatthiasMullie\Minify\CSS($content);
                return "<style{$attributes}>" . $minifier->minify() . "</style>";
            } catch (Exception $e) { return $matches[0]; }
        }

        // --- JS: Use Custom Tokenizer ---
        if ($tag === 'script') {
            // 1. Strip Comments safely using the State Machine
            $cleanJs = myCloudStripJsComments($content);
            
            // 2. Remove Indentation (Trim every line)
            $lines = explode("\n", $cleanJs);
            $packed = [];
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t !== '') $packed[] = $t;
            }
            // Join with newline to preserve ASI safety
            return "<script{$attributes}>" . implode("\n", $packed) . "</script>";
        }

        return $matches[0];
    }, $htmlContent);
}

/**
 * A robust State Machine to strip JS comments.
 * Handles: Strings (" ' `), Regex Literals (/.../), and Escaping (\).
 */
function myCloudStripJsComments($str) {
    $output = '';
    $len = strlen($str);
    $i = 0;
    
    // States
    $inString = false;      // false, "'", '"', or "`"
    $inComment = false;     // false, '//', or '/*'
    $inRegex = false;       // true/false
    
    while ($i < $len) {
        $char = $str[$i];
        $next = ($i + 1 < $len) ? $str[$i + 1] : '';
        
        // 1. IF WE ARE IN A COMMENT
        if ($inComment) {
            if ($inComment === '//' && ($char === "\n" || $char === "\r")) {
                $inComment = false; // End of single line comment
                $output .= $char;    // Keep the newline/CR!
            }
            elseif ($inComment === '/*' && $char === '*' && $next === '/') {
                $inComment = false; // End of multi line comment
                $i++; // Skip the closing '/'
            }
            $i++;
            continue;
        }

        // 2. IF WE ARE IN A STRING ( " or ' or ` )
        if ($inString) {
            $output .= $char;
            if ($char === '\\') {
                // Escape next char (skip output logic for it)
                $output .= $next; 
                $i++;
            } elseif ($char === $inString) {
                $inString = false; // Closed string
            }
            $i++;
            continue;
        }

        // 3. IF WE ARE IN A REGEX ( /.../ )
        if ($inRegex) {
            $output .= $char;
            if ($char === '\\') {
                $output .= $next; // Escape inside regex
                $i++;
            } elseif ($char === '/') {
                $inRegex = false; // End of regex
            }
            $i++;
            continue;
        }

        // 4. NORMAL CODE STATE
        
        // Detect Start of Comment //
        if ($char === '/' && $next === '/') {
            $inComment = '//';
            $i += 2;
            continue;
        }
        // Detect Start of Comment /*
        if ($char === '/' && $next === '*') {
            $inComment = '/*';
            $i += 2;
            continue;
        }
        
        // Detect Start of Regex
        // This is the hardest part. A '/' is a Regex IF it's not a division.
        // A simple heuristic: Division usually follows a variable/number/closing-paren.
        // Regex usually follows a punctuation like ( = , : ! & | ? { or keyword 'return'.
        if ($char === '/') {
            // Look backward for non-whitespace to guess context
            $lastChar = substr(trim(substr($output, 0, -1) . $output[strlen($output)-1] ?? ''), -1);
            if (preg_match('/[\(\=\,\:\!\&\|\?\{\;\r\n]/', $lastChar) || $output === '') {
                 $inRegex = true;
            }
        }
        
        // Detect Start of String
        if ($char === '"' || $char === "'" || $char === '`') {
            $inString = $char;
        }

        // Output the character
        $output .= $char;
        $i++;
    }
    
    return $output;
}

// ======================================================================
//  MODERN SAFE MINIFIER (Line-Based Edition)
// ======================================================================

function myCloudMinifySafe_Html($html) {
    // 1. Extract <script>, <style>, <pre>, <textarea>
    $protected = [];
    $tags = ['script', 'style', 'pre', 'textarea'];

    foreach ($tags as $tag) {
        $html = preg_replace_callback(
            '#<' . $tag . '(?:[^>]*)>.*?</' . $tag . '>#is', 
            function ($matches) use (&$protected, $tag) {
                $placeholder = "###myCloud_PROTECT_" . count($protected) . "###";
                
                $content = $matches[0];
                if ($tag === 'script') {
                    $content = myCloudMinifySafe_Js($content);
                } elseif ($tag === 'style') {
                    $content = myCloudMinifySafe_Css($content);
                }
                
                $protected[$placeholder] = $content;
                return $placeholder;
            }, 
            $html
        );
    }
    
    // 2. Minify HTML (Using # delimiter for regex safety)
    // A. Remove HTML Comments
    $html = preg_replace('##s', '', $html);
    
    // B. Collapse Whitespace (lines and spaces)
    $html = preg_replace('#\s+#', ' ', $html);
    
    // C. Remove space between tags
    $html = preg_replace('#>\s+<#', '><', $html);

    // 3. Restore Protected Blocks
    if (!empty($protected)) {
        $html = str_replace(array_keys($protected), array_values($protected), $html);
    }

    return trim($html);
}

function myCloudMinifySafe_Css($styleTag) {
    return preg_replace_callback('/(<style\b[^>]*>)(.*?)(<\/style>)/is', function($matches) {
        $css = $matches[2];
        // 1. Remove Comments /* ... */
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        // 2. Remove Newlines and Tabs
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        // 3. Collapse multiple spaces to one
        $css = preg_replace('/\s+/', ' ', $css);
        // 4. Remove space around punctuation
        $css = preg_replace('/\s*([:;{}])\s*/', '$1', $css);
        
        return $matches[1] . $css . $matches[3];
    }, $styleTag);
}

function myCloudMinifySafe_Js($scriptTag) {
    return preg_replace_callback('/(<script\b[^>]*>)(.*?)(<\/script>)/is', function($matches) {
        $js = $matches[2];
        
        // METHOD: Line-by-Line Cleaning
        // This is safer than parsing character-by-character for mixed PHP/JS
        
        $lines = explode("\n", $js);
        $cleanLines = [];
        
        foreach ($lines as $line) {
            $trimLine = trim($line);
            
            // 1. Skip lines that are purely single-line comments
            // Checks if line starts with // (ignoring leading space)
            if (strpos($trimLine, '//') === 0) {
                continue; 
            }
            
            // 2. Skip empty lines
            if ($trimLine === '') {
                continue;
            }
            
            // 3. Keep the line exactly as is (preserves indentation for backticks)
            // We append a newline to ensure ASI (Automatic Semicolon Insertion) safety
            $cleanLines[] = $line; 
        }
        
        // Rejoin with newlines. 
        // We do NOT collapse to one line, because that risks breaking JS code 
        // if a semicolon is missing.
        $cleanJs = implode("\n", $cleanLines);

        return $matches[1] . $cleanJs . $matches[3];
    }, $scriptTag);
}




/**
 * Lightweight HTML/CSS/JS obfuscator
 * - Renames classes & IDs
 * - Encodes most string literals in JS
 * - Minifies & slightly mangles structure
 * - Keeps functionality intact in 95%+ of real pages
 *
 * Limitations:
 *   • Does NOT handle dynamic class names created via JS
 *   • Does NOT obfuscate inline event handlers very well (onclick="...")
 *   • Advanced JS frameworks (React/Vue with lots of string-based logic) may break
 *
 * @param string $html Original HTML
 * @param int $js_obf_level 0 = none, 1 = light, 2 = medium (default), 3 = aggressive
 * @return string Obfuscated HTML
 */
function obfuscate_html(string $html, int $js_obf_level = 2): string
{
    // -------------------------------------------------------------------------
    //  1. Collect & rename classes and IDs
    // -------------------------------------------------------------------------
    $class_map = [];
    $id_map    = [];

    $counter = 0;

    // Find all class="" and id=""
    preg_match_all('/class\s*=\s*["\']([^"\']*)["\']/i', $html, $class_matches, PREG_SET_ORDER);
    preg_match_all('/id\s*=\s*["\']([^"\']*)["\']/i',    $html, $id_matches,    PREG_SET_ORDER);

    foreach ($class_matches as $m) {
        foreach (explode(' ', trim($m[1])) as $cls) {
            $cls = trim($cls);
            if ($cls === '' || isset($class_map[$cls])) continue;
            $class_map[$cls] = 'c' . base_convert($counter++, 10, 36);
        }
    }

    foreach ($id_matches as $m) {
        $id = trim($m[1]);
        if ($id === '' || isset($id_map[$id])) continue;
        $id_map[$id] = 'i' . base_convert($counter++, 10, 36);
    }

    // -------------------------------------------------------------------------
    //  2. Replace classes & IDs in HTML
    // -------------------------------------------------------------------------
    $html = preg_replace_callback(
        '/(class|id)\s*=\s*["\']([^"\']*)["\']/i',
        function ($m) use ($class_map, $id_map) {
            $attr = strtolower($m[1]);
            $values = explode(' ', trim($m[2]));
            $new_values = [];

            foreach ($values as $v) {
                $v = trim($v);
                if ($v === '') continue;

                if ($attr === 'class') {
                    $new_values[] = $class_map[$v] ?? $v;
                } else {
                    $new_values[] = $id_map[$v] ?? $v;
                }
            }

            $new_str = implode(' ', $new_values);
            return $attr . '="' . htmlspecialchars($new_str, ENT_QUOTES) . '"';
        },
        $html
    );

    // Replace inline style attribute selectors (very naive — won't catch complex selectors)
    $html = preg_replace_callback(
        '/style\s*=\s*"[^"]*?([#.][a-z0-9_-]+)[^"]*"/i',
        function ($m) use ($class_map, $id_map) {
            $selector = $m[1];
            $prefix   = $selector[0];
            $name     = substr($selector, 1);

            if ($prefix === '.') {
                $new_name = $class_map[$name] ?? $name;
            } elseif ($prefix === '#') {
                $new_name = $id_map[$name] ?? $name;
            } else {
                $new_name = $name;
            }

            return str_replace($selector, $prefix . $new_name, $m[0]);
        },
        $html
    );

    // -------------------------------------------------------------------------
    //  3. Obfuscate JavaScript parts
    // -------------------------------------------------------------------------
    $html = preg_replace_callback(
        '/<script\b[^>]*>([\s\S]*?)<\/script>/i',
        function ($m) use ($js_obf_level) {
            $code = $m[1];

            // Level 0 → do nothing
            if ($js_obf_level === 0) {
                return $m[0];
            }

            // Very light: remove comments & extra whitespace
            if ($js_obf_level === 1) {
                $code = preg_replace('!//.*!m', '', $code);
                $code = preg_replace('!/\*.*?\*/!s', '', $code);
                $code = preg_replace('/\s+/', ' ', $code);
                return '<script>' . trim($code) . '</script>';
            }

            // Medium & aggressive: encode strings + rename some variables
            // We use a very simple string encoder here (base64 + atob)
            $code = preg_replace_callback(
                '/(["\'])((\\\\.|[^\\\\])*?)\\1/s',
                function ($str) {
                    $content = $str[2];
                    // Skip very short strings & already encoded ones
                    if (strlen($content) <= 4 || preg_match('/^[a-z0-9+/=]{20,}$/i', $content)) {
                        return $str[0];
                    }
                    $encoded = base64_encode($content);
                    return 'atob("' . $encoded . '")';
                },
                $code
            );

            if ($js_obf_level >= 3) {
                // Very aggressive — rename common local variables (risky!)
                $common_vars = ['i','j','k','el','elem','node','item','x','y','data','cfg','opt'];
                foreach ($common_vars as $old) {
                    $code = preg_replace('/\b' . preg_quote($old, '/') . '\b(?!\s*:)/', '_'.substr(md5($old.microtime()),0,5), $code);
                }
            }

            return '<script>' . $code . '</script>';
        },
        $html
    );

    // -------------------------------------------------------------------------
    //  4. Final minification touches
    // -------------------------------------------------------------------------
    $html = preg_replace(['/\s+/', '/>\s+</'], [' ', '><'], $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html); // remove HTML comments

    return $html;
}





// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// Captcha functions
function initCaptchaState() {
	if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
	if (isset($_SESSION['captcha_code'])) return; // State already initialized

	// Randomly pick one of the 4 CAPTCHA methods
	$type = random_int(1, 4);
	$_SESSION['captcha_type'] = $type;
    $_SESSION['captcha_time'] = time();

	// Crystal Clear Character Sets (No P/p, C/c, S/s, V/v, W/w, X/x, Z/z, K/k, O/o, l/1/I)
	$upper = 'ABDEFGHNRT'; 
	$lower = 'abdefghnrt';
	$numbers = '234678';

	if ($type === 1 || $type === 2) {
		// TYPE 1 & 2: Standard or Distorted Text
		$chars = $numbers . $upper . $lower;
		$code = '';
		for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
		
		$_SESSION['captcha_code'] = $code;
		$_SESSION['captcha_msg_de'] = 'Bitte die Zeichen aus dem Bild eingeben:';
		$_SESSION['captcha_msg_en'] = 'Please enter the characters from the image:';
		
	} elseif ($type === 3) {
		// TYPE 3: Case Extraction (WCAG Accessible)
		$up_count = random_int(3, 4);
		$low_count = 7 - $up_count;
		
		$pool = [];
		for($i = 0; $i < $up_count; $i++) $pool[] = ['char' => $upper[random_int(0, strlen($upper)-1)], 'is_target' => true];
		for($i = 0; $i < $low_count; $i++) $pool[] = ['char' => $lower[random_int(0, strlen($lower)-1)], 'is_target' => false];
		
		shuffle($pool);
		
		$code = ''; 
		$display = '';
		foreach($pool as $item) {
			if ($item['is_target']) $code .= $item['char'];
			$display .= $item['char'];
		}
		
		$_SESSION['captcha_string_data'] = $display;
		$_SESSION['captcha_code'] = $code;
		$_SESSION['captcha_msg_de'] = '<b>NUR die GROSSBUCHSTABEN</b> aus dem Bild eingeben:';
		$_SESSION['captcha_msg_en'] = 'Enter <b>ONLY the UPPERCASE</b> letters from the image:';
		
	} else {
		// TYPE 4: Extract Numbers
		$letters = $upper . $lower;
		
		$num_count = random_int(3, 4);
		$let_count = 7 - $num_count;
		
		$pool = [];
		for($i = 0; $i < $num_count; $i++) $pool[] = ['char' => $numbers[random_int(0, strlen($numbers)-1)], 'is_num' => true];
		for($i = 0; $i < $let_count; $i++) $pool[] = ['char' => $letters[random_int(0, strlen($letters)-1)], 'is_num' => false];
		
		shuffle($pool);
		
		$code = '';
		$display = '';
		foreach($pool as $item) {
			if ($item['is_num']) $code .= $item['char'];
			$display .= $item['char'];
		}
		
		$_SESSION['captcha_string_data'] = $display;
		$_SESSION['captcha_code'] = $code;
		$_SESSION['captcha_msg_de'] = '<b>NUR die ZAHLEN</b> aus dem Bild eingeben:';
		$_SESSION['captcha_msg_en'] = 'Enter <b>ONLY the NUMBERS</b> from the image:';
	}
}

function generateCaptchaImage() {
	if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
	if (isset($_SESSION['captcha_attempts']) && $_SESSION['captcha_attempts'] >= 3) {
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
	
	// Failsafe initialization
	initCaptchaState();
	$type = $_SESSION['captcha_type'];
	
	$final_w = 220;
	$final_h = 75;
	$final_img = imagecreatetruecolor($final_w, $final_h);
	
	// Ensure final image supports alpha blending for the overlay
	imagealphablending($final_img, true);
	
	$bg_final = imagecolorallocate($final_img, 255, 255, 255);
	imagefilledrectangle($final_img, 0, 0, $final_w, $final_h, $bg_final);
	
	// =========================================================================
	// LAYER 1: THICK NOISE BACKGROUND (Lines, Circles, Squares)
	// =========================================================================
	for ($i = 0; $i < 8; $i++) {
		imagesetthickness($final_img, random_int(1, 5));
		$noise = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
		imageline($final_img, random_int(0, $final_w), random_int(0, $final_h), random_int(0, $final_w), random_int(0, $final_h), $noise);
	}
	for ($i = 0; $i < 6; $i++) {
		imagesetthickness($final_img, random_int(1, 5));
		$noise = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
		$size = random_int(10, 50);
		imageellipse($final_img, random_int(0, $final_w), random_int(0, $final_h), $size, $size, $noise);
	}
	for ($i = 0; $i < 6; $i++) {
		imagesetthickness($final_img, random_int(1, 5));
		$noise = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
		$x = random_int(-10, $final_w);
		$y = random_int(-10, $final_h);
		$size = random_int(10, 50);
		imagerectangle($final_img, $x, $y, $x + $size, $y + $size, $noise);
	}
	if ($type === 2 || $type === 3 || $type === 4) {
		for ($i = 0; $i < 3; $i++) {
			imagesetthickness($final_img, random_int(2, 6));
			$strike = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
			imageline($final_img, random_int(0, $final_w), random_int(0, $final_h), random_int(0, $final_w), random_int(0, $final_h), $strike);
		}
	}

	// =========================================================================
	// LAYER 2: TEXT RENDERING
	// =========================================================================
	if ($type === 1) {
		// METHOD 1: STANDARD UPSCALED TEXT
		$font = 5;
		$char_w = imagefontwidth($font);
		$char_h = imagefontheight($font);
		
		$code = $_SESSION['captcha_code'];
		$base_w = ($char_w + 4) * strlen($code) + 10;
		$base_h = $char_h + 15;
		
		$base_img = imagecreatetruecolor($base_w, $base_h);
		
		// PROPER ALPHA TRANSPARENCY: Prevents overwriting Layer 1 with white blocks
		imagealphablending($base_img, false);
		imagesavealpha($base_img, true);
		$trans = imagecolorallocatealpha($base_img, 0, 0, 0, 127); // 127 = Fully transparent in GD
		imagefilledrectangle($base_img, 0, 0, $base_w, $base_h, $trans);
		imagealphablending($base_img, true);
		
		$text_color = imagecolorallocate($base_img, 20, 30, 80); // Dark Navy Blue
		
		$x = 5;
		for ($i = 0; $i < strlen($code); $i++) {
			$y = random_int(2, $base_h - $char_h - 2);
			imagechar($base_img, $font, $x, $y, $code[$i], $text_color);
			$x += $char_w + random_int(2, 5);
		}
		
		imagecopyresampled($final_img, $base_img, 0, 0, 0, 0, $final_w, $final_h, $base_w, $base_h);
		imagedestroy($base_img);
		
	} elseif ($type === 2 || $type === 3 || $type === 4) {
		// METHOD 2, 3 & 4: SINE-WAVE DISTORTION (Combined rendering logic)
		$font = 5;
		$char_w = imagefontwidth($font);
		$char_h = imagefontheight($font);
		
		$display_string = ($type === 2) ? $_SESSION['captcha_code'] : $_SESSION['captcha_string_data'];
		$code_len = strlen($display_string);
		
		$base_w = ($char_w + 4) * $code_len + 10;
		$base_h = $char_h + 15;
		
		$base_img = imagecreatetruecolor($base_w, $base_h);
		
		// PROPER ALPHA TRANSPARENCY: Prevents overwriting Layer 1 with white blocks
		imagealphablending($base_img, false);
		imagesavealpha($base_img, true);
		$trans = imagecolorallocatealpha($base_img, 0, 0, 0, 127); // 127 = Fully transparent in GD
		imagefilledrectangle($base_img, 0, 0, $base_w, $base_h, $trans);
		imagealphablending($base_img, true);
		
		$x = 5;
		$col = imagecolorallocate($base_img, 20, 30, 80); // Dark Navy Blue
		for ($i = 0; $i < $code_len; $i++) {
			$y = random_int(2, $base_h - $char_h - 2);
			imagechar($base_img, $font, $x, $y, $display_string[$i], $col);
			$x += $char_w + random_int(2, 5);
		}
		
		// Sine-wave algorithm
		$drawn_h = $final_h - 20;
		$scale_x = $final_w / $base_w;
		$freq = random_int(3, 7) / 100;
		$amp = random_int(4, 8);
		
		for ($dst_x = 0; $dst_x < $final_w; $dst_x++) {
			$src_x = (int)($dst_x / $scale_x);
			$offset_y = (int)(sin($dst_x * $freq) * $amp) + 10;
			imagecopyresampled($final_img, $base_img, $dst_x, $offset_y, $src_x, 0, 1, $drawn_h, 1, $base_h);
		}
		imagedestroy($base_img);
	}
	
	// =========================================================================
	// LAYER 3: THIN FOREGROUND NOISE (Pixels and lines directly over text)
	// =========================================================================
	for ($i = 0; $i < 500; $i++) {
		$noise = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
		imagesetpixel($final_img, random_int(0, $final_w), random_int(0, $final_h), $noise);
	}
	for ($i = 0; $i < 15; $i++) {
		imagesetthickness($final_img, 1 );
		$noise = imagecolorallocate($final_img, random_int(140, 210), random_int(140, 210), random_int(140, 210));
		imageline($final_img, random_int(0, $final_w), random_int(0, $final_h), random_int(0, $final_w), random_int(0, $final_h), $noise);
	}

	if (ob_get_level()) ob_clean();
	header('Content-Type: image/png');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	imagepng($final_img); 
	imagedestroy($final_img);
	exit;
}


function isCaptchaBypassed() {
	global $api_key;
	if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
	return (!empty($_SESSION['waf_captcha_solved']) && !empty($_SESSION['waf_captcha_token']) && isset($_COOKIE['waf_captcha_clear']) && is_string($_COOKIE['waf_captcha_clear']) && hash_equals($_SESSION['waf_captcha_token'], $_COOKIE['waf_captcha_clear']));
}

function verifyCaptchaCode($code) {
	if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
	
	if (!isset($_SESSION['captcha_attempts'])) $_SESSION['captcha_attempts'] = 0;
	if ($_SESSION['captcha_attempts'] >= 3) {
		unset($_SESSION['captcha_code']);
		return false;
	}
	$_SESSION['captcha_attempts']++;
	
		$is_timeout = (!isset($_SESSION['captcha_time']) || (time() - $_SESSION['captcha_time'] > 30));
		
		if (!$is_timeout && isset($_SESSION['captcha_code']) && strtolower(trim($code)) === strtolower($_SESSION['captcha_code'])) {
		$_SESSION['waf_captcha_solved'] = true;
		$_SESSION['captcha_attempts'] = 0;
		unset($_SESSION['captcha_code']);
		unset($_SESSION['captcha_time']);
		global $api_key;
		$captcha_token = bin2hex(random_bytes(32));
		$_SESSION['waf_captcha_token'] = $captcha_token;
		$is_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
		setcookie('waf_captcha_clear', $captcha_token, 0, '/', '', $is_secure, true);
		return true;
	}
	unset($_SESSION['captcha_code']); // Force regeneration on fail
	unset($_SESSION['captcha_time']);
	return false;
}