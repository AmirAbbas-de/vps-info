<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$ip   = isset($input['ip']) ? trim($input['ip']) : '';
$user = isset($input['user']) ? strtolower(trim($input['user'])) : '';
$pass = isset($input['pass']) ? $input['pass'] : '';
$port = !empty($input['port']) ? (int)$input['port'] : 3389;
$mode = isset($input['check_only']) ? trim($input['check_only']) : 'ping';

$response = ["ping_ok" => false, "status" => "error", "logs" => [], "system_info" => ""];

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    $response["logs"][] = ["type" => "error", "msg" => "آدرس IP نامعتبر است."];
    echo json_encode($response);
    exit;
}

$safe_ip = escapeshellarg($ip);
$pingCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
    ? "ping -n 1 -w 1000 $safe_ip" 
    : "ping -c 1 -W 1 $safe_ip";

exec($pingCmd, $output, $res);

if ($res === 0) {
    $response["ping_ok"] = true;
    $response["logs"][] = ["type" => "success", "msg" => "Host is Up."];
} else {
    $response["logs"][] = ["type" => "error", "msg" => "Host is Unreachable (Ping Failed)."];
    echo json_encode($response); 
    exit;
}

// اگر کاربر فقط تست پینگ خواسته بود، کار همینجا تمام است
if ($mode === 'ping') {
    $response["status"] = "success";
    echo json_encode($response);
    exit;
}

// بررسی احراز هویت یا پورت در حالت full
if ($mode === 'full') {
    if ($user === 'root') {
        if (function_exists('ssh2_connect')) {
            $conn = @ssh2_connect($ip, 22);
            if ($conn) {
                if (@ssh2_auth_password($conn, $user, $pass)) {
                    $response["status"] = "success";
                    $response["logs"][] = ["type" => "success", "msg" => "SSH Authenticated."];
                    
                    $stream = @ssh2_exec($conn, 'uname -a && uptime');
                    if ($stream) {
                        stream_set_blocking($stream, true);
                        $response["system_info"] = "SNAPSHOT_DATA: \n" . stream_get_contents($stream);
                    }
                } else {
                    $response["logs"][] = ["type" => "error", "msg" => "Invalid SSH credentials."];
                }
            } else {
                $response["logs"][] = ["type" => "error", "msg" => "Could not connect to SSH port 22."];
            }
        } else {
            $response["logs"][] = ["type" => "error", "msg" => "PHP extension 'ssh2' is not installed."];
        }
    } else if ($user === 'administrator') {
        // تست باز بودن پورت RDP ویندوز
        $fp = @fsockopen($ip, $port, $errno, $errstr, 2);
        if ($fp) {
            $response["status"] = "success";
            $response["logs"][] = ["type" => "success", "msg" => "RDP Port ($port) is Open."];
            $response["system_info"] = "Port $port is accepting connections. Remote Desktop is enabled.";
            fclose($fp);
        } else {
            $response["logs"][] = ["type" => "error", "msg" => "RDP Port $port is closed or timed out. Check Windows Firewall/Settings."];
        }
    } else {
        $response["logs"][] = ["type" => "error", "msg" => "Unsupported user or protocol. Use 'root' or 'administrator'."];
    }
}

echo json_encode($response);