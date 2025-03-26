<?php

/**
 * Language switcher endpoint
 * 
 * @author A. Kerem Gök
 */

// Include language controller
require_once 'lang/language.php';

// Security check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

header('Content-Type: application/json');

// Set language
if (isset($_POST['lang'])) {
    $lang = htmlspecialchars($_POST['lang'], ENT_QUOTES, 'UTF-8');
    $result = setLanguage($lang);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Language changed successfully' : 'Invalid language selection'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No language specified'
    ]);
}
