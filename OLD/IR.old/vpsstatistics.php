<?php

    require_once('admin.php');

    $key = 'Hfrwq1PL7HhfWBKIJGq29RCjC4cJgY1lP';
    $pass = 'ldPYRRTzI0pJ08aYnYatmYexj0d4XVxP';
    $ip = '45.129.39.116';
    $port = '4084';


$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

    $post = array();
    $post['vpsid'] = 19211;
    $output = $admin->vps_stats($post);

    print_r(json_encode($output));

?>