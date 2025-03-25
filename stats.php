<?php
// Güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method not allowed');
}

require_once 'config.php';

header('Content-Type: application/json');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'server_stats';

// Windows sistemleri için CPU yükü hesaplama
function getWindowsCpuLoad() {
    $cpuLoad = [0, 0, 0]; // Varsayılan değerler
    
    $cmd = 'wmic cpu get loadpercentage /value';
    exec($cmd, $output);
    
    if (!empty($output)) {
        foreach ($output as $line) {
            if (strpos($line, 'LoadPercentage') !== false) {
                $loadValue = intval(trim(explode('=', $line)[1]));
                // CPU yüzdesini 0-1 aralığına dönüştür (1-core için)
                $loadValue = $loadValue / 100;
                $cpuLoad = [$loadValue, $loadValue, $loadValue];
                break;
            }
        }
    }
    
    return $cpuLoad;
}

// Ana istatistik verilerini topla
function getServerStats()
{
    $stats = [];

    // CPU kullanımı
    if (function_exists('sys_getloadavg')) {
        $loadAvg = sys_getloadavg();
    } else {
        // Windows için alternatif yöntem
        $loadAvg = getWindowsCpuLoad();
    }
    
    $stats['cpu'] = [
        'load_avg_1' => round($loadAvg[0], 2),
        'load_avg_5' => round($loadAvg[1], 2),
        'load_avg_15' => round($loadAvg[2], 2)
    ];

    // Bellek kullanımı (Windows için)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $memoryData = [];
        exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value', $memoryData);

        $totalMemory = 0;
        $freeMemory = 0;

        foreach ($memoryData as $line) {
            if (strpos($line, 'TotalVisibleMemorySize') !== false) {
                $totalMemory = (int)trim(explode('=', $line)[1]);
            }
            if (strpos($line, 'FreePhysicalMemory') !== false) {
                $freeMemory = (int)trim(explode('=', $line)[1]);
            }
        }

        $usedMemory = $totalMemory - $freeMemory;
        $memoryPercent = ($usedMemory / $totalMemory) * 100;

        $stats['memory'] = [
            'total' => round($totalMemory / 1024, 2), // MB
            'used' => round($usedMemory / 1024, 2),   // MB
            'free' => round($freeMemory / 1024, 2),   // MB
            'percent_used' => round($memoryPercent, 1)
        ];
    } else {
        // Linux için bellek kullanımı
        $memoryData = [];
        exec('free -m', $memoryData);

        if (isset($memoryData[1])) {
            $memoryValues = preg_split('/\s+/', $memoryData[1]);
            if (count($memoryValues) >= 7) {
                $total = $memoryValues[1];
                $used = $memoryValues[2];
                $free = $memoryValues[3];
                $percent = ($used / $total) * 100;

                $stats['memory'] = [
                    'total' => (int)$total,
                    'used' => (int)$used,
                    'free' => (int)$free,
                    'percent_used' => round($percent, 1)
                ];
            }
        }
    }

    // Disk kullanımı
    $diskTotal = disk_total_space(SITE_ROOT);
    $diskFree = disk_free_space(SITE_ROOT);
    $diskUsed = $diskTotal - $diskFree;
    $diskPercent = ($diskUsed / $diskTotal) * 100;

    $stats['disk'] = [
        'total' => round($diskTotal / 1073741824, 2), // GB
        'used' => round($diskUsed / 1073741824, 2),   // GB
        'free' => round($diskFree / 1073741824, 2),   // GB
        'percent_used' => round($diskPercent, 1)
    ];

    // Apache Bilgileri
    $apacheStats = [];

    // Windows'ta Apache durumu (tasklist ile)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $apacheInfo = [];
        exec('tasklist /FI "IMAGENAME eq httpd.exe" /FO CSV /NH', $apacheInfo);

        $runningCount = count($apacheInfo);
        $apacheStats['running'] = $runningCount > 0;
        $apacheStats['processes'] = $runningCount;

        // Apache sürümünü bul
        $versionInfo = [];
        exec('httpd -v', $versionInfo);
        $versionString = implode(' ', $versionInfo);

        if (preg_match('/Apache\/([\d\.]+)/i', $versionString, $matches)) {
            $apacheStats['version'] = $matches[1];
        } else {
            $apacheStats['version'] = 'Unknown';
        }
    } else {
        // Linux için Apache durumu
        $apacheInfo = [];
        exec('ps aux | grep -v grep | grep httpd', $apacheInfo);

        $runningCount = count($apacheInfo);
        $apacheStats['running'] = $runningCount > 0;
        $apacheStats['processes'] = $runningCount;

        // Apache sürümünü bul
        $versionInfo = [];
        exec('apache2 -v 2>/dev/null || httpd -v 2>/dev/null', $versionInfo);
        $versionString = implode(' ', $versionInfo);

        if (preg_match('/Apache\/([\d\.]+)/i', $versionString, $matches)) {
            $apacheStats['version'] = $matches[1];
        } else {
            $apacheStats['version'] = 'Unknown';
        }
    }

    $stats['apache'] = $apacheStats;

    // PHP Bilgileri
    $stats['php'] = [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'max_input_time' => ini_get('max_input_time')
    ];

    // Sistem bilgileri
    $stats['system'] = [
        'os' => PHP_OS,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'hostname' => gethostname(),
        'uptime' => getSystemUptime()
    ];

    return $stats;
}

// Sistem çalışma süresini alma
function getSystemUptime()
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $uptimeData = [];
        exec('wmic os get LastBootUpTime /Value', $uptimeData);

        foreach ($uptimeData as $line) {
            if (strpos($line, 'LastBootUpTime') !== false) {
                $bootTime = trim(explode('=', $line)[1]);
                // wmic LastBootUpTime formatı: 20200428123456.000000+180
                $bootTime = substr($bootTime, 0, 14);
                $year = substr($bootTime, 0, 4);
                $month = substr($bootTime, 4, 2);
                $day = substr($bootTime, 6, 2);
                $hour = substr($bootTime, 8, 2);
                $minute = substr($bootTime, 10, 2);
                $second = substr($bootTime, 12, 2);

                $bootTimestamp = mktime($hour, $minute, $second, $month, $day, $year);
                $uptime = time() - $bootTimestamp;

                return formatUptime($uptime);
            }
        }
        return 'Unknown';
    } else {
        // Linux için uptime
        $uptimeData = [];
        exec('cat /proc/uptime', $uptimeData);

        if (isset($uptimeData[0])) {
            $uptime = (int)current(explode(' ', $uptimeData[0]));
            return formatUptime($uptime);
        }
        return 'Unknown';
    }
}

// Uptime formatını okunabilir hale getir
function formatUptime($seconds)
{
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $uptime = '';
    if ($days > 0) {
        $uptime .= $days . ' gün ';
    }
    if ($hours > 0) {
        $uptime .= $hours . ' saat ';
    }
    $uptime .= $minutes . ' dakika';

    return $uptime;
}

// Aktif bağlantıları analiz et
function getConnectionStats()
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'idle' => 0,
        'by_ip' => []
    ];

    // Apache'den aktif bağlantıları al
    $connectionData = [];

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows'ta netstat kullanılır
        exec('netstat -an | findstr ":80 :443"', $connectionData);
    } else {
        // Linux'ta ss veya netstat kullanılabilir
        exec("ss -tn '( sport = :80 or sport = :443 )' | grep -v LISTEN", $connectionData);
    }

    // Bağlantı sayıları
    $stats['total'] = count($connectionData);

    // IP adreslerine göre gruplandır
    $ipCounts = [];
    foreach ($connectionData as $line) {
        // IP adresi çıkarma (netstat veya ss formatına göre ayarlanmalı)
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
            $ip = $matches[1];
            if (!isset($ipCounts[$ip])) {
                $ipCounts[$ip] = 0;
            }
            $ipCounts[$ip]++;
        }
    }

    // En çok bağlantı yapan IP'leri kaydet
    arsort($ipCounts);
    $stats['by_ip'] = array_slice($ipCounts, 0, 10, true);

    return $stats;
}

// Sanal host erişim istatistikleri
function getVhostAccessStats()
{
    $stats = [];

    // Sanal host listesini al
    $vhosts = [];
    if (is_dir(VHOSTS_FOLDER)) {
        $vhostsDir = scandir(VHOSTS_FOLDER);

        foreach ($vhostsDir as $file) {
            if (substr($file, -5) === '.conf') {
                $filePath = VHOSTS_FOLDER . '/' . $file;
                $content = file_get_contents($filePath);

                // ServerName değerini bul
                if (preg_match('/ServerName\s+([^\s]+)/i', $content, $matches)) {
                    $serverName = $matches[1];
                    $vhosts[] = ['serverName' => $serverName, 'confFile' => $file];
                }
            }
        }
    }

    if (!empty($vhosts)) {
        foreach ($vhosts as $vhost) {
            $serverName = $vhost['serverName'] ?? '';
            if (empty($serverName)) continue;

            $accessLog = LOG_FOLDER . '/' . $serverName . '-access.log';
            $errorLog = LOG_FOLDER . '/' . $serverName . '-error.log';

            $vhostStats = [
                'server_name' => $serverName,
                'access_log_size' => file_exists($accessLog) ? filesize($accessLog) : 0,
                'error_log_size' => file_exists($errorLog) ? filesize($errorLog) : 0,
                'hits' => 0,
                'errors' => 0,
                'last_access' => 'N/A'
            ];

            // Son erişim zamanını ve toplam hit sayısını bul
            if (file_exists($accessLog)) {
                $lastLine = exec('tail -n 1 ' . escapeshellarg($accessLog));
                if (!empty($lastLine)) {
                    // Apache erişim log formatından tarih çıkarma
                    if (preg_match('/\[(.*?)\]/', $lastLine, $matches)) {
                        $dateStr = $matches[1];
                        // Apache log tarih formatı: day/month/year:hour:minute:second +zone
                        $dateParts = explode(':', str_replace('/', ' ', $dateStr), 2);
                        $vhostStats['last_access'] = date('Y-m-d H:i:s', strtotime($dateParts[0] . ' ' . $dateParts[1]));
                    }
                }

                // Hit sayısını al (wc -l ile)
                $vhostStats['hits'] = intval(exec('wc -l < ' . escapeshellarg($accessLog)));
            }

            // Hata sayısını bul
            if (file_exists($errorLog)) {
                $vhostStats['errors'] = intval(exec('wc -l < ' . escapeshellarg($errorLog)));
            }

            $stats[$serverName] = $vhostStats;
        }
    }

    return $stats;
}

// İstenilen işleme göre veri döndür
$response = [];

switch ($action) {
    case 'server_stats':
        $response = getServerStats();
        break;
    case 'connection_stats':
        $response = getConnectionStats();
        break;
    case 'vhost_stats':
        $response = getVhostAccessStats();
        break;
    case 'all_stats':
        $response = [
            'server' => getServerStats(),
            'connections' => getConnectionStats(),
            'vhosts' => getVhostAccessStats()
        ];
        break;
    default:
        $response = ['error' => 'Invalid action'];
}

echo json_encode($response);
