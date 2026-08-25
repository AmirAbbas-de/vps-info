<?php
require_once('admin.php');

$key = 'Hfrwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
$pass = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
$ip = '45.129.39.116';
$port = '4084';


// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('memory_limit', '512M');
set_time_limit(120); 

// Make sure the Virtualizor library is included
// require_once('path/to/Virtualizor_Admin_API.php');

$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

if (!is_object($admin)) {
    die("Failed to initialize API\n");
}

// 1. Get the main list - use try-catch to handle API errors
try {
    $list_res = $admin->listvs(1, 9999);
    
    // Debug: Check API response structure
    if (empty($list_res)) {
        die("Empty response from listvs API\n");
    }
    
    // Check for different possible response structures
    if (isset($list_res['vs']) && is_array($list_res['vs'])) {
        $list = $list_res['vs'];
    } elseif (isset($list_res['vps']) && is_array($list_res['vps'])) {
        $list = $list_res['vps'];
    } elseif (isset($list_res['vpslist']) && is_array($list_res['vpslist'])) {
        $list = $list_res['vpslist'];
    } else {
        // Sometimes the response is already the list
        $list = is_array($list_res) ? $list_res : [];
    }
    
    if (empty($list)) {
        die("No VPS found in listvs. Response structure: " . json_encode($list_res) . "\n");
    }
    
    echo "Found " . count($list) . " VPS in initial list\n";
    
} catch (Exception $e) {
    die("Error getting VPS list: " . $e->getMessage() . "\n");
}

// 2. Get status - handle errors properly
$vids = array_keys($list);
$status_data = [];

if (!empty($vids)) {
    try {
        // Try sending all IDs at once
        $status_res = $admin->status($vids);
        
        // Check response structure
        if (is_array($status_res) && !empty($status_res)) {
            $status_data = $status_res['info'] ?? $status_res;
        } else {
            echo "Warning: Status API returned empty or invalid response\n";
            $status_data = [];
        }
        
    } catch (Exception $e) {
        echo "Warning: Could not fetch status in batch: " . $e->getMessage() . "\n";
        
        // Fallback: Try getting status for each VPS individually
        echo "Trying individual status requests...\n";
        $status_data = [];
        
        foreach ($vids as $vid) {
            try {
                $single_status = $admin->status($vid);
                if (!empty($single_status) && is_array($single_status)) {
                    $status_data[$vid] = $single_status['info'] ?? $single_status;
                }
            } catch (Exception $e2) {
                echo "Warning: Could not get status for VPS {$vid}: " . $e2->getMessage() . "\n";
                $status_data[$vid] = [];
            }
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 second
        }
    }
}

echo "Processing " . count($list) . " VPS...\n";

$final_data = [];

foreach ($list as $vpsid => $vps) {
    // Ensure vpsid is numeric/string
    $vpsid = (string)$vpsid;
    
    // Get stats for this VPS
    $stats = $status_data[$vpsid] ?? [];
    
    // Debug individual VPS if needed
    // if (empty($stats)) {
    //     echo "No status data for VPS {$vpsid}\n";
    // }
    
    // Get IP address - handle different possible structures
    $display_ip = "No IP";
    if (!empty($vps['ips'])) {
        if (is_array($vps['ips'])) {
            $ips = is_array($vps['ips']) ? array_values($vps['ips']) : [$vps['ips']];
            $display_ip = !empty($ips) ? (string)$ips[0] : "No IP";
        } else {
            $display_ip = (string)$vps['ips'];
        }
    } elseif (!empty($vps['primary_ip'])) {
        $display_ip = (string)$vps['primary_ip'];
    }
    
    // Usage Data - Prioritize status API, fallback to listvs
    $u_cpu   = (float)($stats['used_cpu'] ?? $stats['cpu_percent'] ?? $vps['cpu_percent'] ?? $vps['used_cpu'] ?? 0);
    $u_ram   = (float)($stats['used_ram'] ?? $vps['used_ram'] ?? 0);
    $u_disk  = (float)($stats['used_disk'] ?? $vps['used_space'] ?? 0);
    $u_bw    = (float)($stats['used_bandwidth'] ?? $vps['used_bandwidth'] ?? 0);
    
    // Limits
    $l_ram   = (float)($vps['ram'] ?? $vps['memory'] ?? $stats['ram'] ?? 0);
    $l_disk  = (float)($vps['space'] ?? $vps['disk'] ?? $stats['disk'] ?? 0);
    $l_bw    = (float)($vps['bandwidth'] ?? $stats['bandwidth'] ?? 0);
    
    // Ensure we have valid numbers
    $u_cpu = max(0, $u_cpu);
    $u_ram = max(0, $u_ram);
    $u_disk = max(0, $u_disk);
    $u_bw = max(0, $u_bw);
    $l_ram = max(0, $l_ram);
    $l_disk = max(0, $l_disk);
    $l_bw = max(0, $l_bw);
    
    // Percentages with safety checks
    $ram_p  = ($l_ram > 0)  ? min(100, ($u_ram / $l_ram) * 100)  : 0;
    $disk_p = ($l_disk > 0) ? min(100, ($u_disk / $l_disk) * 100) : 0;
    $bw_p   = ($l_bw > 0)   ? min(100, ($u_bw / $l_bw) * 100)     : 0;
    
    // Calculate total percentage (average of all metrics)
    $total_p = 0;
    $metrics_count = 0;
    
    $u_cpu_percent = min(100, $u_cpu); // CPU is already a percentage
    $total_p += $u_cpu_percent;
    $metrics_count++;
    
    $total_p += $ram_p;
    $metrics_count++;
    
    $total_p += $disk_p;
    $metrics_count++;
    
    $total_p += $bw_p;
    $metrics_count++;
    
    $total_p = $metrics_count > 0 ? $total_p / $metrics_count : 0;
    
    $status = 'unknown';
    if (isset($stats['status'])) {
        $status = ($stats['status'] == 1) ? 'online' : 'offline';
    } elseif (isset($vps['status'])) {
        $status = ($vps['status'] == 1) ? 'online' : 'offline';
    }
    
    $final_data[$vpsid] = [
        'vpsid'          => $vpsid,
        'hostname'       => $vps['hostname'] ?? 'N/A',
        'ip'             => $display_ip,
        'os'             => $vps['os_name'] ?? $vps['ostemplate'] ?? 'Unknown',
        'node'           => $vps['server_name'] ?? $vps['servername'] ?? 'N/A',
        'status'         => $status,
        'used_cpu'       => round($u_cpu_percent, 2),
        'used_ram'       => round($u_ram, 2),
        'ram_limit'      => round($l_ram, 2),
        'ram_percent'    => round($ram_p, 2),
        'used_disk'      => round($u_disk, 2),
        'disk_limit'     => round($l_disk, 2),
        'disk_percent'   => round($disk_p, 2),
        'used_bandwidth' => round($u_bw, 2),
        'bw_limit'       => round($l_bw, 2),
        'disk_read'      => round($stats['io_read'] ?? $stats['read'] ?? 0, 2),
        'disk_write'     => round($stats['io_write'] ?? $stats['write'] ?? 0, 2),
        'used_inode'     => round($stats['used_inode'] ?? 0, 2),
        'net_in'         => round($stats['net_in'] ?? 0, 2),
        'net_out'        => round($stats['net_out'] ?? 0, 2),
        'total_percent'  => round($total_p, 2),
        'last_updated'   => date('Y-m-d H:i:s')
    ];
}

// Save to file with error checking
$filename = "vps.json";
$json_data = json_encode($final_data, JSON_PRETTY_PRINT);

if ($json_data === false) {
    die("Error encoding JSON data: " . json_last_error_msg() . "\n");
}

$result = file_put_contents($filename, $json_data);

if ($result === false) {
    die("Error writing to file {$filename}. Check permissions.\n");
}

echo "Success! Data for " . count($final_data) . " VPS saved to {$filename}\n";

// Optional: Display summary
$online_count = count(array_filter($final_data, function($vps) {
    return $vps['status'] === 'online';
}));

echo "Online VPS: {$online_count} / " . count($final_data) . "\n";
?>