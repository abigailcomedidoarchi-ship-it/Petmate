<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$message = $_GET['msg'] ?? '';
$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM pet_records WHERE status = 'pending'")->fetchColumn();
$validated_today = (int)$pdo->query("SELECT COUNT(*) FROM pet_records WHERE status = 'validated' AND DATE(visit_date) = CURDATE()")->fetchColumn();
$total_records = (int)$pdo->query("SELECT COUNT(*) FROM pet_records")->fetchColumn();
$rejected_count = (int)$pdo->query("SELECT COUNT(*) FROM pet_records WHERE status = 'rejected'")->fetchColumn();
$pending_billing = (int)$pdo->query("SELECT COUNT(*) FROM pet_records WHERE status = 'pending_billing'")->fetchColumn();

$csr_billing_alerts = $pdo->query("
    SELECT tn.id, tn.plan_id, tp.date, p.name AS pet_name
    FROM treatment_notifications tn
    JOIN treatment_plans tp ON tp.id = tn.plan_id
    JOIN pets p ON p.id = tp.pet_id
    WHERE tn.role = 'csr' AND tn.type = 'discharge_pending_billing' AND tn.is_read = 0
    ORDER BY tn.id DESC
")->fetchAll();

$current_page = 'index';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">CSR Overview</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Overview</p>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($csr_billing_alerts)): ?>
<div class="card mb-6" style="border-left: 4px solid var(--color-accent, #f59e0b);">
  <div class="card-header">
    <h2 style="color:var(--color-heading);"><i class='bx bx-receipt'></i> Discharged — ready to bill</h2>
  </div>
  <p class="text-muted mb-4">The vet assistant finalized discharge for the following pets. Open Billing to compute the invoice.</p>
  <?php foreach ($csr_billing_alerts as $alert): ?>
    <div class="alert flex justify-between items-center mb-2" style="background:var(--color-bg); border:1px solid var(--color-border);">
      <div>
        <strong>Treatment plan #<?= htmlspecialchars((string)$alert['plan_id']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — Pet: <?= htmlspecialchars($alert['pet_name']) ?></span>
      </div>
      <a href="/Petmate/dashboards/csr/compute_bill.php?plan_id=<?= (int)$alert['plan_id'] ?>" class="btn btn-accent btn-sm">
        <i class='bx bx-calculator'></i> Compute bill
      </a>
    </div>
  <?php endforeach; ?>
  <a href="/Petmate/dashboards/csr/billing.php" class="btn btn-outline btn-sm mt-2"><i class='bx bx-list-ul'></i> View all pending billing</a>
</div>
<?php endif; ?>

<div class="grid grid-5 mb-6">
  <div class="stat-card"><span class="stat-label">Pending Validations</span><span class="stat-value"><?= $pending_count ?></span></div>
  <div class="stat-card"><span class="stat-label">Validated Today</span><span class="stat-value success"><?= $validated_today ?></span></div>
  <div class="stat-card"><span class="stat-label">Awaiting billing</span><span class="stat-value accent"><?= $pending_billing ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Records</span><span class="stat-value"><?= $total_records ?></span></div>
  <div class="stat-card"><span class="stat-label">Rejected Records</span><span class="stat-value danger"><?= $rejected_count ?></span></div>
</div>

<div class="grid grid-3 mb-6">
  <div class="card">
    <div class="card-header"><h2><i class='bx bx-info-circle'></i> Pet Information</h2></div>
    <p class="text-muted mb-4">Review and validate pending initial records.</p>
    <a href="/Petmate/dashboards/csr/pet_info.php" class="btn btn-primary">Open Pet Information</a>
  </div>
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-credit-card'></i> Billing</h2>
      <?php if($pending_billing > 0): ?>
         <span class="badge badge-pending"><?= $pending_billing ?></span>
      <?php endif; ?>
    </div>
    <p class="text-muted mb-4">Compute bills for discharged pets.</p>
    <a href="/Petmate/dashboards/csr/billing.php" class="btn btn-accent">Open Billing</a>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class='bx bx-folder'></i> Pet Records</h2></div>
    <p class="text-muted mb-4">Browse validated and completed patient records.</p>
    <a href="/Petmate/dashboards/csr/pet_records.php" class="btn btn-outline">Open Pet Records</a>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
