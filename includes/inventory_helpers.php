<?php
/**
 * LIFELINE — Blood Inventory Helpers
 * Centralized Blood Stock, Threshold Management, and Inventory Transactions
 */

if (!defined('STOCK_THRESHOLD_HEALTHY')) {
    define('STOCK_THRESHOLD_HEALTHY', 10);
}
if (!defined('STOCK_THRESHOLD_LOW')) {
    define('STOCK_THRESHOLD_LOW', 5);
}

/**
 * Standard 8 blood groups supported by LIFELINE
 */
function get_supported_blood_groups()
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

/**
 * Auto-initialize blood stock tables and initial rows if not present
 */
function init_inventory_tables($conn)
{
    if (!$conn) {
        return false;
    }

    // 1. blood_stock table
    $sql1 = "CREATE TABLE IF NOT EXISTS blood_stock (
        stock_id INT AUTO_INCREMENT PRIMARY KEY,
        blood_group VARCHAR(5) NOT NULL UNIQUE,
        units_available INT NOT NULL DEFAULT 0,
        reserved_units INT NOT NULL DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql1);

    // If blood_stock existed from legacy schema without reserved_units, add it safely
    $res_col = mysqli_query($conn, "SHOW COLUMNS FROM blood_stock LIKE 'reserved_units'");
    if ($res_col && mysqli_num_rows($res_col) === 0) {
        @mysqli_query($conn, "ALTER TABLE blood_stock ADD COLUMN reserved_units INT NOT NULL DEFAULT 0 AFTER units_available");
    }

    // 2. inventory_transactions table
    $sql2 = "CREATE TABLE IF NOT EXISTS inventory_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        blood_group VARCHAR(5) NOT NULL,
        transaction_type ENUM('DONATION', 'ALLOCATION', 'ADJUSTMENT') NOT NULL,
        units INT NOT NULL,
        reference_id INT DEFAULT NULL,
        reference_note VARCHAR(255) DEFAULT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bg (blood_group),
        INDEX idx_type (transaction_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql2);

    /*
     * Seed all 8 blood groups if they do not already exist.
     *
     * TiDB production compatibility:
     * stock_id is not automatically generated in the imported
     * production table, so generate it explicitly when a row
     * needs to be inserted.
     */

    $groups = get_supported_blood_groups();

    foreach ($groups as $bg) {

        // Check whether this blood group already exists.
        $check = mysqli_prepare(
            $conn,
            "SELECT stock_id
             FROM blood_stock
             WHERE blood_group = ?
             LIMIT 1"
        );

        if (!$check) {
            continue;
        }

        mysqli_stmt_bind_param(
            $check,
            "s",
            $bg
        );

        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);

        $existing = mysqli_fetch_assoc($result);

        mysqli_stmt_close($check);

        // Already exists — leave the existing stock untouched.
        if ($existing) {
            continue;
        }

        // Generate the next stock ID manually for TiDB.
        $id_result = mysqli_query(
            $conn,
            "SELECT COALESCE(MAX(stock_id), 0) + 1 AS next_id
             FROM blood_stock"
        );

        if (!$id_result) {
            continue;
        }

        $id_row = mysqli_fetch_assoc($id_result);

        $new_stock_id =
            (int)($id_row['next_id'] ?? 1);

        if ($new_stock_id < 1) {
            $new_stock_id = 1;
        }

        // Insert the missing blood-group stock row.
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO blood_stock
            (
                stock_id,
                blood_group,
                units_available,
                reserved_units
            )
            VALUES (?, ?, 0, 0)"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "is",
                $new_stock_id,
                $bg
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);
        }
    }

    return true;
}

/**
 * Return health status of a blood stock quantity
 * - Healthy: 10+ units
 * - Low: 5-9 units
 * - Critical: 0-4 units
 */
function get_blood_stock_status($available_units)
{
    $units = (int)$available_units;
    if ($units >= STOCK_THRESHOLD_HEALTHY) {
        return 'healthy';
    } elseif ($units >= STOCK_THRESHOLD_LOW) {
        return 'low';
    } else {
        return 'critical';
    }
}

/**
 * Get stock record for a single blood group
 */
function get_blood_stock_by_group($conn, $blood_group)
{
    init_inventory_tables($conn);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT stock_id, blood_group, units_available, units_available AS available_units, reserved_units, last_updated, last_updated AS updated_at
         FROM blood_stock
         WHERE blood_group = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $blood_group);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) {
        $row['available_units'] = (int)$row['units_available'];
        $row['status'] = get_blood_stock_status($row['available_units']);
    }
    return $row;
}

/**
 * Get all 8 blood stock records ordered canonically
 */
function get_all_blood_stock($conn)
{
    init_inventory_tables($conn);

    $sql = "SELECT stock_id, blood_group, units_available, units_available AS available_units, reserved_units, last_updated, last_updated AS updated_at
            FROM blood_stock
            ORDER BY FIELD(blood_group, 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')";
    $res = mysqli_query($conn, $sql);

    $stock = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['available_units'] = (int)$row['units_available'];
            $row['status'] = get_blood_stock_status($row['available_units']);
            $stock[$row['blood_group']] = $row;
        }
    }

    // Ensure all 8 groups are represented
    foreach (get_supported_blood_groups() as $bg) {
        if (!isset($stock[$bg])) {
            $stock[$bg] = [
                'stock_id' => 0,
                'blood_group' => $bg,
                'available_units' => 0,
                'reserved_units' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'status' => 'critical'
            ];
        }
    }

    return $stock;
}

/**
 * Get aggregated inventory statistics
 */
function get_inventory_summary($conn)
{
    $all_stock = get_all_blood_stock($conn);

    $total_available = 0;
    $total_reserved = 0;
    $healthy_count = 0;
    $low_count = 0;
    $critical_count = 0;

    foreach ($all_stock as $item) {
        $total_available += (int)$item['available_units'];
        $total_reserved += (int)$item['reserved_units'];
        if ($item['status'] === 'healthy') {
            $healthy_count++;
        } elseif ($item['status'] === 'low') {
            $low_count++;
        } else {
            $critical_count++;
        }
    }

    return [
        'total_available' => $total_available,
        'total_reserved' => $total_reserved,
        'healthy_count' => $healthy_count,
        'low_count' => $low_count,
        'critical_count' => $critical_count
    ];
}

/**
 * Atomically increase blood inventory when a donation is completed
 * Must be executed inside the caller's transaction
 *
 * @param mysqli $conn
 * @param string $blood_group
 * @param int $units
 * @param int|null $donation_id
 * @param int|null $user_id
 * @return bool
 * @throws Exception
 */
function add_donation_to_inventory($conn, $blood_group, $units, $donation_id = null, $user_id = null)
{
    init_inventory_tables($conn);

    $units = intval($units);
    if ($units <= 0) {
        throw new Exception("Invalid donation units for inventory update.");
    }

    // 1. Update available units
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE blood_stock
         SET units_available = units_available + ?
         WHERE blood_group = ?"
    );
    if (!$stmt) {
        throw new Exception("Failed to prepare inventory update query.");
    }

    mysqli_stmt_bind_param($stmt, "is", $units, $blood_group);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to update blood stock for group $blood_group.");
    }
    mysqli_stmt_close($stmt);

    // 2. Log transaction
    $type = 'DONATION';
    $ref_note = $donation_id ? "Donation #$donation_id" : "Completed Donation";
    $log = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_transactions
         (blood_group, transaction_type, units, reference_id, reference_note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$log) {
        throw new Exception("Failed to prepare inventory transaction log.");
    }

    mysqli_stmt_bind_param($log, "ssiisi", $blood_group, $type, $units, $donation_id, $ref_note, $user_id);
    if (!mysqli_stmt_execute($log)) {
        mysqli_stmt_close($log);
        throw new Exception("Failed to log inventory transaction.");
    }
    mysqli_stmt_close($log);

    return true;
}

/**
 * Allocate inventory to fulfill a blood request
 * Decreases available_units; prevents negative stock
 *
 * @param mysqli $conn
 * @param string $blood_group
 * @param int $units
 * @param int|null $request_id
 * @param int|null $user_id
 * @return bool
 * @throws Exception
 */
function allocate_inventory_for_request($conn, $blood_group, $units, $request_id = null, $user_id = null)
{
    init_inventory_tables($conn);

    $units = intval($units);
    if ($units <= 0) {
        throw new Exception("Units to allocate must be greater than zero.");
    }

    // Verify stock
    $stock = get_blood_stock_by_group($conn, $blood_group);
    if (!$stock || $stock['available_units'] < $units) {
        $available = $stock ? $stock['available_units'] : 0;
        throw new Exception("Insufficient stock for group $blood_group. Available: $available, Requested: $units.");
    }

    // Update stock
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE blood_stock
         SET units_available = units_available - ?
         WHERE blood_group = ? AND units_available >= ? /* available_units >= ? */"
    );
    if (!$stmt) {
        throw new Exception("Failed to prepare allocation query.");
    }

    mysqli_stmt_bind_param($stmt, "isi", $units, $blood_group, $units);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 1) {
        mysqli_stmt_close($stmt);
        throw new Exception("Allocation failed due to stock change. Please retry.");
    }
    mysqli_stmt_close($stmt);

    // Log transaction
    $type = 'ALLOCATION';
    $ref_note = $request_id ? "Request #$request_id Fulfillment" : "Request Allocation";
    $log = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_transactions
         (blood_group, transaction_type, units, reference_id, reference_note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if ($log) {
        $neg_units = -$units;
        mysqli_stmt_bind_param($log, "ssiisi", $blood_group, $type, $neg_units, $request_id, $ref_note, $user_id);
        mysqli_stmt_execute($log);
        mysqli_stmt_close($log);
    }

    return true;
}

/**
 * Manual administrative adjustment of blood stock
 *
 * @param mysqli $conn
 * @param string $blood_group
 * @param int $units (positive integer)
 * @param string $adjustment_type ('ADD' or 'DEDUCT')
 * @param string $reason
 * @param int|null $user_id
 * @return bool
 * @throws Exception
 */
function manual_stock_adjustment($conn, $blood_group, $units, $adjustment_type, $reason, $user_id = null)
{
    init_inventory_tables($conn);

    $units = intval($units);
    if ($units <= 0) {
        throw new Exception("Adjustment units must be a positive integer.");
    }

    $valid_groups = get_supported_blood_groups();
    if (!in_array($blood_group, $valid_groups)) {
        throw new Exception("Invalid blood group specified.");
    }

    $adjustment_type = strtoupper(trim($adjustment_type));
    if ($adjustment_type !== 'ADD' && $adjustment_type !== 'DEDUCT') {
        throw new Exception("Adjustment type must be ADD or DEDUCT.");
    }

    if (empty(trim($reason))) {
        throw new Exception("A clear audit reason is required for manual stock adjustments.");
    }

    mysqli_begin_transaction($conn);

    try {
        if ($adjustment_type === 'DEDUCT') {
            $stock = get_blood_stock_by_group($conn, $blood_group);
            if (!$stock || $stock['available_units'] < $units) {
                $avail = $stock ? $stock['available_units'] : 0;
                throw new Exception("Cannot deduct $units units. Only $avail units available for $blood_group.");
            }

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE blood_stock
                 SET units_available = units_available - ?
                 WHERE blood_group = ? AND units_available >= ?"
            );
            mysqli_stmt_bind_param($stmt, "isi", $units, $blood_group, $units);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $signed_units = -$units;
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE blood_stock
                 SET units_available = units_available + ?
                 WHERE blood_group = ?"
            );
            mysqli_stmt_bind_param($stmt, "is", $units, $blood_group);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $signed_units = $units;
        }

        // Log transaction
        $type = 'ADJUSTMENT';
        $log = mysqli_prepare(
            $conn,
            "INSERT INTO inventory_transactions
             (blood_group, transaction_type, units, reference_note, created_by)
             VALUES (?, ?, ?, ?, ?)"
        );
        $note = "[$adjustment_type] " . trim($reason);
        mysqli_stmt_bind_param($log, "ssisi", $blood_group, $type, $signed_units, $note, $user_id);
        mysqli_stmt_execute($log);
        mysqli_stmt_close($log);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Get recent inventory transactions
 */
function get_inventory_transactions($conn, $limit = 50)
{
    init_inventory_tables($conn);

    $limit = intval($limit);
    if ($limit <= 0) {
        $limit = 50;
    }

    $sql = "SELECT t.transaction_id, t.blood_group, t.transaction_type, t.units,
                   t.reference_id, t.reference_note, t.created_by, t.created_at,
                   u.name AS user_name, u.role AS user_role
            FROM inventory_transactions t
            LEFT JOIN users u ON t.created_by = u.user_id
            ORDER BY t.created_at DESC, t.transaction_id DESC
            LIMIT $limit";

    $res = mysqli_query($conn, $sql);
    $transactions = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $transactions[] = $row;
        }
    }
    return $transactions;
}
