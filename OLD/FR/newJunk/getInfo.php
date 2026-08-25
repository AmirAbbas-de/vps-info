<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';



$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);
$page = 1;
$reslen = 9000;
$vpsid = 21177;

// $test = [
//     'status',
//     'used_cpu',
//     'used_ram',
//     'used_inode',
//     'io_read',
//     'io_write',
//     'ram',
//     'vpsid',
//     'hostname',
//     'os_name',
//     'space',
//     'cpu_cores',
//     'nic_type',
//     'mac',
//     'timezone',
//     'server_name',
//     'email',
//     'ips',
// ];

$listvps = $admin->listvs(1, 9000);
$all_ids = array_keys($listvps);

$total_vps = count($all_ids);
$group_size = ceil($total_vps / 4); 
$chunks = array_chunk($all_ids, $group_size);

$full_status_map = [];

foreach ($chunks as $index => $id_group) {
    $search_ids = implode(',', $id_group);
    
    $stat_response = $admin->status(['vpsid' => $search_ids]);
    
    if (is_array($stat_response)) {
        $full_status_map = $full_status_map + $stat_response;
    }
    
    echo "Group " . ($index + 1) . " processed (" . count($id_group) . " IDs)\n";
}

$all_vps_data = [];

foreach ($listvps as $id => $vps_details) {
    $current_status = $full_status_map[$id] ?? [];

    $all_vps_data[$id] = [
        'status'      => $current_status['status'] ?? 'unknown',
        'used_cpu'    => $current_status['used_cpu'] ?? 0,
        'used_ram'    =>      $current_status['used_ram'] ?? 0,       
        'used_inode'  => $current_status['used_inode'] ?? 0,
        'io_read'     => $current_status['io_read'] ?? 0,
        'io_write'    => $current_status['io_write'] ?? 0,
        'ram'         => $vps_details['ram'] ?? 0,
        'vpsid'       => $id,
        'hostname'    => $vps_details['hostname'] ?? '',
        'os_name'     => $vps_details['os_name'] ?? '',
        'space'       => $vps_details['space'] ?? 0,
        'cpu_cores'   => $vps_details['cores'] ?? 0,
        'nic_type'    => $vps_details['nic_type'] ?? '',
        'mac'         => $vps_details['mac'] ?? '',
        'timezone'    => $vps_details['timezone'] ?? '',
        'server_name' => $vps_details['server_name'] ?? '',
        'email'       => $vps_details['email'] ?? 'no-email',
        'ips'         => isset($vps_details['ips']) && is_array($vps_details['ips']) 
                         ? implode(', ', $vps_details['ips']) 
                         : 'N/A',
    ];
}

$jsonData = json_encode($all_vps_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$filename = 'vps.json';
if (file_put_contents($filename, $jsonData)) {
    echo "Success: Data saved to " . $filename;
} else {
    echo "Error: Could not write to file. Check folder permissions.";
}
?>
