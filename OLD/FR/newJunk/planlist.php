<?php

    require_once('admin.php');

    $key =  'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
    $pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
    $ip = '46.105.45.161';
    $port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

$page = 1;
$reslen = 1; 

$post = array();

$post['planname'] = ''; 
$post['ptype'] = 'kvm'; 

$output = $admin->plans($page, $reslen, $post);

if (!empty($output['plans'])) {
    echo json_encode($output['plans'], JSON_PRETTY_PRINT);
} else {
    echo json_encode(["message" => "No plans found or error in connection", "raw" => $output]);
}

?>