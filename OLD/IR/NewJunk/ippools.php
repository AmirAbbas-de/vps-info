<?php
require_once('admin.php');

$key = 'Hfrwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
$pass = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
$ip = '45.129.39.116';
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
    'RS-'    => 'Respina - Emam',
    'HIWEB-'  => 'Hiweb',
    'RSPN-' => 'Respina - Emam',
    'AF' => 'Afranet',
    'HiWeb' => 'Hiweb',
    'EMAM' => 'Respina - Emam'
];

$output = $admin->ippool($page, $reslen);
$ippools = $output['ippools'] ?? [];
$grouped_data = [];

foreach ($ippools as $id => $pool) {
    $name = $pool['ippool_name'];
    
    $gateway = $pool['gateway'];
    $netmask = $pool['netmask'];

    if (strpos($name, 'RSPNPrivate-2a0e:4a40::0/64') !== false || strpos($gateway, ':') !== false) {
        continue;
    }
    
    if (strpos($name, 'IPv6-2a0a:2fc4:0:3fa::/64') !== false || strpos($gateway, ':') !== false) {
        continue;
    }

    if (strpos($name, 'RS-2a0e:4a40::0/64') !== false || strpos($gateway, ':') !== false) {
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