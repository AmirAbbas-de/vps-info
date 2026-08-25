<?php
require_once('admin.php');

$key = 'Hfrwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
$pass = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
$ip = '45.129.39.116';
$port = '4084';



$admin = new Virtualizor_Admin_API($ip, $key, $pass ,$port);


$listVPS = $admin->listvs();
$allPlans = $admin->plans();

// 1. Map Plans for easy lookup
$planDetailsMap = [];
$plansData = isset($allPlans['plans']) ? $allPlans['plans'] : [];

foreach ($plansData as $plan) {
    if (isset($plan['plid'])) {
        $planDetailsMap[$plan['plid']] = [
            'pl_plan_name' => $plan['plan_name'],
            'pl_space'     => $plan['space'],
            'pl_ram'       => $plan['ram'],
            'pl_cpu'       => $plan['cpu'],
            'pl_cores'     => $plan['cores']
        ];
    }
}

// 2. Define fields to keep from the VPS entry
$keysToKeep = [
    'vpsid', 'vps_name', 'plid', 'hostname', 'os_name', 
    'space', 'ram', 'cpu', 'cores', 'email', 
    'server_name', 'ips', 'network_speed'
];

$finalData = [];

// 3. Loop through the response (The IDs are the keys in your response)
foreach ($listVPS as $key => $info) {
    // Only process if the key is a VPS ID (numeric) and $info is an array
    if (is_numeric($key) && is_array($info) && isset($info['vpsid'])) {
        
        $filteredVps = [];
        
        // Pick the basic fields
        foreach ($keysToKeep as $field) {
            $filteredVps[$field] = isset($info[$field]) ? $info[$field] : null;
        }
        
        // 4. Attach Plan Info with "pl_" prefix
        $plid = $info['plid'];
        if (isset($planDetailsMap[$plid])) {
            $filteredVps = array_merge($filteredVps, $planDetailsMap[$plid]);
        } else {
            // If plan isn't found, we still add the fields as empty/default
            $filteredVps['pl_plan_name'] = "No Plan Assigned";
            $filteredVps['pl_space'] = $info['space'];
            $filteredVps['pl_ram'] = $info['ram'];
            $filteredVps['pl_cpu'] = $info['cpu'];
            $filteredVps['pl_cores'] = $info['cores'];
        }

        $finalData[$key] = $filteredVps;
    }
}

// 5. Save to file
$jsonOutput = json_encode($finalData, JSON_PRETTY_PRINT);

if (file_put_contents('resource.json', $jsonOutput)) {
    echo "Success! resource.json created with " . count($finalData) . " VPS entries.\n";
} else {
    echo "Error: Could not write file. Check directory permissions.\n";
}



$jsonFile = 'resource.json';
if (!file_exists($jsonFile)) {
    die("Error: resource.json not found.\n");
}

$data = json_decode(file_get_contents($jsonFile), true);
$csvFile = 'mismatches.csv';
$fileHandle = fopen($csvFile, 'w');

// 1. Define Headers for Excel
$headers = [
    'VPS ID', 'VPS Name', 'Hostname', 'Email', 'Server', 'IPs',
    'Active RAM', 'Plan RAM', 
    'Active Space', 'Plan Space', 
    'Active Cores', 'Plan Cores',
    'Plan Name'
];
fputcsv($fileHandle, $headers);

$count = 0;
foreach ($data as $vps) {
    // 2. Logic to detect mismatch
    $hasMismatch = (float)$vps['ram']   != (float)$vps['pl_ram']   ||
                   (float)$vps['space'] != (float)$vps['pl_space'] ||
                   (float)$vps['cores'] != (float)$vps['pl_cores'];

    if ($hasMismatch) {
        // Flatten IPs array to a string for the cell
        $ipString = is_array($vps['ips']) ? implode(', ', $vps['ips']) : '';

        // 3. Map data to columns
        $row = [
            $vps['vpsid'],
            $vps['vps_name'],
            $vps['hostname'],
            $vps['email'],
            $vps['server_name'],
            $ipString,
            $vps['ram'],
            $vps['pl_ram'],
            $vps['space'],
            $vps['pl_space'],
            $vps['cores'],
            $vps['pl_cores'],
            $vps['pl_plan_name']
        ];
        
        fputcsv($fileHandle, $row);
        $count++;
    }
}

fclose($fileHandle);
echo "Done! Created $csvFile with $count mismatches. You can open this file directly in Excel.\n";

?>