document.addEventListener('DOMContentLoaded', function () {
    // Bootstrap tab işlevselliğini etkinleştir
    const tabElements = document.querySelectorAll('[data-bs-toggle="tab"]');
    if (tabElements.length > 0) {
        tabElements.forEach(tab => {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                const target = document.querySelector(this.getAttribute('data-bs-target'));

                // Tüm tab panellerini gizle
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });

                // Tüm tab butonlarını devre dışı bırak
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                    link.setAttribute('aria-selected', 'false');
                });

                // Seçilen tab ve paneli aktifleştir
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                target.classList.add('show', 'active');
            });
        });
    }

    // Modal açılışında formları sıfırlama
    const addVhostModal = document.getElementById('addVhostModal');
    if (addVhostModal) {
        addVhostModal.addEventListener('show.bs.modal', resetAddVhostForm);
    }
    
    const editVhostModal = document.getElementById('editVhostModal');
    if (editVhostModal) {
        editVhostModal.addEventListener('show.bs.modal', function(event) {
            // Önce formu sıfırla
            resetEditVhostForm();
            
            // Tetikleyen butonu bul
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('edit-vhost')) {
                return; // Edit butonu değilse işlemi durdur
            }
            
            // Form verilerini doldur
            document.getElementById('editServerName').value = button.dataset.serverName || '';
            document.getElementById('editDocumentRoot').value = button.dataset.documentRoot || '';
            document.getElementById('editServerAlias').value = button.dataset.serverAlias || '';
            document.getElementById('editConfFile').value = button.dataset.confFile || '';

            // PHP sürümü seçimini ayarla
            const phpVersionSelect = document.getElementById('editPhpVersion');
            const phpVersion = button.dataset.phpVersion || 'Default';
            if (phpVersionSelect) {
                for (let i = 0; i < phpVersionSelect.options.length; i++) {
                    if (phpVersionSelect.options[i].value === phpVersion) {
                        phpVersionSelect.selectedIndex = i;
                        break;
                    }
                }
            }

            // SSL seçeneğini ayarla
            const sslEnabled = button.dataset.ssl === 'true';
            const editEnableSslCheckbox = document.getElementById('editEnableSsl');
            const editSslSettingsGroup = document.getElementById('editSslSettingsGroup');
            
            // Checkbox'ı güncelle
            if (editEnableSslCheckbox) {
                editEnableSslCheckbox.checked = sslEnabled;
            }
            
            // SSL ayarlarının görünürlüğünü güncelle
            if (editSslSettingsGroup) {
                editSslSettingsGroup.style.display = sslEnabled ? 'block' : 'none';
            }
            
        });
    }
    
    const deleteVhostModal = document.getElementById('deleteVhostModal');
    if (deleteVhostModal) {
        deleteVhostModal.addEventListener('show.bs.modal', resetDeleteVhostForm);
    }
    
    const proxyModal = document.getElementById('proxyModal');
    if (proxyModal) {
        proxyModal.addEventListener('show.bs.modal', resetProxyForm);
    }
    
    const logsModal = document.getElementById('logsModal');
    if (logsModal) {
        logsModal.addEventListener('show.bs.modal', resetLogsForm);
    }

    // Proxy form submission
    const proxyForm = document.getElementById('proxyForm');
    if (proxyForm) {
        proxyForm.addEventListener('submit', function (e) {
            e.preventDefault();

            fetch('save_config.php', {
                method: 'POST',
                body: new FormData(proxyForm)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('proxyModal'));
                        modal.hide();

                        // Refresh status checks
                        const statusIndicators = document.querySelectorAll('.status-indicator');
                        statusIndicators.forEach((indicator, index) => {
                            setTimeout(() => checkStatus(indicator), index * 500);
                        });
                    } else {
                        alert('Failed to save proxy settings: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving proxy settings:', error);
                    alert('Failed to save proxy settings. Please try again.');
                });
        });
    }

    // VHost form submission
    const addVhostForm = document.getElementById('addVhostForm');
    const saveVhostBtn = document.getElementById('saveVhostBtn');
    const enableSslCheckbox = document.getElementById('enableSsl');
    const sslSettingsGroup = document.getElementById('sslSettingsGroup');

    // SSL seçeneği işlevselliği
    if (enableSslCheckbox && sslSettingsGroup) {
        enableSslCheckbox.addEventListener('change', function () {
            sslSettingsGroup.style.display = this.checked ? 'block' : 'none';
        });
    }

    if (addVhostForm && saveVhostBtn) {
        saveVhostBtn.addEventListener('click', function () {
            // Form doğrulama kontrolü
            if (!addVhostForm.checkValidity()) {
                addVhostForm.reportValidity();
                return;
            }

            const formData = new FormData(addVhostForm);
            const feedback = document.getElementById('vhostFormFeedback');

            // Geri bildirim mesaj alanını temizle
            feedback.classList.add('d-none');
            feedback.classList.remove('alert-success', 'alert-danger');
            feedback.textContent = '';

            // Sanal host ekleme isteği gönder
            fetch('add_vhost.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        feedback.classList.remove('d-none', 'alert-danger');
                        feedback.classList.add('alert-success');
                        feedback.textContent = data.message;

                        // 2 saniye sonra sayfayı yenile
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        feedback.classList.remove('d-none');
                        feedback.classList.add('alert-danger');
                        feedback.textContent = data.message || 'Sanal host eklenirken bir hata oluştu.';
                    }
                })
                .catch(error => {
                    console.error('Error adding virtual host:', error);
                    feedback.classList.remove('d-none');
                    feedback.classList.add('alert-danger');
                    feedback.textContent = 'Sunucuyla iletişim hatası.';
                });
        });
    }

    // Edit VHost form handlers
    const editButtons = document.querySelectorAll('.edit-vhost');
    const editVhostForm = document.getElementById('editVhostForm');
    const updateVhostBtn = document.getElementById('updateVhostBtn');
    const editEnableSslCheckbox = document.getElementById('editEnableSsl');
    const editSslSettingsGroup = document.getElementById('editSslSettingsGroup');

    // Düzenleme formunda SSL seçeneği işlevselliği
    if (editEnableSslCheckbox && editSslSettingsGroup) {
        editEnableSslCheckbox.addEventListener('change', function () {
            editSslSettingsGroup.style.display = this.checked ? 'block' : 'none';
        });
    }

    if (editButtons.length && editVhostForm) {
        // Formdaki update butonu işlevselliği
        if (updateVhostBtn) {
            updateVhostBtn.addEventListener('click', function () {
                // Form doğrulama kontrolü
                if (!editVhostForm.checkValidity()) {
                    editVhostForm.reportValidity();
                    return;
                }

                const formData = new FormData(editVhostForm);
                const feedback = document.getElementById('editVhostFormFeedback');

                // Geri bildirim mesaj alanını temizle
                feedback.classList.add('d-none');
                feedback.classList.remove('alert-success', 'alert-danger');
                feedback.textContent = '';

                // Sanal host güncelleme isteği gönder
                fetch('edit_vhost.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            feedback.classList.remove('d-none', 'alert-danger');
                            feedback.classList.add('alert-success');
                            feedback.textContent = data.message;

                            // 2 saniye sonra sayfayı yenile
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            feedback.classList.remove('d-none');
                            feedback.classList.add('alert-danger');
                            feedback.textContent = data.message || 'Sanal host güncellenirken bir hata oluştu.';
                        }
                    })
                    .catch(error => {
                        console.error('Error updating virtual host:', error);
                        feedback.classList.remove('d-none');
                        feedback.classList.add('alert-danger');
                        feedback.textContent = 'Sunucuyla iletişim hatası.';
                    });
            });
        }
    }

    // Delete VHost handlers
    const deleteButtons = document.querySelectorAll('.delete-vhost');
    const deleteVhostForm = document.getElementById('deleteVhostForm');
    const confirmDeleteVhostBtn = document.getElementById('confirmDeleteVhostBtn');

    if (deleteButtons.length && deleteVhostForm) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function () {
                const serverName = this.dataset.serverName;
                const confFile = this.dataset.confFile;

                // Form verilerini doldur
                document.getElementById('deleteConfFile').value = confFile;
                document.getElementById('deleteServerName').value = serverName;
                document.getElementById('deleteVhostName').textContent = serverName;

                // Modalı göster
                const modal = new bootstrap.Modal(deleteVhostModal);
                modal.show();
            });
        });

        // Silme onay butonu işlevselliği
        if (confirmDeleteVhostBtn) {
            confirmDeleteVhostBtn.addEventListener('click', function () {
                const formData = new FormData(deleteVhostForm);
                const feedback = document.getElementById('deleteVhostFormFeedback');

                // Geri bildirim mesaj alanını temizle
                feedback.classList.add('d-none');
                feedback.classList.remove('alert-success', 'alert-danger');
                feedback.textContent = '';

                // Sanal host silme isteği gönder
                fetch('delete_vhost.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            feedback.classList.remove('d-none', 'alert-danger');
                            feedback.classList.add('alert-success');
                            feedback.textContent = data.message;

                            // 2 saniye sonra sayfayı yenile
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            feedback.classList.remove('d-none');
                            feedback.classList.add('alert-danger');
                            feedback.textContent = data.message || 'Sanal host silinirken bir hata oluştu.';
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting virtual host:', error);
                        feedback.classList.remove('d-none');
                        feedback.classList.add('alert-danger');
                        feedback.textContent = 'Sunucuyla iletişim hatası.';
                    });
            });
        }
    }

    // Status checking functionality
    function checkStatus(statusIndicator) {
        const server = statusIndicator.dataset.server;
        const ssl = statusIndicator.dataset.ssl === 'true';
        const dot = statusIndicator.querySelector('.status-dot');
        const text = statusIndicator.querySelector('.status-text');

        statusIndicator.classList.add('status-checking');
        text.textContent = '...';

        fetch(`check_status.php?server=${encodeURIComponent(server)}&ssl=${ssl}`)
            .then(response => response.json())
            .then(data => {
                statusIndicator.classList.remove('status-checking');
                statusIndicator.classList.remove('status-online', 'status-offline', 'status-error');

                switch (data.status) {
                    case 'online':
                        statusIndicator.classList.add('status-online');
                        text.textContent = 'Online';
                        break;
                    case 'offline':
                        statusIndicator.classList.add('status-offline');
                        text.textContent = 'Offline';
                        break;
                    case 'error':
                        statusIndicator.classList.add('status-error');
                        text.textContent = `Error (${data.code})`;
                        break;
                }
            })
            .catch(error => {
                statusIndicator.classList.remove('status-checking');
                statusIndicator.classList.add('status-error');
                text.textContent = 'Check Failed';
                console.error('Status check error:', error);
            });
    }

    // Check all statuses initially
    const statusIndicators = document.querySelectorAll('.status-indicator');
    statusIndicators.forEach((indicator, index) => {
        // Stagger the checks to avoid overwhelming the server
        setTimeout(() => checkStatus(indicator), index * 500);
    });

    // Refresh status every 5 minutes
    setInterval(() => {
        statusIndicators.forEach((indicator, index) => {
            setTimeout(() => checkStatus(indicator), index * 500);
        });
    }, 5 * 60 * 1000);

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const vhostCards = document.getElementById('vhostCards');
    const cards = vhostCards.getElementsByClassName('vhost-item');

    searchInput.addEventListener('keyup', function () {
        const searchTerm = searchInput.value.toLowerCase();

        Array.from(cards).forEach(card => {
            const cardContent = card.textContent.toLowerCase();
            card.style.display = cardContent.includes(searchTerm) ? '' : 'none';
        });

        updateCounter();
    });

    // Counter functionality
    function updateCounter() {
        const counter = document.getElementById('vhostCounter');
        const visibleCards = Array.from(cards).filter(card =>
            card.style.display !== 'none'
        ).length;
        const totalCards = cards.length;
        counter.textContent = `${visibleCards} / ${totalCards} sanal host gösteriliyor`;
    }

    // Log viewer functionality
    const logModal = document.getElementById('logsModal');
    const logType = document.getElementById('logType');
    const serverSelect = document.getElementById('serverSelect');
    const logLineCount = document.getElementById('logLineCount');
    const logSearchInput = document.getElementById('logSearchInput');
    const logSearchBtn = document.getElementById('logSearchBtn');
    const logRefreshBtn = document.getElementById('logRefreshBtn');
    const logContent = document.getElementById('logContent');
    const logLoadingIndicator = document.getElementById('logLoadingIndicator');
    const logFileInfo = document.getElementById('logFileInfo');

    if (logRefreshBtn && logContent) {
        // Log yükleme fonksiyonu
        function loadLogs() {
            // Önce loading göster, içeriği gizle
            logLoadingIndicator.classList.remove('d-none');
            logContent.innerHTML = '';

            const formData = new FormData();
            formData.append('log_type', logType.value);
            formData.append('server_name', serverSelect.value);
            formData.append('line_count', logLineCount.value);

            const searchTerm = logSearchInput.value.trim();
            if (searchTerm) {
                formData.append('search_term', searchTerm);
            }

            fetch('logs.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    logLoadingIndicator.classList.add('d-none');

                    if (data.success) {
                        // Dosya bilgisini göster
                        const searchInfo = searchTerm ? ` (Filtre: "${searchTerm}")` : '';
                        logFileInfo.textContent = `${data.file} - ${data.count} satır gösteriliyor${searchInfo}`;

                        // İçeriği temizle
                        logContent.innerHTML = '';

                        if (data.lines.length === 0) {
                            logContent.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Log dosyası boş veya arama kriterine uygun satır bulunamadı.</div>';
                            return;
                        }

                        // Her satırı ekle
                        data.lines.forEach(line => {
                            const logEntry = document.createElement('div');
                            logEntry.className = `log-entry log-entry-${line.level}`;

                            // Satır numarası ve metin ekleme
                            logEntry.innerHTML = `<span class="log-entry-line-number">${line.index}</span>${line.text}`;

                            // Arama terimi varsa vurgulama
                            if (searchTerm) {
                                const highlightedText = logEntry.innerHTML.replace(
                                    new RegExp(searchTerm, 'gi'),
                                    match => `<mark>${match}</mark>`
                                );
                                logEntry.innerHTML = highlightedText;
                            }

                            logContent.appendChild(logEntry);
                        });

                        // En alt satıra otomatik scroll
                        logContent.scrollTop = logContent.scrollHeight;

                    } else {
                        // Hata mesajı göster
                        logContent.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ${data.message}</div>`;
                        logFileInfo.textContent = '';
                    }
                })
                .catch(error => {
                    logLoadingIndicator.classList.add('d-none');
                    logContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Log dosyası yüklenirken bir hata oluştu.</div>';
                    console.error('Error loading logs:', error);
                    logFileInfo.textContent = '';
                });
        }

        // Butonlara olay dinleyicileri ekle
        logRefreshBtn.addEventListener('click', loadLogs);
        logSearchBtn.addEventListener('click', loadLogs);

        // Enter tuşu ile arama
        logSearchInput.addEventListener('keyup', function (event) {
            if (event.key === 'Enter') {
                loadLogs();
            }
        });

        // Log tipini veya sunucuyu değiştirdiğimizde arama terimini temizle
        logType.addEventListener('change', function () {
            logSearchInput.value = '';
        });

        serverSelect.addEventListener('change', function () {
            logSearchInput.value = '';
        });

        // Modal açıldığında otomatik olarak yükle
        if (logModal) {
            logModal.addEventListener('shown.bs.modal', function () {
                loadLogs();
            });
        }
    }

    // Stats functionality
    const statsModal = document.getElementById('statsModal');
    const statsRefreshBtn = document.getElementById('statsRefreshBtn');
    const statsLoadingIndicator = document.getElementById('statsLoadingIndicator');
    const statsLastUpdate = document.getElementById('stats-last-update');

    // İstatistik verilerini yükleme fonksiyonu
    function loadStats() {
        statsLoadingIndicator.classList.remove('d-none');

        // Tüm istatistikleri al
        fetch('stats.php?action=all_stats')
            .then(response => response.json())
            .then(data => {
                statsLoadingIndicator.classList.add('d-none');
                updateStatsDisplay(data);

                // Son güncelleme zamanını göster
                const now = new Date();
                statsLastUpdate.textContent = 'Son Güncelleme: ' +
                    now.toLocaleDateString('tr-TR') + ' ' +
                    now.toLocaleTimeString('tr-TR');
            })
            .catch(error => {
                statsLoadingIndicator.classList.add('d-none');
                console.error('Error loading stats:', error);
                alert('İstatistikler yüklenirken bir hata oluştu.');
            });
    }

    // İstatistik verilerini görüntüleme fonksiyonu
    function updateStatsDisplay(data) {
        // Sunucu İstatistikleri
        if (data.server) {
            const server = data.server;

            // CPU kullanımı
            const cpuLoad = document.getElementById('stats-cpu-load');
            const cpuProgress = document.getElementById('stats-cpu-progress');

            if (server.cpu) {
                const loadAvg = server.cpu.load_avg_1;
                cpuLoad.textContent = loadAvg;

                // CPU load, 1 değerini %100 olarak kabul edelim (tek çekirdekli CPU için)
                let cpuPercent = Math.min(loadAvg * 100, 100);
                cpuProgress.style.width = cpuPercent + '%';
                cpuProgress.setAttribute('aria-valuenow', cpuPercent);

                // Renklendirme
                cpuProgress.classList.remove('bg-success', 'bg-warning', 'bg-danger');
                if (cpuPercent < 50) {
                    cpuProgress.classList.add('bg-success');
                } else if (cpuPercent < 75) {
                    cpuProgress.classList.add('bg-warning');
                } else {
                    cpuProgress.classList.add('bg-danger');
                }
            }

            // Bellek kullanımı
            const memoryUsed = document.getElementById('stats-memory-used');
            const memoryProgress = document.getElementById('stats-memory-progress');

            if (server.memory) {
                memoryUsed.textContent = server.memory.used + ' MB / ' + server.memory.total + ' MB';

                const memoryPercent = server.memory.percent_used;
                memoryProgress.style.width = memoryPercent + '%';
                memoryProgress.setAttribute('aria-valuenow', memoryPercent);

                // Renklendirme
                memoryProgress.classList.remove('bg-success', 'bg-warning', 'bg-danger');
                if (memoryPercent < 50) {
                    memoryProgress.classList.add('bg-success');
                } else if (memoryPercent < 75) {
                    memoryProgress.classList.add('bg-warning');
                } else {
                    memoryProgress.classList.add('bg-danger');
                }
            }

            // Disk kullanımı
            const diskUsed = document.getElementById('stats-disk-used');
            const diskProgress = document.getElementById('stats-disk-progress');

            if (server.disk) {
                diskUsed.textContent = server.disk.used + ' GB / ' + server.disk.total + ' GB';

                const diskPercent = server.disk.percent_used;
                diskProgress.style.width = diskPercent + '%';
                diskProgress.setAttribute('aria-valuenow', diskPercent);

                // Renklendirme
                diskProgress.classList.remove('bg-info', 'bg-warning', 'bg-danger');
                if (diskPercent < 70) {
                    diskProgress.classList.add('bg-info');
                } else if (diskPercent < 90) {
                    diskProgress.classList.add('bg-warning');
                } else {
                    diskProgress.classList.add('bg-danger');
                }
            }

            // Sistem bilgileri
            if (server.system) {
                document.getElementById('stats-os').textContent = server.system.os;
                document.getElementById('stats-server-software').textContent = server.system.server_software;
                document.getElementById('stats-hostname').textContent = server.system.hostname;
                document.getElementById('stats-uptime-full').textContent = server.system.uptime;
                document.getElementById('stats-uptime').textContent = 'Uptime: ' + server.system.uptime;
            }

            // PHP bilgileri
            if (server.php) {
                document.getElementById('stats-php-version').textContent = server.php.version;
                document.getElementById('stats-php-memory-limit').textContent = server.php.memory_limit;
                document.getElementById('stats-php-max-execution-time').textContent = server.php.max_execution_time + ' saniye';
                document.getElementById('stats-php-upload-max-filesize').textContent = server.php.upload_max_filesize;
            }
        }

        // Bağlantı bilgileri
        if (data.connections) {
            const connections = data.connections;
            document.getElementById('stats-connections').textContent = connections.total;
            document.getElementById('stats-connections-count').textContent = connections.total;

            // IP tablosu
            const ipTable = document.getElementById('stats-connections-table').querySelector('tbody');
            ipTable.innerHTML = '';

            if (Object.keys(connections.by_ip).length > 0) {
                for (const [ip, count] of Object.entries(connections.by_ip)) {
                    const row = document.createElement('tr');

                    const ipCell = document.createElement('td');
                    ipCell.textContent = ip;

                    const countCell = document.createElement('td');
                    countCell.textContent = count;

                    row.appendChild(ipCell);
                    row.appendChild(countCell);
                    ipTable.appendChild(row);
                }
            } else {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 2;
                cell.className = 'text-center';
                cell.textContent = 'Aktif bağlantı yok.';
                row.appendChild(cell);
                ipTable.appendChild(row);
            }
        }

        // Sanal host istatistikleri
        if (data.vhosts) {
            const vhosts = data.vhosts;
            const vhostsTable = document.getElementById('stats-vhosts-table').querySelector('tbody');
            vhostsTable.innerHTML = '';

            if (Object.keys(vhosts).length > 0) {
                for (const [serverName, stats] of Object.entries(vhosts)) {
                    const row = document.createElement('tr');

                    // Sanal host adı
                    const nameCell = document.createElement('td');
                    nameCell.textContent = serverName;

                    // Hit sayısı
                    const hitsCell = document.createElement('td');
                    hitsCell.textContent = stats.hits.toLocaleString();

                    // Hata sayısı
                    const errorsCell = document.createElement('td');
                    errorsCell.textContent = stats.errors.toLocaleString();

                    // Son erişim
                    const lastAccessCell = document.createElement('td');
                    lastAccessCell.textContent = stats.last_access;

                    // Access log boyutu
                    const accessLogCell = document.createElement('td');
                    accessLogCell.textContent = formatBytes(stats.access_log_size);

                    // Error log boyutu
                    const errorLogCell = document.createElement('td');
                    errorLogCell.textContent = formatBytes(stats.error_log_size);

                    row.appendChild(nameCell);
                    row.appendChild(hitsCell);
                    row.appendChild(errorsCell);
                    row.appendChild(lastAccessCell);
                    row.appendChild(accessLogCell);
                    row.appendChild(errorLogCell);
                    vhostsTable.appendChild(row);
                }
            } else {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 6;
                cell.className = 'text-center';
                cell.textContent = 'Sanal host bulunamadı.';
                row.appendChild(cell);
                vhostsTable.appendChild(row);
            }
        }
    }

    // Boyut birimlerini okunaklı formata dönüştüren yardımcı fonksiyon
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 B';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // Butonlara olay dinleyicileri ekle
    if (statsRefreshBtn) {
        statsRefreshBtn.addEventListener('click', loadStats);
    }

    // Modal açıldığında otomatik olarak yükle
    if (statsModal) {
        statsModal.addEventListener('shown.bs.modal', function () {
            loadStats();
        });
    }

    // Initial counter update
    updateCounter();

    // PHP özel ayarları işlevselliği (Ekleme formu)
    const usePHPIniSettings = document.getElementById('usePHPIniSettings');
    const phpSettingsBody = document.querySelector('.php-settings-body');
    
    if (usePHPIniSettings && phpSettingsBody) {
        usePHPIniSettings.addEventListener('change', function() {
            phpSettingsBody.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // PHP özel ayarları işlevselliği (Düzenleme formu)
    const editUsePHPIniSettings = document.getElementById('editUsePHPIniSettings');
    const editPhpSettingsBody = document.querySelector('#editPhpSettings .php-settings-body');
    
    if (editUsePHPIniSettings && editPhpSettingsBody) {
        editUsePHPIniSettings.addEventListener('change', function() {
            editPhpSettingsBody.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Modal formlarının içeriğini sıfırlama fonksiyonları
    function resetAddVhostForm() {
        const form = document.getElementById('addVhostForm');
        if (form) {
            form.reset();
            
            // PHP ayarları panelini gizle
            const phpSettingsBody = document.querySelector('#phpSettings .php-settings-body');
            if (phpSettingsBody) {
                phpSettingsBody.style.display = 'none';
            }
            
            // SSL ayarları panelini gizle
            const sslSettingsGroup = document.getElementById('sslSettingsGroup');
            if (sslSettingsGroup) {
                sslSettingsGroup.style.display = 'none';
            }
            
            // Varsayılan değerleri ayarla
            const phpVersionSelect = document.getElementById('phpVersion');
            if (phpVersionSelect) {
                phpVersionSelect.selectedIndex = 0;
            }
            
            // Geri bildirim mesaj alanını temizle
            const feedback = document.getElementById('vhostFormFeedback');
            if (feedback) {
                feedback.classList.add('d-none');
                feedback.classList.remove('alert-success', 'alert-danger');
                feedback.textContent = '';
            }
        }
    }

    function resetEditVhostForm() {
        const form = document.getElementById('editVhostForm');
        if (form) {
            form.reset();
            
            // PHP ayarları panelini gizle
            const phpSettingsBody = document.querySelector('#editPhpSettings .php-settings-body');
            if (phpSettingsBody) {
                phpSettingsBody.style.display = 'none';
            }
            
            // SSL checkbox işaretini kaldır
            const editEnableSslCheckbox = document.getElementById('editEnableSsl');
            if (editEnableSslCheckbox) {
                editEnableSslCheckbox.checked = false;
            }
            
            // SSL ayarları panelini gizle
            const sslSettingsGroup = document.getElementById('editSslSettingsGroup');
            if (sslSettingsGroup) {
                sslSettingsGroup.style.display = 'none';
            }
            
            // Geri bildirim mesaj alanını temizle
            const feedback = document.getElementById('editVhostFormFeedback');
            if (feedback) {
                feedback.classList.add('d-none');
                feedback.classList.remove('alert-success', 'alert-danger');
                feedback.textContent = '';
            }
        }
    }

    function resetDeleteVhostForm() {
        const form = document.getElementById('deleteVhostForm');
        if (form) {
            form.reset();
            
            // Geri bildirim mesaj alanını temizle
            const feedback = document.getElementById('deleteVhostFormFeedback');
            if (feedback) {
                feedback.classList.add('d-none');
                feedback.classList.remove('alert-success', 'alert-danger');
                feedback.textContent = '';
            }
        }
    }

    function resetProxyForm() {
        const form = document.getElementById('proxyForm');
        if (form) {
            // Form değerlerini sıfırlama yerine, ayarların değişmemesi için sıfırlamıyoruz
            // form.reset();
            
            // Tab seçimlerini sıfırla
            const tabElements = document.querySelectorAll('#settingsTabs .nav-link');
            tabElements.forEach(tab => {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            });
            
            // İlk tab'ı aktif yap
            const firstTab = document.querySelector('#settingsTabs .nav-link');
            if (firstTab) {
                firstTab.classList.add('active');
                firstTab.setAttribute('aria-selected', 'true');
            }
            
            // Tab panellerini gizle
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // İlk tab panelini göster
            const firstPane = document.querySelector('.tab-pane');
            if (firstPane) {
                firstPane.classList.add('show', 'active');
            }
        }
    }

    function resetLogsForm() {
        // Log tipi ve sunucu seçimi sıfırlama
        const logType = document.getElementById('logType');
        const serverSelect = document.getElementById('serverSelect');
        const logSearchInput = document.getElementById('logSearchInput');
        
        if (logType) logType.selectedIndex = 0;
        if (serverSelect) serverSelect.selectedIndex = 0;
        if (logSearchInput) logSearchInput.value = '';
    }
});