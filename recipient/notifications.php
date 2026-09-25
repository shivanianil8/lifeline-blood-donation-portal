<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'recipient')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/notification_helpers.php";

$user_id = (int)$_SESSION['user_id'];
$message = "";
$message_type = "";

// Handle single mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read']))
{
    $notif_id = intval($_POST['notification_id'] ?? 0);
    if ($notif_id > 0)
    {
        mark_notification_as_read($conn, $notif_id, $user_id);
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read']))
{
    mark_all_notifications_as_read($conn, $user_id);
    $message = "All notifications marked as read.";
    $message_type = "success";
}

$filter = trim($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'unread', 'read'])) {
    $filter = 'all';
}

$notifications = get_user_notifications($conn, $user_id, 50, $filter);
$unread_total = get_unread_notification_count($conn, $user_id);

include "recipient-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Notifications</h1>
            <p class="subtitle">
                Real-time updates on donor responses, scheduled appointments, and fulfillment progress.
            </p>
        </div>

        <?php if ($unread_total > 0) { ?>
            <form method="POST" style="margin: 0;">
                <button type="submit" name="mark_all_read" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                    ✓ Mark all as read
                </button>
            </form>
        <?php } ?>
    </div>

    <?php if (!empty($message)) { ?>
        <div class="success-message" style="margin-bottom: 20px;">
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php } ?>

    <!-- FILTER TABS -->
    <div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
        <a href="notifications.php?filter=all" style="text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; <?php echo $filter === 'all' ? 'background: var(--burgundy); color: #fff;' : 'background: #FAF8F5; color: var(--text-secondary);'; ?>">
            All
        </a>
        <a href="notifications.php?filter=unread" style="text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; <?php echo $filter === 'unread' ? 'background: var(--burgundy); color: #fff;' : 'background: #FAF8F5; color: var(--text-secondary);'; ?>">
            Unread <?php if ($unread_total > 0) echo "($unread_total)"; ?>
        </a>
        <a href="notifications.php?filter=read" style="text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; <?php echo $filter === 'read' ? 'background: var(--burgundy); color: #fff;' : 'background: #FAF8F5; color: var(--text-secondary);'; ?>">
            Read
        </a>
    </div>

    <!-- NOTIFICATION LIST -->
    <?php if (empty($notifications)) { ?>
        <div class="requests-empty-card">
            <div class="empty-icon">🔔</div>
            <h2>No notifications</h2>
            <p>
                <?php
                if ($filter === 'unread') {
                    echo "You're all caught up! There are no unread notifications.";
                } else {
                    echo "You don't have any notifications yet. Updates about your blood requests will appear here.";
                }
                ?>
            </p>
        </div>
    <?php } else { ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($notifications as $n) { ?>
                <?php
                $is_unread = ($n['is_read'] == 0);
                ?>
                <div style="background: <?php echo $is_unread ? '#FFFFFF' : '#FAF8F5'; ?>; border: 1px solid <?php echo $is_unread ? 'var(--burgundy-soft, #F5E6E9)' : 'var(--border)'; ?>; border-left: 4px solid <?php echo $is_unread ? 'var(--burgundy)' : '#D1CBC4'; ?>; border-radius: 8px; padding: 16px 20px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; box-shadow: <?php echo $is_unread ? '0 4px 12px rgba(169, 52, 75, 0.04)' : 'none'; ?>;">
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: <?php echo $is_unread ? '#F5E6E9' : '#EEEAE6'; ?>; color: <?php echo $is_unread ? 'var(--burgundy)' : 'var(--muted)'; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                            <?php echo get_notification_icon($n['type']); ?>
                        </div>

                        <div>
                            <p style="font-size: 14px; color: var(--text); font-weight: <?php echo $is_unread ? '600' : '400'; ?>; line-height: 1.5; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($n['message']); ?>
                            </p>
                            <span style="font-size: 11px; color: var(--muted);">
                                <?php echo date("d M Y, h:i A", strtotime($n['created_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($is_unread) { ?>
                        <form method="POST" style="margin: 0; flex-shrink: 0;">
                            <input type="hidden" name="notification_id" value="<?php echo (int)$n['notification_id']; ?>">
                            <button type="submit" name="mark_read" style="background: none; border: 1px solid var(--border); color: var(--text-secondary); font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease;">
                                Mark read
                            </button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

</div>

</main>
</div>
</body>
</html>
