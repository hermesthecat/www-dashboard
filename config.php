<?php
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

// System-specific path separator
define('DS', DIRECTORY_SEPARATOR);

// Proxy Configuration
define('PROXY_ENABLED', true);  // Set to true to enable proxy
define('PROXY_ADDRESS', '127.0.0.1');  // Proxy server address
define('PROXY_PORT', 80);  // Proxy server port

// File Path Configuration
define('VHOSTS_FILE', normalizePath(__DIR__ . DS . 'httpd-vhosts.conf'));  // Path to Apache vhosts configuration file