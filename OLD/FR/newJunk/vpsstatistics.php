<?php

    require_once('admin.php');

    $key =  'HnTSLTUGMnEJJY7PYuM13fl9s8E9LmQL';
    $pass = 'vM6smyuiVsTxOpMVTzNjk18IS38dxfqv';
    $ip = '46.105.45.161';
    $port = '4084';

$admin = new Virtualizor_Admin_API($ip, $key, $pass, $port);

    $post = array();
    $post['vpsid'] = 19211;
    $output = $admin->vps_stats($post);

    print_r(json_encode($output));

?>