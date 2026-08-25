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
    <link rel="stylesheet" href="../../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../../src/css/tom-select.min.css">
    <link rel="stylesheet" href="../../../../src/css/dasboard.css">
    <link rel="stylesheet" href="../../../../src/fonts/css/all.min.css">
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
    <?php include('../../../layouts/index.php') ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4"><?= $serverInfo['server_type'] ?></h5>
            <div class="row">
                <div class="col-md-6">

                    <ul class="list-group">
                        <li class="list-group-item"><strong>IP:</strong> <?php echo htmlspecialchars($serverInfo['ip']); ?></li>
                        <li class="list-group-item"><strong>Model:</strong> <?php echo htmlspecialchars($serverInfo['server_type']); ?></li>
                        <li class="list-group-item"><strong>Serial Number:</strong> <?php echo htmlspecialchars($serverInfo['server_serial']); ?></li>
                        <li class="list-group-item"><strong>CPU:</strong> <?php echo htmlspecialchars($serverInfo['cpu']); ?></li>
                        <li class="list-group-item"><strong>iLO VLAN:</strong> <?php echo htmlspecialchars($serverInfo['ilo_vlan']); ?></li>
                        <li class="list-group-item"><strong>iLO Switch:</strong> <?php echo htmlspecialchars($serverInfo['ilo_switch']); ?></li>
                        <li class="list-group-item"><strong>iLO Port:</strong> <?php echo htmlspecialchars($serverInfo['ilo_port']); ?></li>
                        <li class="list-group-item"><strong>iLO MAC:</strong> <?php echo htmlspecialchars($serverInfo['ilo_mac']); ?></li>
                    </ul>

                </div>
            </div>
        </div>
    </div>
    <script src="../../../../src/js/bootstrap.bundle.min.js"></script>
</body>

</html>