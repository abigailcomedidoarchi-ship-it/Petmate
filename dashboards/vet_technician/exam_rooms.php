<?php
require_once '../../includes/db.php';
require_once '../../includes/session_guard.php';
require_once '../../includes/rbac.php';
require_once '../../includes/auth.php';

requireRole('vet_technician');
require_permission('manage_exam_rooms');

$rooms = $pdo->query("
    SELECT er.*,
           r.room_name,
           p.name  AS pet_name,
           u.name  AS prepared_by_name,
           o.name  AS owner_name
    FROM examination_rooms er
    JOIN rooms r ON r.id = er.room_id
    JOIN pets p  ON p.id = er.pet_id
    LEFT JOIN users u ON u.id = er.prepared_by
    JOIN users o ON o.id = p.owner_id
    WHERE er.status = 'ready'
    ORDER BY er.created_at DESC
")->fetchAll();

$current_page = 'exam_rooms.php';
require_once '../../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">Ready Examination Rooms</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Exam Rooms</p>
  </div>
  <a href="/Petmate/dashboards/vet_technician/index.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
</div>

<div class="card">
  <?php if (empty($rooms)): ?>
    <div class="empty-state">
      <i class='bx bx-check-circle' style="color:var(--color-success);"></i>
      <p>No rooms are currently waiting for examination.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Room</th>
            <th>Pet</th>
            <th>Owner</th>
            <th style="text-align:center;">Equip.</th>
            <th style="text-align:center;">Suppl.</th>
            <th style="text-align:center;">Sanit.</th>
            <th>Notes</th>
            <th>Prepared By</th>
            <th>Time Ready</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $room): ?>
          <tr>
            <td><strong><?= htmlspecialchars($room['room_name']) ?></strong></td>
            <td><?= htmlspecialchars($room['pet_name']) ?></td>
            <td><?= htmlspecialchars($room['owner_name']) ?></td>
            <td style="text-align:center; color:<?= $room['equipment_ready'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
              <i class="bx <?= $room['equipment_ready'] ? 'bx-check' : 'bx-x' ?>" style="font-size:18px;"></i>
            </td>
            <td style="text-align:center; color:<?= $room['supplies_ready'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
              <i class="bx <?= $room['supplies_ready'] ? 'bx-check' : 'bx-x' ?>" style="font-size:18px;"></i>
            </td>
            <td style="text-align:center; color:<?= $room['sanitation_done'] ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
              <i class="bx <?= $room['sanitation_done'] ? 'bx-check' : 'bx-x' ?>" style="font-size:18px;"></i>
            </td>
            <td><small style="color:var(--color-muted);"><?= htmlspecialchars($room['notes'] ?: '—') ?></small></td>
            <td><?= htmlspecialchars($room['prepared_by_name']) ?></td>
            <td><small style="color:var(--color-muted);"><?= date('M d, g:i A', strtotime($room['created_at'])) ?></small></td>
            <td>
              <form method="POST" action="acknowledge_room.php">
                <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class='bx bx-walk'></i> Take Patient
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
