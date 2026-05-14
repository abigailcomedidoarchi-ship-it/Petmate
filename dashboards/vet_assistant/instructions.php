<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT tp.id, tp.description, tp.date, tp.workflow_status, tp.assigned_assistant_id,
           p.name AS pet_name, u.name AS owner_name, a.name AS assigned_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    LEFT JOIN users a ON a.id = tp.assigned_assistant_id
    WHERE tp.workflow_status IN ('forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready')
      AND (tp.assigned_assistant_id = ? OR tp.assigned_assistant_id IS NULL)
    ORDER BY FIELD(tp.workflow_status, 'forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready'),
             tp.date DESC
");
$stmt->execute([$user_id]);
$plans = $stmt->fetchAll();

$current_page = 'instructions';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Medical Instructions</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Instructions</p>
  </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class='bx bx-task'></i> Active treatment pipeline</h2>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Plan ID</th>
                    <th>Date</th>
                    <th>Pet Name</th>
                    <th>Owner</th>
                    <th>Description</th>
                    <th>Assigned To</th>
                    <th>Stage</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                <tr><td colspan="7" class="text-center text-muted">No active treatment tasks at this time.</td></tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan):
                        $wf = $plan['workflow_status'];
                        $wfClass = 'badge badge-outline';
                        if ($wf === 'forwarded' || $wf === 'ongoing_treatment') {
                            $wfClass = 'badge badge-primary';
                        } elseif ($wf === 'monitoring') {
                            $wfClass = 'badge badge-warning';
                        } elseif ($wf === 'discharge_ready') {
                            $wfClass = 'badge badge-success';
                        }
                        $wfLabel = ucwords(str_replace('_', ' ', $wf));
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars((string)$plan['id']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($plan['date']))) ?></td>
                        <td><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></td>
                        <td><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($plan['description']) ?>">
                            <?= htmlspecialchars($plan['description']) ?>
                        </td>
                        <td><?= htmlspecialchars($plan['assigned_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <span class="<?= $wfClass ?>"><?= htmlspecialchars($wfLabel) ?></span>
                        </td>
                        <td>
                            <?php if (in_array($wf, ['forwarded', 'ongoing_treatment'], true)): ?>
                                <a href="administer.php?plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class='bx bx-injection'></i> Administer
                                </a>
                            <?php elseif ($wf === 'monitoring'): ?>
                                <a href="monitor_patient.php?plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class='bx bx-pulse'></i> Monitor
                                </a>
                            <?php elseif ($wf === 'discharge_ready'): ?>
                                <a href="discharge_prep.php?plan_id=<?= (int)$plan['id'] ?>" class="btn btn-sm btn-success">
                                    <i class='bx bx-box'></i> Discharge summary
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
