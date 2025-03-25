<?php
// Güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

require_once 'config.php';

header('Content-Type: application/json');

// Log türü ve log dosyasını kontrol et
if (empty($_POST['log_type'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Log türü belirtilmemiş'
    ]));
}

$logType = htmlspecialchars($_POST['log_type'], ENT_QUOTES, 'UTF-8');
$serverName = !empty($_POST['server_name']) ? htmlspecialchars($_POST['server_name'], ENT_QUOTES, 'UTF-8') : '';
$searchTerm = !empty($_POST['search_term']) ? htmlspecialchars($_POST['search_term'], ENT_QUOTES, 'UTF-8') : '';
$lineCount = isset($_POST['line_count']) ? (int)$_POST['line_count'] : 100;

// Maksimum satır sayısını sınırla
if ($lineCount > 1000) {
    $lineCount = 1000;
} elseif ($lineCount < 10) {
    $lineCount = 10;
}

// Log dosyası yolunu belirle
$logFile = '';
switch ($logType) {
    case 'error':
        $logFile = $serverName ? LOG_FOLDER . '/' . $serverName . '-error.log' : LOG_FOLDER . '/error.log';
        break;
    case 'access':
        $logFile = $serverName ? LOG_FOLDER . '/' . $serverName . '-access.log' : LOG_FOLDER . '/access.log';
        break;
    default:
        die(json_encode([
            'success' => false,
            'message' => 'Geçersiz log türü'
        ]));
}

// Dosya kontrolü
if (!file_exists($logFile)) {
    die(json_encode([
        'success' => false,
        'message' => 'Log dosyası bulunamadı: ' . basename($logFile)
    ]));
}

// Dosya okunabilir mi kontrol et
if (!is_readable($logFile)) {
    die(json_encode([
        'success' => false,
        'message' => 'Log dosyası okunamadı: izin hatası'
    ]));
}

// Log içeriğini oku
$logContent = '';
$lines = array();

// Büyük dosyalar için optimizasyon
if (filesize($logFile) > 5 * 1024 * 1024) { // 5MB'dan büyükse
    // Dosyanın son kısmını oku
    exec("tail -n {$lineCount} " . escapeshellarg($logFile), $lines);
} else {
    // Tüm dosyayı oku ve son satırları al
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($allLines, -$lineCount);
}

// Arama filtresi uygula
if (!empty($searchTerm)) {
    $filteredLines = array();
    foreach ($lines as $line) {
        if (stripos($line, $searchTerm) !== false) {
            $filteredLines[] = $line;
        }
    }
    $lines = $filteredLines;
}

// Her log formatına özel renklendirme
$formattedLines = array();
foreach ($lines as $index => $line) {
    $lineData = array(
        'index' => $index + 1,
        'text' => htmlspecialchars($line),
        'level' => 'info'
    );

    // Hata logları için renklendirme
    if ($logType === 'error') {
        if (stripos($line, 'error') !== false) {
            $lineData['level'] = 'error';
        } elseif (stripos($line, 'warn') !== false) {
            $lineData['level'] = 'warning';
        } elseif (stripos($line, 'notice') !== false) {
            $lineData['level'] = 'notice';
        }
    }

    // Access logları için durum kodu tespiti
    elseif ($logType === 'access') {
        // HTTP durum kodlarını bul (tipik olarak "HTTP/1.1" kısmından sonra)
        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $line, $matches)) {
            $statusCode = (int)$matches[1];
            if ($statusCode >= 400 && $statusCode < 500) {
                $lineData['level'] = 'warning'; // 4xx hataları
            } elseif ($statusCode >= 500) {
                $lineData['level'] = 'error';   // 5xx hataları
            } elseif ($statusCode >= 300 && $statusCode < 400) {
                $lineData['level'] = 'notice';  // 3xx yönlendirmeleri
            }
        }
    }

    // PHP hata logları için renklendirme
    elseif ($logType === 'php') {
        if (stripos($line, 'fatal') !== false || stripos($line, 'error') !== false) {
            $lineData['level'] = 'error';
        } elseif (stripos($line, 'warn') !== false) {
            $lineData['level'] = 'warning';
        } elseif (stripos($line, 'notice') !== false || stripos($line, 'deprecated') !== false) {
            $lineData['level'] = 'notice';
        }
    }

    $formattedLines[] = $lineData;
}

// Tersine çevir (en yeni en üstte)
$formattedLines = array_reverse($formattedLines);

// Yanıt döndür
echo json_encode([
    'success' => true,
    'file' => basename($logFile),
    'lines' => $formattedLines,
    'count' => count($formattedLines),
    'total' => count($lines)
]);
