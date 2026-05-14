<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$stmt = $pdo->query("SELECT pr.*, p.name as pet_name, u.name as owner_name
                     FROM pet_records pr
                     JOIN pets p ON pr.pet_id = p.id
                     JOIN users u ON p.owner_id = u.id
                     WHERE pr.status IN ('validated', 'completed') ORDER BY pr.visit_date DESC");
$validated_records = $stmt->fetchAll();

$current_page = 'pet_records';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Pet Records</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Pet Records</p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-list-check'></i> Validated Records</h2>
    <span class="text-muted"><?= count($validated_records) ?> records</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Date</th><th>Pet</th><th>Owner</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($validated_records)): ?>
          <tr><td colspan="5"><div class="empty-state"><i class='bx bx-list-check'></i><p>No validated records.</p></div></td></tr>
        <?php else: foreach ($validated_records as $record): ?>
          <tr>
            <td style="font-size:12px; color:var(--color-muted);"><?= date('M d, Y', strtotime($record['visit_date'])) ?></td>
            <td><strong><?= htmlspecialchars($record['pet_name']) ?></strong></td>
            <td><?= htmlspecialchars($record['owner_name']) ?></td>
            <td><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span></td>
            <td><a href="/Petmate/dashboards/csr/review_record.php?id=<?= $record['id'] ?>" class="btn btn-outline btn-sm"><i class='bx bx-show'></i> View</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<?php
