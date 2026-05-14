<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/treatment_workflow_schema.php';
requireRole('vet_technician');
require_permission('view_dashboard');

petmate_ensure_treatment_plan_workflow_varchar($pdo);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$success = '';
$error = '';

if (!empty($_GET['submitted'])) {
    $success = 'Treatment plan submitted to the Pet Owner for consent.';
}
if (!empty($_GET['forwarded'])) {
    $success = 'Treatment plan forwarded to the Vet Assistant.';
}

$focus_plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

// Handle Submit to Owner action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_plan'])) {
        $plan_id = (int)$_POST['plan_id'];
        $update = $pdo->prepare("UPDATE treatment_plans SET consent_status = 'pending', workflow_status = 'pending_consent' WHERE id = ?");
        if ($update->execute([$plan_id])) {
            header('Location: treatment_details.php?submitted=1');
            exit;
        }
        $error = "Failed to submit treatment plan.";
    } elseif (isset($_POST['forward_plan'])) {
        $plan_id = (int)$_POST['plan_id'];
        $update = $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'forwarded' WHERE id = ? AND consent_status = 'approved' AND workflow_status NOT IN ('forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed')");
        if ($update->execute([$plan_id]) && $update->rowCount() > 0) {
            // Mark tech notification as read
            $pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE plan_id = ? AND role = 'vet_technician'")->execute([$plan_id]);
            // Create notification for vet_assistant
            $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_assistant', 'forwarded_to_assistant')")->execute([$plan_id]);
            header('Location: treatment_details.php?forwarded=1');
            exit;
        }
        $error = "Forward failed. The plan must be owner-approved (workflow: approved) before assignment.";
    }
}

// Fetch all treatment plans
$stmt = $pdo->prepare("
    SELECT tp.id, tp.description, tp.date, tp.consent_status, tp.consent_note, tp.workflow_status,
           p.name AS pet_name, u.name AS owner_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    ORDER BY tp.date DESC
");
$stmt->execute();
$plans = $stmt->fetchAll();

$current_page = 'treatment_details';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Treatment Details & Consent</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Treatment Details</p>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class='bx bx-list-ul'></i> Treatment Plans</h2>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Pet Name</th>
                    <th>Owner</th>
                    <th>Description</th>
                    <th>Consent Status</th>
                    <th>Workflow</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                <tr><td colspan="8" class="text-center text-muted">No treatment plans found.</td></tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan):
                        $wf = trim((string)($plan['workflow_status'] ?? ''));
                        // Any owner-approved plan that has not yet been assigned/forwarded may be scheduled.
                        $blockedForAssign = ['forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed'];
                        $canForward = ($plan['consent_status'] === 'approved' && !in_array($wf, $blockedForAssign, true));

                        $statusClass = '';
                        switch($plan['consent_status']) {
                            case 'not_submitted': $statusClass = 'badge badge-outline'; break;
                            case 'pending': $statusClass = 'badge badge-warning'; break;
                            case 'approved': $statusClass = 'badge badge-success'; break;
                            case 'declined': $statusClass = 'badge badge-danger'; break;
                        }

                        $wfClass = 'badge badge-outline';
                        switch($plan['workflow_status']) {
                            case 'draft': $wfClass = 'badge badge-outline'; break;
                            case 'in_prep': $wfClass = 'badge badge-success'; break;
                            case 'pending_consent': $wfClass = 'badge badge-warning'; break;
                            case 'approved': $wfClass = 'badge badge-success'; break;
                            case 'forwarded': $wfClass = 'badge badge-primary'; break;
                            case 'ongoing_treatment': $wfClass = 'badge badge-primary'; break;
                            case 'monitoring': $wfClass = 'badge badge-warning'; break;
                            case 'discharge_ready': $wfClass = 'badge badge-success'; break;
                            case 'pending_billing': $wfClass = 'badge badge-accent'; break;
                            case 'awaiting_payment': $wfClass = 'badge badge-accent'; break;
                            case 'completed': $wfClass = 'badge badge-success'; break;
                        }
                    ?>
                    <tr id="plan-row-<?= (int)$plan['id'] ?>"<?= ($focus_plan_id && (int)$plan['id'] === $focus_plan_id) ? ' style="box-shadow: inset 3px 0 0 var(--color-primary, #6366f1); background: rgba(99,102,241,0.08);"' : '' ?>>
                        <td>#<?= htmlspecialchars($plan['id']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($plan['date']))) ?></td>
                        <td><?= htmlspecialchars($plan['pet_name']) ?></td>
                        <td><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($plan['description']) ?>">
                            <?= htmlspecialchars($plan['description']) ?>
                        </td>
                        <td>
                            <span class="<?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $plan['consent_status'])) ?></span>
                        </td>
                        <td>
                            <span class="<?= $wfClass ?>"><?= ucfirst(str_replace('_', ' ', $plan['workflow_status'])) ?></span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="view_treatment.php?id=<?= $plan['id'] ?>" class="btn btn-sm btn-outline" title="Review Plan">
                                    <i class='bx bx-search'></i> View
                                </a>
                                <?php if ($plan['consent_status'] === 'not_submitted'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to submit this plan to the owner for consent?');">
                                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                    <button type="submit" name="submit_plan" class="btn btn-sm btn-primary" title="Submit to Owner">
                                        <i class='bx bx-send'></i> Submit
                                    </button>
                                </form>
                                <?php elseif ($plan['consent_status'] === 'declined'): ?>
                                <a href="treatment_plan.php?edit_id=<?= $plan['id'] ?>" class="btn btn-sm btn-danger" title="Edit & Resubmit Plan">
                                    <i class='bx bx-edit'></i> Edit & Resubmit
                                </a>
                                <?php elseif ($canForward): ?>
                                <a href="schedule_assignment.php?plan_id=<?= $plan['id'] ?>" class="btn btn-sm btn-success" title="Schedule and Assign to Vet Assistant">
                                    <i class='bx bx-calendar-event'></i> Assign & Schedule
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($plan['consent_status'] === 'declined' && !empty($plan['consent_note'])): ?>
                    <tr style="background: #fffafa;">
                        <td colspan="8" style="border-top: none; padding-top: 0;">
                            <div class="alert alert-error" style="margin: 0; padding: 8px 12px; font-size: 13px;">
                                <strong><i class='bx bx-message-square-error'></i> Owner Note (Declined):</strong> <?= htmlspecialchars($plan['consent_note']) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($focus_plan_id): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var row = document.getElementById('plan-row-<?= (int)$focus_plan_id ?>');
  if (row) row.scrollIntoView({ block: 'center', behavior: 'smooth' });
});
</script>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>