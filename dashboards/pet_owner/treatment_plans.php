<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');
require_permission('view_dashboard');

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $plan_id = (int)$_POST['plan_id'];
    
    // Verify the plan belongs to this owner
    $stmt = $pdo->prepare("
        SELECT tp.id FROM treatment_plans tp
        JOIN pets p ON p.id = tp.pet_id
        WHERE tp.id = ? AND p.owner_id = ?
    ");
    $stmt->execute([$plan_id, $user_id]);
    if ($stmt->fetch()) {
        if ($_POST['action'] === 'approve') {
            try {
                $pdo->beginTransaction();

                $lock = $pdo->prepare("
                    SELECT tp.id, tp.workflow_status
                    FROM treatment_plans tp
                    INNER JOIN pets p ON p.id = tp.pet_id
                    WHERE tp.id = ? AND p.owner_id = ?
                    FOR UPDATE
                ");
                $lock->execute([$plan_id, $user_id]);
                $row = $lock->fetch();
                if (!$row) {
                    $pdo->rollBack();
                    $error = 'Invalid treatment plan.';
                } else {
                    $pdo->prepare("UPDATE treatment_plans SET consent_status = 'approved', consent_note = NULL WHERE id = ?")->execute([$plan_id]);

                    $wf = (string)($row['workflow_status'] ?? '');
                    $keepWf = ['forwarded', 'ongoing_treatment', 'monitoring', 'discharge_ready', 'pending_billing', 'awaiting_payment', 'completed'];
                    if (in_array($wf, $keepWf, true)) {
                        // Clinic already advanced this case; do not rewind workflow.
                    } else {
                        try {
                            $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'approved' WHERE id = ?")->execute([$plan_id]);
                        } catch (PDOException $e) {
                            // Older DB ENUMs may not list "approved"; use legacy ready state.
                            $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'in_prep' WHERE id = ?")->execute([$plan_id]);
                        }
                    }

                    // If MySQL silently rejected an invalid ENUM for "approved", workflow can stay pending_consent
                    // or become blank while consent is approved — normalize so the clinic UI can assign.
                    $pdo->prepare("
                        UPDATE treatment_plans
                        SET workflow_status = 'in_prep'
                        WHERE id = ?
                          AND consent_status = 'approved'
                          AND (
                              workflow_status = 'pending_consent'
                              OR workflow_status IS NULL
                              OR TRIM(COALESCE(workflow_status, '')) = ''
                          )
                    ")->execute([$plan_id]);

                    // Refresh unread vet-tech alerts for this plan (avoid duplicates / stale rows).
                    $pdo->prepare("DELETE FROM treatment_notifications WHERE plan_id = ? AND role = 'vet_technician' AND type = 'owner_approved' AND is_read = 0")->execute([$plan_id]);
                    $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'vet_technician', 'owner_approved')")->execute([$plan_id]);

                    $pdo->commit();
                    $success = 'You have approved the treatment plan.';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not record approval. Please try again or contact the clinic.';
            }
        } elseif ($_POST['action'] === 'decline') {
            $note = trim($_POST['decline_note'] ?? '');
            if (empty($note)) {
                $error = "A reason is required when declining a treatment plan.";
            } else {
                $update = $pdo->prepare("UPDATE treatment_plans SET consent_status = 'declined', consent_note = ?, workflow_status = 'draft' WHERE id = ?");
                if ($update->execute([$note, $plan_id])) {
                    $success = "You have declined the treatment plan and your note has been sent to the clinic.";
                }
            }
        }
    } else {
        $error = "Invalid treatment plan.";
    }
}

// Fetch plans for this owner
$stmt = $pdo->prepare("
    SELECT tp.id, tp.description, tp.date, tp.consent_status, tp.consent_note, tp.prescriptions,
           p.name AS pet_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    WHERE p.owner_id = ? AND tp.consent_status != 'not_submitted'
    ORDER BY tp.date DESC
");
$stmt->execute([$user_id]);
$plans = $stmt->fetchAll();

$current_page = 'treatment_plans';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Treatment Plans</h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> Treatment Plans</p>
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
        <h2><i class='bx bx-shield-quarter'></i> Treatment Plan Consent</h2>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Pet Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                <tr><td colspan="6" class="text-center text-muted">No treatment plans require your review at this time.</td></tr>
                <?php else: ?>
                    <?php foreach ($plans as $plan): 
                        $statusClass = '';
                        switch($plan['consent_status']) {
                            case 'pending': $statusClass = 'badge badge-warning'; break;
                            case 'approved': $statusClass = 'badge badge-success'; break;
                            case 'declined': $statusClass = 'badge badge-danger'; break;
                        }
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars($plan['id']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($plan['date']))) ?></td>
                        <td><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($plan['description']) ?>">
                            <?= htmlspecialchars($plan['description']) ?>
                        </td>
                        <td>
                            <span class="<?= $statusClass ?>">
                                <?php if($plan['consent_status'] === 'pending') echo 'Needs Review';
                                      else echo ucfirst($plan['consent_status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_treatment.php?id=<?= $plan['id'] ?>" class="btn btn-sm btn-outline">
                                <i class='bx bx-search'></i> View Details
                            </a>
                            <?php if ($plan['consent_status'] === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="approvePlan(<?= $plan['id'] ?>)">
                                    <i class='bx bx-check'></i> Approve
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="openDeclineModal(<?= $plan['id'] ?>)">
                                    <i class='bx bx-x'></i> Decline
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($plan['consent_status'] === 'declined' && !empty($plan['consent_note'])): ?>
                    <tr style="background: #fffafa;">
                        <td colspan="6" style="border-top: none; padding-top: 0;">
                            <div style="font-size: 13px; color: var(--color-danger); padding-left: 12px;">
                                <strong>Your Note:</strong> <?= htmlspecialchars($plan['consent_note']) ?>
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

<!-- Forms for actions -->
<form id="approve-form" method="POST" style="display:none;">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="plan_id" id="approve_plan_id">
</form>

<div id="decline-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 400px; max-width: 90%;">
        <div class="card-header">
            <h2>Decline Treatment Plan</h2>
            <button type="button" style="background:none; border:none; font-size:24px; cursor:pointer;" onclick="closeDeclineModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="plan_id" id="decline_plan_id">
            
            <div class="form-group">
                <label>Please let the clinic know why you are declining this treatment plan: *</label>
                <textarea name="decline_note" rows="4" required placeholder="Too expensive, seeking second opinion, etc."></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline" onclick="closeDeclineModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function approvePlan(id) {
    if(confirm('Are you sure you want to consent to and approve this treatment plan?')) {
        document.getElementById('approve_plan_id').value = id;
        document.getElementById('approve-form').submit();
    }
}

function openDeclineModal(id) {
    document.getElementById('decline_plan_id').value = id;
    document.getElementById('decline-modal').style.display = 'flex';
}

function closeDeclineModal() {
    document.getElementById('decline-modal').style.display = 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
