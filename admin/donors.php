<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/inventory_helpers.php";

$search = trim($_GET['search'] ?? '');
$bg_filter = trim($_GET['blood_group'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE ? OR d.location LIKE ? OR d.address LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($bg_filter !== 'all' && in_array($bg_filter, get_supported_blood_groups())) {
    $where_clauses[] = "d.blood_group = ?";
    $params[] = $bg_filter;
    $types .= "s";
}

$sql = "SELECT d.donor_id, d.user_id, d.blood_group, d.age, d.gender, d.location, d.address, d.last_donation_date,
               u.name, u.email, u.phone,
               COUNT(dn.donation_id) AS total_donations,
               COALESCE(SUM(dn.units), 0) AS total_units
        FROM donors d
        INNER JOIN users u ON d.user_id = u.user_id
        LEFT JOIN donations dn ON d.donor_id = dn.donor_id
        WHERE " . implode(" AND ", $where_clauses) . "
        GROUP BY d.donor_id
        ORDER BY d.donor_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$donors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $donors[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Donor Registry</h1>
            <p class="subtitle">Complete clinical registry of voluntary donors, hematological profiles, and lifetime donations.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo count($donors); ?></strong> donors
            </span>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 240px;">
                <input type="text" name="search" placeholder="Search by donor name or location..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            </div>

            <div style="min-width: 160px;">
                <select name="blood_group" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($bg_filter === 'all') echo 'selected'; ?>>All Blood Groups</option>
                    <?php foreach (get_supported_blood_groups() as $bg) { ?>
                        <option value="<?php echo $bg; ?>" <?php if ($bg_filter === $bg) echo 'selected'; ?>><?php echo $bg; ?></option>
                    <?php } ?>
                </select>
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Filter
            </button>

            <?php if (!empty($search) || $bg_filter !== 'all') { ?>
                <a href="donors.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- DONORS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($donors)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">🩸</div>
                <h2>No donors found</h2>
                <p>No donor profiles matched your search criteria. Try modifying your search or blood group filter.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">Donor</th>
                            <th style="padding: 12px 16px;">Group</th>
                            <th style="padding: 12px 16px;">Demographics</th>
                            <th style="padding: 12px 16px;">Location</th>
                            <th style="padding: 12px 16px;">Last Donation</th>
                            <th style="padding: 12px 16px; text-align: right;">Total Donated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $d) { ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($d['name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        <?php echo htmlspecialchars($d['email']); ?>
                                        <?php if (!empty($d['phone'])) echo " &middot; " . htmlspecialchars($d['phone']); ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-weight: 800; font-size: 15px; color: var(--burgundy); font-family: var(--font-heading);">
                                        <?php echo !empty($d['blood_group']) ? htmlspecialchars($d['blood_group']) : '<span style="color: var(--muted); font-size: 11px; font-weight: normal;">Pending</span>'; ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary); font-size: 12px;">
                                    <?php echo !empty($d['age']) ? ((int)$d['age'] . " yrs") : '—'; ?>
                                    &middot;
                                    <?php echo !empty($d['gender']) ? ucfirst(htmlspecialchars($d['gender'])) : '—'; ?>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary); font-size: 12px;">
                                    📍 <?php echo !empty($d['location']) ? htmlspecialchars($d['location']) : '—'; ?>
                                </td>
                                <td style="padding: 14px 16px; color: var(--muted); font-size: 12px;">
                                    <?php echo !empty($d['last_donation_date']) ? date("d M Y", strtotime($d['last_donation_date'])) : '<span style="color: #10B981; font-weight: 600;">Eligible now</span>'; ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <strong style="color: var(--text); font-size: 14px;"><?php echo (int)$d['total_units']; ?> units</strong>
                                    <div style="font-size: 11px; color: var(--muted);">
                                        <?php echo (int)$d['total_donations']; ?> sessions
                                    </div>
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
