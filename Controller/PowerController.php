<?php

$message = "";
$output = "";
$downloadFile = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dc_name'])) {
    
    $dc_val = $_POST['dc_name'];
    $dc = escapeshellarg($dc_val); 
    
    $venvPython = "/var/www/html/Power/.venv/bin/python3"; 
    $scriptPath = "/var/www/html/Power/Power-Temp.py";
    $workDir = "/var/www/html/Power";

    $command = "cd $workDir && $venvPython $scriptPath $dc 2>&1";
    // 
    
    $output = shell_exec($command);

    
    $expectedFile = strtolower($dc_val) . "-Temprature_PowerUsage.xlsx";
    $fullPath = "$workDir/$expectedFile";

    if (file_exists($fullPath)) {

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($expectedFile) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($fullPath);
        exit;
    } else {
        $message = "File was not created!";
    }
}
?>