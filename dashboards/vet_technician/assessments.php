<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
$print = isset($_GET['print']) && $_GET['print'] === '1';

function format_test_type($type) {
    $map = [
        'cbc' => 'CBC',
        'chemistry' => 'Blood Chemistry',
        'microscopy' => 'Microscopy',
        'test_kit' => 'Rapid Test Kit'
    ];
    return $map[$type] ?? strtoupper((string)$type);
}

function flatten_result_data($decoded) {
    $rows = [];
    if (!is_array($decoded)) {
        return $rows;
    }
    foreach ($decoded as $key => $value) {
        if ($key === 'kits' && is_array($value)) {
            foreach ($value as $idx => $kit) {
                $rows[] = [
                    'label' => 'Kit Row ' . ($idx + 1),
                    'value' => sprintf(
                        '%s - %s%s',
                        $kit['kit_type'] ?? 'N/A',
                        $kit['result'] ?? 'N/A',
                        !empty($kit['notes']) ? (' (' . $kit['notes'] . ')') : ''
                    )
                ];
            }
            continue;
        }
        $rows[] = [
            'label' => ucwords(str_replace('_', ' ', (string)$key)),
            'value' => is_scalar($value) ? (string)$value : json_encode($value)
        ];
    }
    return $rows;
}

$listSql = "
    SELECT a.id, a.assessment_session_id, a.test_type, a.result, a.status, a.updated_at, a.date,
           p.name AS pet_name,
           s.technician_id,
           u.name AS technician_name,
           rm.room_name
    FROM assessments a
    JOIN pets p ON p.id = a.pet_id
    LEFT JOIN assessment_sessions s ON s.id = a.assessment_session_id
    LEFT JOIN users u ON u.id = s.technician_id
    LEFT JOIN examination_rooms er ON er.id = s.room_id
    LEFT JOIN rooms rm ON rm.id = er.room_id
    WHERE a.status = 'completed'
";
$params = [];
if ($session_id > 0) {
    $listSql .= " AND a.assessment_session_id = ? ";
    $params[] = $session_id;
}
$listSql .= " ORDER BY COALESCE(a.updated_at, a.date) DESC, a.id DESC";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$assessment_rows = $listStmt->fetchAll();

$selected = null;
$selected_rows = [];
if ($view_id > 0) {
    $viewStmt = $pdo->prepare("
        SELECT a.*, p.name AS pet_name, rm.room_name, s.technician_id, u.name AS technician_name
        FROM assessments a
        JOIN pets p ON p.id = a.pet_id
        LEFT JOIN assessment_sessions s ON s.id = a.assessment_session_id
        LEFT JOIN users u ON u.id = s.technician_id
        LEFT JOIN examination_rooms er ON er.id = s.room_id
        LEFT JOIN rooms rm ON rm.id = er.room_id
        WHERE a.id = ? AND a.status = 'completed'
        LIMIT 1
    ");
    $viewStmt->execute([$view_id]);
    $selected = $viewStmt->fetch();
    if ($selected) {
        $decoded = json_decode($selected['result_data'] ?? '', true);
        $selected_rows = flatten_result_data($decoded);
    }
}

if ($print) {
    if (!$selected) {
        die('Assessment result not found.');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assessment Print - #<?= (int)$selected['id'] ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
    h1 { margin: 0 0 10px; font-size: 20px; }
    .meta { margin-bottom: 16px; font-size: 13px; }
    .meta div { margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
    th { background: #f5f5f5; }
    .summary { margin-top: 12px; font-size: 12px; }
  </style>
</head>
<body>
  <h1>PetMate Assessment Result</h1>
  <div class="meta">
    <div><strong>Assessment ID:</strong> <?= (int)$selected['id'] ?></div>
    <div><strong>Session ID:</strong> <?= htmlspecialchars((string)($selected['assessment_session_id'] ?? '-')) ?></div>
    <div><strong>Patient:</strong> <?= htmlspecialchars($selected['pet_name']) ?></div>
    <div><strong>Room:</strong> <?= htmlspecialchars($selected['room_name'] ?: 'N/A') ?></div>
    <div><strong>Technician:</strong> <?= htmlspecialchars($selected['technician_name'] ?: 'N/A') ?></div>
    <div><strong>Test Type:</strong> <?= htmlspecialchars(format_test_type($selected['test_type'])) ?></div>
  </div>
  <table>
    <thead><tr><th>Field</th><th>Value</th></tr></thead>
    <tbody>
    <?php if (empty($selected_rows)): ?>
      <tr><td colspan="2">No structured result data.</td></tr>
    <?php else: foreach ($selected_rows as $row): ?>
      <tr><td><?= htmlspecialchars($row['label']) ?></td><td><?= htmlspecialchars($row['value']) ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <div class="summary"><strong>Short Summary:</strong> <?= htmlspecialchars($selected['result'] ?: '-') ?></div>
  <script>window.print();</script>
</body>
</html>
<?php
    exit;
}

$current_page = 'assessments';
require_once '../../includes/header.php';
?>
<div class="action-bar">
  <div>
    <h1 class="page-heading">Assessments</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Assessments</p>
  </div>
</div>

<?php if ($session_id > 0): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> Results were saved successfully and are now reviewable below.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-list-check'></i> Completed Assessments</h2>
    <span class="text-muted"><?= count($assessment_rows) ?> result(s)</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Date</th><th>Patient</th><th>Test Type</th><th>Summary</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if (empty($assessment_rows)): ?>
        <tr><td colspan="6"><div class="empty-state"><i class='bx bx-inbox'></i><p>No completed assessment results yet.</p></div></td></tr>
      <?php else: foreach ($assessment_rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($row['updated_at'] ?: $row['date']))) ?></td>
          <td><strong><?= htmlspecialchars($row['pet_name']) ?></strong><br><small style="color:var(--color-muted)">Room: <?= htmlspecialchars($row['room_name'] ?: 'N/A') ?></small></td>
          <td><span class="badge badge-pending"><?= htmlspecialchars(format_test_type($row['test_type'])) ?></span></td>
          <td><?= htmlspecialchars($row['result'] ?: '-') ?></td>
          <td><span class="badge badge-completed"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
          <td>
            <a class="btn btn-outline btn-sm" href="/Petmate/dashboards/vet_technician/assessments.php?view_id=<?= (int)$row['id'] ?>"><i class='bx bx-show'></i> Review</a>
            <a class="btn btn-primary btn-sm" target="_blank" href="/Petmate/dashboards/vet_technician/assessments.php?view_id=<?= (int)$row['id'] ?>&print=1"><i class='bx bx-printer'></i> Print</a>
            <a class="btn btn-accent btn-sm" href="/Petmate/dashboards/vet_technician/treatment_plan.php?assessment_id=<?= (int)$row['id'] ?>"><i class='bx bx-notepad'></i> Treatment Plan</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($selected): ?>
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-file'></i> Assessment Review #<?= (int)$selected['id'] ?></h2>
      <span class="text-muted"><?= htmlspecialchars(format_test_type($selected['test_type'])) ?></span>
    </div>
    <div class="info-row"><span class="info-label">Patient</span><span class="info-value"><?= htmlspecialchars($selected['pet_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Room</span><span class="info-value"><?= htmlspecialchars($selected['room_name'] ?: 'N/A') ?></span></div>
    <div class="info-row"><span class="info-label">Technician</span><span class="info-value"><?= htmlspecialchars($selected['technician_name'] ?: 'N/A') ?></span></div>
    <div class="info-row"><span class="info-label">Short Summary</span><span class="info-value"><?= htmlspecialchars($selected['result'] ?: '-') ?></span></div>
    <div class="action-bar" style="margin-top:8px;">
      <a class="btn btn-accent btn-sm" href="/Petmate/dashboards/vet_technician/treatment_plan.php?assessment_id=<?= (int)$selected['id'] ?>"><i class='bx bx-notepad'></i> Create Treatment Plan</a>
    </div>
    <div class="separator" style="height:1px;background:var(--color-border-tertiary);margin:12px 0;"></div>
    <?php if (empty($selected_rows)): ?>
      <p class="text-muted">No structured result data to display.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>Field</th><th>Value</th></tr></thead>
          <tbody>
          <?php foreach ($selected_rows as $row): ?>
            <tr><td><?= htmlspecialchars($row['label']) ?></td><td><?= htmlspecialchars($row['value']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
