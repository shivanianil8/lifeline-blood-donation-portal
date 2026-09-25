<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/inventory_helpers.php";

$bg_filter = trim($_GET['blood_group'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($bg_filter !== 'all' && in_array($bg_filter, get_supported_blood_groups())) {
    $where_clauses[] = "dn.blood_group = ?";
    $params[] = $bg_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE ? OR dn.hospital LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql = "SELECT dn.donation_id, dn.donor_id, dn.appointment_id, dn.blood_group, dn.units, dn.donation_date, dn.hospital,
               u.name AS donor_name, u.phone AS donor_phone
        FROM donations dn
        LEFT JOIN donors d ON dn.donor_id = d.donor_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE " . implode(" AND ", $where_clauses) . "
        ORDER BY dn.donation_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$donations = [];
$total_units_filtered = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $donations[] = $row;
    $total_units_filtered += (int)$row['units'];
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>Master Donations Log</h1>
            <p class="subtitle">Official archive of all clinical blood donations logged across healthcare partner facilities.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo $total_units_filtered; ?> units</strong> (<?php echo count($donations); ?> sessions)
            </span>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" placeholder="Search by donor name or hospital..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
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
                <a href="donations.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- DONATIONS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($donations)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">🩸</div>
                <h2>No donations found</h2>
                <p>No donation records matched your search criteria.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">Donation #</th>
                            <th style="padding: 12px 16px;">Donor</th>
                            <th style="padding: 12px 16px;">Blood Group</th>
                            <th style="padding: 12px 16px;">Units</th>
                            <th style="padding: 12px 16px;">Hospital Facility</th>
                            <th style="padding: 12px 16px;">Donation Date</th>
                            <th style="padding: 12px 16px; text-align: right;">Appointment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $don) { ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px; font-weight: 600; color: var(--muted); font-size: 12px;">
                                    #<?php echo (int)$don['donation_id']; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($don['donor_name'] ?? 'Donor #' . $don['donor_id']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                                        Donor #<?php echo (int)$don['donor_id']; ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-weight: 800; font-size: 16px; color: var(--burgundy); font-family: var(--font-heading);">
                                        <?php echo htmlspecialchars($don['blood_group']); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span style="font-weight: 700; color: #10B981; font-size: 14px;">
                                        +<?php echo (int)$don['units']; ?> unit(s)
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text);">
                                    🏥 <?php echo htmlspecialchars($don['hospital']); ?>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary); font-size: 12px; white-space: nowrap;">
                                    <?php echo date("d M Y", strtotime($don['donation_date'])); ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right; color: var(--muted); font-size: 12px;">
                                    Appt #<?php echo (int)$don['appointment_id']; ?>
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
