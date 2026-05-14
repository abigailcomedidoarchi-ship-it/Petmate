<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/treatment_workflow_schema.php';
requireRole('vet_technician');
require_permission('view_dashboard');

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$error = '';
$success = '';

if (!$plan_id) {
    header('Location: treatment_details.php');
    exit;
}

petmate_ensure_treatment_plan_workflow_varchar($pdo);

$stmt = $pdo->prepare("
    SELECT tp.*, p.name AS pet_name, p.species, p.breed, u.name AS owner_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    WHERE tp.id = ?
");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    die("Treatment plan not found.");
}

if ($plan['consent_status'] !== 'approved') {
    die('Plan must be owner-approved before scheduling and assignment.');
}
$wfAssign = trim((string)($plan['workflow_status'] ?? ''));
$blockedAssign = ['forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed'];
if (in_array($wfAssign, $blockedAssign, true)) {
    die('This treatment plan has already been assigned or is past the scheduling stage.');
}

$data = json_decode($plan['prescriptions'], true) ?: [];
$medicines = $data['medicines'] ?? [];
$surgeries = $data['surgeries'] ?? [];
$procedures = $data['procedures'] ?? [];

$assistants = $pdo->query("SELECT id, name FROM users WHERE role = 'vet_assistant' AND status = 'active'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assigned_assistant_id = (int)$_POST['assigned_assistant_id'];
    $monitoring_instructions = trim($_POST['monitoring_instructions'] ?? '');
    
    if (isset($_POST['surgeries'])) {
        foreach ($_POST['surgeries'] as $index => $surg_data) {
            if (isset($surgeries[$index])) {
                $surgeries[$index]['scheduled_date'] = $surg_data['scheduled_date'];
                $surgeries[$index]['scheduled_time'] = $surg_data['scheduled_time'];
            }
        }
    }
    
    if (isset($_POST['procedures'])) {
        foreach ($_POST['procedures'] as $index => $proc_data) {
            if (isset($procedures[$index])) {
                $procedures[$index]['scheduled_date'] = $proc_data['scheduled_date'];
                $procedures[$index]['scheduled_time'] = $proc_data['scheduled_time'];
            }
        }
    }

    $data['surgeries'] = $surgeries;
    $data['procedures'] = $procedures;
    $data['monitoring_instructions'] = $monitoring_instructions;
    $new_prescriptions = json_encode($data);

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE treatment_plans SET prescriptions = ?, assigned_assistant_id = ?, workflow_status = 'forwarded' WHERE id = ?");
        $update->execute([$new_prescriptions, $assigned_assistant_id, $plan_id]);

        $pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE plan_id = ? AND role = 'vet_technician'")->execute([$plan_id]);
        $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_assistant', 'forwarded_to_assistant')")->execute([$plan_id]);

        $pdo->commit();
        header('Location: treatment_details.php?forwarded=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to schedule and assign plan.";
    }
}

$current_page = 'treatment_details';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Schedule & Assign Treatment</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Treatment Details <span>›</span> Schedule & Assign</p>
  </div>
  <div>
    <a href="treatment_details.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back to List</a>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
      <h2><i class='bx bx-calendar-event'></i> Plan #<?= (int)$plan['id'] ?> - <?= htmlspecialchars($plan['pet_name']) ?></h2>
  </div>
  
  <form method="POST">
    <div class="grid grid-2" style="margin-bottom: 24px;">
      <div class="form-group">
        <label>Assign to Vet Assistant *</label>
        <select name="assigned_assistant_id" required class="form-control">
            <option value="">-- Select Vet Assistant --</option>
            <?php foreach ($assistants as $ast): ?>
                <option value="<?= $ast['id'] ?>"><?= htmlspecialchars($ast['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (!empty($surgeries)): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary);"><i class='bx bx-cut'></i> Schedule Surgeries</h3>
    <?php foreach ($surgeries as $i => $surg): ?>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
            <p><strong>Surgery Name:</strong> <?= htmlspecialchars($surg['name']) ?></p>
            <div class="grid grid-2 mt-2">
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="date" name="surgeries[<?= $i ?>][scheduled_date]" class="form-control" value="<?= htmlspecialchars($surg['scheduled_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Scheduled Time</label>
                    <input type="time" name="surgeries[<?= $i ?>][scheduled_time]" class="form-control" value="<?= htmlspecialchars($surg['scheduled_time'] ?? '') ?>">
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($procedures)): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-pulse'></i> Schedule Procedures</h3>
    <?php foreach ($procedures as $i => $proc): ?>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
            <p><strong>Procedure Name:</strong> <?= htmlspecialchars($proc['name']) ?></p>
            <div class="grid grid-2 mt-2">
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="date" name="procedures[<?= $i ?>][scheduled_date]" class="form-control" value="<?= htmlspecialchars($proc['scheduled_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Scheduled Time</label>
                    <input type="time" name="procedures[<?= $i ?>][scheduled_time]" class="form-control" value="<?= htmlspecialchars($proc['scheduled_time'] ?? '') ?>">
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-notepad'></i> Monitoring & Task Assignments</h3>
    <div class="form-group">
        <label>Monitoring Responsibilities & Notes</label>
        <textarea name="monitoring_instructions" rows="4" class="form-control" placeholder="Specify any medications to administer, vitals to check, frequency of checks..."><?= htmlspecialchars($data['monitoring_instructions'] ?? '') ?></textarea>
    </div>

    <div class="action-bar mt-4">
      <button type="submit" class="btn btn-primary"><i class='bx bx-share-alt'></i> Assign & Forward Plan</button>
    </div>
  </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
