<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/rbac.php';

requireRole('vet_technician');
require_permission('view_dashboard');

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$error = '';

$stmt = $pdo->prepare("
    SELECT s.*, p.name AS pet_name, rm.room_name
    FROM assessment_sessions s
    JOIN pets p ON p.id = s.pet_id
    LEFT JOIN examination_rooms er ON er.id = s.room_id
    LEFT JOIN rooms rm ON rm.id = er.room_id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    $error = 'Assessment summary not found.';
}

$tests = [];
if ($session) {
    $rows = $pdo->prepare("SELECT test_type, result, status, updated_at FROM assessments WHERE assessment_session_id = ? ORDER BY id ASC");
    $rows->execute([$session_id]);
    $tests = $rows->fetchAll();
}

$current_page = 'record_results';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Assessment Summary</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Assessment Summary</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php else: ?>
  <div class="card">
    <div class="info-row"><span class="info-label">Patient</span><span class="info-value"><?= htmlspecialchars($session['pet_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Room</span><span class="info-value"><?= htmlspecialchars($session['room_name'] ?: 'N/A') ?></span></div>
    <div class="info-row"><span class="info-label">Session Status</span><span class="info-value"><span class="badge badge-validated"><?= htmlspecialchars(ucfirst($session['status'])) ?></span></span></div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class='bx bx-list-check'></i> Submitted Tests</h2></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Test</th><th>Short Summary</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
        <?php if (empty($tests)): ?>
          <tr><td colspan="4"><div class="empty-state"><i class='bx bx-info-circle'></i><p>No tests found for this session.</p></div></td></tr>
        <?php else: foreach ($tests as $test): ?>
          <tr>
            <td><strong><?= htmlspecialchars(strtoupper($test['test_type'])) ?></strong></td>
            <td><?= htmlspecialchars($test['result'] ?: '-') ?></td>
            <td><span class="badge badge-completed"><?= htmlspecialchars(ucfirst($test['status'])) ?></span></td>
            <td><?= htmlspecialchars($test['updated_at'] ? date('M d, Y H:i', strtotime($test['updated_at'])) : '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="action-bar">
    <a href="/Petmate/dashboards/vet_technician/exam_rooms.php" class="btn btn-primary"><i class='bx bx-check-circle'></i> Back to Exam Rooms</a>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
