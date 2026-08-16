<?php
/**
 * ============================================================================
 * MODULE: Email Image Proxy & Sanitizer
 * ============================================================================
 * Masks the user's IP, OS, and Browser from sender tracking scripts.
 * Eliminates 1x1 tracking pixels and physically sanitizes image payloads.
 * Conserves native transparency for PNG, GIF, and WebP formats.
 * Bypasses GD for animated GIFs to preserve animation while blocking XSS via headers.
 */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) die('Direct access not permitted');

class MyCloudEmailImageProxy {
    
    public static function handleRequest() {
        if (empty($_GET['myCloud_email_proxy_img'])) return;
        
        // ZERO TRUST: Require session-bound CSRF token to prevent external open proxy abuse
        session_start(['read_and_close' => true]);
        if (empty($_GET['proxy_token']) || $_GET['proxy_token'] !== ($_SESSION['myCloud_csrf_token'] ?? '')) {
            self::serveBlank();
        }

        $urlBase64 = $_GET['myCloud_email_proxy_img'];
        $url = base64_decode($urlBase64);
        
        // --- SERVER-SIDE CACHING ENGINE ---
        $cacheDir = rtrim(__DIR__ . '/../data', '/\\') . '/.email_img_cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);


        $urlHash = hash('sha256', $url);
        $cacheMatches = glob($cacheDir . '/' . $urlHash . '.*');
        
        if (!empty($cacheMatches)) {
            $cacheFile = $cacheMatches[0];
            // Serve from cache if younger than 14 days
            if (time() - filemtime($cacheFile) < 1209600) {
                $ext = pathinfo($cacheFile, PATHINFO_EXTENSION);
                $mimeMap = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
                $mime = $mimeMap[$ext] ?? 'image/jpeg';
                
                $mtime = filemtime($cacheFile);
                $gmdate_mod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
                
                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime) {
                    header('HTTP/1.1 304 Not Modified');
                    exit;
                }
                
                header('Last-Modified: ' . $gmdate_mod);
                header('Cache-Control: private, max-age=604800, immutable');
                header('Content-Type: ' . $mime);
				header('Content-Length: ' . filesize($cacheFile));
                readfile($cacheFile);
                exit;
            }
            @unlink($cacheFile); // Delete expired cache
        }

        // --- URL ANONYMIZATION: Strip known tracking parameters ---
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            
            // Blocklist of common email marketing and analytics trackers
            $trackingKeys = [
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'mc_cid', 'mc_eid',         // Mailchimp
                'mkt_tok',                  // Marketo
                'trk_msg', 'trk_contact',   // General tracking
                'open_id', 'recipient_id', 'subscriber_id', 'user_id',
                'tracking_id', 'click_id', 'camp_id'
            ];

            $cleanedParams = [];
            $paramsStripped = false;
            
			
            // POISON POOL: Fake attribution data to ruin their marketing metrics
            $poisonData = [
                'organic_search', 'direct_traffic', 'null', 'undefined', 'opt_route',
                'social_media_ad', 'false', '0', 'test_user', 'deleted',
                bin2hex(random_bytes(4)), hash('crc32', microtime())
            ];

            foreach ($queryParams as $key => $value) {
                $keyLower = strtolower($key);
                $isTracker = false;
                foreach ($trackingKeys as $tracker) {
                    if (strpos($keyLower, $tracker) !== false) {
                        $isTracker = true;
                        $paramsStripped = true;
                        break;
                    }
                }
                if ($isTracker) {
                    // Overwrite the tracking parameter with database-polluting garbage
                    $cleanedParams[$key] = $poisonData[array_rand($poisonData)];
                    $paramsStripped = true;
                } else {
                    $cleanedParams[$key] = $value;
                }
            }

            // Rebuild the URL if trackers were found and removed
            if ($paramsStripped) {
                $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . 
                       (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '') . 
                       (isset($parsedUrl['path']) ? $parsedUrl['path'] : '');
                
                if (!empty($cleanedParams)) {
                    $url .= '?' . http_build_query($cleanedParams);
                }
            }
        }

        // 1. PREVENT SSRF & LFI ATTACKS
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
            self::serveBlank();
        }
        
        $host = parse_url($url, PHP_URL_HOST);
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            self::serveBlank();
        }

        // --- DEVICE & OS ROULETTE ---
        // Spoof a realistic user agent to destroy their device analytics.
        $uaPool = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36 Edg/115.0.1901.203',
            'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0',
            'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36',
        ];
        $ua = $uaPool[array_rand($uaPool)];
        
        // Randomize Accept-Language to ruin geographic profiling
        $langPool = ['en-US,en;q=0.9', 'de-DE,de;q=0.9,en;q=0.8', 'fr-FR,fr;q=0.9,en;q=0.8', 'es-ES,es;q=0.9', 'en-GB,en;q=0.9'];
        $lang = $langPool[array_rand($langPool)];

        // 2. FETCH IMAGE SECURELY
        $ch = curl_init($url);

        // Prevent DNS Rebinding SSRF by resolving to the validated IP
        $port = parse_url($url, PHP_URL_PORT) ?: (strtolower(parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Disable redirects to prevent routing to private IPs
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: ' . $lang, 'Accept: image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        // Hard limit download size to 8MB to prevent RAM exhaustion DoS
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) {
            return ($downloaded > 8 * 1024 * 1024) ? 1 : 0;
        });

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($ch);

        if ($httpCode !== 200 || empty($data)) {
            self::serveBlank();
        }

        // 3. SANITIZE CONTENT (Reject SVG payloads)
        if (strpos($contentType, 'image/') !== 0 || strpos($contentType, 'svg') !== false) {
            self::serveBlank();
        }

        // Detect the actual image format to route to the correct output handler
        $info = @getimagesizefromstring($data);
        if (!$info) {
            self::serveBlank();
        }
        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];

        // 4. TRACKER ELIMINATION (Drop 1x1 or 2x2 spy pixels)
        // Check dimensions from headers before allocating RAM
        if ($width <= 2 && $height <= 2) {
            self::serveBlank();
        }

        // 5. ANIMATED GIF PRESERVATION
        // PHP's GD library extracts only the first frame. We must pass animated GIFs raw.
        if ($mime === 'image/gif' && strpos($data, 'NETSCAPE2.0') !== false) {
            header('Cache-Control: private, max-age=604800');
            header('Content-Type: image/gif');
            header('X-Content-Type-Options: nosniff'); // Strictly prevent polyglot XSS execution
            echo $data;
            exit;
        }

        // Re-encoding via GD physically destroys EXIF data and embedded XSS
        $img = @imagecreatefromstring($data);
        if (!$img) {
            self::serveBlank();
        }

        // 6. TRANSPARENCY CONSERVATION (For static images)
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        // 7. DELIVER SAFE PAYLOAD
        header('Cache-Control: private, max-age=604800'); 
        header('Content-Type: ' . $mime);

        // Capture GD output into a buffer to save to cache
        ob_start();
        $saveExt = 'jpg';
        
        switch ($mime) {
            case 'image/png':
                imagepng($img);
				$saveExt = 'png';
                break;
            case 'image/gif':
                imagegif($img);
				$saveExt = 'gif';
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagewebp($img);
					$saveExt = 'webp';
                } else {
                    // Fallback to PNG if the server's GD library lacks WebP support
                    header('Content-Type: image/png');
                    imagepng($img);
					$saveExt = 'png';
                }
                break;
            default:
                header('Content-Type: image/jpeg');
                imagejpeg($img, null, 85);
				$saveExt = 'jpg';
                break;
        }
        
        $finalPayload = ob_get_clean();
		imagedestroy($img);
		
        // Save to cache
        @file_put_contents($cacheDir . '/' . $urlHash . '.' . $saveExt, $finalPayload);
        
        // Probabilistic Garbage Collection (Runs ~1% of the time)
        if (rand(1, 100) === 1) {
            self::garbageCollectCache($cacheDir);
        }

        echo $finalPayload;

        exit;
    }

    private static function garbageCollectCache($dir) {
        $files = glob($dir . '/*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) >= 1209600)) { // 14 Days
                @unlink($file);
            }
        }
    }
    private static function serveBlank() {
        header('Content-Type: image/gif');
        header('Cache-Control: private, max-age=604800');
        // Transparent 1x1 GIF
        echo base64_decode('R0lGODlhAQABAIAAAP///wAAACwAAAAAAQABAAACAkQBADs=');
        exit;
    }
}