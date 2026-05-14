<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

$rooms_to_prepare = (int)$pdo->query("SELECT COUNT(*) FROM pet_records WHERE status = 'validated'")->fetchColumn();
$monitoring_tasks = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'monitoring'")->fetchColumn();
$discharge_ready_count = (int)$pdo->query("SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'discharge_ready'")->fetchColumn();

$user_id = (int)$_SESSION['user_id'];
$notifications = $pdo->prepare("SELECT tn.id, tn.plan_id, tp.date, p.name as pet_name FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE tn.role = 'vet_assistant' AND tn.type = 'forwarded_to_assistant' AND tn.is_read = 0 AND (tp.assigned_assistant_id = ? OR tp.assigned_assistant_id IS NULL)");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll();

$discharge_notifs = $pdo->prepare("SELECT tn.id, tn.plan_id, tp.date, p.name as pet_name FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE tn.role = 'vet_assistant' AND tn.type = 'discharge_summary_needed' AND tn.is_read = 0 AND (tp.assigned_assistant_id = ? OR tp.assigned_assistant_id IS NULL)");
$discharge_notifs->execute([$user_id]);
$discharge_notifs = $discharge_notifs->fetchAll();

$current_page = 'index';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Vet Assistant Overview</h1><p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Overview</p></div></div>

<?php if (!empty($notifications)): ?>
<div class="card" style="border-left: 4px solid var(--color-primary);">
  <div class="card-header">
    <h2 style="color:var(--color-primary);"><i class='bx bx-bell'></i> New Treatment Instructions</h2>
  </div>
  <p class="text-muted mb-4">The following treatment plans have been forwarded to you for administration.</p>
  <?php foreach ($notifications as $notif): ?>
    <div class="alert flex justify-between items-center mb-2" style="background:var(--color-bg); border:1px solid var(--color-border);">
      <div>
        <strong>Treatment Plan #<?= htmlspecialchars($notif['plan_id']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — Pet: <?= htmlspecialchars($notif['pet_name']) ?></span><br>
      </div>
      <a href="/Petmate/dashboards/vet_assistant/administer.php?plan_id=<?= $notif['plan_id'] ?>" class="btn btn-primary btn-sm">
        <i class='bx bx-injection'></i> Open Instructions
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($discharge_notifs)): ?>
<div class="card mb-6" style="border-left: 4px solid var(--color-success);">
  <div class="card-header">
    <h2 style="color:var(--color-success);"><i class='bx bx-box'></i> Discharge summary needed</h2>
  </div>
  <p class="text-muted mb-4">A patient has been cleared for discharge. Complete the discharge summary and home-care instructions.</p>
  <?php foreach ($discharge_notifs as $notif): ?>
    <div class="alert flex justify-between items-center mb-2" style="background:var(--color-bg); border:1px solid var(--color-border);">
      <div>
        <strong>Plan #<?= htmlspecialchars((string)$notif['plan_id']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — <?= htmlspecialchars($notif['pet_name']) ?></span>
      </div>
      <a href="/Petmate/dashboards/vet_assistant/discharge_prep.php?plan_id=<?= (int)$notif['plan_id'] ?>" class="btn btn-success btn-sm"><i class='bx bx-edit'></i> Discharge prep</a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-3 mb-6">
  <div class="stat-card"><span class="stat-label">Rooms to Prepare</span><span class="stat-value"><?= $rooms_to_prepare ?></span></div>
  <div class="stat-card"><span class="stat-label">Monitoring tasks</span><span class="stat-value accent"><?= $monitoring_tasks ?></span></div>
  <div class="stat-card"><span class="stat-label">Discharge ready</span><span class="stat-value success"><?= $discharge_ready_count ?></span></div>
</div>
<div class="grid grid-3">
  <div class="card"><h2><i class='bx bx-clinic'></i> Prepare Room</h2><p class="text-muted mb-4">Prepare exam rooms for validated patients awaiting the next step.</p><a href="/Petmate/dashboards/vet_assistant/prepare_room.php" class="btn btn-primary">Open Prepare Room</a></div>
  <div class="card"><h2><i class='bx bx-pulse'></i> Monitoring queue</h2><p class="text-muted mb-4">Record recovery observations and vitals for patients in the monitoring stage.</p><a href="/Petmate/dashboards/vet_assistant/monitoring_queue.php" class="btn btn-warning">Open monitoring</a></div>
  <div class="card"><h2><i class='bx bx-task'></i> Medical instructions</h2><p class="text-muted mb-4">Administration, monitoring, and discharge tasks in one pipeline.</p><a href="/Petmate/dashboards/vet_assistant/instructions.php" class="btn btn-outline">Open pipeline</a></div>
</div>
<?php require_once '../../includes/footer.php'; ?>