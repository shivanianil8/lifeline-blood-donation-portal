<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration · LIFELINE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css?v=2.2">
</head>
<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <!-- BRAND -->
        <a href="../index.php" class="brand" style="text-decoration: none; color: inherit;">
            <span class="brand-mark">+</span>
            <span>LIFELINE</span>
        </a>

        <div class="sidebar-label">
            ADMINISTRATION
        </div>

        <!-- NAVIGATION -->
        <nav>
            <a href="dashboard.php" class="nav-item <?php if ($current_page == 'dashboard.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                Overview
            </a>

            <a href="blood-stock.php" class="nav-item <?php if ($current_page == 'blood-stock.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>
                </span>
                Blood Stock
            </a>

            <a href="requests.php" class="nav-item <?php if ($current_page == 'requests.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </span>
                Blood Requests
            </a>

            <a href="appointments.php" class="nav-item <?php if ($current_page == 'appointments.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </span>
                Appointments
            </a>

            <a href="donations.php" class="nav-item <?php if ($current_page == 'donations.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                Donations Log
            </a>

            <a href="users.php" class="nav-item <?php if ($current_page == 'users.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                Users
            </a>

            <a href="donors.php" class="nav-item <?php if ($current_page == 'donors.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </span>
                Donors
            </a>

            <a href="recipients.php" class="nav-item <?php if ($current_page == 'recipients.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                </span>
                Recipients
            </a>

            <a href="notifications.php" class="nav-item <?php if ($current_page == 'notifications.php') echo 'active'; ?>">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </span>
                Notifications
            </a>
        </nav>

        <!-- BOTTOM NAV -->
        <div class="sidebar-bottom">
            <a href="../index.php" class="nav-item">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </span>
                Public Portal
            </a>

            <a href="../logout.php" class="nav-item logout">
                <span>
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </span>
                Log out
            </a>
        </div>

    </aside>

    <!-- MAIN -->
    <main class="main">
