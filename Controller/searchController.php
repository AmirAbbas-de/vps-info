<?php

require_once '/var/www/html/Database.php';


$search = $_GET['search'] ?? '';
if (empty($search)) {
    die("لطفاً عبارت جستجو را وارد کنید.");
}

$servername = "192.168.100.78";
$username = "ilo_user";
$password = "123456";
$dbname = "whmcs";
$db = new Database($servername, $dbname, $username, $password);

try {
    // IP
    if(filter_var($search, FILTER_VALIDATE_IP)){
        $results = $db->select("SELECT ip FROM server_details WHERE ip = ?", [$search]);
        echo "<pre>";
        $showIp = [];
        foreach($results as $ips){
            $showIp[] = $ips['ip'];
        }
        $uniqueIp = array_unique($showIp);
        $search = $uniqueIp[0];
        header("Location:https://192.168.100.88/views/pages/dedidashboard/index.php?ip=$search");
    }
} catch (Exception $e) {
    echo $e;
    die();
}
