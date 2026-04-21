<?php
// ============================================================
// user/payments.php — Payments (Simulated)
// ============================================================
session_start();
require_once '../config/db.php';
requireLogin();

$uid   = $_SESSION['user_id'];
$error = '';

// ── Handle Payment ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $payment_id = (int)$_POST['payment_id'];
    $mode       = trim($_POST['mode'] ?? 'Cash');

    $stmt = $conn->prepare("
        SELECT p.payment_id FROM Payment p
        JOIN ParkingSession ps ON p.session_id = ps.session_id
        JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
        WHERE p.payment_id=:pid AND v.user_id=:uid AND p.status='pending'
        LIMIT 1
    ");
    $stmt->execute([':pid' => $payment_id, ':uid' => $uid]);
    $pay = $stmt->fetch();

    if (!$pay) {
        $error = 'Payment not found or already completed.';
    } else {
        $allowed_modes = ['Cash', 'UPI', 'Card', 'Wallet'];
        if (!in_array($mode, $allowed_modes)) $mode = 'Cash';

        $stmt = $conn->prepare("UPDATE Payment SET status='paid', mode=:mode, payment_time=NOW() WHERE payment_id=:pid");
        $stmt->execute([':mode' => $mode, ':pid' => $payment_id]);
        setFlash('success', "Payment completed via $mode. Thank you!");
        redirect('payments.php');
    }
}

$flash = getFlash();

$stmt = $conn->prepare("
    SELECT p.payment_id, p.amount, p.status, p.mode, p.payment_time,
           ps.session_id, ps.vehicle_number, ps.start_time, ps.end_time,
           z.name AS zone_name, ps.slot_number, s.slot_type
    FROM Payment p
    JOIN ParkingSession ps ON p.session_id = ps.session_id
    JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number
    JOIN Zone z ON ps.zone_id = z.zone_id
    JOIN Slot s ON ps.zone_id=s.zone_id AND ps.slot_number=s.slot_number
    WHERE v.user_id = :uid
    ORDER BY p.payment_id DESC
");
$stmt->execute([':uid' => $uid]);
$payments = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) AS t FROM Payment p JOIN ParkingSession ps ON p.session_id=ps.session_id JOIN Vehicle v ON ps.vehicle_number=v.vehicle_number WHERE v.user_id=:uid AND p.status='paid'");
$stmt->execute([':uid' => $uid]);
$total_paid = $stmt->fetch()['t'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM Payment p JOIN ParkingSession ps ON p.session_id=ps.session_id JOIN Vehicle v ON ps.vehicle_number=v.vehicle_number WHERE v.user_id=:uid AND p.status='pending'");
$stmt->execute([':uid' => $uid]);
$total_pending = $stmt->fetch()['c'];

$page_title = 'Payments';
$active_nav = 'payments';
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
        <h2>💳 Payments</h2>
        <p>Your payment history and pending dues</p>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card yellow">
        <div class="stat-label">Total Paid</div>
        <div class="stat-value" style="font-size:24px;"><?= formatMoney($total_paid) ?></div>
        <div class="stat-sub">All time</div>
        <div class="stat-icon">💰</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $total_pending ?></div>
        <div class="stat-sub"><?= $total_pending > 0 ? 'Awaiting payment' : 'All clear!' ?></div>
        <div class="stat-icon">⏳</div>
    </div>
</div>

<?php
$has_pending = false;
foreach ($payments as $p) {
    if ($p['status'] === 'pending') { $has_pending = true; break; }
}
?>

<?php if ($has_pending): ?>
<div class="card" style="border-color:var(--accent);border-width:2px;margin-bottom:24px;">
    <div class="card-header" style="background:rgba(245,200,66,0.05);">
        <h3 style="color:var(--accent);">⚠️ Pending Payments</h3>
    </div>
    <?php foreach ($payments as $p): ?>
    <?php if ($p['status'] !== 'pending') continue; ?>
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-family:var(--mono);font-size:13px;color:var(--text2);">
                    Session #<?= $p['session_id'] ?> · <?= htmlspecialchars($p['vehicle_number']) ?>
                </div>
                <div style="font-size:13px;margin-top:3px;">
                    <?= htmlspecialchars($p['zone_name']) ?> — Slot <?= htmlspecialchars($p['slot_number']) ?>
                    <span class="badge badge-blue" style="margin-left:6px;"><?= htmlspecialchars($p['slot_type']) ?></span>
                </div>
                <div style="font-size:12px;color:var(--text2);margin-top:2px;">
                    <?= date('d M y H:i', strtotime($p['start_time'])) ?> → <?= $p['end_time'] ? date('d M y H:i', strtotime($p['end_time'])) : '?' ?>
                    &nbsp;·&nbsp; <?= calcDuration($p['start_time'], $p['end_time']) ?>h
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-family:var(--mono);font-size:22px;font-weight:700;color:var(--accent);"><?= formatMoney($p['amount']) ?></div>
                <button class="btn btn-primary btn-sm" style="margin-top:8px;"
                        onclick="openPayModal(<?= $p['payment_id'] ?>, '<?= formatMoney($p['amount']) ?>')">
                    💳 Pay Now
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>All Transactions</h3></div>
    <div class="table-wrap">
        <?php if (count($payments) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Session</th><th>Vehicle</th><th>Zone / Slot</th>
                    <th>Amount</th><th>Mode</th><th>Date</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td class="text-mono text-muted">#<?= $p['payment_id'] ?></td>
                    <td class="text-mono text-muted">#<?= $p['session_id'] ?></td>
                    <td class="text-mono"><?= htmlspecialchars($p['vehicle_number']) ?></td>
                    <td><?= htmlspecialchars($p['zone_name']) ?> / <?= htmlspecialchars($p['slot_number']) ?></td>
                    <td class="text-mono text-yellow"><?= formatMoney($p['amount']) ?></td>
                    <td><?= $p['mode'] ? '<span class="badge badge-blue">'.htmlspecialchars($p['mode']).'</span>' : '—' ?></td>
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
            <div class="empty-icon">💳</div>
            <p>No payment records yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <div class="modal-header">
            <h3>💳 Complete Payment</h3>
            <button class="modal-close" onclick="closeModal('payModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="payment_id" id="pay_payment_id">
            <div style="background:var(--bg3);border-radius:8px;padding:16px;margin-bottom:20px;text-align:center;">
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;">Amount Due</div>
                <div style="font-family:var(--mono);font-size:32px;font-weight:700;color:var(--accent);margin-top:6px;" id="pay_amount_display">₹0.00</div>
            </div>
            <div class="form-group">
                <label>Payment Mode</label>
                <select name="mode" required>
                    <option value="UPI">📱 UPI</option>
                    <option value="Card">💳 Card</option>
                    <option value="Cash">💵 Cash</option>
                    <option value="Wallet">👛 Wallet</option>
                </select>
            </div>
            <div style="background:rgba(74,222,128,0.06);border:1px solid rgba(74,222,128,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text2);">
                🔒 We have to add additional security functions for payment
            </div>
            <div class="flex-gap">
                <button type="submit" class="btn btn-success" style="flex:1;justify-content:center;">✅ Confirm Payment</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('payModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(payment_id, amount) {
    document.getElementById('pay_payment_id').value       = payment_id;
    document.getElementById('pay_amount_display').textContent = amount;
    document.getElementById('payModal').classList.add('active');
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>

</main>
</div>
</body>
</html>
