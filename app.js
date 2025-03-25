document.addEventListener('DOMContentLoaded', function () {
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
    const editVhostModal = document.getElementById('editVhostModal');
    const updateVhostBtn = document.getElementById('updateVhostBtn');
    
    if (editButtons.length && editVhostForm) {
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Form verilerini doldur
                document.getElementById('editServerName').value = this.dataset.serverName;
                document.getElementById('editDocumentRoot').value = this.dataset.documentRoot;
                document.getElementById('editServerAlias').value = this.dataset.serverAlias || '';
                document.getElementById('editConfFile').value = this.dataset.confFile;
                
                const phpVersionSelect = document.getElementById('editPhpVersion');
                const phpVersion = this.dataset.phpVersion;
                for (let i = 0; i < phpVersionSelect.options.length; i++) {
                    if (phpVersionSelect.options[i].value === phpVersion) {
                        phpVersionSelect.selectedIndex = i;
                        break;
                    }
                }
                
                document.getElementById('editEnableSsl').checked = this.dataset.ssl === 'true';
                
                // Modalı göster
                const modal = new bootstrap.Modal(editVhostModal);
                modal.show();
            });
        });
        
        // Güncelleme butonu işlevselliği
        if (updateVhostBtn) {
            updateVhostBtn.addEventListener('click', function() {
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
    const deleteVhostModal = document.getElementById('deleteVhostModal');
    const confirmDeleteVhostBtn = document.getElementById('confirmDeleteVhostBtn');
    
    if (deleteButtons.length && deleteVhostForm) {
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
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
            confirmDeleteVhostBtn.addEventListener('click', function() {
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
        text.textContent = 'Checking...';

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

    // Initial counter update
    updateCounter();
});