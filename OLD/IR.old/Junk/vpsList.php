<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';


// --- Initialization ---
$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

if (!$admin) {
    die("Error: Unable to connect to Virtualizor API\n");
}

// 1. Fetch initial VPS list (to get all IDs and meta data)
$page = 0;
$reslen = 9000;
$list = $admin->listvs($page, $reslen);

if (empty($list)) {
    die("No VPS found or API error occurred.\n");
}

$vids = array_keys($list);
$vps_meta = [];

// Store static info first
foreach ($list as $vpsid => $vps) {
    $display_ip = "No IP";
    if (isset($vps['ips']) && is_array($vps['ips']) && !empty($vps['ips'])) {
        $display_ip = reset($vps['ips']);
    }

    $vps_meta[$vpsid] = [
        'hostname'    => $vps['hostname'] ?? 'N/A',
        'display_ip'  => $display_ip,
        'os_name'     => $vps['os_name'] ?? 'Unknown',
        'server_name' => $vps['server_name'] ?? 'N/A',
        'email'       => $vps['email'] ?? 'N/A',
        'suspended'   => $vps['suspended'] ?? '0'
    ];
}

// 2. Fetch LIVE status in small chunks (Matches your successful test logic)
$all_live_stats = [];
$chunks = array_chunk($vids, 50); // 50 at a time is the "Sweet Spot" for speed vs stability

echo "Processing " . count($vids) . " servers in " . count($chunks) . " batches...\n";

foreach ($chunks as $index => $chunk) {
    $chunk_stats = $admin->status($chunk);
    if (is_array($chunk_stats)) {
        foreach ($chunk_stats as $vid => $stat) {
            $all_live_stats[$vid] = $stat;
        }
    }
    echo "Batch " . ($index + 1) . " complete...\r";
}
echo "\nAll batches finished.\n";

// 3. Combine Data and Calculate Percentages (Your logic)
$final_data = [];
foreach ($all_live_stats as $vpsid => $v) {
    if (!isset($vps_meta[$vpsid])) continue;
    
    $meta = $vps_meta[$vpsid];
    
    // Limits
    $r_limit = (float)($v['ram'] ?? 0);
    $d_limit = (float)($v['disk'] ?? 0);
    $b_limit = (float)($v['bandwidth'] ?? 0);

    // Percentages
    $cpu_p  = (float)($v['used_cpu'] ?? 0);
    $ram_p  = ($r_limit > 0) ? (($v['used_ram'] ?? 0) / $r_limit) * 100 : 0;
    $disk_p = ($d_limit > 0) ? (($v['used_disk'] ?? 0) / $d_limit) * 100 : 0;
    $bw_p   = ($b_limit > 0) ? (($v['used_bandwidth'] ?? 0) / $b_limit) * 100 : 0;

    $total_score = ($cpu_p + $ram_p + $disk_p + $bw_p) / 4;

    $final_data[$vpsid] = [
        'vpsid'          => $vpsid,
        'hostname'       => $meta['hostname'],
        'ip'             => $meta['display_ip'],
        'os'             => $meta['os_name'],
        'node'           => $meta['server_name'],
        'email'          => $meta['email'],
        'suspended'      => $meta['suspended'],
        'status'         => $v['status'] ?? 0,
        
        // Live Numbers
        'used_cpu'       => $cpu_p,
        'used_ram'       => $v['used_ram'] ?? 0,
        'ram_limit'      => $r_limit,
        'used_disk'      => $v['used_disk'] ?? 0,
        'disk_limit'     => $d_limit,
        'used_bandwidth' => $v['used_bandwidth'] ?? 0,
        'bw_limit'       => $b_limit,
        
        // Extras
        'net_in'         => $v['net_in'] ?? 0,
        'net_out'        => $v['net_out'] ?? 0,
        'io_read'        => $v['io_read'] ?? 0,
        'io_write'       => $v['io_write'] ?? 0,
        
        // Calculated Percentages
        'cpu_percent'    => round($cpu_p, 2),
        'ram_percent'    => round($ram_p, 2),
        'disk_percent'   => round($disk_p, 2),
        'total_percent'  => round($total_score, 2)
    ];
}

// 4. Save to JSON
$filename = date('Y-m-d') . "-list.json";
file_put_contents($filename, json_encode($final_data, JSON_PRETTY_PRINT));

echo "Success! Data saved to $filename\n";
?>