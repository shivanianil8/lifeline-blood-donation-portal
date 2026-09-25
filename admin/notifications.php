<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/notification_helpers.php";

init_notifications_table($conn);

$type_filter = trim($_GET['type'] ?? 'all');
$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($type_filter !== 'all' && in_array($type_filter, ['donor_response', 'appointment', 'donation', 'fulfillment', 'cancellation'])) {
    $where_clauses[] = "n.type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

$sql = "SELECT n.notification_id, n.user_id, n.message, n.type, n.reference_id, n.is_read, n.created_at,
               u.name AS user_name, u.email AS user_email, u.role AS user_role
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.user_id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY n.notification_id DESC
        LIMIT 100";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>System Notification Activity</h1>
            <p class="subtitle">Platform-wide audit log of operational alerts, appointment dispatches, and fulfillment notices.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Showing latest: <strong><?php echo count($notifications); ?></strong> events
            </span>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center;">
            <div style="min-width: 200px;">
                <select name="type" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($type_filter === 'all') echo 'selected'; ?>>All Event Types</option>
                    <option value="donor_response" <?php if ($type_filter === 'donor_response') echo 'selected'; ?>>Donor Responses</option>
                    <option value="appointment" <?php if ($type_filter === 'appointment') echo 'selected'; ?>>Appointments</option>
                    <option value="donation" <?php if ($type_filter === 'donation') echo 'selected'; ?>>Donations</option>
                    <option value="fulfillment" <?php if ($type_filter === 'fulfillment') echo 'selected'; ?>>Fulfillments</option>
                    <option value="cancellation" <?php if ($type_filter === 'cancellation') echo 'selected'; ?>>Cancellations</option>
                </select>
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Filter
            </button>

            <?php if ($type_filter !== 'all') { ?>
                <a href="notifications.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- NOTIFICATIONS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($notifications)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">🔔</div>
                <h2>No notification events found</h2>
                <p>No system notifications have been dispatched matching this filter.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">ID</th>
                            <th style="padding: 12px 16px;">Recipient User</th>
                            <th style="padding: 12px 16px;">Type</th>
                            <th style="padding: 12px 16px;">Message Notice</th>
                            <th style="padding: 12px 16px;">Read State</th>
                            <th style="padding: 12px 16px; text-align: right;">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $n) { ?>
                            <?php
                            $is_read = ($n['is_read'] == 1);
                            $type = strtolower($n['type']);
                            ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px; font-weight: 600; color: var(--muted); font-size: 12px;">
                                    #<?php echo (int)$n['notification_id']; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($n['user_name'] ?? 'User #' . $n['user_id']); ?></strong>
                                    <span style="font-size: 11px; color: var(--muted); display: block;">
                                        (<?php echo ucfirst(htmlspecialchars($n['user_role'] ?? 'user')); ?>)
                                    </span>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span class="badge" style="font-size: 10px; padding: 2px 8px; background: #FAF8F5; border: 1px solid var(--border);">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text); max-width: 360px;">
                                    <?php echo htmlspecialchars($n['message']); ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span class="badge" style="font-size: 10px; padding: 2px 8px; <?php echo $is_read ? 'background: #EEEAE6; color: var(--muted);' : 'background: #D1FAE5; color: #065F46;'; ?>">
                                        <?php echo $is_read ? 'Read' : 'Unread'; ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; color: var(--muted); font-size: 12px; white-space: nowrap;">
                                    <?php echo date("d M Y H:i", strtotime($n['created_at'])); ?>
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
