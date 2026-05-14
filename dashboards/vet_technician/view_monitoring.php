<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/monitoring_logs_schema.php';
requireRole('vet_technician');
require_permission('view_dashboard');

petmate_ensure_monitoring_logs_table($pdo);

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

if (!$plan_id) {
    header('Location: approve_discharge.php');
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

$data = json_decode($plan['prescriptions'], true) ?: [];
$medicines = $data['medicines'] ?? [];
$surgeries = $data['surgeries'] ?? [];
$procedures = $data['procedures'] ?? [];

// Fetch administration logs
$admin_logs = $pdo->prepare("
    SELECT al.*, u.name AS staff_name
    FROM administration_logs al
    LEFT JOIN users u ON u.id = al.vet_assistant_id
    WHERE al.plan_id = ?
    ORDER BY al.administered_at DESC, al.id DESC
");
$admin_logs->execute([$plan_id]);
$admin_logs = $admin_logs->fetchAll();

// Fetch monitoring logs
$mon_logs = $pdo->prepare("
    SELECT ml.*, u.name AS staff_name
    FROM monitoring_logs ml
    LEFT JOIN users u ON u.id = ml.vet_assistant_id
    WHERE ml.plan_id = ?
    ORDER BY ml.created_at DESC, ml.id DESC
");
$mon_logs->execute([$plan_id]);
$mon_logs = $mon_logs->fetchAll();

// Assigned assistant
$assigned_name = 'Unassigned';
if (!empty($plan['assigned_assistant_id'])) {
    $aStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $aStmt->execute([$plan['assigned_assistant_id']]);
    $aRow = $aStmt->fetch();
    if ($aRow) {
        $assigned_name = $aRow['name'];
    }
}

$latestMon = $mon_logs[0] ?? null;

$current_page = 'approve_discharge';
require_once '../../includes/header.php';
?>

<div class="action-bar hide-on-print">
  <div>
    <h1 class="page-heading">Monitoring Report — Plan #<?= (int)$plan_id ?></h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Discharge Review <span>›</span> Monitoring Report</p>
  </div>
  <div style="display:flex; gap:8px;">
    <button type="button" class="btn btn-outline" onclick="window.print()"><i class='bx bx-printer'></i> Print</button>
    <a href="approve_discharge.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
  </div>
</div>

<!-- Patient Overview -->
<div class="card mb-4 printable-area">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <h2><i class='bx bx-user'></i> Patient Overview</h2>
    <?php
      $wfClass = 'badge badge-outline';
      $wf = $plan['workflow_status'] ?? '';
      switch($wf) {
        case 'monitoring': $wfClass = 'badge badge-warning'; break;
        case 'discharge_ready': $wfClass = 'badge badge-success'; break;
        case 'ongoing_treatment': $wfClass = 'badge badge-primary'; break;
      }
    ?>
    <span class="<?= $wfClass ?>"><?= ucfirst(str_replace('_', ' ', $wf)) ?></span>
  </div>
  <div class="grid grid-2" style="margin-bottom: 16px;">
    <div>
      <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></span></div>
      <div class="info-row"><span class="info-label">Species / Breed</span><span class="info-value"><?= htmlspecialchars(($plan['species'] ?: '-') . ' / ' . ($plan['breed'] ?: '-')) ?></span></div>
    </div>
    <div>
      <div class="info-row"><span class="info-label">Owner</span><span class="info-value"><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></span></div>
      <div class="info-row"><span class="info-label">Assigned Assistant</span><span class="info-value"><?= htmlspecialchars($assigned_name) ?></span></div>
      <div class="info-row"><span class="info-label">Plan Date</span><span class="info-value"><?= htmlspecialchars(date('M d, Y H:i', strtotime($plan['date']))) ?></span></div>
    </div>
  </div>

  <?php if ($latestMon): ?>
  <div style="border:2px solid <?= $latestMon['patient_status'] === 'critical' ? '#e74c3c' : ($latestMon['patient_status'] === 'stable' ? '#2ecc71' : '#f39c12') ?>; border-radius:8px; padding:16px; margin-bottom:16px; background:<?= $latestMon['patient_status'] === 'critical' ? '#fff5f5' : ($latestMon['patient_status'] === 'stable' ? '#f0fff4' : '#fffbeb') ?>;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <strong style="font-size:16px;">Current Status:</strong>
        <span class="badge <?= $latestMon['patient_status'] === 'critical' ? 'badge-danger' : ($latestMon['patient_status'] === 'stable' ? 'badge-success' : 'badge-warning') ?>" style="font-size:14px; padding:6px 12px;">
          <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $latestMon['patient_status']))) ?>
        </span>
      </div>
      <span class="text-muted" style="font-size:13px;">Last updated: <?= htmlspecialchars($latestMon['created_at']) ?> by <?= htmlspecialchars($latestMon['staff_name'] ?: '—') ?></span>
    </div>
  </div>
  <?php else: ?>
  <div class="alert alert-warning"><i class='bx bx-info-circle'></i> No monitoring entries have been recorded yet.</div>
  <?php endif; ?>
</div>

<!-- Monitoring History (Main Content) -->
<div class="card mb-4 printable-area">
  <div class="card-header">
    <h2><i class='bx bx-line-chart'></i> Monitoring History (<?= count($mon_logs) ?> entries)</h2>
  </div>
  <?php if (empty($mon_logs)): ?>
    <div class="empty-state"><p>No monitoring entries recorded for this plan.</p></div>
  <?php else: ?>
    <div style="position:relative; padding-left:24px;">
    <?php foreach ($mon_logs as $idx => $m): ?>
      <div style="position:relative; border-left:3px solid <?= $m['patient_status'] === 'critical' ? '#e74c3c' : ($m['patient_status'] === 'stable' ? '#2ecc71' : '#f39c12') ?>; padding:16px 16px 16px 20px; margin-bottom:<?= $idx < count($mon_logs) - 1 ? '0' : '0' ?>px; background:<?= $idx === 0 ? '#fafbff' : '#fff' ?>; border-radius:0 8px 8px 0;">
        <!-- Timeline dot -->
        <div style="position:absolute; left:-9px; top:20px; width:15px; height:15px; border-radius:50%; background:<?= $m['patient_status'] === 'critical' ? '#e74c3c' : ($m['patient_status'] === 'stable' ? '#2ecc71' : '#f39c12') ?>; border:3px solid #fff; box-shadow:0 0 0 2px <?= $m['patient_status'] === 'critical' ? '#e74c3c33' : ($m['patient_status'] === 'stable' ? '#2ecc7133' : '#f39c1233') ?>;"></div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <div>
            <span class="badge <?= $m['patient_status'] === 'critical' ? 'badge-danger' : ($m['patient_status'] === 'stable' ? 'badge-success' : 'badge-warning') ?>">
              <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $m['patient_status']))) ?>
            </span>
            <?php if ($idx === 0): ?><span class="badge badge-outline" style="margin-left:4px;">Latest</span><?php endif; ?>
          </div>
          <span class="text-muted" style="font-size:12px;">
            <i class='bx bx-time-five'></i> <?= htmlspecialchars($m['created_at']) ?> · <?= htmlspecialchars($m['staff_name'] ?: '—') ?>
          </span>
        </div>

        <div class="grid grid-3" style="gap:8px; margin-bottom:8px;">
          <?php if ($m['temperature'] !== null): ?>
          <div class="info-row"><span class="info-label">Temperature</span><span class="info-value"><?= htmlspecialchars((string)$m['temperature']) ?> °C</span></div>
          <?php endif; ?>
          <?php if (!empty($m['appetite'])): ?>
          <div class="info-row"><span class="info-label">Appetite</span><span class="info-value"><?= htmlspecialchars($m['appetite']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($m['energy_level'])): ?>
          <div class="info-row"><span class="info-label">Energy Level</span><span class="info-value"><?= htmlspecialchars($m['energy_level']) ?></span></div>
          <?php endif; ?>
        </div>

        <?php if (!empty($m['observation'])): ?>
        <div class="info-row mt-2"><span class="info-label">Observation</span><span class="info-value"><?= nl2br(htmlspecialchars($m['observation'])) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['wound_condition'])): ?>
        <div class="info-row mt-1"><span class="info-label">Wound Condition</span><span class="info-value"><?= htmlspecialchars($m['wound_condition']) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['bleeding'])): ?>
        <div class="info-row mt-1"><span class="info-label">Bleeding</span><span class="info-value"><?= htmlspecialchars($m['bleeding']) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['pain_indicators'])): ?>
        <div class="info-row mt-1"><span class="info-label">Pain Indicators</span><span class="info-value"><?= htmlspecialchars($m['pain_indicators']) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['medication_response'])): ?>
        <div class="info-row mt-2"><span class="info-label">Medication Response</span><span class="info-value"><?= nl2br(htmlspecialchars($m['medication_response'])) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['recovery_observations'])): ?>
        <div class="info-row mt-2"><span class="info-label">Recovery Observations</span><span class="info-value"><?= nl2br(htmlspecialchars($m['recovery_observations'])) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['complications'])): ?>
        <div class="info-row mt-2" style="color:#c0392b;"><span class="info-label" style="color:#c0392b;">⚠ Complications</span><span class="info-value"><?= nl2br(htmlspecialchars($m['complications'])) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($m['notes'])): ?>
        <div class="info-row mt-2"><span class="info-label">Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($m['notes'])) ?></span></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Administration Logs -->
<div class="card mb-4 printable-area">
  <div class="card-header">
    <h2><i class='bx bx-capsule'></i> Administration Logs (<?= count($admin_logs) ?> records)</h2>
  </div>
  <?php if (empty($admin_logs)): ?>
    <p class="text-muted">No administration records found.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Type</th>
            <th>Staff</th>
            <th>Date</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admin_logs as $log):
            $mn = (string)($log['medicine_name'] ?? '');
            $isProc = strpos($mn, 'Procedure:') === 0;
            $isSurg = strpos($mn, 'Surgery:') === 0;
            $isMed = !$isProc && !$isSurg;
            if ($isProc) {
                $typeBadge = '<span class="badge badge-outline">Procedure</span>';
            } elseif ($isSurg) {
                $typeBadge = '<span class="badge badge-outline">Surgery</span>';
            } else {
                $typeBadge = '<span class="badge badge-primary">Medication</span>';
            }
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($mn ?: '—') ?></strong></td>
            <td><?= $typeBadge ?></td>
            <td><?= htmlspecialchars($log['staff_name'] ?: '—') ?></td>
            <td style="white-space:nowrap;"><?= htmlspecialchars((string)($log['administered_at'] ?? '—')) ?></td>
            <td>
              <?php if (!empty($log['dosage_given'])): ?>
                <div><strong>Dosage:</strong> <?= htmlspecialchars($log['dosage_given']) ?></div>
              <?php endif; ?>
              <?php if (!empty($log['notes'])): ?>
                <div><?= nl2br(htmlspecialchars($log['notes'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($log['patient_response'])): ?>
                <div><strong>Response:</strong> <?= htmlspecialchars($log['patient_response']) ?></div>
              <?php endif; ?>
              <?php if (!empty($log['reaction'])): ?>
                <div style="color:#c0392b;"><strong>Reaction:</strong> <?= htmlspecialchars($log['reaction']) ?></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<style>
  @media print {
      body * { visibility: hidden; }
      .printable-area, .printable-area * { visibility: visible; }
      .printable-area { position: relative; border: none; box-shadow: none; }
      .hide-on-print { display: none !important; }
      .sidebar, .topbar { display: none !important; }
      .main-content { margin-left: 0 !important; padding: 0 !important; }
  }
</style>

<?php require_once '../../includes/footer.php'; ?>
