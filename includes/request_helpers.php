<?php
/**
 * LIFELINE — Blood Request Workflow Helpers
 * (includes/request_helpers.php)
 * 
 * Provides centralized, normalized logic for multi-donor, multi-unit fulfillment
 * and status synchronization without database schema alterations.
 */

/**
 * Get total fulfilled units for a blood request by summing completed donations.
 *
 * @param mysqli $conn
 * @param int $request_id
 * @return int
 */
function get_request_fulfilled_units($conn, $request_id)
{
    $request_id = intval($request_id);
    if ($request_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(SUM(d.units), 0) AS fulfilled
         FROM donations d
         INNER JOIN appointments a ON d.appointment_id = a.appointment_id
         WHERE a.request_id = ?
         AND a.status = 'completed'"
    );

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return intval($row['fulfilled'] ?? 0);
}

/**
 * Get count of active (pending or confirmed) appointments for a blood request.
 *
 * @param mysqli $conn
 * @param int $request_id
 * @return int
 */
function get_request_active_appointments_count($conn, $request_id)
{
    $request_id = intval($request_id);
    if ($request_id <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS active_count
         FROM appointments
         WHERE request_id = ?
         AND status IN ('pending', 'confirmed')"
    );

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return intval($row['active_count'] ?? 0);
}

/**
 * Recalculates and synchronizes the official status of a blood request.
 * 
 * Lifecycle rules:
 * - If status is 'cancelled', remains 'cancelled' unless explicitly reopened.
 * - If fulfilled_units >= units_required => 'fulfilled'
 * - If fulfilled_units > 0 and < units_required => 'partially_fulfilled'
 * - If active_appointments > 0 and fulfilled_units == 0 => 'matched'
 * - If no active appointments and fulfilled_units == 0 => 'pending'
 *
 * @param mysqli $conn
 * @param int $request_id
 * @return string Current status
 */
function sync_request_status($conn, $request_id)
{
    $request_id = intval($request_id);
    if ($request_id <= 0) {
        return 'pending';
    }

    // 1. Fetch current request status and units_required
    $stmt = mysqli_prepare(
        $conn,
        "SELECT units_required, status
         FROM blood_requests
         WHERE request_id = ?"
    );

    if (!$stmt) {
        return 'pending';
    }

    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $req = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$req) {
        return 'pending';
    }

    $current_status = strtolower($req['status'] ?? 'pending');
    if (in_array($current_status, ['cancelled', 'rejected'], true)) {
        return $current_status;
    }

    $required = max(1, intval($req['units_required']));
    $fulfilled = get_request_fulfilled_units($conn, $request_id);
    $active_appointments = get_request_active_appointments_count($conn, $request_id);

    $new_status = 'pending';
    if ($fulfilled >= $required) {
        $new_status = 'fulfilled';
    } elseif ($fulfilled > 0) {
        $new_status = 'partially_fulfilled';
    } elseif ($active_appointments > 0) {
        $new_status = 'matched';
    } else {
        $new_status = 'pending';
    }

    // List of allowed DB statuses to ensure we NEVER violate strict SQL mode
    $allowed_db_statuses = [
        'pending', 'matched', 'partially_fulfilled', 'fulfilled', 'cancelled', 'approved', 'rejected', 'completed'
    ];

    if (!in_array($new_status, $allowed_db_statuses, true)) {
        $new_status = 'pending';
    }

    // Only update if changed
    if ($new_status !== $current_status) {
        try {
            $up = mysqli_prepare(
                $conn,
                "UPDATE blood_requests
                 SET status = ?
                 WHERE request_id = ?"
            );

            if ($up) {
                mysqli_stmt_bind_param($up, "si", $new_status, $request_id);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
        } catch (Throwable $e) {
            error_log("sync_request_status update failed for request $request_id: " . $e->getMessage());
            // Safe fallback to legacy compatible values if needed
            $fallback = ($new_status === 'fulfilled') ? 'completed' : 'pending';
            if ($fallback !== $current_status) {
                try {
                    $fb_stmt = mysqli_prepare($conn, "UPDATE blood_requests SET status = ? WHERE request_id = ?");
                    if ($fb_stmt) {
                        mysqli_stmt_bind_param($fb_stmt, "si", $fallback, $request_id);
                        mysqli_stmt_execute($fb_stmt);
                        mysqli_stmt_close($fb_stmt);
                    }
                } catch (Throwable $ignore) {}
            }
        }
    }

    return $new_status;
}

/**
 * Get comprehensive unit summary for a request.
 *
 * @param mysqli $conn
 * @param int $request_id
 * @return array ['required', 'fulfilled', 'remaining', 'percent', 'status', 'active_appointments']
 */
function get_request_units_summary($conn, $request_id)
{
    $request_id = intval($request_id);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT units_required, status
         FROM blood_requests
         WHERE request_id = ?"
    );

    $required = 1;
    $status = 'pending';

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $required = max(1, intval($row['units_required']));
            $status = strtolower($row['status'] ?? 'pending');
        }
        mysqli_stmt_close($stmt);
    }

    $fulfilled = get_request_fulfilled_units($conn, $request_id);
    $remaining = max(0, $required - $fulfilled);
    $percent = min(100, intval(round(($fulfilled / $required) * 100)));

    // Re-verify status alignment
    if ($status !== 'cancelled') {
        if ($fulfilled >= $required && $status !== 'fulfilled') {
            $status = sync_request_status($conn, $request_id);
        } elseif ($fulfilled > 0 && $fulfilled < $required && $status !== 'partially_fulfilled') {
            $status = sync_request_status($conn, $request_id);
        }
    }

    return [
        'required' => $required,
        'fulfilled' => $fulfilled,
        'remaining' => $remaining,
        'percent' => $percent,
        'status' => $status
    ];
}

/**
 * Fetch all donor responses for a blood request to display to recipient.
 * Only returns appropriate information (donor name, blood group, appointment date/time, status, donated units).
 * Does not expose sensitive private information.
 *
 * @param mysqli $conn
 * @param int $request_id
 * @return array
 */
function get_donor_responses_for_request($conn, $request_id)
{
    $request_id = intval($request_id);
    if ($request_id <= 0) {
        return [];
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            a.appointment_id,
            a.donor_id,
            a.appointment_date,
            a.appointment_time,
            a.status AS appointment_status,
            u.name AS donor_name,
            d.blood_group AS donor_blood_group,
            COALESCE(don.units, 0) AS donated_units,
            don.donation_date
         FROM appointments a
         INNER JOIN donors d ON a.donor_id = d.donor_id
         INNER JOIN users u ON d.user_id = u.user_id
         LEFT JOIN donations don ON a.appointment_id = don.appointment_id
         WHERE a.request_id = ?
         ORDER BY a.appointment_date ASC, a.appointment_time ASC"
    );

    $responses = [];
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $responses[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    return $responses;
}
