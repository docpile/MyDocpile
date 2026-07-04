<?php
/**
 * ============================================================================
 * MODULE: Standalone Public Share Handler
 * ============================================================================
 * INCLUDES: Gallery Mode + Shared Server-Side Caching (Exact Mirror) + File Previews + Bulk Selection
 */

// Allow PHP to handle massive files without crashing
@ini_set('memory_limit', '512M');
@set_time_limit(0);

// ============================================================================
// CONFIGURATION - MUST MATCH MAIN APP EXACTLY FOR SHARED CACHE
// ============================================================================
$cloud_share_db     = $GLOBALS['cloud_share_db'] ?? __DIR__ . '/shares.json';
$blocklist_file     = $GLOBALS['cloud_public_blocklist'] ?? __DIR__ . '/blocklist.json';

$cloud_icon_cache    = $GLOBALS['cloud_icon_cache'] ?? __DIR__ . '/_cache_icons'; 
$cloud_preview_cache = $GLOBALS['cloud_preview_cache'] ?? __DIR__ . '/_cache_previews';
// ============================================================================

$quota_str = $GLOBALS['cloud_public_quotas'] ?? '4GB';
$max_zip_size = $GLOBALS['cloud_public_max_zip_size'] ?? 300 * 1024 * 1024;
$max_login_attempts = $GLOBALS['cloud_public_max_login_attempts'] ?? 5;
$min_guid_length = 20;

// Zero Trust: Prevent Clickjacking
header("X-Frame-Options: SAMEORIGIN");

// --- I18N SETUP ---
$userLang = 'en';
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $browserLang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
    if ($browserLang === 'de') $userLang = 'de';
}

$trans = [
    'en' => [
        'btn_select_all' => "Select All",
        'btn_deselect_all' => "Deselect All",
        'msg_prep_zip' => "Preparing ZIP file... Please wait until the download starts, also aftger the end of this toast.",
		'err_access_denied' => "Too many failed attempts. Access denied.",
        'err_invalid_link' => "Invalid share link format.",
        'err_unavailable' => "Share no longer available.",
        'err_locked' => "This share is temporarily locked due to too many failed password attempts. Try again later.",
        'err_expired' => "Share expired.",
        'err_csrf' => "CSRF validation failed.",
        'err_logout' => "Invalid logout request.",
        'err_file_unavailable' => "File unavailable.",
        'err_pass_incorrect' => "Incorrect password",
        'login_title' => "Password Protected Share",
        'login_placeholder' => "Enter Share Password",
        'login_btn' => "Access",
        'err_invalid_path' => "Invalid path.",
        'err_quota' => "Quota exceeded. ",
        'err_upload_dir' => "Could not create upload directory.",
        'err_upload_path' => "Upload path error.",
        'err_zip_size' => "Folder or selection too large to download as ZIP.",
        'err_zip_missing' => "ZIP extension missing.",
        'err_zip_create' => "Cannot create zip.",
        'err_file_open' => "Cannot open file.",
        'err_file_preview' => "Error loading image. Previewing for this file is not possible in your browser.",
        'shared_file_title' => "Shared File",
        'shared_file_msg' => "The file <span class='filename'>%s</span> has been shared with you.",
        'btn_download' => "Download Now",
        'header_shared' => "Shared: ",
        'header_upload' => "Upload",
        'btn_upload' => "Upload Files",
        'btn_folder' => "+ Folder",
        'btn_zip' => "Download all as ZIP",
        'warn_zip' => "Folder too large to Zip",
        'btn_back' => "&larr; Back",
        'empty_folder' => "Folder is empty.",
        'upload_drag' => "Drag and drop files or folders here. Or click the button below to upload.",
        'btn_select' => "Select Files",
        'modal_uploading' => "Uploading...",
        'modal_stay' => "Please do not close this tab.",
        'js_leave' => "Upload in progress. Are you sure you want to leave?",
        'js_prompt_folder' => "Enter folder name:",
        'js_confirm_del' => "Really delete '%s'? This cannot be undone.",
        'js_success' => "Upload successful.",
        'js_finished' => "Upload finished.",
        'js_failed' => "Upload failed",
        'js_skipped' => " file(s) skipped/blocked.",
        'hint_click' => "Click a file or tap it twice for a full-screen preview, or use the download icon.",
        'view_list' => "List View",
        'view_grid' => "Gallery View",
        'btn_bulk_zip' => "Download ZIP",
        'btn_bulk_files' => "Download all Files",
        'sel_items' => "%s selected",
        'err_no_files' => "No files selected (folders cannot be downloaded individually)."
    ],
    'de' => [
        'btn_select_all' => "Alle auswählen",
        'btn_deselect_all' => "Auswahl aufheben",
        'msg_prep_zip' => "ZIP-Datei wird vorbereitet... Bitte warten bis die Datei heruntergeladen wird, auch nach dem Ende dieses Popups.",
		'err_access_denied' => "Zu viele fehlgeschlagene Versuche. Zugriff verweigert.",
        'err_invalid_link' => "Ungültiges Link-Format.",
        'err_unavailable' => "Freigabe nicht mehr verfügbar.",
        'err_locked' => "Freigabe vorübergehend gesperrt (zu viele falsche Eingaben). Bitte später versuchen.",
        'err_expired' => "Freigabe abgelaufen.",
        'err_csrf' => "CSRF-Validierung fehlgeschlagen.",
        'err_logout' => "Ungültige Abmeldeanfrage.",
        'err_file_unavailable' => "Datei nicht verfügbar.",
        'err_pass_incorrect' => "Falsches Passwort",
        'login_title' => "Passwortgeschützte Freigabe",
        'login_placeholder' => "Passwort eingeben",
        'login_btn' => "Zugriff",
        'err_invalid_path' => "Ungültiger Pfad.",
        'err_quota' => "Speicherplatz erschöpft. ",
        'err_upload_dir' => "Upload-Verzeichnis konnte nicht erstellt werden.",
        'err_upload_path' => "Fehler im Upload-Pfad.",
        'err_zip_size' => "Ordner oder Auswahl zu groß für ZIP-Download.",
        'err_zip_missing' => "ZIP-Erweiterung fehlt.",
        'err_zip_create' => "ZIP konnte nicht erstellt werden.",
        'err_file_open' => "Datei konnte nicht geöffnet werden.",
        'err_file_preview' => "Fehler beim Laden des Bildes.<br>Die Vorschau ist in diesem Browser nicht möglich.",
        'shared_file_title' => "Geteilte Datei",
        'shared_file_msg' => "Die Datei <span class='filename'>%s</span> wurde geteilt.",
        'btn_download' => "Jetzt herunterladen",
        'header_shared' => "Geteilt: ",
        'header_upload' => "Upload",
        'btn_upload' => "Dateien hochladen",
        'btn_folder' => "+ Ordner",
        'btn_zip' => "Alles als ZIP herunterladen",
        'warn_zip' => "Ordner zu groß für ZIP",
        'btn_back' => "&larr; Zurück",
        'empty_folder' => "Ordner ist leer.",
        'upload_drag' => "Dateien oder Ordner entweder hierher ziehen und ablegen, oder den Button klicken.",
        'btn_select' => "Dateien wählen",
        'modal_uploading' => "Wird hochgeladen...",
        'modal_stay' => "Bitte diesen Tab nicht schließen.",
        'js_leave' => "Upload läuft. Seite wirklich verlassen?",
        'js_prompt_folder' => "Ordnername eingeben:",
        'js_confirm_del' => "'%s' wirklich löschen? Das kann nicht rückgängig gemacht werden.",
        'js_success' => "Upload erfolgreich.",
        'js_finished' => "Upload beendet.",
        'js_failed' => "Upload fehlgeschlagen",
        'js_skipped' => " Datei(en) übersprungen/blockiert.",
        'hint_click' => "Datei anklicken oder zweimal antippen für Vollbild-Vorschau, oder das Download-Icon nutzen.",
        'view_list' => "Liste",
        'view_grid' => "Galerie",
        'btn_bulk_zip' => "Als ZIP herunterladen",
        'btn_bulk_files' => "Dateien einzeln herunterladen",
        'sel_items' => "%s ausgewählt",
        'err_no_files' => "Keine einzelnen Dateien ausgewählt (Ordner können nicht einzeln geladen werden)."
    ]
];

function cxLang($key, $arg = null) {
    global $trans, $userLang;
    $str = $trans[$userLang][$key] ?? $trans['en'][$key] ?? $key;
    if ($arg !== null) return sprintf($str, $arg);
    return $str;
}

// --- HELPERS ---
function cxParseQuota($str) {
    $str = strtolower(trim($str));
    if (empty($str)) return PHP_INT_MAX;
    if (substr($str, -1) === 'b') $str = substr($str, 0, -1);
    $last = substr($str, -1);
    $val = (float)$str;
    switch($last) {
        case 't': $val *= 1024; case 'g': $val *= 1024; case 'm': $val *= 1024; case 'k': $val *= 1024;
    }
    return (int)$val;
}

function cxLoadBlocklist() {
    global $blocklist_file;
    if (!file_exists($blocklist_file)) return [];
    $fp = fopen($blocklist_file, 'r');
    if (!$fp) return [];
    $data = [];
    if (flock($fp, LOCK_SH)) {
        $json = stream_get_contents($fp);
        $data = json_decode($json, true) ?? [];
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return is_array($data) ? $data : [];
}

function cxSaveBlocklist($data) {
    global $blocklist_file;
    $fp = fopen($blocklist_file, 'c+');
    if (!$fp) return;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($data, JSON_PRETTY_PRINT)); fflush($fp); flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function cxCheckRateLimit($ip) {
    global $max_login_attempts;
    $data = cxLoadBlocklist();
    $now = time();
    if (isset($data[$ip])) {
        if (isset($data[$ip]['until']) && $now > $data[$ip]['until']) {
            unset($data[$ip]); cxSaveBlocklist($data); return true;
        }
        if (isset($data[$ip]['count']) && $data[$ip]['count'] >= $max_login_attempts) return false;
    }
    return true;
}

function cxRegisterFail($ip) {
    $data = cxLoadBlocklist();
    $now = time();
    if (!isset($data[$ip])) $data[$ip] = ['count' => 0, 'until' => $now + 900];
    $data[$ip]['count']++;
    cxSaveBlocklist($data);
}

function cloudExPublicLoad() {
    global $cloud_share_db;
    if (!file_exists($cloud_share_db)) return [];
    $fp = fopen($cloud_share_db, 'r');
    if (!$fp) return [];
    $data = [];
    if (flock($fp, LOCK_SH)) {
        $json = stream_get_contents($fp);
        $data = json_decode($json, true) ?? [];
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return is_array($data) ? $data : [];
}

function cloudExPublicSave($shares) {
    global $cloud_share_db;
    $fp = fopen($cloud_share_db, 'c+');
    if (!$fp) return;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($shares, JSON_PRETTY_PRINT)); fflush($fp); flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function cxIncrementShareAttempts($guid) {
    $shares = cloudExPublicLoad();
    if (!isset($shares[$guid])) return;
    if (!isset($shares[$guid]['attempts'])) $shares[$guid]['attempts'] = 0;
    $shares[$guid]['attempts']++;
    if (!isset($shares[$guid]['attempt_window_start']) || time() > $shares[$guid]['attempt_window_start'] + 3600) {
        $shares[$guid]['attempt_window_start'] = time();
        $shares[$guid]['attempts'] = 1;
    }
    if ($shares[$guid]['attempts'] >= 15) {
        $shares[$guid]['locked_until'] = time() + 3600;
        $shares[$guid]['attempts'] = 0;
        $shares[$guid]['attempt_window_start'] = time();
    }
    cloudExPublicSave($shares);
}

function cxIsShareLocked($guid) {
    $shares = cloudExPublicLoad();
    return isset($shares[$guid]['locked_until']) && time() < $shares[$guid]['locked_until'];
}

function cxRecordDownload($guid) {
    global $cloud_share_db;
    $fp = fopen($cloud_share_db, 'c+');
    if (!$fp) return;
    if (flock($fp, LOCK_EX)) {
        $json = stream_get_contents($fp);
        $shares = json_decode($json, true) ?? [];
        if (isset($shares[$guid])) {
            $max = isset($shares[$guid]['max_downloads']) ? (int)$shares[$guid]['max_downloads'] : 0;
            $current = isset($shares[$guid]['downloads']) ? (int)$shares[$guid]['downloads'] : 0;
            $shares[$guid]['downloads'] = $current + 1;
            if ($max > 0 && $shares[$guid]['downloads'] >= $max) unset($shares[$guid]);
            ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($shares, JSON_PRETTY_PRINT)); fflush($fp);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function cloudExGetAllowedRoots() {
    $allowed = [];
    if (isset($GLOBALS['user_details']) && is_array($GLOBALS['user_details'])) {
        foreach ($GLOBALS['user_details'] as $user) {
            if (isset($user['cloud']) && is_array($user['cloud'])) {
                foreach ($user['cloud'] as $sharePoint) {
                    if (!empty($sharePoint['path'])) {
                        $resolved = realpath($sharePoint['path']);
                        if ($resolved && is_dir($resolved)) $allowed[] = $resolved;
                    }
                }
            }
        }
    }
    return array_unique($allowed);
}

function cxFmtBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function cxGetDirSize($path, $limit = null) {
    $size = 0;
    try {
        $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO));
        foreach ($dirIterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                if ($limit !== null && $size > $limit) return $size;
            }
        }
    } catch (Exception $e) { return $size; }
    return $size;
}

function cxRemoveRecursive($path) {
    if (is_dir($path)) {
        $objects = scandir($path);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $full = $path . DIRECTORY_SEPARATOR . $object;
                if (is_link($full)) unlink($full);
                elseif (is_dir($full)) cxRemoveRecursive($full);
                else unlink($full);
            }
        }
        rmdir($path);
    } elseif (is_file($path) || is_link($path)) unlink($path);
}

function cxIsSafeFile($name, $tmpPath) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'tiff', 'tif', 'heic', 'heif', 'avif', 'dng', 'cr2', 'cr3', 'nef', 'arw', 'orf', 'raf', 'rw2', 'pef', 'srw', 'x3f', 'erf', 'iiq', 'psd', 'ai', 'eps', 'indd', 'idml', 'indt', 'afdesign', 'afphoto', 'afpub', 'af', 'qxp', 'qxd', 'cdr', 'cmx', 'sketch', 'xcf', 'mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', '3gp', 'ogv', 'mts', 'm2ts', 'ts', 'vob', 'mxf', 'pro', 'mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'wma', 'alac', 'aiff', 'mp2', 'mid', 'midi', 'opus', 'amr', 'doc', 'docx', 'dotx', 'xls', 'xlsx', 'xltx', 'ppt', 'pptx', 'potx', 'pub', 'vcf', 'ics', 'odt', 'ods', 'odp', 'odg', 'pages', 'numbers', 'key', 'pdf', 'txt', 'rtf', 'csv', 'md', 'json', 'xml', 'log', 'msg', 'epub', 'mobi', 'azw3', 'cbr', 'cbz', 'stl', 'obj', 'fbx', 'glb', 'gltf', 'blend', '3ds', 'dae', 'dwg', 'dxf', 'step', 'stp', 'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'iso', 'dmg', 'img'];
    if (!in_array($ext, $allowed, true)) return false;
    if ($name === '.htaccess' || $name === '.user.ini') return false;

    // Prevent Apache double-extension execution attacks (e.g. payload.php.jpg)
    if (preg_match('/\.ph(p[34578]?|t|tml|ar)\./i', $name)) return false;

    if (!is_uploaded_file($tmpPath)) return false;
    if (file_exists($tmpPath)) {
        $handle = fopen($tmpPath, 'rb');
        if ($handle) {
            $bytes = fread($handle, 1024);
            fclose($handle);
            if (strpos($bytes, '<?php') !== false || substr($bytes, 0, 2) === 'MZ' || substr($bytes, 0, 4) === "\x7FELF" || substr($bytes, 0, 2) === '#!') return false;
        }
    }
    return true;
}

function cxGetIcon($isDir, $filename) {
    $color = $isDir ? '#FFC107' : '#0078d4';
    if ($isDir) return '<svg width="24" height="24" viewBox="0 0 24 24" fill="'.$color.'" style="vertical-align:middle;margin-right:8px;"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="'.$color.'" style="vertical-align:middle;margin-right:8px;"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>';
}

function cxGetDeleteIcon() {
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="#d9534f" style="vertical-align:middle;cursor:pointer"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
}

function cxParseMarkdown($text) {
    // Escape all HTML to prevent XSS from malicious readme files
    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    $text = str_replace("\r\n", "\n", $text);
    
    // Fenced Code Blocks (Process before inline code and formatting)
    $text = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $text);
    
    // Inline Code
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    // Bold, Italic, Strikethrough, and Highlight
    $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/_([^_`]+?)_/s', '<em>$1</em>', $text);
    $text = preg_replace('/\*([^\*`]+?)\*/s', '<em>$1</em>', $text);
    $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text);
    $text = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $text);

    $text = preg_replace('/\[color:([a-zA-Z]+|#[a-fA-F0-9]{3,8})\](.*?)\[\/color\]/is', '<span class="cx-color" style="color:$1;">$2</span>', $text);

    // Horizontal Rules
    $text = preg_replace('/^(?:\-{3,}|\*{3,}|_{3,})$/m', '<hr>', $text);

    // Strict Autolinks (<https://...>)
    $text = preg_replace('/<((?:https?|ftp):\/\/[^>]+)>/i', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    // Images (Process before links)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%; height:auto;">', $text);
    $text = preg_replace('/src="javascript:[^"]*"/i', 'src="#"', $text);

    // Links (Block javascript: protocol for security, negative lookbehind to skip images)
    $text = preg_replace('/(?<!!)\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    $text = preg_replace('/href="javascript:[^"]*"/i', 'href="#"', $text);
    
    // Headers (H1 - H6)
    $text = preg_replace_callback('/^(#{1,6})\s+(.+)$/m', function($m) {
        $h = strlen($m[1]);
        return "<h{$h}>" . trim($m[2]) . "</h{$h}>";
    }, $text);
    
    // Blockquotes
    $text = preg_replace('/^>\s+(.+)$/m', '<blockquote>$1</blockquote>', $text);
    
    // Tables
    $text = preg_replace_callback('/^(\|.*\|)\n(\|[-:| ]+\|)\n((?:\|.*\|(?:\n|$))*)/m', function($m) {
        $processRow = function($rowStr) {
            $rowStr = trim($rowStr);
            if (substr($rowStr, 0, 1) === '|') $rowStr = substr($rowStr, 1);
            if (substr($rowStr, -1) === '|') $rowStr = substr($rowStr, 0, -1);
            return explode('|', $rowStr);
        };

        $html = "<table>\n<thead>\n<tr>";
        foreach ($processRow($m[1]) as $th) {
            $html .= "<th>" . trim($th) . "</th>";
        }
        $html .= "</tr>\n</thead>\n";
        
        $tbody = trim($m[3]);
        if (!empty($tbody)) {
            $html .= "<tbody>\n";
            $rows = explode("\n", $tbody);
            foreach ($rows as $row) {
                $html .= "<tr>";
                foreach ($processRow($row) as $cell) {
                    $html .= "<td>" . trim($cell) . "</td>";
                }
                $html .= "</tr>\n";
            }
            $html .= "</tbody>\n";
        }
        $html .= "</table>";
        return $html;
    }, $text);

    // Task Lists (Process BEFORE standard Unordered Lists)
    $text = preg_replace('/^[\*\-]\s+\[ \]\s+(.+)$/m', '<li class="ul-item task-list"><input type="checkbox" disabled> $1</li>', $text);
    $text = preg_replace('/^[\*\-]\s+\[[xX]\]\s+(.+)$/m', '<li class="ul-item task-list"><input type="checkbox" checked disabled> $1</li>', $text);

    // Lists (Unordered and Ordered)
    $text = preg_replace('/^[\*\-]\s+(.+)$/m', '<li class="ul-item">$1</li>', $text);
    $text = preg_replace('/^\d+\.\s+(.+)$/m', '<li class="ol-item">$1</li>', $text);
    
    // Wrap Lists
    $text = preg_replace('/((?:<li class="ul-item(?: task-list)?">.*?<\/li>\n?)+)/s', "<ul>\n$1</ul>\n", $text);
    $text = preg_replace('/((?:<li class="ol-item">.*?<\/li>\n?)+)/s', "<ol>\n$1</ol>\n", $text);
    
    // Clean up temporary list classes
    $text = preg_replace('/ class="(?:ul-item|ol-item)(?: task-list)?"/', '', $text);
    
    // Paragraphs
    $blocks = explode("\n\n", $text);
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        // Prevent wrapping block-level elements in <p> tags
        if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote|img|hr|pre|table)/i', $block)) {
            $html .= $block . "\n";
        } else {
            $html .= "<p>" . nl2br($block) . "</p>\n";
        }
    }
    
    // Merge adjacent blockquotes into a single blockquote with linebreaks
    $html = preg_replace('/<\/blockquote>\n*<blockquote>/s', "<br>\n", $html);

    return trim($html);
}

function cxEnsureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0, 
                'path' => '/', 
                'secure' => $isSecure, 
                'httponly' => true, 
                'samesite' => 'Lax'
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', null, $isSecure, true);
        }
        session_start();
    }
}

// ============================================================================
// SHARED CACHING LOGIC (COPIED FROM server.beta.php)
// ============================================================================
function cxGenerateThumbnail($source, $dest) {
    $maxDim = 300; // Size for share thumbnails
    
    if (file_exists($dest) && filemtime($dest) >= filemtime($source)) return true;

    // 1. MEMORY SAFEGUARD (PREVENT FATAL ERRORS)
    // getimagesize() reads only the file header, taking almost 0 RAM.
    $imgInfo = @getimagesize($source);
    if (!$imgInfo || !isset($imgInfo[0], $imgInfo[1], $imgInfo['mime'])) return false;
    
    $w = $imgInfo[0];
    $h = $imgInfo[1];
    $mime = strtolower($imgInfo['mime']);
    
    // GD requires ~4 bytes per pixel. Imagick can require up to ~8 bytes.
    // Capping at 45,000,000 pixels (~45 Megapixels) keeps RAM usage well inside the 512M limit.
    if (($w * $h) > 45000000) {
        return false; // Image too huge, skip thumbnail to prevent fatal memory crash
    }

    $generated = false;

    // 2. IMAGICK
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            if (in_array($mime, ['image/jpeg', 'image/jpg'])) {
                // [OPTIMIZATION] Tell libjpeg to read a smaller version on load, slashing memory usage
                $im->setOption('jpeg:size', ($maxDim * 2) . 'x' . ($maxDim * 2));
            }
            $im->readImage($source);
            $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            
            if ($im->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
                $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }
            
            $im->setImageFormat('jpg');
            
            switch($im->getImageOrientation()) {
                case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateimage("#000", 180); break;
                case Imagick::ORIENTATION_RIGHTTOP: $im->rotateimage("#000", 90); break;
                case Imagick::ORIENTATION_LEFTBOTTOM: $im->rotateimage("#000", -90); break;
                case Imagick::ORIENTATION_LEFTTOP: $im->flopImage(); $im->rotateImage("#000", 90); break;
                case Imagick::ORIENTATION_RIGHTBOTTOM: $im->flopImage(); $im->rotateImage("#000", -90); break;
            }
            $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $d = $im->getImageGeometry();
            
            if ($d['width'] > $maxDim || $d['height'] > $maxDim) {
                $im->resizeImage($maxDim, $maxDim, Imagick::FILTER_LANCZOS, 1, true);
            }
            
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(60);
            $im->writeImage($dest);
            $im->clear();
            $im->destroy();
            $generated = true;
        } catch (Exception $e) {} catch (Error $e) {}
    }

    // 3. GD FALLBACK
    if (!$generated && extension_loaded('gd')) {
        $ratio = $w / $h;
        if ($w > $maxDim || $h > $maxDim) {
            if ($ratio > 1) { $nw = $maxDim; $nh = $maxDim / $ratio; }
            else { $nh = $maxDim; $nw = $maxDim * $ratio; }
        } else { 
            $nw = $w; $nh = $h; 
        }
        
        $srcImg = null;
        
        // Route processing based on actual MIME type from header, not fake file extensions
        switch($mime) {
            case 'image/jpeg': case 'image/jpg': case 'image/pjpeg':
                $srcImg = @imagecreatefromjpeg($source); 
                break;
            case 'image/png': 
                $srcImg = @imagecreatefrompng($source); 
                break;
            case 'image/webp': 
                if (function_exists('imagecreatefromwebp')) $srcImg = @imagecreatefromwebp($source); 
                break;
            case 'image/gif': 
                $srcImg = @imagecreatefromgif($source); 
                break;
            case 'image/bmp': case 'image/x-ms-bmp':
                if (function_exists('imagecreatefrombmp')) $srcImg = @imagecreatefrombmp($source); 
                break;
        }

        if ($srcImg) {
            if (function_exists('exif_read_data') && in_array($mime, ['image/jpeg', 'image/jpg'])) {
                $exif = @exif_read_data($source, 'ANY_TAG', true);
                if (isset($exif['IFD0']['Orientation'])) {
                    $orientation = $exif['IFD0']['Orientation'];
                    switch($orientation) {
                        case 3: $srcImg = imagerotate($srcImg, 180, 0); break;
                        case 6: $srcImg = imagerotate($srcImg, -90, 0); break;
                        case 8: $srcImg = imagerotate($srcImg, 90, 0); break;
                    }
                    if (in_array($orientation, [6, 8])) {
                        $w = imagesx($srcImg); $h = imagesy($srcImg);
                        $ratio = $w / $h;
                        if ($w > $maxDim || $h > $maxDim) {
                            if ($ratio > 1) { $nw = $maxDim; $nh = $maxDim / $ratio; }
                            else { $nh = $maxDim; $nw = $maxDim * $ratio; }
                        } else { $nw = $w; $nh = $h; }
                    }
                }
            }
            
            $dst = imagecreatetruecolor((int)$nw, (int)$nh);
            $bg = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $bg);
            
            if (in_array($mime, ['image/png', 'image/webp'])) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $transparent);
            }

            imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, (int)$nw, (int)$nh, $w, $h);
            imagejpeg($dst, $dest, 60);
            
            imagedestroy($srcImg); 
            imagedestroy($dst);
            
            $generated = true;
        }
    }
    return $generated;
}

function cxServeIcon($fullPath) {
    global $cloud_icon_cache;
    
    if (!file_exists($fullPath)) { header("HTTP/1.1 404 Not Found"); exit; }

    // [PERFORMANCE] Unlock session immediately so background tiles don't queue
    session_write_close();

    // Replicate absolute path structure: Remove ':' and leading slashes
    $safePath = ltrim(str_replace(':', '', $fullPath), '/\\');
    
    $cacheFile = rtrim($cloud_icon_cache, '/') . '/' . $safePath . '_thumb.jpg';
    $cachePath = dirname($cacheFile);

    if (!is_dir($cachePath)) @mkdir($cachePath, 0755, true);

    if (!file_exists($cacheFile) || filemtime($fullPath) > filemtime($cacheFile)) {
        cxGenerateThumbnail($fullPath, $cacheFile);
    }

    if (file_exists($cacheFile)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($cacheFile);
    } else {
        header("HTTP/1.1 404 Not Found");
    }
    exit;
}

function cxLogAction($shareName, $action, $result, $details = '', $targetPath = '') {
    // Use the global $list_dir, fallback to the script's directory if undefined
    $logDir = $GLOBALS['list_dir'] ?? __DIR__;
    $logFile = rtrim($logDir, '/\\') . '/shared_actions.txt';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    // Define the raw data fields
    $fields = [
        date('Y-m-d'),
        date('H:i:s'),
        $ip,
        $shareName,
        $action,
        $result,
        $details,
        $targetPath
    ];
    
    // STRIP TABS AND NEWLINES FROM EVERY FIELD
    // This ensures that even if a filename contains a tab, it won't break the log format
    foreach ($fields as &$field) {
        $field = str_replace(["\t", "\n", "\r"], ' ', (string)$field);
        // Trim to remove any leading/trailing whitespace that might look like a tab
        $field = trim($field);
    }
    
    // Create the tab-delimited string
    $logLine = implode("\t", $fields) . "\n";
    
    // Append to file
    $fp = @fopen($logFile, 'a');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $logLine);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

if (isset($_GET['cloudshare'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'];
    if (!cxCheckRateLimit($clientIp)) die(cxLang('err_access_denied'));

    $guid = $_GET['cloudshare'];
    if (!preg_match('/^[a-zA-Z0-9_-]{'.$min_guid_length.',128}$/', $guid)) die(cxLang('err_invalid_link'));

    $shares = cloudExPublicLoad();
    if (!isset($shares[$guid])) die(cxLang('err_unavailable'));
    $share = $shares[$guid];
	$shareName = $share['name'] ?? $guid;
	$readmePos = $share['readme_pos'] ?? 'bottom';

    $maxDL = $share['max_downloads'] ?? 0;
    $curDL = $share['downloads'] ?? 0;
    if ($maxDL > 0 && $curDL >= $maxDL) { unset($shares[$guid]); die(cxLang('err_unavailable')); }
    if (cxIsShareLocked($guid)) { cxLogAction($shareName, '🔒 LOGIN', '🛡 ️BLOCKED', 'Locked due to failures', ''); die(cxLang('err_locked')); }
    if (!empty($share['expires']) && time() > $share['expires']) die(cxLang('err_expired'));

    if (isset($_GET['logout'])) {
        cxEnsureSession();
        if (!isset($_SESSION['cx_csrf'])) $_SESSION['cx_csrf'] = bin2hex(random_bytes(32));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf']) || !hash_equals($_SESSION['cx_csrf'], $_POST['csrf'])) die(cxLang('err_csrf'));
            cxLogAction($shareName, '❎ LOGOUT', '✅ SUCCESS', 'Manual logout', '');
			session_destroy();
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . '?cloudshare=' . $guid); exit;
        }
        die(cxLang('err_logout'));
    }

    if (isset($_GET['tab_logout'])) {
        // If silent, just log it. Do NOT destroy the session.
        if (isset($_GET['silent'])) {
            exit; 
        } else {
            // Manual logout button clicked: Destroy session
            if (session_status() === PHP_SESSION_NONE) session_start();
            cxLogAction($shareName, '❎ LOGOUT', '✅ SUCCESS', 'Manual logout', '');
            session_destroy();
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . '?cloudshare=' . $guid);
			exit;
        }       
    }

    $sharedRootPath = realpath($share['path']);
    $isValidLocation = false;
    $validRoots = cloudExGetAllowedRoots();
    if ($sharedRootPath && file_exists($sharedRootPath)) {
        foreach ($validRoots as $root) {
            if ($root && strpos($sharedRootPath, $root) === 0) { $isValidLocation = true; break; }
        }
    }
    if (!$isValidLocation) die(cxLang('err_file_unavailable'));

    if (!empty($share['password'])) {
        cxEnsureSession();
        
        $sessKey = 'cx_share_' . $guid;

        if (empty($_SESSION[$sessKey])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_pass'])) {
                if (!isset($_POST['csrf']) || !hash_equals($_SESSION['cx_csrf'] ?? '', $_POST['csrf'])) die(cxLang('err_csrf'));
                if (password_verify($_POST['share_pass'], $share['password'])) {
                    cxLogAction($shareName, '🔒 LOGIN', '✅ SUCCESS', 'Password verified', '');
					session_regenerate_id(true);
                    $_SESSION[$sessKey] = bin2hex(random_bytes(16));
                    $_SESSION['cx_just_auth'] = true;
                    if (empty($_SESSION['cx_csrf'])) $_SESSION['cx_csrf'] = bin2hex(random_bytes(32));
                    header("Location: " . $_SERVER['REQUEST_URI']); exit;
                } else {
                    cxLogAction($shareName, '🔒 LOGIN', '🛑 FAILURE', 'Incorrect password', '');
					cxRegisterFail($clientIp); cxIncrementShareAttempts($guid); $err = cxLang('err_pass_incorrect');
                }
            }
            cxEnsureSession();
            if (empty($_SESSION['cx_csrf'])) $_SESSION['cx_csrf'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['cx_csrf'];
            
            // Prevent Firefox bfcache from serving stale CSRF tokens
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            ?>
            <!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self' 'unsafe-inline'">
            <meta http-equiv="Referrer-Policy" content="no-referrer">
            <style>
			body{font-family:sans-serif;background:#f3f3f3;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} @keyframes fadeInScale {0% { opacity: 0; transform: scale(0.76); } 100% { opacity: 1; transform: scale(1); }} .box{background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:300px;text-align:center;animation: fadeInScale 0.4s ease-out both;} input{width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:4px;box-sizing:border-box} button{background:#0078d4;color:white;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;width:100%}.err{color:red;font-size:12px;margin-bottom:10px}
            body.dark-mode { background: #1e1e1e; color: #e0e0e0; } body.dark-mode .box { background: #2d2d2d; box-shadow: 0 4px 20px rgba(0,0,0,0.5); } body.dark-mode input { background: #1e1e1e; color: #e0e0e0; border-color: #555; }
			</style>
			</head>
            <body><script>(function(){var d=localStorage.getItem('cx_dark_mode');if(d==='1'||(d===null&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches))document.body.classList.add('dark-mode');})();</script><div class="box"><h3><?php echo htmlspecialchars(cxLang('login_title')); ?></h3>
            <?php echo isset($err) ? "<div class='err'>".htmlspecialchars($err)."</div>" : ''; ?>
            <form method="POST"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="password" name="share_pass" placeholder="<?php echo htmlspecialchars(cxLang('login_placeholder')); ?>" required autofocus autocomplete="off"><button><?php echo htmlspecialchars(cxLang('login_btn')); ?></button></form></div></body></html>
            <?php exit;
        }
    } else {
        cxEnsureSession();
        $sessKeyFree = 'cx_share_entry_' . $guid;
        if (empty($_SESSION[$sessKeyFree])) {
            cxLogAction($shareName, '🟢 FREE_ACCESS', '✅ SUCCESS', 'Entered', '');
            $_SESSION[$sessKeyFree] = bin2hex(random_bytes(16));
            $_SESSION['cx_just_auth'] = true;
        }
    }

    // Ensure the CSRF Token ALWAYS exists for normal loads
    if (empty($_SESSION['cx_csrf'])) {
        $_SESSION['cx_csrf'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['cx_csrf'];

    // Generate Tab-Binding Security Script
    $activeSessKey = !empty($share['password']) ? ('cx_share_' . $guid) : ('cx_share_entry_' . $guid);
    $activeToken = $_SESSION[$activeSessKey] ?? '';
    $justAuth = !empty($_SESSION['cx_just_auth']);
    $tabSecurityScript = "<script>(function(){
        var t='".htmlspecialchars($activeToken)."';
        var sk='cx_tab_tok_".$guid."';
        if(" . ($justAuth ? 'true' : 'false') . "){
            sessionStorage.setItem(sk,t);
        } else if(t && sessionStorage.getItem(sk) !== t && window.location.search.indexOf('tab_logout') === -1) {
            window.location.replace('?cloudshare=".$guid."&tab_logout=1');
        }
    })();</script>";
    if ($justAuth) unset($_SESSION['cx_just_auth']);

    $perm = $share['permission'] ?? 'read';
    $canModify = ($perm === 'modify' && !empty($share['password']));
    $isUploadOnly = ($perm === 'upload');
    $subPath = $isUploadOnly ? '' : ($_GET['subpath'] ?? '');
    $subPath = trim(str_replace(['..', '\\'], ['', '/'], $subPath), '/');
    $realCurrentPath = realpath($sharedRootPath . ($subPath ? DIRECTORY_SEPARATOR . $subPath : ''));
    if (!$realCurrentPath || strpos($realCurrentPath, $sharedRootPath) !== 0) die(cxLang('err_invalid_path'));

    // --- BULK ZIP SELECTION HANDLER ---
    if (!$isUploadOnly && isset($_POST['bulk_zip']) && $_POST['bulk_zip'] === '1' && is_dir($realCurrentPath)) {
        if (!isset($_POST['csrf']) || !hash_equals($csrfToken, $_POST['csrf'])) die(cxLang('err_csrf'));
        $selectedItems = json_decode($_POST['selected_items'] ?? '[]', true);
        if (!is_array($selectedItems) || empty($selectedItems)) die(cxLang('err_file_unavailable'));
		
		session_write_close();

//       $totalSize = 0;
        $validPaths = [];
        foreach ($selectedItems as $item) {
            $cleanItem = trim(str_replace(['..', '\\'], ['', '/'], $item), '/');
            if (empty($cleanItem)) continue;
            
            $itemPath = realpath($realCurrentPath . DIRECTORY_SEPARATOR . $cleanItem);
            if ($itemPath && strpos($itemPath, $realCurrentPath) === 0 && file_exists($itemPath)) {
                $itemSize = is_dir($itemPath) ? cxGetDirSize($itemPath, $max_zip_size + 1) : filesize($itemPath);
               $totalSize += $itemSize;
                $validPaths[] = ['full' => $itemPath, 'rel' => $cleanItem];
            }
        }

        if ($totalSize > 5368709120) { cxLogAction($shareName, '🗜️ DOWNLOAD_BULK_ZIP', '🛑 FAILURE', 'Size limit exceeded', $realCurrentPath); echo '<!DOCTYPE html><html><body><script>parent.showToast("'.addslashes(cxLang('err_zip_size')).'");</script></body></html>'; exit; }
        if (empty($validPaths)) { echo '<!DOCTYPE html><html><body><script>parent.showToast("'.addslashes(cxLang('err_file_unavailable')).'");</script></body></html>'; exit; }
        if (!class_exists('ZipArchive')) { echo '<!DOCTYPE html><html><body><script>parent.showToast("'.addslashes(cxLang('err_zip_missing')).'");</script></body></html>'; exit; }

        while (ob_get_level()) ob_end_clean(); set_time_limit(0);
        $zipName = 'selection-' . date('Ymd-His') . '.zip';

        // Use the global temp directory, fallback to system default if undefined
        $safeTempDir = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
        $tmpFile = tempnam($safeTempDir, 'cxzip');

        register_shutdown_function(function() use ($tmpFile) { if (file_exists($tmpFile)) unlink($tmpFile); });

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) die(cxLang('err_zip_create'));

        foreach ($validPaths as $vp) {
            if (is_file($vp['full'])) {
                $zip->addFile($vp['full'], basename($vp['rel']));
            } elseif (is_dir($vp['full'])) {
                $baseFolder = basename($vp['rel']);
                $zip->addEmptyDir($baseFolder);
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vp['full'], RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = $baseFolder . '/' . str_replace('\\', '/', substr($filePath, strlen($vp['full']) + 1));
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
        }
        $zip->close();

        if (file_exists($tmpFile)) {
            cxLogAction($shareName, '🗜️ DOWNLOAD_BULK_ZIP', '✅ SUCCESS', count($validPaths) . ' items', $realCurrentPath);
            cxRecordDownload($guid);
            header("X-Content-Type-Options: nosniff"); header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $zipName) . '"');
            header('Content-Length: ' . filesize($tmpFile));
			header('X-Accel-Buffering: no');
            while (ob_get_level()) ob_end_clean(); 
            $handle = @fopen($tmpFile, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) { echo fread($handle, 1048576); flush(); }
                fclose($handle);
            }
            exit;
        }
    }

    if (($canModify || $isUploadOnly) && $_SERVER['REQUEST_METHOD'] === 'POST' && is_dir($realCurrentPath)) {
        set_time_limit(0);
        if (!isset($_POST['csrf']) || !hash_equals($csrfToken, $_POST['csrf'])) die(cxLang('err_csrf'));
        $action = $_POST['action'] ?? '';

        if ($canModify && $action === 'mkdir' && !empty($_POST['dirname'])) {
            $newDirName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $_POST['dirname']);
            if ($newDirName && !file_exists($realCurrentPath . DIRECTORY_SEPARATOR . $newDirName)) {
                if (mkdir($realCurrentPath . DIRECTORY_SEPARATOR . $newDirName, 0755, true)) {
                    cxLogAction($shareName, '🔷 CREATE_FOLDER', '✅ SUCCESS', '', $realCurrentPath . DIRECTORY_SEPARATOR . $newDirName);
                } else {
                    cxLogAction($shareName, '🔷 CREATE_FOLDER', '🛑 FAILURE', '', $realCurrentPath . DIRECTORY_SEPARATOR . $newDirName);
                }
            }
        }
        if ($canModify && $action === 'delete' && !empty($_POST['target'])) {
            $target = basename($_POST['target']);
            $targetPath = $realCurrentPath . DIRECTORY_SEPARATOR . $target;
            if (file_exists($targetPath) && dirname($targetPath) === $realCurrentPath) {
                cxRemoveRecursive($targetPath);
                cxLogAction($shareName, '🗑️ DELETE_ITEM', '✅ SUCCESS', '', $targetPath);
            }
        }
        if ($action === 'upload' && !empty($_FILES['files'])) {
            $targetBaseDir = $realCurrentPath;
            if ($isUploadOnly) {
                $sessionUploadDirKey = 'cx_upload_dir_' . $guid;
                if (!isset($_SESSION[$sessionUploadDirKey])) {
                    $uploadFolderName = date('Y-m-d_H-i-s');
                    $fullUploadPath = $sharedRootPath . DIRECTORY_SEPARATOR . $uploadFolderName;
                    if (file_exists($fullUploadPath)) $fullUploadPath .= '_' . uniqid();
                    if (mkdir($fullUploadPath, 0755, true)) $_SESSION[$sessionUploadDirKey] = $fullUploadPath;
                    else die(json_encode(['status' => 'error', 'msg' => cxLang('err_upload_dir')]));
                }
                $targetBaseDir = $_SESSION[$sessionUploadDirKey];
                if (strpos(realpath($targetBaseDir), $sharedRootPath) !== 0) die(json_encode(['status' => 'error', 'msg' => cxLang('err_upload_path')]));
            }
            $quotaBytes = cxParseQuota($quota_str);
            $currentShareSize = cxGetDirSize($sharedRootPath, $quotaBytes + 1);
            $count = count($_FILES['files']['name']);
            $skipped = 0; $quotaExceeded = false;

            for ($i = 0; $i < $count; $i++) {
                if ($quotaExceeded) { $skipped++; continue; }
                $name = $_FILES['files']['name'][$i]; $tmp = $_FILES['files']['tmp_name'][$i]; $err = $_FILES['files']['error'][$i]; $size = $_FILES['files']['size'][$i];
                $relPathRaw = str_replace('\\', '/', $_POST['relpaths'][$i] ?? '');
                if (preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $relPathRaw) || preg_match('#^/#', $relPathRaw) || strpos($relPathRaw, '..') !== false || preg_match('#\.{2,}#', $relPathRaw)) { $skipped++; continue; }
                
                $parts = explode('/', $relPathRaw); $safeParts = [];
                foreach ($parts as $part) { $part = trim($part); if ($part !== '' && $part !== '.' && $part !== '..') $safeParts[] = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $part); }
                $safeRelPath = implode('/', $safeParts);

                if ($err === UPLOAD_ERR_OK) {
                    if (($currentShareSize + $size) > $quotaBytes) { $quotaExceeded = true; $skipped++; continue; }
                    if (cxIsSafeFile($name, $tmp)) {
                        $targetDir = $targetBaseDir;
                        if ($safeRelPath !== '') {
                            $targetDir .= DIRECTORY_SEPARATOR . $safeRelPath;
                            if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
                        }
                        $targetReal = realpath($targetDir) ?: $targetDir;
                        $baseCheckPath = $isUploadOnly ? $_SESSION['cx_upload_dir_' . $guid] : $realCurrentPath;
                        $baseCheckPathReal = realpath($baseCheckPath);
                        if (!$baseCheckPathReal && $isUploadOnly) $baseCheckPathReal = $baseCheckPath;

                        if ($baseCheckPathReal && strpos($targetReal, $baseCheckPathReal) === 0) {
                            if (is_uploaded_file($tmp)) {
                                $dest = $targetDir . DIRECTORY_SEPARATOR . basename($name);
                                if (move_uploaded_file($tmp, $dest)) $currentShareSize += $size;
                                else die(json_encode(['status' => 'error', 'msg' => 'Write Error']));
                            } else $skipped++;
                        } else $skipped++;
                    } else $skipped++;
                } else $skipped++;
            }
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $msg = $quotaExceeded ? cxLang('err_quota') : "";
                if ($isUploadOnly) $msg = ($skipped > 0) ? "Error: $skipped file(s) skipped." : ($msg ?: cxLang('js_success'));

                $uploadResult = ($skipped === $count && $count > 0) ? '🛑 FAILURE' : ($skipped > 0 ? '❓ PARTIAL' : '✅ SUCCESS');
                $uploadDetails = "Total: $count, Skipped: $skipped" . ($quotaExceeded ? " (Quota Exceeded)" : "");
                cxLogAction($shareName, '⬆️ UPLOAD', $uploadResult, $uploadDetails, $targetBaseDir);

                echo json_encode(['status' => 'ok', 'skipped' => $skipped, 'msg' => $msg]); exit;
            }
        }
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) { header("Location: " . $_SERVER['REQUEST_URI']); exit; }
    }

    if (!$isUploadOnly && isset($_GET['zip']) && $_GET['zip'] === '1' && is_dir($realCurrentPath)) {
//        $totalSize = cxGetDirSize($realCurrentPath, $max_zip_size + 1);
//        if ($totalSize > $max_zip_size) die(cxLang('err_zip_size'));
        if (!class_exists('ZipArchive')) die(cxLang('err_zip_missing'));
		
		session_write_close();
		
        while (ob_get_level()) ob_end_clean(); set_time_limit(0);
        $zipName = 'download-' . date('Ymd-His') . '.zip';
        $tmpFile = tempnam(sys_get_temp_dir(), 'cxzip');
        register_shutdown_function(function() use ($tmpFile) { if (file_exists($tmpFile)) unlink($tmpFile); });
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) die(cxLang('err_zip_create'));
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realCurrentPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($realCurrentPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        if (file_exists($tmpFile)) {
            cxLogAction($shareName, '🗜️ DOWNLOAD_ZIP', '✅ SUCCESS', '', $realCurrentPath);
            cxRecordDownload($guid);
            header("X-Content-Type-Options: nosniff"); header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $zipName) . '"');
            header('Content-Length: ' . filesize($tmpFile));
			header('X-Accel-Buffering: no');
            while (ob_get_level()) ob_end_clean(); 
            $handle = @fopen($tmpFile, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) { echo fread($handle, 1048576); flush(); }
                fclose($handle);
            }
            exit;
        }
    }

    // --- ICON THUMBNAIL HANDLER ---
    if (!$isUploadOnly && isset($_GET['icon']) && $_GET['icon'] === '1' && is_file($realCurrentPath)) {
    	while (ob_get_level()) ob_end_clean();
        cxServeIcon($realCurrentPath);
    }

    // --- FILE DOWNLOAD / INLINE PREVIEW HANDLER ---
    if (!$isUploadOnly && is_file($realCurrentPath)) {
        $filename = basename($realCurrentPath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ((isset($_GET['download']) && $_GET['download'] === '1') || (isset($_GET['inline']) && $_GET['inline'] === '1') || (isset($_GET['direct']) && $_GET['direct'] === '1') || ($realCurrentPath === $sharedRootPath && isset($_GET['download']))) {
            while (ob_get_level()) ob_end_clean(); set_time_limit(0); cxRecordDownload($guid);
            
            // Frees the session for other uploads/interactions when streaming huge videos/images
            session_write_close();
			
			// Check if we should serve the optimized PREVIEW instead of the raw file
            $servePath = $realCurrentPath;
            if (isset($_GET['inline']) && $_GET['inline'] === '1' && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'])) {
                $safePath = ltrim(str_replace(':', '', $realCurrentPath), '/\\');
                $cachedPreview = rtrim($cloud_preview_cache, '/') . '/' . $safePath . '.jpg';
                if (file_exists($cachedPreview)) {
                    $servePath = $cachedPreview;
                    $ext = 'jpg'; // Override extension since cronjob previews are always jpg
                }
             }

            // Map common file types for proper inline previewing
            $mime_types = [
                'pdf' => 'application/pdf',
                'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
                'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp', 'tiff' => 'image/tiff', 'tif' => 'image/tiff',
                'txt' => 'text/plain', 'md' => 'text/plain', 'csv' => 'text/csv', 'json' => 'application/json'
            ];
            $contentType = $mime_types[$ext] ?? 'application/octet-stream';
            $disposition = (isset($_GET['inline']) && $_GET['inline'] === '1') ? 'inline' : 'attachment';

            // [FIX] Disable errors to prevent PHP warnings from silently corrupting binary streams (e.g. 25MB images)
            error_reporting(0);
            @ini_set('display_errors', 0);

            header("X-Content-Type-Options: nosniff");
            if ($disposition === 'attachment') {
                header("Content-Security-Policy: default-src 'none';");
            } else {
                // Sandboxes inline content: allows viewing but strictly blocks script execution
                header("Content-Security-Policy: default-src 'none'; img-src 'self' data: blob:; media-src 'self' blob:; style-src 'unsafe-inline'; sandbox allow-same-origin;");
            }

            header('Content-Type: ' . $contentType);
            header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Length: ' . filesize($servePath)); 
            header('Cache-Control: no-cache');
			
			cxLogAction($shareName, $disposition === 'inline' ? '👁️ VIEW_INLINE' : '⬇️ DOWNLOAD_FILE', '✅ SUCCESS', cxFmtBytes(filesize($servePath)), $realCurrentPath);
            
            while (ob_get_level()) ob_end_clean();
            
            // [FIX] Robust chunked output ensures we do not hit memory limits for large 25MB+ files
            $handle = @fopen($servePath, 'rb');
            if ($handle !== false) {
                while (!feof($handle)) {
                    echo fread($handle, 1048576); // 1MB chunks
                    flush();
                }
                fclose($handle);
            }
            exit;
        }

        if ($realCurrentPath === $sharedRootPath) {
            ?>
            <!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
            <style>
			body{font-family:sans-serif;background:#f3f3f3;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .box{background:#fff;padding:35px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:350px;text-align:center;line-height:1.5;} .filename{display:block;margin:15px 0;font-weight:bold;color:#000;word-break:break-all;font-size:1.1em} .btn{display:inline-block;background:#0078d4;color:white;text-decoration:none;padding:12px 30px;border-radius:4px;font-weight:600;margin-top:10px}
			body.dark-mode { background: #1e1e1e; color: #e0e0e0; } body.dark-mode .box { background: #2d2d2d; box-shadow: 0 4px 20px rgba(0,0,0,0.5); } body.dark-mode .filename { color: #fff; }
			</style>
            <?php echo $tabSecurityScript; ?>
			</head><body>
			<script>(function(){var d=localStorage.getItem('cx_dark_mode');if(d==='1'||(d===null&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches))document.body.classList.add('dark-mode');})();</script>
            <div class="box"><h3><?php echo htmlspecialchars(cxLang('shared_file_title')); ?></h3>
            <div class="msg"><?php echo cxLang('shared_file_msg', htmlspecialchars($filename)); ?></div>
            <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'download=1'); ?>" class="btn"><?php echo htmlspecialchars(cxLang('btn_download')); ?></a></div></body></html>
            <?php exit;
        }
    }

    if (is_dir($realCurrentPath)) {
        $rootName = $share['name'] ?? 'Share';
        $reqUri = strtok($_SERVER["REQUEST_URI"], '?');
        $dirs = []; $files = []; $previewableFiles = [];
        $totalFileCount = 0;
        $imgCount = 0;
        $imgExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif'];
        $previewableExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','mp4','webm','ogg','mp3','wav','pdf','txt','md','csv','json'];

        if (!$isUploadOnly) {
            $items = scandir($realCurrentPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                // Hide readme files from the table/gallery 
                if (strtolower($item) === 'readme.md') continue;

                $fullSub = $realCurrentPath . DIRECTORY_SEPARATOR . $item;
                $relPath = ($subPath ? $subPath . '/' : '') . $item;
                $entry = ['name' => $item, 'date' => date('Y-m-d H:i', filemtime($fullSub)), 'size' => is_dir($fullSub) ? '-' : cxFmtBytes(filesize($fullSub)), 'path' => $relPath, 'isDir' => is_dir($fullSub)];
                if (is_dir($fullSub)) { $dirs[] = $entry; } 
                else { 
                    $files[] = $entry;
                    $totalFileCount++;
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($ext, $imgExts)) $imgCount++;
                    if (in_array($ext, $previewableExts)) {
                        $dlLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($relPath) . '&download=1';
                        $inlineLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($relPath) . '&inline=1';
                        $previewableFiles[] = [
                            'name' => $item,
                            'ext' => $ext,
                            'inline' => $inlineLink,
                            'dl' => $dlLink
                        ];
                    }
                }
            }
        }
        $autoGrid = ($totalFileCount > 0 && ($imgCount / $totalFileCount) > 0.5);
        
        // --- README.MD PROCESSING (DUAL ZONE) ---
        $readmeTopHtml = '';
        $readmeBottomHtml = '';
        
        // Only interpret the file if a valid position is configured
        if (in_array($readmePos, ['top', 'bottom'], true)) {
            foreach (['readme.md', 'README.md', 'README.MD'] as $rm) {
                $rmPath = $realCurrentPath . DIRECTORY_SEPARATOR . $rm;
                if (file_exists($rmPath) && is_file($rmPath)) {
                    $rawMd = file_get_contents($rmPath);
                    
                    if ($readmePos === 'bottom') {
                        // Force everything to the bottom. Strip the [FOOTER] tag so it doesn't render as text.
                        $cleanMd = preg_replace('/\[FOOTER\]/i', '', $rawMd);
                        $readmeBottomHtml = cxParseMarkdown(trim($cleanMd));
                    } else { // $readmePos === 'top'
                        if (preg_match('/\[FOOTER\]/i', $rawMd)) {
                            $parts = preg_split('/\[FOOTER\]/i', $rawMd, 2);
                            $readmeTopHtml = cxParseMarkdown(trim($parts[0]));
                            $readmeBottomHtml = cxParseMarkdown(trim($parts[1]));
                        } else {
                            $readmeTopHtml = cxParseMarkdown(trim($rawMd));
                        }
                    }
                    break; 
                }
            }
        }
        // ----------------------------------------

        $currentDirSize = cxGetDirSize($realCurrentPath, 5 * 1024 * 1024 * 1024); // 5GB Hard Limit
        $canZip = ($currentDirSize <= 5368709120);
        $parentLink = null;
        if (!empty($subPath) && !$isUploadOnly) {
            $parts = explode('/', $subPath); array_pop($parts); $upPath = implode('/', $parts);
            $parentLink = $reqUri . '?cloudshare=' . $guid . ($upPath ? '&subpath=' . urlencode($upPath) : '');
        }
        $zipLink = $reqUri . '?cloudshare=' . $guid . ($subPath ? '&subpath=' . urlencode($subPath) : '') . '&zip=1';
        $shouldAnimate = !isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'cloudshare='.$guid) === false;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; media-src 'self' blob:; frame-src 'self'">
            <meta http-equiv="Referrer-Policy" content="no-referrer">
            <title><?php echo htmlspecialchars($isUploadOnly ? cxLang('header_upload') : cxLang('header_shared') . $rootName); ?></title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; margin: 0; color: #333; min-height:100vh; }
                .header { background: #f4f4f4; padding: 15px 20px; border-bottom: 1px solid #ddd; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap:10px;}
                .header h2 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; color:#333; }
                .actions { display: flex; gap: 10px; align-items: center; }
                .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: 1px solid #ccc; background: #fff; color: #333; }
                .btn-primary { background: #0078d4; color: white; border-color: #0078d4; }
                .btn-default:hover { background: #f0f0f0; }
                .container { max-width: 1000px; margin: 0 auto; padding: 20px; padding-bottom: 80px; }
                @keyframes fadeInScaleMain { 0% { opacity: 0; transform: scale(0.76); } 100% { opacity: 1; transform: scale(1); } }
                .bounce-enter { animation: fadeInScaleMain 0.4s ease-out both; }
                
                /* LIST VIEW */
                .file-list { width: 100%; border-collapse: collapse; margin-top:10px; table-layout: fixed; }
                .file-list th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; color: #666; font-size: 12px; text-transform: uppercase; }
                .file-list td { padding: 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: background 0.2s; }
                .file-list tr:hover td { background: #f9f9f9; }
                .row-link { text-decoration: none; color: inherit; display: flex; align-items: center; font-weight: 500; }
                .row-link:hover { color: #0078d4; }
                .meta { color: #888; font-size: 13px; }
                .empty { padding: 40px; text-align: center; color: #999; }
                .size-warning { font-size: 11px; color: #999; align-self: center; margin-right: 10px; }
                .del-btn { background:none; border:none; padding:5px; border-radius:4px; }
                .del-btn:hover { background: #fee; }
                .hint-text { margin-bottom: 10px; font-size: 13px; color: #aaa; }
                input[type="checkbox"].cx-item-cb { width: 16px; height: 16px; cursor: pointer; }
                
                @media (max-width: 720px) {
                    .file-list .col-date, .file-list .col-size {
                        display: none;
                    }
                }

                /* DOWNLOAD ICON BUTTONS */
                .dl-icon-btn { color: #0078d4; text-decoration: none; display: inline-flex; align-items: center; padding: 4px; border-radius: 4px; transition: background 0.2s; }
                .dl-icon-btn:hover { background: rgba(0, 120, 212, 0.1); }
                .gallery-dl-btn { position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6); color: #fff; border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; text-decoration: none; z-index: 10; opacity: 0; transition: opacity 0.2s, background 0.2s; }
                .gallery-item:hover .gallery-dl-btn, .gallery-item.touch-active .gallery-dl-btn { opacity: 1; }
                .gallery-dl-btn:hover { background: #0078d4; }

                #drop-zone { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,120,212,0.1); border:5px dashed #0078d4; box-sizing:border-box; z-index:900; pointer-events:none; display:none; }
                body.dragging #drop-zone { display:block; }
                .upload-only-container { display:flex; height:80vh; align-items:center; justify-content:center; flex-direction:column; text-align:center; }
                .upload-box { border: 2px dashed #0078d4; padding: 50px; border-radius: 8px; background: #f9f9f9; width: 60%; max-width: 600px; }
                .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; flex-direction: column; }
                .modal-content { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center; }
                .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #0078d4; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 15px auto; }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                .toast { display: none; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 12px 24px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 1001; font-size: 14px; text-align: center; }
                
                /* GALLERY VIEW */
                #view-gallery { display: none; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 4px; margin-top:10px; }
                .gallery-item { position: relative; aspect-ratio: 1/1; background: #f3f3f3; border: 1px solid #ddd; overflow: visible; cursor: pointer; display: flex; flex-direction: column; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, z-index 0s; user-select: none; -webkit-touch-callout: none; }
                .gallery-item:hover, .gallery-item.touch-active { transform: scale(1.4); z-index: 100; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border-color:#888; }
                .gallery-item.selected { border-color: #0078d4; box-shadow: 0 0 0 2px #0078d4 inset; }
                .gallery-thumb { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; background:#fff; }
                .gallery-img { width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.3s; }
                .gallery-img.loaded { opacity: 1; }
                .gallery-meta { padding: 4px; background: rgba(0,0,0,0.03); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; border-top: 1px solid #eee; }
                .gallery-item.is-dir .gallery-thumb svg { width: 48px; height: 48px; }

                /* README BOX */
                .readme-box { background: #fff; padding: 25px 0px; padding-bottom: 35px; border-radius: 8px; margin-top: 25px;  line-height: 1.6; color: #444; }
                .readme-box h1, .readme-box h2, .readme-box h3 { margin-top: 0; color: #000;  padding-bottom: 8px; margin-bottom: 3px; }
				.readme-box p:last-child { margin-bottom: 0; }
                .readme-box a { color: #0078d4; text-decoration: none; font-weight: 500; }
                .readme-box a:hover { text-decoration: underline; }
                .readme-box ul { padding-left: 20px; }
                .readme-box code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
                .readme-box blockquote { border-left: 4px solid #ddd; margin-left: 0; padding-left: 15px; color: #666; }
                .readme-box hr { border: 0; border-top: 1px solid #ddd; margin: 20px 0; }
                .readme-box pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
                .readme-box pre code { padding: 0; background: transparent; }
                body.dark-mode .readme-box blockquote { border-left-color: #555; color: #aaa; }
                body.dark-mode .readme-box pre { background: #1a1a1a; }
                .readme-box table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px; }
                .readme-box th, .readme-box td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .readme-box th { background: #f9f9f9; font-weight: 600; }
                body.dark-mode .readme-box th, body.dark-mode .readme-box td { border-color: #444; }
                body.dark-mode .readme-box th { background: #222; }
                .readme-box li input[type="checkbox"] { margin: 0 8px 0 -22px; vertical-align: middle; cursor: default; }
                .readme-box ul { list-style-position: inside; }
                .readme-box mark { background: #fff3cd; padding: 0 4px; border-radius: 3px; color: #856404; }
                body.dark-mode .readme-box mark { background: #664d03; color: #ffeb8a; }
                

                /* SELECTION OVERLAY & BAR */
                .gallery-cb-wrap { position: absolute; top: 8px; left: 8px; z-index: 10; opacity: 0; transition: opacity 0.2s; background: rgba(255,255,255,0.9); border-radius: 4px; padding: 3px 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .gallery-item:hover .gallery-cb-wrap, .gallery-item.touch-active .gallery-cb-wrap, .gallery-item.selected .gallery-cb-wrap { opacity: 1; }
                .selection-bar { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: #2b2b2b; color: #fff; padding: 12px 24px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 20px; z-index: 999; transition: bottom 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                .selection-bar.active { bottom: 30px; }
                .selection-bar span { font-size: 14px; font-weight: 500; }
                .selection-bar button { background: #0078d4; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 500; display:flex; align-items:center; gap:5px; }
                .selection-bar button:hover { background: #005a9e; }

                /* PREVIEW MODAL OVERLAY */
                .cx-preview-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.85); z-index: 10000; flex-direction: column;
                    align-items: center; justify-content: center; backdrop-filter: blur(10px);
                }
                .cx-preview-header {
                    position: absolute; top: 0; left: 0; width: 100%; padding: 15px 20px;
                    display: flex; justify-content: space-between; box-sizing: border-box;
                    background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
                    color: #fff; z-index: 10001; align-items: center; pointer-events: none;
                }
                .cx-preview-header > * { pointer-events: auto; }
                .cx-preview-title { font-weight: 600; font-size: 16px; text-shadow: 0 1px 3px rgba(0,0,0,0.8); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;}
                .cx-preview-close { background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 24px; cursor: pointer; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: background 0.2s;}
                .cx-preview-close:hover { background: rgba(255,255,255,0.4); }
                .cx-preview-body {
                    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; padding-top: 60px; box-sizing: border-box;
                }
				.cx-preview-body img, .cx-preview-body video { width: 100%; height: 100%; max-width: 95vw; max-height: calc(100vh - 90px); object-fit: contain; box-shadow: 0 5px 25px rgba(0,0,0,0.5); border-radius: 4px; background: transparent; }
				.cx-preview-body iframe { width: 100%; height: 100%; max-width: 95vw; max-height: calc(100vh - 90px); border: none; background: #fff; box-shadow: 0 5px 25px rgba(0,0,0,0.5); border-radius: 4px; }
                .cx-preview-body pre { background: #fff; padding: 20px; width: 80%; max-height: 80vh; overflow: auto; border-radius: 4px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); text-align: left; }
                .cx-preview-error { color: #fff; font-size: 16px; margin-top: 20px; text-align: center; line-height: 1.5; }
                
                /* PREVIEW NAVIGATION ARROWS */
                .cx-preview-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.15); border: none; color: #fff; font-size: 24px; cursor: pointer; border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; z-index: 10002; }
                .cx-preview-nav:hover { background: rgba(255,255,255,0.35); }
                .cx-preview-prev { left: 20px; }
                .cx-preview-next { right: 20px; }
                .cx-preview-nav.disabled { opacity: 0.2; cursor: default; pointer-events: none; }

                /* DARK MODE */
                body.dark-mode { background: #1e1e1e; color: #e0e0e0; }
                body.dark-mode .header { background: #2d2d2d; border-bottom: 1px solid #444; }
                body.dark-mode .header h2 { color: #e0e0e0; }
                body.dark-mode .btn-default { background: #333; color: #e0e0e0; border: 1px solid #555; }
                body.dark-mode .btn-default:hover { background: #444; }
                body.dark-mode .file-list th { border-bottom: 2px solid #444; color: #aaa; }
                body.dark-mode .file-list td { border-bottom: 1px solid #333; }
                body.dark-mode .file-list tr:hover td { background: #2a2a2a; }
                body.dark-mode .gallery-item { background: #2d2d2d; border-color: #444; }
                body.dark-mode .gallery-thumb { background: #222; }
                body.dark-mode .gallery-meta { background: rgba(0,0,0,0.2); border-top-color: #444; color:#ccc; }
                body.dark-mode .gallery-cb-wrap { background: rgba(45,45,45,0.9); }
                body.dark-mode .readme-box { background:  #1e1e1e; color: #ccc; border-color: #444; }
                body.dark-mode .readme-box h1, body.dark-mode .readme-box h2, body.dark-mode .readme-box h3 { color: #eee; }
                body.dark-mode .readme-box h1, body.dark-mode .readme-box h2 { border-bottom-color: #444; }
                body.dark-mode .readme-box code { background: #1e1e1e; }
                body.dark-mode .modal-content, body.dark-mode .upload-box { background: #2d2d2d; color: #e0e0e0; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
                body.dark-mode .upload-box { border-color: #555; background: #2a2a2a; }
                body.dark-mode .hint-text { color: #aaa; }
                body.dark-mode .empty { color: #777; }
                body.dark-mode .row-link { color: #e0e0e0; }
                body.dark-mode .row-link:hover { color: #5eb0ef; }
                body.dark-mode .dl-icon-btn { color: #5eb0ef; }
                body.dark-mode .dl-icon-btn:hover { background: rgba(94, 176, 239, 0.15); }
                body.dark-mode .del-btn:hover { background: rgba(217, 83, 79, 0.2); }
				
				body.dark-mode .cx-color { filter: invert(1) hue-rotate(180deg); }

            </style>
			<?php echo $tabSecurityScript; ?>
        </head>
        <body>
            <script>
                (function(){
                    var d = localStorage.getItem('cx_dark_mode');
                    var m = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
                    if (d === '1' || (d === null && m && m.matches)) document.body.classList.add('dark-mode');
                    if (m) {
                        m.addEventListener('change', function(e) {
                            if (localStorage.getItem('cx_dark_mode') === null) {
                                document.body.classList.toggle('dark-mode', e.matches);
                            }
                        });
                    }
                })();
            </script>
            <div id="drop-zone"></div>
            <div id="upload-modal" class="modal-overlay"><div class="modal-content"><div class="spinner"></div><div style="font-weight:600;"><?php echo htmlspecialchars(cxLang('modal_uploading')); ?></div><div style="font-size:12px; color:#666; margin-top:5px;"><?php echo htmlspecialchars(cxLang('modal_stay')); ?></div></div></div>
            <div id="security-toast" class="toast"></div>
            
            <div class="header">
                <h2><?php echo cxGetIcon(true, ''); ?> <?php echo htmlspecialchars($isUploadOnly ? cxLang('header_upload') : ($rootName . ($subPath ? ' / ' . $subPath : ''))); ?></h2>
                <div class="actions">
                    <button class="btn btn-default" onclick="cxToggleDarkMode()" title="Toggle Dark Mode">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8C12.92 3.04 12.46 3 12 3z"/></svg>
                    </button>
                    <?php if (!$isUploadOnly): ?>
                    <button class="btn btn-default" id="selectAllBtn" onclick="cxToggleMasterSelect()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> 
                        <span id="selectAllText"><?php echo cxLang('btn_select_all'); ?></span>
                    </button>

                    <button class="btn btn-default" id="toggleViewBtn" onclick="toggleView()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M4 11h5V5H4v6zm0 7h5v-6H4v6zm6 0h5v-6h-5v6zm6 0h5v-6h-5v6zm-6-7h5V5h-5v6zm6-6v6h5V5h-5z"/></svg> 
                        <span id="toggleText"><?php echo cxLang('view_grid'); ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($canModify): ?>
                        <input type="file" id="fileInput" multiple style="display:none" onchange="cxUploadFiles(this.files)">
                        <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()"><?php echo htmlspecialchars(cxLang('btn_upload')); ?></button>
                        <button class="btn btn-default" onclick="cxNewFolder()"><?php echo htmlspecialchars(cxLang('btn_folder')); ?></button>
                    <?php endif; ?>
                    <?php if (!$isUploadOnly): ?>
                        <button onclick="cxDownloadFolderZip('<?php echo htmlspecialchars($zipLink, ENT_QUOTES); ?>')" class="btn btn-default"><?php echo htmlspecialchars(cxLang('btn_zip')); ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="container <?php echo $shouldAnimate ? 'bounce-enter' : ''; ?>">
                <?php if ($isUploadOnly): ?>
                    <div class="upload-only-container">
                        <div class="upload-box" id="upload-box-trigger">
                            <h3><?php echo htmlspecialchars(cxLang('btn_upload')); ?></h3>
                            <p><?php echo htmlspecialchars(cxLang('upload_drag')); ?></p>
                            <input type="file" id="fileInputUploadOnly" multiple style="display:none" onchange="cxUploadFiles(this.files)">
                            <button class="btn btn-primary" style="padding:15px 30px; font-size:16px;" onclick="document.getElementById('fileInputUploadOnly').click()"><?php echo htmlspecialchars(cxLang('btn_select')); ?></button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($readmeTopHtml)): ?>
                        <div class="readme-box" style="margin-top: 0; margin-bottom: 25px;">
                            <?php echo $readmeTopHtml; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($parentLink): ?>
                        <div style="margin-bottom: 15px;">
                            <a href="<?php echo htmlspecialchars($parentLink); ?>" class="btn btn-default"><?php echo cxLang('btn_back'); ?></a>
                            <div class="hint-text" style="display:inline-block; margin-left:15px; margin-bottom:0;"><b><?php echo cxLang('hint_click'); ?></b></div>
                        </div>
                    <?php else: ?>
                        <div class="hint-text"><b><?php echo cxLang('hint_click'); ?></b></div>
                    <?php endif; ?>
                    
                    <div id="view-list">
                        <table class="file-list">
                            <thead><tr>
                                <th width="30" style="text-align:center;"><input type="checkbox" id="selectAllList" onclick="cxToggleAll(this.checked)"></th>
                                <th>Name</th>
                                <th class="col-date" width="140">Date</th>
                                <?php if(!empty($files)) echo '<th class="col-size" width="80">Size</th><th width="50" style="text-align:center;">Action</th>'; ?>
                                <?php if($canModify) echo '<th width="50"></th>'; ?>
                            </tr></thead>
                            <tbody>
                                <?php if (empty($dirs) && empty($files)): ?><tr><td colspan="<?php echo $canModify ? 4 : 3; ?>" class="empty"><?php echo htmlspecialchars(cxLang('empty_folder')); ?></td></tr><?php endif; ?>
                                
                                <?php foreach ($dirs as $d): $link = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($d['path']); ?>
                                    <tr>
                                        <td align="center"><input type="checkbox" class="cx-item-cb" value="<?php echo htmlspecialchars($d['name']); ?>" data-isdir="1" onclick="cxUpdateSelection(); event.stopPropagation();"></td>
                                        <td><a href="<?php echo htmlspecialchars($link); ?>" class="row-link"><?php echo cxGetIcon(true, $d['name']); ?> <?php echo htmlspecialchars($d['name']); ?></a></td>
                                        <td class="meta col-date"><?php echo $d['date']; ?></td>
                                        <?php if(!empty($files)) echo '<td class="meta col-size">-</td><td class="meta"></td>'; ?>
                                        <?php if($canModify): ?><td class="meta" align="center"><button class="del-btn" title="Delete" onclick="cxDelete('<?php echo htmlspecialchars($d['name']); ?>')"><?php echo cxGetDeleteIcon(); ?></button></td><?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php foreach ($files as $f): 
                                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                                    $dlLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($f['path']) . '&download=1';
                                    $inlineLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($f['path']) . '&inline=1';
                                    $isPreviewable = in_array($ext, $previewableExts);
                                ?>
                                    <tr>
                                        <td align="center"><input type="checkbox" class="cx-item-cb" value="<?php echo htmlspecialchars($f['name']); ?>" data-isdir="0" data-dl="<?php echo htmlspecialchars($dlLink); ?>" onclick="cxUpdateSelection(); event.stopPropagation();"></td>
                                        <td>
                                            <?php if ($isPreviewable): ?>
                                                <a href="javascript:void(0)" onclick="cxOpenPreview('<?php echo htmlspecialchars($inlineLink, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>', '<?php echo $ext; ?>', '<?php echo htmlspecialchars($dlLink, ENT_QUOTES); ?>')" class="row-link"><?php echo cxGetIcon(false, $f['name']); ?> <?php echo htmlspecialchars($f['name']); ?></a>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($dlLink); ?>" class="row-link"><?php echo cxGetIcon(false, $f['name']); ?> <?php echo htmlspecialchars($f['name']); ?></a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="meta col-date"><?php echo $f['date']; ?></td>
                                        <td class="meta col-size"><?php echo $f['size']; ?></td>
                                        <td class="meta" align="center">
                                            <a href="<?php echo htmlspecialchars($dlLink); ?>" class="dl-icon-btn" title="Download" download>
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                            </a>
                                        </td>
                                        <?php if($canModify): ?><td class="meta" align="center"><button class="del-btn" title="Delete" onclick="cxDelete('<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>')"><?php echo cxGetDeleteIcon(); ?></button></td><?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>                    </div>

                    <div id="view-gallery">
                        <?php foreach ($dirs as $d): $link = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($d['path']); ?>
                            <div class="gallery-item is-dir" onclick="window.location.href='<?php echo htmlspecialchars($link); ?>'">
                                <div class="gallery-cb-wrap" onclick="event.stopPropagation();"><input type="checkbox" class="cx-item-cb" value="<?php echo htmlspecialchars($d['name']); ?>" data-isdir="1" onclick="event.stopPropagation(); cxUpdateSelection();"></div>
                                <div class="gallery-thumb"><?php echo cxGetIcon(true, ''); ?></div>
                                <div class="gallery-meta"><?php echo htmlspecialchars($d['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($files as $f): 
                            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                            $isImg = in_array($ext, $imgExts);
                            $dlLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($f['path']) . '&download=1';
                            $inlineLink = $reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($f['path']) . '&inline=1';
                            $iconLink = $isImg ? ($reqUri . '?cloudshare=' . $guid . '&subpath=' . urlencode($f['path']) . '&icon=1') : '';
                            $isPreviewable = in_array($ext, $previewableExts);
                        ?>
                            <?php if ($isPreviewable): ?>
                                <div class="gallery-item" onclick="cxGalleryClick(event, 'preview', '<?php echo htmlspecialchars($inlineLink, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>', '<?php echo $ext; ?>', '<?php echo htmlspecialchars($dlLink, ENT_QUOTES); ?>')">
                            <?php else: ?>
                                <div class="gallery-item" onclick="cxGalleryClick(event, 'download', '<?php echo htmlspecialchars($dlLink, ENT_QUOTES); ?>', '', '', '')">
                            <?php endif; ?>
                                
                                <div class="gallery-cb-wrap" onclick="event.stopPropagation();"><input type="checkbox" class="cx-item-cb" value="<?php echo htmlspecialchars($f['name']); ?>" data-isdir="0" data-dl="<?php echo htmlspecialchars($dlLink); ?>" onclick="event.stopPropagation(); cxUpdateSelection();"></div>

                                <a href="<?php echo htmlspecialchars($dlLink); ?>" class="gallery-dl-btn" title="Download" download onclick="event.stopPropagation();">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                </a>

                                <div class="gallery-thumb">
                                    <?php if($isImg): ?>
                                        <img class="gallery-img" data-src="<?php echo htmlspecialchars($iconLink); ?>" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNlZWVlZWUiIHN0cm9rZS13aWR0aD0iMiI+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz48L3N2Zz4=">
                                    <?php else: ?>
                                        <?php echo cxGetIcon(false, $f['name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="gallery-meta"><?php echo htmlspecialchars($f['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($readmeBottomHtml)): ?>
                    <div class="readme-box">
                        <?php echo $readmeBottomHtml; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="selection-bar" class="selection-bar">
                <span id="sel-count">0 selected</span>
                <button onclick="cxDownloadBulkZip()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-3.5 9h-5v-2h5v2zm0-4h-5V9h5v2z"/></svg>
                    <?php echo cxLang('btn_bulk_zip'); ?>
                </button>
                <button onclick="cxDownloadBulkFiles()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    <?php echo cxLang('btn_bulk_files'); ?>
                </button>
            </div>
            
            <form id="bulkZipForm" method="POST" target="bulk_zip_frame" style="display:none;">
                <input type="hidden" name="bulk_zip" value="1">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="selected_items" id="bulkSelectedItems">
            </form>
			<iframe name="bulk_zip_frame" style="display:none;"></iframe>
            
            <div id="cxPreviewOverlay" class="cx-preview-overlay" style="display:none;">
                <div class="cx-preview-header">
                    <div class="cx-preview-title" id="cxPreviewTitle"></div>
                    <div style="display:flex; gap:15px; align-items:center;">
                        <a id="cxPreviewDownload" href="#" class="btn btn-primary" style="margin:0; padding:6px 15px; border:none; background:rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                        </a>
                        <button class="cx-preview-close" onclick="cxClosePreview()">&times;</button>
                    </div>
                </div>
                <button class="cx-preview-nav cx-preview-prev" id="cxPreviewPrev" onclick="cxNavigatePreview(-1)" style="display:none;">&#10094;</button>
                <button class="cx-preview-nav cx-preview-next" id="cxPreviewNext" onclick="cxNavigatePreview(1)" style="display:none;">&#10095;</button>
                <div class="cx-preview-body" id="cxPreviewBody"></div>
            </div>

            <?php if ($canModify || $isUploadOnly): ?>
            <form id="cx-form" method="POST" style="display:none;"><input type="hidden" name="action" id="cx-action"><input type="hidden" name="dirname" id="cx-dirname"><input type="hidden" name="target" id="cx-target"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>"></form>
            <?php endif; ?>
            
            <script>
            const guid = "<?php echo $guid; ?>";
            const isPasswordProtected = "<?php echo !empty($share['password']) ? '1' : '0'; ?>" === "1";
            if (isPasswordProtected) {
                if (!sessionStorage.getItem('cx_share_active_' + guid)) sessionStorage.setItem('cx_share_active_' + guid, '1');
            }

            // Global Toast Notification
            var toast = document.getElementById('security-toast');
            function showToast(msg, duration) { toast.innerText = msg; toast.style.display = 'block'; setTimeout(function() { toast.style.display = 'none'; }, duration || 5000); }
			
            // --- DARK MODE LOGIC ---
            function cxToggleDarkMode() {
                var isDark = document.body.classList.toggle('dark-mode');
                localStorage.setItem('cx_dark_mode', isDark ? '1' : '0');
            }

           // --- SELECTION LOGIC ---
            var isAllSelected = false;

            function cxToggleMasterSelect() {
                isAllSelected = !isAllSelected;
                cxToggleAll(isAllSelected);
            }

            function cxUpdateSelection() {
                var activeView = isGrid ? document.getElementById('view-gallery') : document.getElementById('view-list');
                if (!activeView) return;
                
                // 1. Gather the true state from the currently active view
                var selectedValues = new Set();
                activeView.querySelectorAll('.cx-item-cb:checked').forEach(function(cb) {
                    selectedValues.add(cb.value);
                });
                
                // 2. Synchronize ALL checkboxes (both hidden and visible) and apply styles
                var cbs = document.querySelectorAll('.cx-item-cb');
                cbs.forEach(function(cb) {
                    cb.checked = selectedValues.has(cb.value);
                    
                    var row = cb.closest('tr');
                    var card = cb.closest('.gallery-item');
                    if (cb.checked) {
                        if(row) row.style.background = '#f0f8ff';
                        if(card) card.classList.add('selected');
                    } else {
                        if(row) row.style.background = '';
                        if(card) card.classList.remove('selected');
                    }
                });
                
                // 3. Update Counters based strictly on active view to prevent double-counting
                var selectedCount = selectedValues.size;
                var activeCbs = activeView.querySelectorAll('.cx-item-cb');
                var totalItems = activeCbs.length;
                
                var bar = document.getElementById('selection-bar');
                var countTxt = document.getElementById('sel-count');
                var selTextTpl = "<?php echo cxLang('sel_items'); ?>";
                
                if (selectedCount > 0) {
                    countTxt.innerText = selTextTpl.replace('%s', selectedCount);
                    bar.classList.add('active');
                } else {
                    bar.classList.remove('active');
                }
                
                // 4. Automatically sync the master button and list header checkbox state
                isAllSelected = (selectedCount === totalItems && totalItems > 0);
                var textEl = document.getElementById('selectAllText');
                if (textEl) {
                    textEl.innerText = isAllSelected ? "<?php echo cxLang('btn_deselect_all'); ?>" : "<?php echo cxLang('btn_select_all'); ?>";
                }
                var listHeaderCb = document.getElementById('selectAllList');
                if (listHeaderCb) listHeaderCb.checked = isAllSelected;
            }
            
            function cxToggleAll(checked) {
				isAllSelected = checked;
                document.querySelectorAll('.cx-item-cb').forEach(function(cb) { cb.checked = checked; });
                cxUpdateSelection();
            }

            function cxDownloadBulkZip() {
                var selected = [];
                var activeView = isGrid ? document.getElementById('view-gallery') : document.getElementById('view-list');
                
                activeView.querySelectorAll('.cx-item-cb:checked').forEach(function(cb) {
                    selected.push(cb.value);
                });
                
                if (selected.length === 0) return;
                
                showToast("<?php echo cxLang('msg_prep_zip'); ?>", 15000);
                document.getElementById('bulkSelectedItems').value = JSON.stringify(selected);
                document.getElementById('bulkZipForm').submit();
            }
            
            function cxDownloadFolderZip(url) {
                showToast("<?php echo cxLang('msg_prep_zip'); ?>", 15000);
                var frame = document.getElementsByName('bulk_zip_frame')[0];
                if (frame) frame.src = url;
                else window.location.href = url;
            }

            // REPLACEMENT: Complete routine for cxDownloadBulkFiles()
            function cxDownloadBulkFiles() {
                var links = [];
                var activeView = isGrid ? document.getElementById('view-gallery') : document.getElementById('view-list');
                
                activeView.querySelectorAll('.cx-item-cb:checked').forEach(function(cb) {
                    if (cb.getAttribute('data-isdir') !== '1' && cb.getAttribute('data-dl')) {
                        links.push(cb.getAttribute('data-dl'));
                    }
                });
                
                if (links.length === 0) {
                    alert("<?php echo cxLang('err_no_files'); ?>");
                    return;
                }
          
                // FIX: Notify the user about the browser's security block
                if (links.length > 1) {
                    showToast("Starting downloads... If your browser blocks them, please click 'Allow multiple files' in your address bar.", 10000);
                }
                
                // FIX: Increase delay to 800ms to avoid browser spam filters
                links.forEach(function(link, i) {
                    setTimeout(function() {
                        var a = document.createElement('a');
                        a.href = link;
                        a.download = '';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }, i * 800); 
                });
            }

            // --- GALLERY LOGIC ---
            var isGrid = <?php echo $autoGrid ? 'true' : 'false'; ?>;
            var observer;

            function toggleView() {
                isGrid = !isGrid;
                localStorage.setItem('cx_share_view', isGrid ? 'grid' : 'list');
                applyView();
            }

            function applyView() {
                var list = document.getElementById('view-list');
                var gallery = document.getElementById('view-gallery');
                var txt = document.getElementById('toggleText');
                if(!list || !gallery) return;

                if (isGrid) {
                    list.style.display = 'none';
                    gallery.style.display = 'grid';
                    if(txt) txt.innerText = "<?php echo cxLang('view_list'); ?>";
                    initLazyLoad();
					// Force intersection check for items already in viewport after view switch
					setTimeout(function() { window.dispatchEvent(new Event('scroll')); }, 50);
                } else {
                    list.style.display = 'table';
                    gallery.style.display = 'none';
                    if(txt) txt.innerText = "<?php echo cxLang('view_grid'); ?>";
                }
            }

            function initLazyLoad() {
                if(observer) return; 
                observer = new IntersectionObserver(function(entries, obs) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.onload = function() { img.classList.add('loaded'); };
                                obs.unobserve(img);
                            }
                        }
                    });
                }, { rootMargin: "200px" });
                
                var imgs = document.querySelectorAll('img[data-src]');
                imgs.forEach(function(img) { observer.observe(img); });
            }

            // Apply preference on load
            applyView();

            // [UX] Touch Interaction Logic: 
            var lastTouchTime = 0;
            var touchFlag = false;

            window.addEventListener('touchstart', function() { touchFlag = true; }, {passive: true});

            function cxGalleryClick(e, action, url1, url2, url3, url4) {
                if (!touchFlag) {
                    if (action === 'preview') cxOpenPreview(url1, url2, url3, url4);
                    else window.location.href = url1;
                    return;
                }

                var el = e.currentTarget;
                var now = new Date().getTime();
                var isDoubleTap = (now - lastTouchTime) < 300;
                lastTouchTime = now;

                if (el.classList.contains('touch-active') || isDoubleTap) {
                    if (action === 'preview') cxOpenPreview(url1, url2, url3, url4);
                    else window.location.href = url1;
                } else {
                    e.preventDefault(); e.stopPropagation();
                    document.querySelectorAll('.gallery-item.touch-active').forEach(function(i) { i.classList.remove('touch-active'); });
                    el.classList.add('touch-active');
                }
            }

            // Deselect when clicking empty space
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.gallery-item')) {
                    document.querySelectorAll('.gallery-item.touch-active').forEach(function(i) { i.classList.remove('touch-active'); });
                }
            });

            // --- FULLSCREEN PREVIEW & NAVIGATION LOGIC ---
            var previewFiles = <?php echo json_encode($previewableFiles ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            var currentPreviewIndex = -1;

            function cxOpenPreview(inlineUrl, filename, ext, dlUrl) {
                currentPreviewIndex = previewFiles.findIndex(function(f) { return f.name === filename; });
                cxRenderPreview(inlineUrl, filename, ext, dlUrl);
            }

            function cxRenderPreview(inlineUrl, filename, ext, dlUrl) {
                var overlay = document.getElementById('cxPreviewOverlay');
                var body = document.getElementById('cxPreviewBody');
                var title = document.getElementById('cxPreviewTitle');
                var dlBtn = document.getElementById('cxPreviewDownload');
                var prevBtn = document.getElementById('cxPreviewPrev');
                var nextBtn = document.getElementById('cxPreviewNext');

                title.innerText = filename;
                dlBtn.href = dlUrl;
                body.innerHTML = '<div class="spinner" style="border-top-color:#fff;"></div>';
                overlay.style.display = 'flex';

                if (previewFiles.length > 1 && currentPreviewIndex !== -1) {
                    prevBtn.style.display = 'flex';
                    nextBtn.style.display = 'flex';
                    prevBtn.classList.toggle('disabled', currentPreviewIndex <= 0);
                    nextBtn.classList.toggle('disabled', currentPreviewIndex >= previewFiles.length - 1);
                } else {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                }

                var imgExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif'];
                var vidExts = ['mp4','webm','ogg'];
                var audExts = ['mp3','wav'];
                var txtExts = ['txt','md','csv','json'];

                if (imgExts.indexOf(ext) !== -1) {
                    var img = new Image();
                    img.onload = function() { body.innerHTML = ''; body.appendChild(img); };
                    
                    // Provides a clear link to the underlying error if the image stream times out or is corrupted
                    img.onerror = function() { 
                        body.innerHTML = '<div class="cx-preview-error"><?php echo cxLang('err_file_preview'); ?></div>'; 
                    };
                    
                    img.src = inlineUrl;
                } else if (vidExts.indexOf(ext) !== -1) {
                    body.innerHTML = '<video controls autoplay playsinline preload="metadata"><source src="'+inlineUrl+'"></video>'
                } else if (audExts.indexOf(ext) !== -1) {
                    body.innerHTML = '<audio controls autoplay><source src="'+inlineUrl+'"></audio>';
                } else if (ext === 'pdf') {
                    body.innerHTML = '<iframe src="'+inlineUrl+'"></iframe>';
                } else if (txtExts.indexOf(ext) !== -1) {
                    fetch(inlineUrl).then(function(r) { return r.text() }).then(function(txt) {
                        var pre = document.createElement('pre');
                        pre.innerText = txt;
                        body.innerHTML = '';
                        body.appendChild(pre);
                    }).catch(function() {
                        body.innerHTML = '<div class="cx-preview-error">Error loading text file</div>';
                    });
                } else {
                    body.innerHTML = '<div class="cx-preview-error">Preview not supported</div>';
                }
            }

            function cxNavigatePreview(dir) {
                if (currentPreviewIndex === -1) return;
                var newIndex = currentPreviewIndex + dir;
                if (newIndex >= 0 && newIndex < previewFiles.length) {
                    currentPreviewIndex = newIndex;
                    var f = previewFiles[newIndex];
                    cxRenderPreview(f.inline, f.name, f.ext, f.dl);
                }
            }

            function cxClosePreview() {
                var overlay = document.getElementById('cxPreviewOverlay');
                var body = document.getElementById('cxPreviewBody');
                overlay.style.display = 'none';
                body.innerHTML = ''; 
            }

            document.getElementById('cxPreviewOverlay').addEventListener('click', function(e) {
                if (e.target.id === 'cxPreviewOverlay' || e.target.id === 'cxPreviewBody') {
                    cxClosePreview();
                }
            });

            // Touch Swipe Logic for Navigation
            var touchStartX = 0;
            var touchEndX = 0;
            var previewOverlay = document.getElementById('cxPreviewOverlay');

            previewOverlay.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, {passive: true});

            previewOverlay.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                var threshold = 50; 
                if (touchEndX < touchStartX - threshold) {
                    cxNavigatePreview(1); // Swiped Left -> Next
                } else if (touchEndX > touchStartX + threshold) {
                    cxNavigatePreview(-1); // Swiped Right -> Prev
                }
            }, {passive: true});
            </script>

            <?php if ($canModify || $isUploadOnly): ?>
            <script>
            var isUploading = false;
            var uploadModal = document.getElementById('upload-modal');
           
            var csrfToken = "<?php echo $csrfToken; ?>";
            var isUploadOnlyMode = <?php echo $isUploadOnly ? 'true' : 'false'; ?>;

            window.addEventListener('beforeunload', function (e) { if (isUploading) { e.preventDefault(); e.returnValue = "<?php echo cxLang('js_leave'); ?>"; } });
            function cxNewFolder() { var name = prompt("<?php echo cxLang('js_prompt_folder'); ?>"); if (name) { document.getElementById('cx-action').value = 'mkdir'; document.getElementById('cx-dirname').value = name; document.getElementById('cx-form').submit(); } }
            function cxDelete(name) { var msg = "<?php echo cxLang('js_confirm_del', '%s'); ?>".replace('%s', name); if (confirm(msg)) { document.getElementById('cx-action').value = 'delete'; document.getElementById('cx-target').value = name; document.getElementById('cx-form').submit(); } }
            window.addEventListener('dragover', function(e) { e.preventDefault(); document.body.classList.add('dragging'); });
            window.addEventListener('dragleave', function(e) { if (e.clientX === 0 && e.clientY === 0) document.body.classList.remove('dragging'); });
            window.addEventListener('drop', cxDrop);
            function cxDrop(e) { e.preventDefault(); document.body.classList.remove('dragging'); var items = e.dataTransfer.items; if (items && items.length > 0) { var entries = []; for (var i=0; i<items.length; i++) { var entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null; if (entry) entries.push(entry); } if(entries.length > 0) scanFiles(entries); } else if (e.dataTransfer.files.length > 0) { cxUploadFiles(e.dataTransfer.files); } }
            var formData = new FormData();
            function scanFiles(entries, path) { path = path || ""; var promises = []; entries.forEach(function(entry) { if (entry.isFile) { promises.push(new Promise(function(resolve) { entry.file(function(file) { formData.append("files[]", file); formData.append("relpaths[]", path); resolve(); }); })); } else if (entry.isDirectory) { var dirReader = entry.createReader(); promises.push(new Promise(function(resolve) { dirReader.readEntries(function(subEntries) { scanFiles(subEntries, path + entry.name + "/").then(resolve); }); })); } }); return Promise.all(promises).then(function() { if (path === "") sendFormData(); }); }
            function cxUploadFiles(files) { formData = new FormData(); for (var i = 0; i < files.length; i++) { formData.append("files[]", files[i]); } sendFormData(); }
            function sendFormData() { if (!formData.has('files[]')) return; isUploading = true; uploadModal.style.display = 'flex'; formData.append('action', 'upload'); formData.append('csrf', csrfToken); var xhr = new XMLHttpRequest(); xhr.open('POST', window.location.href, true); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); xhr.onload = function() { isUploading = false; uploadModal.style.display = 'none'; try { var res = JSON.parse(xhr.responseText); var msg = (res.msg || "") + (res.skipped > 0 ? res.skipped + "<?php echo cxLang('js_skipped'); ?>" : ""); if (msg) { showToast(msg); if (!isUploadOnlyMode) { setTimeout(function() { window.location.reload(); }, 3500); } } else { if (!isUploadOnlyMode) { window.location.reload(); } else { showToast("<?php echo cxLang('js_success'); ?>"); } } } catch(e) { if (!isUploadOnlyMode) window.location.reload(); else showToast("<?php echo cxLang('js_finished'); ?>"); } }; xhr.onerror = function() { isUploading = false; uploadModal.style.display = 'none'; alert("<?php echo cxLang('js_failed'); ?>"); }; xhr.send(formData); }
            </script>
            <?php endif; ?>
            <script>
                window.addEventListener('pagehide', function() {
                    // Only trigger if we are not navigating to a file download
                    // This check prevents triggering logouts during the download process
                    var isDownload = event.persisted === false; 
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('?cloudshare=<?php echo $guid; ?>&tab_logout=1&silent=1');
                    }
                });
            </script>
        </body>
        </html>
<?php
        
// +.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+
// Output the buffered HTML directly (skipped missing minify functions)
    echo ob_get_clean();
// +.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+.+

    exit;
    }
}