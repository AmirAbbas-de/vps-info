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

function isIPInSubnet($ip, $subnet)
{
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
            'allocated_ips' => $allocatedIPs
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
    <title>VPS Info | IR</title>
    <link rel="stylesheet" href="../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../src/css/tom-select.min.css">
    <link rel="stylesheet" href="../../../src/css/dasboard.css">
    <link rel="stylesheet" href="../../../src/fonts/css/all.min.css">

    <?php
    include('../../layouts/header.php')
    ?>
    <style>
        .background {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 0;
            inset: 0;
            background: linear-gradient(to right, rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, linear-gradient(rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, radial-gradient(500px at 20% 80%, rgb(14 14 14 / 30%), transparent) 0% 0% / 100% 100%, radial-gradient(500px at 80% 20%, rgb(0 0 0 / 30%), transparent) 0% 0% / 100% 100% rgb(255, 255, 255);
        }
    </style>
</head>

<body>
    <div class="background"></div>
    <?php
    include('../../layouts/index.php')

    ?>


    <!-- Stats and Filters Section -->
    <div class="container-fluid">
        <div class="row mb-4">
            <!-- Filters Section -->

            <div class="card filter-section fade-in-up">
                <div class="">
                    <div class="row mb-3">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="widget-item " style="background: rgba(0, 0, 0, 0.05);">
                                <div class="icon-wrapper">
                                    <i class="fas fa-server"></i>
                                </div>
                                <div class="content">
                                    <div class="title">
                                        Total Pools
                                    </div>
                                    <div class="sub-text" id="total-pools">
                                        <?php echo $totalPools; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <!-- pick a color for ip blue -->
                            <div class="widget-item " style="background: rgba(0, 0, 255, 0.05);">
                                <div class="icon-wrapper" style="color:rgb(0,0,255); border-color:rgb(0,0,255);">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <div class="content">
                                    <div class="title" style="color:rgb(0,0,255);">
                                        Total IPs
                                    </div>
                                    <div class="sub-text" id="total-ips">
                                        <?php echo $totalIPs; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="widget-item " style="background: rgba(128, 0, 0, 0.05);">
                                <div class="icon-wrapper" style="color:rgb(128, 0, 0); border-color:rgb(128, 0, 0);">
                                    <i class="fas fa-location-pin-lock"></i>
                                </div>
                                <div class="content">
                                    <div class="title" style="color:rgb(128, 0, 0);">
                                        Used IPs
                                    </div>
                                    <div class="sub-text" id="total-used">
                                        <?php echo $totalUsed; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="widget-item " style="background: rgba(0, 128, 0, 0.05);">
                                <div class="icon-wrapper" style="color:rgb(0,128,0); border-color:rgb(0,128,0);">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div class="content">
                                    <div class="title" style="color:rgb(0,128,0);">
                                        Free IPs
                                    </div>
                                    <div class="sub-text" id="total-free">
                                        <?php echo $totalFree; ?>
                                    </div>
                                </div>
                            </div>
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
                            <label for="group-filter" class="form-label">
                                <i class="fas fa-globe me-1"></i>IP Group
                            </label>
                            <select class="form-control select-beast" id="group-filter">
                                <option value="">All Groups</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="subnet-filter" class="form-label">
                                <i class="fas fa-network-wired me-1"></i>Subnet
                            </label>
                            <select class="form-control select-beast" id="subnet-filter">
                                <option value="">All Subnets</option>
                                <?php foreach ($subnets as $subnet): ?>
                                    <option value="<?php echo $subnet; ?>"><?php echo $subnet; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label for="utilization-filter" class="form-label">
                                <i class="fas fa-chart-bar me-1"></i>Utilization
                            </label>
                            <select class="form-control select-beast" id="utilization-filter">
                                <option value="">All</option>
                                <option value="high">Most Used (>80%)</option>
                                <option value="low">Less Used (< 20%) </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Table Section -->
            <div class="card table-section fade-in-up">
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
                                </tr>
                            </thead>
                            <tbody id="ipam-tbody">
                                <?php foreach ($ipamData as $pool): ?>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Pool Details -->
        <div class="modal fade" id="poolModal" data-bs-backdrop="false" tabindex="-1">
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
        <script src="../../../src/js/bootstrap.bundle.min.js"></script>
        <script src="../../../src/js/tom-select.complete.min.js"></script>
        <script>
            document.querySelectorAll('.select-beast').forEach(select => {
                new TomSelect(select, {
                    allowEmptyOption: true,
                    create: true
                });
            });
            // Data
            const ipamData = <?php echo json_encode($ipamData); ?>;

            // Elements
            const searchInput = document.getElementById('search');
            const groupFilter = document.getElementById('group-filter');
            const subnetFilter = document.getElementById('subnet-filter');
            const utilizationFilter = document.getElementById('utilization-filter');
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
                `;
                    row.addEventListener('click', () => showPoolDetails(pool));
                    ipamTableBody.appendChild(row);
                });
            }

            function showPoolDetails(pool) {
                const utilClass = pool.utilization > 80 ? 'utilization-high' : (pool.utilization > 50 ? 'utilization-medium' : 'utilization-low');
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