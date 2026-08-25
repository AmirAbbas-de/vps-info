<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '/var/www/html/Database.php';


$servername = "192.168.100.78";
$username = "ilo_user";
$password = "123456";
$dbname = "whmcs";
$ip = $_GET['ip'] ?? '';

$db = new Database($servername, $dbname, $username, $password);

try {
    $results = $db->select("SELECT * FROM server_details WHERE ip = ?", [$ip]);


    if (empty($results)) {
        die("سرور با این IP پیدا نشد!");
    }

    $server = $results[0]['id'] ?? null;
    $serverInfo = $results[0];
    $ServerLogInfo = $db->select("SELECT * FROM server_log WHERE server_details_id = ?", [$server]);

    $ServerRaid = $db->select("SELECT * FROM server_logical_drive WHERE server_details_id = ?", [$server]);

    $ServerMac = $db->select("SELECT * FROM server_mac WHERE server_details_id = ?", [$server]);

    // hards
    $ServerHard = $db->select("SELECT * FROM server_physical_drive WHERE server_details_id = ?", [$server]);

    $ServerPower = $db->select("SELECT * FROM server_power WHERE server_details_id = ?", [$server]);

    // RAMs
    $ServerRAM = $db->select("SELECT * FROM server_memory_detail WHERE server_details_id = ?", [$server]);
} catch (PDOException $e) {
    die("خطا در اتصال: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../../src/css/tom-select.min.css">
    <link rel="stylesheet" href="../../../../src/css/dasboard.css">
    <link rel="stylesheet" href="../../../../src/fonts/css/all.min.css">
    <style>
        /* =========================
   GLOBAL UI THEME
========================= */

        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;

            --green: #16a34a;
            --red: #ef4444;
            --amber: #f59e0b;
            --blue: #2563eb;

            --radius: 12px;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
        }

        /* =========================
   BACKGROUND (soft minimal)
========================= */

        .background {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at top, #ffffff 0%, var(--bg) 60%);
            z-index: -1;
        }

        /* =========================
   MAIN CONTAINER
========================= */

        .container {
            max-width: 1400px;
            margin-top: 20px;
        }

        /* =========================
   MAIN CARD WRAPPER
========================= */

        .filter-section {
            background: transparent !important;
            border: none !important;
        }

        .card-body {
            padding: 0;
        }

        /* =========================
   HEADER
========================= */

        .card-title {
            padding: 16px 0;
        }

        .card-title h4 {
            font-size: 18px;
            font-weight: 600;
        }

        .btn-outline-secondary {
            border-radius: 10px;
            font-size: 13px;
        }

        /* =========================
   SERVER SPEC CARD
========================= */

        .server-spec-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px;
        }

        .spec-header {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--muted);
        }

        .spec-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fafafa;
        }

        .spec-label {
            font-size: 12px;
            color: var(--muted);
        }

        .spec-value {
            font-size: 13px;
            font-weight: 500;
        }

        /* =========================
   RAID WIDGET
========================= */

        .raid-widget {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px;
        }

        .raid-widget.status-green {
            border-left: 4px solid var(--green);
        }

        .raid-widget.status-amber {
            border-left: 4px solid var(--amber);
        }

        .label-tag {
            font-size: 11px;
            color: var(--muted);
        }

        .drive-label {
            font-size: 14px;
            margin: 4px 0 0;
        }

        .status-indicator {
            font-size: 12px;
            color: var(--muted);
        }

        /* =========================
   DRIVE DETAILS
========================= */

        .drive-details {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
        }

        .detail-item small {
            color: var(--muted);
            font-size: 11px;
        }

        .detail-item p {
            margin: 2px 0 0;
            font-size: 13px;
            font-weight: 500;
        }

        /* =========================
   MAC / NETWORK CARD
========================= */

        .spec-card {
            margin-bottom: 10px;
        }

        .spec-mac-item {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px;
            cursor: pointer;
        }

        .spec-mac-item:hover {
            border-color: #cbd5e1;
        }

        .fiber-style {
            border-left: 4px solid var(--blue);
        }

        .copper-style {
            border-left: 4px solid #b87333;
        }

        .type-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .icon-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .icon-dot.connect {
            background: var(--green);
        }

        .icon-dot.notconnect {
            background: var(--red);
        }

        /* =========================
   TABS (Bootstrap override)
========================= */

        .nav-pills .nav-link {
            border-radius: 10px;
            font-size: 13px;
            color: var(--muted);
            border: 1px solid var(--border);
            margin-bottom: 6px;
        }

        .nav-pills .nav-link.active {
            background: var(--blue);
            color: white;
        }

        /* =========================
   CARD (GLOBAL OVERRIDE)
========================= */

        .card {
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
        }

        /* dark bootstrap override cleanup */
        .card.bg-dark {
            background: #111827 !important;
            border-color: #1f2937 !important;
        }

        /* =========================
   BADGES
========================= */

        .badge {
            border-radius: 999px;
            font-size: 11px;
        }

        /* =========================
   MODALS (clean minimal)
========================= */

        .modal-content {
            border-radius: 14px;
            border: 1px solid var(--border);
        }

        .modal-body {
            font-size: 13px;
        }

        /* =========================
   UTILITIES
========================= */

        hr {
            border-color: var(--border);
        }

        .text-muted {
            color: var(--muted) !important;
        }

        /* =========================
   POWER SUPPLY CARD
========================= */

        .power-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            transition: 0.15s ease;
        }

        .power-card:hover {
            border-color: #cbd5e1;
        }

        .power-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .power-title {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }

        .power-badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
        }

        /* body layout */
        .power-body {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .power-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        /* labels */
        .power-row .label {
            font-size: 12px;
            color: #6b7280;
        }

        /* values */
        .power-row .value {
            font-size: 13px;
            font-weight: 500;
            color: #111827;
        }

        /* monospace */
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        /* =========================
   SERVER RAM SLOT VIEW
========================= */

        .ram-server {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            padding: 10px;
        }

        @media (max-width: 992px) {
            .ram-server {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .ram-server {
                grid-template-columns: 1fr;
            }
        }

        /* SLOT */
        .ram-slot {
            border-radius: 10px;
            padding: 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            cursor: pointer;
            transition: 0.15s ease;

            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ram-slot:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        /* filled vs empty */
        .ram-slot.filled {
            border-left: 4px solid #16a34a;
        }

        .ram-slot.empty {
            border-left: 4px solid #9ca3af;
            opacity: 0.75;
        }

        /* labels */
        .slot-label {
            font-size: 12px;
            color: #6b7280;
        }

        .slot-state {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }

        /* empty style override */
        .ram-slot.empty .slot-state {
            color: #9ca3af;
            font-weight: 500;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .info-row span {
            font-size: 12px;
            color: #6b7280;
        }

        .info-row b {
            font-size: 13px;
            color: #111827;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        /* =========================
   STORAGE / DISK BAYS (SERVER STYLE)
========================= */
        /* MAIN DISK CARD */
        .disk-card {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            transition: 0.15s ease;
            position: relative;
        }

        /* hover feel like hardware slot */
        .disk-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        /* STATUS STRIP (like LED indicator) */
        .disk-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 4px;
            border-radius: 4px;
            background: #16a34a;
            /* default green */
        }

        /* if degraded / warning (optional class support) */
        .disk-card.warning::before {
            background: #f59e0b;
        }

        .disk-card.bad::before {
            background: #ef4444;
        }

        /* HEADER */
        .disk-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .disk-location {
            font-size: 12px;
            font-weight: 600;
            color: #111827;
        }

        .disk-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #6b7280;
        }

        /* BODY */
        .disk-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* ROW STYLE */
        .disk-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 10px;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .disk-row span:first-child {
            font-size: 11px;
            color: #6b7280;
        }

        .disk-row span:last-child {
            font-size: 12px;
            font-weight: 500;
            color: #111827;
        }

        /* MONO STYLE (serial) */
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .nav-link.active {
            background-color: #fff !important;
        }
    </style>
</head>

<?php
$allDisconnected = true;
foreach ($ServerMac as $item) {
    $id = $item['id'];
    $ServerUesAbleIp = $db->select("SELECT * FROM server_usable_ips WHERE server_mac_id = ?", [$id]);
    if (!empty($ServerUesAbleIp) && $ServerUesAbleIp[0]['status'] == 'connect') {
        $allDisconnected = false;
        break;
    }
}
?>

<body>
    <div class="background"></div>
    <?php include('../../layouts/index.php') ?>
    <div class="container">
        <div class="card filter-section p-5 pt-4">
            <div class="card-body">
                <div class="card-title mb-4 d-flex justify-content-between">
                    <h4 class="ms-3"><?= $serverInfo['server_type'] ?><?php if ($allDisconnected) { ?><span class="ms-3 text-success">( Free )</span><?php } ?></h4>
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logs"><i class="fas fa-log"></i>Logs</button>
                </div>
                <div class="row">
                    <div class="col-md-5 col-12">
                        <div class="server-spec-card border">
                            <div class="spec-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-microchip"></i> Hardware Configuration</span>
                            </div>
                            <div class="spec-grid">
                                <div class="spec-item">
                                    <span class="spec-label">IP Address</span>
                                    <span class="spec-value"><?= htmlspecialchars($serverInfo['ip']); ?></span>
                                </div>
                                <div class="spec-item">
                                    <span class="spec-label">Model</span>
                                    <span class="spec-value"><?= htmlspecialchars($serverInfo['server_type']); ?></span>
                                </div>

                                <div class="spec-item">
                                    <span class="spec-label">Processor</span>
                                    <span class="spec-value"><?= htmlspecialchars($serverInfo['cpu']); ?></span>
                                </div>
                                <div class="spec-item pb-0">
                                    <span class="spec-label">Serial Number</span>
                                    <span class="spec-value text-uppercase"><?= htmlspecialchars($serverInfo['server_serial']); ?></span>
                                </div>
                                <div class="spec-item">
                                    <span class="spec-label">Switch Info</span>
                                    <span class="spec-value"><?= htmlspecialchars($serverInfo['ilo_switch']); ?> (Port: <?= htmlspecialchars($serverInfo['ilo_port']); ?>)</span>
                                </div>
                                <div class="spec-item full-width">
                                    <span class="spec-label">iLO MAC Address</span>
                                    <span class="spec-value font-monospace"><?= htmlspecialchars($serverInfo['ilo_mac']); ?></span>
                                </div>
                                <div class="spec-item ">
                                    <span class="spec-label">iLO VLAN</span>
                                    <span class="spec-value"><?= htmlspecialchars($serverInfo['ilo_vlan']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="server-spec-card border mt-2">
                            <?php
                            $ramGroups = [];
                            $totalRam = 0;

                            foreach ($ServerRAM as $ram) {

                                $isActive = stripos($ram['status'], 'Good') !== false;
                                if (!$isActive) continue;

                                // extract size (8GB → 8)
                                preg_match('/(\d+)/', $ram['size'], $sizeMatch);
                                $size = isset($sizeMatch[1]) ? (int)$sizeMatch[1] : 0;

                                // extract frequency (2800 MHz → 2800)
                                preg_match('/(\d+)/', $ram['frequency'], $freqMatch);
                                $freq = isset($freqMatch[1]) ? (int)$freqMatch[1] : 0;

                                if ($size > 0) {
                                    $key = $size . '_' . $freq;

                                    if (!isset($ramGroups[$key])) {
                                        $ramGroups[$key] = [
                                            'size' => $size,
                                            'frequency' => $freq,
                                            'count' => 0,
                                            'total' => 0
                                        ];
                                    }

                                    $ramGroups[$key]['count']++;
                                    $ramGroups[$key]['total'] += $size;
                                    $totalRam += $size;
                                }
                            }
                            ?>
                            <div class="spec-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-memory"></i> RAM Details</span>
                            </div>
                            <div class="spec-grid">
                                <div class="spec-item">
                                    <span class="spec-label">Total RAM</span>
                                    <span class="spec-value"><strong><?= $totalRam / 1024 ?> GB</strong></span>
                                </div>
                                <?php foreach ($ramGroups as $group): ?>
                                    <div class="spec-item">
                                        <span class="spec-label"><?= $group['count'] ?> - <?= $group['frequency'] ?>MHz</span>
                                        <span class="spec-value"><?= $group['size'] / 1024 ?>GB</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="server-spec-card border mt-2">
                            <?php
                            $diskGroups = [];
                            $totalStorage = 0;

                            foreach ($ServerHard as $disk) {

                                // extract size number (e.g. "1 TB" → 1)
                                preg_match('/([\d\.]+)/', $disk['capacity'], $sizeMatch);
                                $size = isset($sizeMatch[1]) ? (float)$sizeMatch[1] : 0;

                                $type = $disk['media_type'] ?? 'Unknown';
                                $model = $disk['model'] ?? 'Unknown';

                                if ($size > 0) {

                                    $key = $type . '_' . $model . '_' . $size;

                                    if (!isset($diskGroups[$key])) {
                                        $diskGroups[$key] = [
                                            'type' => $type,
                                            'model' => $model,
                                            'size' => $size,
                                            'count' => 0,
                                            'total' => 0
                                        ];
                                    }

                                    $diskGroups[$key]['count']++;
                                    $diskGroups[$key]['total'] += $size;
                                    $totalStorage += $size;
                                }
                            }
                            ?>
                          
                            <div class="spec-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-server"></i> Hard Details</span>
                            </div>
                            <div class="spec-grid">
                                <div class="spec-item">
                                    <span class="spec-label">Total Storage</span>
                                    <span class="spec-value"><strong><?= $totalStorage ?> GB</strong></span>
                                </div>
                                <?php foreach ($diskGroups as $group): ?>
                                    <div class="spec-item">
                                        <span class="spec-label"><?= $group['count'] ?> - <?= strtoupper($group['type']) ?></span>
                                        <span class="spec-value"><?= $group['size'] ?>GB</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 col-12">
                        <?php foreach ($ServerRaid as $drive) { ?>
                            <div class="raid-widget border mb-2 <?= ($drive['status'] == 'Degraded') ? 'status-amber' : 'status-green' ?>">
                                <div class="widget-inner">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="label-tag">Logical Drive</span>
                                            <h4 class="drive-label">Label <?= $drive['label'] ?></h4>
                                        </div>
                                        <div class="status-indicator">
                                            <span class="pulse-dot"></span>
                                            <?= $drive['status'] ?>
                                        </div>
                                    </div>

                                    <div class="drive-details">
                                        <div class="detail-item">
                                            <small>Capacity</small>
                                            <p><?= $drive['capacity'] ?></p>
                                        </div>
                                        <div class="detail-item">
                                            <small>Fault Tolerance</small>
                                            <p><?= $drive['fault_tolerance'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php }  ?>
                    </div>
                    <div class="col-lg-3 col-md-6 col-12">
                        <?php
                        $macGroups = [];
                        foreach ($ServerMac as $item) {
                            $clean = strtolower(str_replace(['.', ':', '-'], '', $item['mac']));
                            $prefix = substr($clean, 0, 10); // Get the first 10 chars
                            $macGroups[$prefix] = ($macGroups[$prefix] ?? 0) + 1;
                        }
                        ?>


                        <?php foreach ($ServerMac as $key => $item) {
                            $cleanMac = strtolower(str_replace(['.', ':', '-'], '', $item['mac']));
                            $prefix = substr($cleanMac, 0, 10);
                            $count = $macGroups[$prefix] ?? 0;
                            $speedLabel = 'Unknown';
                            if ($count === 4) {
                                $speedLabel = '1G';
                            } elseif ($count === 2) {
                                $speedLabel = '10G';
                            }
                            $id = $item['id'];
                            $ServerUesAbleIp = $db->select("SELECT * FROM server_usable_ips WHERE server_mac_id = ?", [$id]);
                        ?>
                            <div class="spec-card">
                                <div class="spec-mac-item border <?= $speedLabel == '10G' ? 'fiber-style' : 'copper-style' ?>" data-bs-toggle="modal" data-bs-target="#vlanInfo-<?= $key ?>">
                                    <div class="type-badge <?= $ServerUesAbleIp[0]['status'] == 'connect' ? 'connect' : 'notconnect' ?>">
                                        <span class="icon-dot <?= $ServerUesAbleIp[0]['status'] == 'connect' ? 'connect' : 'notconnect' ?>"></span>
                                        <span style="color:<?= $speedLabel == '10G' ? '#2069ad' : '#b87333' ?>">
                                            <?= $speedLabel == '10G' ? 'Fiber Optic' : 'Copper Wire' ?>
                                        </span>
                                    </div>
                                    <div class="mac-address text-uppercase">
                                        <span><?= htmlspecialchars($item['mac']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>


                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="d-flex align-items-start">
                        <div class="nav flex-column nav-pills me-3" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active" id="v-pills-home-tab" data-bs-toggle="pill" data-bs-target="#v-pills-home" type="button" role="tab" aria-controls="v-pills-home" aria-selected="true">POWER</button>
                            <button class="nav-link" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab" aria-controls="v-pills-profile" aria-selected="false">Hardware</button>
                            <button class="nav-link" id="v-pills-tab" data-bs-toggle="pill" data-bs-target="#v-pills" type="button" role="tab" aria-controls="v-pills" aria-selected="false">RAM</button>
                        </div>
                        <div class="vr"></div>
                        <div class="tab-content w-100 ps-2" id="v-pills-tabContent">
                            <div class="tab-pane fade show active" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab" tabindex="0">
                                <div class="row g-4">

                                    <?php foreach ($ServerPower as $power): ?>
                                        <div class="col-md-6">

                                            <div class="power-card">

                                                <div class="power-header">

                                                    <div class="power-title">
                                                        Power Supply
                                                    </div>

                                                    <div class="power-badge">
                                                        <?= htmlspecialchars($power['watt']) ?>
                                                    </div>

                                                </div>

                                                <div class="power-body">

                                                    <div class="power-row">
                                                        <span class="label">Model</span>
                                                        <span class="value">
                                                            <?= htmlspecialchars($power['model']) ?>
                                                        </span>
                                                    </div>

                                                    <div class="power-row">
                                                        <span class="label">Serial Number</span>
                                                        <span class="value mono">
                                                            <?= htmlspecialchars($power['sn']) ?>
                                                        </span>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>
                            </div>
                            <div class="tab-pane fade" id="v-pills" role="tabpanel" aria-labelledby="v-pills-home-tab" tabindex="0">
                                <div class="row g-4">

                                    <div class="ram-server">

                                        <?php foreach ($ServerRAM as $index => $ram): ?>

                                            <?php $isActive = stripos($ram['status'], 'Good') !== false; ?>

                                            <div class="ram-slot <?= $isActive ? 'filled' : 'empty' ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#ramModal-<?= $index ?>">

                                                <div class="slot-label">
                                                    DIMM <?= htmlspecialchars($ram['socket']) ?>
                                                </div>

                                                <div class="slot-state">
                                                    <?= $isActive ? htmlspecialchars($ram['size']) : 'EMPTY' ?>
                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                </div>

                            </div>
                            <div class="tab-pane fade" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab" tabindex="0">
                                <div class="row">
                                    <div class="disk-grid row">

                                        <?php foreach ($ServerHard as $disk): ?>

                                            <div class="col-md-3 mb-4">

                                                <div class="power-card">

                                                    <div class="disk-header">

                                                        <div class="disk-location">
                                                            <?= htmlspecialchars($disk['location']) ?>
                                                        </div>

                                                        <div class="disk-status">
                                                            <?= htmlspecialchars($disk['status']) ?>
                                                        </div>

                                                    </div>

                                                    <div class="disk-body">

                                                        <div class="disk-row">
                                                            <span>Model</span>
                                                            <span><?= htmlspecialchars($disk['model']) ?></span>
                                                        </div>

                                                        <div class="disk-row">
                                                            <span>Serial</span>
                                                            <span class="mono"><?= htmlspecialchars($disk['serial_number']) ?></span>
                                                        </div>

                                                        <div class="disk-row">
                                                            <span>Firmware</span>
                                                            <span><?= htmlspecialchars($disk['fw_version']) ?></span>
                                                        </div>

                                                        <div class="disk-row">
                                                            <span>Capacity</span>
                                                            <span><?= htmlspecialchars($disk['capacity']) ?></span>
                                                        </div>

                                                        <div class="disk-row">
                                                            <span>Media</span>
                                                            <span><?= htmlspecialchars($disk['media_type']) ?></span>
                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- vlan id -->
    <!-- name  -->
    <!-- subnet -->

    <!-- primaryIP -->

    <!-- hardware -->
    <?php foreach ($ServerMac as $key => $item) {
        $id = $item['id'];
        $ServerUesAbleIp = $db->select("SELECT * FROM server_usable_ips WHERE server_mac_id = ?", [$id]);
    ?>
        <div class="modal fade" style="z-index:10000" id="vlanInfo-<?= $key ?>">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content p-3 bg-white">
                    <div class="modal-header">
                        <h5>VLAN : <?= htmlspecialchars($item['mac']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $hasInfo = false;
                        if (!empty($ServerUesAbleIp)) {
                            foreach ($ServerUesAbleIp as $ServerIp) {
                                if ($ServerIp['status'] == 'connect') {
                                    $json = json_decode($ServerIp['arp'], true);
                                    $mac_address = strval($json['primary_mac']);
                                    $SwMacInfo = $db->select("SELECT vlan,port_swild FROM sw_mac_info WHERE mac_address = ?", [$mac_address]);
                                    foreach ($SwMacInfo as $info) {
                                        $vlan = $info['vlan'];
                                        $RouterVlanInfo = $db->select("SELECT customer_name,router_name,subnet FROM router_vlan_info WHERE vlan_id = ?", [$vlan]);
                                        foreach ($RouterVlanInfo as $router) {
                                            $hasInfo = true;
                                            echo "<div class='item-vlan-wrapper'>";
                                            echo "<p><strong>Customer:</strong> " . htmlspecialchars($router['customer_name']) . "</p>";
                                            echo "<p><strong>Router:</strong> " . htmlspecialchars($router['router_name']) . "</p>";
                                            echo "<p><strong>Subnet:</strong> " . htmlspecialchars($router['subnet']) . "</p>";
                                            echo "<hr>";
                                            echo "</div>";
                                        }
                                    }
                                }
                            }
                        }
                        if (!$hasInfo) {
                            echo "<p class='text-muted'>No VLAN information available.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <div class="modal fade" id="logs">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white p-3">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body">
                    <!-- logs ilo -->
                    <?php
                    usort($ServerLogInfo, function ($a, $b) {
                        return strtotime($b['last_update']) <=> strtotime($a['last_update']);
                    });
                    ?>

                    <?php foreach ($ServerLogInfo as $log) { ?>
                        <p><?= $log['descript'] ?></p>
                        <div><span class="ms-auto d-inline-block text-muted"><?= $log['last_update'] ?></span></div>
                        <hr>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php foreach ($ServerRAM as $index => $ram): ?>

        <?php $isActive = stripos($ram['status'], 'Good') !== false; ?>

        <div class="modal fade" id="ramModal-<?= $index ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-clean bg-white">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            DIMM Slot <?= htmlspecialchars($ram['socket']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <?php if ($isActive): ?>

                            <div class="info-grid">

                                <div class="info-row">
                                    <span>Size</span>
                                    <b><?= htmlspecialchars($ram['size']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Type</span>
                                    <b><?= htmlspecialchars($ram['memory_type']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Technology</span>
                                    <b><?= htmlspecialchars($ram['technology']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Frequency</span>
                                    <b><?= htmlspecialchars($ram['frequency']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Part Number</span>
                                    <b class="mono"><?= htmlspecialchars($ram['part_number']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Voltage</span>
                                    <b><?= htmlspecialchars($ram['minimum_voltage']) ?></b>
                                </div>

                                <div class="info-row">
                                    <span>Ranks</span>
                                    <b><?= htmlspecialchars($ram['ranks']) ?></b>
                                </div>
                                <div class="info-row">
                                    <span>CPU</span>
                                    <b><?= htmlspecialchars($ram['cpu']) ?></b>
                                </div>
                                <div class="info-row">
                                    <span>Socket</span>
                                    <b><?= htmlspecialchars($ram['socket']) ?></b>
                                </div>

                            </div>

                        <?php else: ?>

                            <p class="muted">No memory installed in this slot.</p>

                        <?php endif; ?>

                    </div>

                </div>
            </div>
        </div>

    <?php endforeach; ?>
    <script src="../../../../src/js/bootstrap.bundle.min.js"></script>
</body>

</html>