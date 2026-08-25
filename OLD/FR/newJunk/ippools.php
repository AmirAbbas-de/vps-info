<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);
$page = 1;
$reslen = 9000;

function maskToCidr($mask) {
    if (strpos($mask, '.') === false) return $mask;
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}


$group_mapping = [
    'EQ'            => 'EQ-L4 - Turkey',
    '45.95.65.'     => 'EQ-L4 - Turkey',
    '181.214.115.' => 'M247 - UAE',
    '181.41.216.' => 'M247 - UAE',
    '185.135.156' => 'M247 - UAE',
    '194.110.242.' => 'M247 - UAE',
    '181.214.140'   => 'Redstation - Netherland',
    '81.161.229'    => 'Redstation - Netherland',
    '185.179.216'   => 'Redstation - England',
    '213.137.72'    => 'Redstation - England',
    '185.214.101'   => 'Redstation - Spin',
    'TR'           => 'MUV - Turkey',
    'FR3' => 'OVH - France',
    'FR-152.' => 'OVH - France',
    'FR-146.' => 'OVH - France',
    '151.80.171' => 'OVH - France',
    'FR-149.202' => 'OVH - France',
    '176.31.219' => 'OVH - France',
    'CA571823SA' => 'OVH - Canada',
    '66.70.234' => 'OVH - Canada',
    '191.101.113' => 'HostKEY - Netherland',
    'PM-158.' => 'Payam - UAE',
    'PM-185' => 'Payam - UAE',
    '81.22.134' => 'HostKEY - Netherland',
    '46.183.31' => 'HostKEY - Netherland',
    '141.11.0.' => 'HostKEY - Netherland',
    '213.139.72' => 'Redstation - England',
    '185.45.252' => 'Redstation - France',
    'RDST-2001:1b40:5000' => 'Redstation - V6',
    '163.5.94' => 'Redstation - Germany',
    '188.209.138' => 'HostKEY - USA',
    '217.138.162' => 'M247 - UAE',
    '81.168.119' => 'Redstation - England',
    'CA5' => 'OVH - Canada',
    '91.239.211' => 'HostKEY - Germany',
    '82.22.175' => 'HostKEY - Germany',
    '84.245.19' => 'HostKEY - Netherland'
];

$output = $admin->ippool($page, $reslen);
$ippools = $output['ippools'] ?? [];
$grouped_data = [];

foreach ($ippools as $id => $pool) {
    $name = $pool['ippool_name'];
    
    $gateway = $pool['gateway'];
    $netmask = $pool['netmask'];

    if (strpos($name, 'RDST-2001:1b40:5000') !== false || strpos($gateway, ':') !== false) {
        continue;
    }
    
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

file_put_contents('ippools.json', json_encode($grouped_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
?>