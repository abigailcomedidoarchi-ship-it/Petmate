<?php
require_once '../../includes/db.php';
require_once '../../includes/session_guard.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireRole('vet_assistant');
require_permission('view_dashboard');

if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $role, $action, $target_table, $target_id) {
        log_audit($pdo, $user_id, $role . ':' . $action, $target_table, $target_id);
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_id = (int)($_POST['pet_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $equipment_ready = isset($_POST['equipment_ready']) ? 1 : 0;
    $supplies_ready = isset($_POST['supplies_ready']) ? 1 : 0;
    $sanitation_done = isset($_POST['sanitation_done']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    $prepared_by = (int)($_SESSION['user_id'] ?? 0);

    if ($pet_id <= 0 || $room_id <= 0) {
        $error = 'Pet and room are required.';
    } else {
        try {
            $pdo->beginTransaction();

            // Check if pet is already assigned to a room
            $checkPet = $pdo->prepare("SELECT id FROM examination_rooms WHERE pet_id = ? AND status IN ('ready', 'in_use') FOR UPDATE");
            $checkPet->execute([$pet_id]);
            if ($checkPet->fetch()) {
                throw new Exception('This pet is already assigned to an exam room.');
            }

            $roomCheck = $pdo->prepare("SELECT status FROM rooms WHERE id = ? FOR UPDATE");
            $roomCheck->execute([$room_id]);
            $room = $roomCheck->fetch();

            if (!$room || $room['status'] !== 'available') {
                throw new Exception('Selected room is no longer available.');
            }

            $insert = $pdo->prepare("INSERT INTO examination_rooms (
                pet_id, room_id, equipment_ready, supplies_ready, sanitation_done, notes, status, prepared_by
            ) VALUES (?, ?, ?, ?, ?, ?, 'ready', ?)");
            $insert->execute([
                $pet_id,
                $room_id,
                $equipment_ready,
                $supplies_ready,
                $sanitation_done,
                $notes !== '' ? $notes : null,
                $prepared_by
            ]);
            $record_id = (int)$pdo->lastInsertId();

            $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
            $updateRoom->execute([$room_id]);

            log_action($pdo, $prepared_by, 'vet_assistant', 'room_prepared', 'examination_rooms', $record_id);

            $pdo->commit();
            $success = 'Room marked as ready. Veterinary Technician has been notified.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$petsStmt = $pdo->query("SELECT pr.id AS record_id, p.id AS pet_id, p.name AS pet_name, u.name AS owner_name
                         FROM pet_records pr
                         JOIN pets p ON pr.pet_id = p.id
                         JOIN users u ON p.owner_id = u.id
                         WHERE pr.status = 'validated'
                         ORDER BY pr.visit_date ASC, p.name ASC");
$validated_pets = $petsStmt->fetchAll();

$roomsStmt = $pdo->query("SELECT id, room_name FROM rooms WHERE status = 'available' ORDER BY room_name ASC");
$available_rooms = $roomsStmt->fetchAll();

$current_page = 'prepare_room';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Prepare Room</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Prepare Room</p>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-clinic'></i> Room Preparation Form</h2>
  </div>
  <form method="POST" action="">
    <div class="grid grid-2">
      <div class="form-group">
        <label>Select Pet (Validated Visit) *</label>
        <select name="pet_id" required>
          <option value="">-- Select Pet --</option>
          <?php foreach ($validated_pets as $pet): ?>
            <option value="<?= (int)$pet['pet_id'] ?>">
              <?= htmlspecialchars($pet['pet_name']) ?> - Owner: <?= htmlspecialchars($pet['owner_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Select Available Room *</label>
        <select name="room_id" required>
          <option value="">-- Select Room --</option>
          <?php foreach ($available_rooms as $room): ?>
            <option value="<?= (int)$room['id'] ?>"><?= htmlspecialchars($room['room_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="section-title">Preparation Checklist</div>
    <div class="grid grid-3">
      <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="equipment_ready"> Equipment ready</label>
      <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="supplies_ready"> Rigorous Cleaning</label>
      <label style="display:flex; align-items:center; gap:8px;"><input type="checkbox" name="sanitation_done"> Sanitation done</label>
    </div>

    <div class="form-group mt-4">
      <label>Notes</label>
      <textarea name="notes" rows="4" placeholder="Optional room preparation remarks"></textarea>
    </div>

    <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Mark Room as Ready</button>
  </form>
</div>

<?php require_once '../../includes/footer.php'; ?>