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

// Initialize tables if needed
init_inventory_tables($conn);

/* =====================================================
   AGGREGATE DASHBOARD METRICS
===================================================== */

// Users, Donors, Recipients
$q_users = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users");
$total_users = ($r = mysqli_fetch_assoc($q_users)) ? (int)$r['cnt'] : 0;

$q_donors = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM donors");
$total_donors = ($r = mysqli_fetch_assoc($q_donors)) ? (int)$r['cnt'] : 0;

$q_recipients = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM recipients");
$total_recipients = ($r = mysqli_fetch_assoc($q_recipients)) ? (int)$r['cnt'] : 0;

// Blood Requests by Lifecycle
$q_reqs = mysqli_query($conn, "SELECT 
    COUNT(*) AS total_reqs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_reqs,
    SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) AS matched_reqs,
    SUM(CASE WHEN status = 'partially_fulfilled' THEN 1 ELSE 0 END) AS partial_reqs,
    SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) AS fulfilled_reqs,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_reqs,
    SUM(CASE WHEN status IN ('pending', 'matched', 'partially_fulfilled') THEN 1 ELSE 0 END) AS active_reqs
    FROM blood_requests");
$req_stats = mysqli_fetch_assoc($q_reqs) ?: [
    'total_reqs' => 0, 'pending_reqs' => 0, 'matched_reqs' => 0,
    'partial_reqs' => 0, 'fulfilled_reqs' => 0, 'cancelled_reqs' => 0, 'active_reqs' => 0
];

// Appointments
$q_appts = mysqli_query($conn, "SELECT 
    COUNT(*) AS total_appts,
    SUM(CASE WHEN status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) AS active_appts,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_appts
    FROM appointments");
$appt_stats = mysqli_fetch_assoc($q_appts) ?: ['total_appts' => 0, 'active_appts' => 0, 'completed_appts' => 0];

// Donations
$q_don = mysqli_query($conn, "SELECT COUNT(*) AS total_donations, COALESCE(SUM(units), 0) AS total_units FROM donations");
$don_stats = mysqli_fetch_assoc($q_don) ?: ['total_donations' => 0, 'total_units' => 0];

// Blood Stock Summary
$stock_summary = get_inventory_summary($conn);
$all_stock = get_all_blood_stock($conn);

// Requests by Blood Group for Visual Analytics
$q_bg = mysqli_query($conn, "SELECT blood_group, COUNT(*) as cnt FROM blood_requests GROUP BY blood_group");
$bg_counts = [];
$max_bg_cnt = 1;
if ($q_bg) {
    while ($row = mysqli_fetch_assoc($q_bg)) {
        $bg_counts[$row['blood_group']] = (int)$row['cnt'];
        if ((int)$row['cnt'] > $max_bg_cnt) {
            $max_bg_cnt = (int)$row['cnt'];
        }
    }
}

// Recent Requests (last 5)
$recent_requests = [];
$q_recent_req = mysqli_query($conn, "SELECT r.request_id, r.blood_group, r.units_required, r.hospital, r.priority, r.status, r.required_date, u.name as recipient_name
    FROM blood_requests r
    LEFT JOIN recipients rec ON r.recipient_id = rec.recipient_id OR r.recipient_id = rec.user_id
    LEFT JOIN users u ON rec.user_id = u.user_id
    ORDER BY r.request_id DESC
    LIMIT 5");
if ($q_recent_req) {
    while ($row = mysqli_fetch_assoc($q_recent_req)) {
        $recent_requests[] = $row;
    }
}

// Recent Donations (last 5)
$recent_donations = [];
$q_recent_don = mysqli_query($conn, "SELECT d.donation_id, d.blood_group, d.units, d.donation_date, d.hospital, u.name as donor_name
    FROM donations d
    LEFT JOIN donors dn ON d.donor_id = dn.donor_id
    LEFT JOIN users u ON dn.user_id = u.user_id
    ORDER BY d.donation_id DESC
    LIMIT 5");
if ($q_recent_don) {
    while ($row = mysqli_fetch_assoc($q_recent_don)) {
        $recent_donations[] = $row;
    }
}

include "admin-layout.php";
?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header" style="margin-bottom: 26px;">
        <div>
            <h1>Administrative Overview</h1>
            <p class="subtitle">
                System-wide metrics, blood inventory monitoring, request fulfillment status, and recent activity.
            </p>
        </div>
    </div>

    <!-- PRIMARY KPIS (GRID OF 5) -->
    <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 24px;">
        <div class="stat-card">
            <span class="stat-label">TOTAL USERS</span>
            <div class="stat-value"><?php echo number_format($total_users); ?></div>
            <p class="stat-desc"><?php echo $total_donors; ?> donors &middot; <?php echo $total_recipients; ?> recipients</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">ACTIVE REQUESTS</span>
            <div class="stat-value" style="color: var(--burgundy);"><?php echo number_format($req_stats['active_reqs']); ?></div>
            <p class="stat-desc"><?php echo (int)$req_stats['pending_reqs']; ?> pending matching</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">FULFILLED REQUESTS</span>
            <div class="stat-value" style="color: #10B981;"><?php echo number_format($req_stats['fulfilled_reqs']); ?></div>
            <p class="stat-desc"><?php echo (int)$req_stats['partial_reqs']; ?> partially fulfilled</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">COMPLETED DONATIONS</span>
            <div class="stat-value"><?php echo number_format($don_stats['total_donations']); ?></div>
            <p class="stat-desc"><?php echo number_format($don_stats['total_units']); ?> units collected</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">ACTIVE APPOINTMENTS</span>
            <div class="stat-value" style="color: #2B6CB0;"><?php echo number_format($appt_stats['active_appts']); ?></div>
            <p class="stat-desc">Scheduled with donors</p>
        </div>
    </div>

    <!-- BLOOD RESERVE STRIP -->
    <div class="profile-card" style="padding: 22px; margin-bottom: 28px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: var(--text);">Blood Inventory Reserves</h3>
                <p style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                    Total Available: <strong><?php echo number_format($stock_summary['total_available']); ?> units</strong>
                    &nbsp;&middot;&nbsp;
                    Healthy (&ge;10): <span style="color: #10B981; font-weight: 600;"><?php echo $stock_summary['healthy_count']; ?></span>
                    &nbsp;&middot;&nbsp;
                    Low (5-9): <span style="color: #F59E0B; font-weight: 600;"><?php echo $stock_summary['low_count']; ?></span>
                    &nbsp;&middot;&nbsp;
                    Critical (&le;4): <span style="color: #EF4444; font-weight: 600;"><?php echo $stock_summary['critical_count']; ?></span>
                </p>
            </div>
            <a href="blood-stock.php" class="secondary-button" style="font-size: 11px; padding: 6px 14px;">
                Manage Inventory &rarr;
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px;">
            <?php foreach (get_supported_blood_groups() as $bg) { ?>
                <?php
                $item = $all_stock[$bg] ?? ['available_units' => 0, 'status' => 'critical'];
                $avail = (int)$item['available_units'];
                $status = $item['status'];
                ?>
                <div style="background: #FAF8F5; border: 1px solid var(--border); border-radius: 8px; padding: 12px 10px; text-align: center;">
                    <div style="font-size: 16px; font-weight: 800; font-family: var(--font-heading); color: var(--burgundy); margin-bottom: 4px;">
                        <?php echo $bg; ?>
                    </div>
                    <div style="font-size: 18px; font-weight: 700; color: <?php echo $status === 'critical' ? '#EF4444' : ($status === 'low' ? '#B45309' : '#065F46'); ?>;">
                        <?php echo $avail; ?>
                    </div>
                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">
                        <span class="badge badge-<?php echo htmlspecialchars($status); ?>" style="font-size: 9px; padding: 2px 6px;">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- LIGHTWEIGHT VISUAL ANALYTICS (2-COLUMN) -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">

        <!-- REQUEST STATUS DISTRIBUTION -->
        <div class="profile-card" style="padding: 22px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px;">Request Lifecycle Distribution</h3>
            <p style="font-size: 12px; color: var(--muted); margin-bottom: 16px;">Current breakdown of all <?php echo (int)$req_stats['total_reqs']; ?> blood requests</p>

            <?php
            $tot = max(1, (int)$req_stats['total_reqs']);
            $p_pend = round(((int)$req_stats['pending_reqs'] / $tot) * 100);
            $p_mat = round(((int)$req_stats['matched_reqs'] / $tot) * 100);
            $p_part = round(((int)$req_stats['partial_reqs'] / $tot) * 100);
            $p_ful = round(((int)$req_stats['fulfilled_reqs'] / $tot) * 100);
            $p_canc = round(((int)$req_stats['cancelled_reqs'] / $tot) * 100);
            ?>

            <!-- Stacked progress bar -->
            <div style="width: 100%; height: 14px; background: #EEEAE6; border-radius: 7px; overflow: hidden; display: flex; margin-bottom: 18px;">
                <div style="width: <?php echo $p_pend; ?>%; background: #9CA3AF;" title="Pending: <?php echo (int)$req_stats['pending_reqs']; ?>"></div>
                <div style="width: <?php echo $p_mat; ?>%; background: #3B82F6;" title="Matched: <?php echo (int)$req_stats['matched_reqs']; ?>"></div>
                <div style="width: <?php echo $p_part; ?>%; background: #F59E0B;" title="Partially Fulfilled: <?php echo (int)$req_stats['partial_reqs']; ?>"></div>
                <div style="width: <?php echo $p_ful; ?>%; background: #10B981;" title="Fulfilled: <?php echo (int)$req_stats['fulfilled_reqs']; ?>"></div>
                <div style="width: <?php echo $p_canc; ?>%; background: #EF4444;" title="Cancelled: <?php echo (int)$req_stats['cancelled_reqs']; ?>"></div>
            </div>

            <!-- Legend items -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: #9CA3AF;"></span>
                    <span>Pending: <strong><?php echo (int)$req_stats['pending_reqs']; ?></strong> (<?php echo $p_pend; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: #3B82F6;"></span>
                    <span>Matched: <strong><?php echo (int)$req_stats['matched_reqs']; ?></strong> (<?php echo $p_mat; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: #F59E0B;"></span>
                    <span>Partially: <strong><?php echo (int)$req_stats['partial_reqs']; ?></strong> (<?php echo $p_part; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: #10B981;"></span>
                    <span>Fulfilled: <strong><?php echo (int)$req_stats['fulfilled_reqs']; ?></strong> (<?php echo $p_ful; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: #EF4444;"></span>
                    <span>Cancelled: <strong><?php echo (int)$req_stats['cancelled_reqs']; ?></strong> (<?php echo $p_canc; ?>%)</span>
                </div>
            </div>
        </div>

        <!-- REQUESTS BY BLOOD GROUP BARS -->
        <div class="profile-card" style="padding: 22px;">
            <h3 style="font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px;">Demands by Blood Group</h3>
            <p style="font-size: 12px; color: var(--muted); margin-bottom: 16px;">Total blood requests filed per group</p>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach (get_supported_blood_groups() as $bg) { ?>
                    <?php
                    $cnt = $bg_counts[$bg] ?? 0;
                    $w = min(100, round(($cnt / $max_bg_cnt) * 100));
                    ?>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 12px;">
                        <span style="width: 32px; font-weight: 700; color: var(--burgundy);"><?php echo $bg; ?></span>
                        <div style="flex: 1; height: 8px; background: #EEEAE6; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo max(4, $w); ?>%; height: 100%; background: var(--burgundy); border-radius: 4px;"></div>
                        </div>
                        <span style="width: 24px; text-align: right; color: var(--muted); font-weight: 600;"><?php echo $cnt; ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>

    </div>

    <!-- RECENT ACTIVITY SPLIT -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

        <!-- RECENT BLOOD REQUESTS -->
        <div class="profile-card" style="padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="font-size: 15px; font-weight: 700; color: var(--text);">Recent Blood Requests</h3>
                <a href="requests.php" style="font-size: 11px; color: var(--burgundy); text-decoration: none; font-weight: 600;">View All &rarr;</a>
            </div>

            <?php if (empty($recent_requests)) { ?>
                <p style="font-size: 12px; color: var(--muted);">No requests recorded yet.</p>
            <?php } else { ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <tbody>
                        <?php foreach ($recent_requests as $req) { ?>
                            <?php $st = strtolower($req['status'] ?? 'pending'); ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 8px 0; font-weight: 700; color: var(--burgundy); width: 40px;">
                                    <?php echo htmlspecialchars($req['blood_group']); ?>
                                </td>
                                <td style="padding: 8px 6px;">
                                    <strong><?php echo htmlspecialchars($req['hospital']); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted);">
                                        <?php echo (int)$req['units_required']; ?> units &middot; <?php echo htmlspecialchars($req['recipient_name'] ?? 'Recipient'); ?>
                                    </div>
                                </td>
                                <td style="padding: 8px 0; text-align: right;">
                                    <span class="badge badge-<?php echo htmlspecialchars(str_replace(' ', '_', $st)); ?>" style="font-size: 10px; padding: 2px 7px;">
                                        <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

        <!-- RECENT COMPLETED DONATIONS -->
        <div class="profile-card" style="padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="font-size: 15px; font-weight: 700; color: var(--text);">Latest Completed Donations</h3>
                <a href="donations.php" style="font-size: 11px; color: var(--burgundy); text-decoration: none; font-weight: 600;">View All &rarr;</a>
            </div>

            <?php if (empty($recent_donations)) { ?>
                <p style="font-size: 12px; color: var(--muted);">No donations recorded yet.</p>
            <?php } else { ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <tbody>
                        <?php foreach ($recent_donations as $don) { ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 8px 0; font-weight: 700; color: var(--burgundy); width: 40px;">
                                    <?php echo htmlspecialchars($don['blood_group']); ?>
                                </td>
                                <td style="padding: 8px 6px;">
                                    <strong><?php echo htmlspecialchars($don['donor_name'] ?? 'Donor'); ?></strong>
                                    <div style="font-size: 11px; color: var(--muted);">
                                        <?php echo htmlspecialchars($don['hospital']); ?> &middot; <?php echo date("d M Y", strtotime($don['donation_date'])); ?>
                                    </div>
                                </td>
                                <td style="padding: 8px 0; text-align: right;">
                                    <span style="font-weight: 700; color: #10B981; font-size: 12px;">
                                        +<?php echo (int)$don['units']; ?> unit(s)
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

    </div>

</div>

</main>
</div>
</body>
</html>
