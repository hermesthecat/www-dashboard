<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = "<?php\n";
    
    // Proxy settings
    $proxyEnabled = isset($_POST['proxy_enabled']) ? 'true' : 'false';
    $proxyAddress = filter_var($_POST['proxy_address'], FILTER_SANITIZE_STRING) ?: '127.0.0.1';
    $proxyPort = filter_var($_POST['proxy_port'], FILTER_VALIDATE_INT) ?: 8080;
    
    $config .= "// Proxy Configuration\n";
    $config .= "define('PROXY_ENABLED', {$proxyEnabled});\n";
    $config .= "define('PROXY_ADDRESS', '{$proxyAddress}');\n";
    $config .= "define('PROXY_PORT', {$proxyPort});\n\n";
    $config .= "// Other configurations can be added here\n";
    
    // Write to config file
    if (file_put_contents('config.php', $config)) {
        $response = ['success' => true, 'message' => 'Configuration saved successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Failed to save configuration'];
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid request method'];
}

header('Content-Type: application/json');
echo json_encode($response);