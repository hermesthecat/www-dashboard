<?php
// Function to parse vhosts from httpd-vhosts.conf
function parseVhosts($configFile)
{
    $vhosts = [];
    $content = file_get_contents($configFile);

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
        if (preg_match('/DocumentRoot\s+([^\s]+)/', $vhostBlock, $docRoot)) {
            $vhost['documentRoot'] = $docRoot[1];
        }

        // Extract ServerAlias
        if (preg_match('/ServerAlias\s+(.+)/', $vhostBlock, $serverAlias)) {
            $vhost['serverAlias'] = $serverAlias[1];
        }

        if (!empty($vhost)) {
            $vhosts[] = $vhost;
        }
    }

    return $vhosts;
}

// Include configuration
require_once 'config.php';

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
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#proxyModal">
                                <i class="bi bi-gear"></i> Proxy Settings
                            </button>
                            <div id="vhostCounter" class="text-muted"></div>
                        </div>
                        <div class="mt-3">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search virtual hosts...">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="vhostTable">
                                <thead>
                                    <tr>
                                        <th class="sortable">Status <span class="sort-arrow"></span></th>
                                        <th class="sortable">Server Name <span class="sort-arrow"></span></th>
                                        <th class="sortable">Document Root <span class="sort-arrow"></span></th>
                                        <th class="sortable">Server Admin <span class="sort-arrow"></span></th>
                                        <th class="sortable">Server Alias <span class="sort-arrow"></span></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vhosts as $vhost): ?>
                                        <tr>
                                            <td>
                                                <span class="status-indicator" data-server="<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>">
                                                    <span class="status-dot"></span>
                                                    <span class="status-text">Checking...</span>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($vhost['documentRoot'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($vhost['serverAdmin'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($vhost['serverAlias'] ?? ''); ?></td>
                                            <td>
                                                <a href="http://<?php echo htmlspecialchars($vhost['serverName'] ?? ''); ?>"
                                                    class="btn btn-primary btn-sm"
                                                    target="_blank">Visit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>

</html>