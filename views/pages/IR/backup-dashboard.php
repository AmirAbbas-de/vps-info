<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

function getOSGroup($osName)
{
    $os = strtolower($osName ?? '');
    if (strpos($os, 'windows') !== false) return 'Windows';
    if (strpos($os, 'mikrotik') !== false) return 'Mikrotik';
    return 'Linux';
}

function isIPInSubnet($ip, $subnet)
{
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
    <!-- Navbar -->


    <!-- Stats and Filters Section -->
    <div class="container-fluid">
        <div class="row mb-4">
            <!-- Filters Section -->

            <div class="card filter-section fade-in-up">
                <div class="">
                    <div class="col-12">
                        <div class="row mb-3">
                            <!-- آیتم ویندوز -->
                            <div class="col-md-3 mb-2 mb-md-0">
                                <div class="widget-item windows" style="background: rgba(0, 120, 212, 0.05);">
                                    <div class="icon-wrapper">
                                        <i class="fa-brands fa-windows"></i>
                                    </div>
                                    <div class="content">
                                        <div class="title">
                                            Windows
                                        </div>
                                        <div class="sub-text">
                                            <?php echo count($osGroups['Windows']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <div class="widget-item linux" style="background: rgba(244, 188, 0, 0.05);">
                                    <div class="icon-wrapper">
                                        <i class="fab fa-linux"></i>
                                    </div>
                                    <div class="content">
                                        <div class="title">
                                            Linux
                                        </div>
                                        <div class="sub-text">
                                            <?php echo count($osGroups['Linux']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <div class="widget-item mikrotik" style="background: rgba(211, 47, 47, 0.05);">
                                    <div class="icon-wrapper">
                                        <img src="mikrotik.webp" alt="Winbox" style="width: 28px; height: 28px; object-fit: contain;">
                                    </div>
                                    <div class="content">
                                        <div class="title">
                                            Mikrotik
                                        </div>
                                        <div class="sub-text">
                                            <?php echo count($osGroups['Mikrotik']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <div class="widget-item total" style="background: rgba(0, 0, 0, 0.05);">
                                    <div class="icon-wrapper">
                                        <i class="fas fa-server"></i>
                                    </div>
                                    <div class="content">
                                        <div class="title">
                                            Total VPS
                                        </div>
                                        <div class="sub-text">
                                            <?php echo count($vpsData); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body ">
                    <div class="row g-3 mb-3">
                        <div class="col-lg-4 col-md-4">
                            <label for="server-group" class="form-label" id="server-group-label">
                                <i class="fas fa-globe me-1"></i>Server Group
                            </label>
                            <select class="form-control select-beast" id="server-group">
                                <option value="">All Groups</option>
                                <?php foreach ($serverGroups as $group): ?>
                                    <option value="<?php echo $group; ?>"><?php echo $group; ?> (<?php echo $groupCounts[$group] ?? 0; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-4">
                            <label for="subnet" class="form-label" id="subnet-label">
                                <i class="fas fa-network-wired me-1"></i>Subnet
                            </label>
                            <select class="form-control select-beast" id="subnet">
                                <option value="">Select Server Group First</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-4">
                            <label for="server-name" class="form-label" id="server-name-label">
                                <i class="fas fa-server me-1"></i>Server Name
                            </label>
                            <select class="form-control select-beast" id="server-name">
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
                            <select class="form-control select-beast" id="os-filter">
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
                            <select class="form-control select-beast" multiple id="ram-filter">
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
                            <select class="form-control select-beast" id="status-filter">
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
            <div class="card table-section fade-in-up ">
                <div class="card-body bg-transparent">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="m-0"><i class="fas fa-table me-2"></i>VPS Instances</h4>
                        <button class="btn" id="btn-export-file"><i class="fas fa-file-alt"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped " id="vps-table">
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
                                </tr>
                            </thead>
                            <tbody id="vps-tbody">

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="vpsModal" data-bs-backdrop="false" tabindex="-1">
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
    </div>
    </div>


    <script src="../../../src/js/bootstrap.bundle.min.js"></script>
    <script src="../../../src/js/tom-select.complete.min.js"></script>
    <script>
        // Data from PHP
        const vpsData = <?php echo json_encode($vpsData); ?>;
        const ippoolsData = <?php echo json_encode($ippoolsData); ?>;
        const serverGroups = <?php echo json_encode($serverGroups); ?>;
        // Create TomSelect instances and keep references on window.TS for sync
        window.TS = window.TS || {};
        document.querySelectorAll('.select-beast').forEach(select => {
            try {
                window.TS[select.id] = new TomSelect(select, {
                    allowEmptyOption: true,
                    create: true
                });
            } catch (e) {
                console.warn('TomSelect init failed for', select.id, e);
            }
        });
    </script>
    <script src="../../../src/js/dashboard.js"></script>
    <!-- <script src="../../../src/js/all.min.js"></script> -->
</body>

</html>


</html>