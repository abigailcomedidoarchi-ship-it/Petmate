<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$message = $_GET['msg'] ?? '';

// Fetch pending records
$stmt = $pdo->query("SELECT pr.*, p.name as pet_name, u.name as owner_name
                     FROM pet_records pr
                     JOIN pets p ON pr.pet_id = p.id
                     JOIN users u ON p.owner_id = u.id
                     WHERE pr.status = 'pending' ORDER BY pr.visit_date ASC");
$pending_records = $stmt->fetchAll();

// Fetch validated records
$stmtValidated = $pdo->query("SELECT pr.*, p.name as pet_name, u.name as owner_name
                              FROM pet_records pr
                              JOIN pets p ON pr.pet_id = p.id
                              JOIN users u ON p.owner_id = u.id
                              WHERE pr.status IN ('validated', 'completed') ORDER BY pr.visit_date DESC");
$validated_records = $stmtValidated->fetchAll();

require_once '../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">CSR Dashboard</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR</p>
  </div>
  <button class="btn btn-primary"><i class='bx bx-plus'></i> New Walk-in Record</button>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <!-- Pending Validations -->
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-check-circle'></i> Pending Validations</h2>
      <span class="badge badge-pending"><?= count($pending_records) ?></span>
    </div>
    <p class="text-muted mb-4">Review and validate initial pet information before passing to the Vet Assistant.</p>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Pet</th>
            <th>Owner</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pending_records)): ?>
            <tr><td colspan="4"><div class="empty-state"><i class='bx bx-check-shield'></i><p>No pending validations.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($pending_records as $record): ?>
            <tr>
              <td style="font-size:12px; color:var(--color-muted);"><?= date('M d', strtotime($record['visit_date'])) ?></td>
              <td><strong><?= htmlspecialchars($record['pet_name']) ?></strong></td>
              <td><?= htmlspecialchars($record['owner_name']) ?></td>
              <td>
                <a href="/Petmate/dashboards/review_record.php?id=<?= $record['id'] ?>" class="btn btn-accent btn-sm">
                  <i class='bx bx-search-alt'></i> Review
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Billing and Records -->
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-receipt'></i> Billing & Summaries</h2>
    </div>
    <p class="text-muted mb-4">Generate bills and forward visit summaries to records.</p>
    <div class="empty-state">
      <i class='bx bx-receipt'></i>
      <p>No pending billing actions at this time.</p>
    </div>
  </div>
</div>

<!-- Validated Records -->
<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-list-check'></i> Validated Records</h2>
    <span class="text-muted"><?= count($validated_records) ?> records</span>
  </div>
  <p class="text-muted mb-4">Past validations that have been accepted.</p>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Pet</th>
          <th>Owner</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($validated_records)): ?>
          <tr><td colspan="5"><div class="empty-state"><i class='bx bx-list-check'></i><p>No validated records.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($validated_records as $record): ?>
          <tr>
            <td style="font-size:12px; color:var(--color-muted);"><?= date('M d, Y', strtotime($record['visit_date'])) ?></td>
            <td><strong><?= htmlspecialchars($record['pet_name']) ?></strong></td>
            <td><?= htmlspecialchars($record['owner_name']) ?></td>
            <td><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span></td>
            <td>
              <a href="/Petmate/dashboards/review_record.php?id=<?= $record['id'] ?>" class="btn btn-outline btn-sm">
                <i class='bx bx-show'></i> View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
