<?php
// Güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

require_once 'config.php';

header('Content-Type: application/json');

// Temel parametreleri kontrol et
if (empty($_POST['server_name']) || empty($_POST['document_root']) || empty($_POST['conf_file'])) {
    die(json_encode([
        'success' => false, 
        'message' => 'Sunucu adı, belge kök dizini ve yapılandırma dosyası gereklidir'
    ]));
}

$serverName = htmlspecialchars($_POST['server_name'], ENT_QUOTES, 'UTF-8');
$documentRoot = htmlspecialchars($_POST['document_root'], ENT_QUOTES, 'UTF-8');
$serverAlias = !empty($_POST['server_alias']) ? htmlspecialchars($_POST['server_alias'], ENT_QUOTES, 'UTF-8') : '';
$phpVersion = !empty($_POST['php_version']) ? htmlspecialchars($_POST['php_version'], ENT_QUOTES, 'UTF-8') : 'Default';
$enableSsl = isset($_POST['enable_ssl']) && $_POST['enable_ssl'] === 'on';
$confFile = htmlspecialchars($_POST['conf_file'], ENT_QUOTES, 'UTF-8');

// Dosya yolunu oluştur
$filePath = VHOSTS_FILE . '/' . $confFile;

// Dosya kontrolü
if (!file_exists($filePath)) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası bulunamadı: ' . $confFile
    ]));
}

// Dizin kontrolü
if (!is_dir(VHOSTS_FILE)) {
    die(json_encode([
        'success' => false, 
        'message' => 'VHosts dizini bulunamadı: ' . VHOSTS_FILE
    ]));
}

// Dosya yazılabilir mi kontrol et
if (!is_writable($filePath)) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası düzenlenemedi: izin hatası'
    ]));
}

// PHP sürümü handler'ı
$phpHandler = '';
if ($phpVersion !== 'Default' && is_numeric($phpVersion)) {
    // PHP sürüm formatını belirle (56, 70, 74, 80 gibi)
    if (strlen($phpVersion) == 2) {
        // php56, php70 gibi formatlar için
        $phpHandler = <<<EOT
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php{$phpVersion[0]}{$phpVersion[1]}-cgi
    </FilesMatch>
EOT;
    } else {
        // php74, php80 gibi formatlar için
        $phpHandler = <<<EOT
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php{$phpVersion}-cgi
    </FilesMatch>
EOT;
    }
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
# Updated by WWW Dashboard on: " . date('Y-m-d H:i:s') . "

{$httpVhostBlock}
{$sslVhostBlock}
EOT;

// Dosyayı yaz
if (file_put_contents($filePath, $vhostContent)) {
    echo json_encode([
        'success' => true,
        'message' => 'Sanal host başarıyla güncellendi: ' . $serverName,
        'file' => $confFile
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Sanal host güncellenirken bir hata oluştu'
    ]);
} 