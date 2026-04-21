<?php
// ============================================================
// user/vehicles.php — Vehicle Management
// ============================================================
session_start();
require_once '../config/db.php';
requireLogin();

$uid   = $_SESSION['user_id'];
$error = '';

// ── Handle Actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_vehicle') {
        $vnum  = strtoupper(trim($_POST['vehicle_number'] ?? ''));
        $vtype = trim($_POST['vehicle_type'] ?? 'Car');

        $allowed_types = ['Car', 'Bike', 'Truck'];
        if (!in_array($vtype, $allowed_types)) $vtype = 'Car';

        if (empty($vnum)) {
            $error = 'Vehicle number cannot be empty.';
        } elseif (!preg_match('/^[A-Z0-9\-]{3,20}$/', $vnum)) {
            $error = 'Invalid vehicle number format. Use letters, digits, hyphens only (3–20 chars).';
        } else {
            $stmt = $conn->prepare("SELECT vehicle_number FROM Vehicle WHERE vehicle_number=:vnum");
            $stmt->execute([':vnum' => $vnum]);
            if ($stmt->fetch()) {
                $error = "Vehicle '$vnum' is already registered in the system.";
            } else {
                $stmt = $conn->prepare("INSERT INTO Vehicle (vehicle_number, user_id, vehicle_type) VALUES (:vnum, :uid, :vtype)");
                $stmt->execute([':vnum' => $vnum, ':uid' => $uid, ':vtype' => $vtype]);
                setFlash('success', "Vehicle '$vnum' ($vtype) added successfully.");
                redirect('vehicles.php');
            }
        }
    }

    elseif ($action === 'delete_vehicle') {
        $vnum = trim($_POST['vehicle_number'] ?? '');

        $stmt = $conn->prepare("SELECT session_id FROM ParkingSession WHERE vehicle_number=:vnum AND end_time IS NULL");
        $stmt->execute([':vnum' => $vnum]);
        if ($stmt->fetch()) {
            setFlash('error', 'Cannot remove vehicle with an active parking session.');
        } else {
            $stmt = $conn->prepare("SELECT 1 FROM Vehicle WHERE vehicle_number=:vnum AND user_id=:uid");
            $stmt->execute([':vnum' => $vnum, ':uid' => $uid]);
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM Vehicle WHERE vehicle_number=:vnum");
                $stmt->execute([':vnum' => $vnum]);
                setFlash('success', "Vehicle '$vnum' removed.");
            }
        }
        redirect('vehicles.php');
    }
}

$flash = getFlash();
$stmt  = $conn->prepare("
    SELECT v.vehicle_number, v.vehicle_type,
           COUNT(ps.session_id) AS session_count,
           MAX(ps.start_time) AS last_session,
           SUM(p.amount) AS total_spent,
           (SELECT COUNT(*) FROM ParkingSession WHERE vehicle_number=v.vehicle_number AND end_time IS NULL) AS active
    FROM Vehicle v
    LEFT JOIN ParkingSession ps ON v.vehicle_number = ps.vehicle_number
    LEFT JOIN Payment p ON ps.session_id = p.session_id AND p.status='paid'
    WHERE v.user_id = :uid
    GROUP BY v.vehicle_number, v.vehicle_type
");
$stmt->execute([':uid' => $uid]);
$vehicles = $stmt->fetchAll();

$page_title = 'My Vehicles';
$active_nav = 'vehicles';
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
        <h2>🚗 My Vehicles</h2>
        <p>Manage your registered vehicles</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addVehicleModal')">
        ➕ Add Vehicle
    </button>
</div>

<?php
$type_icons = ['Car' => '🚗', 'Bike' => '🏍️', 'Truck' => '🚛'];
?>

<?php if (count($vehicles) > 0): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">
    <?php foreach ($vehicles as $v): ?>
    <div class="zone-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="font-family:var(--mono);font-size:18px;font-weight:700;color:var(--text);">
                <?= htmlspecialchars($v['vehicle_number']) ?>
            </div>
            <?php if ($v['active'] > 0): ?>
                <span class="badge badge-green">PARKED</span>
            <?php else: ?>
                <span class="badge badge-gray">Idle</span>
            <?php endif; ?>
        </div>

        <!-- Vehicle Type Badge -->
        <div style="margin-bottom:12px;">
            <span class="badge badge-blue">
                <?= $type_icons[$v['vehicle_type']] ?? '🚗' ?> <?= htmlspecialchars($v['vehicle_type']) ?>
            </span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <div style="font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:0.08em;">Sessions</div>
                <div style="font-family:var(--mono);font-size:18px;font-weight:700;"><?= $v['session_count'] ?></div>
            </div>
            <div>
                <div style="font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:0.08em;">Spent</div>
                <div style="font-family:var(--mono);font-size:16px;font-weight:700;color:var(--accent);"><?= formatMoney($v['total_spent'] ?? 0) ?></div>
            </div>
        </div>
        <?php if ($v['last_session']): ?>
        <div style="font-size:11px;color:var(--text2);margin-bottom:12px;">
            Last: <?= date('d M Y', strtotime($v['last_session'])) ?>
        </div>
        <?php endif; ?>
        <?php if (!$v['active']): ?>
        <form method="POST" onsubmit="return confirm('Remove vehicle <?= htmlspecialchars($v['vehicle_number']) ?>?');">
            <input type="hidden" name="action" value="delete_vehicle">
            <input type="hidden" name="vehicle_number" value="<?= htmlspecialchars($v['vehicle_number']) ?>">
            <button type="submit" class="btn btn-danger btn-xs">🗑️ Remove</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🚗</div>
        <p>No vehicles added yet. Add your vehicle to start parking.</p>
        <button class="btn btn-primary" onclick="openModal('addVehicleModal')" style="margin-top:16px;">
            ➕ Add Vehicle
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Add Vehicle Modal -->
<div class="modal-overlay" id="addVehicleModal">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Add Vehicle</h3>
            <button class="modal-close" onclick="closeModal('addVehicleModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_vehicle">
            <div class="form-group">
                <label>Vehicle Registration Number</label>
                <input type="text" name="vehicle_number"
                       placeholder="e.g. MH12AB1234"
                       style="text-transform:uppercase;"
                       pattern="[A-Za-z0-9\-]{3,20}"
                       required maxlength="20">
                <p class="form-hint">Letters, numbers, hyphens only. 3–20 characters.</p>
            </div>
            <div class="form-group">
                <label>Vehicle Type</label>
                <select name="vehicle_type" required>
                    <option value="Car">🚗 Car</option>
                    <option value="Bike">🏍️ Bike</option>
                    <option value="Truck">🚛 Truck</option>
                </select>
            </div>
            <div class="flex-gap">
                <button type="submit" class="btn btn-primary">✅ Add Vehicle</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addVehicleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
});
</script>

</main>
</div>
</body>
</html>
