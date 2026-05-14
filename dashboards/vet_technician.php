<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');

// Fetch pending assessments
$stmt = $pdo->query("SELECT a.*, p.name as pet_name
                     FROM assessments a
                     JOIN pets p ON a.pet_id = p.id
                     WHERE a.result IS NULL OR a.result = '' ORDER BY a.date ASC");
$assessments = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">Veterinary Technician Dashboard</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician</p>
  </div>
</div>

<!-- Treatment & Discharge -->
<div class="card mb-4">
  <div class="card-header">
    <h2><i class='bx bx-notepad'></i> Treatment & Discharge</h2>
  </div>
  <p class="text-muted mb-4">Create treatment plans and prepare patients for discharge.</p>

  <div class="grid grid-3">
    <div style="border-left: 4px solid var(--color-accent); padding-left: 16px;">
      <h3 style="font-size:15px; margin-bottom:6px;">Assess Patient</h3>
      <p class="text-muted" style="font-size:13px; margin-bottom:12px;">Create assessment record and order lab tests if necessary.</p>
      <button class="btn btn-outline btn-sm"><i class='bx bx-plus'></i> New Assessment</button>
    </div>

    <div style="border-left: 4px solid var(--color-success); padding-left: 16px;">
      <h3 style="font-size:15px; margin-bottom:6px;">Treatment Plan</h3>
      <p class="text-muted" style="font-size:13px; margin-bottom:12px;">Present plan to owner, prescribe medication, and relay instructions to assistant.</p>
      <button class="btn btn-outline btn-sm"><i class='bx bx-file-blank'></i> Draft Plan</button>
    </div>

    <div style="border-left: 4px solid var(--color-espresso); padding-left: 16px;">
      <h3 style="font-size:15px; margin-bottom:6px;">Discharge</h3>
      <p class="text-muted" style="font-size:13px; margin-bottom:12px;">Prepare discharge summary and finalize the visit.</p>
      <button class="btn btn-outline btn-sm"><i class='bx bx-check-circle'></i> Prepare Discharge</button>
    </div>
  </div>
</div>

<!-- Lab & Assessment Queue -->
<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-test-tube'></i> Lab & Assessment Queue</h2>
    <span class="badge badge-pending"><?= count($assessments) ?> pending</span>
  </div>
  <p class="text-muted mb-4">Perform tests and record assessment data.</p>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Pet</th>
          <th>Test Type</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($assessments)): ?>
          <tr><td colspan="5"><div class="empty-state"><i class='bx bx-test-tube'></i><p>No pending tests.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($assessments as $test): ?>
          <tr>
            <td style="font-size:12px; color:var(--color-muted);"><?= date('M d, H:i', strtotime($test['date'])) ?></td>
            <td><strong><?= htmlspecialchars($test['pet_name']) ?></strong></td>
            <td><span class="badge badge-pending"><?= htmlspecialchars($test['test_type']) ?></span></td>
            <td><span class="badge badge-pending">Pending</span></td>
            <td>
              <button class="btn btn-primary btn-sm"><i class='bx bx-edit'></i> Record Results</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
