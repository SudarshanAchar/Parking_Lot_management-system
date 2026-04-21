<?php
session_start();
require_once '../config/db.php';
requireAdmin();

$flash = getFlash();

$users = $conn->query("
    SELECT u.user_id, u.name, u.phone, u.email, u.role,
           COUNT(DISTINCT v.vehicle_number) AS vehicle_count,
           COUNT(DISTINCT ps.session_id) AS session_count,
           COALESCE(SUM(p.amount), 0) AS total_spent
    FROM Users u
    LEFT JOIN Vehicle v ON u.user_id = v.user_id
    LEFT JOIN ParkingSession ps ON v.vehicle_number = ps.vehicle_number
    LEFT JOIN Payment p ON ps.session_id = p.session_id AND p.status = 'paid'
    WHERE u.role = 'user'
    GROUP BY u.user_id, u.name, u.phone, u.email, u.role
    ORDER BY u.user_id DESC
")->fetchAll();

$page_title = 'Manage Users';
$active_nav = 'users';
include '_layout.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>👥 Registered Users</h2>
        <p>All users registered in the system</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (count($users) > 0): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Vehicles</th><th>Sessions</th><th>Total Spent</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-mono text-muted"><?= $u['user_id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar" style="width:28px;height:28px;font-size:11px;">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                            <?= htmlspecialchars($u['name']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td class="text-mono"><?= htmlspecialchars($u['phone']) ?></td>
                    <td><span class="badge badge-blue"><?= $u['vehicle_count'] ?></span></td>
                    <td><?= $u['session_count'] ?></td>
                    <td class="text-mono text-yellow"><?= formatMoney($u['total_spent']) ?></td>
                    <td>
                        <a href="user_detail.php?id=<?= $u['user_id'] ?>" class="btn btn-ghost btn-xs">🔍 Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <p>No users registered yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
