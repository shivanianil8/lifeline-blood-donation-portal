<?php

include "../includes/auth.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
{
    header("Location: ../login.php");
    exit();
}

include "../config/database.php";

$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($role_filter !== 'all' && in_array($role_filter, ['donor', 'recipient', 'admin'])) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$sql = "SELECT user_id, name, email, phone, role, created_at 
        FROM users 
        WHERE " . implode(" AND ", $where_clauses) . " 
        ORDER BY user_id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}
mysqli_stmt_close($stmt);

include "admin-layout.php";
?>

<div class="content">

    <!-- HEADER -->
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h1>User Accounts</h1>
            <p class="subtitle">Manage and inspect registered donors, recipients, and administrator accounts.</p>
        </div>
        <div>
            <span class="badge" style="font-size: 13px; padding: 6px 14px; background: #FAF8F5; border: 1px solid var(--border);">
                Total: <strong><?php echo count($users); ?></strong> users
            </span>
        </div>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="profile-card" style="padding: 16px 20px; margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 240px;">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            </div>

            <div style="min-width: 160px;">
                <select name="role" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
                    <option value="all" <?php if ($role_filter === 'all') echo 'selected'; ?>>All Roles</option>
                    <option value="donor" <?php if ($role_filter === 'donor') echo 'selected'; ?>>Donors</option>
                    <option value="recipient" <?php if ($role_filter === 'recipient') echo 'selected'; ?>>Recipients</option>
                    <option value="admin" <?php if ($role_filter === 'admin') echo 'selected'; ?>>Administrators</option>
                </select>
            </div>

            <button type="submit" class="secondary-button" style="font-size: 12px; padding: 8px 16px;">
                Filter
            </button>

            <?php if (!empty($search) || $role_filter !== 'all') { ?>
                <a href="users.php" style="font-size: 12px; color: var(--muted); text-decoration: none;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <!-- USERS TABLE -->
    <div class="profile-card" style="padding: 0; overflow: hidden;">
        <?php if (empty($users)) { ?>
            <div class="requests-empty-card" style="padding: 36px;">
                <div class="empty-icon">👥</div>
                <h2>No users found</h2>
                <p>No user accounts matched your search criteria. Try adjusting the search keywords or role filter.</p>
            </div>
        <?php } else { ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border); background: #FAF8F5; text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th style="padding: 12px 16px;">ID</th>
                            <th style="padding: 12px 16px;">User</th>
                            <th style="padding: 12px 16px;">Contact Phone</th>
                            <th style="padding: 12px 16px;">Role</th>
                            <th style="padding: 12px 16px;">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u) { ?>
                            <?php
                            $role = strtolower($u['role']);
                            $role_bg = ($role === 'admin') ? 'background: #F5E6E9; color: var(--burgundy);' : (($role === 'donor') ? 'background: #D1FAE5; color: #065F46;' : 'background: #EBF3FA; color: #2B6CB0;');
                            ?>
                            <tr style="border-bottom: 1px solid #F3EFEA;">
                                <td style="padding: 14px 16px; color: var(--muted); font-size: 12px;">
                                    #<?php echo (int)$u['user_id']; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <strong style="color: var(--text);"><?php echo htmlspecialchars($u['name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                                        <?php echo htmlspecialchars($u['email']); ?>
                                    </div>
                                </td>
                                <td style="padding: 14px 16px; color: var(--text-secondary);">
                                    <?php echo !empty($u['phone']) ? htmlspecialchars($u['phone']) : '<span style="color: var(--muted); font-style: italic;">Not provided</span>'; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <span class="badge" style="font-size: 11px; padding: 3px 10px; <?php echo $role_bg; ?>">
                                        <?php echo ucfirst($role); ?>
                                    </span>
                                </td>
                                <td style="padding: 14px 16px; color: var(--muted); font-size: 12px; white-space: nowrap;">
                                    <?php echo !empty($u['created_at']) ? date("d M Y", strtotime($u['created_at'])) : '—'; ?>
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
