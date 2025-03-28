<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = "<?php\n";

    // Proxy settings
    $proxyEnabled = isset($_POST['proxy_enabled']) ? 'true' : 'false';
    $proxyAddress = htmlspecialchars($_POST['proxy_address'], ENT_QUOTES, 'UTF-8');
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

    // Polyfill for str_starts_with() function for PHP < 8.0
    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle)
        {
            return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
        }
    }

    // VHosts file path
    $vhostsFile = htmlspecialchars($_POST['vhosts_file'], ENT_QUOTES, 'UTF-8');
    if (!str_starts_with($vhostsFile, '/') && !preg_match('~^[A-Z]:~i', $vhostsFile)) {
        // If relative path, make it absolute
        $vhostsFile = __DIR__ . DIRECTORY_SEPARATOR . $vhostsFile;
    }
    $vhostsFile = normalizePath($vhostsFile);

    // Site root path
    $siteRoot = htmlspecialchars($_POST['site_root'], ENT_QUOTES, 'UTF-8');
    $siteRoot = normalizePath($siteRoot);

    // Log root path
    $logRoot = htmlspecialchars($_POST['log_root'], ENT_QUOTES, 'UTF-8');
    $logRoot = normalizePath($logRoot);

    // SSL Certificate Configuration
    $sslCertRoot = htmlspecialchars($_POST['ssl_cert_root'], ENT_QUOTES, 'UTF-8');
    $sslCertRoot = normalizePath($sslCertRoot);

    $sslCertFile = htmlspecialchars($_POST['ssl_cert_file'], ENT_QUOTES, 'UTF-8');
    $sslKeyFile = htmlspecialchars($_POST['ssl_key_file'], ENT_QUOTES, 'UTF-8');

    // Verify directory exists or is writable
    if (!file_exists($vhostsFile) && !is_dir($vhostsFile) && !is_writable(dirname($vhostsFile))) {
        die(json_encode(['success' => false, 'message' => 'Invalid vhosts directory path or directory not writable']));
    }

    $config .= "// Proxy Configuration\n";
    $config .= "define('PROXY_ENABLED', {$proxyEnabled});\n";
    $config .= "define('PROXY_ADDRESS', '{$proxyAddress}');\n";
    $config .= "define('PROXY_PORT', {$proxyPort});\n\n";

    $config .= "// Root directory\n";
    $config .= "define('ROOT_DIR', '" . dirname($siteRoot) . "');\n\n";

    $config .= "// File Path Configuration\n";
    $config .= "define('VHOSTS_FOLDER', ROOT_DIR . '/apache/conf/extra/vhosts');\n";
    $config .= "define('LOG_FOLDER', ROOT_DIR . '/logs');\n";
    $config .= "define('SITE_ROOT', ROOT_DIR . '/htdocs');\n";
    $config .= "define('PHP_ERROR_LOG_FOLDER', ROOT_DIR . '/logs/php');\n\n";

    $config .= "// SSL Certificate Configuration\n";
    $config .= "define('SSL_CERT_ROOT', '{$sslCertRoot}');\n";
    $config .= "define('SSL_CERTIFICATE_FILE', SSL_CERT_ROOT . '/{$sslCertFile}');\n";
    $config .= "define('SSL_CERTIFICATE_KEY_FILE', SSL_CERT_ROOT . '/{$sslKeyFile}');\n";

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
