<?php

/**
 * Language controller
 * 
 * @author A. Kerem Gök
 */

// Default language
if (!defined('DEFAULT_LANG')) {
    define('DEFAULT_LANG', 'tr');
}

// Available languages
$available_languages = [
    'tr' => 'Türkçe',
    'en' => 'English'
];

// Get language from cookie or set default
function getCurrentLanguage()
{
    if (isset($_COOKIE['www_dashboard_lang']) && array_key_exists($_COOKIE['www_dashboard_lang'], $GLOBALS['available_languages'])) {
        return $_COOKIE['www_dashboard_lang'];
    }

    return DEFAULT_LANG;
}

// Set language cookie
function setLanguage($lang)
{
    if (array_key_exists($lang, $GLOBALS['available_languages'])) {
        setcookie('www_dashboard_lang', $lang, time() + (86400 * 30), "/"); // 30 days
        return true;
    }

    return false;
}

// Get language strings
function getLanguageStrings($lang = null)
{
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }

    $langFile = __DIR__ . '/' . $lang . '.php';

    if (file_exists($langFile)) {
        return require $langFile;
    }

    // Fallback to default language
    $defaultLangFile = __DIR__ . '/' . DEFAULT_LANG . '.php';
    if (file_exists($defaultLangFile)) {
        return require $defaultLangFile;
    }

    // Emergency fallback (should never happen)
    return [];
}

// Change language (used by AJAX request)
if (isset($_POST['set_language']) && isset($_POST['lang'])) {
    $result = setLanguage($_POST['lang']);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
    exit;
}

// Get current language strings
$lang = getLanguageStrings();

// Shorthand function for translation
function __($key, $default = null)
{
    global $lang;

    if (isset($lang[$key])) {
        return $lang[$key];
    }

    return $default !== null ? $default : $key;
}
