<?php
/**
 * ============================================================================
 * MODULE: Internationalization (i18n) Controller
 * ============================================================================
 * Manages dynamic language selection, translation string mapping, and right-to-left 
 * (RTL) configuration based on user profiles or browser header preferences.
 * 
 * Dynamic Language Controller
 * 1. Scans lang/ directory.
 * 2. Checks User Profile -> Browser Language -> Default (English).
 * 3. Merges selected language over English to ensure no missing keys.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
$username = $_SESSION['username'] ?? '';

// 1. Define Paths & Default
$langDir = __DIR__ . '/lang';
$defaultLang = 'en';

// 2. Define available languages to eliminate I/O overhead 
$available_languages = [
    'ar' => 'العربية (AI)', 'bar' => 'Bairisch', 'de' => 'Deutsch', 'en' => 'English',
    'es' => 'Español (AI)', 'fa' => 'فارسی (AI)', 'fr' => 'Français (AI)', 'hes' => 'Hessisch (AI)',
    'hi' => 'हिन्दी (AI)', 'it' => 'Italiano (AI)', 'ja' => '日本語 (AI)', 'ko' => '한국어 (AI)',
    'lb' => 'Lëtzebuergesch (AI)', 'pcm' => 'Nigerian Pidgin (AI)', 'pt' => 'Português (AI)',
    'ru' => ' усский (AI)', 'tr' => 'Türkçe (AI)', 'uk' => 'Українська (AI)', 'vi' => 'Tiếng Việt (AI)',
    'zh-cn' => '中文 (简体) (AI)'
];

// 3. Logic: Determine Preferred Language
$detectedLang = null;

// A. Check User Profile (Server-side preference)
if ($username && !empty($GLOBALS['cloud_user_profiles'])) {
    $profileDir = rtrim($GLOBALS['cloud_user_profiles'], '/\\');
    $profileFile = $profileDir . '/' . $username . '.json';
    
    if (file_exists($profileFile)) {
        $json = file_get_contents($profileFile);
        $data = json_decode($json, true);
        // Only accept if the language file actually exists
        if (isset($data['language']) && isset($available_languages[$data['language']])) {
            $detectedLang = $data['language'];
        }
    }
}

// B. Check Browser Language (if no profile setting found)
if (!$detectedLang && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    // Robust Parsing: Iterate through comma separated list (e.g. "de-DE,de;q=0.9,en;q=0.8")
    $accepted = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($accepted as $langStr) {
        // Remove q-factor and trim
        $code = strtolower(trim(explode(';', $langStr)[0]));
        // Get primary code (e.g. 'de' from 'de-de')
        $short = substr($code, 0, 2);
        if (isset($available_languages[$short])) {
            $detectedLang = $short;
            break; // Stop at first match
        }
     }
 }

// C. Fallback to Default
if (!$detectedLang) {
    $detectedLang = $defaultLang;
}

// 4. Set Global Variable
global $language, $L;
$language = $detectedLang;

// 5. Load & Merge Data (The Fallback System)

// A. Load English Base (The "Safety Net")
// This ensures that if a key is missing in the target language, English is shown.
$englishPath = "$langDir/en.php";
$baseArray = file_exists($englishPath) ? include $englishPath : [];

// B. Load Target Language
if ($language === 'en') {
    $L = $baseArray;
} else {
    $targetPath = "$langDir/$language.php";
    $targetArray = file_exists($targetPath) ? include $targetPath : [];
    
    // Merge: Base (English) is overwritten by Target where keys exist
    // array_merge($base, $target) ensures keys missing in $target remain from $base
    $L = array_merge($baseArray, is_array($targetArray) ? $targetArray : []);
}

// 6. Inject Dynamic Data
$L['available_languages'] = $available_languages;

// 7. Allow extensions to modify $L here
if(isset($GLOBALS['CLOUD_LANG_EXTENSIONS']) && is_array($GLOBALS['CLOUD_LANG_EXTENSIONS'])) {
    foreach($GLOBALS['CLOUD_LANG_EXTENSIONS'] as $ext_lang => $ext_strings) {
        // If extension matches current language OR English (as fallback base for extension strings)
        if ($language === $ext_lang) {
            $L = array_merge($L, $ext_strings);
        }
    }
}