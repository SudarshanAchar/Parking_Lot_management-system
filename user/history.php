<?php
// ============================================================
// user/history.php — Session History + End Session
// ============================================================
session_start();
require_once '../config/db.php';
requireLogin();

$uid   = $_SESSION['user_id'];
$error = '';

// ── Handle End Session ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'end_session') {
    $session_id = (int)$_POST['session_id'];

    $stmt = $conn->prepare("
        SELECT ps.*, s.slot_type, z.price_per_hour
        FROM ParkingSession ps
        JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
        JOIN Slot s ON ps.zone_id=s.zone_id AND ps.slot_number=s.slot_number
        JOIN Zone z ON ps.zone_id = z.zone_id
        WHERE ps.session_id=:sid AND v.user_id=:uid AND ps.end_time IS NULL
        LIMIT 1
    ");
    $stmt->execute([':sid' => $session_id, ':uid' => $uid]);
    $sess = $stmt->fetch();

    if (!$sess) {
        $error = 'Session not found or already ended.';
    } else {
        $zone_id        = $sess['zone_id'];
        $slot_num       = $sess['slot_number'];
        $slot_type      = $sess['slot_type'];
        $price_per_hour = $sess['price_per_hour'];
        $hours          = calcDuration($sess['start_time'], null);
        $amount         = calcFee($hours, $price_per_hour, $slot_type);

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE ParkingSession SET end_time=NOW() WHERE session_id=:sid");
            $stmt->execute([':sid' => $session_id]);

            $stmt = $conn->prepare("UPDATE Slot SET status='available' WHERE zone_id=:zid AND slot_number=:snum");
            $stmt->execute([':zid' => $zone_id, ':snum' => $slot_num]);

            $stmt = $conn->prepare("INSERT INTO Payment (session_id, amount, status) VALUES (:sid, :amount, 'pending')");
            $stmt->execute([':sid' => $session_id, ':amount' => $amount]);
            $conn->commit();

            setFlash('success', "Session ended. Duration: {$hours}h. Charge: ₹{$amount}. Please complete payment.");
            redirect('payments.php');
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Failed to end session: ' . $e->getMessage();
        }
    }
}

$flash = getFlash();

$stmt = $conn->prepare("
    SELECT ps.session_id, ps.vehicle_number, z.name AS zone_name, ps.slot_number,
           ps.start_time, ps.end_time, p.amount, p.status AS pay_status,
           p.payment_id, s.slot_type, z.price_per_hour, v.vehicle_type
    FROM ParkingSession ps
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Slot s ON ps.zone_id=s.zone_id AND ps.slot_number=s.slot_number
    LEFT JOIN Payment p ON ps.session_id = p.session_id
    WHERE v.user_id = :uid
    ORDER BY ps.session_id DESC
");
$stmt->execute([':uid' => $uid]);
$sessions = $stmt->fetchAll();

$page_title = 'My Sessions';
$active_nav = 'history';
include '_layout.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>📋 My Sessions</h2>
        <p>All your parking sessions and their status</p>
    </div>
    <a href="book.php" class="btn btn-primary">🅿️ Book New</a>
</div>

<div class="card">
    <div class="table-wrap">
        <?php if (count($sessions) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Zone / Slot</th>
                    <th>Rate/hr</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Duration</th>
                    <th>Charge</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s):
                    $is_active      = is_null($s['end_time']);
                    $duration       = calcDuration($s['start_time'], $s['end_time']);
                    $price_per_hour = $s['price_per_hour'];
                    $fee            = $s['amount'] ?? calcFee($duration, $price_per_hour, $s['slot_type']);
                    $est_fee        = calcFee($duration, $price_per_hour, $s['slot_type']);
                ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $s['session_id'] ?></td>
                    <td class="text-mono"><?= htmlspecialchars($s['vehicle_number']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($s['vehicle_type']) ?></span></td>
                    <td><?= htmlspecialchars($s['zone_name']) ?> / <strong><?= htmlspecialchars($s['slot_number']) ?></strong></td>
                    <td class="text-mono" style="font-size:12px;"><?= formatMoney($price_per_hour) ?>/hr</td>
                    <td style="font-size:12px;"><?= date('d M y, H:i', strtotime($s['start_time'])) ?></td>
                    <td style="font-size:12px;">
                        <?= $s['end_time'] ? date('d M y, H:i', strtotime($s['end_time'])) : '<span class="badge badge-green">Active</span>' ?>
                    </td>
                    <td class="text-mono">
                        <?php if ($is_active): ?>
                            <span class="text-green"><?= $duration ?>h+</span>
                        <?php else: ?>
                            <?= $duration ?>h
                        <?php endif; ?>
                    </td>
                    <td class="text-mono text-yellow"><?= formatMoney($fee) ?></td>
                    <td>
                        <?php if ($s['pay_status'] === 'paid'): ?>
                            <span class="badge badge-green">✅ Paid</span>
                        <?php elseif ($s['pay_status'] === 'pending'): ?>
                            <span class="badge badge-yellow">⏳ Pending</span>
                        <?php else: ?>
                            <span class="badge badge-gray">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_active): ?>
                            <form method="POST" onsubmit="return confirm('End this parking session? You will be billed ₹<?= $est_fee ?> (~<?= $duration ?>h).');">
                                <input type="hidden" name="action" value="end_session">
                                <input type="hidden" name="session_id" value="<?= $s['session_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-xs">🔴 End</button>
                            </form>
                        <?php elseif ($s['pay_status'] === 'pending'): ?>
                            <a href="payments.php" class="btn btn-primary btn-xs">💳 Pay</a>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🚗</div>
            <p>No sessions yet. <a href="book.php" style="color:var(--accent);">Book your first parking slot!</a></p>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>
