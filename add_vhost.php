<?php
// Güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

require_once 'config.php';

header('Content-Type: application/json');

// Temel parametreleri kontrol et
if (empty($_POST['server_name']) || empty($_POST['document_root'])) {
    die(json_encode([
        'success' => false, 
        'message' => 'Sunucu adı ve belge kök dizini gereklidir'
    ]));
}

$serverName = htmlspecialchars($_POST['server_name'], ENT_QUOTES, 'UTF-8');
$documentRoot = htmlspecialchars($_POST['document_root'], ENT_QUOTES, 'UTF-8');
$serverAlias = !empty($_POST['server_alias']) ? htmlspecialchars($_POST['server_alias'], ENT_QUOTES, 'UTF-8') : '';
$phpVersion = !empty($_POST['php_version']) ? htmlspecialchars($_POST['php_version'], ENT_QUOTES, 'UTF-8') : 'Default';
$enableSsl = isset($_POST['enable_ssl']) && $_POST['enable_ssl'] === 'on';

// Dosya adını oluştur
$fileName = preg_replace('/[^\w\d]/', '_', $serverName) . '.conf';
$filePath = VHOSTS_FILE . '/' . $fileName;

// Dizin kontrolü
if (!is_dir(VHOSTS_FILE)) {
    die(json_encode([
        'success' => false, 
        'message' => 'VHosts dizini bulunamadı: ' . VHOSTS_FILE
    ]));
}

// Dosya dizininin yazılabilir olup olmadığını kontrol et
if (!is_writable(VHOSTS_FILE)) {
    die(json_encode([
        'success' => false, 
        'message' => 'VHosts dizini yazılabilir değil: ' . VHOSTS_FILE
    ]));
}

// PHP sürümü handler'ı
$phpHandler = '';
if ($phpVersion !== 'Default' && is_numeric($phpVersion)) {
    $phpHandler = <<<EOT
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php{$phpVersion}-cgi
    </FilesMatch>
EOT;
}

// HTTP bloğunu oluştur
$httpVhostBlock = <<<EOT
<VirtualHost *:80>
    DocumentRoot "\${SITEROOT}/{$documentRoot}"
    ServerName {$serverName}
    ErrorLog "\${LOGROOT}/{$serverName}-error.log"
    CustomLog "\${LOGROOT}/{$serverName}-access.log" common
    <Directory "\${SITEROOT}/{$documentRoot}">
        Order allow,deny
        Allow from all
    </Directory>
{$phpHandler}
</VirtualHost>

EOT;

// SSL bloğunu oluştur (eğer isteniyorsa)
$sslVhostBlock = '';
if ($enableSsl) {
    $sslVhostBlock = <<<EOT
<VirtualHost *:443>
    DocumentRoot "\${SITEROOT}/{$documentRoot}"
    ServerName {$serverName}
    ErrorLog "\${LOGROOT}/{$serverName}-error.log"
    CustomLog "\${LOGROOT}/{$serverName}-access.log" common
    <Directory "\${SITEROOT}/{$documentRoot}">
        Order allow,deny
        Allow from all
    </Directory>
{$phpHandler}
    SSLEngine on
    SSLCertificateFile "\${CERTROOT}/local.keremgok.tr-chain.pem"
    SSLCertificateKeyFile "\${CERTROOT}/local.keremgok.tr-key.pem"
</VirtualHost>

EOT;
}

// Sunucu takma adlarını ekle (eğer varsa)
if (!empty($serverAlias)) {
    // HTTP Alias
    $httpVhostBlock = str_replace(
        "ServerName {$serverName}",
        "ServerName {$serverName}\n    ServerAlias {$serverAlias}",
        $httpVhostBlock
    );
    
    // SSL Alias (eğer SSL etkinse)
    if ($enableSsl) {
        $sslVhostBlock = str_replace(
            "ServerName {$serverName}",
            "ServerName {$serverName}\n    ServerAlias {$serverAlias}",
            $sslVhostBlock
        );
    }
}

// Vhost dosyasının içeriğini oluştur
$vhostContent = <<<EOT
# Virtual Hosts
#
# Created by WWW Dashboard on: " . date('Y-m-d H:i:s') . "

{$httpVhostBlock}
{$sslVhostBlock}
EOT;

// Dosyayı yaz
if (file_put_contents($filePath, $vhostContent)) {
    echo json_encode([
        'success' => true,
        'message' => 'Sanal host başarıyla oluşturuldu: ' . $serverName,
        'file' => $fileName
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Sanal host dosyası oluşturulamadı'
    ]);
} 