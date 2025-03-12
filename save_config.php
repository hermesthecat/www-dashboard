<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = "<?php\n";

    // Proxy settings
    $proxyEnabled = isset($_POST['proxy_enabled']) ? 'true' : 'false';
    $proxyAddress = filter_var($_POST['proxy_address'], FILTER_SANITIZE_STRING) ?: '127.0.0.1';
    $proxyPort = filter_var($_POST['proxy_port'], FILTER_VALIDATE_INT) ?: 8080;

    // Path normalization function
    function normalizePath($path)
    {
        // Convert Windows backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        // Remove multiple consecutive slashes
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        // Remove trailing slash
        return rtrim($path, '/');
    }

    // VHosts file path
    $vhostsFile = filter_var($_POST['vhosts_file'], FILTER_SANITIZE_STRING) ?: 'httpd-vhosts.conf';
    if (!str_starts_with($vhostsFile, '/') && !preg_match('~^[A-Z]:~i', $vhostsFile)) {
        // If relative path, make it absolute
        $vhostsFile = __DIR__ . DIRECTORY_SEPARATOR . $vhostsFile;
    }
    $vhostsFile = normalizePath($vhostsFile);

    // Verify file exists or is writable
    if (!file_exists($vhostsFile) && !is_writable(dirname($vhostsFile))) {
        die(json_encode(['success' => false, 'message' => 'Invalid vhosts file path or directory not writable']));
    }

    $config .= "// Proxy Configuration\n";
    $config .= "define('PROXY_ENABLED', {$proxyEnabled});\n";
    $config .= "define('PROXY_ADDRESS', '{$proxyAddress}');\n";
    $config .= "define('PROXY_PORT', {$proxyPort});\n\n";
    $config .= "// File Path Configuration\n";
    $config .= "define('VHOSTS_FILE', '{$vhostsFile}');\n\n";
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
