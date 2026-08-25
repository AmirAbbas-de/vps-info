<?php
require_once '/var/www/html/Database.php';

$servername = "192.168.100.78";
$username = "ilo_user";
$password = "123456";
$dbWhmcs = new Database($servername, "whmcs", $username, $password);

// Fetch data including your custom JOIN logic
$sql = "SELECT DISTINCT 
            sd.ip AS ilo_ip, 
            JSON_UNQUOTE(JSON_EXTRACT(sui.arp, '$.primary_ip')) AS primary_ip
        FROM server_details sd
        INNER JOIN server_mac sm ON sd.id = sm.server_details_id
        INNER JOIN server_usable_ips sui ON sm.id = sui.server_mac_id
        WHERE sui.status != 'notConnect' AND JSON_VALID(sui.arp)";

$results = $dbWhmcs->select($sql);

// Grouping by Rack (The 3rd octet of the iLO IP)
$racks = [];
foreach ($results as $row) {
    $ipParts = explode('.', $row['ilo_ip']);
    if (count($ipParts) === 4) {
        $rackNum = $ipParts[2];

        // If Primary IP is empty or null, mark it as 'FREE'
        if (empty($row['primary_ip']) || $row['primary_ip'] == "null") {
            $row['primary_ip'] = "FREE";
        }

        $racks[$rackNum][] = $row;
    }
}

// Sort Racks numerically (Rack 2, Rack 3...)
ksort($racks);

// Sort IPs inside each rack numerically
foreach ($racks as $key => &$items) {
    usort($items, function ($a, $b) {
        return strnatcmp($a['ilo_ip'], $b['ilo_ip']);
    });
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Live Monitor</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --primary: #1c1c1e;
            --online: #34c759;
            --offline: #ff3b30;
            --muted: #8e8e93;
            --border: #e5e5ea;
        }

        body {
            font-family: -apple-system, sans-serif;
            background: var(--bg);
            color: var(--primary);
            margin: 0;
            padding: 20px;
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Main Content */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .card {
            background: var(--card);
            border-radius: 14px;
            padding: 16px;
            border: 1px solid var(--border);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .row:first-child {
            border-bottom: 1px solid #f2f2f7;
            margin-bottom: 8px;
        }

        .label {
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .ip {
            font-family: 'SF Mono', monospace;
            font-size: 13px;
        }

        .status {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            min-width: 55px;
            text-align: center;
        }

        .status.pending {
            background: #f2f2f7;
            color: #999;
        }

        .status.online {
            background: #eafaf1;
            color: var(--online);
        }

        .status.offline {
            background: #fff2f2;
            color: var(--offline);
        }

        /* Aside Log */
        .sidebar {
            background: var(--card);
            border-radius: 14px;
            border: 1px solid var(--border);
            height: calc(100vh - 40px);
            position: sticky;
            top: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h2 {
            font-size: 14px;
            margin: 0;
        }

        .log-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .log-item {
            font-size: 11px;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 6px;
            background: #fafafa;
            border-right: 3px solid #eee;
            animation: slideIn 0.3s ease;
        }

        .log-time {
            color: var(--muted);
            font-size: 9px;
            display: block;
        }

        .clear-btn {
            background: none;
            border: none;
            color: var(--offline);
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .rack-section {
            background: #ebedf0;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 40px;
            border: 2px solid var(--border);
        }

        .rack-header {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rack-header::before {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #007aff;
            border-radius: 3px;
        }

        .log-item {
            font-size: 12px;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: #ffffff;
            border: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            animation: slideIn 0.4s ease-out;
        }

        .log-time {
            color: var(--muted);
            font-family: 'SF Mono', monospace;
            font-size: 10px;
            background: #eee;
            padding: 2px 6px;
            border-radius: 4px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Main Layout: Sidebar stays fixed, Main content scrolls */
        .layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Vertical Stack for Racks */
        main {
            display: flex;
            flex-direction: column;
            gap: 25px;
            /* Space between Rack 2 and Rack 3 */
        }

        .rack-section {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: inset 5px 0 0 #007aff;
            /* Blue "spine" on the left of the rack */
        }

        .rack-header {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        /* Servers inside the rack */
        .grid {
            display: grid;
            /* This makes servers stay in a row, wrapping to the next line if too many */
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }

        .card {
            background: var(--bg);
            /* Darker background for cards to pop against white rack */
            border-radius: 10px;
            padding: 12px;
            border: 1px solid var(--border);
        }

        .summary-counters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .counter-card {
            background: #fff;
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .counter-label {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: bold;
        }

        .counter-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--offline);
        }

        .sidebar {
            min-height: 400px;
            /* Ensure it has a visible height */
            z-index: 100;
        }

        .status.free {
            background: #e1f0ff;
            color: #007aff;
        }

        /* Ensure logs look distinct */
        .log-item b {
            color: var(--primary);
            font-family: monospace;
        }
    </style>
</head>

<body>

    <div class="layout">
        <main>
            <header>
                <h1>Availibility Checker</h1>
                <p style="color: var(--muted); font-size: 13px;">Made with LOVE</p>
            </header>
            <div class="summary-counters">
                <div class="counter-card">
                    <span class="counter-label">Total Offline iLOs</span>
                    <span id="ilo-offline-count" class="counter-value">0</span>
                </div>
                <div class="counter-card">
                    <span class="counter-label">Total Offline Public IPs</span>
                    <span id="primary-offline-count" class="counter-value">0</span>
                </div>
                <div class="counter-card" style="border-bottom: 4px solid #007aff;">
                    <span class="counter-label">Total Free Servers</span>
                    <span id="free-count" class="counter-value" style="color: #007aff;">0</span>
                </div>
            </div>

            <?php foreach ($racks as $rackNum => $servers): ?>
                <section class="rack-section">
                    <div class="rack-header">
                        <span>📍 Rack Unit: <?= $rackNum ?></span>
                    </div>

                    <div class="grid">
                        <?php foreach ($servers as $row): ?>
                            <div class="card">
                                <div class="row">
                                    <div>
                                        <div class="label">iLO</div>
                                        <div class="ip"><?= $row['ilo_ip'] ?></div>
                                    </div>
                                    <div class="status pending ping-target" data-ip="<?= $row['ilo_ip'] ?>">Wait</div>
                                </div>
                                <div class="row">
                                    <div>
                                        <div class="label">Public IP</div>
                                        <div class="ip"><?= $row['primary_ip'] ?></div>
                                    </div>
                                    <div class="status pending ping-target" data-ip="<?= $row['primary_ip'] ?>">Wait</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>

        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Activity Logs</h2>
                <button class="clear-btn" onclick="clearLogs()">Clear</button>
            </div>
            <div id="logs" class="log-list"></div>
        </aside>
    </div>

    <script>
        // Configuration
        const CONFIG = {
            GO_ENGINE: 'http://192.168.100.88:8080/all-stats',
            LOG_STORAGE_KEY: 'network_activity_log_v7',
            STATE_STORAGE_KEY: 'network_last_known_state',
            INTERVAL: 20000 // 20 Seconds
        };

        async function updateDashboard() {
            console.log("--- Cycle Start ---");
            try {
                const response = await fetch(CONFIG.GO_ENGINE);
                const currentData = await response.json();

                // 1. Load the previous states from Storage (The "Variable" that survives refresh)
                let lastKnownState = JSON.parse(sessionStorage.getItem(CONFIG.STATE_STORAGE_KEY) || "{}");
                let activityLogBuffer = localStorage.getItem(CONFIG.LOG_STORAGE_KEY) || "";

                const targets = document.querySelectorAll('.ping-target');
                let counts = { ilo: 0, primary: 0, free: 0 };

                targets.forEach(el => {
                    const ip = el.dataset.ip.trim();
                    const isFree = (ip === "FREE");

                    // Determine Status
                    let status = isFree ? "free" : (currentData[ip] || 'offline').toLowerCase();
                    let previousStatus = lastKnownState[ip];

                    // Update UI Card
                    el.textContent = status.toUpperCase();
                    el.className = 'status ' + status;

                    // Update Counters
                    if (isFree) {
                        counts.free++;
                    } else if (status === 'offline') {
                        const label = el.closest('.row').querySelector('.label').textContent.toLowerCase();
                        label.includes('ilo') ? counts.ilo++ : counts.primary++;
                    }

                    // CHANGE DETECTION LOGIC
                    // We only log if we have a previous state and it's different
                    if (previousStatus && previousStatus !== status) {
                        console.log(`%c Detected: ${ip} changed from ${previousStatus} to ${status}`, "color: #007aff");

                        // Generate the log HTML
                        const time = new Date().toLocaleTimeString('en-GB', { hour12: false });
                        const color = status === 'online' ? '#34c759' : (status === 'free' ? '#007aff' : '#ff3b30');

                        const newEntry = `
                        <div class="log-item" style="border-right: 4px solid ${color}">
                            <span class="log-time">${time}</span>
                            <div><b>${ip}</b>: ${previousStatus.toUpperCase()} → <span style="color:${color}; font-weight:bold">${status.toUpperCase()}</span></div>
                        </div>`;

                        // Add to the top of our variable
                        activityLogBuffer = newEntry + activityLogBuffer;
                    }

                    // Update the state variable for this IP
                    lastKnownState[ip] = status;
                });

                // 2. Push the Variable content to the Screen
                document.getElementById('logs').innerHTML = activityLogBuffer;

                // 3. Save variables back to storage
                localStorage.setItem(CONFIG.LOG_STORAGE_KEY, activityLogBuffer);
                sessionStorage.setItem(CONFIG.STATE_STORAGE_KEY, JSON.stringify(lastKnownState));

                // Update Header Numbers
                document.getElementById('ilo-offline-count').textContent = counts.ilo;
                document.getElementById('primary-offline-count').textContent = counts.primary;
                document.getElementById('free-count').textContent = counts.free;

            } catch (error) {
                console.error("Critical: Cannot reach Go Engine", error);
            }

            setTimeout(updateDashboard, CONFIG.INTERVAL);
        }

        // This function handles the "Clear" part you asked for
        function clearLogs() {
            if (confirm("Clear all logs?")) {
                // Empty the variable by removing it from storage
                localStorage.removeItem(CONFIG.LOG_STORAGE_KEY);
                // Immediately update the screen
                document.getElementById('logs').innerHTML = "";
                console.log("Log variable cleared.");
            }
        }

        // Start everything
        window.onload = () => {
            const savedLog = localStorage.getItem(CONFIG.LOG_STORAGE_KEY);
            if (savedLog) document.getElementById('logs').innerHTML = savedLog;
            updateDashboard();
        };
    </script>
</body>

</html>