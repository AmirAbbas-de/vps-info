<?php
    $serversData = json_decode(file_get_contents('virtualizor_servers_info_2025-12-28_101516.json'), true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtualizor Servers Info</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e9ecef;
            --bg-card: rgba(0, 0, 0, 0.02);
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --accent: #007bff;
            --accent-hover: #0056b3;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --border: rgba(0, 0, 0, 0.125);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
        }

        .navbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--accent);
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
            border-radius: 12px;
        }

        .table {
            background: transparent;
            color: var(--text-primary);
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: var(--bg-tertiary);
            border: none;
            color: var(--text-primary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.7rem;
            padding: 1rem 0.75rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .metric-value {
            font-weight: 700;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
        }

        .status-online {
            background: var(--success);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .status-offline {
            background: var(--danger);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .locked-yes {
            background: var(--warning);
            color: #212529;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .locked-no {
            background: var(--success);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .container-fluid {
            max-width: 1600px;
            padding: 0 2rem;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 0 1rem;
            }

            .table-responsive {
                max-height: 50vh;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-hover);
        }

        .filter-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.7rem;
        }

        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-primary);
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            color: var(--text-primary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-server me-2"></i>Virtualizor Servers
            </a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Stats and Filters Section -->
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card fade-in-up">
                    <div class="card-body text-center">
                        <i class="fas fa-server fa-2x text-primary mb-2"></i>
                        <div class="stats-number" id="total-servers"><?php echo count($serversData); ?></div>
                        <div class="stats-label">Total Servers</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card fade-in-up">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <div class="stats-number" id="online-servers"><?php echo count(array_filter($serversData, fn($s) => $s['status'] == 1)); ?></div>
                        <div class="stats-label">Online Servers</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card fade-in-up">
                    <div class="card-body text-center">
                        <i class="fas fa-lock fa-2x text-warning mb-2"></i>
                        <div class="stats-number" id="locked-servers"><?php echo count(array_filter($serversData, fn($s) => $s['locked']['status'])); ?></div>
                        <div class="stats-label">Locked Servers</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card fade-in-up">
                    <div class="card-body text-center">
                        <i class="fas fa-cubes fa-2x text-info mb-2"></i>
                        <div class="stats-number" id="total-vps"><?php echo array_sum(array_column($serversData, 'vps.running_count')); ?></div>
                        <div class="stats-label">Total VPS</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="card filter-section fade-in-up mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h4>
                </div>
                <div class="row g-3">
                    <div class="col-lg-2 col-md-4">
                        <label for="status-filter" class="form-label">
                            <i class="fas fa-circle me-1"></i>Status
                        </label>
                        <select class="form-select" id="status-filter">
                            <option value="">All</option>
                            <option value="1">Online</option>
                            <option value="0">Offline</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="locked-filter" class="form-label">
                            <i class="fas fa-lock me-1"></i>Locked
                        </label>
                        <select class="form-select" id="locked-filter">
                            <option value="">All</option>
                            <option value="true">Locked</option>
                            <option value="false">Unlocked</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="cpu-filter" class="form-label">
                            <i class="fas fa-microchip me-1"></i>CPU Used (%)
                        </label>
                        <select class="form-select" id="cpu-filter">
                            <option value="">All</option>
                            <option value="low">< 50%</option>
                            <option value="medium">50-80%</option>
                            <option value="high">> 80%</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="ram-filter" class="form-label">
                            <i class="fas fa-memory me-1"></i>RAM Used (%)
                        </label>
                        <select class="form-select" id="ram-filter">
                            <option value="">All</option>
                            <option value="low">< 50%</option>
                            <option value="medium">50-80%</option>
                            <option value="high">> 80%</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="disk-filter" class="form-label">
                            <i class="fas fa-hdd me-1"></i>Disk Used (%)
                        </label>
                        <select class="form-select" id="disk-filter">
                            <option value="">All</option>
                            <option value="low">< 50%</option>
                            <option value="medium">50-80%</option>
                            <option value="high">> 80%</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="vps-filter" class="form-label">
                            <i class="fas fa-cubes me-1"></i>Running VPS
                        </label>
                        <select class="form-select" id="vps-filter">
                            <option value="">All</option>
                            <option value="low">< 10</option>
                            <option value="medium">10-50</option>
                            <option value="high">> 50</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="load-filter" class="form-label">
                            <i class="fas fa-tachometer-alt me-1"></i>Sys Load
                        </label>
                        <select class="form-select" id="load-filter">
                            <option value="">All</option>
                            <option value="low">< 1</option>
                            <option value="medium">1-5</option>
                            <option value="high">> 5</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card fade-in-up">
            <div class="card-body">
                <h4 class="mb-4"><i class="fas fa-table me-2"></i>Server Information</h4>
                <div class="table-responsive">
                    <table class="table" id="servers-table">
                        <thead>
                            <tr>
                                <th>Server Name</th>
                                <th>IP</th>
                                <th>Status</th>
                                <th>Locked</th>
                                <th>Total RAM (MB)</th>
                                <th>Available RAM (MB)</th>
                                <th>Free Space (GB)</th>
                                <th>OS</th>
                                <th>Sys Load</th>
                                <th>CPU Used (%)</th>
                                <th>RAM Used (%)</th>
                                <th>Disk Used (%)</th>
                                <th>Running VPS</th>
                                <th>Allocated RAM (MB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($serversData as $server): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($server['server_name']); ?></td>
                                    <td><?php echo htmlspecialchars($server['ip']); ?></td>
                                    <td><span class="status-<?php echo $server['status'] == 1 ? 'online' : 'offline'; ?>"><?php echo $server['status'] == 1 ? 'Online' : 'Offline'; ?></span></td>
                                    <td><span class="locked-<?php echo $server['locked']['status'] ? 'yes' : 'no'; ?>"><?php echo $server['locked']['status'] ? 'Locked' : 'Unlocked'; ?></span></td>
                                    <td><span class="metric-value"><?php echo number_format($server['total_ram_mb']); ?></span></td>
                                    <td><span class="metric-value"><?php echo number_format($server['available_ram_mb']); ?></span></td>
                                    <td><span class="metric-value"><?php echo number_format($server['free_space_gb']); ?></span></td>
                                    <td><?php echo htmlspecialchars($server['os']); ?></td>
                                    <td><span class="metric-value"><?php echo htmlspecialchars($server['sys_load']); ?></span></td>
                                    <td><span class="metric-value"><?php echo htmlspecialchars($server['resource_usage']['cpu']['used_percent']); ?></span></td>
                                    <td><span class="metric-value"><?php echo htmlspecialchars($server['resource_usage']['ram']['used_percent']); ?></span></td>
                                    <td><span class="metric-value"><?php echo htmlspecialchars($server['resource_usage']['disk']['used_percent']); ?></span></td>
                                    <td><span class="metric-value"><?php echo htmlspecialchars($server['vps']['running_count']); ?></span></td>
                                    <td><span class="metric-value"><?php echo number_format($server['vps']['allocated_ram_mb']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data from PHP
        const serversData = <?php echo json_encode($serversData); ?>;

        // Elements
        const statusFilter = document.getElementById('status-filter');
        const lockedFilter = document.getElementById('locked-filter');
        const cpuFilter = document.getElementById('cpu-filter');
        const ramFilter = document.getElementById('ram-filter');
        const diskFilter = document.getElementById('disk-filter');
        const vpsFilter = document.getElementById('vps-filter');
        const loadFilter = document.getElementById('load-filter');
        const serversTableBody = document.getElementById('servers-table').querySelector('tbody');
        const totalServers = document.getElementById('total-servers');
        const onlineServers = document.getElementById('online-servers');
        const lockedServers = document.getElementById('locked-servers');
        const totalVps = document.getElementById('total-vps');

        // Filter table
        function filterTable() {
            const selectedStatus = statusFilter.value;
            const selectedLocked = lockedFilter.value;
            const selectedCpu = cpuFilter.value;
            const selectedRam = ramFilter.value;
            const selectedDisk = diskFilter.value;
            const selectedVps = vpsFilter.value;
            const selectedLoad = loadFilter.value;

            let filteredServers = serversData.slice();

            filteredServers = filteredServers.filter(server => {
                // Status
                if (selectedStatus !== '' && server.status != selectedStatus) {
                    return false;
                }
                // Locked
                if (selectedLocked !== '' && (server.locked.status ? 'true' : 'false') !== selectedLocked) {
                    return false;
                }
                // CPU
                const cpuUsed = parseFloat(server.resource_usage.cpu.used_percent);
                if (selectedCpu === 'low' && cpuUsed >= 50) return false;
                if (selectedCpu === 'medium' && (cpuUsed < 50 || cpuUsed > 80)) return false;
                if (selectedCpu === 'high' && cpuUsed <= 80) return false;
                // RAM
                const ramUsed = parseFloat(server.resource_usage.ram.used_percent);
                if (selectedRam === 'low' && ramUsed >= 50) return false;
                if (selectedRam === 'medium' && (ramUsed < 50 || ramUsed > 80)) return false;
                if (selectedRam === 'high' && ramUsed <= 80) return false;
                // Disk
                const diskUsed = parseFloat(server.resource_usage.disk.used_percent);
                if (selectedDisk === 'low' && diskUsed >= 50) return false;
                if (selectedDisk === 'medium' && (diskUsed < 50 || diskUsed > 80)) return false;
                if (selectedDisk === 'high' && diskUsed <= 80) return false;
                // VPS
                const runningVps = parseInt(server.vps.running_count);
                if (selectedVps === 'low' && runningVps >= 10) return false;
                if (selectedVps === 'medium' && (runningVps < 10 || runningVps > 50)) return false;
                if (selectedVps === 'high' && runningVps <= 50) return false;
                // Load
                const sysLoad = parseFloat(server.sys_load);
                if (selectedLoad === 'low' && sysLoad >= 1) return false;
                if (selectedLoad === 'medium' && (sysLoad < 1 || sysLoad > 5)) return false;
                if (selectedLoad === 'high' && sysLoad <= 5) return false;
                return true;
            });

            // Update stats
            const total = filteredServers.length;
            const online = filteredServers.filter(s => s.status == 1).length;
            const locked = filteredServers.filter(s => s.locked.status).length;
            const vps = filteredServers.reduce((sum, s) => sum + parseInt(s.vps.running_count), 0);

            totalServers.textContent = total;
            onlineServers.textContent = online;
            lockedServers.textContent = locked;
            totalVps.textContent = vps;

            // Render table
            renderTable(filteredServers);
        }

        function renderTable(servers) {
            serversTableBody.innerHTML = '';
            servers.forEach(server => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${server.server_name}</td>
                    <td>${server.ip}</td>
                    <td><span class="status-${server.status == 1 ? 'online' : 'offline'}">${server.status == 1 ? 'Online' : 'Offline'}</span></td>
                    <td><span class="locked-${server.locked.status ? 'yes' : 'no'}">${server.locked.status ? 'Locked' : 'Unlocked'}</span></td>
                    <td><span class="metric-value">${server.total_ram_mb.toLocaleString()}</span></td>
                    <td><span class="metric-value">${server.available_ram_mb.toLocaleString()}</span></td>
                    <td><span class="metric-value">${server.free_space_gb.toLocaleString()}</span></td>
                    <td>${server.os}</td>
                    <td><span class="metric-value">${server.sys_load}</span></td>
                    <td><span class="metric-value">${server.resource_usage.cpu.used_percent}</span></td>
                    <td><span class="metric-value">${server.resource_usage.ram.used_percent}</span></td>
                    <td><span class="metric-value">${server.resource_usage.disk.used_percent}</span></td>
                    <td><span class="metric-value">${server.vps.running_count}</span></td>
                    <td><span class="metric-value">${server.vps.allocated_ram_mb.toLocaleString()}</span></td>
                `;
                serversTableBody.appendChild(row);
            });
        }

        // Event listeners
        statusFilter.addEventListener('change', filterTable);
        lockedFilter.addEventListener('change', filterTable);
        cpuFilter.addEventListener('change', filterTable);
        ramFilter.addEventListener('change', filterTable);
        diskFilter.addEventListener('change', filterTable);
        vpsFilter.addEventListener('change', filterTable);
        loadFilter.addEventListener('change', filterTable);

        // Initial render
        filterTable();
    </script>
</body>
</html>