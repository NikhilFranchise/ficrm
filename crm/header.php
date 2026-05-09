<?php
require_once 'auth.php';
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>CRM Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <style>
        .pagination { margin-top: 20px; }
        
        /* Global Page Loader */
        #global-loader {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.85);
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        #global-loader.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Global Page Loader -->
    <div id="global-loader">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="mt-3 text-primary fw-bold">Processing Data...</h5>
    </div>
    
    <script>
        // Hide loader when page is fully loaded
        window.addEventListener('load', function() {
            document.getElementById('global-loader').classList.add('hidden');
        });
        
        // Show loader when a form is submitted
        document.addEventListener('submit', function() {
            document.getElementById('global-loader').classList.remove('hidden');
        });
        
        // Show loader before navigating away (on hyperlink click)
        document.addEventListener('click', function(e) {
            let target = e.target.closest('a');
            if (target && target.href && !target.href.includes('javascript:') && !target.href.includes('#') && target.target !== '_blank') {
                document.getElementById('global-loader').classList.remove('hidden');
            }
        });
    </script>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <img src="https://www.franchiseindia.com/newhomepage/assets/img/Logo.svg" alt="FranchiseIndia Logo" style="max-height: 35px; margin-left: 0.5rem;" />
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block">
                        <i class="bx bx-menu bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                        <a href="index.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Dashboard">Dashboard</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'investors.php' ? 'active' : '' ?>">
                        <a href="investors.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Investors">Investors</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'franchisors.php' ? 'active' : '' ?>">
                        <a href="franchisors.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-store"></i>
                            <div data-i18n="Franchisors">Franchisors</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'meetings.php' ? 'active' : '' ?>">
                        <a href="meetings.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-calendar-event"></i>
                            <div data-i18n="Meetings">Meetings</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'shortlisted.php' ? 'active' : '' ?>">
                        <a href="shortlisted.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-list-check"></i>
                            <div data-i18n="Matched Investors">Matched Investors</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'active' : '' ?>">
                        <a href="matches.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-target-lock"></i>
                            <div data-i18n="Matchmaking">Match Engine</div>
                        </a>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : '' ?>">
                        <a href="logs.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-history"></i>
                            <div data-i18n="Activity Logs">Activity Logs</div>
                        </a>
                    </li>
                    <?php if($role === 'admin' || $role === 'manager'): ?>
                    <li class="menu-header small text-uppercase">
                      <span class="menu-header-text">Administration</span>
                    </li>
                    <li class="menu-item <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                        <a href="users.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Users">User Management</div>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Date Info -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center fw-medium text-muted">
                                <i class="bx bx-calendar fs-4 lh-0 me-2"></i>
                                <span><?= date('l, F j, Y') ?></span>
                            </div>
                        </div>
                        <!-- /Date Info -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-medium d-block"><?= htmlspecialchars($_SESSION['username']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars(ucfirst($role)) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li>
                                        <a class="dropdown-item" href="logout.php">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Log Out</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->
                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
