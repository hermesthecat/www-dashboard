<?php
header('Content-Type: application/json');

function checkVhostStatus($url) {
    $ch = curl_init("http://" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 seconds connection timeout
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);

    return [
        'status' => $httpCode > 0 ? ($httpCode < 400 ? 'online' : 'error') : 'offline',
        'code' => $httpCode,
        'error' => $error
    ];
}

if (isset($_GET['server'])) {
    $server = filter_var($_GET['server'], FILTER_SANITIZE_URL);
    echo json_encode(checkVhostStatus($server));
} else {
    echo json_encode(['error' => 'No server specified']);
}