<?php

$toast = $_SESSION['toast'] ?? null;

if ($toast) {
    unset($_SESSION['toast']);
}
?>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <?php if ($toast): ?>
        <div class="toast" role="alert" id="liveToast" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header">
                <div class="rounded rounded-2 me-2 bg-<?php echo htmlspecialchars($toast['type'] ?? 'primary'); ?>" style="width: 20px;height: 20px;"></div>
                <strong class="me-auto"><?php echo htmlspecialchars($toast['title'] ?? 'Notification'); ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php echo htmlspecialchars($toast['body'] ?? ''); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <div class="offcanvas offcanvas-start h-100" data-bs-backdrop="false" id="offcanvas-nav" style="width: 90%;">
            <div class="offcanvas-header">
                <a href="./index.html">
                    <?php if (file_exists('./flag-iran.png')) { ?>
                        <img src="./flag-iran.png" alt="" height="50px" style="border-radius:10px">
                    <?php } ?>
                    <?php if (file_exists('./Flag_of_Europe.svg')) { ?>
                        <img src="./Flag_of_Europe.svg" alt="" height="50px" style="border-radius:10px">
                    <?php } ?>
                </a>
                <button class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown  dropdown-nav">
                        <a href="#" class="nav-link" data-bs-toggle="dropdown"> Virtualizor <i class="fas fa-angle-down"></i></a>
                        <ul class="dropdown-menu fade">
                            <li class="submenu-wrapper d-none d-lg-block">
                                <span class="d-flex justify-content-around align-items-center">
                                    <a href="#" class="dropdown-item" data-bs-toggle="dropdown"><i class="fa-solid icon-dropdown fa-circle"></i>France</a>
                                    <i class="fas fa-angle-right me-3"></i>
                                </span>
                                <ul class="dropdown-submenu">
                                    <li><a class="dropdown-item" href="../FR/dashboard.php"><i class="fa-solid icon-dropdown fa-circle"></i>VPS Info</a></li>
                                    <li><a class="dropdown-item" href="../FR/ipam.php"><i class="fa-solid icon-dropdown fa-circle"></i>IP Info</a></li>
                                </ul>
                            </li>
                            <li class="submenu-wrapper d-none d-lg-block">
                                <span class="d-flex justify-content-around align-items-center">
                                    <a href="#" class="dropdown-item" data-bs-toggle="dropdown"><i class="fa-solid icon-dropdown fa-circle"></i>Iran</a>
                                    <i class="fas fa-angle-right me-3"></i>
                                </span>
                                <ul class="dropdown-submenu">
                                    <li><a class="dropdown-item" href="../IR/dashboard.php"><i class="fa-solid icon-dropdown fa-circle"></i>VPS Info</a></li>
                                    <li><a class="dropdown-item" href="../IR/ipam.php"><i class="fa-solid icon-dropdown fa-circle"></i>IP Info</a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown-nav dropdown">
                        <a href="#" class="nav-link" data-bs-toggle="dropdown"> Dedicate Servers <i class="fas fa-angle-down"></i></a>
                        <form method="POST" action="../../../Controller/PowerContorller.php">
                            <ul class="dropdown-menu">
                                <li class="submenu-wrapper d-none d-lg-block">
                                    <span class="d-flex justify-content-around align-items-center">
                                        <a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Arvand" class="dropdown-item"><i class="fa-solid icon-dropdown fa-circle"></i>Arvand</a>
                                        
                                    </span>
                                    
                                </li>
                                <li class="submenu-wrapper d-none d-lg-block">
                                    <span class="d-flex justify-content-around align-items-center">
                                        <a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Afranet" class="dropdown-item"><i class="fa-solid icon-dropdown fa-circle"></i>Afranet</a>
                                        
                                    </span>
                                    
                                </li>
                                <li class="submenu-wrapper d-none d-lg-block">
                                    <span class="d-flex justify-content-around align-items-center">
                                        <a class="dropdown-item"><i class="fa-solid icon-dropdown fa-circle"></i>Hiweb</a>
                                        <i class="fas fa-angle-right me-3"></i>
                                    </span>
                                    <ul class="dropdown-submenu">
                                        <li><a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Hiweb" class="dropdown-item" data-bs-toggle="dropdown"><i class="fa-solid icon-dropdown fa-circle"></i>Hiweb</a></li>
                                        <li><a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Hiweb" class="dropdown-item" data-bs-toggle="dropdown"><i class="fa-solid icon-dropdown fa-circle"></i>Torob</a></li>
                                        <li><a href="#" class="dropdown-item" data-bs-toggle="dropdown"><i class="fa-solid icon-dropdown fa-circle"></i>Hamravesh</a></li>
                                        
                                    </ul>
                                </li>
                            
                                <li class="submenu-wrapper d-none d-lg-block">
                                    <span class="d-flex justify-content-around align-items-center">
                                        <a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Emam" class="dropdown-item" ><i class="fa-solid icon-dropdown fa-circle"></i>Emam</a>
                          
                                    </span>
                                    
                                </li>
                                <li class="submenu-wrapper d-none d-lg-block">
                                    <span class="d-flex justify-content-around align-items-center">
                                        <a href="http://192.168.100.88/views/pages/ilodata/ilodata.php?dc_power=Milad-Hamravesh-Down" class="dropdown-item" ><i class="fa-solid icon-dropdown fa-circle"></i>Milad-Hamravesh-Down</a>
                                    </span>
                                    
                                </li>
                            </ul>
                        </form>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="../log.php">Log Checker</a></li>
                    <li class="nav-item"><a class="nav-link" href="http://192.168.100.88/ConfigCode/configure.php">Configure</a></li>
                    <li class="nav-item"><a href="http://192.168.100.88/views/pages/InvoiceDedicate/index.php" class="nav-link">InvoiceDedicate</a></li>
                    <li class="nav-item"><a class="nav-link" href="http://192.168.100.77/">Dedicated Panel</a></li>
                </ul>
                <ul class="navbar-nav ms-lg-auto">
                    <li class="nav-item my-auto">
                        <form action="../../../Controller/searchController.php" method="GET" class="position-relative">
                            <input type="text" name="search" class="search-input" placeholder="Search...">
                            <button type="submit" class="btn d-none" ><i class="fas fa-search"></i></button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        <div class="navbar-brand m-0">
            <?php if (file_exists('./flag-iran.png')) { ?>
                <img src="./flag-iran.png" alt="" class="ms-3" height="50px" style="border-radius:10px">
            <?php } ?>
            <?php if (file_exists('./Flag_of_Europe.svg')) { ?>
                <img src="./Flag_of_Europe.svg" alt="" class="ms-3" height="50px" style="border-radius:10px">
            <?php } ?>
        </div>
        <button class="icon-nav-offcanvas btn d-lg-none" data-bs-target="#offcanvas-nav" data-bs-toggle="offcanvas">
            <i class="fa-solid fa-bars fa-2x text-secondary"></i>
        </button>
    </div>
</nav>
<?php if ($toast): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('liveToast');
            if (toastEl) {
                var t = new bootstrap.Toast(toastEl);
                t.show();
            }
        });
    </script>
<?php endif; ?>