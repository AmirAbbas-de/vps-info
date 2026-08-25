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

    // $ServerLogInfo = $db->select("SELECT * FROM server_log WHERE server_details_id = ?", [$server]);
    // echo "<pre>";
    // print_r($serverInfo);
    // echo "</pre>";
    // die();

    // $ServerRaid = $db->select("SELECT * FROM server_logical_drive WHERE server_details_id = ?", [$server]);
    // echo "<pre>";
    // print_r($ServerRaid);
    // echo "</pre>";
    // // die();
    // $ServerMac = $db->select("SELECT * FROM server_mac WHERE server_details_id = ?", [$server]);
    // // echo "<pre>";
    // // print_r($ServerMac);
    // // echo "</pre>";

    // foreach ($ServerMac as $row) {
    //     $id = $row['id'];
    //     $ServerUesAbleIp = $db->select("SELECT * FROM server_usable_ips WHERE server_mac_id = ?", [$id]);
    //     foreach ($ServerUesAbleIp as $ServerIp) {
    //         if ($ServerIp['status'] == 'connect') {
    //             $json = json_decode($ServerIp['arp'], true);
    //             $mac_address = strval($json['primary_mac']);
    //             $SwMacInfo = $db->select("SELECT vlan,port_swild FROM sw_mac_info WHERE mac_address = ?", [$mac_address]);

    //             foreach($SwMacInfo as $info){
    //                 $vlan = $info['vlan'];
    //                 $RouterVlanInfo = $db->select("SELECT customer_name,router_name,subnet FROM router_vlan_info WHERE vlan_id = ?", [$vlan]);
    //                 echo "<pre>";
    //                 print_r($RouterVlanInfo);
    //                 echo "</pre>";  
    //             }
    //         }
    //     }
    // }

    // $ServerHard = $db->select("SELECT * FROM server_physical_drive WHERE server_details_id = ?", [$server]);
    // echo "<pre>";
    // print_r($ServerHard);
    // echo "</pre>";


    // $ServerPower = $db->select("SELECT * FROM server_power WHERE server_details_id = ?", [$server]);
    // echo "<pre>";
    // print_r($ServerPower);
    // echo "</pre>";


    // $ServerRAM = $db->select("SELECT * FROM server_memory_detail WHERE server_details_id = ?", [$server]);
    // echo "<pre>";
    // print_r($ServerRAM);
    // echo "</pre>";

    // die();

} catch (PDOException $e) {
    die("خطا در اتصال: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <title>HPE ProLiant DL360 Gen10 - Digital Twin</title>

    <link rel="stylesheet" href="/src/css/bootstrap.min.css">
    <link rel="stylesheet" href="/src/css/tom-select.min.css">
    <link rel="stylesheet" href="/src/css/dasboard.css">
    <link rel="stylesheet" href="/src/fonts/css/all.min.css">
    <style>
        :root {
            --hpe-silver: #a7a9ac;
            --hpe-green: #01a982;
            --hpe-amber: #ff8c00;
            --chassis-dark: #1a1a1a;
            --chassis-metal: #2d2d2d;
            --led-glow: 0 0 8px rgba(1, 169, 130, 0.8);
        }

        body {
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px;
        }

        .background {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 0;
            inset: 0;
            background: linear-gradient(to right, rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, linear-gradient(rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, radial-gradient(500px at 20% 80%, rgb(14 14 14 / 30%), transparent) 0% 0% / 100% 100%, radial-gradient(500px at 80% 20%, rgb(0 0 0 / 30%), transparent) 0% 0% / 100% 100% rgb(255, 255, 255);
        }

        /* Container & Controls */
        .server-wrapper {
            width: 1000px;
            perspective: 1000px;
        }

        .view-controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        button {
            
            border-radius: 10px;
            color: #555;
            border: 1px solid #555;
            padding: 8px 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        button.active {
            
        }

        /* Main Chassis */
        .chassis {
            width: 100%;
            height: 120px;
            /* 1U Scale */
            background: linear-gradient(180deg, #333 0%, #1a1a1a 100%);
            border: 2px solid #444;
            border-radius: 4px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            display: none;
            overflow: hidden;
        }

        .chassis.active {
            display: flex;
        }

        /* FRONT VIEW */
        #front-view {
            padding: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .drive-array {
            display: flex;
            gap: 4px;
            height: 100%;
        }

        .drive-bay {
            width: 75px;
            height: 100%;
            background: #222;
            border: 1px solid #333;
            border-radius: 2px;
            position: relative;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.2s;
        }

        .drive-bay:hover {
            box-shadow: inset 0 0 10px var(--hpe-green);
            transform: scale(1.02);
            z-index: 10;
        }

        .drive-bay.selected {
            border-color: var(--hpe-green);
            background: #2a2a2a;
        }

        .latch {
            position: absolute;
            bottom: 10px;
            left: 5px;
            right: 5px;
            height: 20px;
            background: #333;
            border-radius: 1px;
        }

        .led {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            position: absolute;
            top: 10px;
            right: 10px;
            background: #111;
        }

        .led.active {
            background: var(--hpe-green);
            box-shadow: var(--led-glow);
        }

        .front-panel-right {
            width: 180px;
            height: 100%;
            border-left: 2px solid #444;
            padding-left: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
        }

        .power-btn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #444;
            border: 1px solid #666;
            cursor: pointer;
        }

        .power-btn.on {
            background: var(--hpe-green);
            box-shadow: var(--led-glow);
        }

        /* REAR VIEW */
        #rear-view {
            padding: 10px;
            align-items: center;
            gap: 20px;
        }

        .psu-section {
            display: flex;
            gap: 5px;
        }

        .psu {
            width: 120px;
            height: 90px;
            background: #222;
            border: 2px solid #444;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .psu:hover {
            border-color: var(--hpe-green);
        }

        .port-group {
            display: flex;
            gap: 8px;
            flex-grow: 1;
            align-items: center;
        }

        .port {
            background: #111;
            border: 1px solid #555;
            padding: 5px;
            font-size: 8px;
            text-align: center;
            cursor: pointer;
        }

        .vga {
            width: 40px;
            height: 25px;
            background: #004a99;
        }

        /* INTERNAL VIEW */
        #internal-view {
            height: 500px;
            flex-direction: column;
            padding: 20px;
            background: #252525;
        }

        .mobo {
            width: 100%;
            height: 100%;
            background: #1e3a2a;
            /* Dark green PCB */
            border-radius: 5px;
            position: relative;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .cpu-socket {
            width: 120px;
            height: 120px;
            background: #aaa;
            border: 4px solid #777;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #333;
        }

        .ram-bank {
            display: flex;
            gap: 2px;
        }

        .dimm {
            width: 8px;
            height: 100px;
            background: #444;
            border: 1px solid #222;
            cursor: pointer;
        }

        .dimm:hover {
            background: var(--hpe-green);
        }

        .dimm.selected {
            background: #fff;
        }

        /* Sidebar Info */
        .info-panel {
            position: fixed;
            right: 20px;
            top: 100px;
            width: 250px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid var(--hpe-green);
            padding: 20px;
            border-radius: 8px;
        }

        .label-tag {
            font-size: 10px;
            color: var(--hpe-green);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="background"></div>
    <?php include('../../layouts/index.php') ?>

    <div class="position-relative text-center mt-5">
        <h1 class="text-muted "><?= $serverInfo['server_type'] ?></h1>

        <div class="view-controls position-relative">
            <button onclick="switchView('front')" id="btn-front" class="active">Front </button>
            <button onclick="switchView('rear')" id="btn-rear">Rear </button>
            <button onclick="switchView('internal')" id="btn-internal">Internal </button>
        </div>

        <div class="server-wrapper">
            <div id="front-view" class="chassis active">
                <div class="drive-array" id="drive-container">
                </div>
                <div class="front-panel-right">
                    <div class="power-btn on" onclick="this.classList.toggle('on')" title="Power Button"></div>
                    <div class="led active" style="position:relative; top:0; right:0;" title="UID LED"></div>
                    <div class="port" style="width:40px">USB 3.0</div>
                    <div class="label-tag">DL360 Gen10</div>
                </div>
            </div>

            <div id="rear-view" class="chassis">
                <div class="psu-section">
                    <div class="psu" onclick="selectComp('PSU 1 (800W Flex Slot)')">PSU 1</div>
                    <div class="psu" onclick="selectComp('PSU 2 (800W Flex Slot)')">PSU 2</div>
                </div>
                <div class="port-group">
                    <div class="port vga" onclick="selectComp('VGA Port')">VGA</div>
                    <div class="port" onclick="selectComp('iLO Management Port')">iLO</div>
                    <div class="port" onclick="selectComp('NIC Port 1')">1GbE 1</div>
                    <div class="port" onclick="selectComp('NIC Port 2')">1GbE 2</div>
                </div>
                <div class="port" style="width:100px; height:60px" onclick="selectComp('PCIe Riser Slot')">PCIe Gen3 x16</div>
            </div>

            <div id="internal-view" class="chassis">
                <div class="mobo">
                    <div class="ram-bank" id="ram-bank-1"></div>
                    <div class="cpu-socket" onclick="selectComp('Intel Xeon Scalable CPU 1')">CPU 1</div>
                    <div class="cpu-socket" onclick="selectComp('Intel Xeon Scalable CPU 2')">CPU 2</div>
                    <div class="ram-bank" id="ram-bank-2"></div>
                </div>
            </div>
        </div>

        <div class="info-panel" id="info-panel">
            <h3 id="comp-name">Component Details</h3>
            <p id="comp-desc">Hover or click a hardware component to see technical specifications.</p>
            <hr style="border: 0.5px solid #444">
            <div id="selection-list">
                <strong>Selected:</strong>
                <ul id="selected-items" style="font-size: 12px; padding-left: 20px;"></ul>
            </div>
        </div>
    </div>

    <script>
        // Initialize Drives
        const driveContainer = document.getElementById('drive-container');
        for (let i = 1; i <= 8; i++) {
            const drive = document.createElement('div');
            drive.className = 'drive-bay';
            drive.innerHTML = `<div class="led active"></div><div class="latch"></div><div style="font-size:8px; margin-top:25px; text-align:center">SFF ${i}</div>`;
            drive.onclick = () => selectComp(`Drive Bay ${i}: 1.2TB SAS 10K SFF`);
            driveContainer.appendChild(drive);
        }

        // Initialize RAM
        const createRam = (containerId) => {
            const container = document.getElementById(containerId);
            for (let i = 1; i <= 12; i++) {
                const dimm = document.createElement('div');
                dimm.className = 'dimm';
                dimm.onclick = () => selectComp(`DIMM Slot ${i}: 32GB Dual Rank x4 DDR4-2933`);
                container.appendChild(dimm);
            }
        }
        createRam('ram-bank-1');
        createRam('ram-bank-2');

        const selectedItems = new Set();

        function switchView(view) {
            document.querySelectorAll('.chassis').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.view-controls button').forEach(el => el.classList.remove('active'));

            document.getElementById(`${view}-view`).classList.add('active');
            document.getElementById(`btn-${view}`).classList.add('active');
        }

        function selectComp(name) {
            const nameEl = document.getElementById('comp-name');
            const descEl = document.getElementById('comp-desc');
            const listEl = document.getElementById('selected-items');

            nameEl.innerText = name;
            descEl.innerText = `Hardware ID: HPE_CONF_${Math.floor(Math.random()*10000)}\nStatus: Operational\nTemperature: 34°C`;

            if (selectedItems.has(name)) {
                selectedItems.delete(name);
            } else {
                selectedItems.add(name);
            }

            listEl.innerHTML = '';
            selectedItems.forEach(item => {
                const li = document.createElement('li');
                li.innerText = item;
                listEl.appendChild(li);
            });
        }
    </script>

    <script src="/src/js/bootstrap.bundle.min.js"></script>

</body>

</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

</head>

<body>


</body>

</html>