<?php
require_once '../../includes/db.php';
require_once '../../includes/session_guard.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireRole('vet_assistant');
require_permission('view_dashboard');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_room_id'])) {
    $release_room_id = (int)$_POST['release_room_id'];

    try {
        $pdo->beginTransaction();

        $roomStmt = $pdo->prepare("SELECT status FROM rooms WHERE id = ? FOR UPDATE");
        $roomStmt->execute([$release_room_id]);
        $room = $roomStmt->fetch();

        if (!$room) {
            throw new Exception('Room not found.');
        }

        if ($room['status'] === 'occupied') {
            $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$release_room_id]);

            $pdo->prepare("UPDATE examination_rooms 
                           SET status = 'done' 
                           WHERE room_id = ? AND status IN ('ready', 'in_use')
                           ORDER BY created_at DESC
                           LIMIT 1")->execute([$release_room_id]);

            log_audit($pdo, $_SESSION['user_id'], 'vet_assistant:room_released', 'rooms', $release_room_id);
            $success = 'Room released and set to available.';
        } else {
            $error = 'Only occupied rooms can be released.';
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error === '') {
            $error = $e->getMessage();
        }
    }
}

$roomsStmt = $pdo->query("SELECT r.id, r.room_name, r.status,
                                 p.name AS current_pet_name
                          FROM rooms r
                          LEFT JOIN examination_rooms er
                            ON er.room_id = r.id
                           AND er.id = (
                                SELECT er2.id
                                FROM examination_rooms er2
                                WHERE er2.room_id = r.id
                                ORDER BY er2.created_at DESC
                                LIMIT 1
                           )
                          LEFT JOIN pets p ON p.id = er.pet_id
                          ORDER BY r.room_name ASC");
$rooms = $roomsStmt->fetchAll();

function room_status_badge_class($status) {
    switch ($status) {
        case 'available':
            return 'badge-validated';
        case 'occupied':
            return 'badge-rejected';
        case 'cleaning':
            return 'badge-pending';
        case 'maintenance':
            return 'badge-suspended';
        default:
            return 'badge-pending';
    }
}

function room_status_color($status) {
    switch ($status) {
        case 'available':
            return 'var(--color-success)';
        case 'occupied':
            return 'var(--color-danger)';
        case 'cleaning':
            return '#eab308';
        case 'maintenance':
            return 'var(--color-muted)';
        default:
            return 'var(--color-text)';
    }
}

$current_page = 'room_status';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Room Status Monitoring</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Room Status</p>
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
    <h2><i class='bx bx-table'></i> Current Clinic Room Status</h2>
  </div>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Room</th>
          <th>Status</th>
          <th>Current Pet</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rooms)): ?>
          <tr><td colspan="4"><div class="empty-state"><i class='bx bx-clinic'></i><p>No rooms found.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($rooms as $room): ?>
            <tr>
              <td><strong><?= htmlspecialchars($room['room_name']) ?></strong></td>
              <td><span class="badge <?= room_status_badge_class($room['status']) ?>" style="color: <?= room_status_color($room['status']) ?>; border-color: <?= room_status_color($room['status']) ?>33;"><?= ucfirst($room['status']) ?></span></td>
              <td><?= $room['status'] === 'occupied' ? htmlspecialchars($room['current_pet_name'] ?: '-') : '-' ?></td>
              <td>
                <?php if ($room['status'] === 'occupied'): ?>
                  <form method="POST" action="">
                    <input type="hidden" name="release_room_id" value="<?= (int)$room['id'] ?>">
                    <button type="submit" class="btn btn-outline btn-sm"><i class='bx bx-reset'></i> Release Room</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">No action</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
