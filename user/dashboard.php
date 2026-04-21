<?php
// ============================================================
// user/dashboard.php — User Dashboard
// ============================================================
session_start();
require_once '../config/db.php';
requireLogin();

$uid = $_SESSION['user_id'];

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM Vehicle WHERE user_id=:uid");
$stmt->execute([':uid' => $uid]);
$vehicle_count = $stmt->fetch()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM ParkingSession ps JOIN Vehicle v ON ps.vehicle_number=v.vehicle_number WHERE v.user_id=:uid");
$stmt->execute([':uid' => $uid]);
$total_sessions = $stmt->fetch()['c'];

$stmt = $conn->prepare("SELECT ps.*, z.name AS zone_name, s.slot_type, z.price_per_hour FROM ParkingSession ps JOIN Vehicle v ON ps.vehicle_number=v.vehicle_number JOIN Zone z ON ps.zone_id=z.zone_id JOIN Slot s ON ps.zone_id=s.zone_id AND ps.slot_number=s.slot_number WHERE v.user_id=:uid AND ps.end_time IS NULL LIMIT 1");
$stmt->execute([':uid' => $uid]);
$active_session = $stmt->fetch();

$stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) AS t FROM Payment p JOIN ParkingSession ps ON p.session_id=ps.session_id JOIN Vehicle v ON ps.vehicle_number=v.vehicle_number WHERE v.user_id=:uid AND p.status='paid'");
$stmt->execute([':uid' => $uid]);
$total_spent = $stmt->fetch()['t'];

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
        <h2>👋 Hello, <?= htmlspecialchars($_SESSION['name']) ?>!</h2>
        <p>Here's your parking summary — <?= date('l, d M Y') ?></p>
    </div>
    <?php if (!$active_session): ?>
    <a href="book.php" class="btn btn-primary">🅿️ Book Parking</a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">My Vehicles</div>
        <div class="stat-value"><?= $vehicle_count ?></div>
        <div class="stat-sub"><a href="vehicles.php" style="color:var(--accent4);">Manage</a></div>
        <div class="stat-icon">🚗</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Total Sessions</div>
        <div class="stat-value"><?= $total_sessions ?></div>
        <div class="stat-sub">All time</div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Currently Parked</div>
        <div class="stat-value"><?= $active_session ? '1' : '0' ?></div>
        <div class="stat-sub"><?= $active_session ? 'Active session' : 'Not parked' ?></div>
        <div class="stat-icon">🅿️</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value" style="font-size:22px;"><?= formatMoney($total_spent) ?></div>
        <div class="stat-sub">All payments</div>
        <div class="stat-icon">💳</div>
    </div>
</div>

<!-- Active Session Banner -->
<?php if ($active_session): ?>
<div class="card" style="border-color:var(--accent2);border-width:2px;margin-bottom:24px;">
    <div class="card-header" style="background:rgba(74,222,128,0.06);">
        <h3 style="color:var(--accent2);">🟢 Active Parking Session</h3>
        <span class="badge badge-green">LIVE</span>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:20px;margin-bottom:20px;">
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Vehicle</div>
                <div style="font-family:var(--mono);font-size:16px;font-weight:700;"><?= htmlspecialchars($active_session['vehicle_number']) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Zone</div>
                <div style="font-size:15px;font-weight:600;"><?= htmlspecialchars($active_session['zone_name']) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Slot</div>
                <div style="font-family:var(--mono);font-size:16px;font-weight:700;color:var(--accent);"><?= htmlspecialchars($active_session['slot_number']) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Started</div>
                <div style="font-size:14px;"><?= date('d M, H:i', strtotime($active_session['start_time'])) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Duration</div>
                <div style="font-family:var(--mono);font-size:16px;font-weight:700;color:var(--accent4);" id="live-duration">
                    <?= calcDuration($active_session['start_time'], null) ?>h
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px;">Est. Charge</div>
                <div style="font-family:var(--mono);font-size:16px;font-weight:700;color:var(--accent);">
                    <?= formatMoney(calcFee(calcDuration($active_session['start_time'], null), $active_session['price_per_hour'], $active_session['slot_type'])) ?>
                </div>
            </div>
        </div>
        <a href="history.php" class="btn btn-danger">
            🔴 End Session & Pay
        </a>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="text-align:center;padding:36px;">
        <div style="font-size:48px;margin-bottom:12px;">🅿️</div>
        <h3 style="font-family:var(--mono);margin-bottom:8px;">No Active Session</h3>
        <p style="color:var(--text2);margin-bottom:20px;">Park your vehicle by booking an available slot.</p>
        <?php if ($vehicle_count == 0): ?>
        <a href="vehicles.php" class="btn btn-primary">➕ Add Vehicle First</a>
        <?php else: ?>
        <a href="book.php" class="btn btn-primary">🅿️ Book a Slot</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Sessions -->
<div class="card">
    <div class="card-header">
        <h3>📋 Recent Sessions</h3>
        <a href="history.php" class="btn btn-ghost btn-xs">View All →</a>
    </div>
    <div class="table-wrap">
        <?php
        $stmt = $conn->prepare("
            SELECT ps.session_id, ps.vehicle_number, z.name AS zone_name, ps.slot_number,
                   ps.start_time, ps.end_time, p.amount, p.status AS pay_status, v.vehicle_type
            FROM ParkingSession ps
            JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
            JOIN Zone z ON ps.zone_id = z.zone_id
            LEFT JOIN Payment p ON ps.session_id = p.session_id
            WHERE v.user_id = :uid
            ORDER BY ps.session_id DESC LIMIT 5
        ");
        $stmt->execute([':uid' => $uid]);
        $recent = $stmt->fetchAll();
        ?>
        <?php if (count($recent) > 0): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Vehicle</th><th>Type</th><th>Zone / Slot</th><th>Date</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $r['session_id'] ?></td>
                    <td class="text-mono"><?= htmlspecialchars($r['vehicle_number']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($r['vehicle_type']) ?></span></td>
                    <td><?= htmlspecialchars($r['zone_name']) ?> / <strong><?= htmlspecialchars($r['slot_number']) ?></strong></td>
                    <td style="font-size:12px;"><?= date('d M, H:i', strtotime($r['start_time'])) ?></td>
                    <td class="text-mono"><?= $r['amount'] ? formatMoney($r['amount']) : '—' ?></td>
                    <td>
                        <?php if (!$r['end_time']): ?>
                            <span class="badge badge-green">Active</span>
                        <?php elseif ($r['pay_status'] === 'paid'): ?>
                            <span class="badge badge-gray">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-yellow">Unpaid</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🚗</div>
            <p>No sessions yet. <a href="book.php" style="color:var(--accent);">Book your first slot!</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
