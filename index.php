<?php
// Function to parse vhosts from httpd-vhosts.conf
function parseVhosts($configDir)
{
    $vhosts = [];

    // Klasördeki tüm .conf dosyalarını alıyorum
    $confFiles = glob($configDir . '/*.conf');

    foreach ($confFiles as $confFile) {
        $content = file_get_contents($confFile);

        // Regular expression to match VirtualHost blocks
        preg_match_all('/<VirtualHost.*?>\s*(.*?)\s*<\/VirtualHost>/s', $content, $matches);

        foreach ($matches[1] as $vhostBlock) {
            $vhost = [];

            // Extract ServerName
            if (preg_match('/ServerName\s+([^\s]+)/', $vhostBlock, $serverName)) {
                $vhost['serverName'] = $serverName[1];
            }

            // Extract ServerAdmin
            if (preg_match('/ServerAdmin\s+([^\s]+)/', $vhostBlock, $serverAdmin)) {
                $vhost['serverAdmin'] = $serverAdmin[1];
            }

            // Extract DocumentRoot
            if (preg_match('/DocumentRoot\s+"?([^"\s]+)"?/', $vhostBlock, $docRoot)) {
                // Değişkenleri çözme işlemi (örneğin ${SITEROOT} gibi)
                $documentRoot = $docRoot[1];
                $documentRoot = preg_replace('/\${SITEROOT}/', 'Y:/xampp/htdocs', $documentRoot);
                $vhost['documentRoot'] = $documentRoot;
            }

            // Extract ServerAlias
            if (preg_match('/ServerAlias\s+(.+)/', $vhostBlock, $serverAlias)) {
                $vhost['serverAlias'] = $serverAlias[1];
            }

            // Extract SSL info
            $vhost['ssl'] = preg_match('/SSLEngine\s+on/i', $vhostBlock) ? true : false;

            // Extract PHP version handler
            if (preg_match('/SetHandler\s+application\/x-httpd-php(\d+)/i', $vhostBlock, $phpVersion)) {
                $vhost['phpVersion'] = $phpVersion[1];
            } else {
                $vhost['phpVersion'] = 'Default';
            }

            // Dosya adını da vhost bilgisine ekle
            $vhost['confFile'] = basename($confFile);

            if (!empty($vhost) && !empty($vhost['serverName'])) {
                // Aynı sunucu adı için hem HTTP hem HTTPS varsa birleştir
                $serverNameExists = false;
                foreach ($vhosts as $key => $existingVhost) {
                    if ($existingVhost['serverName'] === $vhost['serverName']) {
                        $serverNameExists = true;
                        // SSL bilgisini güncelle
                        if ($vhost['ssl']) {
                            $vhosts[$key]['ssl'] = true;
                        }
                        break;
                    }
                }

                if (!$serverNameExists) {
                    $vhosts[] = $vhost;
                }
            }
        }
    }

    return $vhosts;
}

// Include configuration
require_once 'config.php';

// PHP sürüm klasörlerini oku
function getPhpVersions()
{
    $phpVersions = ['Default' => 'Varsayılan'];
    $multiPhpDir = 'Y:/xampp/multi-php';

    if (is_dir($multiPhpDir)) {
        $dirs = glob($multiPhpDir . '/php*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $version = basename($dir);
            // php56, php70 formatından 56, 70 formatına çevir
            $numericVer = preg_replace('/[^0-9]/', '', $version);
            // 56, 70 formatını 5.6, 7.0 gibi formata çevir (opsiyonel)
            if (strlen($numericVer) == 2) {
                $formattedVer = substr($numericVer, 0, 1) . '.' . substr($numericVer, 1, 1);
                $phpVersions[$numericVer] = 'PHP ' . $formattedVer;
            } elseif (strlen($numericVer) == 3) {
                // 74, 80, 81 gibi format için
                $formattedVer = substr($numericVer, 0, 1) . '.' . substr($numericVer, 1, 2);
                $phpVersions[$numericVer] = 'PHP ' . $formattedVer;
            }
        }
    }

    return $phpVersions;
}

$phpVersions = getPhpVersions();

// Parse vhosts
$vhosts = parseVhosts(VHOSTS_FILE);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WWW Dashboard</title>
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">WWW Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Virtual Hosts</h5>
                            <div>
                                <button type="button" class="btn btn-outline-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addVhostModal">
                                    <i class="bi bi-plus-circle"></i> Yeni Host
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#logsModal">
                                    <i class="bi bi-journal-text"></i> Loglar
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#proxyModal">
                                    <i class="bi bi-gear"></i> Proxy Settings
                                </button>
                            </div>
                            <div id="vhostCounter" class="text-muted"></div>
                        </div>
                        <div class="mt-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="Sanal hostlarda ara...">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-4" id="vhostCards">
                            <?php foreach ($vhosts as $vhost): ?>
                                <div class="col vhost-item">
                                    <div class="card h-100">
                                        <div class="card-header bg-transparent">
                                            <div class="d-flex justify-content-between">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-primary btn-sm edit-vhost me-2" 
                                                        data-server-name="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>"
                                                        data-document-root="<?php echo htmlspecialchars(preg_replace('/.*\/([^\/]+)$/', '$1', $vhost['documentRoot'] ?? '')); ?>"
                                                        data-server-alias="<?php echo htmlspecialchars($vhost['serverAlias'] ?? ''); ?>"
                                                        data-php-version="<?php echo htmlspecialchars($vhost['phpVersion'] ?? 'Default'); ?>"
                                                        data-ssl="<?php echo !empty($vhost['ssl']) && $vhost['ssl'] ? 'true' : 'false'; ?>"
                                                        data-conf-file="<?php echo htmlspecialchars($vhost['confFile'] ?? ''); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm delete-vhost"
                                                        data-server-name="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>"
                                                        data-conf-file="<?php echo htmlspecialchars($vhost['confFile'] ?? ''); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <span class="status-indicator" 
                                                    data-server="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>"
                                                    data-ssl="<?php echo !empty($vhost['ssl']) && $vhost['ssl'] ? 'true' : 'false'; ?>">
                                                    <span class="status-dot"></span>
                                                    <span class="status-text">...</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title" title="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>
                                            </h5>
                                            <div class="card-text">
                                                <div class="text-info-line">
                                                    <i class="bi bi-folder"></i>
                                                    <span class="text-muted" title="<?php echo htmlspecialchars($vhost['documentRoot'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($vhost['documentRoot'] ?? ''); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($vhost['confFile'])): ?>
                                                    <div class="text-info-line">
                                                        <i class="bi bi-file-earmark-code"></i>
                                                        <span class="text-muted">
                                                            <?php echo htmlspecialchars($vhost['confFile']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($vhost['serverAlias'])): ?>
                                                    <div class="text-info-line">
                                                        <i class="bi bi-link-45deg"></i>
                                                        <div class="alias-list text-muted">
                                                            <?php
                                                            $aliases = preg_split('/\s+/', trim($vhost['serverAlias']));
                                                            foreach ($aliases as $alias): ?>
                                                                <div class="alias-item" title="<?php echo htmlspecialchars($alias); ?>">
                                                                    <?php echo htmlspecialchars($alias); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="row mb-2">
                                                <?php if (!empty($vhost['ssl']) && $vhost['ssl']): ?>
                                                    <div class="col-auto">
                                                        <span class="badge bg-success" title="SSL Sertifikası Var">
                                                            <i class="bi bi-shield-lock"></i> SSL
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($vhost['phpVersion'])): ?>
                                                    <div class="col-auto ms-auto">
                                                        <span class="badge bg-info" title="PHP Sürümü">
                                                            <i class="bi bi-filetype-php"></i> PHP <?php echo htmlspecialchars($vhost['phpVersion']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo ($vhost['ssl'] ?? false) ? 'https://' : 'http://'; ?><?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>"
                                                class="btn btn-primary btn-sm w-100"
                                                target="_blank">
                                                <i class="bi bi-box-arrow-up-right"></i> Ziyaret Et
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Proxy Settings Modal -->
    <div class="modal fade" id="proxyModal" tabindex="-1" aria-labelledby="proxyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proxyModalLabel">Proxy Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="proxyForm" action="save_config.php" method="post">
                        <h6 class="mb-3">Proxy Settings</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="proxyEnabled" name="proxy_enabled" <?php echo defined('PROXY_ENABLED') && PROXY_ENABLED ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="proxyEnabled">Enable Proxy</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="proxyAddress" class="form-label">Proxy Address</label>
                            <input type="text" class="form-control" id="proxyAddress" name="proxy_address"
                                value="<?php echo defined('PROXY_ADDRESS') ? PROXY_ADDRESS : '127.0.0.1'; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="proxyPort" class="form-label">Proxy Port</label>
                            <input type="number" class="form-control" id="proxyPort" name="proxy_port"
                                value="<?php echo defined('PROXY_PORT') ? PROXY_PORT : '8080'; ?>">
                        </div>
                        <hr class="my-4">
                        <h6 class="mb-3">File Path Settings</h6>
                        <div class="mb-3">
                            <label for="vhostsFile" class="form-label">VHosts Configuration File</label>
                            <input type="text" class="form-control" id="vhostsFile" name="vhosts_file"
                                value="<?php echo defined('VHOSTS_FILE') ? str_replace('\\', '/', VHOSTS_FILE) : 'httpd-vhosts.conf'; ?>">
                            <div class="form-text">
                                Full path to Apache virtual hosts configuration file.<br>
                                Current file: <code><?php echo defined('VHOSTS_FILE') ? str_replace('\\', '/', VHOSTS_FILE) : 'Not set'; ?></code>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="proxyForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add VHost Modal -->
    <div class="modal fade" id="addVhostModal" tabindex="-1" aria-labelledby="addVhostModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVhostModalLabel">Yeni Sanal Host Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addVhostForm">
                        <div class="mb-3">
                            <label for="serverName" class="form-label">Sunucu Adı</label>
                            <input type="text" class="form-control" id="serverName" name="server_name"
                                placeholder="ornek.local.keremgok.tr" required>
                            <div class="form-text">
                                Sanal hostun tam alan adı
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="documentRoot" class="form-label">Belge Kök Dizini</label>
                            <input type="text" class="form-control" id="documentRoot" name="document_root"
                                placeholder="ornek" required>
                            <div class="form-text">
                                ${SITEROOT} klasörü altındaki dizin adı
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="serverAlias" class="form-label">Sunucu Takma Adları</label>
                            <input type="text" class="form-control" id="serverAlias" name="server_alias"
                                placeholder="www.ornek.local.keremgok.tr ornek.test">
                            <div class="form-text">
                                İsteğe bağlı: Boşlukla ayrılmış alternatif alan adları
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phpVersion" class="form-label">PHP Sürümü</label>
                            <select class="form-select" id="phpVersion" name="php_version">
                                <?php foreach ($phpVersions as $version => $name): ?>
                                    <option value="<?php echo $version; ?>">
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enableSsl" name="enable_ssl">
                            <label class="form-check-label" for="enableSsl">SSL Etkinleştir</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="createDocumentRoot" name="create_document_root" checked>
                            <label class="form-check-label" for="createDocumentRoot">Belge kök dizini yoksa oluştur</label>
                        </div>
                        <div class="mb-3">
                            <label for="indexFileType" class="form-label">Varsayılan index dosyası</label>
                            <select class="form-select" id="indexFileType" name="index_file_type">
                                <option value="none">Oluşturma</option>
                                <option value="html" selected>index.html</option>
                                <option value="php">index.php</option>
                            </select>
                            <div class="form-text">
                                Yeni oluşturulan dizine eklenecek dosya türü
                            </div>
                        </div>
                        <div id="vhostFormFeedback" class="alert alert-danger d-none"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="saveVhostBtn" class="btn btn-primary">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit VHost Modal -->
    <div class="modal fade" id="editVhostModal" tabindex="-1" aria-labelledby="editVhostModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVhostModalLabel">Sanal Host Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editVhostForm">
                        <input type="hidden" id="editConfFile" name="conf_file">
                        <div class="mb-3">
                            <label for="editServerName" class="form-label">Sunucu Adı</label>
                            <input type="text" class="form-control" id="editServerName" name="server_name" 
                                placeholder="ornek.local.keremgok.tr" required>
                            <div class="form-text">
                                Sanal hostun tam alan adı
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editDocumentRoot" class="form-label">Belge Kök Dizini</label>
                            <input type="text" class="form-control" id="editDocumentRoot" name="document_root" 
                                placeholder="ornek" required>
                            <div class="form-text">
                                ${SITEROOT} klasörü altındaki dizin adı
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editServerAlias" class="form-label">Sunucu Takma Adları</label>
                            <input type="text" class="form-control" id="editServerAlias" name="server_alias" 
                                placeholder="www.ornek.local.keremgok.tr ornek.test">
                            <div class="form-text">
                                İsteğe bağlı: Boşlukla ayrılmış alternatif alan adları
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editPhpVersion" class="form-label">PHP Sürümü</label>
                            <select class="form-select" id="editPhpVersion" name="php_version">
                                <?php foreach ($phpVersions as $version => $name): ?>
                                    <option value="<?php echo $version; ?>">
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editEnableSsl" name="enable_ssl">
                            <label class="form-check-label" for="editEnableSsl">SSL Etkinleştir</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editCreateDocumentRoot" name="create_document_root" checked>
                            <label class="form-check-label" for="editCreateDocumentRoot">Belge kök dizini yoksa oluştur</label>
                        </div>
                        <div class="mb-3">
                            <label for="editIndexFileType" class="form-label">Varsayılan index dosyası</label>
                            <select class="form-select" id="editIndexFileType" name="index_file_type">
                                <option value="none">Oluşturma</option>
                                <option value="html" selected>index.html</option>
                                <option value="php">index.php</option>
                            </select>
                            <div class="form-text">
                                Yeni oluşturulan dizine eklenecek dosya türü
                            </div>
                        </div>
                        <div id="editVhostFormFeedback" class="alert alert-danger d-none"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="updateVhostBtn" class="btn btn-primary">Güncelle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete VHost Confirmation Modal -->
    <div class="modal fade" id="deleteVhostModal" tabindex="-1" aria-labelledby="deleteVhostModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteVhostModalLabel">Sanal Host Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="deleteVhostForm">
                        <input type="hidden" id="deleteConfFile" name="conf_file">
                        <input type="hidden" id="deleteServerName" name="server_name">
                        <p>Aşağıdaki sanal hostu silmek istediğinizden emin misiniz?</p>
                        <p class="fw-bold" id="deleteVhostName"></p>
                        <p class="text-danger">Bu işlem geri alınamaz!</p>
                        <div id="deleteVhostFormFeedback" class="alert alert-danger d-none"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="confirmDeleteVhostBtn" class="btn btn-danger">Sil</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Modal -->
    <div class="modal fade" id="logsModal" tabindex="-1" aria-labelledby="logsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logsModalLabel">Log Görüntüleyici</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="logType" class="form-label">Log Türü</label>
                            <select class="form-select" id="logType">
                                <option value="error">Hata Logu</option>
                                <option value="access">Erişim Logu</option>
                                <option value="php">PHP Hata Logu</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="serverSelect" class="form-label">Sanal Host</label>
                            <select class="form-select" id="serverSelect">
                                <option value="">Genel Apache Logu</option>
                                <?php foreach ($vhosts as $vhost): ?>
                                    <option value="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="logLineCount" class="form-label">Satır Sayısı</label>
                            <select class="form-select" id="logLineCount">
                                <option value="50">Son 50 satır</option>
                                <option value="100" selected>Son 100 satır</option>
                                <option value="200">Son 200 satır</option>
                                <option value="500">Son 500 satır</option>
                                <option value="1000">Son 1000 satır</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Log içinde ara..." id="logSearchInput">
                                <button class="btn btn-primary" type="button" id="logSearchBtn">
                                    <i class="bi bi-search"></i> Ara
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="logRefreshBtn">
                                    <i class="bi bi-arrow-clockwise"></i> Yenile
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div id="logLoadingIndicator" class="text-center d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                                <p>Log dosyası yükleniyor...</p>
                            </div>
                            
                            <div id="logContent" class="log-viewer">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Log görüntülemek için yukarıdaki seçenekleri belirleyip "Yenile" butonuna tıklayın.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="log-viewer-info small text-muted me-auto">
                        <span id="logFileInfo"></span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>

</html>