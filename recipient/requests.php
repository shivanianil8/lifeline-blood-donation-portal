<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'recipient')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/request_helpers.php";
include "../includes/notification_helpers.php";

$message = "";
$message_type = "";

/* =========================
   GET RECIPIENT ID
========================= */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT recipient_id
     FROM recipients
     WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recipient = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$recipient)
{
    $init = mysqli_prepare($conn, "INSERT INTO recipients (user_id) VALUES (?)");
    if ($init)
    {
        mysqli_stmt_bind_param($init, "i", $user_id);
        mysqli_stmt_execute($init);
        $recipient_id = mysqli_insert_id($conn);
        mysqli_stmt_close($init);
    }
    else
    {
        $recipient_id = $user_id;
    }
}
else
{
    $recipient_id = (int)$recipient['recipient_id'];
}


/* =========================
   CANCEL REQUEST ACTION
========================= */

if (isset($_POST['cancel_request']))
{
    $cancel_id = intval($_POST['request_id'] ?? 0);

    if ($cancel_id > 0)
    {
        // Verify ownership
        $stmt = mysqli_prepare(
            $conn,
            "SELECT request_id, status
             FROM blood_requests
             WHERE request_id = ?
             AND (recipient_id = ? OR recipient_id = ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iii",
            $cancel_id,
            $recipient_id,
            $user_id
        );

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $req_to_cancel = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($req_to_cancel)
        {
            $cur_st = strtolower($req_to_cancel['status']);

            if ($cur_st === 'fulfilled')
            {
                $message = "Fulfilled requests cannot be cancelled.";
                $message_type = "error";
            }
            elseif ($cur_st === 'cancelled')
            {
                $message = "This request has already been cancelled.";
                $message_type = "error";
            }
            else
            {
                mysqli_begin_transaction($conn);

                try
                {
                    $up = mysqli_prepare(
                        $conn,
                        "UPDATE blood_requests
                         SET status = 'cancelled'
                         WHERE request_id = ?"
                    );

                    mysqli_stmt_bind_param($up, "i", $cancel_id);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);

                    // Find active donors to notify them of cancellation
                    $active_donors = [];
                    $q_donors = mysqli_prepare($conn, "SELECT d.user_id FROM appointments a JOIN donors d ON a.donor_id = d.donor_id WHERE a.request_id = ? AND a.status IN ('pending', 'confirmed')");
                    if ($q_donors) {
                        mysqli_stmt_bind_param($q_donors, "i", $cancel_id);
                        mysqli_stmt_execute($q_donors);
                        $res_d = mysqli_stmt_get_result($q_donors);
                        while ($row_d = mysqli_fetch_assoc($res_d)) {
                            if (!empty($row_d['user_id'])) {
                                $active_donors[] = (int)$row_d['user_id'];
                            }
                        }
                        mysqli_stmt_close($q_donors);
                    }

                    // Cancel any active non-completed appointments
                    $up_a = mysqli_prepare(
                        $conn,
                        "UPDATE appointments
                         SET status = 'cancelled'
                         WHERE request_id = ?
                         AND status IN ('pending', 'confirmed')"
                    );

                    mysqli_stmt_bind_param($up_a, "i", $cancel_id);
                    mysqli_stmt_execute($up_a);
                    mysqli_stmt_close($up_a);

                    // Dispatch notifications
                    foreach ($active_donors as $donor_uid) {
                        create_notification($conn, $donor_uid, "Blood request #$cancel_id has been cancelled by the recipient.", 'cancellation', $cancel_id);
                    }
                    create_notification($conn, $user_id, "Your blood request #$cancel_id has been cancelled.", 'cancellation', $cancel_id);

                    mysqli_commit($conn);

                    $message = "Request #$cancel_id has been cancelled successfully.";
                    $message_type = "success";
                }
                catch (Exception $e)
                {
                    mysqli_rollback($conn);
                    $message = "Unable to cancel request. Please try again.";
                    $message_type = "error";
                }
            }
        }
        else
        {
            $message = "Request not found or access denied.";
            $message_type = "error";
        }
    }
}


/* =========================
   GET RECIPIENT NAME
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT name
     FROM users
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$name = $user['name'] ?? "Recipient";


/* =========================
   GET MY REQUESTS
========================= */

$requests = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        request_id,
        blood_group,
        units_required,
        hospital,
        location,
        required_date,
        priority,
        reason,
        status,
        created_at
     FROM blood_requests
     WHERE (recipient_id = ? OR recipient_id = ?)
     ORDER BY created_at DESC"
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $recipient_id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result))
{
    $requests[] = $row;
}

mysqli_stmt_close($stmt);


include "recipient-layout.php";

?>

        <section class="content requests-page">

            <!-- HEADER -->
            <div class="page-heading">
                <p class="eyebrow">
                    MY REQUESTS
                </p>

                <h1>
                    Your blood requests
                </h1>

                <p class="subtitle">
                    Track the fulfillment lifecycle and donor responses for all your blood requests in real time.
                </p>
            </div>

            <!-- FEEDBACK MESSAGE -->
            <?php if ($message != "") { ?>
                <div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php } ?>

            <!-- CREATE BUTTON -->
            <div style="margin-bottom: 28px;">
                <a href="create_request.php" class="primary-button">
                    + Create New Request
                </a>
            </div>

            <?php if (empty($requests)) { ?>

                <!-- EMPTY STATE -->
                <div class="requests-empty-card">
                    <div class="empty-icon">
                        +
                    </div>
                    <h2>No blood requests yet</h2>
                    <p>
                        You haven't submitted any blood requests. When you create one, compatible voluntary donors will be notified immediately.
                    </p>
                    <a href="create_request.php" class="primary-button">
                        Create a Request
                    </a>
                </div>

            <?php } else { ?>

                <!-- REQUEST LIST -->
                <div class="request-list">

                    <?php foreach ($requests as $request) { ?>

                        <?php
                        $req_id = (int)$request['request_id'];
                        $summary = get_request_units_summary($conn, $req_id);
                        $status = $summary['status'];
                        $required_units = $summary['required'];
                        $fulfilled_units = $summary['fulfilled'];
                        $remaining_units = $summary['remaining'];
                        $percent = $summary['percent'];
                        $priority = strtolower($request['priority'] ?? 'normal');

                        $donor_responses = get_donor_responses_for_request($conn, $req_id);
                        $response_count = count($donor_responses);
                        ?>

                        <div class="request-card">

                            <!-- CARD TOP -->
                            <div class="request-card-top">
                                <div>
                                    <div class="request-blood-group">
                                        <?php echo htmlspecialchars($request['blood_group']); ?>
                                    </div>
                                    <span class="request-units">
                                        REQUEST #<?php echo $req_id; ?>
                                    </span>
                                </div>

                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <span class="priority-badge priority-<?php echo htmlspecialchars($priority); ?>">
                                        <?php echo ucfirst(htmlspecialchars($priority)); ?>
                                    </span>

                                    <span class="badge badge-<?php echo htmlspecialchars(str_replace(' ', '_', $status)); ?>">
                                        <?php
                                        if ($status === 'partially_fulfilled') {
                                            echo "Partially Fulfilled";
                                        } else {
                                            echo ucfirst(htmlspecialchars($status));
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <!-- MAIN DETAILS -->
                            <div class="request-main">
                                <h2><?php echo htmlspecialchars($request['hospital']); ?></h2>
                                <p class="request-location">
                                    📍 <?php echo htmlspecialchars($request['location']); ?>
                                    &nbsp;·&nbsp;
                                    Required by <?php echo !empty($request['required_date']) ? date("d M Y", strtotime($request['required_date'])) : 'Urgent'; ?>
                                </p>

                                <!-- PROGRESS BAR -->
                                <div style="margin-top: 14px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #4A4A4A; margin-bottom: 4px;">
                                        <span>Fulfillment Progress</span>
                                        <span><?php echo $fulfilled_units; ?> / <?php echo $required_units; ?> units (<?php echo $percent; ?>%)</span>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-bar <?php if ($percent >= 100) echo 'fulfilled'; ?>" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                </div>

                                <!-- UNITS STAT GRID -->
                                <div class="units-stat-grid">
                                    <div class="units-stat-item">
                                        <span>Requested</span>
                                        <strong><?php echo $required_units; ?> units</strong>
                                    </div>
                                    <div class="units-stat-item">
                                        <span>Fulfilled</span>
                                        <strong class="<?php if ($fulfilled_units > 0) echo 'highlight'; ?>"><?php echo $fulfilled_units; ?> units</strong>
                                    </div>
                                    <div class="units-stat-item">
                                        <span>Remaining</span>
                                        <strong><?php echo $remaining_units; ?> units</strong>
                                    </div>
                                </div>

                                <?php if (!empty($request['reason'])) { ?>
                                    <p class="request-reason">
                                        <strong>Clinical Note:</strong> <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                                    </p>
                                <?php } ?>
                            </div>

                            <!-- DONOR RESPONSES SECTION -->
                            <div class="donor-responses-box">
                                <div class="donor-responses-header">
                                    <h4>
                                        DONOR RESPONSES
                                        <?php if ($response_count > 0) { ?>
                                            (<?php echo $response_count; ?> <?php echo $response_count == 1 ? 'donor' : 'donors'; ?>)
                                        <?php } ?>
                                    </h4>

                                    <?php if ($status !== 'fulfilled' && $status !== 'cancelled') { ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this blood request? Scheduled appointments will also be cancelled.');" style="margin: 0;">
                                            <input type="hidden" name="request_id" value="<?php echo $req_id; ?>">
                                            <button type="submit" name="cancel_request" class="btn-cancel-request">
                                                Cancel Request
                                            </button>
                                        </form>
                                    <?php } ?>
                                </div>

                                <?php if ($response_count === 0) { ?>
                                    <div style="font-size: 12px; color: var(--muted); font-style: italic; padding: 8px 0;">
                                        No donor responses yet. Compatible donors can see this request in their portal.
                                    </div>
                                <?php } else { ?>
                                    <div class="donor-response-list">
                                        <?php foreach ($donor_responses as $resp) { ?>
                                            <?php
                                            $resp_status = strtolower($resp['appointment_status'] ?? 'pending');
                                            $donor_display_name = !empty($resp['donor_name']) ? htmlspecialchars($resp['donor_name']) : 'Voluntary Donor';
                                            $initial = strtoupper(substr($donor_display_name, 0, 1));
                                            ?>
                                            <div class="donor-response-card">
                                                <div class="donor-response-donor">
                                                    <div class="donor-response-avatar">
                                                        <?php echo $initial; ?>
                                                    </div>
                                                    <div>
                                                        <strong style="color: var(--text);">Donor (<?php echo $donor_display_name; ?>)</strong>
                                                        <div style="font-size: 11px; color: var(--text-secondary);">
                                                            Blood Group: <strong><?php echo htmlspecialchars($resp['donor_blood_group']); ?></strong>
                                                            &nbsp;·&nbsp;
                                                            Scheduled: <?php echo date("d M Y", strtotime($resp['appointment_date'])); ?>
                                                            <?php if (!empty($resp['appointment_time'])) echo ' at ' . date("h:i A", strtotime($resp['appointment_time'])); ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="text-align: right;">
                                                    <span class="badge badge-<?php echo htmlspecialchars($resp_status); ?>">
                                                        <?php
                                                        if ($resp_status === 'completed') {
                                                            echo "Donated (" . intval($resp['donated_units'] ?? 1) . " u)";
                                                        } elseif ($resp_status === 'confirmed') {
                                                            echo "Confirmed";
                                                        } elseif ($resp_status === 'cancelled') {
                                                            echo "Cancelled";
                                                        } else {
                                                            echo "Scheduled";
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>

                            <!-- CARD FOOTER -->
                            <div class="request-card-top" style="border-bottom: none; padding-top: 14px; font-size: 11px; color: var(--muted);">
                                <span>Submitted: <?php echo date("d M Y, h:i A", strtotime($request['created_at'])); ?></span>
                            </div>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

        </section>

    </main>

</div>

</body>

</html>