<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$stmt = $pdo->query("SELECT pr.*, p.name as pet_name, u.name as owner_name
                     FROM pet_records pr
                     JOIN pets p ON pr.pet_id = p.id
                     JOIN users u ON p.owner_id = u.id
                     WHERE pr.status = 'pending' ORDER BY pr.visit_date ASC");
$pending_records = $stmt->fetchAll();

$current_page = 'pet_info';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Pet Information</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Pet Information</p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-check-circle'></i> Pending Validations</h2>
    <span class="badge badge-pending"><?= count($pending_records) ?></span>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Date</th><th>Pet</th><th>Owner</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($pending_records)): ?>
          <tr><td colspan="4"><div class="empty-state"><i class='bx bx-check-shield'></i><p>No pending validations.</p></div></td></tr>
        <?php else: foreach ($pending_records as $record): ?>
          <tr>
            <td style="font-size:12px; color:var(--color-muted);"><?= date('M d', strtotime($record['visit_date'])) ?></td>
            <td><strong><?= htmlspecialchars($record['pet_name']) ?></strong></td>
            <td><?= htmlspecialchars($record['owner_name']) ?></td>
            <td><a href="/Petmate/dashboards/csr/review_record.php?id=<?= $record['id'] ?>" class="btn btn-accent btn-sm"><i class='bx bx-search-alt'></i> Review</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<?php
