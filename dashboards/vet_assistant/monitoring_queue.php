<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

$stmt = $pdo->query("
    SELECT tp.id, tp.description, tp.date, p.name AS pet_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    WHERE tp.workflow_status = 'monitoring'
    ORDER BY tp.date DESC
");
$rows = $stmt->fetchAll();

$current_page = 'monitoring_queue';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Monitoring queue</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Recovery monitoring</p>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2><i class='bx bx-pulse'></i> Patients in recovery</h2></div>
  <?php if (empty($rows)): ?>
    <div class="empty-state"><p>No patients are currently in the monitoring stage.</p></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th>Plan</th><th>Pet</th><th>Description</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><strong><?= htmlspecialchars($r['pet_name']) ?></strong></td>
            <td style="max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($r['description']) ?>"><?= htmlspecialchars($r['description']) ?></td>
            <td><a class="btn btn-sm btn-primary" href="monitor_patient.php?plan_id=<?= (int)$r['id'] ?>"><i class='bx bx-show'></i> Monitor</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
