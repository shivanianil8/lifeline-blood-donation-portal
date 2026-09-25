<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

require_once "../config/database.php";
require_once "../includes/notification_helpers.php";

$unread_notifications = 0;
if (isset($_SESSION['user_id']) && isset($conn) && $conn) {
    $unread_notifications = get_unread_notification_count($conn, (int)$_SESSION['user_id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Portal · LIFELINE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css?v=2.1">
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

        <!-- ROLE -->
        <div class="sidebar-label">
            RECIPIENT WORKSPACE
        </div>

        <!-- NAVIGATION -->
        <nav>
            <a
                href="dashboard.php"
                class="nav-item <?php if ($current_page == 'dashboard.php') echo 'active'; ?>"
            >
                <span>
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                Overview
            </a>

            <a
                href="profile.php"
                class="nav-item <?php if ($current_page == 'profile.php') echo 'active'; ?>"
            >
                <span>
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </span>
                Recipient Profile
            </a>

            <a
                href="create_request.php"
                class="nav-item <?php if ($current_page == 'create_request.php') echo 'active'; ?>"
            >
                <span>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                </span>
                Create Request
            </a>

            <a
                href="requests.php"
                class="nav-item <?php if ($current_page == 'requests.php') echo 'active'; ?>"
            >
                <span>
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </span>
                My Requests
            </a>

            <a
                href="notifications.php"
                class="nav-item <?php if ($current_page == 'notifications.php') echo 'active'; ?>"
            >
                <span>
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </span>
                Notifications
                <?php if ($unread_notifications > 0) { ?>
                    <span class="badge badge-critical" style="margin-left: auto; font-size: 10px; padding: 2px 7px; border-radius: 10px;">
                        <?php echo $unread_notifications; ?>
                    </span>
                <?php } ?>
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

        <!-- TOP BAR -->
        <header class="topbar">
            <div>
                <p class="eyebrow" style="margin-bottom: 0;">
                    RECIPIENT PORTAL
                </p>
            </div>

            <!-- USER -->
            <div class="user">
                <div class="avatar">
                    <?php
                    $name = $_SESSION['name'] ?? 'Recipient';
                    echo strtoupper(substr($name, 0, 1));
                    ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($name); ?>
                    </strong>
                    <small>
                        Verified Recipient
                    </small>
                </div>
            </div>
        </header>