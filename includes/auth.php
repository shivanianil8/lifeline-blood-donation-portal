<?php

if (session_status() === PHP_SESSION_NONE)
{
    session_start();
}

if (!isset($_SESSION['user_id']))
{
    $login_path = file_exists("login.php") ? "login.php" : "../login.php";
    header("Location: " . $login_path);
    exit();
}