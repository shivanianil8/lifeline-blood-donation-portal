<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'donor')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/request_helpers.php";
include "../includes/inventory_helpers.php";
include "../includes/notification_helpers.php";


/* =========================
   GET DONOR ID
========================= */

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT donor_id
     FROM donors
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

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


$message = "";
$message_type = "";


/* =========================
   CANCEL APPOINTMENT ACTION
========================= */

if (isset($_POST['cancel_appointment']))
{
    $appointment_id = intval($_POST['appointment_id'] ?? 0);

    if ($appointment_id < 1 || $donor_id < 1)
    {
        $message = "Invalid appointment.";
        $message_type = "error";
    }
    else
    {
        // 1. Fetch appointment
        $stmt = mysqli_prepare(
            $conn,
            "SELECT appointment_id, request_id, status
             FROM appointments
             WHERE appointment_id = ?
             AND donor_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $donor_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $appt = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$appt)
        {
            $message = "Appointment not found or unauthorized.";
            $message_type = "error";
        }
        elseif (strtolower($appt['status']) === 'completed')
        {
            $message = "Completed donations cannot be cancelled.";
            $message_type = "error";
        }
        elseif (strtolower($appt['status']) === 'cancelled')
        {
            $message = "This appointment is already cancelled.";
            $message_type = "error";
        }
        else
        {
            $req_id = (int)$appt['request_id'];

            mysqli_begin_transaction($conn);

            try
            {
                // Update appointment status to cancelled
                $up = mysqli_prepare(
                    $conn,
                    "UPDATE appointments
                     SET status = 'cancelled'
                     WHERE appointment_id = ?
                     AND donor_id = ?"
                );

                mysqli_stmt_bind_param($up, "ii", $appointment_id, $donor_id);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);

                // Recalculate request status: if 0 completed donations and 0 other active appointments, returns to PENDING
                sync_request_status($conn, $req_id);

                // Notify recipient
                $rec_stmt = mysqli_prepare($conn, "SELECT r.user_id FROM blood_requests br JOIN recipients r ON br.recipient_id = r.recipient_id OR br.recipient_id = r.user_id WHERE br.request_id = ? LIMIT 1");
                if ($rec_stmt) {
                    mysqli_stmt_bind_param($rec_stmt, "i", $req_id);
                    mysqli_stmt_execute($rec_stmt);
                    $rec_res = mysqli_stmt_get_result($rec_stmt);
                    $rec_row = mysqli_fetch_assoc($rec_res);
                    mysqli_stmt_close($rec_stmt);
                    if ($rec_row && !empty($rec_row['user_id'])) {
                        create_notification($conn, (int)$rec_row['user_id'], "An appointment for your blood request #$req_id was cancelled by the donor.", 'cancellation', $req_id);
                    }
                }
                // Notify donor
                create_notification($conn, $user_id, "Your appointment #$appointment_id has been cancelled.", 'cancellation', $appointment_id);

                mysqli_commit($conn);

                $message = "Appointment #$appointment_id has been cancelled.";
                $message_type = "success";
            }
            catch (Exception $e)
            {
                mysqli_rollback($conn);
                $message = "Unable to cancel appointment. Please try again.";
                $message_type = "error";
            }
        }
    }
}


/* =========================
   COMPLETE DONATION ACTION
========================= */

if (isset($_POST['complete_donation']))
{
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $input_units = max(1, intval($_POST['units_donated'] ?? 1));

    if ($appointment_id < 1 || $donor_id < 1)
    {
        $message = "Invalid appointment.";
        $message_type = "error";
    }
    else
    {
        /*
         * Get appointment + matching blood request.
         */
        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                a.appointment_id,
                a.request_id,
                a.hospital,
                a.appointment_date,
                a.status AS appointment_status,
                br.blood_group,
                br.units_required,
                br.status AS request_status
             FROM appointments a
             INNER JOIN blood_requests br
                ON a.request_id = br.request_id
             WHERE a.appointment_id = ?
             AND a.donor_id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $appointment_id,
            $donor_id
        );

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$appointment)
        {
            $message = "Appointment not found or unauthorized.";
            $message_type = "error";
        }
        elseif (strtolower($appointment['appointment_status']) === 'cancelled')
        {
            $message = "A cancelled appointment cannot be completed.";
            $message_type = "error";
        }
        elseif (strtolower($appointment['appointment_status']) === 'completed')
        {
            $message = "This appointment has already been completed.";
            $message_type = "error";
        }
        elseif (strtolower($appointment['request_status']) === 'cancelled')
        {
            $message = "The underlying blood request has been cancelled.";
            $message_type = "error";
        }
        else
        {
            /*
             * Check whether this appointment already has a donation record.
             */
            $stmt = mysqli_prepare(
                $conn,
                "SELECT donation_id
                 FROM donations
                 WHERE appointment_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, "i", $appointment_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $existing_donation = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($existing_donation)
            {
                $message = "This donation has already been recorded.";
                $message_type = "error";
            }
            else
            {
                $req_id = (int)$appointment['request_id'];
                $summary = get_request_units_summary($conn, $req_id);
                $remaining_units = $summary['remaining'];

                if ($remaining_units <= 0 || strtolower($appointment['request_status']) === 'fulfilled')
                {
                    $message = "This request has already been fully fulfilled.";
                    $message_type = "error";
                }
                else
                {
                    // Never allow donation to exceed remaining units
                    $donated_units = 1;
                    if (isset($_POST['units_donated']))
                    {
                        $donated_units = min($input_units, $remaining_units);
                    }

                    mysqli_begin_transaction($conn);

                    try
                    {
                        // 1. Generate donation ID manually for TiDB
                        $donation_id_result = mysqli_query(
                            $conn,
                            "SELECT COALESCE(MAX(donation_id), 0) + 1 AS next_id
                             FROM donations"
                        );

                        if (!$donation_id_result)
                        {
                            throw new Exception("Unable to generate donation ID.");
                        }

                        $donation_id_row = mysqli_fetch_assoc($donation_id_result);

                        $donation_id = (int)($donation_id_row['next_id'] ?? 1);

                        if ($donation_id < 1)
                        {
                            $donation_id = 1;
                        }

                        // Insert donation
                        $stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO donations
                            (
                                donation_id,
                                donor_id,
                                appointment_id,
                                blood_group,
                                units,
                                donation_date,
                                hospital
                            )
                            VALUES
                            (?, ?, ?, ?, ?, CURDATE(), ?)"
                        );

                        if (!$stmt)
                        {
                            throw new Exception("Unable to prepare donation record.");
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            "iiisis",
                            $donation_id,
                            $donor_id,
                            $appointment_id,
                            $appointment['blood_group'],
                            $donated_units,
                            $appointment['hospital']
                        );

                        if (!mysqli_stmt_execute($stmt))
                        {
                            throw new Exception(mysqli_stmt_error($stmt));
                        }

                        mysqli_stmt_close($stmt);

                        // Atomically increment blood inventory for this blood group
                        add_donation_to_inventory($conn, $appointment['blood_group'], $donated_units, $donation_id, $user_id);

                        // 2. Update appointment status to completed
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE appointments
                             SET status = 'completed'
                             WHERE appointment_id = ?
                             AND donor_id = ?"
                        );

                        if (!$stmt)
                        {
                            throw new Exception("Unable to prepare appointment update.");
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            "ii",
                            $appointment_id,
                            $donor_id
                        );

                        if (!mysqli_stmt_execute($stmt))
                        {
                            throw new Exception(mysqli_stmt_error($stmt));
                        }

                        mysqli_stmt_close($stmt);

                        // 3. Update donor's last donation date
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE donors
                             SET last_donation_date = CURDATE()
                             WHERE donor_id = ?"
                        );

                        if (!$stmt)
                        {
                            throw new Exception("Unable to prepare donor date update.");
                        }

                        mysqli_stmt_bind_param($stmt, "i", $donor_id);

                        if (!mysqli_stmt_execute($stmt))
                        {
                            throw new Exception(mysqli_stmt_error($stmt));
                        }

                        mysqli_stmt_close($stmt);

                        // 4. Update request status based on total fulfilled units
                        $new_total_fulfilled = $summary['fulfilled'] + $donated_units;
                        $total_required = $summary['required'];

                        $new_req_status = ($new_total_fulfilled >= $total_required) ? 'fulfilled' : 'partially_fulfilled';

                        $up_req = mysqli_prepare(
                            $conn,
                            "UPDATE blood_requests
                             SET status = ?
                             WHERE request_id = ?"
                        );

                        mysqli_stmt_bind_param($up_req, "si", $new_req_status, $req_id);
                        mysqli_stmt_execute($up_req);
                        mysqli_stmt_close($up_req);

                        // 5. Notifications
                        $rec_stmt = mysqli_prepare($conn, "SELECT r.user_id FROM blood_requests br JOIN recipients r ON br.recipient_id = r.recipient_id OR br.recipient_id = r.user_id WHERE br.request_id = ? LIMIT 1");
                        if ($rec_stmt) {
                            mysqli_stmt_bind_param($rec_stmt, "i", $req_id);
                            mysqli_stmt_execute($rec_stmt);
                            $rec_res = mysqli_stmt_get_result($rec_stmt);
                            $rec_row = mysqli_fetch_assoc($rec_res);
                            mysqli_stmt_close($rec_stmt);
                            if ($rec_row && !empty($rec_row['user_id'])) {
                                $rec_uid = (int)$rec_row['user_id'];
                                if ($new_req_status === 'fulfilled') {
                                    create_notification($conn, $rec_uid, "Congratulations! Your blood request #$req_id has been fully fulfilled ($new_total_fulfilled/$total_required units).", 'fulfillment', $req_id);
                                } else {
                                    create_notification($conn, $rec_uid, "A donation of $donated_units unit(s) has been recorded for your blood request #$req_id ($new_total_fulfilled/$total_required units).", 'donation', $req_id);
                                }
                            }
                        }

                        // Notify donor
                        create_notification($conn, $user_id, "Your donation of $donated_units unit(s) at " . htmlspecialchars($appointment['hospital']) . " has been recorded. Thank you for saving lives!", 'donation', $appointment_id);

                        // 6. Commit atomic transaction
                        mysqli_commit($conn);

                        $message = "Donation of $donated_units unit(s) recorded successfully! Thank you for saving lives.";
                        $message_type = "success";
                    }
                    catch (Exception $e)
                    {
                        mysqli_rollback($conn);
                        $message = "Failed to complete donation: " . $e->getMessage();
                        $message_type = "error";
                    }
                }
            }
        }
    }
}


/* =========================
   GET DONOR APPOINTMENTS
========================= */

$appointments = [];

if ($donor_id > 0)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            a.appointment_id,
            a.request_id,
            a.hospital,
            a.appointment_date,
            a.appointment_time,
            a.status,
            br.blood_group,
            br.units_required,
            br.status AS request_status,
            COALESCE(don.units, 0) AS completed_units,
            don.donation_date
         FROM appointments a
         INNER JOIN blood_requests br
            ON a.request_id = br.request_id
         LEFT JOIN donations don
            ON a.appointment_id = don.appointment_id
         WHERE a.donor_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );

    if ($stmt)
    {
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $donor_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result))
        {
            $appointments[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
}

include "donor-layout.php";

?>

<section class="content">

    <!-- PAGE HEADING -->
    <div class="page-heading">
        <p class="eyebrow">
            APPOINTMENTS
        </p>

        <h1>
            Your appointments
        </h1>

        <p class="subtitle">
            Manage your scheduled blood donation appointments and confirm completed donations.
        </p>
    </div>

    <!-- MESSAGE FEEDBACK -->
    <?php if ($message != "") { ?>
        <div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php } ?>

    <!-- APPOINTMENTS LIST -->
    <?php if (count($appointments) > 0) { ?>

        <div class="request-list">

            <?php foreach ($appointments as $appointment) { ?>

                <?php
                $status = strtolower($appointment['status'] ?? 'pending');
                $req_id = (int)$appointment['request_id'];
                $summary = get_request_units_summary($conn, $req_id);
                $remaining_for_req = $summary['remaining'];
                $is_active = in_array($status, ['pending', 'confirmed']);
                ?>

                <div class="request-card">

                    <!-- TOP -->
                    <div class="request-card-top">
                        <div>
                            <div class="request-blood-group">
                                <?php echo htmlspecialchars($appointment['blood_group'] ?? 'DONATION'); ?>
                            </div>
                            <span class="request-units">
                                Appointment #<?php echo htmlspecialchars($appointment['appointment_id']); ?>
                            </span>
                        </div>

                        <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                            <?php
                            if ($status === 'completed') {
                                echo "Completed (" . intval($appointment['completed_units'] > 0 ? $appointment['completed_units'] : 1) . " u)";
                            } elseif ($status === 'confirmed') {
                                echo "Confirmed";
                            } elseif ($status === 'cancelled') {
                                echo "Cancelled";
                            } else {
                                echo "Scheduled";
                            }
                            ?>
                        </span>
                    </div>

                    <!-- MAIN INFO -->
                    <div class="request-main">
                        <h2><?php echo htmlspecialchars($appointment['hospital']); ?></h2>
                        <p class="request-location">
                            Blood Request #<?php echo $req_id; ?> &nbsp;·&nbsp;
                            Need: <strong><?php echo $summary['fulfilled']; ?> / <?php echo $summary['required']; ?> units fulfilled</strong>
                            (<?php echo $remaining_for_req; ?> units still needed)
                        </p>
                    </div>

                    <!-- DETAILS -->
                    <div class="request-details">
                        <div>
                            <span>DATE</span>
                            <strong>
                                <?php echo date("d M Y", strtotime($appointment['appointment_date'])); ?>
                            </strong>
                        </div>

                        <div>
                            <span>TIME</span>
                            <strong>
                                <?php
                                if (!empty($appointment['appointment_time'])) {
                                    echo date("h:i A", strtotime($appointment['appointment_time']));
                                } else {
                                    echo "10:00 AM";
                                }
                                ?>
                            </strong>
                        </div>

                        <div>
                            <span>UNITS NEEDED</span>
                            <strong>
                                <?php echo $remaining_for_req; ?> unit(s)
                            </strong>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div class="request-action" style="padding-top: 18px;">
                        <?php if ($is_active) { ?>
                            <?php if ($remaining_for_req > 0) { ?>
                                <form
                                    method="POST"
                                    onsubmit="return confirm('Confirm that you have completed this blood donation?');"
                                    style="display: flex; flex-direction: column; gap: 10px;"
                                >
                                    <input
                                        type="hidden"
                                        name="appointment_id"
                                        value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                    >

                                    <?php if ($remaining_for_req > 1) { ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #555;">
                                            <label for="units_<?php echo $appointment['appointment_id']; ?>">Units Donated:</label>
                                            <input
                                                type="number"
                                                id="units_<?php echo $appointment['appointment_id']; ?>"
                                                name="units_donated"
                                                min="1"
                                                max="<?php echo $remaining_for_req; ?>"
                                                value="1"
                                                style="width: 70px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px;"
                                                required
                                            >
                                        </div>
                                    <?php } else { ?>
                                        <input type="hidden" name="units_donated" value="1">
                                    <?php } ?>

                                    <button
                                        type="submit"
                                        name="complete_donation"
                                        class="respond-button"
                                        style="background: var(--success); width: 100%;"
                                    >
                                        ✓ Mark Donation as Completed
                                    </button>
                                </form>
                            <?php } else { ?>
                                <div style="font-size: 12px; color: var(--muted); padding: 8px 0; font-style: italic;">
                                    This request has reached full capacity.
                                </div>
                            <?php } ?>

                            <!-- CANCEL APPOINTMENT FORM -->
                            <form
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to cancel this scheduled appointment?');"
                                style="margin-top: 8px;"
                            >
                                <input
                                    type="hidden"
                                    name="appointment_id"
                                    value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                >
                                <button
                                    type="submit"
                                    name="cancel_appointment"
                                    class="btn-cancel-request"
                                    style="width: 100%; text-align: center; padding: 8px;"
                                >
                                    Cancel Scheduled Appointment
                                </button>
                            </form>

                        <?php } elseif ($status === 'completed') { ?>
                            <div style="padding: 10px; background: #EAF3ED; border: 1px solid #C8E2D2; border-radius: 8px; font-size: 12px; color: var(--success); text-align: center; font-weight: 600;">
                                ✓ Donation Completed
                                <?php if (!empty($appointment['donation_date'])) { ?>
                                    on <?php echo date("d M Y", strtotime($appointment['donation_date'])); ?>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div style="padding: 10px; background: #FAF8F5; border: 1px solid #EBE7E1; border-radius: 8px; font-size: 12px; color: var(--muted); text-align: center;">
                                Appointment Cancelled
                            </div>
                        <?php } ?>
                    </div>

                </div>

            <?php } ?>

        </div>

    <?php } else { ?>

        <div class="requests-empty-card">
            <div class="empty-icon">
                📅
            </div>
            <h2>No appointments scheduled</h2>
            <p>
                You haven't scheduled any blood donation appointments yet. Browse matching blood requests and click "I can donate" to schedule an appointment.
            </p>
            <a href="requests.php" class="primary-button" style="margin-top: 14px;">
                Browse Blood Requests →
            </a>
        </div>

    <?php } ?>

</section>

</main>
</div>
</body>
</html>