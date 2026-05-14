<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');

$tests_queue = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE result IS NULL OR result = ''")->fetchColumn();
$submitted_today = (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE DATE(date) = CURDATE() AND result IS NOT NULL AND result != ''")->fetchColumn();
$pending_consent = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'pending_consent'")->fetchColumn();
$monitoring_active = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'monitoring'")->fetchColumn();
$discharge_approval_queue = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'monitoring'")->fetchColumn();

$notifications = $pdo->query("SELECT tn.id, tn.plan_id, tp.date, p.name as pet_name FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE tn.role = 'vet_technician' AND tn.type = 'owner_approved' AND tn.is_read = 0")->fetchAll();

$approvalPlansFallback = $pdo->query("
    SELECT tp.id AS plan_id, tp.date, p.name AS pet_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    WHERE tp.consent_status = 'approved'
      AND tp.workflow_status NOT IN ('forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed')
")->fetchAll();
$seenPlanIds = [];
foreach ($notifications as $n) {
    $seenPlanIds[(int)$n['plan_id']] = true;
}
foreach ($approvalPlansFallback as $p) {
    $pid = (int)$p['plan_id'];
    if (!isset($seenPlanIds[$pid])) {
        $notifications[] = $p;
        $seenPlanIds[$pid] = true;
    }
}
$critical_alerts = $pdo->query("SELECT tn.id, tn.plan_id, p.name as pet_name FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE tn.role = 'vet_technician' AND tn.type = 'monitoring_critical' AND tn.is_read = 0")->fetchAll();

$current_page = 'index';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Veterinary Technician Overview</h1><p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Overview</p></div></div>

<?php if (!empty($notifications)): ?>
<div class="card" style="border-left: 4px solid var(--color-success);">
  <div class="card-header">
    <h2 style="color:var(--color-success);"><i class='bx bx-check-shield'></i> Approved by Owner</h2>
  </div>
  <p class="text-muted mb-4">The following treatment plans have been approved by the pet owners and are ready to be forwarded to the Vet Assistant.</p>
  <?php foreach ($notifications as $notif): ?>
    <div class="alert alert-success flex justify-between items-center mb-2">
      <div>
        <strong>Treatment Plan #<?= htmlspecialchars($notif['plan_id']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — Pet: <?= htmlspecialchars($notif['pet_name']) ?></span><br>
      </div>
      <a href="/Petmate/dashboards/vet_technician/treatment_details.php?plan_id=<?= $notif['plan_id'] ?>" class="btn btn-success btn-sm">
        <i class='bx bx-search'></i> Review & Forward
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($critical_alerts)): ?>
<div class="card mb-6" style="border-left: 4px solid var(--color-danger);">
  <div class="card-header">
    <h2 style="color:var(--color-danger);"><i class='bx bx-error-alt'></i> Monitoring alert — critical</h2>
  </div>
  <p class="text-muted mb-4">A patient has been logged as critical during recovery monitoring. Review the case immediately.</p>
  <?php foreach ($critical_alerts as $alert): ?>
    <div class="alert alert-error flex justify-between items-center mb-2">
      <div>
        <strong>Plan #<?= htmlspecialchars((string)$alert['plan_id']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — <?= htmlspecialchars($alert['pet_name']) ?></span>
      </div>
      <a href="/Petmate/dashboards/vet_technician/view_treatment.php?id=<?= (int)$alert['plan_id'] ?>" class="btn btn-danger btn-sm"><i class='bx bx-show'></i> Review</a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$scheduling_ready = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE consent_status = 'approved' AND workflow_status NOT IN ('forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed')")->fetchColumn();
?>

<div class="grid grid-3 mb-6">
  <div class="stat-card"><span class="stat-label">Pending owner consent</span><span class="stat-value warning"><?= $pending_consent ?></span></div>
  <div class="stat-card"><span class="stat-label">Ready to schedule / forward</span><span class="stat-value success"><?= $scheduling_ready ?></span></div>
  <div class="stat-card"><span class="stat-label">In recovery monitoring</span><span class="stat-value accent"><?= $monitoring_active ?></span></div>
</div>
<div class="grid grid-3 mb-6">
  <div class="stat-card"><span class="stat-label">Tests in Queue</span><span class="stat-value"><?= $tests_queue ?></span></div>
  <div class="stat-card"><span class="stat-label">Results Submitted Today</span><span class="stat-value success"><?= $submitted_today ?></span></div>
  <div class="stat-card"><span class="stat-label">Discharge approvals pending</span><span class="stat-value"><?= $discharge_approval_queue ?></span></div>
</div>
<div class="grid grid-3">
  <div class="card"><h2><i class='bx bx-check-shield'></i> Discharge approval</h2><p class="text-muted mb-4">Review monitoring and approve patients for discharge preparation when recovery criteria are met.</p><a href="/Petmate/dashboards/vet_technician/approve_discharge.php" class="btn btn-success">Open queue</a></div>
  <div class="card"><h2><i class='bx bx-door-open'></i> Exam Rooms</h2><p class="text-muted mb-4">View rooms marked ready by Vet Assistants and take the patient.</p><a href="/Petmate/dashboards/vet_technician/exam_rooms.php" class="btn btn-primary">Open Exam Rooms</a></div>
  <div class="card"><h2><i class='bx bx-list-ul'></i> Assessment Queue</h2><p class="text-muted mb-4">Review pending diagnostics and laboratory requests.</p><a href="/Petmate/dashboards/vet_technician/assessment_queue.php" class="btn btn-primary">Open Queue</a></div>
  <div class="card"><h2><i class='bx bx-detail'></i> Treatment Details</h2><p class="text-muted mb-4">View downstream treatment details from submitted tests.</p><a href="/Petmate/dashboards/vet_technician/treatment_details.php" class="btn btn-outline">View Details</a></div>
</div>
<?php require_once '../../includes/footer.php'; ?>