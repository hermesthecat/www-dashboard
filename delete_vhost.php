<?php
// Güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

require_once 'config.php';

header('Content-Type: application/json');

// Temel parametreleri kontrol et
if (empty($_POST['conf_file'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası belirtilmemiş'
    ]));
}

$confFile = htmlspecialchars($_POST['conf_file'], ENT_QUOTES, 'UTF-8');
$serverName = !empty($_POST['server_name']) ? htmlspecialchars($_POST['server_name'], ENT_QUOTES, 'UTF-8') : '';

// Dosya yolunu oluştur
$filePath = VHOSTS_FOLDER . '/' . $confFile;

// Dosya kontrolü
if (!file_exists($filePath)) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası bulunamadı: ' . $confFile
    ]));
}

// Dosya yazılabilir mi kontrol et
if (!is_writable($filePath)) {
    die(json_encode([
        'success' => false,
        'message' => 'Yapılandırma dosyası silinemedi: izin hatası'
    ]));
}

// Dosyayı sil
if (unlink($filePath)) {
    echo json_encode([
        'success' => true,
        'message' => 'Sanal host başarıyla silindi' . ($serverName ? ': ' . $serverName : ''),
        'file' => $confFile
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Sanal host silinirken bir hata oluştu'
    ]);
}
