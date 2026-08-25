<?php
$workDir = "/var/www/html/Power";
$message = "";
$ranges = [];
$currentDc = "";

if (isset($_POST['save_ranges']) && isset($_POST['dc_name'])) {

    $dc = strtolower($_POST['dc_name']);
    $filePath = $workDir . "/" . $dc . ".json";

    $newRanges = array_values(array_filter($_POST['ranges']));

    $newJson = [
        "ranges" => $newRanges
    ];

    file_put_contents(
        $filePath,
        json_encode(
            $newJson,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );
    header("Location: index.php?action=edit&dc_name=$dc");
    exit;
}