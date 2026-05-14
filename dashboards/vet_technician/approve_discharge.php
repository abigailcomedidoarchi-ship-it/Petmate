<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/monitoring_logs_schema.php';
requireRole('vet_technician');
require_permission('view_dashboard');

petmate_ensure_monitoring_logs_table($pdo);

$error = '';
$success = '';

function tp_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $s = $pdo->prepare('SHOW COLUMNS FROM treatment_plans LIKE ?');
    $s->execute([$column]);
    $cache[$column] = (bool) $s->fetch();
    return $cache[$column];
}

function latest_monitoring_status(PDO $pdo, int $plan_id): ?array
{
    $st = $pdo->prepare("SELECT patient_status, created_at FROM monitoring_logs WHERE plan_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
    $st->execute([$plan_id]);
    $row = $st->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_discharge'])) {
    $plan_id = (int)$_POST['plan_id'];
    $latest = latest_monitoring_status($pdo, $plan_id);
    if (!$latest) {
        $error = 'There is no monitoring documentation for this plan yet. The Vet Assistant must record recovery observations before discharge can be approved.';
    } elseif (!in_array($latest['patient_status'], ['stable', 'recovering'], true)) {
        $error = 'Discharge cannot be approved unless the latest monitoring status is Stable or Recovering. Current status: ' . htmlspecialchars(str_replace('_', ' ', $latest['patient_status']));
    } else {
        $sql = tp_has_column($pdo, 'discharge_approved_at')
            ? "UPDATE treatment_plans SET workflow_status = 'discharge_ready', discharge_approved_at = NOW() WHERE id = ? AND workflow_status = 'monitoring'"
            : "UPDATE treatment_plans SET workflow_status = 'discharge_ready' WHERE id = ? AND workflow_status = 'monitoring'";
        $upd = $pdo->prepare($sql);
        $upd->execute([$plan_id]);
        if ($upd->rowCount() === 0) {
            $error = 'This plan is not in monitoring or was already updated.';
        } else {
            $pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE plan_id = ? AND role = 'vet_technician' AND type = 'monitoring_critical'")->execute([$plan_id]);
            $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'pet_owner', 'discharge_ready')")->execute([$plan_id]);
            $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_assistant', 'discharge_summary_needed')")->execute([$plan_id]);
            $success = 'Discharge approved. The Vet Assistant may now complete the discharge summary.';
            header('Location: approve_discharge.php?msg=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_discharge'])) {
    $plan_id = (int) ($_POST['plan_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id FROM treatment_plans WHERE id = ? AND workflow_status = 'monitoring'");
    $chk->execute([$plan_id]);
    if (!$chk->fetch()) {
        $error = 'This plan is not in monitoring or cannot be rejected from this screen.';
    } else {
        $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_assistant', 'discharge_review_followup')")->execute([$plan_id]);
        header('Location: approve_discharge.php?msg_reject=1');
        exit;
    }
}

if (!empty($_GET['msg_reject'])) {
    $success = 'The plan remains in monitoring. The Vet Assistant has been notified to continue recovery documentation.';
} elseif (!empty($_GET['msg'])) {
    $success = 'Discharge approved. The Vet Assistant may now complete the discharge summary.';
}

$stmt = $pdo->query("
    SELECT tp.id, tp.description, tp.date, p.name AS pet_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    WHERE tp.workflow_status = 'monitoring'
    ORDER BY tp.date DESC
");
$plans = $stmt->fetchAll();

$current_page = 'approve_discharge';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Discharge approval</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Recovery review</p>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header"><h2><i class='bx bx-shield-quarter'></i> Patients in monitoring</h2></div>
  <p class="text-muted mb-4">Review monitoring documentation. You may approve discharge only when the <strong>latest</strong> monitoring entry is <strong>Stable</strong> or <strong>Recovering</strong>.</p>

  <?php if (empty($plans)): ?>
    <div class="empty-state"><p>No treatment plans are awaiting discharge approval.</p></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Plan</th>
            <th>Pet</th>
            <th>Latest monitoring</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plans as $pl):
            $latest = latest_monitoring_status($pdo, (int)$pl['id']);
          ?>
          <tr>
            <td>#<?= (int)$pl['id'] ?></td>
            <td><strong><?= htmlspecialchars($pl['pet_name']) ?></strong></td>
            <td>
              <?php if (!$latest): ?>
                <span class="badge badge-outline">No logs yet</span>
              <?php else: ?>
                <span class="badge <?= $latest['patient_status'] === 'critical' ? 'badge-danger' : ($latest['patient_status'] === 'stable' ? 'badge-success' : 'badge-warning') ?>">
                  <?= htmlspecialchars(str_replace('_', ' ', $latest['patient_status'])) ?>
                </span>
                <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($latest['created_at']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/Petmate/dashboards/vet_technician/view_monitoring.php?plan_id=<?= (int)$pl['id'] ?>" class="btn btn-sm btn-outline"><i class='bx bx-line-chart'></i> View monitoring</a>
              <?php if ($latest && in_array($latest['patient_status'], ['stable', 'recovering'], true)): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this patient for discharge preparation?');">
                <input type="hidden" name="plan_id" value="<?= (int)$pl['id'] ?>">
                <button type="submit" name="approve_discharge" class="btn btn-sm btn-success"><i class='bx bx-check-shield'></i> Approve discharge</button>
              </form>
              <?php endif; ?>
              <?php if ($latest): ?>
              <form method="POST" class="mt-1" onsubmit="return confirm('Keep this patient in monitoring and notify the Vet Assistant?');">
                <input type="hidden" name="plan_id" value="<?= (int)$pl['id'] ?>">
                <button type="submit" name="reject_discharge" class="btn btn-sm btn-outline"><i class='bx bx-x'></i> Reject — stay in monitoring</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
