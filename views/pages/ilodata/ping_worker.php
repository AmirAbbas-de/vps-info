<?php
session_start();
header('Content-Type: application/json');

$ip = $_GET['ip'] ?? '';
$cache_duration = 10; // مدت زمان کش به ثانیه

if (empty($ip)) {
    echo json_encode(['status' => 'error']);
    exit;
}

$cache_key = "p_cache_" . md5($ip);

// بررسی کش برای کاهش لود سرور
if (isset($_SESSION[$cache_key])) {
    $c = $_SESSION[$cache_key];
    if (time() - $c['time'] < $cache_duration) {
        echo json_encode(['status' => $c['status'], 'source' => 'cache']);
        exit;
    }
}

// اجرای پینگ
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$command = $isWindows ? "ping -n 1 -w 500 $ip" : "ping -c 1 -W 1 $ip";
exec($command, $output, $resultCode);

$status = ($resultCode === 0) ? 'online' : 'offline';

// ذخیره در کش نشست
$_SESSION[$cache_key] = ['status' => $status, 'time' => time()];

echo json_encode(['status' => $status, 'source' => 'live']);