<?php
// ============================================================
// user/book.php — Book Parking Slot
// ============================================================
session_start();
require_once '../config/db.php';
requireLogin();

$uid   = $_SESSION['user_id'];
$error = '';

// ── Handle Booking ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $zone_id        = (int)$_POST['zone_id'];
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');

    if (!$zone_id || empty($vehicle_number)) {
        $error = 'Please select both a zone and a vehicle.';
    } else {
        // Verify vehicle belongs to user
        $stmt = $conn->prepare("SELECT 1 FROM Vehicle WHERE vehicle_number=:vnum AND user_id=:uid");
        $stmt->execute([':vnum' => $vehicle_number, ':uid' => $uid]);
        if (!$stmt->fetch()) {
            $error = 'Invalid vehicle selection.';
        } else {
            // Check vehicle doesn't already have active session
            $stmt = $conn->prepare("SELECT 1 FROM ParkingSession WHERE vehicle_number=:vnum AND end_time IS NULL");
            $stmt->execute([':vnum' => $vehicle_number]);
            if ($stmt->fetch()) {
                $error = 'This vehicle already has an active parking session.';
            } else {
                // Find first available slot in zone
                $stmt = $conn->prepare("SELECT slot_number, slot_type FROM Slot WHERE zone_id=:zid AND status='available' ORDER BY slot_number LIMIT 1");
                $stmt->execute([':zid' => $zone_id]);
                $slot = $stmt->fetch();

                if (!$slot) {
                    $error = 'No available slots in this zone. Please try another zone.';
                } else {
                    $slot_num = $slot['slot_number'];
                    try {
                        $conn->beginTransaction();
                        // Insert parking session
                        $stmt = $conn->prepare("INSERT INTO ParkingSession (vehicle_number, zone_id, slot_number, start_time)
                                                VALUES (:vnum, :zid, :snum, NOW())");
                        $stmt->execute([':vnum' => $vehicle_number, ':zid' => $zone_id, ':snum' => $slot_num]);
                        // Mark slot as occupied
                        $stmt = $conn->prepare("UPDATE Slot SET status='occupied' WHERE zone_id=:zid AND slot_number=:snum");
                        $stmt->execute([':zid' => $zone_id, ':snum' => $slot_num]);
                        $conn->commit();

                        setFlash('success', "Parking booked! Slot $slot_num assigned in Zone.");
                        redirect('dashboard.php');
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $error = 'Booking failed. Please try again.';
                    }
                }
            }
        }
    }
}

// ── Fetch Data ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT vehicle_number, vehicle_type FROM Vehicle WHERE user_id=:uid");
$stmt->execute([':uid' => $uid]);
$vehicles = $stmt->fetchAll();

// Check if user has an active session already
$stmt = $conn->prepare("SELECT ps.session_id FROM ParkingSession ps JOIN Vehicle v ON ps.vehicle_number = v.vehicle_number WHERE v.user_id=:uid AND ps.end_time IS NULL LIMIT 1");
$stmt->execute([':uid' => $uid]);
$active_check = $stmt->fetch() ? 1 : 0;

// Fetch zones with availability
$zones_stmt = $conn->query("
    SELECT z.zone_id, z.name, z.location, z.type, z.total_slots, z.price_per_hour,
           SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) AS avail
    FROM Zone z
    JOIN Slot s ON z.zone_id = s.zone_id
    GROUP BY z.zone_id, z.name, z.location, z.type, z.total_slots, z.price_per_hour
    HAVING SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) > 0
    ORDER BY z.zone_id
");
$zones = $zones_stmt->fetchAll();

$page_title = 'Book Parking';
$active_nav = 'book';
include '_layout.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>🅿️ Book Parking</h2>
        <p>Select a zone — a slot will be auto-assigned</p>
    </div>
</div>

<?php if ($active_check > 0): ?>
<div class="alert alert-warning">
    ⚠️ You already have an active parking session. 
    <a href="history.php" style="color:var(--accent);font-weight:600;">End current session first →</a>
</div>
<?php elseif (count($vehicles) === 0): ?>
<div class="alert alert-info">
    ℹ️ You need to add a vehicle before booking. 
    <a href="vehicles.php" style="color:var(--accent4);font-weight:600;">Add Vehicle →</a>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

    <!-- Zone Selection -->
    <div>
        <div class="card">
            <div class="card-header"><h3>Available Zones</h3></div>
            <?php if (count($zones) > 0): ?>
            <div style="padding:20px;">
                <div class="zone-grid" style="grid-template-columns:1fr;">
                    <?php foreach ($zones as $z):
                        $fill = $z['total_slots'] > 0 ? round((($z['total_slots'] - $z['avail']) / $z['total_slots']) * 100) : 0;
                    ?>
                    <div class="zone-card" style="cursor:pointer;border:2px solid var(--border);"
                         onclick="selectZone(<?= $z['zone_id'] ?>, '<?= addslashes($z['name']) ?>', '<?= addslashes($z['location']) ?>', <?= $z['avail'] ?>, '<?= formatMoney($z['price_per_hour']) ?>/hr')"
                         id="zone-card-<?= $z['zone_id'] ?>">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                            <div>
                                <div class="zone-name"><?= htmlspecialchars($z['name']) ?></div>
                                <div class="zone-meta">📍 <?= htmlspecialchars($z['location']) ?></div>
                            </div>
                            <span class="badge badge-blue"><?= htmlspecialchars($z['type']) ?></span>
                        </div>
                        <div class="zone-progress"><div class="zone-progress-fill" style="width:<?= $fill ?>%;"></div></div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text2);margin-top:6px;">
                            <span class="text-green">✅ <?= $z['avail'] ?> available</span>
                            <span class="text-red">🚗 <?= $z['total_slots'] - $z['avail'] ?> occupied</span>
                        </div>
                        <div style="margin-top:8px;font-size:12px;font-family:var(--mono);color:var(--accent);font-weight:600;">
                            💰 <?= formatMoney($z['price_per_hour']) ?>/hr
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🅿️</div>
                <p>No zones with available slots right now.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Form -->
    <div class="card" style="position:sticky;top:20px;">
        <div class="card-header"><h3>📋 Booking Summary</h3></div>
        <div class="card-body">
            <form method="POST" id="bookingForm">
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="zone_id" id="selected_zone_id" value="">

                <!-- Zone Preview -->
                <div id="zone-preview" style="background:var(--bg3);border-radius:8px;padding:14px;margin-bottom:16px;border:1px dashed var(--border);">
                    <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:6px;">Selected Zone</div>
                    <div id="preview-name" style="font-family:var(--mono);font-size:15px;color:var(--text2);">— Click a zone to select —</div>
                    <div id="preview-loc" style="font-size:12px;color:var(--text2);margin-top:3px;"></div>
                    <div id="preview-avail" style="font-size:12px;color:var(--accent2);margin-top:4px;"></div>
                    <div id="preview-rate" style="font-size:12px;color:var(--accent);font-family:var(--mono);font-weight:600;margin-top:4px;"></div>
                </div>

                <div class="form-group">
                    <label>Your Vehicle</label>
                    <select name="vehicle_number" required>
                        <option value="">— Select vehicle —</option>
                        <?php foreach ($vehicles as $v): ?>
                        <option value="<?= htmlspecialchars($v['vehicle_number']) ?>">
                            <?= htmlspecialchars($v['vehicle_number']) ?> (<?= htmlspecialchars($v['vehicle_type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="background:rgba(245,200,66,0.07);border:1px solid rgba(245,200,66,0.2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text2);">
                    <strong style="color:var(--accent);">ℹ️ Auto-Assignment:</strong><br>
                    The system will automatically assign the first available slot.
                    Select a zone to see its hourly rate.
                </div>

                <button type="submit" class="btn btn-primary" id="bookBtn" disabled
                        style="width:100%;justify-content:center;">
                    🅿️ Confirm Booking
                </button>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function selectZone(id, name, loc, avail, rate) {
    document.querySelectorAll('.zone-card').forEach(c => {
        c.style.borderColor = 'var(--border)';
    });
    const card = document.getElementById('zone-card-' + id);
    if (card) card.style.borderColor = 'var(--accent)';
    document.getElementById('selected_zone_id').value = id;
    document.getElementById('preview-name').textContent  = name;
    document.getElementById('preview-name').style.color  = 'var(--text)';
    document.getElementById('preview-loc').textContent   = '📍 ' + loc;
    document.getElementById('preview-avail').textContent = '✅ ' + avail + ' slots available';
    document.getElementById('preview-rate').textContent  = rate ? '💰 ' + rate : '';
    document.getElementById('bookBtn').disabled = false;
}
</script>

</main>
</div>
</body>
</html>
