<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';

$test_vps_id = '20282'; 

$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

if (!$admin) {
    die("Error: Unable to connect to Virtualizor API\n");
}

echo "--- Testing Live Stats for VPS ID: $test_vps_id ---\n";

// 1. Fetch Status (Live Usage)
// We pass the ID in an array because the status() method expects a list
$status_result = $admin->status([$test_vps_id]);

// 2. Fetch Detailed Info (Configuration Limits)
// This is used to get the RAM/Disk limits to calculate percentages
$list_result = $admin->listvs(0, 1, ['vpsid' => $test_vps_id]);

// 3. Extract Data
$v = $status_result[$test_vps_id] ?? null;
$config = $list_result[$test_vps_id] ?? null;

if (!$v) {
    die("Error: Could not retrieve status for VPS $test_vps_id. Check if API credentials have sufficient permissions.\n");
}

// 4. Statistics Logic
$ram_limit = (float)($v['ram'] ?? 0);
$ram_used  = (float)($v['used_ram'] ?? 0);
$ram_percent = ($ram_limit > 0) ? ($ram_used / $ram_limit) * 100 : 0;

$disk_limit = (float)($v['disk'] ?? 0);
$disk_used  = (float)($v['used_disk'] ?? 0);
$disk_percent = ($disk_limit > 0) ? ($disk_used / $disk_limit) * 100 : 0;

$cpu_used = (float)($v['used_cpu'] ?? 0);

// 5. Display Results
echo "Status: " . ($v['status'] == 1 ? "ONLINE" : "OFFLINE") . "\n";
echo "Hostname: " . ($config['hostname'] ?? 'Unknown') . "\n";
echo "---------------------------------\n";
echo "CPU Usage:    " . $cpu_used . "%\n";
echo "RAM Usage:    " . $ram_used . " MB / " . $ram_limit . " MB (" . round($ram_percent, 2) . "%)\n";
echo "Disk Usage:   " . $disk_used . " GB / " . $disk_limit . " GB (" . round($disk_percent, 2) . "%)\n";
echo "Bandwidth:    " . ($v['used_bandwidth'] ?? 0) . " GB\n";
echo "Network In:   " . ($v['net_in'] ?? '0') . "\n";
echo "Network Out:  " . ($v['net_out'] ?? '0') . "\n";
echo "IO Read:      " . ($v['io_read'] ?? '0') . "\n";
echo "IO Write:     " . ($v['io_write'] ?? '0') . "\n";
echo "---------------------------------\n";

// If you want to see every single raw key available:
// print_r($v); 
?>