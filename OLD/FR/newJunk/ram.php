<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass ,$port);
// Output file
$output_file = 'virtualizor_servers_info_' . date('Y-m-d_His') . '.json';


$output = $admin->listservers();

if (empty($output['servers']) || !is_array($output['servers'])) {
    die("Error: Could not fetch servers list\n");
}


$servers_data = [];

foreach ($output['servers'] as $serid => $s) {
    $res = json_decode($s['data'] ?? '{}', true);
    $resource = $res['resource'] ?? [];

    $server_info = [
        'serid'            => (int)$serid,
        'server_name'      => $s['server_name'] ?? null,
        'ip'               => $s['ip'] ?? null,
        'total_ram_mb'     => (int)($s['total_ram'] ?? 0),
        'overcommit'       => $s['overcommit'] ?? null,
        'available_ram_mb' => (int)($s['ram'] ?? 0),
        'free_space_gb'    => (int)($s['space'] ?? 0),
        'os'               => $s['os'] ?? null,
        'virtualizor_version' => $s['version'] ?? null,
        'patch'            => $s['patch'] ?? null,
        'license_expires'  => $s['lic_expires'] ?? null,
        
        'locked'           => !empty($s['locked']) 
            ? (is_array($s['locked']) 
                ? [
                    'status' => true,
                    'time'   => $s['locked']['time'] ?? null,
                    'reason' => $s['locked']['reason'] ?? null
                  ] 
                : ['status' => (bool)$s['locked']])
            : ['status' => false],
        
        'status'           => (int)$s['status'],
        'last_reverse_sync'=> $s['last_reverse_sync'] ? date('Y-m-d H:i:s', $s['last_reverse_sync']) : null,
        'sys_load'         => $s['sys_load'] ?? null,
        
        'resource_usage'   => [
            'disk' => [
                'used_percent'  => $resource['disk']['percent'] ?? null,
                'free_percent'  => $resource['disk']['percent_free'] ?? null,
            ],
            'cpu' => [
                'used_percent'  => $resource['cpu']['percent'] ?? null,
                'free_percent'  => $resource['cpu']['percent_free'] ?? null,
            ],
            'ram' => [
                'used_percent'  => $resource['ram']['percent'] ?? null,
                'free_percent'  => $resource['ram']['percent_free'] ?? null,
            ],
            'uptime'           => $resource['uptime'] ?? null,
        ],
        
        'vps' => [
            'running_count'    => (int)($s['numvps'] ?? 0),
            'allocated_ram_mb' => (int)($s['alloc_ram'] ?? 0),
            'allocated_space_gb'=> (int)($s['alloc_space'] ?? 0),
            'allocated_vcpus'  => (int)($s['alloc_cpu'] ?? 0),
            'total_vcores'     => (int)($s['vcores'] ?? 0),
            'alloc_cpu_percent'=> $s['alloc_cpu_percent'] ?? null,
            'alloc_bandwidth'  => $s['alloc_bandwidth'] ?? null,
        ]
    ];

    $servers_data[] = $server_info;
}

$json_content = json_encode($servers_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (file_put_contents($output_file, $json_content) === false) {
    die("Error: Could not write to file: $output_file\n");
}

echo "Success!\n";
echo "File saved: $output_file\n";
echo "Total servers saved: " . count($servers_data) . "\n";

?>