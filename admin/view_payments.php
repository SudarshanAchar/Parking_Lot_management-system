<?php
// ============================================================
// admin/view_payments.php — Payment Records
// ============================================================
session_start();
require_once '../config/db.php';
requireAdmin();

$total_paid     = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM Payment WHERE status='paid'")->fetch()['t'];
$total_pending  = $conn->query("SELECT COUNT(*) AS c FROM Payment WHERE status='pending'")->fetch()['c'];
$total_payments = $conn->query("SELECT COUNT(*) AS c FROM Payment")->fetch()['c'];

$payments = $conn->query("
    SELECT p.*, ps.vehicle_number, z.name AS zone_name, ps.slot_number,
           u.name AS user_name, s.slot_type, v.vehicle_type
    FROM Payment p
    JOIN ParkingSession ps ON p.session_id = ps.session_id
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Users u ON v.user_id = u.user_id
    JOIN Slot s ON ps.zone_id = s.zone_id AND ps.slot_number = s.slot_number
    ORDER BY p.payment_id DESC
")->fetchAll();

$page_title = 'Payments';
$active_nav = 'payments';
include '_layout.php';
?>

<div class="page-header">
    <div>
        <h2>💰 Payment Records</h2>
        <p>All payment transactions in the system</p>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card yellow">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value" style="font-size:24px;"><?= formatMoney($total_paid) ?></div>
        <div class="stat-sub">From paid transactions</div>
        <div class="stat-icon">💰</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Total Transactions</div>
        <div class="stat-value"><?= $total_payments ?></div>
        <div class="stat-sub">All payment records</div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Pending Payments</div>
        <div class="stat-value"><?= $total_pending ?></div>
        <div class="stat-sub">Awaiting payment</div>
        <div class="stat-icon">⏳</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>All Payments</h3></div>
    <div class="table-wrap">
        <?php if (count($payments) > 0): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Session</th><th>User</th><th>Vehicle</th><th>Veh. Type</th><th>Zone / Slot</th><th>Amount</th><th>Mode</th><th>Time</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $p['payment_id'] ?></td>
                    <td class="text-mono text-muted">#<?= $p['session_id'] ?></td>
                    <td><?= htmlspecialchars($p['user_name']) ?></td>
                    <td class="text-mono"><?= htmlspecialchars($p['vehicle_number']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($p['vehicle_type']) ?></span></td>
                    <td><?= htmlspecialchars($p['zone_name']) ?> / <?= htmlspecialchars($p['slot_number']) ?></td>
                    <td class="text-mono text-yellow"><?= formatMoney($p['amount']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($p['mode'] ?? '—') ?></span></td>
                    <td style="font-size:12px;"><?= date('d M y, H:i', strtotime($p['payment_time'])) ?></td>
                    <td>
                        <?php if ($p['status'] === 'paid'): ?>
                            <span class="badge badge-green">✅ Paid</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">💰</div>
            <p>No payment records yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
