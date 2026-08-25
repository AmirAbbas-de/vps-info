<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '/var/www/html/jdf.php';
require_once '/var/www/html/Database.php';

$servername = "127.0.0.1";
$username   = "npm";
$password   = "Lwdatda@11wr"; // TODO: move to env var / config outside webroot
$dbname     = "IRvpsInfo";

try {
    $db = new Database($servername, $dbname, $username, $password);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ------------------------------------------------------------------
// Cache setup (this was declared but never actually used before)
// ------------------------------------------------------------------
$cacheDir       = __DIR__ . '/cache';
$cacheFileVPS   = $cacheDir . '/vps_cache.json';
$cacheFileIPPools = $cacheDir . '/ippools_cache.json';
$cacheTime      = 300; // seconds

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

/**
 * Small helper: read a cache file if it exists and isn't stale.
 * Returns null if there's no usable cache.
 */
function readCache(string $file, int $ttl)
{
    if (!is_file($file)) {
        return null;
    }
    if ((time() - filemtime($file)) > $ttl) {
        return null; // stale
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return $data === null ? null : $data;
}

function writeCache(string $file, $data): void
{
    // best effort; don't let a cache write failure break the page
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

// ------------------------------------------------------------------
// Distinct saved_at times (cached — this list rarely changes within
// a 5 minute window, no reason to hit the DB on every request)
// ------------------------------------------------------------------
$cacheKeyTimes = $cacheDir . '/times_cache.json';
$uniqueTimes = readCache($cacheKeyTimes, $cacheTime);

if ($uniqueTimes === null) {
    $recordedTimeNow     = $db->select("SELECT DISTINCT saved_at FROM vps_snapshots ORDER BY saved_at DESC ");
    $recordedTimeHistory = $db->select("SELECT DISTINCT saved_at FROM vps_hourly_history ORDER BY saved_at DESC ");

    $allTimes = [];
    foreach ($recordedTimeNow as $row) {
        $allTimes[] = $row['saved_at'];
    }
    foreach ($recordedTimeHistory as $row) {
        $allTimes[] = $row['saved_at'];
    }
    $uniqueTimes = array_values(array_unique($allTimes));
    rsort($uniqueTimes);

    writeCache($cacheKeyTimes, $uniqueTimes);
}

$groupedTimes = [];
foreach ($uniqueTimes as $dateTime) {
    $parts = explode(' ', $dateTime);
    if (count($parts) == 2) {
        [$date, $time] = $parts;
        $groupedTimes[$date][] = $time;
    }
}

// ------------------------------------------------------------------
// Figure out which table to read VPS data from
// ------------------------------------------------------------------
$selectedSavedAt = $_GET['saved_at'] ?? null;
$hoursDifference = 0; // FIX: this was never defined before, causing an
                      // undefined-variable warning on every request and
                      // silently always falling into the "else" branch.

if ($selectedSavedAt) {
    $nowTimestamp = time();
    preg_match_all('!\d+!', $selectedSavedAt, $matches);
    $m = $matches[0];

    // Basic sanity check before calling jmktime
    if (count($m) >= 5) {
        $userTimestamp   = jmktime((int)$m[3], (int)$m[4], 0, (int)$m[1], (int)$m[2], (int)$m[0]);
        $diffSeconds     = $userTimestamp - $nowTimestamp;
        $absDiffSeconds  = abs($diffSeconds);
        $hoursDifference = floor($absDiffSeconds / 3600);
        $minutes         = floor(($absDiffSeconds % 3600) / 60);
    }
}

// ------------------------------------------------------------------
// Fetch VPS data (cached per saved_at value, since old snapshots
// never change once written)
// ------------------------------------------------------------------
$vpsCacheKey = $cacheDir . '/vps_' . ($selectedSavedAt ? md5($selectedSavedAt) : 'latest') . '.json';
$vpsData = readCache($vpsCacheKey, $cacheTime);

if ($vpsData === null) {
    if ($selectedSavedAt) {
        if ($hoursDifference >= 1) {
            $vpsData = $db->select("SELECT * FROM vps_hourly_history WHERE saved_at = ? ", [$selectedSavedAt]);
        } else {
            $vpsData = $db->select("SELECT * FROM vps_snapshots WHERE saved_at = ? ", [$selectedSavedAt]);
            if (empty($vpsData)) {
                $vpsData = $db->select("SELECT * FROM vps_snapshots WHERE saved_at = (SELECT MAX(saved_at) FROM vps_snapshots)");
            }
        }
    } else {
        $vpsData = $db->select("SELECT * FROM vps_snapshots WHERE saved_at = (SELECT MAX(saved_at) FROM vps_snapshots) ");
    }
    writeCache($vpsCacheKey, $vpsData);
}

// ------------------------------------------------------------------
// IP pools (cached separately — this table changes far less often
// than vps_snapshots)
// ------------------------------------------------------------------
$pools = readCache($cacheFileIPPools, $cacheTime);
if ($pools === null) {
    $pools = $db->select("SELECT * FROM ippool_snapshots");
    writeCache($cacheFileIPPools, $pools);
}

$ippoolsData = [];
foreach ($pools as $pool) {
    $group = trim($pool['group'] ?? '');
    if ($group === '') {
        $group = trim($pool['location'] ?? 'Unknown');
    }
    $ippoolsData[$group][] = $pool;
}

// ------------------------------------------------------------------
// Group VPS by OS
// ------------------------------------------------------------------
$osGroups = ['Windows' => [], 'Mikrotik' => [], 'Linux' => []];
foreach ($vpsData as $vps) {
    $osGroups[getOSGroup($vps['os_name'] ?? '')][] = $vps;
}

$serverGroups = array_keys($ippoolsData);
sort($serverGroups);

// ------------------------------------------------------------------
// Count VPS per group — this used to be an O(vps * groups * pools)
// triple loop, re-parsing every subnet string on every comparison.
// Now we:
//   1. Pre-parse every subnet ONCE into [network_long, mask_bits]
//   2. Sort candidate subnets so we still short-circuit on first match
//   3. Reuse the parsed IP long for each VPS instead of re-exploding
// ------------------------------------------------------------------
$parsedPools = []; // group => [ [netLong, maskBits], ... ]
foreach ($ippoolsData as $group => $poolList) {
    foreach ($poolList as $pool) {
        $parsed = parseSubnet($pool['subnet']);
        if ($parsed !== null) {
            $parsedPools[$group][] = $parsed;
        }
    }
}

$groupCounts = [];
foreach ($vpsData as $vps) {
    $ipStr = explode(',', $vps['ips'] ?? '')[0] ?? '';
    if ($ipStr === '') {
        continue;
    }
    $ipLong = ip2long(trim($ipStr));
    if ($ipLong === false) {
        continue;
    }

    foreach ($parsedPools as $group => $subnets) {
        foreach ($subnets as [$netLong, $maskBits]) {
            $mask = $maskBits === 0 ? 0 : (~0 << (32 - $maskBits));
            if (($ipLong & $mask) === ($netLong & $mask)) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
                continue 3; // matched this VPS, move to next VPS
            }
        }
    }
}

// ------------------------------------------------------------------
// Helper functions
// ------------------------------------------------------------------
function getOSGroup($osName)
{
    $os = strtolower($osName ?? '');
    if (strpos($os, 'windows') !== false) return 'Windows';
    if (strpos($os, 'mikrotik') !== false) return 'Mikrotik';
    return 'Linux';
}

/**
 * Parse "a.b.c.d/mask" once into [networkLong, maskBits].
 * Returns null on malformed input instead of throwing warnings.
 */
function parseSubnet(?string $subnet): ?array
{
    if (!$subnet || strpos($subnet, '/') === false) {
        return null;
    }
    [$subnetIP, $mask] = explode('/', $subnet, 2);
    $mask = (int)$mask;
    $netLong = ip2long(trim($subnetIP));
    if ($netLong === false || $mask < 0 || $mask > 32) {
        return null;
    }
    return [$netLong, $mask];
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
    <link rel="stylesheet" href="../../../src/css/jalalidate.css">
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
        jdp-container .jdp-time-container .jdp-time:first-child {
            display: none !important;
        }
        jdp-time-container span:last-of-type {
            display: none !important;
        }
        jdp-time-container {
            justify-content: center !important;
            gap: 5px;
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
                     
                        <div class="d-flex ">
                            <div class="form-group d-flex" style="z-index:10000;" >
                                <?php if($_GET['saved_at']){ 
                                    $req = $_GET['saved_at'];
                                    $parts = explode(' ',$req);
                                    $today = $parts[0];
                                    $hour = $parts[1];
                                }?>
                                <select id="day-select" class="form-control select-beast">
                                    <?php if($today){?>
                                    <option value="<?php echo $today ?>"><?php echo $today?></option>                                  
                                    <?php }else{?>
                                    <option value="">select a day</option>                                  
                                    <?php }?>
                                    <?php foreach ($groupedTimes as $day => $times) { ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php } ?>
                                </select>
                                <select id="time-select" class="form-control select-beast">
                                    <?php if($hour){?>
                                    <option value="<?php echo $hour?>"><?php echo $hour?></option>
                                    <?php foreach ($groupedTimes as $f => $s) {?>
                                        <?php if($f == $today){?>
                                            <?php foreach ($s as $dateTime){?>
                                            <option value="<?php echo $dateTime?>"><?php echo $dateTime?></option>
                                            <?php }?>
                                        <?php }?>
                                    <?php }?>
                                    <?php }else{ ?>
                                    <option value="">select an hour</option>
                                    <?php }?>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2" style="z-index: 100;">                            
                                <button class="btn btn-outline-success ms-3" id="btn-export-file"><i class="fas fa-file-alt"></i></button>
                            </div>
                        </div>
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
                                    <th class="sortable" data-sort="saved_at" data-sort-dir="asc">
                                        <div class="d-flex justify-content">
                                            <i></i>creation_date_shamsi
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
        let vpsData = <?php echo json_encode($vpsData); ?>;
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
        window.TS = window.TS || {};

        const groupedData = <?php echo json_encode($groupedTimes); ?>;

        // ۲. اینیت کردن تمام Selectها
        document.querySelectorAll('.select-beast').forEach(select => {
            try {
                window.TS[select.id] = new TomSelect(select, {
                    allowEmptyOption: true,
                    create: false // معمولاً برای انتخاب زمان نیازی به create نیست
                });
            } catch (e) {
                console.warn('TomSelect init failed for', select.id, e);
            }
        });

        // ۳. مدیریت تغییرات روز
        document.getElementById('day-select').addEventListener('change', function() {
            const selectedDay = this.value;
            const timeSelectElement = document.getElementById('time-select');
            const timeSelectTom = window.TS['time-select']; // دسترسی به اینسنتس TomSelect

            if (timeSelectTom) {
                // خالی کردن گزینه‌های قبلی در TomSelect
                timeSelectTom.clear();
                timeSelectTom.clearOptions();

                if (selectedDay && groupedData[selectedDay]) {
                    // اضافه کردن آپشن‌های جدید
                    const options = groupedData[selectedDay].map(time => {
                        return { value: time, text: time };
                    });
                    
                    timeSelectTom.addOptions(options);
                    timeSelectTom.refreshOptions(false);
                }
            }
        });
    </script>
    <script src="../../../src/js/dashboard.js"></script>
</body>

</html>

