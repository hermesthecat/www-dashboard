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

    // Status checking functionality
    function checkStatus(statusIndicator) {
        const server = statusIndicator.dataset.server;
        const dot = statusIndicator.querySelector('.status-dot');
        const text = statusIndicator.querySelector('.status-text');

        statusIndicator.classList.add('status-checking');
        text.textContent = 'Checking...';

        fetch(`check_status.php?server=${encodeURIComponent(server)}`)
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
    const vhostTable = document.getElementById('vhostTable');
    const tableRows = vhostTable.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function () {
        const searchTerm = searchInput.value.toLowerCase();

        // Start from 1 to skip header row
        for (let i = 1; i < tableRows.length; i++) {
            const row = tableRows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;

            for (let j = 0; j < cells.length - 1; j++) { // -1 to skip Actions column
                const cellText = cells[j].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    found = true;
                    break;
                }
            }

            row.style.display = found ? '' : 'none';
        }
    });

    // Sorting functionality
    const ths = document.getElementsByClassName('sortable');
    let lastSortedColumn = null;
    let ascending = true;

    Array.from(ths).forEach((th, index) => {
        th.addEventListener('click', function () {
            const tbody = vhostTable.querySelector('tbody');
            const rows = Array.from(tbody.getElementsByTagName('tr'));

            // Reset arrows on all headers except current
            Array.from(ths).forEach(header => {
                if (header !== th) {
                    header.classList.remove('asc', 'desc');
                }
            });

            // Determine sort direction
            if (lastSortedColumn === index) {
                ascending = !ascending;
            } else {
                ascending = true;
            }
            lastSortedColumn = index;

            // Update arrow class
            th.classList.toggle('asc', ascending);
            th.classList.toggle('desc', !ascending);

            // Sort rows
            rows.sort((a, b) => {
                const aText = a.cells[index].textContent.trim().toLowerCase();
                const bText = b.cells[index].textContent.trim().toLowerCase();
                return ascending ?
                    aText.localeCompare(bText) :
                    bText.localeCompare(aText);
            });

            // Reattach sorted rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Counter functionality
    function updateCounter() {
        const counter = document.getElementById('vhostCounter');
        const visibleRows = Array.from(tableRows).slice(1).filter(row =>
            row.style.display !== 'none'
        ).length;
        counter.textContent = `Showing ${visibleRows} of ${tableRows.length - 1} vhosts`;
    }

    // Update counter on search
    searchInput.addEventListener('keyup', updateCounter);

    // Initial counter update
    updateCounter();
});