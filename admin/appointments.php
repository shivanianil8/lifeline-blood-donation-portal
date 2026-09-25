<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/request_helpers.php";
include "../includes/notification_helpers.php";

$message = "";
$message_type = "";

/* =====================================================
   HANDLE APPOINTMENT ACTIONS (CONFIRM / CANCEL)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $appt_id = intval($_POST['appointment_id'] ?? 0);

    if ($appt_id > 0 && isset($_POST['action']))
    {
        $action = trim($_POST['action']);

        // Fetch appointment details
        $stmt = mysqli_prepare(
            $conn,
            "SELECT a.appointment_id, a.request_id, a.donor_id, a.hospital, a.appointment_date, a.status,
                    d.user_id AS donor_user_id,
                    br.recipient_id
             FROM appointments a
             JOIN donors d ON a.donor_id = d.donor_id
             JOIN blood_requests br ON a.request_id = br.request_id
             WHERE a.appointment_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $appt_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $appt = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$appt)
        {
            $message = "Appointment not found.";
            $message_type = "error";
        }
        elseif ($action === 'confirm')
        {
            if ($appt['status'] !== 'pending')
            {
                $message = "Only pending appointments can be confirmed.";
                $message_type = "error";
            }
            else
            {
                $up = mysqli_prepare($conn, "UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?");
                mysqli_stmt_bind_param($up, "i", $appt_id);
                if (mysqli_stmt_execute($up))
                {
                    create_notification($conn, (int)$appt['donor_user_id'], "Your appointment #$appt_id at " . htmlspecialchars($appt['hospital']) . " has been confirmed by hospital staff.", 'appointment', $appt_id);
                    $message = "Appointment #$appt_id has been confirmed.";
                    $message_type = "success";
                }
                mysqli_stmt_close($up);
            }
        }
        elseif ($action === 'cancel')
        {
            if ($appt['status'] === 'completed')
            {
                $message = "Completed appointments cannot be cancelled.";
                $message_type = "error";
            }
            elseif ($appt['status'] === 'cancelled')
            {
                $message = "This appointment is already cancelled.";
                $message_type = "error";
            }
            else
            {
                mysqli_begin_transaction($conn);
                try
                {
                    $up = mysqli_prepare($conn, "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
                    mysqli_stmt_bind_param($up, "i", $appt_id);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);

                    // Sync request status (returns to pending if no other active appointments/donations)
                    $req_id = (int)$appt['request_id'];
                    sync_request_status($conn, $req_id);

                    // Notify donor
                    create_notification($conn, (int)$appt['donor_user_id'], "Your appointment #$appt_id at " . htmlspecialchars($appt['hospital']) . " has been cancelled by administration.", 'cancellation', $appt_id);

                    // Notify recipient
                    $rec_stmt = mysqli_prepare($conn, "SELECT r.user_id FROM recipients r WHERE r.recipient_id = ? OR r.user_id = ? LIMIT 1");
                    if ($rec_stmt) {
                        $rec_id = (int)$appt['recipient_id'];
                        mysqli_stmt_bind_param($rec_stmt, "ii", $rec_id, $rec_id);
                        mysqli_stmt_execute($rec_stmt);
                        $rec_res = mysqli_stmt_get_result($rec_stmt);
                        $rec_row = mysqli_fetch_assoc($rec_res);
                        mysqli_stmt_close($rec_stmt);
                        if ($rec_row && !empty($rec_row['user_id'])) {
                            create_notification($conn, (int)$rec_row['user_id'], "An appointment for your blood request #$req_id was cancelled by administration.", 'cancellation', $req_id);
                        }
                    }

                    mysqli_commit($conn);
                    $message = "Appointment #$appt_id has been cancelled.";
                    $message_type = "success";
                }
                catch (Exception $e)
                {
                    mysqli_rollback($conn);
                    $message = "Error cancelling appointment: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
}

$status_filter = trim($_GET['status'] ?? 'all');
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'confirmed', 'completed', 'cancelled'])) {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT a.appointment_id, a.request_id, a.hospital, a.appointment_date, a.appointment_time, a.status,
               u.name AS donor_name, u.phone AS donor_phone, d.blood_group AS donor_blood_group,
               br.blood_group AS req_blood_group, br.units_required
        FROM appointments a
        JOIN donors d ON a.donor_id = d.donor_id
        JOIN users u ON d.user_id = u.user_id
        JOIN blood_requests br ON a.request_id = br.request_id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY a.appointment_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$appointments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $appointments[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Appointments Coordination</h1>
            <p class="subtitle">Monitor donor scheduling, confirm upcoming appointments, and coordinate clinical slots.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo count($appointments); ?></strong> appointments
            </span>
        </div>
    </div>

    <!-- FEEDBACK ALERT -->
    <?php if (!empty($message)) { ?>
        <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>" style="margin-bottom: 24px;">
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php } ?>

    <!-- STATUS FILTER -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center;">
            <div style="min-width: 180px;">
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($status_filter === 'all') echo 'selected'; ?>>All Statuses</option>
                    <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="confirmed" <?php if ($status_filter === 'confirmed') echo 'selected'; ?>>Confirmed</option>
                    <option value="completed" <?php if ($status_filter === 'completed') echo 'selected'; ?>>Completed</option>
                    <option value="cancelled" <?php if ($status_filter === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Filter
            </button>

            <?php if ($status_filter !== 'all') { ?>
                <a href="appointments.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($appointments)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">📅</div>
                <h2>No appointments found</h2>
                <p>No appointments matched the specified filter criteria.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">Appt #</th>
                            <th style="padding: 12px 16px;">Donor &amp; Group</th>
                            <th style="padding: 12px 16px;">Hospital &amp; Request</th>
                            <th style="padding: 12px 16px;">Scheduled Date &amp; Time</th>
                            <th style="padding: 12px 16px;">Status</th>
                            <th style="padding: 12px 16px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt) { ?>
                            <?php
                            $st = strtolower($apt['status']);
                            ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px; font-weight: 600; color: var(--muted); font-size: 12px;">
                                    #<?php echo (int)$apt['appointment_id']; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($apt['donor_name']); ?></strong>
                                    <span style="display: inline-block; margin-left: 6px; font-weight: 800; color: var(--burgundy); font-family: var(--font-heading);">
                                        <?php echo htmlspecialchars($apt['donor_blood_group']); ?>
                                    </span>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        <?php echo !empty($apt['donor_phone']) ? htmlspecialchars($apt['donor_phone']) : ''; ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong><?php echo htmlspecialchars($apt['hospital']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        Request #<?php echo (int)$apt['request_id']; ?> (requires <?php echo htmlspecialchars($apt['req_blood_group']); ?>)
                                    </div>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary); font-size: 12px; white-space: nowrap;">
                                    <?php echo date("d M Y", strtotime($apt['appointment_date'])); ?>
                                    <?php if (!empty($apt['appointment_time'])) echo " at " . date("h:i A", strtotime($apt['appointment_time'])); ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span class="badge badge-<?php echo htmlspecialchars($st); ?>">
                                        <?php echo ucfirst($st); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <?php if ($st === 'pending') { ?>
                                        <div style="display: inline-flex; gap: 6px;">
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" style="background: #EBF3FA; border: 1px solid #CCE0F5; color: #2B6CB0; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                                    Confirm
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Cancel this appointment?');" style="margin: 0;">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" style="background: none; border: 1px solid #FECACA; color: #991B1B; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                                    Cancel
                                                </button>
                                            </form>
                                        </div>
                                    <?php } elseif ($st === 'confirmed') { ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this confirmed appointment?');" style="margin: 0;">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['appointment_id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" style="background: none; border: 1px solid #FECACA; color: #991B1B; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php } else { ?>
                                        <span style="font-size: 11px; color: var(--muted);">—</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>

</div>

</main>
</div>
</body>
</html>
