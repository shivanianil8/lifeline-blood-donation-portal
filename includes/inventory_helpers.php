<?php
/**
 * LIFELINE — Blood Inventory Helpers
 * Centralized Blood Stock, Threshold Management, and Inventory Transactions
 *
 * TiDB-compatible version:
 * - Does not depend on AUTO_INCREMENT for imported production tables
 * - Explicitly generates stock_id and transaction_id
 * - Avoids DDL during active business transactions
 */

if (!defined('STOCK_THRESHOLD_HEALTHY')) {
    define('STOCK_THRESHOLD_HEALTHY', 10);
}

if (!defined('STOCK_THRESHOLD_LOW')) {
    define('STOCK_THRESHOLD_LOW', 5);
}


/**
 * Standard 8 blood groups supported by LIFELINE.
 */
function get_supported_blood_groups()
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}


/**
 * Initialize inventory tables and missing blood-group rows.
 *
 * This function is intended for setup/read contexts.
 * Do NOT call this from inside an already-running business transaction.
 */
function init_inventory_tables($conn)
{
    if (!$conn) {
        return false;
    }

    /*
     * 1. blood_stock table
     */
    $sql1 = "CREATE TABLE IF NOT EXISTS blood_stock (
        stock_id INT AUTO_INCREMENT PRIMARY KEY,
        blood_group VARCHAR(5) NOT NULL UNIQUE,
        units_available INT NOT NULL DEFAULT 0,
        reserved_units INT NOT NULL DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    mysqli_query($conn, $sql1);


    /*
     * Add reserved_units if an older schema is being used.
     */
    $res_col = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM blood_stock LIKE 'reserved_units'"
    );

    if ($res_col && mysqli_num_rows($res_col) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE blood_stock
             ADD COLUMN reserved_units INT NOT NULL DEFAULT 0
             AFTER units_available"
        );
    }


    /*
     * 2. inventory_transactions table
     */
    $sql2 = "CREATE TABLE IF NOT EXISTS inventory_transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        blood_group VARCHAR(5) NOT NULL,
        transaction_type ENUM(
            'DONATION',
            'ALLOCATION',
            'ADJUSTMENT'
        ) NOT NULL,
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
     * Seed all 8 blood groups.
     *
     * TiDB compatibility:
     * Explicitly generate stock_id because imported production
     * tables may not have working AUTO_INCREMENT behavior.
     */
    foreach (get_supported_blood_groups() as $bg) {

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

        mysqli_stmt_bind_param($check, "s", $bg);
        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);
        $existing = mysqli_fetch_assoc($result);

        mysqli_stmt_close($check);

        if ($existing) {
            continue;
        }


        /*
         * Generate next stock ID.
         */
        $id_result = mysqli_query(
            $conn,
            "SELECT COALESCE(MAX(stock_id), 0) + 1 AS next_id
             FROM blood_stock"
        );

        if (!$id_result) {
            continue;
        }

        $id_row = mysqli_fetch_assoc($id_result);

        $new_stock_id = (int)($id_row['next_id'] ?? 1);

        if ($new_stock_id < 1) {
            $new_stock_id = 1;
        }


        /*
         * Insert missing blood group.
         */
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
 * Get health status of a blood stock quantity.
 *
 * Healthy: 10+ units
 * Low:      5-9 units
 * Critical: 0-4 units
 */
function get_blood_stock_status($available_units)
{
    $units = (int)$available_units;

    if ($units >= STOCK_THRESHOLD_HEALTHY) {
        return 'healthy';
    } elseif ($units >= STOCK_THRESHOLD_LOW) {
        return 'low';
    }

    return 'critical';
}


/**
 * Internal helper:
 * Get a stock row WITHOUT running table initialization/DDL.
 *
 * Important for use inside transactions.
 */
function get_stock_row_no_init($conn, $blood_group)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            stock_id,
            blood_group,
            units_available,
            reserved_units,
            last_updated
         FROM blood_stock
         WHERE blood_group = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $blood_group);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if ($row) {
        $row['units_available'] = (int)$row['units_available'];
        $row['reserved_units'] = (int)$row['reserved_units'];
        $row['available_units'] = $row['units_available'];
        $row['status'] = get_blood_stock_status(
            $row['available_units']
        );
    }

    return $row;
}


/**
 * Ensure a blood-group stock row exists.
 *
 * IMPORTANT:
 * No CREATE TABLE / ALTER TABLE is performed here.
 * Safe to use inside a transaction.
 */
function ensure_stock_row($conn, $blood_group)
{
    $existing = get_stock_row_no_init(
        $conn,
        $blood_group
    );

    if ($existing) {
        return $existing;
    }


    /*
     * Generate stock_id manually.
     */
    $id_result = mysqli_query(
        $conn,
        "SELECT COALESCE(MAX(stock_id), 0) + 1 AS next_id
         FROM blood_stock"
    );

    if (!$id_result) {
        throw new Exception(
            "Failed to generate blood stock ID."
        );
    }

    $id_row = mysqli_fetch_assoc($id_result);

    $new_stock_id = (int)($id_row['next_id'] ?? 1);

    if ($new_stock_id < 1) {
        $new_stock_id = 1;
    }


    /*
     * Insert stock row.
     */
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

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare blood stock creation."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $new_stock_id,
        $blood_group
    );

    if (!mysqli_stmt_execute($stmt)) {

        $error = mysqli_stmt_error($stmt);

        mysqli_stmt_close($stmt);

        throw new Exception(
            "Failed to create blood stock row: " . $error
        );
    }

    mysqli_stmt_close($stmt);


    return get_stock_row_no_init(
        $conn,
        $blood_group
    );
}


/**
 * Generate next inventory transaction ID.
 *
 * TiDB-compatible because it does not depend on AUTO_INCREMENT.
 */
function get_next_inventory_transaction_id($conn)
{
    $result = mysqli_query(
        $conn,
        "SELECT COALESCE(MAX(transaction_id), 0) + 1 AS next_id
         FROM inventory_transactions"
    );

    if (!$result) {
        throw new Exception(
            "Failed to generate inventory transaction ID."
        );
    }

    $row = mysqli_fetch_assoc($result);

    $next_id = (int)($row['next_id'] ?? 1);

    if ($next_id < 1) {
        $next_id = 1;
    }

    return $next_id;
}


/**
 * Get stock record for a single blood group.
 */
function get_blood_stock_by_group($conn, $blood_group)
{
    init_inventory_tables($conn);

    return get_stock_row_no_init(
        $conn,
        $blood_group
    );
}


/**
 * Get all 8 blood stock records ordered canonically.
 */
function get_all_blood_stock($conn)
{
    init_inventory_tables($conn);

    $sql = "SELECT
                stock_id,
                blood_group,
                units_available,
                units_available AS available_units,
                reserved_units,
                last_updated,
                last_updated AS updated_at
            FROM blood_stock
            ORDER BY FIELD(
                blood_group,
                'A+',
                'A-',
                'B+',
                'B-',
                'AB+',
                'AB-',
                'O+',
                'O-'
            )";

    $res = mysqli_query($conn, $sql);

    $stock = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {

            $row['available_units'] =
                (int)$row['units_available'];

            $row['reserved_units'] =
                (int)$row['reserved_units'];

            $row['status'] =
                get_blood_stock_status(
                    $row['available_units']
                );

            $stock[$row['blood_group']] = $row;
        }
    }


    /*
     * Ensure all 8 groups are represented in the returned array.
     */
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
 * Get aggregated inventory statistics.
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

        $total_available +=
            (int)$item['available_units'];

        $total_reserved +=
            (int)$item['reserved_units'];


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
 * Atomically increase blood inventory when a donation is completed.
 *
 * IMPORTANT:
 * This function is designed to be called INSIDE the caller's
 * existing transaction.
 *
 * It does NOT:
 * - CREATE TABLE
 * - ALTER TABLE
 * - START another transaction
 * - COMMIT
 * - ROLLBACK
 *
 * TiDB compatibility:
 * - Explicit stock row creation
 * - Explicit transaction_id generation
 */
function add_donation_to_inventory(
    $conn,
    $blood_group,
    $units,
    $donation_id = null,
    $user_id = null
) {
    $units = intval($units);

    if ($units <= 0) {
        throw new Exception(
            "Invalid donation units for inventory update."
        );
    }

    if (!in_array(
        $blood_group,
        get_supported_blood_groups(),
        true
    )) {
        throw new Exception(
            "Invalid blood group for inventory update."
        );
    }

    // No DDL here — safe inside the caller's transaction.
    ensure_stock_row($conn, $blood_group);

    // Increase available stock.
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE blood_stock
         SET units_available = units_available + ?
         WHERE blood_group = ?"
    );

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare inventory update query."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $units,
        $blood_group
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new Exception(
            "Failed to update blood stock: " . $error
        );
    }

    mysqli_stmt_close($stmt);

    // Generate transaction ID manually for TiDB.
    $transaction_id =
        get_next_inventory_transaction_id($conn);

    $type = 'DONATION';

    $ref_note = $donation_id
        ? "Donation #" . $donation_id
        : "Completed Donation";

    // inventory_transactions has NO stock_id.
    $log = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_transactions
        (
            transaction_id,
            blood_group,
            transaction_type,
            units,
            reference_id,
            reference_note,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$log) {
        throw new Exception(
            "Failed to prepare inventory transaction log."
        );
    }

    mysqli_stmt_bind_param(
        $log,
        "issiisi",
        $transaction_id,
        $blood_group,
        $type,
        $units,
        $donation_id,
        $ref_note,
        $user_id
    );

    if (!mysqli_stmt_execute($log)) {
        $error = mysqli_stmt_error($log);
        mysqli_stmt_close($log);

        throw new Exception(
            "Failed to log inventory transaction: " . $error
        );
    }

    mysqli_stmt_close($log);

    return true;
}
/**
 * Allocate inventory to fulfill a blood request.
 *
 * This function is intended for an existing transaction context.
 */
function allocate_inventory_for_request(
    $conn,
    $blood_group,
    $units,
    $request_id = null,
    $user_id = null
) {
    $units = intval($units);

    if ($units <= 0) {
        throw new Exception(
            "Units to allocate must be greater than zero."
        );
    }


    if (!in_array(
        $blood_group,
        get_supported_blood_groups(),
        true
    )) {
        throw new Exception(
            "Invalid blood group specified."
        );
    }


    /*
     * Do not initialize tables inside a transaction.
     * Ensure the stock row exists without DDL.
     */
    ensure_stock_row(
        $conn,
        $blood_group
    );


    /*
     * Verify available stock.
     */
    $stock = get_stock_row_no_init(
        $conn,
        $blood_group
    );

    if (
        !$stock ||
        $stock['available_units'] < $units
    ) {

        $available = $stock
            ? $stock['available_units']
            : 0;

        throw new Exception(
            "Insufficient stock for group " .
            $blood_group .
            ". Available: " .
            $available .
            ", Requested: " .
            $units .
            "."
        );
    }


    /*
     * Decrease stock safely.
     */
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE blood_stock
         SET units_available = units_available - ?
         WHERE blood_group = ?
         AND units_available >= ?"
    );

    if (!$stmt) {
        throw new Exception(
            "Failed to prepare allocation query."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "isi",
        $units,
        $blood_group,
        $units
    );

    if (
        !mysqli_stmt_execute($stmt) ||
        mysqli_stmt_affected_rows($stmt) < 1
    ) {

        mysqli_stmt_close($stmt);

        throw new Exception(
            "Allocation failed due to stock change. Please retry."
        );
    }

    mysqli_stmt_close($stmt);


    /*
     * Log allocation.
     */
    $transaction_id =
        get_next_inventory_transaction_id($conn);

    $type = 'ALLOCATION';

    $neg_units = -$units;

    $ref_note = $request_id
        ? "Request #" . $request_id . " Fulfillment"
        : "Request Allocation";


    $log = mysqli_prepare(
        $conn,
        "INSERT INTO inventory_transactions
        (
            transaction_id,
            blood_group,
            transaction_type,
            units,
            reference_id,
            reference_note,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$log) {
        throw new Exception(
            "Failed to prepare allocation transaction log."
        );
    }

    mysqli_stmt_bind_param(
        $log,
        "issiisi",
        $transaction_id,
        $blood_group,
        $type,
        $neg_units,
        $request_id,
        $ref_note,
        $user_id
    );

    if (!mysqli_stmt_execute($log)) {

        $error = mysqli_stmt_error($log);

        mysqli_stmt_close($log);

        throw new Exception(
            "Failed to log allocation transaction: " . $error
        );
    }

    mysqli_stmt_close($log);

    return true;
}


/**
 * Manual administrative adjustment of blood stock.
 *
 * ADD     -> increases stock
 * DEDUCT  -> decreases stock
 */
function manual_stock_adjustment(
    $conn,
    $blood_group,
    $units,
    $adjustment_type,
    $reason,
    $user_id = null
) {
    $units = intval($units);

    if ($units <= 0) {
        throw new Exception(
            "Adjustment units must be a positive integer."
        );
    }


    if (!in_array(
        $blood_group,
        get_supported_blood_groups(),
        true
    )) {
        throw new Exception(
            "Invalid blood group specified."
        );
    }


    $adjustment_type =
        strtoupper(trim($adjustment_type));


    if (
        $adjustment_type !== 'ADD' &&
        $adjustment_type !== 'DEDUCT'
    ) {
        throw new Exception(
            "Adjustment type must be ADD or DEDUCT."
        );
    }


    if (empty(trim($reason))) {
        throw new Exception(
            "A clear audit reason is required for manual stock adjustments."
        );
    }


    /*
     * Initialize before starting the transaction.
     * Never run DDL inside the transaction.
     */
    init_inventory_tables($conn);


    mysqli_begin_transaction($conn);

    try {

        /*
         * Ensure stock row exists without DDL.
         */
        ensure_stock_row(
            $conn,
            $blood_group
        );


        if ($adjustment_type === 'DEDUCT') {

            $stock = get_stock_row_no_init(
                $conn,
                $blood_group
            );

            if (
                !$stock ||
                $stock['available_units'] < $units
            ) {

                $avail = $stock
                    ? $stock['available_units']
                    : 0;

                throw new Exception(
                    "Cannot deduct " .
                    $units .
                    " units. Only " .
                    $avail .
                    " units available for " .
                    $blood_group .
                    "."
                );
            }


            $stmt = mysqli_prepare(
                $conn,
                "UPDATE blood_stock
                 SET units_available = units_available - ?
                 WHERE blood_group = ?
                 AND units_available >= ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare stock deduction."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "isi",
                $units,
                $blood_group,
                $units
            );

            if (
                !mysqli_stmt_execute($stmt) ||
                mysqli_stmt_affected_rows($stmt) < 1
            ) {

                mysqli_stmt_close($stmt);

                throw new Exception(
                    "Stock deduction failed."
                );
            }

            mysqli_stmt_close($stmt);

            $signed_units = -$units;

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE blood_stock
                 SET units_available = units_available + ?
                 WHERE blood_group = ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "Failed to prepare stock addition."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "is",
                $units,
                $blood_group
            );

            if (!mysqli_stmt_execute($stmt)) {

                $error = mysqli_stmt_error($stmt);

                mysqli_stmt_close($stmt);

                throw new Exception(
                    "Failed to add stock: " . $error
                );
            }

            mysqli_stmt_close($stmt);

            $signed_units = $units;
        }


        /*
         * Generate transaction ID manually.
         */
        $transaction_id =
            get_next_inventory_transaction_id($conn);


        /*
         * Log adjustment.
         */
        $type = 'ADJUSTMENT';

        $note =
            "[" .
            $adjustment_type .
            "] " .
            trim($reason);


        $log = mysqli_prepare(
            $conn,
            "INSERT INTO inventory_transactions
            (
                transaction_id,
                blood_group,
                transaction_type,
                units,
                reference_note,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$log) {
            throw new Exception(
                "Failed to prepare adjustment transaction log."
            );
        }


        mysqli_stmt_bind_param(
            $log,
            "issisi",
            $transaction_id,
            $blood_group,
            $type,
            $signed_units,
            $note,
            $user_id
        );


        if (!mysqli_stmt_execute($log)) {

            $error = mysqli_stmt_error($log);

            mysqli_stmt_close($log);

            throw new Exception(
                "Failed to log stock adjustment: " . $error
            );
        }

        mysqli_stmt_close($log);


        mysqli_commit($conn);

        return true;

    } catch (Exception $e) {

        mysqli_rollback($conn);

        throw $e;
    }
}


/**
 * Get recent inventory transactions.
 */
function get_inventory_transactions(
    $conn,
    $limit = 50
) {
    init_inventory_tables($conn);

    $limit = intval($limit);

    if ($limit <= 0) {
        $limit = 50;
    }


    /*
     * Limit is already converted to integer,
     * so it is safe to place into this query.
     */
    $sql = "SELECT
                t.transaction_id,
                t.blood_group,
                t.transaction_type,
                t.units,
                t.reference_id,
                t.reference_note,
                t.created_by,
                t.created_at,
                u.name AS user_name,
                u.role AS user_role
            FROM inventory_transactions t
            LEFT JOIN users u
                ON t.created_by = u.user_id
            ORDER BY
                t.created_at DESC,
                t.transaction_id DESC
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