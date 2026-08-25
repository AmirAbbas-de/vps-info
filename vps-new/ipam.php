<?php
    // Cache JSON data for 5 minutes
    $cacheFileVPS = 'cache/vps_cache.json';
    $cacheFileIPPools = 'cache/ippools_cache.json';
    $cacheTime = 300; // 5 minutes

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

    function isIPInSubnet($ip, $subnet) {
        list($subnetIP, $mask) = explode('/', $subnet);
        $maskInt = (int)$mask;
        $ipParts = explode('.', trim($ip));
        $subnetParts = explode('.', trim($subnetIP));
        $maskBytes = floor($maskInt / 8);
        for ($i = 0; $i < $maskBytes; $i++) {
            if ($ipParts[$i] != $subnetParts[$i]) return false;
        }
        if ($maskInt % 8 != 0) {
            $bitMask = 255 << (8 - ($maskInt % 8));
            if (($ipParts[$maskBytes] & $bitMask) != ($subnetParts[$maskBytes] & $bitMask)) return false;
        }
        return true;
    }

    // Compute IPAM data (exclude IPv6 subnets)
    $ipamData = [];
    $totalPools = 0;
    $totalIPs = 0;
    $totalUsed = 0;
    $totalFree = 0;
    foreach ($ippoolsData as $group => $pools) {
        foreach ($pools as $pool) {
            // Skip IPv6 subnets (contain colons)
            if (strpos($pool['subnet'], ':') !== false) {
                continue;
            }
            $totalPools++;
            $totalIPs += (int)$pool['total_ip'];
            $used = 0;
            $allocatedIPs = [];
            foreach ($vpsData as $vps) {
                $ips = explode(',', $vps['ips']);
                foreach ($ips as $ip) {
                    if (isIPInSubnet(trim($ip), $pool['subnet'])) {
                        $used++;
                        $allocatedIPs[] = trim($ip) . ' (' . $vps['hostname'] . ')';
                    }
                }
            }
            $free = (int)$pool['total_ip'] - $used;
            $totalUsed += $used;
            $totalFree += $free;
            $ipamData[] = [
                'group' => $group,
                'name' => $pool['name'],
                'subnet' => $pool['subnet'],
                'total' => (int)$pool['total_ip'],
                'free' => $free,
                'used' => $used,
                'utilization' => $used / (int)$pool['total_ip'] * 100,
                'allocated_ips' => $allocatedIPs,
                'locked' => (int)($pool['locked'] ?? 0),
                'lock_time' => $pool['lock_time'] ?? null,
                'lock_description' => $pool['lock_description'] ?? '',
            ];
        }
    }

    $groups = array_unique(array_column($ipamData, 'group'));
    sort($groups);
    $subnets = array_unique(array_column($ipamData, 'subnet'));
    sort($subnets);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Info | IR</title>
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
            text-align: center;

            letter-spacing: 0.5px;
            font-size: 0.8rem;
            padding: 1.25rem 1rem;
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
            text-align: center;

        }

        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .sortable::after {
            content: '↕';
            margin-left: 0.5rem;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }

        .sortable:hover::after {
            opacity: 1;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .utilization-high {
            background: linear-gradient(45deg, var(--danger), #ff6b6b);
        }

        .utilization-medium {
            background: linear-gradient(45deg, var(--warning), #ffd93d);
        }

        .utilization-low {
            background: linear-gradient(45deg, var(--success), #6bcf7f);
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
    </style>
</head>
<body>
    <?php
        include '../index.php'
    ?>
   

    <!-- Stats and Filters Section -->
    <div class="container-fluid">
        <div class="row mb-4">
            <!-- Filters Section -->
            <div class="col-12">
                <div class="card filter-section fade-in-up">
            <div class="card-body">
            <div class="card filter-section fade-in-up">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2">
                            <div class="card stats-card fade-in-up">
                                <div class="card-body py-1 px-2">
                                    <div class="stats-number" id="total-pools" style="font-size: 1.2rem;"><?php echo $totalPools; ?></div>
                                    <div class="stats-label" style="font-size: 0.7rem;">Total Pools</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card stats-card fade-in-up">
                                <div class="card-body py-1 px-2">
                                    <div class="stats-number" id="total-ips" style="font-size: 1.2rem;"><?php echo $totalIPs; ?></div>
                                    <div class="stats-label" style="font-size: 0.7rem;">Total IPs</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card stats-card fade-in-up">
                                <div class="card-body py-1 px-2">
                                    <div class="stats-number" id="total-used" style="font-size: 1.2rem;"><?php echo $totalUsed; ?></div>
                                    <div class="stats-label" style="font-size: 0.7rem;">Used IPs</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card stats-card fade-in-up">
                                <div class="card-body py-1 px-2">
                                    <div class="stats-number" id="total-free" style="font-size: 1.2rem;"><?php echo $totalFree; ?></div>
                                    <div class="stats-label" style="font-size: 0.7rem;">Free IPs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h4>     
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label for="search" class="form-label">
                                <i class="fas fa-search me-1"></i>Search
                            </label>
                            <input type="text" class="form-control" id="search" placeholder="Search anything...">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="group-filter" class="form-label">
                                <i class="fas fa-globe me-1"></i>IP Group
                            </label>
                            <select class="form-select" id="group-filter">
                                <option value="">All Groups</option>
                                <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="subnet-filter" class="form-label">
                                <i class="fas fa-network-wired me-1"></i>Subnet
                            </label>
                            <select class="form-select" id="subnet-filter">
                                <option value="">All Subnets</option>
                                <?php foreach($subnets as $subnet): ?>
                                    <option value="<?php echo $subnet; ?>"><?php echo $subnet; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="utilization-filter" class="form-label">
                                <i class="fas fa-chart-bar me-1"></i>Utilization
                            </label>
                            <select class="form-select" id="utilization-filter">
                                <option value="">All</option>
                                <option value="high">Most Used (>80%)</option>
                                <option value="low">Less Used (<20%)</option>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="locked-filter" class="form-label">
                                <i class="fas fa-lock me-1"></i>Lock Status
                            </label>
                            <select class="form-select" id="locked-filter">
                                <option value="">All</option>
                                <option value="locked">Locked Only</option>
                                <option value="free">Free Only</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card fade-in-up">
            <div class="card-body">
                <h4 class="mb-4"><i class="fas fa-table me-2"></i>IP Pools</h4>
                <div class="table-responsive">
                    <table class="table" id="ipam-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="group" data-sort-dir="asc">
                                    <i class="fas fa-globe me-1"></i>Group
                                </th>
                                <th class="sortable" data-sort="name" data-sort-dir="asc">
                                    <i class="fas fa-tag me-1"></i>Name
                                </th>
                                <th class="sortable" data-sort="subnet" data-sort-dir="asc">
                                    <i class="fas fa-network-wired me-1"></i>Subnet
                                </th>
                                <th class="sortable" data-sort="total" data-sort-dir="asc">
                                    <i class="fas fa-list me-1"></i>Total IPs
                                </th>
                                <th class="sortable" data-sort="free" data-sort-dir="asc">
                                    <i class="fas fa-check-circle me-1"></i>Free IPs
                                </th>
                                <th class="sortable" data-sort="used" data-sort-dir="asc">
                                    <i class="fas fa-times-circle me-1"></i>Used IPs
                                </th>
                                <th class="sortable" data-sort="utilization" data-sort-dir="asc">
                                    <i class="fas fa-chart-bar me-1"></i>Utilization
                                </th>
                                <th>
                                    <i class="fas fa-lock me-1"></i>Locked
                                </th>
                            </tr>
                        </thead>
                        <tbody id="ipam-tbody">
                            <?php foreach($ipamData as $pool): ?>
                                <?php
                                    $utilClass = $pool['utilization'] > 80 ? 'utilization-high' : ($pool['utilization'] > 50 ? 'utilization-medium' : 'utilization-low');
                                ?>
                                <tr data-pool-group="<?php echo $pool['group']; ?>" data-pool-utilization="<?php echo $pool['utilization']; ?>">
                                    <td><?php echo $pool['group']; ?></td>
                                    <td><?php echo $pool['name']; ?></td>
                                    <td><span class="metric-value"><?php echo $pool['subnet']; ?></span></td>
                                    <td><span class="metric-value"><?php echo $pool['total']; ?></span></td>
                                    <td><span class="metric-value"><?php echo $pool['free']; ?></span></td>
                                    <td><span class="metric-value"><?php echo $pool['used']; ?></span></td>
                                    <td>
                                        <div class="progress mb-1">
                                            <div class="progress-bar <?php echo $utilClass; ?>" role="progressbar" style="width: <?php echo $pool['utilization']; ?>%" aria-valuenow="<?php echo $pool['utilization']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($pool['utilization'], 1); ?>%</small>
                                    </td>
                                    <td>
                                        <?php if ($pool['locked']): ?>
                                            <span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Locked</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-lock-open me-1"></i>Free</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Pool Details -->
    <div class="modal fade" id="poolModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">
                        <i class="fas fa-network-wired me-2"></i>IP Pool Details
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="pool-details">
                    <!-- Details will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data
        const ipamData = <?php echo json_encode($ipamData); ?>;

        // Elements
        const searchInput = document.getElementById('search');
        const groupFilter = document.getElementById('group-filter');
        const subnetFilter = document.getElementById('subnet-filter');
        const utilizationFilter = document.getElementById('utilization-filter');
        const lockedFilter = document.getElementById('locked-filter');
        const ipamTableBody = document.getElementById('ipam-tbody');
        const totalPoolsEl = document.getElementById('total-pools');
        const totalIPsEl = document.getElementById('total-ips');
        const totalUsedEl = document.getElementById('total-used');
        const totalFreeEl = document.getElementById('total-free');

        // Filter table
        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedGroup = groupFilter.value;
            const selectedSubnet = subnetFilter.value;
            const selectedUtilization = utilizationFilter.value;
            const selectedLocked = lockedFilter.value;

            let filteredPools = ipamData.filter(pool => {
                // Search
                if (searchTerm && !Object.values(pool).some(val => String(val).toLowerCase().includes(searchTerm))) {
                    return false;
                }
                // Group
                if (selectedGroup && pool.group !== selectedGroup) {
                    return false;
                }
                // Subnet
                if (selectedSubnet && pool.subnet !== selectedSubnet) {
                    return false;
                }
                // Utilization
                if (selectedUtilization === 'high' && pool.utilization <= 80) {
                    return false;
                }
                if (selectedUtilization === 'low' && pool.utilization >= 20) {
                    return false;
                }
                // Lock status
                if (selectedLocked === 'locked' && !pool.locked) {
                    return false;
                }
                if (selectedLocked === 'free' && pool.locked) {
                    return false;
                }
                return true;
            });

            // Calculate stats for filtered
            const totalPools = filteredPools.length;
            const totalIPs = filteredPools.reduce((sum, pool) => sum + pool.total, 0);
            const totalUsed = filteredPools.reduce((sum, pool) => sum + pool.used, 0);
            const totalFree = filteredPools.reduce((sum, pool) => sum + pool.free, 0);

            // Update stats
            totalPoolsEl.textContent = totalPools;
            totalIPsEl.textContent = totalIPs;
            totalUsedEl.textContent = totalUsed;
            totalFreeEl.textContent = totalFree;


            // Render table
            renderTable(filteredPools);
        }

        function renderTable(pools) {
            ipamTableBody.innerHTML = '';
            pools.forEach(pool => {
                const utilClass = pool.utilization > 80 ? 'utilization-high' : (pool.utilization > 50 ? 'utilization-medium' : 'utilization-low');
                const lockedBadge = pool.locked
                    ? `<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Locked</span>`
                    : `<span class="badge bg-success"><i class="fas fa-lock-open me-1"></i>Free</span>`;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${pool.group}</td>
                    <td>${pool.name}</td>
                    <td><span class="metric-value">${pool.subnet}</span></td>
                    <td><span class="metric-value">${pool.total}</span></td>
                    <td><span class="metric-value">${pool.free}</span></td>
                    <td><span class="metric-value">${pool.used}</span></td>
                    <td>
                        <div class="progress mb-1">
                            <div class="progress-bar ${utilClass}" role="progressbar" style="width: ${pool.utilization}%" aria-valuenow="${pool.utilization}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted">${pool.utilization.toFixed(1)}%</small>
                    </td>
                    <td>${lockedBadge}</td>
                `;
                row.addEventListener('click', () => showPoolDetails(pool));
                ipamTableBody.appendChild(row);
            });
        }

        function showPoolDetails(pool) {
            const utilClass = pool.utilization > 80 ? 'utilization-high' : (pool.utilization > 50 ? 'utilization-medium' : 'utilization-low');
            const lockedBadge = pool.locked
                ? `<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>Locked</span>`
                : `<span class="badge bg-success"><i class="fas fa-lock-open me-1"></i>Free</span>`;
            const lockTimeStr = pool.lock_time ? new Date(pool.lock_time).toLocaleString() : '—';
            const lockDescStr = pool.lock_description || '—';
            let details = `
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Pool Information</h5>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Group:</strong></div>
                            <div class="col-sm-8">${pool.group}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Name:</strong></div>
                            <div class="col-sm-8">${pool.name}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Subnet:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${pool.subnet}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Total IPs:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${pool.total}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Free IPs:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${pool.free}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Used IPs:</strong></div>
                            <div class="col-sm-8"><span class="metric-value">${pool.used}</span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Utilization:</strong></div>
                            <div class="col-sm-8">
                                <div class="progress mb-1">
                                    <div class="progress-bar ${utilClass}" role="progressbar" style="width: ${pool.utilization}%" aria-valuenow="${pool.utilization}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span class="metric-value">${pool.utilization.toFixed(2)}%</span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8">${lockedBadge}</div>
                        </div>
                        ${pool.locked ? `
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Locked At:</strong></div>
                            <div class="col-sm-8">${lockTimeStr}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Lock Reason:</strong></div>
                            <div class="col-sm-8">${lockDescStr}</div>
                        </div>` : ''}
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="fas fa-list me-2"></i>Allocated IPs</h5>
            `;
            if (pool.allocated_ips.length > 0) {
                details += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>IP Address</th><th>Hostname</th></tr></thead><tbody>`;
                pool.allocated_ips.forEach(ip => {
                    const [ipAddr, hostname] = ip.split(' (');
                    const cleanHostname = hostname.slice(0, -1); // remove )
                    details += `<tr><td><span class="metric-value">${ipAddr}</span></td><td><a href="dashboard.php?search=${encodeURIComponent(cleanHostname)}" target="_blank" class="text-decoration-none">${cleanHostname}</a></td></tr>`;
                });
                details += `</tbody></table></div>`;
            } else {
                details += `<p class="text-muted">No IPs allocated in this pool.</p>`;
            }
            details += `
                    </div>
                </div>
            `;
            document.getElementById('pool-details').innerHTML = details;
            new bootstrap.Modal(document.getElementById('poolModal')).show();
        }

        // Sorting
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const sortDir = this.dataset.sortDir;
                const currentPools = Array.from(ipamTableBody.querySelectorAll('tr')).map(row => {
                    const cells = row.querySelectorAll('td');
                    return {
                        group: cells[0].textContent,
                        name: cells[1].textContent,
                        subnet: cells[2].textContent,
                        total: parseInt(cells[3].textContent) || 0,
                        free: parseInt(cells[4].textContent) || 0,
                        used: parseInt(cells[5].textContent) || 0,
                        utilization: parseFloat(cells[6].textContent) || 0,
                        element: row
                    };
                });
                currentPools.sort((a, b) => {
                    let cmp;
                    if (sortBy === 'utilization') {
                        cmp = a[sortBy] - b[sortBy];
                    } else if (typeof a[sortBy] === 'string') {
                        cmp = a[sortBy].localeCompare(b[sortBy]);
                    } else {
                        cmp = a[sortBy] - b[sortBy];
                    }
                    return sortDir === 'asc' ? cmp : -cmp;
                });
                ipamTableBody.innerHTML = '';
                currentPools.forEach(item => ipamTableBody.appendChild(item.element));
                // Toggle sort direction
                this.dataset.sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            });
        });

        // Populate subnets based on group
        groupFilter.addEventListener('change', function() {
            const selectedGroup = this.value;
            subnetFilter.innerHTML = '<option value="">All</option>';
            if (selectedGroup) {
                const groupSubnets = ipamData.filter(pool => pool.group === selectedGroup).map(pool => pool.subnet).sort();
                groupSubnets.forEach(subnet => {
                    const option = document.createElement('option');
                    option.value = subnet;
                    option.textContent = subnet;
                    subnetFilter.appendChild(option);
                });
            } else {
                // All subnets
                const allSubnets = [...new Set(ipamData.map(pool => pool.subnet))].sort();
                allSubnets.forEach(subnet => {
                    const option = document.createElement('option');
                    option.value = subnet;
                    option.textContent = subnet;
                    subnetFilter.appendChild(option);
                });
            }
            filterTable();
        });

        // Debounce search input
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterTable, 300);
        });

        // Event listeners
        subnetFilter.addEventListener('change', filterTable);
        utilizationFilter.addEventListener('change', filterTable);
        lockedFilter.addEventListener('change', filterTable);

        // Initial populate subnets
        const allSubnets = [...new Set(ipamData.map(pool => pool.subnet))].sort();
        allSubnets.forEach(subnet => {
            const option = document.createElement('option');
            option.value = subnet;
            option.textContent = subnet;
            subnetFilter.appendChild(option);
        });

        // Initial render
        filterTable();
    </script>
</body>
</html>