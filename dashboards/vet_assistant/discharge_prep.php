<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/monitoring_logs_schema.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

petmate_ensure_monitoring_logs_table($pdo);

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!$plan_id) {
    header('Location: index.php');
    exit;
}

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

$wf = strtolower(trim((string)($plan['workflow_status'] ?? '')));
$notReady = ($wf !== 'discharge_ready');

if ($notReady) {
    $current_page = 'discharge_prep.php';
    require_once '../../includes/header.php';

    $stepMessages = [
        'forwarded'          => ['Awaiting administration', 'The treatment plan has been forwarded but medications/procedures have not been administered yet. <a href="administer.php?plan_id=' . $plan_id . '" class="btn btn-sm btn-primary" style="margin-left:8px;"><i class="bx bx-injection"></i> Go to Administration</a>'],
        'ongoing_treatment'  => ['Treatment in progress', 'Treatment is currently being administered. Complete all administration tasks first, then the patient will move to recovery monitoring. <a href="administer.php?plan_id=' . $plan_id . '" class="btn btn-sm btn-primary" style="margin-left:8px;"><i class="bx bx-injection"></i> Continue Administration</a>'],
        'monitoring'         => ['In recovery monitoring', 'The patient is being monitored. The <strong>Vet Technician</strong> must review the monitoring documentation and approve discharge before this step can be completed. <a href="monitor_patient.php?plan_id=' . $plan_id . '" class="btn btn-sm btn-warning" style="margin-left:8px;"><i class="bx bx-pulse"></i> View Monitoring</a>'],
        'pending_billing'    => ['Already discharged', 'This plan has already been discharged and sent to billing.'],
        'awaiting_payment'   => ['Awaiting payment', 'This plan is awaiting owner payment.'],
        'completed'          => ['Completed', 'This treatment has been fully completed.'],
        'paid'               => ['Completed', 'This treatment has been fully completed and paid.'],
    ];
    $info = $stepMessages[$wf] ?? ['Not ready', 'Current status: <strong>' . htmlspecialchars($wf ?: '(empty)') . '</strong>. The plan must reach <em>discharge_ready</em> before a discharge summary can be prepared.'];
    ?>
    <div class="action-bar">
      <div>
        <h1 class="page-heading">Discharge Preparation</h1>
        <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Discharge Prep</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2><i class='bx bx-box'></i> Plan #<?= (int)$plan_id ?> — <?= htmlspecialchars($plan['pet_name']) ?></h2>
        <span class="badge badge-warning"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $wf ?: 'unknown'))) ?></span>
      </div>

      <div class="alert alert-warning mb-4" style="border-left:4px solid #f39c12;">
        <strong><i class='bx bx-info-circle'></i> <?= $info[0] ?></strong><br>
        <?= $info[1] ?>
      </div>

      <h3 style="font-size:14px; color:var(--color-muted); margin-bottom:12px;">Workflow Progress</h3>
      <div style="display:flex; gap:4px; flex-wrap:wrap; margin-bottom:16px;">
        <?php
          $stages = ['forwarded','ongoing_treatment','monitoring','discharge_ready','pending_billing'];
          $stageLabels = ['Forwarded','Administering','Monitoring','Discharge Ready','Billing'];
          $currentIdx = array_search($wf, $stages);
          foreach ($stages as $si => $sv):
            $done = ($currentIdx !== false && $si < $currentIdx);
            $active = ($sv === $wf);
            $bg = $done ? 'var(--color-success, #2ecc71)' : ($active ? 'var(--color-primary, #6366f1)' : '#e0e0e0');
            $fg = ($done || $active) ? '#fff' : '#888';
        ?>
        <span style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; background:<?= $bg ?>; color:<?= $fg ?>;">
          <?php if ($done): ?><i class='bx bx-check'></i> <?php endif; ?>
          <?= $stageLabels[$si] ?>
        </span>
        <?php if ($si < count($stages) - 1): ?><span style="color:#ccc; line-height:32px;">→</span><?php endif; ?>
        <?php endforeach; ?>
      </div>

      <a href="index.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
    </div>
    <?php
    require_once '../../includes/footer.php';
    exit;
}

$data = json_decode($plan['prescriptions'], true) ?: [];
$planMedicines = $data['medicines'] ?? [];
$planSurgeries = $data['surgeries'] ?? [];
$planProcedures = $data['procedures'] ?? [];

$adminLogsStmt = $pdo->prepare("
    SELECT al.*, u.name AS staff_name
    FROM administration_logs al
    LEFT JOIN users u ON u.id = al.vet_assistant_id
    WHERE al.plan_id = ?
    ORDER BY al.administered_at DESC, al.id DESC
");
$adminLogsStmt->execute([$plan_id]);
$admin_logs = $adminLogsStmt->fetchAll();

$monFull = [];
try {
    $mf = $pdo->prepare("SELECT ml.*, u.name AS staff_name FROM monitoring_logs ml LEFT JOIN users u ON u.id = ml.vet_assistant_id WHERE ml.plan_id = ? ORDER BY ml.created_at DESC, ml.id DESC");
    $mf->execute([$plan_id]);
    $monFull = $mf->fetchAll();
} catch (Throwable $e) {
    $monFull = [];
}

$pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE plan_id = ? AND role = 'vet_assistant' AND type = 'discharge_summary_needed'")->execute([$plan_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $discharge_notes = trim($_POST['discharge_notes'] ?? '');
    $home_care = trim($_POST['home_care'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '');
    $warnings = trim($_POST['warnings'] ?? '');

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare("INSERT INTO discharge_summaries (plan_id, vet_assistant_id, discharge_notes, home_care, follow_up_date, warnings) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$insert->execute([$plan_id, $user_id, $discharge_notes, $home_care, $follow_up_date ?: null, $warnings])) {
            throw new RuntimeException('Failed to save discharge summary.');
        }

        $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'pending_billing' WHERE id = ?")->execute([$plan_id]);

        // Match the active visit for this pet so CSR billing can join pet_records + treatment_plans.
        $updVisit = $pdo->prepare("
            UPDATE pet_records
            SET status = 'pending_billing'
            WHERE pet_id = ? AND status IN ('pending', 'validated', 'assessed', 'completed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $updVisit->execute([$plan['pet_id']]);
        if ($updVisit->rowCount() === 0) {
            $updVisit2 = $pdo->prepare("
                UPDATE pet_records
                SET status = 'pending_billing'
                WHERE pet_id = ? AND status NOT IN ('pending_billing', 'awaiting_payment')
                ORDER BY id DESC
                LIMIT 1
            ");
            $updVisit2->execute([$plan['pet_id']]);
        }

        $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'csr', 'discharge_pending_billing')")->execute([$plan_id]);

        $stmt_er = $pdo->prepare("SELECT id, room_id FROM examination_rooms WHERE pet_id = ? AND status IN ('ready', 'in_use') ORDER BY id DESC LIMIT 1");
        $stmt_er->execute([$plan['pet_id']]);
        $er_record = $stmt_er->fetch();
        if ($er_record) {
            $pdo->prepare("UPDATE examination_rooms SET status = 'completed' WHERE id = ?")->execute([$er_record['id']]);
            $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$er_record['room_id']]);
        }

        $pdo->commit();
        $success = "Discharge summary saved. The record has been forwarded to the CSR for billing.";
        $plan['workflow_status'] = 'pending_billing';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to save discharge summary.";
    }
}

$current_page = 'discharge_prep.php';
require_once '../../includes/header.php';
?>

<div class="action-bar hide-on-print">
  <div>
    <h1 class="page-heading">Discharge Preparation</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Discharge Prep</p>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success">
      <i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?>
      <br><br>
      <a href="index.php" class="btn btn-primary btn-sm">Return to Dashboard</a>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($plan['workflow_status'] === 'discharge_ready'): ?>
<div class="card mb-4">
  <div class="card-header"><h2><i class='bx bx-file'></i> Treatment summary</h2></div>
  <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($plan['description'])) ?></p>
  <?php if (!empty($planMedicines)): ?>
    <h4 class="mt-3" style="font-size:14px;">Medications on plan</h4>
    <ul class="text-muted" style="font-size:14px;">
      <?php foreach ($planMedicines as $pm): ?>
        <li><strong><?= htmlspecialchars($pm['medicine_name'] ?? '') ?></strong> — <?= htmlspecialchars($pm['dosage'] ?? '') ?> · <?= htmlspecialchars($pm['frequency'] ?? '') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <div class="card-header"><h2><i class='bx bx-capsule'></i> Completed administration</h2></div>
  <?php if (empty($admin_logs)): ?>
    <p class="text-muted">No administration logs.</p>
  <?php else: ?>
    <?php foreach ($admin_logs as $log):
        $mn = (string) ($log['medicine_name'] ?? '');
        $tag = '';
        if (strpos($mn, 'Procedure:') === 0) {
            $tag = 'Procedure';
        } elseif (strpos($mn, 'Surgery:') === 0) {
            $tag = 'Surgery';
        } else {
            $tag = 'Medication';
        }
    ?>
      <div style="border:1px solid var(--color-border); border-radius:8px; padding:10px; margin-bottom:8px; font-size:13px;">
        <span class="badge badge-outline"><?= htmlspecialchars($tag) ?></span>
        <strong><?= htmlspecialchars($mn) ?></strong>
        <span class="text-muted"> — <?= htmlspecialchars((string) ($log['staff_name'] ?? '')) ?> · <?= htmlspecialchars((string) ($log['administered_at'] ?? '')) ?></span>
        <?php if (!empty($log['dosage_given'])): ?><div>Dosage given: <?= htmlspecialchars($log['dosage_given']) ?></div><?php endif; ?>
        <?php if (!empty($log['notes'])): ?><div><?= nl2br(htmlspecialchars($log['notes'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card mb-4">
  <div class="card-header"><h2><i class='bx bx-line-chart'></i> Monitoring history (full)</h2></div>
  <?php if (empty($monFull)): ?>
    <p class="text-muted">No monitoring entries on file.</p>
  <?php else: ?>
    <?php foreach ($monFull as $m): ?>
      <div style="border:1px solid var(--color-border); border-radius:8px; padding:10px; margin-bottom:8px; font-size:13px;">
        <strong><?= htmlspecialchars(str_replace('_', ' ', (string) ($m['patient_status'] ?? ''))) ?></strong>
        <span class="text-muted"> — <?= htmlspecialchars((string) ($m['created_at'] ?? '')) ?> · <?= htmlspecialchars((string) ($m['staff_name'] ?? '')) ?></span>
        <?php if (!empty($m['temperature'])): ?><div>Temp: <?= htmlspecialchars((string) $m['temperature']) ?> °C</div><?php endif; ?>
        <?php if (!empty($m['observation'])): ?><div><?= nl2br(htmlspecialchars($m['observation'])) ?></div><?php endif; ?>
        <?php if (!empty($m['complications'])): ?><div class="text-danger"><strong>Complications:</strong> <?= nl2br(htmlspecialchars($m['complications'])) ?></div><?php endif; ?>
        <?php if (!empty($m['recovery_observations'])): ?><div><strong>Recovery:</strong> <?= nl2br(htmlspecialchars($m['recovery_observations'])) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
      <h2><i class='bx bx-box'></i> Prepare Discharge Summary</h2>
  </div>
  
  <div class="grid grid-2" style="margin-bottom: 24px; background: #fafafa; padding: 16px; border-radius: 8px; border: 1px solid var(--color-border);">
    <div>
        <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></span></div>
        <div class="info-row"><span class="info-label">Owner Name</span><span class="info-value"><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></span></div>
    </div>
    <div>
        <div class="info-row"><span class="info-label">Plan ID</span><span class="info-value">#<?= $plan_id ?></span></div>
    </div>
  </div>

  <form method="POST">
      <div class="form-group">
          <label>General Discharge Notes *</label>
          <textarea name="discharge_notes" rows="3" required placeholder="Summarize the treatment performed and the pet's current condition..."></textarea>
      </div>
      
      <div class="form-group">
          <label>Home Care Instructions *</label>
          <textarea name="home_care" rows="3" required placeholder="Instructions for the owner (e.g. keep indoors, diet restrictions, wound care)..."></textarea>
      </div>
      
      <div class="grid grid-2">
          <div class="form-group">
              <label>Follow-Up Date</label>
              <input type="date" name="follow_up_date">
          </div>
          <div class="form-group">
              <label>Warnings / Signs to Watch For</label>
              <input type="text" name="warnings" placeholder="e.g. Vomiting, lethargy, bleeding">
          </div>
      </div>
      
      <div class="action-bar mt-4">
          <a href="index.php" class="btn btn-outline">Cancel</a>
          <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to finalize this discharge? The owner will be notified to pick up their pet.');">
              <i class='bx bx-check-shield'></i> Finalize Discharge
          </button>
      </div>
  </form>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>