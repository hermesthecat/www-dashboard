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
$createDocumentRoot = isset($_POST['create_document_root']) && $_POST['create_document_root'] === 'on';
$indexFileType = !empty($_POST['index_file_type']) ? htmlspecialchars($_POST['index_file_type'], ENT_QUOTES, 'UTF-8') : 'none';
$confFile = htmlspecialchars($_POST['conf_file'], ENT_QUOTES, 'UTF-8');

// Belge kök dizinini oluştur
if ($createDocumentRoot) {
    $fullDocumentRootPath = SITE_ROOT . '/' . $documentRoot;
    
    // Dizin oluşturma
    if (!is_dir($fullDocumentRootPath)) {
        if (!mkdir($fullDocumentRootPath, 0755, true)) {
            die(json_encode([
                'success' => false,
                'message' => 'Belge kök dizini oluşturulamadı: ' . $fullDocumentRootPath
            ]));
        }
        
        // İndex dosyası oluşturma
        if ($indexFileType !== 'none') {
            $indexFilePath = $fullDocumentRootPath . '/index.' . $indexFileType;
            $indexContent = '';
            
            if ($indexFileType === 'html') {
                $indexContent = <<<EOT
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$serverName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        h1 {
            color: #3498db;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        p {
            font-size: 1.1rem;
            color: #555;
        }
        .info {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 0.8rem 1rem;
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
    <h1>{$serverName}</h1>
    <p>Bu sanal host {$documentRoot} dizininde bulunmaktadır.</p>
    <div class="info">
        <p>Bu sayfa WWW Dashboard tarafından otomatik olarak oluşturulmuştur.</p>
        <p>Güncelleme tarihi: " . date('Y-m-d H:i:s') . "</p>
    </div>
</body>
</html>
EOT;
            } elseif ($indexFileType === 'php') {
                $indexContent = <<<EOT
<?php
/**
 * Bu dosya WWW Dashboard tarafından otomatik olarak oluşturulmuştur.
 * Güncelleme tarihi: " . date('Y-m-d H:i:s') . "
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo '{$serverName}'; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        h1 {
            color: #3498db;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        p {
            font-size: 1.1rem;
            color: #555;
        }
        .info {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 0.8rem 1rem;
            margin: 1.5rem 0;
        }
        .php-info {
            margin-top: 2rem;
            background-color: #f1f1f1;
            padding: 1rem;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1><?php echo '{$serverName}'; ?></h1>
    <p>Bu sanal host <strong><?php echo '{$documentRoot}'; ?></strong> dizininde bulunmaktadır.</p>
    
    <div class="info">
        <p>Bu sayfa WWW Dashboard tarafından otomatik olarak oluşturulmuştur.</p>
        <p>Güncelleme tarihi: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <div class="php-info">
        <h2>PHP Bilgileri</h2>
        <p>PHP Sürümü: <?php echo PHP_VERSION; ?></p>
        <p>Server IP: <?php echo \$_SERVER['SERVER_ADDR']; ?></p>
        <p>Server Software: <?php echo \$_SERVER['SERVER_SOFTWARE']; ?></p>
    </div>
</body>
</html>
EOT;
            }
            
            if (!file_put_contents($indexFilePath, $indexContent)) {
                die(json_encode([
                    'success' => false,
                    'message' => 'Index dosyası oluşturulamadı: ' . $indexFilePath
                ]));
            }
        }
    }
}

// Dosya yolunu oluştur
$filePath = VHOSTS_FOLDER . '/' . $confFile;

// Dosya kontrolü
if (!file_exists($filePath)) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası bulunamadı: ' . $confFile
    ]));
}

// Dizin kontrolü
if (!is_dir(VHOSTS_FOLDER)) {
    die(json_encode([
        'success' => false,
        'message' => 'VHosts dizini bulunamadı: ' . VHOSTS_FOLDER
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