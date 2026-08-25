<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "192.168.100.78";
$username = "ilo_user";
$password = "123456";
$dbname = "power";

$dc_power = isset($_GET['dc_power']) ? $_GET['dc_power'] : 'Arvand';
try {

    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT * FROM ilo_power_data WHERE dc_power = :dc_power");
    $stmt->execute(['dc_power' => $dc_power]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $racks = [];
    $offline_servers = []; // آرایه برای ذخیره سرورهای خاموش

    foreach ($results as $row) {
        // جمع‌آوری سرورهای خاموش
        if (strtoupper($row['power_status']) !== 'ON') {
            $offline_servers[] = $row['ip'];
        }

        $ip_parts = explode('.', $row['ip']);
        if (count($ip_parts) === 4) {
            $rack_id = $ip_parts[2];
            $last_byte = (int)$ip_parts[3];
            $racks[$rack_id][$last_byte] = $row;
        }
    }
    ksort($racks);
} catch (PDOException $e) {
    die("خطا در اتصال: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <title> ILO Info | IR </title>
    <link rel="stylesheet" href="../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../src/css/tom-select.min.css">
    <link rel="stylesheet" href="../../../src/css/dasboard.css">
    <link rel="stylesheet" href="../../../src/fonts/css/all.min.css">

    <style>
        :root {
            --rack-border: #212529;
            --rack-bg: #343a40;
            --unit-bg: #495057;
        }

        /* The Data Center Rack Frame */
        .rack-container {
            background-color: var(--rack-bg);
            border: 4px solid var(--rack-border);
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            min-width: 220px;
        }

        /* Individual Server Slot */
        .server-slot {
            min-height: 80px;
            transition: all 0.2s ease;
            border: 1px solid #6c757d;
            border-radius: 4px;
            position: relative;
            margin-bottom: 4px;
            color: white;
            background: var(--unit-bg);
        }

        .server-online {
            border-right: 6px solid #2ecc71 !important;
            background: #4e555b;
        }

        .server-offline {
            border-right: 6px solid #e74c3c !important;
            opacity: 0.8;
        }

        .server-empty {
            background-color: rgba(0, 0, 0, 0.2);
            border: 1px dashed #6c757d;
            opacity: 0.4;
        }

        .ip-text {
            font-size: 0.8rem;
            font-family: monospace;
            color: #f8f9fa;
        }

        .stat-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.3);
            color: #fff;
        }

        .u-label {
            position: absolute;
            left: -35px;
            color: #6c757d;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .bolt-status {
            font-size: 1.1rem;

        }

        .navbar::before {
            background-color: white;
            backdrop-filter: unset;
            border-radius: 0;
        }

        .dropdown-menu::before {
            background-color: white;
            backdrop-filter: unset;
        }

        .submenu-wrapper .dropdown-submenu {
            background-color: white;
            backdrop-filter: unset;
        }

        .nav {
            border-radius: 0;
        }
    </style>
</head>

<body>
    <div class="background"></div>
    <?php include('../../layouts/index.php') ?>
    <div class="py-4 ">
        <?php if (empty($racks)): ?>
            <div class="alert alert-warning text-center">هیچ داده‌ای برای این محدوده یافت نشد.</div>
        <?php else: ?>
            <div class="btn-wrapper mb-3">
                <form action="./iloPing.php" method="get">
                    <button class="btn btn-outline-success">PING</button>
                    <input type="hidden" name="db" value="<?php echo $dc_power ?>">
                </form>
            </div>

            <!-- بخش جدید: نمایش آی‌پی سرورهای خاموش -->
            <div class="alert alert-danger mb-4 shadow-sm container" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-power-off fa-lg me-2"></i>
                    <strong class="me-auto"> <?php echo count($offline_servers); ?>:</strong>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($offline_servers)): ?>
                        <?php
                        // مرتب‌سازی بر اساس ساختار عددی IP از کوچک به بزرگ
                        usort($offline_servers, function ($a, $b) {
                            return ip2long($a) <=> ip2long($b);
                        });
                        ?>
                        <?php foreach ($offline_servers as $off_ip): ?>
                            <a href="https://<?php echo urlencode($off_ip); ?>"
                                class="badge bg-white text-danger border border-danger text-decoration-none p-2 font-monospace" target="_blank">
                                <i class="fas fa-server me-1"></i><?php echo htmlspecialchars($off_ip); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-success small">تمام سرورها روشن هستند.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-row flex-nowrap overflow-auto pb-4" style="gap: 10px; padding-left: 40px;">
                <?php foreach ($racks as $rack_no => $servers):
                    $min = min(array_keys($servers));
                    $max = max(array_keys($servers));
                    $j = 0;
                    $average_temp = 0;
                    $sum_max_power = 0;
                    $count = 0;
                ?>
                    <div class="rack-wrapper">
                        <h6 class="text-center mb-2 fw-bold text-dark">Rack <?php echo $rack_no; ?></h6>
                        <div class="rack-container">
                            <div class="d-flex flex-column">
                                <?php for ($i = 44; $i >= 2; $i--): ?>
                                    <div class="position-relative">
                                        <?php if (isset($servers[$i])):
                                            $s = $servers[$i];
                                            $is_on = (strtoupper($s['power_status']) == "ON");
                                            $has_error = (strtoupper($s['status']) !== "OK");
                                        ?>
                                            <div class="server-slot p-2 <?php echo $is_on ? 'server-online' : 'server-offline'; ?> 
                                        <?php echo $has_error ? 'server-error' : ''; ?>"
                                                <?php if ($has_error): ?>
                                                data-error="<?php echo htmlspecialchars($s['status']); ?>"
                                                data-ip="<?php echo htmlspecialchars($s['ip']); ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#status"
                                                <?php endif; ?>>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <a href="../dedidashboard/index.php?ip=<?php echo urlencode($s['ip']); ?>" class="ip-text text-decoration-none"><?php echo htmlspecialchars($s['ip']); ?></a>
                                                    <div class="bolt-status">
                                                        <?php if ($is_on): ?>
                                                            <i class="fas fa-circle text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-circle text-danger"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="mt-2 d-flex justify-content-between">
                                                    <span class="stat-badge
                                                    <?php
                                                    $st = $s['ambient_temp'];
                                                    if ($st > 10 && $st < 35) echo 'bg-success';
                                                    elseif ($st < 40) echo 'bg-warning';
                                                    else echo 'bg-danger';
                                                    ?>">
                                                        <i class="fas fa-thermometer-half me-1"></i>
                                                        <?php echo htmlspecialchars($s['ambient_temp']); ?>°C
                                                    </span>

                                                    <span class="stat-badge">
                                                        <i class="fas fa-bolt me-1 text-warning"></i>
                                                        <?php echo htmlspecialchars($s['max_power']); ?>W
                                                    </span>
                                                </div>
                                            </div>

                                            <?php
                                            $average_temp += intval($s['ambient_temp']);
                                            $sum_max_power += intval($s['max_power']);
                                            $count++;
                                            ?>
                                        <?php else: ?>
                                            <div class="server-slot server-empty p-2 d-flex align-items-center justify-content-center">
                                                <small class="text-white" style="font-size: 0.6rem;">EMPTY SLOT</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>

                                <div class="d-flex averag justify-content-between flex-wrap align-item-center">
                                    <span class="text-white ">Temp : <?php echo $count > 0 ? round($average_temp / $count, 1) : 0 ?></span>
                                    <span class="text-white ">PW : <?php echo round($sum_max_power / 256, 1) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="status">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body">
                    <!-- status lio -->
                </div>
            </div>
        </div>
    </div>
    <script src="../../../src/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.server-error').forEach(function(el) {
            el.addEventListener('click', function() {
                let error = this.getAttribute('data-error');
                let ip = this.getAttribute('data-ip');

                document.querySelector('#status .modal-body').innerHTML = `
                    <div class="text-center">
                        <h5>Server IP: ${ip}</h5>
                        <p class="text-danger mt-3">Error: ${error}</p>
                    </div>
                `;
            });
        });
    </script>
</body>

</html>