<?php
// ============================================================
// admin/dashboard.php — Admin Dashboard
// ============================================================
session_start();
require_once '../config/db.php';
requireAdmin();

$total_zones    = $conn->query("SELECT COUNT(*) AS c FROM Zone")->fetch()['c'];
$total_slots    = $conn->query("SELECT COUNT(*) AS c FROM Slot")->fetch()['c'];
$occupied_slots = $conn->query("SELECT COUNT(*) AS c FROM Slot WHERE status='occupied'")->fetch()['c'];
$avail_slots    = $total_slots - $occupied_slots;
$total_users    = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE role='user'")->fetch()['c'];
$active_sess    = $conn->query("SELECT COUNT(*) AS c FROM ParkingSession WHERE end_time IS NULL")->fetch()['c'];
$total_revenue  = $conn->query("SELECT COALESCE(SUM(amount),0) AS r FROM Payment WHERE status='paid'")->fetch()['r'];

$recent = $conn->query("
    SELECT ps.session_id, ps.vehicle_number, z.name AS zone_name, ps.slot_number,
           ps.start_time, ps.end_time, u.name AS user_name
    FROM ParkingSession ps
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Users u ON v.user_id = u.user_id
    ORDER BY ps.session_id DESC LIMIT 8
")->fetchAll();

$flash = getFlash();
$page_title = 'Dashboard';
$active_nav = 'dashboard';
include '_layout.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>📊 Dashboard</h2>
        <p>System overview — <?= date('l, d M Y') ?></p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-label">Parking Zones</div>
        <div class="stat-value"><?= $total_zones ?></div>
        <div class="stat-sub">Active zones</div>
        <div class="stat-icon">🗺️</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Total Slots</div>
        <div class="stat-value"><?= $total_slots ?></div>
        <div class="stat-sub"><?= $avail_slots ?> available</div>
        <div class="stat-icon">🅿️</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Occupied Slots</div>
        <div class="stat-value"><?= $occupied_slots ?></div>
        <div class="stat-sub">Currently in use</div>
        <div class="stat-icon">🚗</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Registered Users</div>
        <div class="stat-value"><?= $total_users ?></div>
        <div class="stat-sub"><?= $active_sess ?> active sessions</div>
        <div class="stat-icon">👥</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <h3>🟢 Active Sessions</h3>
            <a href="view_sessions.php" class="btn btn-ghost btn-xs">View All</a>
        </div>
        <div style="padding:20px;text-align:center;">
            <div style="font-family:var(--mono);font-size:60px;font-weight:700;color:var(--accent2);"><?= $active_sess ?></div>
            <div style="font-size:13px;color:var(--text2);">vehicles currently parked</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>💰 Total Revenue</h3>
            <a href="view_payments.php" class="btn btn-ghost btn-xs">View Payments</a>
        </div>
        <div style="padding:20px;text-align:center;">
            <div style="font-family:var(--mono);font-size:40px;font-weight:700;color:var(--accent);"><?= formatMoney($total_revenue) ?></div>
            <div style="font-size:13px;color:var(--text2);">from completed sessions</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>🗺️ Zone Overview</h3>
        <a href="manage_zones.php" class="btn btn-primary btn-sm">Manage Zones</a>
    </div>
    <div class="table-wrap">
        <?php
        $zones = $conn->query("
            SELECT z.zone_id, z.name, z.location, z.type, z.total_slots, z.price_per_hour,
                   SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) AS avail,
                   SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) AS occup
            FROM Zone z
            LEFT JOIN Slot s ON z.zone_id = s.zone_id
            GROUP BY z.zone_id, z.name, z.location, z.type, z.total_slots, z.price_per_hour
            ORDER BY z.zone_id
        ")->fetchAll();
        ?>
        <?php if (count($zones) > 0): ?>
        <table>
            <thead>
                <tr><th>Zone</th><th>Location</th><th>Type</th><th>Price/hr</th><th>Total</th><th>Available</th><th>Occupied</th><th>Occupancy</th></tr>
            </thead>
            <tbody>
                <?php foreach ($zones as $z): ?>
                <?php $pct = $z['total_slots'] > 0 ? round(($z['occup'] / $z['total_slots']) * 100) : 0; ?>
                <tr>
                    <td class="text-mono"><?= htmlspecialchars($z['name']) ?></td>
                    <td><?= htmlspecialchars($z['location']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($z['type']) ?></span></td>
                    <td><span class="text-mono" style="color:var(--accent);font-weight:600;"><?= formatMoney($z['price_per_hour']) ?></span></td>
                    <td><?= $z['total_slots'] ?></td>
                    <td><span class="text-green"><?= $z['avail'] ?? 0 ?></span></td>
                    <td><span class="text-red"><?= $z['occup'] ?? 0 ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="zone-progress" style="flex:1;margin:0;">
                                <div class="zone-progress-fill" style="width:<?= $pct ?>%;background:<?= $pct>80?'var(--accent3)':'var(--accent)' ?>;"></div>
                            </div>
                            <span style="font-family:var(--mono);font-size:11px;color:var(--text2);min-width:30px;"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🗺️</div>
            <p>No zones created yet. <a href="manage_zones.php" style="color:var(--accent);">Add a zone</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>🕐 Recent Sessions</h3>
        <a href="view_sessions.php" class="btn btn-ghost btn-xs">View All →</a>
    </div>
    <div class="table-wrap">
        <?php if (count($recent) > 0): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Vehicle</th><th>User</th><th>Zone / Slot</th><th>Start</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $r['session_id'] ?></td>
                    <td class="text-mono"><?= htmlspecialchars($r['vehicle_number']) ?></td>
                    <td><?= htmlspecialchars($r['user_name']) ?></td>
                    <td><?= htmlspecialchars($r['zone_name']) ?> / <strong><?= htmlspecialchars($r['slot_number']) ?></strong></td>
                    <td style="font-size:12px;color:var(--text2);"><?= date('d M, H:i', strtotime($r['start_time'])) ?></td>
                    <td>
                        <?php if ($r['end_time']): ?>
                            <span class="badge badge-gray">Completed</span>
                        <?php else: ?>
                            <span class="badge badge-green">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🚗</div>
            <p>No sessions recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
