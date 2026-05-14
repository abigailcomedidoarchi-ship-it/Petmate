<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/monitoring_logs_schema.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

petmate_ensure_monitoring_logs_table($pdo);

function ml_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    try {
        $s = $pdo->prepare('SHOW COLUMNS FROM monitoring_logs LIKE ?');
        $s->execute([$column]);
        $cache[$column] = (bool) $s->fetch();
    } catch (Throwable $e) {
        $cache[$column] = false;
    }
    return $cache[$column];
}

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

if (!$plan_id) {
    header('Location: monitoring_queue.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT tp.*, p.name AS pet_name, p.species, p.breed, u.name AS owner_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    WHERE tp.id = ? AND tp.workflow_status = 'monitoring'
");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    die('Treatment plan not found or not in recovery monitoring.');
}

$data = json_decode($plan['prescriptions'], true) ?: [];
$medicines = $data['medicines'] ?? [];
$surgeries = $data['surgeries'] ?? [];
$procedures = $data['procedures'] ?? [];

$admin_logs = $pdo->prepare("
    SELECT al.*, u.name AS staff_name
    FROM administration_logs al
    LEFT JOIN users u ON u.id = al.vet_assistant_id
    WHERE al.plan_id = ?
    ORDER BY al.administered_at DESC, al.id DESC
");
$admin_logs->execute([$plan_id]);
$admin_logs = $admin_logs->fetchAll();

$mon_logs = $pdo->prepare("
    SELECT ml.*, u.name AS staff_name
    FROM monitoring_logs ml
    LEFT JOIN users u ON u.id = ml.vet_assistant_id
    WHERE ml.plan_id = ?
    ORDER BY ml.created_at DESC, ml.id DESC
");
$mon_logs->execute([$plan_id]);
$mon_logs = $mon_logs->fetchAll();

$latestMon = $mon_logs[0] ?? null;
$criticalActive = $latestMon && ($latestMon['patient_status'] ?? '') === 'critical';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_monitoring'])) {
    $observation = trim($_POST['observation'] ?? '');
    $patient_status = $_POST['patient_status'] ?? 'under_observation';
    $allowed_status = ['stable', 'recovering', 'critical', 'under_observation'];
    if (!in_array($patient_status, $allowed_status, true)) {
        $patient_status = 'under_observation';
    }
    $temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float) $_POST['temperature'] : null;
    $appetite = trim($_POST['appetite'] ?? '');
    $energy_level = trim($_POST['energy_level'] ?? '');
    $complications = trim($_POST['complications'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $wound_condition = trim($_POST['wound_condition'] ?? '');
    $bleeding = trim($_POST['bleeding'] ?? '');
    $pain_indicators = trim($_POST['pain_indicators'] ?? '');
    $medication_response = trim($_POST['medication_response'] ?? '');
    $recovery_observations = trim($_POST['recovery_observations'] ?? '');

    $hasBody = $observation !== '' || $notes !== '' || $complications !== ''
        || $wound_condition !== '' || $bleeding !== '' || $pain_indicators !== ''
        || $medication_response !== '' || $recovery_observations !== ''
        || $appetite !== '' || $energy_level !== ''
        || $temperature !== null;

    if (!$hasBody) {
        $error = 'Please enter at least one observation field (clinical note, wound, complications, etc.).';
    } else {
        try {
            $pdo->beginTransaction();
            $cols = ['plan_id', 'vet_assistant_id', 'observation', 'patient_status', 'temperature', 'appetite', 'energy_level', 'complications', 'notes'];
            $vals = [
                $plan_id,
                $user_id,
                $observation ?: null,
                $patient_status,
                $temperature,
                $appetite ?: null,
                $energy_level ?: null,
                $complications ?: null,
                $notes ?: null,
            ];
            $extra = [
                'wound_condition' => $wound_condition ?: null,
                'bleeding' => $bleeding ?: null,
                'pain_indicators' => $pain_indicators ?: null,
                'medication_response' => $medication_response ?: null,
                'recovery_observations' => $recovery_observations ?: null,
            ];
            foreach ($extra as $col => $val) {
                if (ml_has_column($pdo, $col)) {
                    $cols[] = $col;
                    $vals[] = $val;
                }
            }
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $ins = $pdo->prepare('INSERT INTO monitoring_logs (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')');
            $ins->execute($vals);

            if ($patient_status === 'critical') {
                $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_technician', 'monitoring_critical')")->execute([$plan_id]);
            }

            $pdo->commit();
            $success = 'Monitoring entry saved.';
            header('Location: monitor_patient.php?plan_id=' . $plan_id . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not save monitoring log. Ensure database migration has been applied.';
        }
    }
}

if (!empty($_GET['saved'])) {
    $success = 'Monitoring entry saved.';
}

$current_page = 'monitor_patient';
require_once '../../includes/header.php';
?>

<div class="action-bar hide-on-print">
  <div>
    <h1 class="page-heading">Recovery monitoring — Plan #<?= (int)$plan_id ?></h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Monitoring</p>
  </div>
  <div>
    <a href="monitoring_queue.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Queue</a>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($criticalActive): ?>
<div class="alert alert-error mb-4" style="border-left:4px solid #c0392b;">
  <strong><i class='bx bx-error'></i> Critical patient status.</strong>
  Continue close observation. Discharge is blocked until the Vet Technician sees improvement. The Vet Technician has been notified.
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header"><h2><i class='bx bx-user'></i> Patient</h2></div>
  <div class="grid grid-2">
    <div class="info-row"><span class="info-label">Pet</span><span class="info-value"><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></span></div>
    <div class="info-row"><span class="info-label">Owner</span><span class="info-value"><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></span></div>
    <div class="info-row"><span class="info-label">Workflow</span><span class="info-value"><span class="badge badge-info">Monitoring</span></span></div>
  </div>
</div>

<div class="grid grid-2 mb-4">
  <div class="card">
    <div class="card-header"><h2><i class='bx bx-capsule'></i> Medications administered</h2></div>
    <?php if (empty($admin_logs)): ?>
      <p class="text-muted">No administration logs yet.</p>
    <?php else: ?>
      <?php foreach ($admin_logs as $log):
          $mn = (string) ($log['medicine_name'] ?? '');
          $isProc = strpos($mn, 'Procedure:') === 0;
          $isSurg = strpos($mn, 'Surgery:') === 0;
      ?>
        <div style="border:1px solid var(--color-border); border-radius:8px; padding:12px; margin-bottom:10px; background:#fafafa;">
          <strong><?= htmlspecialchars($mn ?: '—') ?></strong>
          <?php if ($isProc): ?><span class="badge badge-outline">Procedure</span><?php endif; ?>
          <?php if ($isSurg): ?><span class="badge badge-outline">Surgery</span><?php endif; ?>
          <span class="text-muted" style="font-size:12px;"> — <?= htmlspecialchars($log['staff_name'] ?: '') ?> · <?= htmlspecialchars((string) ($log['administered_at'] ?? '')) ?></span>
          <?php if (!empty($log['dosage_given'])): ?><div class="info-row mt-1"><span class="info-label">Dosage given</span><span class="info-value"><?= htmlspecialchars($log['dosage_given']) ?></span></div><?php endif; ?>
          <?php if (!empty($log['notes'])): ?><div class="info-row mt-1"><span class="info-label">Notes</span><span class="info-value"><?= htmlspecialchars($log['notes']) ?></span></div><?php endif; ?>
          <?php if (!empty($log['patient_response'])): ?><div class="info-row mt-1"><span class="info-label">Response</span><span class="info-value"><?= htmlspecialchars($log['patient_response']) ?></span></div><?php endif; ?>
          <?php if (!empty($log['reaction'])): ?><div class="info-row mt-1"><span class="info-label">Reaction</span><span class="info-value"><?= htmlspecialchars($log['reaction']) ?></span></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class='bx bx-pulse'></i> Planned surgeries & procedures</h2></div>
    <?php if (empty($surgeries) && empty($procedures)): ?>
      <p class="text-muted">No surgeries or procedures on file.</p>
    <?php else: ?>
      <?php foreach ($surgeries as $s): ?>
        <div class="info-row"><span class="info-label">Surgery</span><span class="info-value"><?= htmlspecialchars($s['name'] ?? '') ?></span></div>
      <?php endforeach; ?>
      <?php foreach ($procedures as $p): ?>
        <div class="info-row"><span class="info-label">Procedure</span><span class="info-value"><?= htmlspecialchars($p['name'] ?? '') ?></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h2><i class='bx bx-line-chart'></i> Monitoring history</h2></div>
  <?php if (empty($mon_logs)): ?>
    <p class="text-muted">No monitoring entries yet. Record the first observation below.</p>
  <?php else: ?>
    <?php foreach ($mon_logs as $m): ?>
      <div style="border:1px solid var(--color-border); border-radius:8px; padding:12px; margin-bottom:10px;">
        <div class="flex justify-between items-center mb-2">
          <span class="badge <?= $m['patient_status'] === 'critical' ? 'badge-danger' : ($m['patient_status'] === 'stable' ? 'badge-success' : 'badge-warning') ?>">
            <?= htmlspecialchars(str_replace('_', ' ', $m['patient_status'])) ?>
          </span>
          <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($m['created_at']) ?> · <?= htmlspecialchars($m['staff_name'] ?: '') ?></span>
        </div>
        <?php if ($m['temperature'] !== null): ?><div class="info-row"><span class="info-label">Temp °C</span><span class="info-value"><?= htmlspecialchars((string)$m['temperature']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['appetite'])): ?><div class="info-row"><span class="info-label">Appetite</span><span class="info-value"><?= htmlspecialchars($m['appetite']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['energy_level'])): ?><div class="info-row"><span class="info-label">Energy</span><span class="info-value"><?= htmlspecialchars($m['energy_level']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['observation'])): ?><div class="info-row mt-2"><span class="info-label">Observation</span><span class="info-value"><?= nl2br(htmlspecialchars($m['observation'])) ?></span></div><?php endif; ?>
        <?php if (!empty($m['complications'])): ?><div class="info-row mt-2"><span class="info-label">Complications</span><span class="info-value"><?= nl2br(htmlspecialchars($m['complications'])) ?></span></div><?php endif; ?>
        <?php if (!empty($m['notes'])): ?><div class="info-row mt-2"><span class="info-label">Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($m['notes'])) ?></span></div><?php endif; ?>
        <?php if (!empty($m['wound_condition'])): ?><div class="info-row"><span class="info-label">Wound</span><span class="info-value"><?= htmlspecialchars($m['wound_condition']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['bleeding'])): ?><div class="info-row"><span class="info-label">Bleeding</span><span class="info-value"><?= htmlspecialchars($m['bleeding']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['pain_indicators'])): ?><div class="info-row"><span class="info-label">Pain</span><span class="info-value"><?= htmlspecialchars($m['pain_indicators']) ?></span></div><?php endif; ?>
        <?php if (!empty($m['medication_response'])): ?><div class="info-row mt-2"><span class="info-label">Medication response</span><span class="info-value"><?= nl2br(htmlspecialchars($m['medication_response'])) ?></span></div><?php endif; ?>
        <?php if (!empty($m['recovery_observations'])): ?><div class="info-row mt-2"><span class="info-label">Recovery</span><span class="info-value"><?= nl2br(htmlspecialchars($m['recovery_observations'])) ?></span></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2><i class='bx bx-plus-medical'></i> New monitoring entry</h2></div>
  <form method="POST">
    <div class="grid grid-2">
      <div class="form-group">
        <label>Patient status *</label>
        <select name="patient_status" class="form-control" required>
          <option value="stable">Stable</option>
          <option value="recovering">Recovering</option>
          <option value="under_observation" selected>Under observation</option>
          <option value="critical">Critical</option>
        </select>
      </div>
      <div class="form-group">
        <label>Temperature (°C)</label>
        <input type="number" step="0.1" name="temperature" placeholder="e.g. 38.5">
      </div>
      <div class="form-group">
        <label>Appetite</label>
        <input type="text" name="appetite" placeholder="Normal / reduced / none">
      </div>
      <div class="form-group">
        <label>Energy level</label>
        <input type="text" name="energy_level" placeholder="Alert / lethargic">
      </div>
      <?php if (ml_has_column($pdo, 'wound_condition')): ?>
      <div class="form-group">
        <label>Wound condition</label>
        <input type="text" name="wound_condition" class="form-control" placeholder="Clean / redness / drainage">
      </div>
      <?php endif; ?>
      <?php if (ml_has_column($pdo, 'bleeding')): ?>
      <div class="form-group">
        <label>Bleeding</label>
        <input type="text" name="bleeding" class="form-control" placeholder="None / location / amount">
      </div>
      <?php endif; ?>
      <?php if (ml_has_column($pdo, 'pain_indicators')): ?>
      <div class="form-group">
        <label>Pain indicators</label>
        <input type="text" name="pain_indicators" class="form-control" placeholder="Vocalizing, posture, guarding">
      </div>
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label>Clinical observation</label>
      <textarea name="observation" rows="3" placeholder="Recovery observations, wound condition, bleeding, behavior…"></textarea>
    </div>
    <?php if (ml_has_column($pdo, 'medication_response')): ?>
    <div class="form-group">
      <label>Medication response</label>
      <textarea name="medication_response" rows="2" class="form-control" placeholder="Response to meds given in recovery"></textarea>
    </div>
    <?php endif; ?>
    <?php if (ml_has_column($pdo, 'recovery_observations')): ?>
    <div class="form-group">
      <label>Recovery observations</label>
      <textarea name="recovery_observations" rows="2" class="form-control"></textarea>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label>Complications</label>
      <textarea name="complications" rows="2" placeholder="If any complications, describe here."></textarea>
    </div>
    <div class="form-group">
      <label>Additional notes</label>
      <textarea name="notes" rows="2"></textarea>
    </div>
    <div class="alert alert-info" style="font-size:13px;">
      <strong>Critical status</strong> notifies the Vet Technician immediately. Discharge requires Vet Technician approval when the patient is <strong>stable</strong> or <strong>recovering</strong>.
    </div>
    <button type="submit" name="add_monitoring" class="btn btn-primary"><i class='bx bx-save'></i> Save monitoring entry</button>
  </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
