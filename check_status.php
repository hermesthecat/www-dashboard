<?php
header('Content-Type: application/json');

// Configuration constants from index.php
require_once 'config.php';

function checkVhostStatus($url, $ssl = false)
{
    $protocol = $ssl ? "https://" : "http://";
    $ch = curl_init($protocol . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 seconds connection timeout
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // Add proxy configuration if enabled
    if (defined('PROXY_ENABLED') && PROXY_ENABLED) {
        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
        curl_setopt($ch, CURLOPT_PROXYPORT, PROXY_PORT);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    return [
        'status' => $httpCode > 0 ? ($httpCode < 400 ? 'online' : 'error') : 'offline',
        'code' => $httpCode,
        'error' => $error,
        'protocol' => $ssl ? 'https' : 'http',
        'proxy_used' => defined('PROXY_ENABLED') && PROXY_ENABLED
    ];
}

if (isset($_GET['server'])) {
    $server = htmlspecialchars($_GET['server'], ENT_QUOTES, 'UTF-8');
    $ssl = isset($_GET['ssl']) ? filter_var($_GET['ssl'], FILTER_VALIDATE_BOOLEAN) : false;
    echo json_encode(checkVhostStatus($server, $ssl));
} else {
    echo json_encode(['error' => 'No server specified']);
}
