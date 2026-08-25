<?php
require_once('admin.php');

$key = 'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
$pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
$ip = '46.105.45.161';
$port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass ,$port);



$listVPS = $admin->listvs();

if (empty($listVPS)) {
    die("CRITICAL: The API returned a totally empty response. Check Firewall/IP Whitelist.");
}

if (isset($listVPS['error'])) {
    echo "API ERROR: ";
    print_r($listVPS['error']);
    die();
}

// This will show us the actual keys available
echo "Available keys in response: " . implode(', ', array_keys($listVPS)) . "\n";

if (!isset($listVPS['vs'])) {
    echo "DEBUG: 'vs' key not found. Printing full response for inspection:\n";
    #print_r($listVPS);
    die();
}

?>