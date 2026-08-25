<?php
    $cacheFileVPS = 'cache/vps_cache.json';
    $cacheFileIPPools = 'cache/ippools_cache.json';
    $cacheTime = 300; 

    if (!file_exists('cache')) {
        mkdir('cache', 0755, true);
    }

    if (file_exists($cacheFileVPS) && (time() - filemtime($cacheFileVPS) < $cacheTime)) {
        $vpsData = json_decode(file_get_contents($cacheFileVPS), true);
    } else {
        $vpsData = json_decode(file_get_contents('vps.json'), true);
        file_put_contents($cacheFileVPS, json_encode($vpsData));
    }

    if (file_exists($cacheFileIPPools) && (time() - filemtime($cacheFileIPPools) < $cacheTime)) {
        $ippoolsData = json_decode(file_get_contents($cacheFileIPPools), true);
    } else {
        $ippoolsData = json_decode(file_get_contents('ippools.json'), true);
        file_put_contents($cacheFileIPPools, json_encode($ippoolsData));
    }

    // Group VPS by OS
    $osGroups = ['Windows' => [], 'Mikrotik' => [], 'Linux' => []];
    foreach ($vpsData as $vps) {
        $os = strtolower($vps['os_name'] ?? '');
        if (strpos($os, 'windows') !== false) {
            $osGroups['Windows'][] = $vps;
        } elseif (strpos($os, 'mikrotik') !== false) {
            $osGroups['Mikrotik'][] = $vps;
        } else {
            $osGroups['Linux'][] = $vps;
        }
    }

    // Server groups from ippools keys
    $serverGroups = array_keys($ippoolsData);
    sort($serverGroups);

    // Count VPS per group
    $groupCounts = [];
    foreach ($vpsData as $vps) {
        $ip = explode(',', $vps['ips'])[0];
        foreach ($ippoolsData as $group => $pools) {
            foreach ($pools as $pool) {
                if (isIPInSubnet($ip, $pool['subnet'])) {
                    $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
                    break 2;
                }
            }
        }
    }

    function getOSGroup($osName) {
        $os = strtolower($osName ?? '');
        if (strpos($os, 'windows') !== false) return 'Windows';
        if (strpos($os, 'mikrotik') !== false) return 'Mikrotik';
        return 'Linux';
    }

    function isIPInSubnet($ip, $subnet) {
        list($subnetIP, $mask) = explode('/', $subnet);
        $mask = (int)$mask;
        $ipParts = array_map('intval', explode('.', $ip));
        $subnetParts = array_map('intval', explode('.', $subnetIP));
        $maskBytes = floor($mask / 8);
        for ($i = 0; $i < $maskBytes; $i++) {
            if ($ipParts[$i] !== $subnetParts[$i]) return false;
        }
        if ($mask % 8 !== 0) {
            $bitMask = 255 << (8 - ($mask % 8));
            if (($ipParts[$maskBytes] & $bitMask) !== ($subnetParts[$maskBytes] & $bitMask)) return false;
        }
        return true;
    }


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPS Info | FR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.1;
        }

        .hero-content {
            position: relative;
            z-index: 1;
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

        .stats-card {
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stats-icon {
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .stats-number {
            font-weight: 800;
            margin: 0;
            color: var(--accent);
        }

        .stats-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
            margin-top: 0.25rem;
            color: var(--text-secondary);
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
            cursor: pointer;
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

        .table td:nth-child(1), .table td:nth-child(2) {
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table td:nth-child(7), .table td:nth-child(8), .table td:nth-child(9),
        .table td:nth-child(10), .table td:nth-child(11), .table td:nth-child(12), .table td:nth-child(13) {
            width: 80px;
            min-width: 80px;
            font-size: 0.8rem;
            padding: 0.5rem 0.25rem;
        }

        .metric-value {
            font-size: 0.75rem;
        }

        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            background: #f5f5f5;
        }

        .sortable div::after {
            content: '↕';
            margin-left: 0.5rem;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }

        .sortable:hover::after {
            opacity: 1;
        }
        .status-unknown{
            background: #bbb;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 20px;
            height: 20px;
            text-align: center;
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
            min-width: 20px;
            height: 20px;
            text-align: center;
        }
        table{
            table-layout: auto;
        }
        th, td {
            white-space: nowrap;  
            text-align: center;
            /* جلوگیری از رفتن به خط بعد */
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
            min-width: 20px;
            height: 20px;
            text-align: center;
        }

        .status-suspend {
            background: var(--warning);
            color: #212529;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            display: inline-block;
            min-width: 20px;
            height: 20px;
            text-align: center;
        }

        .metric-value {
            font-weight: 700;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
        }

        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            color: white;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text-primary);
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .container-fluid {
            max-width: 1600px;
            padding: 0 2rem;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 0 1rem;
            }

            .hero-section {
                padding: 2rem 0;
            }

            .stats-card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }

            .stats-number {
                font-size: 2.5rem;
            }

            .filter-section {
                padding: 1.5rem;
            }

            .table-responsive {
                max-height: 50vh;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
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

        /* Loading animation */
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

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        .d-flex{
            display:flex;
        }
        
        .justify-content{
            justify-content:center;
        }
        
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php
        include('../index.php')
    ?>
  

    <!-- Stats and Filters Section -->
    <div class="container-fluid">
        <div class="row mb-4">
            <!-- Filters Section -->

            <div class="card filter-section fade-in-up">
            <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h4>
                </div>
                            <div class="col-12">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <div class="card stats-card fade-in-up">
                            <div class="card-body py-1 px-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="stats-icon text-primary me-2" style="font-size: 1.2rem;">
                                        <i class="fab fa-windows"></i>
                                    </div>
                                    <div>
                                        <div class="stats-number" id="windows-count" style="font-size: 1.5rem;"><?php echo count($osGroups['Windows']); ?></div>
                                        <div class="stats-label" style="font-size: 0.7rem;">Windows VPS</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card stats-card fade-in-up">
                            <div class="card-body py-1 px-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="stats-icon text-success me-2" style="font-size: 1.2rem;">
                                        <i class="fab fa-linux"></i>
                                    </div>
                                    <div>
                                        <div class="stats-number" id="linux-count" style="font-size: 1.5rem;"><?php echo count($osGroups['Linux']); ?></div>
                                        <div class="stats-label" style="font-size: 0.7rem;">Linux VPS</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="card stats-card fade-in-up">
                            <div class="card-body py-1 px-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="stats-icon me-2">
                                        <img src="winbox.svg" alt="Winbox" style="width: 28px; height: 28px; object-fit: contain;">
                                    </div>
                                    <div>
                                        <div class="stats-number" id="mikrotik-count" style="font-size: 1.5rem;"><?php echo count($osGroups['Mikrotik']); ?></div>
                                        <div class="stats-label" style="font-size: 0.7rem;">Mikrotik VPS</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-lg-4 col-md-4">
                        <label for="server-group" class="form-label" id="server-group-label">
                            <i class="fas fa-globe me-1"></i>Server Group
                        </label>
                        <select class="form-select" id="server-group">
                            <option value="">All Groups</option>
                            <?php foreach($serverGroups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?> (<?php echo $groupCounts[$group] ?? 0; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <label for="subnet" class="form-label" id="subnet-label">
                            <i class="fas fa-network-wired me-1"></i>Subnet
                        </label>
                        <select class="form-select" id="subnet" disabled>
                            <option value="">Select Server Group First</option>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <label for="server-name" class="form-label" id="server-name-label">
                            <i class="fas fa-server me-1"></i>Server Name
                        </label>
                        <select class="form-select" id="server-name" disabled>
                            <option value="">Select Subnet First</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label for="search" class="form-label">
                            <i class="fas fa-search me-1"></i>Search
                        </label>
                        <input type="text" class="form-control" id="search" placeholder="Search anything...">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="os-filter" class="form-label" id="os-label">
                            <i class="fas fa-desktop me-1"></i>OS Type
                        </label>
                        <select class="form-select" id="os-filter">
                            <option value="">All OS</option>
                            <option value="Windows">Windows</option>
                            <option value="Linux">Linux</option>
                            <option value="Mikrotik">Mikrotik</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="ram-filter" class="form-label" id="ram-label">
                            <i class="fas fa-memory me-1"></i>RAM
                        </label>
                        <select class="form-select" id="ram-filter">
                            <option value="">All RAM</option>
                            <option value="2048">+2GB</option>
                            <option value="4096">+4GB</option>
                            <option value="8192">+8GB</option>
                            <option value="16384">+16GB</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="status-filter" class="form-label" id="status-label">
                            <i class="fas fa-circle me-1"></i>Status
                        </label>
                        <select class="form-select" id="status-filter">
                            <option value="">All Status</option>
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                            <option value="Suspend">Suspend</option>
                            <option value="Unknown">Unknown</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card fade-in-up">
            <div class="card-body">
                <h4 class="mb-4"><i class="fas fa-table me-2"></i>VPS Instances</h4>
                <div class="table-responsive">
                    <table class="table" id="vps-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="status" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Status
                                    </div>
                                </th>
                                <th class="sortable" data-sort="hostname" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Hostname
                                    </div>
                                </th>
                                <th class="sortable" data-sort="vpsid" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>VPS ID
                                    </div>
                                </th>
                                <th class="sortable" data-sort="ips" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>IP
                                    </div>
                                </th>
                                <th>
                                    <i"></i>OS
                                </th>
                                <th class="sortable" data-sort="server_name" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Server Name
                                    </div>
                                </th>
                                <th class="sortable" data-sort="IP Location" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Location
                                    </div>
                                </th>
                                <th class="sortable" data-sort="cpu_cores" data-sort-dir="asc">                                    
                                    <div class="d-flex justify-content">
                                        <i></i>CPU Cores
                                    </div>
                                </th>
                                <th class="sortable" data-sort="ram" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>RAM
                                    </div>
                                </th>
                                <th class="sortable" data-sort="ram" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Disk (GB)
                                    </div>
                                </th>
                                <th class="sortable" data-sort="used_cpu" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Used CPU
                                    </div>
                                </th>
                                <th class="sortable" data-sort="used_ram" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Used RAM (%)
                                    </div>
                                </th>
                                <th class="sortable" data-sort="io_read" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>IO Read
                                    </div>
                                </th>
                                <th class="sortable" data-sort="io_write" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>IO Write
                                    </div>
                                </th>
                                <th class="sortable" data-sort="used_inode" data-sort-dir="asc">
                                    <div class="d-flex justify-content">
                                        <i></i>Used Inodes
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="vps-tbody">
                            <?php foreach($vpsData as $vps): ?>
                                <tr data-vps-id="<?php echo $vps['vpsid']; ?>">
                                    <td><span class="status-<?php echo strtolower($vps['status']); ?>"><?php echo $vps['status']; ?></span></td>
                                    <td><?php echo $vps['hostname']; ?></td>
                                    <td><span class="metric-value"><?php echo $vps['vpsid']; ?></span></td>
                                    <td><?php echo explode(',', $vps['ips'])[0]; ?></td>
                                    <td class="align-middle">
                                        <?php 
                                            $os = getOSGroup($vps['os_name']); 
                                            if ($os === 'Mikrotik'): 
                                        ?>
                                            <img src="winbox.svg" alt="MikroTik" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">
                                        <?php else: 
                                            $iconClass = 'fab fa-' . strtolower($os); 
                                        ?>
                                            <i class="<?php echo $iconClass; ?>"></i>
                                        <?php endif; ?>
                                        <span class="ms-1"><?php echo $os; ?></span>
                                    </td><td><?php echo $vps['IP Location'] ?? 'N/A'; ?></td>
                                    <td><span class="metric-value"><?php echo $vps['cpu_cores']; ?></span></td>
                                    <td><span class="metric-value"><?php echo number_format($vps['ram'] / 1024, 1); ?></span></td>
                                    <td><span class="metric-value"><?php echo $vps['space']; ?></span></td>
                                    <td><span class="metric-value"><?php echo $vps['used_cpu']; ?>%</span></td>
                                    <td><span class="metric-value"><?php echo $vps['used_ram']; ?>%</span></td>
                                    <td><span class="metric-value"><?php echo $vps['io_read'] ?? '0'; ?></span></td>
                                    <td><span class="metric-value"><?php echo $vps['io_write'] ?? '0'; ?></span></td>
                                    <td><span class="metric-value"><?php echo $vps['used_inode'] ?? '0'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vpsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        <i class="fas fa-server me-2"></i>VPS Details
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="vps-details">
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data from PHP
        const vpsData = <?php echo json_encode($vpsData); ?>;
        const ippoolsData = <?php echo json_encode($ippoolsData); ?>;
        const serverGroups = <?php echo json_encode($serverGroups); ?>;

        // Elements
        const searchInput = document.getElementById('search');
        const serverGroupSelect = document.getElementById('server-group');
        const subnetSelect = document.getElementById('subnet');
        const serverNameSelect = document.getElementById('server-name');
        const osFilterSelect = document.getElementById('os-filter');
        const ramFilterSelect = document.getElementById('ram-filter');
        const statusFilterSelect = document.getElementById('status-filter');
        const vpsTableBody = document.getElementById('vps-tbody');
        const windowsCount = document.getElementById('windows-count');
        const linuxCount = document.getElementById('linux-count');
        const mikrotikCount = document.getElementById('mikrotik-count');

        // Populate subnets and server names based on server group
        serverGroupSelect.addEventListener('change', function() {
            const selectedGroup = this.value;
            subnetSelect.innerHTML = '<option value="">All</option>';
            serverNameSelect.innerHTML = '<option value="">All</option>';
            if (selectedGroup && ippoolsData[selectedGroup]) {
                ippoolsData[selectedGroup].sort((a, b) => a.subnet.localeCompare(b.subnet)).forEach(pool => {
                    let count = 0;
                    Object.values(vpsData).forEach(vps => {
                        const vpsIP = vps.ips.split(',')[0];
                        if (isIPInSubnet(vpsIP, pool.subnet)) {
                            count++;
                        }
                    });
                    const option = document.createElement('option');
                    option.value = pool.subnet;
                    option.textContent = pool.subnet + ' (' + pool.name + ') (' + count + ')';
                    subnetSelect.appendChild(option);
                });
                subnetSelect.disabled = false;
                document.getElementById('subnet-label').textContent = `Subnet (${ippoolsData[selectedGroup].length})`;
                // Populate server names for the group
                const serverNameCounts = {};
                Object.values(vpsData).forEach(vps => {
                    const vpsIP = vps.ips.split(',')[0];
                    const groupSubnets = ippoolsData[selectedGroup] || [];
                    if (groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet))) {
                        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
                    }
                });
                Object.keys(serverNameCounts).sort().forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = `${name} (${serverNameCounts[name]})`;
                    serverNameSelect.appendChild(option);
                });
                serverNameSelect.disabled = false;
                document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
            } else {
                subnetSelect.disabled = false;
                serverNameSelect.disabled = false;
                document.getElementById('subnet-label').textContent = 'Subnet';
                document.getElementById('server-name-label').textContent = 'Server Name';
                // Populate all subnets
                const allSubnets = [];
                Object.values(ippoolsData).forEach(groupPools => {
                    groupPools.forEach(pool => {
                        allSubnets.push(pool);
                    });
                });
                allSubnets.sort((a, b) => a.subnet.localeCompare(b.subnet));
                subnetSelect.innerHTML = '<option value="">All</option>';
                allSubnets.forEach(pool => {
                    const option = document.createElement('option');
                    option.value = pool.subnet;
                    option.textContent = pool.subnet + ' (' + pool.name + ')';
                    subnetSelect.appendChild(option);
                });
                document.getElementById('subnet-label').textContent = `Subnet (${allSubnets.length})`;
                // Populate all server names
                const allServerNames = [...new Set(Object.values(vpsData).map(vps => vps.server_name))].sort();
                serverNameSelect.innerHTML = '<option value="">All</option>';
                allServerNames.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    serverNameSelect.appendChild(option);
                });
                document.getElementById('server-name-label').textContent = `Server Name (${allServerNames.length})`;
            }
            filterTable();
        });

        // Populate server names based on subnet
        subnetSelect.addEventListener('change', function() {
            const selectedSubnet = this.value;
            serverNameSelect.innerHTML = '<option value="">All</option>';
            if (selectedSubnet) {
                const serverNameCounts = {};
                Object.values(vpsData).forEach(vps => {
                    const vpsIP = vps.ips.split(',')[0];
                    if (isIPInSubnet(vpsIP, selectedSubnet)) {
                        serverNameCounts[vps.server_name] = (serverNameCounts[vps.server_name] || 0) + 1;
                    }
                });
                Object.keys(serverNameCounts).sort().forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = `${name} (${serverNameCounts[name]})`;
                    serverNameSelect.appendChild(option);
                });
                serverNameSelect.disabled = false;
                document.getElementById('server-name-label').textContent = `Server Name (${Object.keys(serverNameCounts).length})`;
            } else {
                serverNameSelect.disabled = true;
                document.getElementById('server-name-label').textContent = 'Server Name';
            }
            filterTable();
        });

        // Filter table
        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedGroup = serverGroupSelect.value;
            const selectedSubnet = subnetSelect.value;
            const selectedServerName = serverNameSelect.value;
            const selectedOS = osFilterSelect.value;
            const selectedRAM = ramFilterSelect.value;
            const selectedStatus = statusFilterSelect.value;

            let filteredVPS = Object.values(vpsData);

            filteredVPS = filteredVPS.filter(vps => {
                // Search
                if (searchTerm && !Object.values(vps).some(val => String(val).toLowerCase().includes(searchTerm))) {
                    return false;
                }
                // Server Group
                if (selectedGroup) {
                    const vpsIP = vps.ips.split(',')[0];
                    const groupSubnets = ippoolsData[selectedGroup] || [];
                    const inGroup = groupSubnets.some(pool => isIPInSubnet(vpsIP, pool.subnet));
                    if (!inGroup) return false;
                }
                // Subnet - check if vps IP is in subnet
                if (selectedSubnet) {
                    const vpsIP = vps.ips.split(',')[0];
                    if (!isIPInSubnet(vpsIP, selectedSubnet)) {
                        return false;
                    }
                }
                // Server Name
                if (selectedServerName && vps.server_name !== selectedServerName) {
                    return false;
                }
                return true;
            });

            // Count OS, RAM, Status from filtered by location
            let windows = 0, linux = 0, mikrotik = 0;
            let ram2gb = 0, ram4gb = 0, ram8gb = 0, ram16gb = 0;
            let statusOnline = 0, statusOffline = 0, statusSuspend = 0 ,statusUnknow = 0;

            filteredVPS.forEach(vps => {
                const os = getOSGroup(vps.os_name);
                if (os === 'Windows') windows++;
                else if (os === 'Linux') linux++;
                else if (os === 'Mikrotik') mikrotik++;

                const ram = parseInt(vps.ram);
                if (ram >= 2048 && ram < 4096) ram2gb++;
                else if (ram >= 4096 && ram < 8192) ram4gb++;
                else if (ram >= 8192 && ram < 16384) ram8gb++;
                else if (ram >= 16384) ram16gb++;

                if (vps.status === 'Online') statusOnline++;
                else if (vps.status === 'Offline') statusOffline++;
                else if (vps.status === 'Suspend') statusSuspend++;
                else if (vps.status === 'Unknow') statusUnknow++;
            });

            // Now apply OS, RAM, Status filters
            filteredVPS = filteredVPS.filter(vps => {
                // OS
                const os = getOSGroup(vps.os_name);
                if (selectedOS && os !== selectedOS) {
                    return false;
                }
                // RAM
                if (selectedRAM && parseInt(vps.ram) < parseInt(selectedRAM)) {
                    return false;
                }
                // Status
                if (selectedStatus && vps.status !== selectedStatus) {
                    
                    return false;
                }
                return true;
            });

            // Count final OS for top cards
            let finalWindows = 0, finalLinux = 0, finalMikrotik = 0;
            filteredVPS.forEach(vps => {
                const os = getOSGroup(vps.os_name);
                if (os === 'Windows') finalWindows++;
                else if (os === 'Linux') finalLinux++;
                else if (os === 'Mikrotik') finalMikrotik++;
            });

            // Update top counts
            windowsCount.textContent = finalWindows;
            linuxCount.textContent = finalLinux;
            mikrotikCount.textContent = finalMikrotik;


            // Render table
            renderTable(filteredVPS);
        }

        function getOSGroup(osName) {
            const os = (osName || '').toLowerCase();
            if (os.includes('windows')) return 'Windows';
            if (os.includes('mikrotik')) return 'Mikrotik';
            return 'Linux';
        }

        function isIPInSubnet(ip, subnet) {
            // Simple check, assuming CIDR
            const [subnetIP, mask] = subnet.split('/');
            const maskInt = parseInt(mask);
            const ipParts = ip.split('.').map(Number);
            const subnetParts = subnetIP.split('.').map(Number);
            const maskBytes = Math.floor(maskInt / 8);
            for (let i = 0; i < maskBytes; i++) {
                if (ipParts[i] !== subnetParts[i]) return false;
            }
            if (maskInt % 8 !== 0) {
                const bitMask = 255 << (8 - (maskInt % 8));
                if ((ipParts[maskBytes] & bitMask) !== (subnetParts[maskBytes] & bitMask)) return false;
            }
            return true;
        }

        
        function renderTable(vpsList) {
                    vpsTableBody.innerHTML = '';
                    vpsList.forEach(vps => {
                        const row = document.createElement('tr');
                        const os = getOSGroup(vps.os_name);

                        let osIconHtml = '';
                        if (os === 'Mikrotik') {

                            osIconHtml = `<img src="winbox.svg" alt="Winbox" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">`;
                        } else {
                            const iconClass = 'fab fa-' + os.toLowerCase();
                            osIconHtml = `<i class="${iconClass}"></i>`;
                        }
                    
                        row.innerHTML = `
                            <td><span class="status-${vps.status.toLowerCase()}"></span></td>
                            <td>${vps.hostname}</td>
                            <td><span class="metric-value">${vps.vpsid}</span></td>
                            <td>${vps.ips.split(',')[0]}</td>
                            <td>${osIconHtml}</td>
                            <td><small>${vps.server_name}</small></td>
                            <td>${vps['IP Location'] || 'N/A'}</td>
                            <td><span class="metric-value">${vps.cpu_cores}</span></td>
                            <td><span class="metric-value">${(vps.ram / 1024).toFixed(1)}</span></td>
                            <td><span class="metric-value">${vps.space}</span></td>
                            <td><span class="metric-value">${vps.used_cpu}%</span></td>
                            <td><span class="metric-value">${vps.used_ram}%</span></td>
                            <td><span class="metric-value">${vps.io_read || '0'}</span></td>
                            <td><span class="metric-value">${vps.io_write || '0'}</span></td>
                            <td><span class="metric-value">${vps.used_inode || '0'}</span></td>
                        `;
                        row.addEventListener('click', () => showVPSDetails(vps));
                        vpsTableBody.appendChild(row);
                    });
                }

        function showVPSDetails(vps) {
            
            const shownFields = ['status', 'hostname', 'vpsid', 'ips', 'os_name', 'IP Location', 'cpu_cores', 'used_cpu', 'ram', 'used_ram', 'io_read', 'io_write', 'used_inode', 'space'];
            const details = Object.entries(vps).filter(([key]) => !shownFields.includes(key)).map(([key, value]) => `
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong></div>
                    <div class="col-sm-8">${value}</div>
                </div>
            `).join('');
            document.getElementById('vps-details').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8"><span class="status-${vps.status.toLowerCase()}">${vps.status}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Hostname:</strong></div>
                            <div class="col-sm-8">${vps.hostname}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>VPS ID:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.vpsid}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>IP Address:</strong></div>
                            <div class="col-sm-8">${vps.ips}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>OS Name:</strong></div>
                            <div class="col-sm-8">${vps.os_name}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>IP Location:</strong></div>
                            <div class="col-sm-8">${vps['IP Location'] || 'N/A'}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Performance Metrics</h5>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>CPU Cores:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.cpu_cores}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Used CPU:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.used_cpu}%</span></div>
                        </div>
                        <div class="row mb-2">
                        <div class="col-sm-4"><strong>RAM:</strong></div>
                        <div class="col-sm-8"><span class="metric-value">${(vps.ram / 1024).toFixed(1)} GB</span></div>
                        </div>
                        <div class="row mb-2">
                        <div class="col-sm-4"><strong>Disk:</strong></div>
                        <div class="col-sm-8"><span class="metric-value">${vps.space} GB</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Used RAM:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.used_ram}%</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>IO Read:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.io_read || '0'}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>IO Write:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.io_write || '0'}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Used Inodes:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${vps.used_inode || '0'}</span></div>
                        </div>
                    </div>
                </div>
                <hr>
                <h5 class="mb-3"><i class="fas fa-cogs me-2"></i>Additional Details</h5>
                ${details}
            `;
            console.log(vps)
            new bootstrap.Modal(document.getElementById('vpsModal')).show();
        }

        // Sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const sortDir = this.dataset.sortDir;
                const currentVPS = Array.from(vpsTableBody.querySelectorAll('tr')).map(row => {
                    const cells = row.querySelectorAll('td');
                    return {
                        status: cells[0].textContent,
                        hostname: cells[1].textContent,
                        vpsid: parseInt(cells[2].textContent) || 0,
                        ips: cells[3].textContent,
                        os: cells[4].textContent,
                        'IP Location': cells[5].textContent,
                        cpu_cores: parseInt(cells[6].textContent) || 0,
                        ram: parseFloat(cells[7].textContent) || 0,
                        space: parseInt(cells[8].textContent) || 0,
                        used_cpu: parseFloat(cells[9].textContent) || 0,
                        used_ram: parseInt(cells[10].textContent) || 0,
                        io_read: parseInt(cells[11].textContent) || 0,
                        io_write: parseInt(cells[12].textContent) || 0,
                        used_inode: cells[13].textContent,
                        element: row
                    };
                });
                currentVPS.sort((a, b) => {
                    let cmp;
                    if (typeof a[sortBy] === 'string') {
                        cmp = a[sortBy].localeCompare(b[sortBy]);
                    } else {
                        cmp = a[sortBy] - b[sortBy];
                    }
                    return sortDir === 'asc' ? cmp : -cmp;
                });
                vpsTableBody.innerHTML = '';
                currentVPS.forEach(item => vpsTableBody.appendChild(item.element));
                // Toggle sort direction
                this.dataset.sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            });
        });

        // Debounce search input
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterTable, 300);
        });

        // Event listeners for filters
        serverNameSelect.addEventListener('change', filterTable);
        osFilterSelect.addEventListener('change', filterTable);
        ramFilterSelect.addEventListener('change', filterTable);
        statusFilterSelect.addEventListener('change', filterTable);


        // Check for URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        const subnetParam = urlParams.get('subnet');
        const filterSubnetParam = urlParams.get('filter_subnet');

        if (searchParam) {
            searchInput.value = searchParam;
        }

        const targetSubnet = subnetParam || filterSubnetParam;
        if (targetSubnet) {
            // Find the group for this subnet and set it
            for (const [group, pools] of Object.entries(ippoolsData)) {
                if (pools.some(pool => pool.subnet === targetSubnet)) {
                    serverGroupSelect.value = group;
                    // Trigger the change event to populate subnets
                    serverGroupSelect.dispatchEvent(new Event('change'));
                    // Wait a bit for the options to be populated, then select the subnet
                    setTimeout(() => {
                        subnetSelect.value = targetSubnet;
                        filterTable();
                    }, 100);
                    break;
                }
            }
        }

        // Initial render
        filterTable();
    </script>
</body>
</html>


</html>

