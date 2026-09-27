<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['role']) && !empty($_SESSION['role']);
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['name'] ?? '';

$dashboard_link = "/index.php";
if ($user_role === 'donor') {
    $dashboard_link = "/donor/dashboard.php";
} elseif ($user_role === 'recipient') {
    $dashboard_link = "/recipient/dashboard.php";
} elseif ($user_role === 'admin') {
    $dashboard_link = "/admin/dashboard.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFELINE · Blood Donation Management Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css?v=2.1">
</head>
<body>

<header class="site-header">
    <a href="/index.php" class="site-brand">
        <span class="site-brand-mark">+</span>
        <span>LIFELINE</span>
    </a>

    <nav class="site-nav">
        <a href="/index.php#how-it-works">How it works</a>
        <a href="/index.php#for-donors">For donors</a>
        <a href="/index.php#for-recipients">For recipients</a>
        <a href="/index.php#compatibility">Compatibility</a>
        <a href="/about.php">About</a>
    </nav>

    <div class="site-header-actions">
        <?php if ($is_logged_in): ?>
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="btn-nav-login">
                Dashboard (<?php echo htmlspecialchars(ucfirst($user_role)); ?>)
            </a>
            <a href="/logout.php" class="btn-nav-register">
                Log out
            </a>
        <?php else: ?>
            <a href="/login.php" class="btn-nav-login">
                Sign in
            </a>
            <a href="/register.php" class="btn-nav-register">
                Join Network
            </a>
        <?php endif; ?>

        <button class="mobile-toggle" aria-label="Toggle Navigation" aria-expanded="false">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="drawer-overlay"></div>
<aside class="mobile-drawer">
    <div class="mobile-drawer-header">
        <a href="/index.php" class="site-brand">
            <span class="site-brand-mark">+</span>
            <span>LIFELINE</span>
        </a>
        <button class="mobile-toggle" aria-label="Close Navigation">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <nav class="mobile-drawer-nav">
        <a href="/index.php">Home</a>
        <a href="/index.php#how-it-works">How it works</a>
        <a href="/index.php#for-donors">For Donors</a>
        <a href="/index.php#for-recipients">For Recipients</a>
        <a href="/index.php#compatibility">Compatibility Matrix</a>
        <a href="/about.php">About LIFELINE</a>
    </nav>

    <div class="mobile-drawer-footer">
        <?php if ($is_logged_in): ?>
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="primary-button" style="width: 100%;">
                My Dashboard
            </a>
            <a href="/logout.php" class="secondary-button" style="width: 100%;">
                Log out
            </a>
        <?php else: ?>
            <a href="/login.php" class="secondary-button" style="width: 100%;">
                Sign in
            </a>
            <a href="/register.php" class="primary-button" style="width: 100%;">
                Join Network
            </a>
        <?php endif; ?>
    </div>
</aside>

<main class="public-main">