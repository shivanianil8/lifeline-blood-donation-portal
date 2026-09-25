<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'donor')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/request_helpers.php";

$user_id = $_SESSION['user_id'];


/* =========================
   GET DONOR ID & PROFILE
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT donor_id,
            blood_group,
            age,
            gender,
            location,
            address,
            last_donation_date
     FROM donors
     WHERE user_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$donor = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$donor)
{
    $donor_id = 0;
}
else
{
    $donor_id = (int)$donor['donor_id'];
}


/* =========================
   PROFILE DATA & COMPLETION
========================= */

$blood_group = $donor['blood_group'] ?? "";
$age = $donor['age'] ?? "";
$gender = $donor['gender'] ?? "";
$location = $donor['location'] ?? "";
$address = $donor['address'] ?? "";
$last_donation_date = $donor['last_donation_date'] ?? "";

$profile_fields = [
    $blood_group,
    $age,
    $gender,
    $location,
    $address
];

$completed = 0;
foreach ($profile_fields as $field)
{
    if (!empty($field))
    {
        $completed++;
    }
}

$profile_percentage = round(
    ($completed / count($profile_fields)) * 100
);


/* =========================
   REAL DONATION STATS
========================= */

$total_donations = 0;
$total_units = 0;

if ($donor_id > 0)
{
    $don_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total_donations, COALESCE(SUM(units), 0) AS total_units
         FROM donations
         WHERE donor_id = ?"
    );

    if ($don_stmt)
    {
        mysqli_stmt_bind_param($don_stmt, "i", $donor_id);
        mysqli_stmt_execute($don_stmt);
        $don_res = mysqli_stmt_get_result($don_stmt);
        if ($don_row = mysqli_fetch_assoc($don_res))
        {
            $total_donations = (int)$don_row['total_donations'];
            $total_units = (int)$don_row['total_units'];
        }
        mysqli_stmt_close($don_stmt);
    }
}


/* =========================
   ACTIVE APPOINTMENTS COUNT
========================= */

$active_appointments_count = 0;

if ($donor_id > 0)
{
    $act_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS active_count
         FROM appointments
         WHERE donor_id = ?
         AND status IN ('pending', 'confirmed')"
    );

    if ($act_stmt)
    {
        mysqli_stmt_bind_param($act_stmt, "i", $donor_id);
        mysqli_stmt_execute($act_stmt);
        $act_res = mysqli_stmt_get_result($act_stmt);
        if ($act_row = mysqli_fetch_assoc($act_res))
        {
            $active_appointments_count = (int)$act_row['active_count'];
        }
        mysqli_stmt_close($act_stmt);
    }
}


/* =========================
   AVAILABLE MATCHING REQUESTS
========================= */

$matching_requests_count = 0;

if (!empty($blood_group))
{
    $req_stmt = mysqli_prepare(
        $conn,
        "SELECT request_id
         FROM blood_requests
         WHERE blood_group = ?
         AND status IN ('pending', 'matched', 'partially_fulfilled')"
    );

    if ($req_stmt)
    {
        mysqli_stmt_bind_param($req_stmt, "s", $blood_group);
        mysqli_stmt_execute($req_stmt);
        $req_res = mysqli_stmt_get_result($req_stmt);

        while ($req_row = mysqli_fetch_assoc($req_res))
        {
            $sum = get_request_units_summary($conn, $req_row['request_id']);
            if ($sum['remaining'] > 0 && $sum['status'] !== 'fulfilled')
            {
                $matching_requests_count++;
            }
        }
        mysqli_stmt_close($req_stmt);
    }
}


/* =========================
   UPCOMING APPOINTMENT
========================= */

$upcoming_appointment = null;

if ($donor_id > 0)
{
    $app_stmt = mysqli_prepare(
        $conn,
        "SELECT
            a.appointment_id,
            a.hospital,
            a.appointment_date,
            a.appointment_time,
            a.request_id,
            a.status,
            br.blood_group,
            br.units_required
         FROM appointments a
         LEFT JOIN blood_requests br
            ON a.request_id = br.request_id
         WHERE a.donor_id = ?
           AND a.status IN ('pending', 'confirmed')
           AND a.appointment_date >= CURDATE()
         ORDER BY a.appointment_date ASC, a.appointment_time ASC
         LIMIT 1"
    );

    if ($app_stmt)
    {
        mysqli_stmt_bind_param($app_stmt, "i", $donor_id);
        mysqli_stmt_execute($app_stmt);
        $app_res = mysqli_stmt_get_result($app_stmt);
        $upcoming_appointment = mysqli_fetch_assoc($app_res);
        mysqli_stmt_close($app_stmt);
    }
}


/* =========================
   RECENT DONATIONS
========================= */

$recent_donations = [];

if ($donor_id > 0)
{
    $rec_stmt = mysqli_prepare(
        $conn,
        "SELECT
            donation_id,
            blood_group,
            units,
            hospital,
            donation_date
         FROM donations
         WHERE donor_id = ?
         ORDER BY donation_date DESC
         LIMIT 3"
    );

    if ($rec_stmt)
    {
        mysqli_stmt_bind_param($rec_stmt, "i", $donor_id);
        mysqli_stmt_execute($rec_stmt);
        $rec_res = mysqli_stmt_get_result($rec_stmt);
        while ($row = mysqli_fetch_assoc($rec_res))
        {
            $recent_donations[] = $row;
        }
        mysqli_stmt_close($rec_stmt);
    }
}


/* =========================
   PAGE LAYOUT
========================= */

include "donor-layout.php";

?>

<section class="content dashboard-page">

    <!-- PAGE HEADING -->
    <div class="page-heading">
        <p class="eyebrow">
            DONOR WORKSPACE
        </p>

        <h1>
            Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>.
        </h1>

        <p class="subtitle">
            Your live donation activity, matched blood requests, and scheduled hospital appointments.
        </p>
    </div>


    <!-- 4 USEFUL LIVE STAT CARDS (Section 11) -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 28px;">

        <!-- MATCHING REQUESTS -->
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">MATCHING REQUESTS</span>
                <span class="stat-icon">●</span>
            </div>
            <div class="stat-value" style="color: var(--burgundy);">
                <?php echo $matching_requests_count; ?>
            </div>
            <p class="stat-description">
                Active requests for <?php echo !empty($blood_group) ? htmlspecialchars($blood_group) : 'you'; ?>
            </p>
        </div>

        <!-- ACTIVE APPOINTMENTS -->
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">ACTIVE APPOINTMENTS</span>
                <span class="stat-icon">□</span>
            </div>
            <div class="stat-value">
                <?php echo $active_appointments_count; ?>
            </div>
            <p class="stat-description">
                Scheduled donation visits
            </p>
        </div>

        <!-- TOTAL DONATIONS -->
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">TOTAL DONATIONS</span>
                <span class="stat-icon">↗</span>
            </div>
            <div class="stat-value">
                <?php echo $total_donations; ?>
            </div>
            <p class="stat-description">
                Completed donation sessions
            </p>
        </div>

        <!-- TOTAL UNITS DONATED -->
        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">UNITS DONATED</span>
                <span class="stat-icon">+</span>
            </div>
            <div class="stat-value">
                <?php echo $total_units; ?>
            </div>
            <p class="stat-description">
                Total pints/units contributed
            </p>
        </div>

    </div>


    <!-- LOWER GRID -->
    <div class="dashboard-grid">

        <!-- PROFILE STATUS -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <p class="card-label">DONOR PROFILE</p>
                    <h2>
                        <?php echo $profile_percentage == 100 ? "Profile complete" : "Complete your profile"; ?>
                    </h2>
                </div>
                <span class="card-arrow">→</span>
            </div>

            <p class="card-description">
                Blood Group: <strong><?php echo !empty($blood_group) ? htmlspecialchars($blood_group) : "Not set"; ?></strong> &nbsp;·&nbsp;
                Location: <strong><?php echo !empty($location) ? htmlspecialchars($location) : "Not set"; ?></strong>
            </p>

            <div class="progress-area" style="margin: 14px 0;">
                <div class="progress-info">
                    <span>Profile completion</span>
                    <span><?php echo $profile_percentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $profile_percentage; ?>%;"></div>
                </div>
            </div>

            <a href="profile.php" class="dashboard-link">
                <?php echo $profile_percentage == 100 ? "Update profile details" : "Complete profile now"; ?> <span>→</span>
            </a>
        </div>


        <!-- UPCOMING APPOINTMENT -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <p class="card-label">APPOINTMENT</p>
                    <h2>Upcoming donation</h2>
                </div>
                <span class="card-arrow">→</span>
            </div>

            <?php if ($upcoming_appointment) { ?>
                <div class="appointment-preview" style="margin-top: 10px;">
                    <div class="appointment-hospital" style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($upcoming_appointment['hospital']); ?>
                    </div>
                    <div class="appointment-date" style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px;">
                        📅 <?php echo date("l, d M Y", strtotime($upcoming_appointment['appointment_date'])); ?>
                        <?php if (!empty($upcoming_appointment['appointment_time'])) { ?>
                            at <?php echo date("h:i A", strtotime($upcoming_appointment['appointment_time'])); ?>
                        <?php } ?>
                    </div>

                    <a href="appointments.php" class="primary-button" style="height: 40px; padding: 0 18px; font-size: 13px;">
                        Manage Appointment →
                    </a>
                </div>
            <?php } else { ?>
                <div class="empty-appointment" style="padding: 18px 0;">
                    <span style="font-size: 13px; color: var(--muted);">No upcoming appointments scheduled.</span>
                </div>
                <a href="requests.php" class="dashboard-link">
                    Browse matching requests <span>→</span>
                </a>
            <?php } ?>
        </div>

    </div>


    <!-- RECENT DONATIONS -->
    <div class="dashboard-card recent-card" style="margin-top: 24px;">
        <div class="dashboard-card-header">
            <div>
                <p class="card-label">HISTORY</p>
                <h2>Recent Completed Donations</h2>
            </div>
            <a href="history.php" class="view-link" style="color: var(--burgundy); font-weight: 600; font-size: 13px;">
                View full history →
            </a>
        </div>

        <?php if (!empty($recent_donations)) { ?>
            <div class="request-list" style="margin-top: 16px;">
                <?php foreach ($recent_donations as $don) { ?>
                    <div class="request-card" style="margin-bottom: 12px;">
                        <div class="request-card-top">
                            <div>
                                <span class="request-blood-group">
                                    <?php echo htmlspecialchars($don['blood_group']); ?>
                                </span>
                                <span class="request-units">
                                    <?php echo htmlspecialchars($don['units']); ?> unit(s) donated
                                </span>
                            </div>
                            <span class="badge badge-completed">
                                Completed
                            </span>
                        </div>
                        <div class="request-main">
                            <h2><?php echo htmlspecialchars($don['hospital']); ?></h2>
                            <p class="request-location">
                                Donated on <?php echo date("d M Y", strtotime($don['donation_date'])); ?>
                            </p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty-history" style="padding: 20px 0; text-align: center; color: var(--muted); font-size: 13px;">
                <span>No donation history recorded yet.</span>
            </div>
        <?php } ?>
    </div>

</section>

</main>
</div>
</body>
</html>