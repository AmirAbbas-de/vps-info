<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '/var/www/html/Database.php';

header('Content-Type: application/json');

$servername = "127.0.0.1";
$username = "npm";
$password = "Lwdatda@11wr";
$dbname = "IRvpsInfo";

try {
    $db = new Database($servername, $dbname, $username, $password);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$saved_at = $_GET['saved_at'] ?? null;

if (!$saved_at) {
    echo json_encode(['error' => 'Missing saved_at parameter']);
    exit;
}

$vpsData = $db->select("SELECT * FROM vps_snapshots WHERE saved_at = ?", [$saved_at]);

echo json_encode($vpsData);
?>