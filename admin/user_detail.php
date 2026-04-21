<?php
// ============================================================
// admin/user_detail.php — User Detail Page
// ============================================================
session_start();
require_once '../config/db.php';
requireAdmin();

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) { redirect('manage_users.php'); }

$stmt = $conn->prepare("SELECT * FROM Users WHERE user_id=:uid");
$stmt->execute([':uid' => $user_id]);
$user = $stmt->fetch();
if (!$user) { redirect('manage_users.php'); }

$stmt = $conn->prepare("SELECT vehicle_number, vehicle_type FROM Vehicle WHERE user_id=:uid");
$stmt->execute([':uid' => $user_id]);
$vehicles = $stmt->fetchAll();

$stmt = $conn->prepare("
    SELECT ps.*, z.name AS zone_name, p.amount, p.status AS pay_status, s.slot_type, v.vehicle_type
    FROM ParkingSession ps
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Slot s ON ps.zone_id=s.zone_id AND ps.slot_number=s.slot_number
    LEFT JOIN Payment p ON ps.session_id = p.session_id
    WHERE v.user_id = :uid
    ORDER BY ps.session_id DESC
");
$stmt->execute([':uid' => $user_id]);
$sessions = $stmt->fetchAll();

$page_title = 'User Detail';
$active_nav = 'users';
include '_layout.php';
?>

<div class="page-header">
    <div>
        <h2>👤 <?= htmlspecialchars($user['name']) ?></h2>
        <p>User ID #<?= $user_id ?> · <?= htmlspecialchars($user['email']) ?></p>
    </div>
    <a href="manage_users.php" class="btn btn-ghost btn-sm">← Back to Users</a>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-body" style="text-align:center;">
                <div class="avatar" style="width:64px;height:64px;font-size:26px;margin:0 auto 14px;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div style="font-weight:600;font-size:16px;"><?= htmlspecialchars($user['name']) ?></div>
                <div style="font-size:12px;color:var(--text2);margin-top:4px;"><?= htmlspecialchars($user['email']) ?></div>
                <div style="font-size:12px;color:var(--text2);"><?= htmlspecialchars($user['phone']) ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>🚗 Vehicles</h3></div>
            <div class="card-body">
                <?php if (count($vehicles) > 0): ?>
                <?php foreach ($vehicles as $v): ?>
                <div style="font-family:var(--mono);font-size:13px;padding:8px;background:var(--bg3);border-radius:6px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                    <span><?= htmlspecialchars($v['vehicle_number']) ?></span>
                    <span class="badge badge-blue" style="font-family:sans-serif;"><?= htmlspecialchars($v['vehicle_type']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="font-size:13px;color:var(--text2);">No vehicles added.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>📋 Session History</h3></div>
        <div class="table-wrap">
            <?php if (count($sessions) > 0): ?>
            <table>
                <thead>
                    <tr><th>#</th><th>Vehicle</th><th>Veh. Type</th><th>Zone / Slot</th><th>Slot Type</th><th>Start</th><th>End</th><th>Amount</th><th>Payment</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td class="text-mono text-muted">#<?= $s['session_id'] ?></td>
                        <td class="text-mono"><?= htmlspecialchars($s['vehicle_number']) ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($s['vehicle_type']) ?></span></td>
                        <td><?= htmlspecialchars($s['zone_name']) ?> / <?= htmlspecialchars($s['slot_number']) ?></td>
                        <td><span class="badge badge-blue"><?= $s['slot_type'] ?></span></td>
                        <td style="font-size:12px;"><?= date('d M y, H:i', strtotime($s['start_time'])) ?></td>
                        <td style="font-size:12px;"><?= $s['end_time'] ? date('d M y, H:i', strtotime($s['end_time'])) : '<span class="badge badge-green">Active</span>' ?></td>
                        <td class="text-mono"><?= $s['amount'] ? formatMoney($s['amount']) : '—' ?></td>
                        <td>
                            <?php if ($s['pay_status'] === 'paid'): ?>
                                <span class="badge badge-green">Paid</span>
                            <?php elseif ($s['pay_status'] === 'pending'): ?>
                                <span class="badge badge-yellow">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-gray">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>No sessions for this user.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>
</body>
</html>
