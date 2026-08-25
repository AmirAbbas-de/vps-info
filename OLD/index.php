<?php
/**
 * Virtualizor Dashboard & Power Control
 */
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
    
    $output = shell_exec($command);

    $expectedFile = strtolower($dc_val) . "-Temprature_PowerUsage.xlsx";
    $fullPath = "$workDir/$expectedFile";

    if (file_exists($fullPath)) {

        // جلوگیری از هر خروجی اضافی
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


// Open file Json
$workDir = "/var/www/html/Power";
$message = "";
$ranges = [];
$currentDc = "";

if (isset($_GET['action']) && $_GET['action'] == "edit" && isset($_GET['dc_name'])) {

    $currentDc = strtolower($_GET['dc_name']);
    $filePath = $workDir . "/" . $currentDc . ".json";

    if (file_exists($filePath)) {

        $jsonData = json_decode(file_get_contents($filePath), true);

        if (isset($jsonData['ranges']) && is_array($jsonData['ranges'])) {
            $ranges = $jsonData['ranges'];
        }
    }
}

?>

<style>
body{
    margin: 0;
    padding: 0;
}
.navbar-custom {
    background: #007bff;
    padding: 12px 25px;
    display: flex;
    margin-bottom: 20px;
    align-items: center;
    justify-content: space-between;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.nav-left, .nav-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.navbar-custom a,
.navbar-custom button {
    color: white;
    text-decoration: none;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    padding: 8px 12px;
    border-radius: 6px;
    transition: 0.2s;
}

.navbar-custom a:hover,
.navbar-custom button:hover {
    background: rgba(235, 235, 235, 0.15);
}

.dropdown {
    position: relative;
}
/* dropdown اصلی */
.dropdown-content {
    display: none;
    position: absolute;
    top: 25px;
    left: 0;
    background: #ffffff;
    min-width: 180px;
    border-radius: 10px;
    padding: 6px 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    z-index: 1000;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* آیتم‌ها */
.dropdown-content a,
.dropdown-content button {
    width: 100%;
    display: block;
    padding: 9px 16px;
    background: none;
    border: none;
    text-align: left;
    font-size: 14px;
    color: #333;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

/* حذف border های قدیمی */
.dropdown-content a,
.dropdown-content button {
    border: none;
}

/* hover نرم و مینیمال */
.dropdown-content a:hover,
.dropdown-content button:hover {
    background: #f5f5f5;
    color: #000;
}

/* جداکننده اختیاری */
.dropdown-divider {
    height: 1px;
    background: #eee;
    margin: 6px 0;
}
.dropdown:hover .dropdown-content {
    display: block;
}
.submenu{
    position: relative;
}
.submenu-content{
    position: absolute;
    display:none;
}
.submenu-content{
    display: none;
    position: absolute;
    top: 0px;
    left: 100%;
    background: #ffffff;
    min-width: 180px;
    border-radius: 10px;
    padding: 6px 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    z-index: 1000;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.submenu:hover .submenu-content{
    display:block;
}
.icon-menu{
    float:right;
}
.textarea-json{
   width:100%; 
   height:500px; 
   font-family:monospace; 
   font-size:14px;
   border: 2px solid #ccc;
   border-radius: 15px;
   padding: 10px;
}

</style>

<nav class="navbar-custom">

    <div class="nav-left">
        <div class="dropdown">
            <button>Virtualizor ▾</button>
            <div class="dropdown-content">
                <div class="submenu">
                    <button>France <span class="icon-menu">></span></button>
                    <div class="submenu-content">
                        <a href="/FR/dashboard.php">VPS Info</a>
                        <a href="/FR/ipam.php">IP Info</a>
                    </div>
                </div>
                <div class="submenu">
                    <button>Iran <span class="icon-menu">></span></button>
                    <div class="submenu-content">
                        <a href="/IR/dashboard.php">VPS Info</a>
                        <a href="/IR/ipam.php">IP Info</a>
                    </div>
                </div>
            </div>
        </div>
        <a href="http://192.168.100.77/">Dedicated Panel</a>
        <div class="dropdown">
            <button>Power & Temp ▾</button>
            <div class="dropdown-content">
                <form method="POST" action="./index.php">
                    <div class="submenu">
                        <button type="button">Arvand <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=arvand">Edit</a>
                            <button type="submit" name="dc_name" value="arvand">Run</button>
                        </div>
                    </div>
                    <div class="submenu">
                        <button type="button">Afranet <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=afranet">Edit</a>
                            <button type="submit" name="dc_name" value="afranet">Run</button>
                        </div>
                    </div>
                    <div class="submenu">
                        <button type="button">Hiweb <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=hiweb">Edit</a>
                            <button type="submit" name="dc_name" value="hiweb">Run</button>
                        </div>
                    </div>
                    
                    <div class="submenu">
                        <button type="button">Torob Hiweb <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=hiwebtorob">Edit</a>
                            <button type="submit" name="dc_name" value="hiwebtorob">Run</button>
                        </div>
                    </div>
                    <div class="submenu">
                        <button type="button">Hamravesh <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=hiwebhamravesh">Edit</a>
                            <button type="submit" name="dc_name" value="hamravesh">Run</button>
                        </div>
                    </div>
                    <div class="submenu">
                        <button type="button">Emam <span class="icon-menu">></span></button>
                        <div class="submenu-content">
                            <a href="?action=edit&dc_name=emam">Edit</a>
                            <button type="submit" name="dc_name" value="emam">Run</button>
                        </div>
                    </div>
<!-- 
                    <button type="submit" name="dc_name" value="hiwebtorob">Torob Hiweb</button>
                    <button type="submit" name="dc_name" value="hiwebhamravesh">Hamravesh</button>
                    <button type="submit" name="dc_name" value="Emam">Emam</button> -->
                </form>
            </div>
        </div>
        <a href="/log.php">Log Checker</a>
    </div>

    <div class="nav-right">
        <img src="./Flag_of_Europe.svg" alt="" height="50px" style="border-radius:10px">
        <img src="./flag-iran.png" alt="" height="50px" style="border-radius:10px">
    </div>
</nav>
<div class="result-box" style="padding:20px;">

<?php if (!empty($ranges)): ?>

    <form method="POST" action="./edit_Json.php">
        <input type="hidden" name="dc_name" value="<?php echo htmlspecialchars($currentDc); ?>">

        <div id="ranges-container">

            <?php foreach ($ranges as $range): ?>
                <div class="range-item" style="margin-bottom:10px;">
                    <input type="text"
                           name="ranges[]"
                           value="<?php echo htmlspecialchars($range); ?>"
                           style="width:350px; padding:6px;">
                    
                    <button type="button" onclick="removeRange(this)"
                            style="background:#dc3545;color:white;border:none;padding:6px 10px;border-radius:5px;">
                        ❌
                    </button>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- دکمه افزودن -->
        <button type="button" onclick="addRange()"
                style="margin-top:10px;background:#28a745;color:white;border:none;padding:8px 15px;border-radius:6px;">
            ➕ Add IP Range
        </button>

        <br><br>

        <button type="submit" name="save_ranges"
                style="background:#007bff;color:white;border:none;padding:8px 15px;border-radius:6px;">
            💾 Save
        </button>

    </form>

<?php endif; ?>
<script>

function addRange() {

    const container = document.getElementById('ranges-container');

    const div = document.createElement('div');
    div.className = "range-item";
    div.style.marginBottom = "10px";

    div.innerHTML = `
        <input type="text"
               name="ranges[]"
               placeholder="Enter IP range (e.g. 192.168.1.0/24)"
               style="width:350px; padding:6px;">

        <button type="button"
                onclick="removeRange(this)"
                style="background:#dc3545;color:white;border:none;padding:6px 10px;border-radius:5px;">
            ❌
        </button>
    `;

    container.appendChild(div);
}

function removeRange(button) {
    button.parentElement.remove();
}

</script>
</div>