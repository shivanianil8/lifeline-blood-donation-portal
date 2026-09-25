<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/inventory_helpers.php";
include "../includes/request_helpers.php";

$bg_filter = trim($_GET['blood_group'] ?? 'all');
$priority_filter = trim($_GET['priority'] ?? 'all');
$status_filter = trim($_GET['status'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($bg_filter !== 'all' && in_array($bg_filter, get_supported_blood_groups())) {
    $where_clauses[] = "br.blood_group = ?";
    $params[] = $bg_filter;
    $types .= "s";
}

if ($priority_filter !== 'all' && in_array($priority_filter, ['low', 'normal', 'urgent'])) {
    $where_clauses[] = "br.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'matched', 'partially_fulfilled', 'fulfilled', 'cancelled'])) {
    $where_clauses[] = "br.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT br.request_id, br.recipient_id, br.blood_group, br.units_required,
               br.hospital, br.location, br.required_date, br.priority, br.reason, br.status,
               u.name AS recipient_name, u.phone AS recipient_phone
        FROM blood_requests br
        LEFT JOIN recipients r ON br.recipient_id = r.recipient_id OR br.recipient_id = r.user_id
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY br.request_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Blood Requests Oversight</h1>
            <p class="subtitle">Complete administrative view of all patient requests, multi-unit fulfillment, and statuses.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo count($requests); ?></strong> requests
            </span>
        </div>
    </div>

    <!-- FILTERS BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="min-width: 140px;">
                <select name="blood_group" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($bg_filter === 'all') echo 'selected'; ?>>All Groups</option>
                    <?php foreach (get_supported_blood_groups() as $bg) { ?>
                        <option value="<?php echo $bg; ?>" <?php if ($bg_filter === $bg) echo 'selected'; ?>><?php echo $bg; ?></option>
                    <?php } ?>
                </select>
            </div>

            <div style="min-width: 140px;">
                <select name="priority" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($priority_filter === 'all') echo 'selected'; ?>>All Priorities</option>
                    <option value="urgent" <?php if ($priority_filter === 'urgent') echo 'selected'; ?>>Urgent</option>
                    <option value="normal" <?php if ($priority_filter === 'normal') echo 'selected'; ?>>Normal</option>
                    <option value="low" <?php if ($priority_filter === 'low') echo 'selected'; ?>>Low</option>
                </select>
            </div>

            <div style="min-width: 160px;">
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($status_filter === 'all') echo 'selected'; ?>>All Statuses</option>
                    <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="matched" <?php if ($status_filter === 'matched') echo 'selected'; ?>>Matched</option>
                    <option value="partially_fulfilled" <?php if ($status_filter === 'partially_fulfilled') echo 'selected'; ?>>Partially Fulfilled</option>
                    <option value="fulfilled" <?php if ($status_filter === 'fulfilled') echo 'selected'; ?>>Fulfilled</option>
                    <option value="cancelled" <?php if ($status_filter === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Filter
            </button>

            <?php if ($bg_filter !== 'all' || $priority_filter !== 'all' || $status_filter !== 'all') { ?>
                <a href="requests.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- REQUESTS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($requests)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">🩸</div>
                <h2>No blood requests found</h2>
                <p>No blood requests match your selected filters. Try broadening your filter selection.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">Req #</th>
                            <th style="padding: 12px 16px;">Group</th>
                            <th style="padding: 12px 16px;">Recipient &amp; Hospital</th>
                            <th style="padding: 12px 16px;">Units &amp; Progress</th>
                            <th style="padding: 12px 16px;">Priority</th>
                            <th style="padding: 12px 16px;">Required Date</th>
                            <th style="padding: 12px 16px; text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req) { ?>
                            <?php
                            $req_id = (int)$req['request_id'];
                            $summary = get_request_units_summary($conn, $req_id);
                            $st = $summary['status'];
                            $req_u = $summary['required'];
                            $ful_u = $summary['fulfilled'];
                            $rem_u = $summary['remaining'];
                            $pct = $summary['percent'];
                            $priority = strtolower($req['priority'] ?? 'normal');
                            ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px; font-weight: 600; color: var(--muted); font-size: 12px;">
                                    #<?php echo $req_id; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-weight: 800; font-size: 16px; color: var(--burgundy); font-family: var(--font-heading);">
                                        <?php echo htmlspecialchars($req['blood_group']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($req['hospital']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        Recipient: <?php echo htmlspecialchars($req['recipient_name'] ?? 'Recipient #' . $req['recipient_id']); ?>
                                        &middot; 📍 <?php echo htmlspecialchars($req['location']); ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px; min-width: 160px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600; margin-bottom: 4px;">
                                        <span><?php echo $ful_u; ?> / <?php echo $req_u; ?> units</span>
                                        <span style="color: var(--muted);"><?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="progress-container" style="height: 6px;">
                                        <div class="progress-bar <?php if ($pct >= 100) echo 'fulfilled'; ?>" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <div style="font-size: 10px; color: var(--muted); margin-top: 4px;">
                                        Needed: <strong><?php echo $rem_u; ?> unit(s)</strong>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span class="priority-badge priority-<?php echo htmlspecialchars($priority); ?>">
                                        <?php echo ucfirst(htmlspecialchars($priority)); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary); font-size: 12px; white-space: nowrap;">
                                    <?php echo !empty($req['required_date']) ? date("d M Y", strtotime($req['required_date'])) : 'Urgent'; ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <span class="badge badge-<?php echo htmlspecialchars(str_replace(' ', '_', $st)); ?>">
                                        <?php
                                        if ($st === 'partially_fulfilled') {
                                            echo "Partially Fulfilled";
                                        } else {
                                            echo ucfirst(htmlspecialchars(str_replace('_', ' ', $st)));
                                        }
                                        ?>
                                    </span>
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
