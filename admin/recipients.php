<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";

$search = trim($_GET['search'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql = "SELECT r.recipient_id, r.user_id,
               u.name, u.email, u.phone, u.created_at,
               COUNT(br.request_id) AS total_requests,
               SUM(CASE WHEN br.status IN ('pending', 'matched', 'partially_fulfilled') THEN 1 ELSE 0 END) AS active_requests,
               SUM(CASE WHEN br.status = 'fulfilled' THEN 1 ELSE 0 END) AS fulfilled_requests
        FROM recipients r
        INNER JOIN users u ON r.user_id = u.user_id
        LEFT JOIN blood_requests br ON (r.recipient_id = br.recipient_id OR r.user_id = br.recipient_id)
        WHERE " . implode(" AND ", $where_clauses) . "
        GROUP BY r.recipient_id
        ORDER BY r.recipient_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$recipients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recipients[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Recipient Registry</h1>
            <p class="subtitle">Registered patients and healthcare recipients coordinating urgent blood requirements.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo count($recipients); ?></strong> recipients
            </span>
        </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center;">
            <div style="flex: 1;">
                <input type="text" name="search" placeholder="Search by recipient name, email, or phone number..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Search
            </button>

            <?php if (!empty($search)) { ?>
                <a href="recipients.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- RECIPIENTS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($recipients)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">🏥</div>
                <h2>No recipients found</h2>
                <p>No recipient profiles matched your search criteria. Try modifying your search keywords.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">Recipient</th>
                            <th style="padding: 12px 16px;">Contact Details</th>
                            <th style="padding: 12px 16px; text-align: center;">Total Requests</th>
                            <th style="padding: 12px 16px; text-align: center;">Active Requests</th>
                            <th style="padding: 12px 16px; text-align: center;">Fulfilled Requests</th>
                            <th style="padding: 12px 16px;">Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipients as $rec) { ?>
                            <?php
                            $active = (int)$rec['active_requests'];
                            $fulfilled = (int)$rec['fulfilled_requests'];
                            ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($rec['name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        Recipient #<?php echo (int)$rec['recipient_id']; ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary);">
                                    <div><?php echo htmlspecialchars($rec['email']); ?></div>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        <?php echo !empty($rec['phone']) ? htmlspecialchars($rec['phone']) : '<span style="font-style:italic;">No phone</span>'; ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <strong><?php echo (int)$rec['total_requests']; ?></strong>
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <span style="font-weight: 700; color: <?php echo $active > 0 ? '#B45309' : 'var(--muted)'; ?>;">
                                        <?php echo $active; ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <span style="font-weight: 700; color: <?php echo $fulfilled > 0 ? '#10B981' : 'var(--muted)'; ?>;">
                                        <?php echo $fulfilled; ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--muted); font-size: 12px; white-space: nowrap;">
                                    <?php echo !empty($rec['created_at']) ? date("d M Y", strtotime($rec['created_at'])) : '—'; ?>
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
