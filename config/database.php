<?php

$conn = mysqli_connect("localhost", "root", "", "blood_donation_portal");

if (!$conn)
{
    die("Database connection failed: " . mysqli_connect_error());
}

?>