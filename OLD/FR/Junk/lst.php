<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);
$page = 0;
$reslen = 9000;


function maskToCidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}


$group_mapping = [
    'EQ'            => 'EQ-L4 - Turkey',
    '45.95.65.'     => 'Payam - Turkey',   
    '181.214.140'   => 'Redstation - Netherland',
    '81.161.229'    => 'Redstation - Netherland',
    '185.179.216'   => 'Redstation - England',
    '213.137.72'    => 'Redstation - England',
    '185.214.101'   => 'Redstation - Spin',
    'TR'           => 'MUV - Turkey',
    'FR3193224AN' => 'OVH - France',
    'CA571823SA' => 'OVH - Canada'
];

$output = $admin->ippool($page, $reslen);
$ippools = $output['ippools'] ?? [];
$grouped_data = [];

foreach ($ippools as $id => $pool) {
    $name = $pool['ippool_name'];
    $gateway = $pool['gateway'];
    $netmask = $pool['netmask'];
    
    $subnet_ip = long2ip(ip2long($gateway) - 1);
    $cidr = maskToCidr($netmask);
    $subnet_field = $subnet_ip . "/" . $cidr;

    $final_group = "Other"; 
    foreach ($group_mapping as $identifier => $assigned_name) {
        if (strpos($name, $identifier) !== false) {
            $final_group = $assigned_name;
            break; 
        }
    }

    $grouped_data[$final_group][] = [
        'ippid'    => $pool['ippid'],
        'name'     => $name,
        'subnet'   => $subnet_field,
        'gateway'  => $gateway,
        'netmask'  => $netmask,
        'total_ip' => $pool['totalip'] ?? 0,
        'free_ip'  => $pool['unassignedip'] ?? 0
    ];
}

file_put_contents('ip_location_grouped.json', json_encode($grouped_data, JSON_PRETTY_PRINT));
echo "Successfully grouped " . count($ippools) . " pools into " . count($grouped_data) . " categories.";
?>