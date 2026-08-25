<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Open file Json
$workDir = "../../../Power";
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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit | IP</title>
    <link rel="stylesheet" href="../../../src/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../src/css/dasboard.css">
    <link rel="stylesheet" href="../../../src/fonts/all.min.css">

    <?php
    include('../../layouts/header.php');
    ?>
    <style>
        .background {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 0;
            inset: 0;
            background: linear-gradient(to right, rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, linear-gradient(rgba(229, 231, 235, 0.8) 1px, transparent 1px) 0% 0% / 48px 48px, radial-gradient(500px at 20% 80%, rgb(14 14 14 / 30%), transparent) 0% 0% / 100% 100%, radial-gradient(500px at 80% 20%, rgb(0 0 0 / 30%), transparent) 0% 0% / 100% 100% rgb(255, 255, 255);
            background-size: "48px 48px, 48px 48px, 100% 100%, 100% 100%"
        }

        .result-box {
            background: rgba(214, 214, 214, 0.18);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 14px;
            box-shadow: 0 0px 32px rgba(31, 38, 135, 0.1);
            transition: all 250ms cubic-bezier(0.4, 0.0, 0.2, 1);
            width: 30%;
        }

        @media (max-width: 768px) {
            .result-box {
                width: 100% !important;
            }
        }

        .btn-danger-liquid {
            /* From https://css.glass */
            background: #ff000033;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(12.4px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff0000;
        }

        .btn-success-liquid {
            /* From https://css.glass */
            background: rgba(0, 255, 31, 0.2);
            border-radius: 10px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 31, 0.9);
            padding: 8px 15px;
            transition: 0.2s;
        }

        .btn-success-liquid:hover {
            background: rgba(0, 255, 31, 0.37);
            box-shadow: 0 4px 30px rgba(0, 255, 31, 0.37);

        }
    </style>
</head>

<body>
    <?php
    include('../../layouts/index.php');
    ?>
    <div class="background"></div>

    <div class="result-box mx-auto" style="padding:20px;">
        <?php if (!empty($ranges)): ?>

            <h2 class="text-capitalize mb-3 ms-4"><?php echo $_GET['dc_name'] ?></h2>
            <form method="POST" action="../../../Controller/editJsonController.php">
                <input type="hidden" name="dc_name" value="<?php echo htmlspecialchars($currentDc); ?>">

                <div id="ranges-container">

                    <?php foreach ($ranges as $range): ?>
                        <div class="input-group mb-3 w-100">
                            <input type="text" class="form-control"
                                name="ranges[]"
                                value="<?php echo htmlspecialchars($range); ?>"
                                class="form-control w-25">
                            <button class="btn btn-danger-liquid" style="font-size: 14px;" onclick="removeRange(this)" type="button" id="button-addon2"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- دکمه افزودن -->
                <div class="d-flex align-items-center justify-content-between">
                    <button type="button" class="btn text-primary" onclick="addRange()">
                        <i class="fas fa-plus"></i> Add IP Range
                    </button>
                    <button type="submit" class="btn btn-success-liquid" name="save_ranges">
                        Save
                    </button>
                </div>

            </form>

        <?php endif; ?>
    </div>


    <script src="../../../src/js/bootstrap.bundle.min.js"></script>
    <script src="../../../src/js/all.min.js"></script>
    <script>
        <?php if ($toast): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var toastEl = document.getElementById('liveToast');
                if (toastEl) {
                    var t = new bootstrap.Toast(toastEl);
                    t.show();
                }
            });

        <?php endif; ?>

        function addRange() {

            const container = document.getElementById('ranges-container');

            const div = document.createElement('div');
            div.className = "range-item";
            div.style.marginBottom = "10px";

            div.innerHTML = `
            <div class="input-group mb-3 w-100">
                <input type="text"
                        name="ranges[]"
                        class="form-control text-start"
                        placeholder="Enter IP range (e.g. 192.168.1.0/24)">

                <button type="button"
                        onclick="removeRange(this)"
                        class="btn btn-danger-liquid">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
    `;

            container.appendChild(div);
        }

        function removeRange(button) {
            button.parentElement.remove();
        }
    </script>
</body>

</html>