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
$vhosts = parseVhosts(VHOSTS_FOLDER);
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
                                <button type="button" class="btn btn-outline-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#statsModal">
                                    <i class="bi bi-bar-chart"></i> İstatistikler
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#proxyModal">
                                    <i class="bi bi-gear"></i> Ayarlar
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
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editVhostModal"
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proxyModalLabel">Ayarlar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form id="proxyForm">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="proxy-tab" data-bs-toggle="tab" data-bs-target="#proxy-tab-pane" type="button" role="tab" aria-controls="proxy-tab-pane" aria-selected="true">Proxy</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="paths-tab" data-bs-toggle="tab" data-bs-target="#paths-tab-pane" type="button" role="tab" aria-controls="paths-tab-pane" aria-selected="false">Dizinler</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ssl-tab" data-bs-toggle="tab" data-bs-target="#ssl-tab-pane" type="button" role="tab" aria-controls="ssl-tab-pane" aria-selected="false">SSL Sertifikaları</button>
                            </li>
                        </ul>
                        <div class="tab-content mt-3" id="settingsTabContent">
                            <!-- Proxy Ayarları -->
                            <div class="tab-pane fade show active" id="proxy-tab-pane" role="tabpanel" aria-labelledby="proxy-tab" tabindex="0">
                                <h5>Proxy Ayarları</h5>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="proxyEnabled" name="proxy_enabled" <?= defined('PROXY_ENABLED') && PROXY_ENABLED ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="proxyEnabled">Proxy Etkinleştir</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="proxyAddress" class="form-label">Proxy Adresi</label>
                                        <input type="text" class="form-control" id="proxyAddress" name="proxy_address" value="<?= defined('PROXY_ADDRESS') ? PROXY_ADDRESS : '127.0.0.1' ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="proxyPort" class="form-label">Proxy Port</label>
                                        <input type="number" class="form-control" id="proxyPort" name="proxy_port" value="<?= defined('PROXY_PORT') ? PROXY_PORT : '8080' ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Dizin Ayarları -->
                            <div class="tab-pane fade" id="paths-tab-pane" role="tabpanel" aria-labelledby="paths-tab" tabindex="0">
                                <h5>Dizin Ayarları</h5>
                                <div class="mb-3">
                                    <label for="vhostsFile" class="form-label">VHosts Dosyası</label>
                                    <input type="text" class="form-control" id="vhostsFile" name="vhosts_file" value="<?= defined('VHOSTS_FOLDER') ? VHOSTS_FOLDER : '' ?>">
                                    <div class="form-text">VHosts yapılandırma dosyalarının bulunduğu dizin.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="siteRoot" class="form-label">Site Kök Dizini</label>
                                    <input type="text" class="form-control" id="siteRoot" name="site_root" value="<?= defined('SITE_ROOT') ? SITE_ROOT : '' ?>">
                                    <div class="form-text">Web sitelerinin bulunduğu ana dizin.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="logRoot" class="form-label">Log Dizini</label>
                                    <input type="text" class="form-control" id="logRoot" name="log_root" value="<?= defined('LOG_FOLDER') ? LOG_FOLDER : '' ?>">
                                    <div class="form-text">Log dosyalarının bulunduğu dizin.</div>
                                </div>
                            </div>

                            <!-- SSL Sertifika Ayarları -->
                            <div class="tab-pane fade" id="ssl-tab-pane" role="tabpanel" aria-labelledby="ssl-tab" tabindex="0">
                                <h5>SSL Sertifika Ayarları</h5>
                                <div class="mb-3">
                                    <label for="sslCertRoot" class="form-label">SSL Sertifika Dizini</label>
                                    <input type="text" class="form-control" id="sslCertRoot" name="ssl_cert_root" value="<?= defined('SSL_CERT_ROOT') ? SSL_CERT_ROOT : '' ?>">
                                    <div class="form-text">SSL sertifikalarının bulunduğu ana dizin.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="sslCertFile" class="form-label">SSL Sertifika Dosyası</label>
                                    <input type="text" class="form-control" id="sslCertFile" name="ssl_cert_file" value="<?= defined('SSL_CERTIFICATE_FILE') ? basename(SSL_CERTIFICATE_FILE) : 'local.keremgok.tr-chain.pem' ?>">
                                    <div class="form-text">SSL sertifika dosyasının adı (sertifika dizinine göre).</div>
                                </div>
                                <div class="mb-3">
                                    <label for="sslKeyFile" class="form-label">SSL Anahtar Dosyası</label>
                                    <input type="text" class="form-control" id="sslKeyFile" name="ssl_key_file" value="<?= defined('SSL_CERTIFICATE_KEY_FILE') ? basename(SSL_CERTIFICATE_KEY_FILE) : 'local.keremgok.tr-key.pem' ?>">
                                    <div class="form-text">SSL özel anahtar dosyasının adı (sertifika dizinine göre).</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="proxyForm" class="btn btn-primary">Kaydet</button>
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

                        <!-- PHP Ayarları Paneli -->
                        <div id="phpSettings" class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">PHP Ayarları</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="usePHPIniSettings" name="use_php_ini_settings">
                                    <label class="form-check-label" for="usePHPIniSettings">Özel Ayarları Kullan</label>
                                </div>
                            </div>
                            <div class="card-body php-settings-body" style="display: none;">
                                <div class="mb-3">
                                    <label for="phpMemoryLimit" class="form-label">Bellek Limiti</label>
                                    <input type="text" class="form-control" id="phpMemoryLimit" name="php_memory_limit" placeholder="128M">
                                    <div class="form-text">Örnek: 128M, 256M, 1G</div>
                                </div>
                                <div class="mb-3">
                                    <label for="phpMaxExecutionTime" class="form-label">Maksimum Çalışma Süresi</label>
                                    <input type="number" class="form-control" id="phpMaxExecutionTime" name="php_max_execution_time" placeholder="30">
                                    <div class="form-text">Saniye cinsinden</div>
                                </div>
                                <div class="mb-3">
                                    <label for="phpUploadMaxFilesize" class="form-label">Maksimum Dosya Yükleme Boyutu</label>
                                    <input type="text" class="form-control" id="phpUploadMaxFilesize" name="php_upload_max_filesize" placeholder="8M">
                                    <div class="form-text">Örnek: 8M, 16M, 1G</div>
                                </div>
                                <div class="mb-3">
                                    <label for="phpPostMaxSize" class="form-label">Maksimum POST Boyutu</label>
                                    <input type="text" class="form-control" id="phpPostMaxSize" name="php_post_max_size" placeholder="8M">
                                    <div class="form-text">Örnek: 8M, 16M, 1G</div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="phpDisplayErrors" name="php_display_errors">
                                    <label class="form-check-label" for="phpDisplayErrors">Hataları Göster</label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="phpErrorReporting" name="php_error_reporting">
                                    <label class="form-check-label" for="phpErrorReporting">Tüm Hata Tiplerini Raporla</label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="phpErrorLog" name="php_error_log">
                                    <label class="form-check-label" for="phpErrorLog">Hata Logu Oluştur</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enableSsl" name="enable_ssl">
                            <label class="form-check-label" for="enableSsl">SSL Etkinleştir</label>
                        </div>
                        <div id="sslSettingsGroup" style="display: none;">
                            <div class="mb-3">
                                <label for="sslCertificatePath" class="form-label">SSL Sertifika Dosyası Yolu</label>
                                <input type="text" class="form-control" id="sslCertificatePath" name="ssl_certificate_path"
                                    value="<?= defined('SSL_CERTIFICATE_FILE') ? SSL_CERTIFICATE_FILE : '' ?>">
                                <div class="form-text">
                                    Varsayılan sertifika yerine özel bir sertifika yolu belirtmek için.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="sslKeyPath" class="form-label">SSL Anahtar Dosyası Yolu</label>
                                <input type="text" class="form-control" id="sslKeyPath" name="ssl_key_path"
                                    value="<?= defined('SSL_CERTIFICATE_KEY_FILE') ? SSL_CERTIFICATE_KEY_FILE : '' ?>">
                                <div class="form-text">
                                    Varsayılan anahtar dosyası yerine özel bir anahtar dosyası yolu belirtmek için.
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="createDocumentRoot" name="create_document_root">
                            <label class="form-check-label" for="createDocumentRoot">Belge kök dizini yoksa oluştur</label>
                        </div>
                        <div class="mb-3">
                            <label for="indexFileType" class="form-label">Varsayılan index dosyası</label>
                            <select class="form-select" id="indexFileType" name="index_file_type">
                                <option value="none" selected>Oluşturma</option>
                                <option value="html">index.html</option>
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

                        <!-- PHP Ayarları Paneli (Düzenleme Formu) -->
                        <div id="editPhpSettings" class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">PHP Ayarları</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="editUsePHPIniSettings" name="use_php_ini_settings">
                                    <label class="form-check-label" for="editUsePHPIniSettings">Özel Ayarları Kullan</label>
                                </div>
                            </div>
                            <div class="card-body php-settings-body" style="display: none;">
                                <div class="mb-3">
                                    <label for="editPhpMemoryLimit" class="form-label">Bellek Limiti</label>
                                    <input type="text" class="form-control" id="editPhpMemoryLimit" name="php_memory_limit" placeholder="128M">
                                    <div class="form-text">Örnek: 128M, 256M, 1G</div>
                                </div>
                                <div class="mb-3">
                                    <label for="editPhpMaxExecutionTime" class="form-label">Maksimum Çalışma Süresi</label>
                                    <input type="number" class="form-control" id="editPhpMaxExecutionTime" name="php_max_execution_time" placeholder="30">
                                    <div class="form-text">Saniye cinsinden</div>
                                </div>
                                <div class="mb-3">
                                    <label for="editPhpUploadMaxFilesize" class="form-label">Maksimum Dosya Yükleme Boyutu</label>
                                    <input type="text" class="form-control" id="editPhpUploadMaxFilesize" name="php_upload_max_filesize" placeholder="8M">
                                    <div class="form-text">Örnek: 8M, 16M, 1G</div>
                                </div>
                                <div class="mb-3">
                                    <label for="editPhpPostMaxSize" class="form-label">Maksimum POST Boyutu</label>
                                    <input type="text" class="form-control" id="editPhpPostMaxSize" name="php_post_max_size" placeholder="8M">
                                    <div class="form-text">Örnek: 8M, 16M, 1G</div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="editPhpDisplayErrors" name="php_display_errors">
                                    <label class="form-check-label" for="editPhpDisplayErrors">Hataları Göster</label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="editPhpErrorReporting" name="php_error_reporting">
                                    <label class="form-check-label" for="editPhpErrorReporting">Tüm Hata Tiplerini Raporla</label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="editPhpErrorLog" name="php_error_log">
                                    <label class="form-check-label" for="editPhpErrorLog">Hata Logu Oluştur</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editEnableSsl" name="enable_ssl">
                            <label class="form-check-label" for="editEnableSsl">SSL Etkinleştir</label>
                        </div>
                        <div id="editSslSettingsGroup" style="display: none;">
                            <div class="mb-3">
                                <label for="editSslCertificatePath" class="form-label">SSL Sertifika Dosyası Yolu</label>
                                <input type="text" class="form-control" id="editSslCertificatePath" name="ssl_certificate_path"
                                    value="<?= defined('SSL_CERTIFICATE_FILE') ? SSL_CERTIFICATE_FILE : '' ?>">
                                <div class="form-text">
                                    Varsayılan sertifika yerine özel bir sertifika yolu belirtmek için.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editSslKeyPath" class="form-label">SSL Anahtar Dosyası Yolu</label>
                                <input type="text" class="form-control" id="editSslKeyPath" name="ssl_key_path"
                                    value="<?= defined('SSL_CERTIFICATE_KEY_FILE') ? SSL_CERTIFICATE_KEY_FILE : '' ?>">
                                <div class="form-text">
                                    Varsayılan anahtar dosyası yerine özel bir anahtar dosyası yolu belirtmek için.
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="editCreateDocumentRoot" name="create_document_root">
                            <label class="form-check-label" for="editCreateDocumentRoot">Belge kök dizini yoksa oluştur</label>
                        </div>
                        <div class="mb-3">
                            <label for="editIndexFileType" class="form-label">Varsayılan index dosyası</label>
                            <select class="form-select" id="editIndexFileType" name="index_file_type">
                                <option value="none" selected>Oluşturma</option>
                                <option value="html">index.html</option>
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

    <!-- Stats Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statsModalLabel">Sunucu İstatistikleri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div id="statsLoadingIndicator" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                                <p>İstatistikler yükleniyor...</p>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="card stats-card h-100 border-left-primary">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">CPU Kullanımı</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stats-cpu-load">-</div>
                                            <div class="mt-2 progress">
                                                <div id="stats-cpu-progress" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-cpu fs-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="card stats-card h-100 border-left-success">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Bellek Kullanımı</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stats-memory-used">-</div>
                                            <div class="mt-2 progress">
                                                <div id="stats-memory-progress" class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-memory fs-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="card stats-card h-100 border-left-info">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Disk Kullanımı</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stats-disk-used">-</div>
                                            <div class="mt-2 progress">
                                                <div id="stats-disk-progress" class="progress-bar bg-info" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-hdd fs-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <div class="card stats-card h-100 border-left-warning">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Aktif Bağlantılar</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stats-connections">-</div>
                                            <div class="small text-muted mt-2" id="stats-uptime">-</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-diagram-3 fs-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <ul class="nav nav-tabs" id="statsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="true">Sistem Bilgileri</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="vhosts-tab" data-bs-toggle="tab" data-bs-target="#vhosts" type="button" role="tab" aria-controls="vhosts" aria-selected="false">Sanal Host İstatistikleri</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="connections-tab" data-bs-toggle="tab" data-bs-target="#connections" type="button" role="tab" aria-controls="connections" aria-selected="false">Bağlantı Detayları</button>
                                </li>
                            </ul>
                            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="statsTabContent">
                                <div class="tab-pane fade show active" id="system" role="tabpanel" aria-labelledby="system-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="mb-3">Sistem Bilgileri</h5>
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <th width="30%">İşletim Sistemi</th>
                                                        <td id="stats-os">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Sunucu Yazılımı</th>
                                                        <td id="stats-server-software">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Bilgisayar Adı</th>
                                                        <td id="stats-hostname">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Çalışma Süresi</th>
                                                        <td id="stats-uptime-full">-</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="mb-3">PHP Bilgileri</h5>
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <th width="30%">PHP Sürümü</th>
                                                        <td id="stats-php-version">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Bellek Limiti</th>
                                                        <td id="stats-php-memory-limit">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Çalışma Süresi</th>
                                                        <td id="stats-php-max-execution-time">-</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Yükleme Limiti</th>
                                                        <td id="stats-php-upload-max-filesize">-</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="vhosts" role="tabpanel" aria-labelledby="vhosts-tab">
                                    <table class="table table-sm table-hover" id="stats-vhosts-table">
                                        <thead>
                                            <tr>
                                                <th width="30%">Sanal Host</th>
                                                <th>Hit</th>
                                                <th>Hata</th>
                                                <th>Son Erişim</th>
                                                <th>Access Log</th>
                                                <th>Error Log</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="6" class="text-center">Veri yükleniyor...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="connections" role="tabpanel" aria-labelledby="connections-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="mb-3">Bağlantı Özeti</h5>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="h6 mb-0">Toplam Aktif Bağlantı:</span>
                                                        <span class="badge bg-primary fs-6" id="stats-connections-count">0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="mb-3">En Çok Bağlantı Yapan IP'ler</h5>
                                            <table class="table table-sm table-hover" id="stats-connections-table">
                                                <thead>
                                                    <tr>
                                                        <th>IP Adresi</th>
                                                        <th>Bağlantı Sayısı</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="2" class="text-center">Veri yükleniyor...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted me-auto" id="stats-last-update">Son Güncelleme: -</div>
                    <button type="button" class="btn btn-outline-primary me-2" id="statsRefreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Yenile
                    </button>
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