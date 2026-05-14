<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');

$stmt = $pdo->query("SELECT a.*, p.name as pet_name
                     FROM assessments a
                     JOIN pets p ON a.pet_id = p.id
                     WHERE a.result IS NULL OR a.result = '' ORDER BY a.date ASC");
$assessments = $stmt->fetchAll();

$current_page = 'assessment_queue';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Assessment Queue</h1><p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Assessment Queue</p></div></div>
<div class="card">
  <div class="card-header"><h2><i class='bx bx-test-tube'></i> Lab &amp; Assessment Queue</h2><span class="badge badge-pending"><?= count($assessments) ?> pending</span></div>
  <div class="table-responsive"><table><thead><tr><th>Date</th><th>Pet</th><th>Test Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php if (empty($assessments)): ?><tr><td colspan="5"><div class="empty-state"><i class='bx bx-test-tube'></i><p>No pending tests.</p></div></td></tr>
    <?php else: foreach ($assessments as $test): ?>
      <tr><td style="font-size:12px; color:var(--color-muted);"><?= date('M d, H:i', strtotime($test['date'])) ?></td><td><strong><?= htmlspecialchars($test['pet_name']) ?></strong></td><td><span class="badge badge-pending"><?= htmlspecialchars($test['test_type']) ?></span></td><td><span class="badge badge-pending">Pending</span></td><td><a href="/Petmate/dashboards/vet_technician/record_results.php?id=<?= $test['id'] ?>" class="btn btn-primary btn-sm"><i class='bx bx-edit'></i> Record Results</a></td></tr>
    <?php endforeach; endif; ?>
  </tbody></table></div>
</div>
<?php require_once '../../includes/footer.php'; ?>