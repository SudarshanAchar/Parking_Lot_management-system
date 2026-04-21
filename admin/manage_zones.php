<?php
// ============================================================
// admin/manage_zones.php — Zone & Slot Management
// ============================================================
session_start();
require_once '../config/db.php';
requireAdmin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ZONE ────────────────────────────────────────────
    if ($action === 'add_zone') {
        $name          = trim($_POST['name'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $type          = trim($_POST['type'] ?? '');
        $total_slots   = (int)($_POST['total_slots'] ?? 0);
        $price_per_hour = (float)($_POST['price_per_hour'] ?? 30);

        if (empty($name) || empty($location) || $total_slots < 1 || $price_per_hour <= 0) {
            $error = 'All fields required. Slots must be ≥ 1 and price must be > 0.';
        } else {
            try {
                $conn->beginTransaction();
                $stmt = $conn->prepare("INSERT INTO Zone (name, location, type, total_slots, price_per_hour)
                                        VALUES (:name,:location,:type,:total_slots,:price) RETURNING zone_id");
                $stmt->execute([':name' => $name, ':location' => $location, ':type' => $type,
                                ':total_slots' => $total_slots, ':price' => $price_per_hour]);
                $zone_id = $stmt->fetch()['zone_id'];

                $words  = explode(' ', $name);
                $prefix = '';
                foreach ($words as $w) { if ($w) $prefix .= strtoupper($w[0]); }
                $prefix = substr($prefix, 0, 2);

                $insert_slot = $conn->prepare("INSERT INTO Slot (zone_id, slot_number, slot_type, status) VALUES (:zid, :snum, :stype, 'available')");
                for ($i = 1; $i <= $total_slots; $i++) {
                    $slot_num  = $prefix . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $slot_type = ($i % 5 === 0) ? 'Truck' : (($i % 3 === 0) ? 'Bike' : 'Car');
                    $insert_slot->execute([':zid' => $zone_id, ':snum' => $slot_num, ':stype' => $slot_type]);
                }
                $conn->commit();
                setFlash('success', "Zone '$name' created with $total_slots slots at " . formatMoney($price_per_hour) . "/hr.");
                redirect('manage_zones.php');
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Failed to create zone: ' . $e->getMessage();
            }
        }
    }

    // ── EDIT ZONE ───────────────────────────────────────────
    elseif ($action === 'edit_zone') {
        $zone_id        = (int)$_POST['zone_id'];
        $name           = trim($_POST['name'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $type           = trim($_POST['type'] ?? '');
        $price_per_hour = (float)($_POST['price_per_hour'] ?? 30);

        if ($zone_id && $name && $location && $price_per_hour > 0) {
            $stmt = $conn->prepare("UPDATE Zone SET name=:name, location=:location, type=:type, price_per_hour=:price WHERE zone_id=:zid");
            $stmt->execute([':name' => $name, ':location' => $location, ':type' => $type,
                            ':price' => $price_per_hour, ':zid' => $zone_id]);
            setFlash('success', 'Zone updated successfully.');
            redirect('manage_zones.php');
        } else {
            $error = 'Invalid data for zone update. Price must be greater than 0.';
        }
    }

    // ── UPDATE PRICE ONLY ────────────────────────────────────
    elseif ($action === 'update_price') {
        $zone_id        = (int)$_POST['zone_id'];
        $price_per_hour = (float)($_POST['price_per_hour'] ?? 0);

        if ($zone_id && $price_per_hour > 0) {
            $stmt = $conn->prepare("UPDATE Zone SET price_per_hour=:price WHERE zone_id=:zid");
            $stmt->execute([':price' => $price_per_hour, ':zid' => $zone_id]);
            setFlash('success', 'Zone price updated to ' . formatMoney($price_per_hour) . '/hr.');
        } else {
            setFlash('error', 'Invalid zone or price value.');
        }
        redirect('manage_zones.php');
    }

    // ── DELETE ZONE ─────────────────────────────────────────
    elseif ($action === 'delete_zone') {
        $zone_id = (int)$_POST['zone_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM ParkingSession WHERE zone_id=:zid AND end_time IS NULL");
        $stmt->execute([':zid' => $zone_id]);
        if ($stmt->fetch()['c'] > 0) {
            setFlash('error', 'Cannot delete zone with active parking sessions.');
        } else {
            $stmt = $conn->prepare("DELETE FROM Zone WHERE zone_id=:zid");
            $stmt->execute([':zid' => $zone_id]);
            setFlash('success', 'Zone deleted successfully.');
        }
        redirect('manage_zones.php');
    }
}

// ── Fetch Zones ──────────────────────────────────────────────
$flash = getFlash();
$zones = $conn->query("
    SELECT z.*,
           COUNT(s.slot_number) AS slot_count,
           SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) AS avail_count,
           SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) AS occup_count
    FROM Zone z
    LEFT JOIN Slot s ON z.zone_id = s.zone_id
    GROUP BY z.zone_id, z.name, z.location, z.type, z.total_slots, z.price_per_hour
    ORDER BY z.zone_id
")->fetchAll();

$view_zone_id   = (int)($_GET['view'] ?? 0);
$view_zone_data = null;
$view_slots     = null;
if ($view_zone_id) {
    $stmt = $conn->prepare("SELECT * FROM Zone WHERE zone_id=:zid");
    $stmt->execute([':zid' => $view_zone_id]);
    $view_zone_data = $stmt->fetch();

    $stmt = $conn->prepare("
        SELECT s.*, ps.vehicle_number, ps.session_id
        FROM Slot s
        LEFT JOIN ParkingSession ps ON s.zone_id=ps.zone_id AND s.slot_number=ps.slot_number AND ps.end_time IS NULL
        WHERE s.zone_id=:zid
        ORDER BY s.slot_number
    ");
    $stmt->execute([':zid' => $view_zone_id]);
    $view_slots = $stmt->fetchAll();
}

$page_title = 'Manage Zones';
$active_nav = 'zones';
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
        <h2>🗺️ Parking Zones</h2>
        <p>Create, manage, and monitor all parking zones and slots</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addZoneModal')">➕ Add New Zone</button>
</div>

<?php if (count($zones) > 0): ?>
<div class="card">
    <div class="card-header"><h3>All Zones (<?= count($zones) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Zone Name</th>
                    <th>Location</th>
                    <th>Type</th>
                    <th>Price/hr</th>
                    <th>Slots</th>
                    <th>Available</th>
                    <th>Occupied</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zones as $z): ?>
                <?php $pct = $z['slot_count'] > 0 ? round(($z['occup_count'] / $z['slot_count']) * 100) : 0; ?>
                <tr>
                    <td class="text-mono text-muted"><?= $z['zone_id'] ?></td>
                    <td><strong><?= htmlspecialchars($z['name']) ?></strong></td>
                    <td><?= htmlspecialchars($z['location']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($z['type']) ?></span></td>
                    <td>
                        <span class="text-mono" style="color:var(--accent);font-weight:600;">
                            <?= formatMoney($z['price_per_hour']) ?>/hr
                        </span>
                    </td>
                    <td><?= $z['slot_count'] ?></td>
                    <td><span class="text-green"><?= $z['avail_count'] ?? 0 ?></span></td>
                    <td><span class="text-red"><?= $z['occup_count'] ?? 0 ?></span></td>
                    <td>
                        <?php if ($pct >= 100): ?>
                            <span class="badge badge-red">Full</span>
                        <?php elseif ($pct >= 75): ?>
                            <span class="badge badge-yellow">Busy</span>
                        <?php else: ?>
                            <span class="badge badge-green">Open</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="flex-gap">
                            <a href="?view=<?= $z['zone_id'] ?>" class="btn btn-ghost btn-xs">🔍 Slots</a>
                            <button class="btn btn-ghost btn-xs"
                                onclick="openEditModal(<?= $z['zone_id'] ?>, '<?= addslashes($z['name']) ?>', '<?= addslashes($z['location']) ?>', '<?= addslashes($z['type']) ?>', <?= $z['price_per_hour'] ?>)">
                                ✏️ Edit
                            </button>
                            <button class="btn btn-ghost btn-xs"
                                onclick="openPriceModal(<?= $z['zone_id'] ?>, '<?= addslashes($z['name']) ?>', <?= $z['price_per_hour'] ?>)">
                                💰 Price
                            </button>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Delete zone \'<?= addslashes($z['name']) ?>\'? All slots will be deleted.');">
                                <input type="hidden" name="action" value="delete_zone">
                                <input type="hidden" name="zone_id" value="<?= $z['zone_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🗺️</div>
        <p>No zones created yet. Click "Add New Zone" to get started.</p>
    </div>
</div>
<?php endif; ?>

<!-- Slot View for Selected Zone -->
<?php if ($view_zone_data && $view_slots): ?>
<div class="card">
    <div class="card-header">
        <h3>🅿️ Slots — <?= htmlspecialchars($view_zone_data['name']) ?>
            <span style="font-size:13px;font-weight:400;color:var(--accent);margin-left:10px;">
                💰 <?= formatMoney($view_zone_data['price_per_hour']) ?>/hr
            </span>
        </h3>
        <a href="manage_zones.php" class="btn btn-ghost btn-xs">✕ Close</a>
    </div>
    <div style="display:flex;gap:16px;padding:16px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;">
        <span class="badge badge-green">🟢 Available</span>
        <span class="badge badge-red">🔴 Occupied</span>
    </div>
    <div class="slot-grid">
        <?php foreach ($view_slots as $slot): ?>
        <div class="slot-box slot-<?= $slot['status'] ?>"
             title="<?= htmlspecialchars($slot['slot_number']) ?> — <?= $slot['status'] ?><?= $slot['vehicle_number'] ? ' (' . $slot['vehicle_number'] . ')' : '' ?>">
            <span class="slot-icon">
                <?= $slot['slot_type'] === 'Bike' ? '🏍️' : ($slot['slot_type'] === 'Truck' ? '🚛' : '🚗') ?>
            </span>
            <?= htmlspecialchars($slot['slot_number']) ?>
            <?php if ($slot['vehicle_number']): ?>
            <span style="font-size:8px;margin-top:2px;opacity:0.7;"><?= htmlspecialchars($slot['vehicle_number']) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── MODALS ─────────────────────────────────────────────── -->

<!-- Add Zone Modal -->
<div class="modal-overlay" id="addZoneModal">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Add New Zone</h3>
            <button class="modal-close" onclick="closeModal('addZoneModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_zone">
            <div class="form-group">
                <label>Zone Name</label>
                <input type="text" name="name" placeholder="e.g. Zone Alpha" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Location / Description</label>
                <input type="text" name="location" placeholder="e.g. Block A - Ground Floor" required maxlength="50">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Zone Type</label>
                    <select name="type" required>
                        <option value="Covered">Covered</option>
                        <option value="Open">Open</option>
                        <option value="Underground">Underground</option>
                        <option value="Multi-level">Multi-level</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Slots</label>
                    <input type="number" name="total_slots" min="1" max="200" placeholder="e.g. 20" required>
                    <p class="form-hint">Slots are auto-generated</p>
                </div>
            </div>
            <div class="form-group">
                <label>Price per Hour (₹)</label>
                <input type="number" name="price_per_hour" min="1" step="0.50" placeholder="e.g. 30" value="30" required>
                <p class="form-hint">Base hourly rate for this zone</p>
            </div>
            <div class="flex-gap" style="margin-top:8px;">
                <button type="submit" class="btn btn-primary">✅ Create Zone</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addZoneModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Zone Modal -->
<div class="modal-overlay" id="editZoneModal">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Edit Zone</h3>
            <button class="modal-close" onclick="closeModal('editZoneModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_zone">
            <input type="hidden" name="zone_id" id="edit_zone_id">
            <div class="form-group">
                <label>Zone Name</label>
                <input type="text" name="name" id="edit_name" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" id="edit_location" required maxlength="50">
            </div>
            <div class="form-group">
                <label>Zone Type</label>
                <select name="type" id="edit_type">
                    <option value="Covered">Covered</option>
                    <option value="Open">Open</option>
                    <option value="Underground">Underground</option>
                    <option value="Multi-level">Multi-level</option>
                </select>
            </div>
            <div class="form-group">
                <label>Price per Hour (₹)</label>
                <input type="number" name="price_per_hour" id="edit_price" min="1" step="0.50" required>
                <p class="form-hint">Hourly rate charged to users for this zone</p>
            </div>
            <p style="font-size:12px;color:var(--text2);margin-bottom:16px;">
                ℹ️ Changing total slots is not allowed after creation (to protect existing data).
            </p>
            <div class="flex-gap">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('editZoneModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Price Modal (quick price-only update) -->
<div class="modal-overlay" id="priceModal">
    <div class="modal">
        <div class="modal-header">
            <h3>💰 Set Parking Price</h3>
            <button class="modal-close" onclick="closeModal('priceModal')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="zone_id" id="price_zone_id">
            <div style="background:var(--bg3);border-radius:8px;padding:12px;margin-bottom:16px;">
                <div style="font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:0.1em;">Zone</div>
                <div id="price_zone_name" style="font-weight:600;font-size:15px;margin-top:4px;"></div>
            </div>
            <div class="form-group">
                <label>New Price per Hour (₹)</label>
                <input type="number" name="price_per_hour" id="price_value" min="1" step="0.50" required>
                <p class="form-hint">This rate will apply to all future bookings in this zone</p>
            </div>
            <div class="flex-gap">
                <button type="submit" class="btn btn-primary">💾 Update Price</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('priceModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openEditModal(zone_id, name, location, type, price) {
    document.getElementById('edit_zone_id').value  = zone_id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_location').value = location;
    document.getElementById('edit_type').value     = type;
    document.getElementById('edit_price').value    = price;
    openModal('editZoneModal');
}

function openPriceModal(zone_id, name, current_price) {
    document.getElementById('price_zone_id').value      = zone_id;
    document.getElementById('price_zone_name').textContent = name;
    document.getElementById('price_value').value        = current_price;
    openModal('priceModal');
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

</main>
</div>
</body>
</html>
