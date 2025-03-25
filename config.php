<?php
// Proxy Configuration
define('PROXY_ENABLED', false);
define('PROXY_ADDRESS', '127.0.0.1');
define('PROXY_PORT', 80);

// Root directory
define('ROOT_DIR', 'Y:/xampp');

// File Path Configuration
define('VHOSTS_FOLDER', ROOT_DIR . '/apache/conf/extra/vhosts');
define('LOG_FOLDER', ROOT_DIR . '/logs');
define('SITE_ROOT', ROOT_DIR . '/htdocs');
define('PHP_ERROR_LOG_FOLDER', ROOT_DIR . '/logs/php');

// SSL Certificate Configuration
define('SSL_CERT_ROOT', 'Y:/xampp/win-acme/certificates');
define('SSL_CERTIFICATE_FILE', SSL_CERT_ROOT . '/local.keremgok.tr-chain.pem');
define('SSL_CERTIFICATE_KEY_FILE', SSL_CERT_ROOT . '/local.keremgok.tr-key.pem');
