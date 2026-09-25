<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";
include "../includes/inventory_helpers.php";

// Initialize tables if needed
init_inventory_tables($conn);

$admin_id = $_SESSION['user_id'] ?? null;
$message = "";
$message_type = "";

/* ==================================================
   HANDLE MANUAL STOCK ADJUSTMENT
================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock']))
{
    $blood_group = trim($_POST['blood_group'] ?? '');
    $units = intval($_POST['units'] ?? 0);
    $adjustment_type = trim($_POST['adjustment_type'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($blood_group) || !in_array($blood_group, get_supported_blood_groups()))
    {
        $message = "Please select a valid blood group.";
        $message_type = "error";
    }
    elseif ($units <= 0)
    {
        $message = "Please enter a valid positive number of units.";
        $message_type = "error";
    }
    elseif ($adjustment_type !== 'ADD' && $adjustment_type !== 'DEDUCT')
    {
        $message = "Please select a valid adjustment action (Add or Deduct).";
        $message_type = "error";
    }
    elseif (empty($reason))
    {
        $message = "Please provide an audit justification reason for this stock adjustment.";
        $message_type = "error";
    }
    else
    {
        try
        {
            manual_stock_adjustment($conn, $blood_group, $units, $adjustment_type, $reason, $admin_id);
            $signed_str = ($adjustment_type === 'ADD' ? "+$units" : "-$units");
            $message = "Stock adjustment of $signed_str units for $blood_group successfully recorded.";
            $message_type = "success";
        }
        catch (Exception $e)
        {
            $message = "Adjustment failed: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch live stock data & summary
$stock_summary = get_inventory_summary($conn);
$all_stock = get_all_blood_stock($conn);
$transactions = get_inventory_transactions($conn, 40);

include "admin-layout.php";
?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1>Blood Stock &amp; Inventory</h1>
            <p class="subtitle">
                Real-time clinical blood bank inventory, health thresholds, and transactional audit trail.
            </p>
        </div>
    </div>

    <!-- FEEDBACK ALERT -->
    <?php if (!empty($message)) { ?>
        <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>" style="margin-bottom: 24px;">
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php } ?>

    <!-- OVERVIEW STAT CARDS -->
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 28px;">
        <div class="stat-card">
            <span class="stat-label">TOTAL AVAILABLE</span>
            <div class="stat-value"><?php echo number_format($stock_summary['total_available']); ?> <span style="font-size: 14px; font-weight: 500; color: var(--muted);">units</span></div>
            <p class="stat-desc">Ready for hospital dispatch</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">HEALTHY GROUPS</span>
            <div class="stat-value" style="color: #10B981;"><?php echo $stock_summary['healthy_count']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--muted);">/ 8</span></div>
            <p class="stat-desc">&ge; 10 units in reserve</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">LOW STOCK GROUPS</span>
            <div class="stat-value" style="color: #F59E0B;"><?php echo $stock_summary['low_count']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--muted);">/ 8</span></div>
            <p class="stat-desc">5 &ndash; 9 units available</p>
        </div>

        <div class="stat-card">
            <span class="stat-label">CRITICAL GROUPS</span>
            <div class="stat-value" style="color: #EF4444;"><?php echo $stock_summary['critical_count']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--muted);">/ 8</span></div>
            <p class="stat-desc">&le; 4 units (Urgent donor call)</p>
        </div>
    </div>

    <!-- 8 BLOOD GROUPS INVENTORY GRID -->
    <div class="section-title" style="margin-bottom: 16px;">
        <h2 style="font-size: 18px; font-weight: 700; color: var(--text);">Blood Reserve by Group</h2>
    </div>

    <div class="stock-grid">
        <?php foreach ($all_stock as $bg => $item) { ?>
            <?php
            $avail = (int)$item['available_units'];
            $status = $item['status'];
            $pct = min(100, round(($avail / 15) * 100));
            ?>
            <div class="stock-card">
                <div class="stock-card-top">
                    <div class="stock-blood-group"><?php echo htmlspecialchars($bg); ?></div>
                    <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                        <?php echo ucfirst(htmlspecialchars($status)); ?>
                    </span>
                </div>

                <div class="stock-metrics">
                    <div class="stock-metric-row">
                        <span>Available Units</span>
                        <strong style="font-size: 16px; color: <?php echo $status === 'critical' ? '#EF4444' : ($status === 'low' ? '#B45309' : '#065F46'); ?>;">
                            <?php echo $avail; ?>
                        </strong>
                    </div>

                    <div class="stock-metric-row">
                        <span>Reserved Units</span>
                        <strong><?php echo (int)$item['reserved_units']; ?></strong>
                    </div>
                </div>

                <div class="stock-fill-bar">
                    <div class="stock-fill-fill stock-fill-<?php echo htmlspecialchars($status); ?>" style="width: <?php echo $pct; ?>%;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-top: 8px;">
                    <span>Target: 15+</span>
                    <span>Updated: <?php echo date("d M H:i", strtotime($item['updated_at'])); ?></span>
                </div>
            </div>
        <?php } ?>
    </div>

    <!-- MANUAL ADJUSTMENT & TRANSACTIONS SPLIT -->
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 30px;">

        <!-- MANUAL ADJUSTMENT FORM -->
        <div class="profile-card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 20px; padding-bottom: 16px;">
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--text);">Manual Adjustment</h3>
                    <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">Audited stock correction</p>
                </div>
            </div>

            <form method="POST">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="blood_group" style="font-size: 12px; font-weight: 600;">Blood Group</label>
                    <select name="blood_group" id="blood_group" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px;">
                        <option value="">Select blood group...</option>
                        <?php foreach (get_supported_blood_groups() as $bg) { ?>
                            <option value="<?php echo $bg; ?>"><?php echo $bg; ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="adjustment_type" style="font-size: 12px; font-weight: 600;">Action Type</label>
                    <select name="adjustment_type" id="adjustment_type" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px;">
                        <option value="ADD">Add Units (+ Received / Restocked)</option>
                        <option value="DEDUCT">Deduct Units (- Expired / Transferred)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="units" style="font-size: 12px; font-weight: 600;">Quantity (Units)</label>
                    <input type="number" name="units" id="units" min="1" max="100" value="1" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px;">
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="reason" style="font-size: 12px; font-weight: 600;">Audit Reason / Note</label>
                    <textarea name="reason" id="reason" rows="3" placeholder="e.g., Clinical batch restock from Central Blood Bank" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; font-family: inherit;"></textarea>
                </div>

                <button type="submit" name="adjust_stock" class="primary-button" style="width: 100%; text-align: center; justify-content: center;">
                    Apply Stock Adjustment
                </button>
            </form>
        </div>

        <!-- RECENT TRANSACTIONS LOG -->
        <div class="profile-card" style="padding: 24px;">
            <div class="card-header" style="margin-bottom: 20px; padding-bottom: 16px;">
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--text);">Inventory Audit Log</h3>
                    <p style="font-size: 12px; color: var(--muted); margin-top: 4px;">Complete chronological trail of all stock movements</p>
                </div>
            </div>

            <?php if (empty($transactions)) { ?>
                <div class="requests-empty-card" style="padding: 30px; text-align: center;">
                    <div class="empty-icon" style="margin: 0 auto 12px;">📋</div>
                    <h4 style="font-size: 15px; font-weight: 600;">No transactions recorded yet</h4>
                    <p style="font-size: 12px; color: var(--muted);">Stock changes from completed donations, hospital allocations, or adjustments will appear here.</p>
                </div>
            <?php } else { ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="padding: 8px 10px;">Date</th>
                                <th style="padding: 8px 10px;">Group</th>
                                <th style="padding: 8px 10px;">Type</th>
                                <th style="padding: 8px 10px;">Units</th>
                                <th style="padding: 8px 10px;">Reference / Note</th>
                                <th style="padding: 8px 10px;">Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx) { ?>
                                <?php
                                $u = (int)$tx['units'];
                                $is_pos = $u > 0;
                                ?>
                                <tr style="border-bottom: 1px solid #F3EFEA;">
                                    <td style="padding: 10px; white-space: nowrap; color: var(--text-secondary);">
                                        <?php echo date("d M Y H:i", strtotime($tx['created_at'])); ?>
                                    </td>
                                    <td style="padding: 10px; font-weight: 700; color: var(--burgundy);">
                                        <?php echo htmlspecialchars($tx['blood_group']); ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <span class="badge" style="font-size: 10px; padding: 2px 8px; <?php echo $tx['transaction_type'] === 'DONATION' ? 'background: #D1FAE5; color: #065F46;' : ($tx['transaction_type'] === 'ALLOCATION' ? 'background: #EBF3FA; color: #2B6CB0;' : 'background: #FEF3C7; color: #B45309;'); ?>">
                                            <?php echo htmlspecialchars($tx['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; font-weight: 700; color: <?php echo $is_pos ? '#10B981' : '#EF4444'; ?>;">
                                        <?php echo ($is_pos ? "+$u" : "$u"); ?> u
                                    </td>
                                    <td style="padding: 10px; color: var(--text);">
                                        <?php echo htmlspecialchars($tx['reference_note'] ?? '—'); ?>
                                    </td>
                                    <td style="padding: 10px; color: var(--muted);">
                                        <?php echo htmlspecialchars($tx['user_name'] ?? 'System'); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>

    </div>

</div>

</main>
</div>
</body>
</html>
