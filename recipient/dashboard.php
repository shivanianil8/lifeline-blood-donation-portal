<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";
require_once "../includes/request_helpers.php";

$user_id = $_SESSION['user_id'];


/* =====================================================
   GET OR AUTO-INITIALIZE RECIPIENT PROFILE
===================================================== */

$recipient_id = 0;

$stmt = mysqli_prepare(
    $conn,
    "SELECT recipient_id FROM recipients WHERE user_id = ? LIMIT 1"
);

if ($stmt)
{
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $recipient = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($recipient)
    {
        $recipient_id = (int)$recipient['recipient_id'];
    }
    else
    {
        $init = mysqli_prepare(
            $conn,
            "INSERT INTO recipients (user_id) VALUES (?)"
        );

        if ($init)
        {
            mysqli_stmt_bind_param($init, "i", $user_id);
            mysqli_stmt_execute($init);
            $recipient_id = mysqli_insert_id($conn);
            mysqli_stmt_close($init);
        }
    }
}


/* =====================================================
   CALCULATE 4 REAL-TIME LIFECYCLE STATS
===================================================== */

$active_count = 0;
$pending_count = 0;
$partially_fulfilled_count = 0;
$fulfilled_count = 0;

$all_requests_stmt = mysqli_prepare(
    $conn,
    "SELECT request_id, status, units_required
     FROM blood_requests
     WHERE (recipient_id = ? OR recipient_id = ?)"
);

if ($all_requests_stmt)
{
    mysqli_stmt_bind_param($all_requests_stmt, "ii", $recipient_id, $user_id);
    mysqli_stmt_execute($all_requests_stmt);
    $res = mysqli_stmt_get_result($all_requests_stmt);

    while ($r = mysqli_fetch_assoc($res))
    {
        $st = sync_request_status($conn, $r['request_id']);

        if (in_array($st, ['pending', 'matched', 'partially_fulfilled']))
        {
            $active_count++;
        }

        if ($st === 'pending')
        {
            $pending_count++;
        }
        elseif ($st === 'partially_fulfilled')
        {
            $partially_fulfilled_count++;
        }
        elseif ($st === 'fulfilled' || $st === 'completed')
        {
            $fulfilled_count++;
        }
    }

    mysqli_stmt_close($all_requests_stmt);
}


/* =====================================================
   RECENT REQUESTS
===================================================== */

$recent_requests = [];

$sql = "
    SELECT request_id, blood_group, units_required, hospital, location, required_date, priority, status
    FROM blood_requests
    WHERE (recipient_id = ? OR recipient_id = ?)
    ORDER BY created_at DESC
    LIMIT 4
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt)
{
    mysqli_stmt_bind_param($stmt, "ii", $recipient_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result))
    {
        $recent_requests[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$user_name = $_SESSION['name'] ?? 'Recipient';

include "recipient-layout.php";

?>

<section class="content">

    <!-- PAGE HEADING -->
    <div class="page-heading">
        <p class="eyebrow">
            RECIPIENT OVERVIEW
        </p>

        <h1>
            Good day, <?php echo htmlspecialchars($user_name); ?>.
        </h1>

        <p class="subtitle">
            Real-time status of your hospital blood requests and donor responses.
        </p>
    </div>


    <!-- 4 REAL-TIME STAT CARDS (Section 10) -->
    <div class="dashboard-stats" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 28px;">

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">ACTIVE REQUESTS</span>
                <span class="stat-icon">↗</span>
            </div>
            <div class="stat-value">
                <?php echo $active_count; ?>
            </div>
            <p class="stat-description">
                Pending, matched & in-progress
            </p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">PENDING</span>
                <span class="stat-icon">○</span>
            </div>
            <div class="stat-value">
                <?php echo $pending_count; ?>
            </div>
            <p class="stat-description">
                Awaiting first donor response
            </p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">PARTIALLY FULFILLED</span>
                <span class="stat-icon">↻</span>
            </div>
            <div class="stat-value">
                <?php echo $partially_fulfilled_count; ?>
            </div>
            <p class="stat-description">
                Transfusion in progress
            </p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">FULFILLED</span>
                <span class="stat-icon">✓</span>
            </div>
            <div class="stat-value">
                <?php echo $fulfilled_count; ?>
            </div>
            <p class="stat-description">
                All units 100% completed
            </p>
        </div>

    </div>


    <!-- CREATE REQUEST CARD -->
    <div class="dashboard-card" style="margin-bottom: 28px;">
        <div class="dashboard-card-header">
            <div>
                <p class="card-label">
                    NEW BLOOD REQUEST
                </p>
                <h2>
                    Need blood transfusion support?
                </h2>
            </div>
            <span class="card-arrow">
                →
            </span>
        </div>

        <p class="card-description">
            Submit an emergency or scheduled blood request. Compatible donors across the network will be notified immediately.
        </p>

        <a href="create_request.php" class="primary-button" style="margin-top: 8px;">
            Create Blood Request →
        </a>
    </div>


    <!-- RECENT REQUESTS WITH PROGRESS INDICATORS -->
    <div class="dashboard-card recent-card">
        <div class="dashboard-card-header">
            <div>
                <p class="card-label">
                    ACTIVITY
                </p>
                <h2>
                    Recent Blood Requests
                </h2>
            </div>

            <a href="requests.php" class="view-link" style="color: var(--burgundy); font-weight: 600; font-size: 13px;">
                View all requests →
            </a>
        </div>

        <?php if (!empty($recent_requests)) { ?>
            <div class="request-list" style="margin-top: 18px;">
                <?php foreach ($recent_requests as $req) { ?>
                    <?php
                    $req_id = (int)$req['request_id'];
                    $summary = get_request_units_summary($conn, $req_id);
                    $st = $summary['status'];
                    $fulfilled = $summary['fulfilled'];
                    $required = $summary['required'];
                    $percent = $summary['percent'];
                    $priority = strtolower($req['priority'] ?? 'normal');
                    ?>
                    <div class="request-card" style="margin-bottom: 14px;">
                        <div class="request-card-top">
                            <div>
                                <div class="request-blood-group">
                                    <?php echo htmlspecialchars($req['blood_group']); ?>
                                </div>
                                <span class="request-units">
                                    Request #<?php echo $req_id; ?>
                                </span>
                            </div>

                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span class="priority-badge priority-<?php echo htmlspecialchars($priority); ?>">
                                    <?php echo ucfirst(htmlspecialchars($priority)); ?>
                                </span>

                                <span class="badge badge-<?php echo htmlspecialchars(str_replace(' ', '_', $st)); ?>">
                                    <?php
                                    if ($st === 'partially_fulfilled') {
                                        echo "Partially Fulfilled";
                                    } else {
                                        echo ucfirst(htmlspecialchars($st));
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="request-main">
                            <h2><?php echo htmlspecialchars($req['hospital']); ?></h2>
                            <p class="request-location">📍 <?php echo htmlspecialchars($req['location']); ?></p>

                            <!-- PROGRESS BAR -->
                            <div style="margin-top: 12px;">
                                <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600; color: #555; margin-bottom: 4px;">
                                    <span><?php echo htmlspecialchars($req['blood_group']); ?> · <?php echo $fulfilled; ?> / <?php echo $required; ?> units fulfilled</span>
                                    <span><?php echo $percent; ?>%</span>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar <?php if ($percent >= 100) echo 'fulfilled'; ?>" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty-history" style="padding: 24px 0; text-align: center; color: var(--muted); font-size: 13px;">
                <span>No blood requests submitted yet.</span>
            </div>
        <?php } ?>
    </div>

</section>

</main>
</div>
</body>
</html>