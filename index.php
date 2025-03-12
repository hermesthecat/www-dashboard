<?php
// Function to parse vhosts from httpd-vhosts.conf
function parseVhosts($configFile) {
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

// Parse vhosts
$vhosts = parseVhosts('httpd-vhosts.conf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WWW Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>
</html>