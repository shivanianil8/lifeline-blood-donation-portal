<?php

/**
 * LIFELINE — Notification System Helpers
 * Centralized Notification Dispatcher, Query Engine, and Read State Management
 */


/**
 * Auto-initialize notifications table if not existing
 */
function init_notifications_table($conn)
{
    if (!$conn) {
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'general',
        reference_id INT DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_user_time (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $res = mysqli_query($conn, $sql);


    /*
    |--------------------------------------------------------------------------
    | Ensure legacy installations have required columns
    |--------------------------------------------------------------------------
    */

    $col_check = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM notifications LIKE 'type'"
    );

    if ($col_check && mysqli_num_rows($col_check) === 0) {

        @mysqli_query(
            $conn,
            "ALTER TABLE notifications
             ADD COLUMN type VARCHAR(50)
             NOT NULL DEFAULT 'general'
             AFTER message"
        );
    }


    $ref_check = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM notifications LIKE 'reference_id'"
    );

    if ($ref_check && mysqli_num_rows($ref_check) === 0) {

        @mysqli_query(
            $conn,
            "ALTER TABLE notifications
             ADD COLUMN reference_id INT DEFAULT NULL
             AFTER type"
        );
    }


    return $res;
}


/**
 * Create a notification for a user with duplicate prevention
 *
 * TiDB production compatibility:
 * notification_id is generated manually because the imported
 * production table does not automatically generate this ID.
 *
 * @param mysqli $conn
 * @param int $user_id
 * @param string $message
 * @param string $type
 * @param int|null $reference_id
 * @return bool
 */
function create_notification(
    $conn,
    $user_id,
    $message,
    $type,
    $reference_id = null
)
{
    init_notifications_table($conn);


    $user_id = intval($user_id);
    $message = trim($message);
    $type = trim($type);


    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if ($user_id <= 0 || empty($message)) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate unread notifications
    |--------------------------------------------------------------------------
    */

    if ($reference_id !== null) {

        $chk = mysqli_prepare(
            $conn,
            "SELECT notification_id
             FROM notifications
             WHERE user_id = ?
             AND type = ?
             AND reference_id = ?
             AND is_read = 0
             LIMIT 1"
        );


        if ($chk) {

            $ref_int = intval($reference_id);


            mysqli_stmt_bind_param(
                $chk,
                "isi",
                $user_id,
                $type,
                $ref_int
            );


            mysqli_stmt_execute($chk);


            $res = mysqli_stmt_get_result($chk);

            $dup = mysqli_fetch_assoc($res);


            mysqli_stmt_close($chk);


            if ($dup) {
                return false;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate notification ID manually for TiDB
    |--------------------------------------------------------------------------
    */

    $id_result = mysqli_query(
        $conn,
        "SELECT COALESCE(MAX(notification_id), 0) + 1 AS next_id
         FROM notifications"
    );


    if (!$id_result) {
        return false;
    }


    $id_row = mysqli_fetch_assoc($id_result);


    $new_notification_id =
        (int)($id_row['next_id'] ?? 1);


    if ($new_notification_id < 1) {
        $new_notification_id = 1;
    }


    /*
    |--------------------------------------------------------------------------
    | Insert notification
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO notifications
        (
            notification_id,
            user_id,
            message,
            type,
            reference_id,
            is_read
        )
        VALUES (?, ?, ?, ?, ?, 0)"
    );


    if (!$stmt) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Reference ID
    |--------------------------------------------------------------------------
    */

    $ref_val = (
        $reference_id !== null
    )
        ? intval($reference_id)
        : null;


    /*
    |--------------------------------------------------------------------------
    | Bind parameters
    |--------------------------------------------------------------------------
    |
    | notification_id → i
    | user_id         → i
    | message         → s
    | type            → s
    | reference_id    → i
    |
    */

    mysqli_stmt_bind_param(
        $stmt,
        "iissi",
        $new_notification_id,
        $user_id,
        $message,
        $type,
        $ref_val
    );


    $success = mysqli_stmt_execute($stmt);


    mysqli_stmt_close($stmt);


    return $success;
}


/**
 * Get count of unread notifications for a user
 */
function get_unread_notification_count(
    $conn,
    $user_id
)
{
    init_notifications_table($conn);


    $user_id = intval($user_id);


    if ($user_id <= 0) {
        return 0;
    }


    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ?
         AND is_read = 0"
    );


    if (!$stmt) {
        return 0;
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    mysqli_stmt_execute($stmt);


    $res = mysqli_stmt_get_result($stmt);


    $row = mysqli_fetch_assoc($res);


    mysqli_stmt_close($stmt);


    return (int)(
        $row['unread_count'] ?? 0
    );
}


/**
 * Retrieve notifications for a user
 * with optional read/unread filter
 */
function get_user_notifications(
    $conn,
    $user_id,
    $limit = 50,
    $filter = 'all'
)
{
    init_notifications_table($conn);


    $user_id = intval($user_id);


    if ($user_id <= 0) {
        return [];
    }


    $limit = intval($limit);


    if ($limit <= 0) {
        $limit = 50;
    }


    /*
    |--------------------------------------------------------------------------
    | Build filter
    |--------------------------------------------------------------------------
    */

    $where = "WHERE user_id = ?";


    if ($filter === 'unread') {

        $where .= " AND is_read = 0";

    }

    elseif ($filter === 'read') {

        $where .= " AND is_read = 1";

    }


    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    $sql = "SELECT
                notification_id,
                user_id,
                message,
                type,
                reference_id,
                is_read,
                created_at
            FROM notifications
            $where
            ORDER BY created_at DESC, notification_id DESC
            LIMIT $limit";


    $stmt = mysqli_prepare(
        $conn,
        $sql
    );


    if (!$stmt) {
        return [];
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    mysqli_stmt_execute($stmt);


    $res = mysqli_stmt_get_result($stmt);


    $notifications = [];


    while ($row = mysqli_fetch_assoc($res)) {

        $notifications[] = $row;
    }


    mysqli_stmt_close($stmt);


    return $notifications;
}


/**
 * Mark a single notification as read
 * while enforcing user ownership
 */
function mark_notification_as_read(
    $conn,
    $notification_id,
    $user_id
)
{
    init_notifications_table($conn);


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_read = 1
         WHERE notification_id = ?
         AND user_id = ?"
    );


    if (!$stmt) {
        return false;
    }


    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $notification_id,
        $user_id
    );


    $success =
        mysqli_stmt_execute($stmt);


    mysqli_stmt_close($stmt);


    return $success;
}


/**
 * Mark all notifications as read for a user
 */
function mark_all_notifications_as_read(
    $conn,
    $user_id
)
{
    init_notifications_table($conn);


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications
         SET is_read = 1
         WHERE user_id = ?
         AND is_read = 0"
    );


    if (!$stmt) {
        return false;
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );


    $success =
        mysqli_stmt_execute($stmt);


    mysqli_stmt_close($stmt);


    return $success;
}


/**
 * Return friendly SVG icon according to notification type
 */
function get_notification_icon($type)
{
    switch ($type) {

        case 'donor_response':

        case 'appointment':

            return '<svg
                viewBox="0 0 24 24"
                style="
                    width:16px;
                    height:16px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >
                <rect
                    x="3"
                    y="4"
                    width="18"
                    height="18"
                    rx="2"
                    ry="2"
                ></rect>

                <line
                    x1="16"
                    y1="2"
                    x2="16"
                    y2="6"
                ></line>

                <line
                    x1="8"
                    y1="2"
                    x2="8"
                    y2="6"
                ></line>

                <line
                    x1="3"
                    y1="10"
                    x2="21"
                    y2="10"
                ></line>

            </svg>';


        case 'donation':

        case 'fulfillment':

            return '<svg
                viewBox="0 0 24 24"
                style="
                    width:16px;
                    height:16px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >

                <path
                    d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"
                ></path>

            </svg>';


        case 'cancellation':

            return '<svg
                viewBox="0 0 24 24"
                style="
                    width:16px;
                    height:16px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >

                <circle
                    cx="12"
                    cy="12"
                    r="10"
                ></circle>

                <line
                    x1="15"
                    y1="9"
                    x2="9"
                    y2="15"
                ></line>

                <line
                    x1="9"
                    y1="9"
                    x2="15"
                    y2="15"
                ></line>

            </svg>';


        default:

            return '<svg
                viewBox="0 0 24 24"
                style="
                    width:16px;
                    height:16px;
                    stroke:currentColor;
                    stroke-width:2;
                    fill:none;
                "
            >

                <path
                    d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"
                ></path>

                <path
                    d="M13.73 21a2 2 0 0 1-3.46 0"
                ></path>

            </svg>';
    }
}

?>