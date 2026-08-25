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