<?php
// ============================================================
// admin/view_sessions.php — All Parking Sessions
// ============================================================
session_start();
require_once '../config/db.php';
requireAdmin();

$flash = getFlash();

$filter = trim($_GET['filter'] ?? 'all');

$where = '';
if ($filter === 'active')    $where = "WHERE ps.end_time IS NULL";
if ($filter === 'completed') $where = "WHERE ps.end_time IS NOT NULL";

$sessions = $conn->query("
    SELECT ps.*, z.name AS zone_name, u.name AS user_name,
           p.amount, p.status AS pay_status, p.mode AS pay_mode,
           s.slot_type
    FROM ParkingSession ps
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Users u ON v.user_id = u.user_id
    JOIN Slot s ON ps.zone_id = s.zone_id AND ps.slot_number = s.slot_number
    LEFT JOIN Payment p ON ps.session_id = p.session_id
    $where
    ORDER BY ps.session_id DESC
")->fetchAll();

$page_title = 'Parking Sessions';
$active_nav = 'sessions';
include '_layout.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>🚗 Parking Sessions</h2>
        <p>All parking sessions across all zones</p>
    </div>
    <div class="flex-gap">
        <a href="?filter=all"       class="btn btn-ghost btn-sm <?= $filter==='all'?'btn-primary':'' ?>">All</a>
        <a href="?filter=active"    class="btn btn-ghost btn-sm <?= $filter==='active'?'btn-primary':'' ?>">Active</a>
        <a href="?filter=completed" class="btn btn-ghost btn-sm <?= $filter==='completed'?'btn-primary':'' ?>">Completed</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (count($sessions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Vehicle</th><th>Veh. Type</th><th>User</th><th>Zone / Slot</th><th>Slot Type</th>
                    <th>Rate/hr</th><th>Start Time</th><th>End Time</th><th>Duration</th>
                    <th>Amount</th><th>Payment</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s):
                    $duration  = calcDuration($s['start_time'], $s['end_time']);
                    $is_active = is_null($s['end_time']);
                ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $s['session_id'] ?></td>
                    <td class="text-mono"><?= htmlspecialchars($s['vehicle_number']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($s['vehicle_type']) ?></span></td>
                    <td><?= htmlspecialchars($s['user_name']) ?></td>
                    <td><?= htmlspecialchars($s['zone_name']) ?> / <strong><?= htmlspecialchars($s['slot_number']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($s['slot_type']) ?></span></td>
                    <td class="text-mono" style="font-size:12px;"><?= formatMoney($s['price_per_hour']) ?>/hr</td>
                    <td style="font-size:12px;"><?= date('d M y, H:i', strtotime($s['start_time'])) ?></td>
                    <td style="font-size:12px;">
                        <?= $s['end_time'] ? date('d M y, H:i', strtotime($s['end_time'])) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="text-mono"><?= $is_active ? '<span class="text-green">'.$duration.'h+</span>' : $duration.'h' ?></td>
                    <td class="text-mono text-yellow"><?= $s['amount'] ? formatMoney($s['amount']) : '—' ?></td>
                    <td>
                        <?php if ($s['pay_status'] === 'paid'): ?>
                            <span class="badge badge-green">Paid</span>
                        <?php elseif ($s['pay_status'] === 'pending'): ?>
                            <span class="badge badge-yellow">Pending</span>
                        <?php else: ?>
                            <span class="badge badge-gray">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $is_active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Done</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🚗</div>
            <p>No sessions found for this filter.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
