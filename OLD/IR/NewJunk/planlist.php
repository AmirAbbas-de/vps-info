<?php

    require_once('admin.php');

    $key = 'Hfrwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
    $pass = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
    $ip = '45.129.39.116';
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