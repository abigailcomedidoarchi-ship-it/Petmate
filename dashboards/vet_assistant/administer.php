<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_assistant');
require_permission('view_dashboard');

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

function admin_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $s = $pdo->prepare('SHOW COLUMNS FROM administration_logs LIKE ?');
    $s->execute([$column]);
    $cache[$column] = (bool) $s->fetch();
    return $cache[$column];
}

require_once __DIR__ . '/../../includes/treatment_workflow_schema.php';
require_once __DIR__ . '/../../includes/monitoring_logs_schema.php';

$plan_id = isset($_GET['plan_id']) ? (int) $_GET['plan_id'] : 0;
$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if (!$plan_id) {
    header('Location: index.php');
    exit;
}

petmate_ensure_treatment_plan_workflow_varchar($pdo);
petmate_ensure_monitoring_logs_table($pdo);

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
    die('Treatment plan not found.');
}

/**
 * If "Begin treatment" ran but workflow stayed forwarded/empty (ENUM mismatch, etc.),
 * or treatment_started_at was set but status not updated, normalize to ongoing_treatment and refetch.
 */
function administer_sync_ongoing_state(PDO $pdo, PDOStatement $stmt, int $plan_id, array $plan, bool $fromStartedParam): array
{
    $ws = strtolower(trim((string) ($plan['workflow_status'] ?? '')));
    $startedCol = false;
    try {
        $c = $pdo->prepare('SHOW COLUMNS FROM treatment_plans LIKE ?');
        $c->execute(['treatment_started_at']);
        $startedCol = (bool) $c->fetch();
    } catch (Throwable $e) {
        $startedCol = false;
    }
    $ts = $startedCol ? trim((string) ($plan['treatment_started_at'] ?? '')) : '';

    $needsFix = ($fromStartedParam && in_array($ws, ['forwarded', ''], true))
        || ($ts !== '' && strcasecmp($ws, 'ongoing_treatment') !== 0 && strcasecmp($ws, 'monitoring') !== 0 && in_array($ws, ['forwarded', ''], true));

    if (!$needsFix) {
        return $plan;
    }

    try {
        $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'ongoing_treatment' WHERE id = ? AND (workflow_status IN ('forwarded', '') OR workflow_status IS NULL)")->execute([$plan_id]);
        $stmt->execute([$plan_id]);
        $fresh = $stmt->fetch();
        if ($fresh) {
            return $fresh;
        }
    } catch (Throwable $e) {
    }

    return $plan;
}

$plan = administer_sync_ongoing_state($pdo, $stmt, $plan_id, $plan, !empty($_GET['started']));

$wf = trim((string) ($plan['workflow_status'] ?? ''));

if ($wf === 'monitoring') {
    header('Location: monitor_patient.php?plan_id=' . $plan_id);
    exit;
}

if ($wf === 'administered') {
    try {
        $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'monitoring' WHERE id = ?")->execute([$plan_id]);
    } catch (Throwable $e) {
    }
    $chk = $pdo->prepare("SELECT workflow_status FROM treatment_plans WHERE id = ?");
    $chk->execute([$plan_id]);
    $wf2 = trim((string) $chk->fetchColumn());
    if ($wf2 === 'monitoring') {
        header('Location: monitor_patient.php?plan_id=' . $plan_id);
        exit;
    }
    header('Location: discharge_prep.php?plan_id=' . $plan_id);
    exit;
}

$allowedAdmin = ['forwarded', 'ongoing_treatment'];
$wfLc = strtolower($wf);
if (!in_array($wfLc, $allowedAdmin, true)) {
    $fwdCount = $pdo->prepare("SELECT COUNT(*) FROM treatment_notifications WHERE plan_id = ? AND type = 'forwarded_to_assistant'");
    $fwdCount->execute([$plan_id]);
    if ((int) $fwdCount->fetchColumn() > 0) {
        try {
            $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'forwarded' WHERE id = ?")->execute([$plan_id]);
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch();
            if (!$plan) {
                die('Treatment plan not found.');
            }
            $wf = trim((string) ($plan['workflow_status'] ?? ''));
        } catch (Throwable $e) {
        }
    }
}

// Notification repair may have reset workflow; re-apply "started" sync so execution UI matches DB/timestamp.
$plan = administer_sync_ongoing_state($pdo, $stmt, $plan_id, $plan, !empty($_GET['started']));
$wf = trim((string) ($plan['workflow_status'] ?? ''));

if (!in_array(strtolower(trim((string) ($plan['workflow_status'] ?? ''))), $allowedAdmin, true)) {
    $stage = (string) ($plan['workflow_status'] ?? '');
    $stageLabel = $stage === '' ? '(empty — ensure workflow_status allows forwarded/ongoing_treatment)' : htmlspecialchars($stage);
    die('This treatment plan is not available for administration. Current stage: ' . $stageLabel);
}

$data = json_decode($plan['prescriptions'], true) ?: [];
$medicines = $data['medicines'] ?? [];
$surgeries = $data['surgeries'] ?? [];
$procedures = $data['procedures'] ?? [];

$colDosageGiven = admin_has_column($pdo, 'dosage_given');
$colPatientResponse = admin_has_column($pdo, 'patient_response');
$colReaction = admin_has_column($pdo, 'reaction');
$colMonitoringRequired = admin_has_column($pdo, 'monitoring_required');
$colAdministeredAt = admin_has_column($pdo, 'administered_at');
$colProcedureCompleted = admin_has_column($pdo, 'procedure_completed');
$colSurgeryCompleted = admin_has_column($pdo, 'surgery_completed');
$hasExtendedAdminLogs = $colDosageGiven && $colPatientResponse && $colReaction && $colMonitoringRequired;
$hasProcSurgFlags = $colProcedureCompleted && $colSurgeryCompleted;

$pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE plan_id = ? AND role = 'vet_assistant' AND type = 'forwarded_to_assistant'")->execute([$plan_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_treatment'])) {
        $sets = ["workflow_status = 'ongoing_treatment'"];
        $params = [];
        if (tp_has_column($pdo, 'treatment_started_at')) {
            $sets[] = 'treatment_started_at = NOW()';
        }
        if (tp_has_column($pdo, 'started_by')) {
            $sets[] = 'started_by = ?';
            $params[] = $user_id;
        }
        $params[] = $plan_id;
        $sql = 'UPDATE treatment_plans SET ' . implode(', ', $sets) . " WHERE id = ? AND LOWER(TRIM(COALESCE(workflow_status, ''))) = 'forwarded'";
        $u = $pdo->prepare($sql);
        $u->execute($params);
        if ($u->rowCount() === 0) {
            $error = 'Treatment could not be started (already in progress or invalid state).';
        } else {
            header('Location: administer.php?plan_id=' . $plan_id . '&started=1');
            exit;
        }
    }

    if (isset($_POST['complete_administration'])) {
        $stmt->execute([$plan_id]);
        $planLive = $stmt->fetch() ?: $plan;
        $wsLive = strtolower(trim((string) ($planLive['workflow_status'] ?? '')));
        if ($wsLive !== 'ongoing_treatment') {
            try {
                $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'ongoing_treatment' WHERE id = ? AND (workflow_status IN ('forwarded', '') OR workflow_status IS NULL)")->execute([$plan_id]);
                $stmt->execute([$plan_id]);
                $planLive = $stmt->fetch() ?: $planLive;
                $wsLive = strtolower(trim((string) ($planLive['workflow_status'] ?? '')));
            } catch (Throwable $e) {
            }
        }
        if ($wsLive !== 'ongoing_treatment') {
            $error = 'Use “Begin treatment” first, then record administration before entering monitoring.';
        } else {
            $plan = $planLive;
            $med_notes = $_POST['med_notes'] ?? [];
            $dosage_given_in = $_POST['dosage_given'] ?? [];
            $patient_resp = $_POST['patient_response'] ?? [];
            $reaction = $_POST['reaction'] ?? [];
            $mon_req = $_POST['monitoring_required'] ?? [];
            $proc_complete = $_POST['proc_complete'] ?? [];
            $proc_notes = $_POST['proc_notes'] ?? [];
            $surg_complete = $_POST['surg_complete'] ?? [];
            $surg_notes = $_POST['surg_notes'] ?? [];
            $surg_recovery = $_POST['surg_recovery'] ?? [];
            $surg_postop = $_POST['surg_postop'] ?? [];

            foreach ($medicines as $i => $med) {
                if (trim((string) ($med_notes[$i] ?? '')) === '') {
                    $error = 'Each medication requires administration notes before you can enter monitoring.';
                    break;
                }
                if (trim((string) ($dosage_given_in[$i] ?? '')) === '') {
                    $error = 'Record dosage given for each medication (actual amount or route given).';
                    break;
                }
            }

            if ($error === '' && !empty($procedures)) {
                foreach ($procedures as $i => $proc) {
                    if (empty($proc_complete[$i])) {
                        $error = 'Mark every listed procedure as completed (or confirm it was not performed per hospital policy) before completing administration.';
                        break;
                    }
                }
            }

            if ($error === '' && !empty($surgeries)) {
                foreach ($surgeries as $i => $surg) {
                    if (empty($surg_complete[$i])) {
                        $error = 'Mark every listed surgery assistance as completed before completing administration.';
                        break;
                    }
                }
            }

            if ($error === '' && empty($medicines) && empty($procedures) && empty($surgeries)) {
                if (trim((string) ($_POST['general_admin_notes'] ?? '')) === '') {
                    $error = 'Enter general administration notes for this visit.';
                }
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();

                    if (!empty($medicines)) {
                        if ($hasExtendedAdminLogs) {
                            $insSql = 'INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, dosage_given, notes, patient_response, reaction, monitoring_required';
                            if ($colAdministeredAt) {
                                $insSql .= ', administered_at';
                            }
                            if ($hasProcSurgFlags) {
                                $insSql .= ', procedure_completed, surgery_completed';
                            }
                            $insSql .= ') VALUES (?, ?, ?, ?, ?, ?, ?, ?';
                            if ($colAdministeredAt) {
                                $insSql .= ', NOW()';
                            }
                            if ($hasProcSurgFlags) {
                                $insSql .= ', 0, 0';
                            }
                            $insSql .= ')';
                            $ins = $pdo->prepare($insSql);
                        } else {
                            $ins = $pdo->prepare('INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes) VALUES (?, ?, ?, ?)');
                        }

                        foreach ($medicines as $i => $med) {
                            $mname = $med['medicine_name'] ?? 'Medication';
                            $note = trim((string) ($med_notes[$i] ?? ''));
                            $dg = trim((string) ($dosage_given_in[$i] ?? ''));
                            $presp = trim((string) ($patient_resp[$i] ?? ''));
                            $react = trim((string) ($reaction[$i] ?? ''));
                            $mreq = !empty($mon_req[$i]) ? 1 : 0;
                            if ($hasExtendedAdminLogs) {
                                $ins->execute([$plan_id, $user_id, $mname, $dg ?: null, $note ?: null, $presp ?: null, $react ?: null, $mreq]);
                            } else {
                                $combined = $note;
                                if ($dg !== '') {
                                    $combined .= ($combined !== '' ? ' | ' : '') . 'Dosage given: ' . $dg;
                                }
                                if ($presp !== '') {
                                    $combined .= ($combined !== '' ? ' | ' : '') . 'Response: ' . $presp;
                                }
                                if ($react !== '') {
                                    $combined .= ($combined !== '' ? ' | ' : '') . 'Reaction: ' . $react;
                                }
                                $ins->execute([$plan_id, $user_id, $mname, $combined ?: null]);
                            }
                        }
                    } else {
                        $gen = trim((string) ($_POST['general_admin_notes'] ?? ''));
                        if ($gen !== '') {
                            $ins = $pdo->prepare('INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes) VALUES (?, ?, ?, ?)');
                            $ins->execute([$plan_id, $user_id, 'General treatment', $gen]);
                        }
                    }

                    if ($hasProcSurgFlags && !empty($procedures)) {
                        $pIns = $pdo->prepare(
                            'INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes, procedure_completed, surgery_completed'
                            . ($colAdministeredAt ? ', administered_at' : '')
                            . ') VALUES (?, ?, ?, ?, 1, 0'
                            . ($colAdministeredAt ? ', NOW()' : '')
                            . ')'
                        );
                        foreach ($procedures as $i => $proc) {
                            if (empty($proc_complete[$i])) {
                                continue;
                            }
                            $pname = $proc['name'] ?? 'Procedure';
                            $pn = trim((string) ($proc_notes[$i] ?? ''));
                            $pIns->execute([$plan_id, $user_id, 'Procedure: ' . $pname, $pn ?: null]);
                        }
                    } elseif (!empty($procedures) && $hasExtendedAdminLogs) {
                        foreach ($procedures as $i => $proc) {
                            if (empty($proc_complete[$i])) {
                                continue;
                            }
                            $pname = $proc['name'] ?? 'Procedure';
                            $pn = trim((string) ($proc_notes[$i] ?? ''));
                            $fallback = $pdo->prepare(
                                'INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, dosage_given, notes, patient_response, reaction, monitoring_required'
                                . ($colAdministeredAt ? ', administered_at' : '')
                                . ") VALUES (?, ?, ?, NULL, ?, NULL, NULL, 0"
                                . ($colAdministeredAt ? ', NOW()' : '')
                                . ')'
                            );
                            $fallback->execute([$plan_id, $user_id, 'Procedure: ' . $pname, $pn ?: 'Procedure completed.']);
                        }
                    } elseif (!empty($procedures)) {
                        $minP = $pdo->prepare('INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes) VALUES (?, ?, ?, ?)');
                        foreach ($procedures as $i => $proc) {
                            if (empty($proc_complete[$i])) {
                                continue;
                            }
                            $pname = $proc['name'] ?? 'Procedure';
                            $pn = trim((string) ($proc_notes[$i] ?? ''));
                            $minP->execute([$plan_id, $user_id, 'Procedure: ' . $pname, $pn ?: 'Procedure completed.']);
                        }
                    }

                    if ($hasProcSurgFlags && !empty($surgeries)) {
                        $sIns = $pdo->prepare(
                            'INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes, procedure_completed, surgery_completed'
                            . ($colAdministeredAt ? ', administered_at' : '')
                            . ') VALUES (?, ?, ?, ?, 0, 1'
                            . ($colAdministeredAt ? ', NOW()' : '')
                            . ')'
                        );
                        foreach ($surgeries as $i => $surg) {
                            if (empty($surg_complete[$i])) {
                                continue;
                            }
                            $sname = $surg['name'] ?? 'Surgery';
                            $parts = array_filter([
                                trim((string) ($surg_notes[$i] ?? '')) ? 'Assistance notes: ' . trim($surg_notes[$i]) : '',
                                trim((string) ($surg_recovery[$i] ?? '')) ? 'Recovery concerns: ' . trim($surg_recovery[$i]) : '',
                                trim((string) ($surg_postop[$i] ?? '')) ? 'Post-op observation: ' . trim($surg_postop[$i]) : '',
                            ]);
                            $snote = implode("\n", $parts);
                            $sIns->execute([$plan_id, $user_id, 'Surgery: ' . $sname, $snote ?: null]);
                        }
                    } elseif (!empty($surgeries) && $hasExtendedAdminLogs) {
                        foreach ($surgeries as $i => $surg) {
                            if (empty($surg_complete[$i])) {
                                continue;
                            }
                            $sname = $surg['name'] ?? 'Surgery';
                            $parts = array_filter([
                                trim((string) ($surg_notes[$i] ?? '')) ? 'Assistance notes: ' . trim($surg_notes[$i]) : '',
                                trim((string) ($surg_recovery[$i] ?? '')) ? 'Recovery concerns: ' . trim($surg_recovery[$i]) : '',
                                trim((string) ($surg_postop[$i] ?? '')) ? 'Post-op observation: ' . trim($surg_postop[$i]) : '',
                            ]);
                            $snote = implode("\n", $parts);
                            $fb = $pdo->prepare(
                                'INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, dosage_given, notes, patient_response, reaction, monitoring_required'
                                . ($colAdministeredAt ? ', administered_at' : '')
                                . ") VALUES (?, ?, ?, NULL, ?, NULL, NULL, 0"
                                . ($colAdministeredAt ? ', NOW()' : '')
                                . ')'
                            );
                            $fb->execute([$plan_id, $user_id, 'Surgery: ' . $sname, $snote ?: 'Surgery assistance completed.']);
                        }
                    } elseif (!empty($surgeries)) {
                        $minS = $pdo->prepare('INSERT INTO administration_logs (plan_id, vet_assistant_id, medicine_name, notes) VALUES (?, ?, ?, ?)');
                        foreach ($surgeries as $i => $surg) {
                            if (empty($surg_complete[$i])) {
                                continue;
                            }
                            $sname = $surg['name'] ?? 'Surgery';
                            $parts = array_filter([
                                trim((string) ($surg_notes[$i] ?? '')) ? 'Assistance notes: ' . trim($surg_notes[$i]) : '',
                                trim((string) ($surg_recovery[$i] ?? '')) ? 'Recovery concerns: ' . trim($surg_recovery[$i]) : '',
                                trim((string) ($surg_postop[$i] ?? '')) ? 'Post-op observation: ' . trim($surg_postop[$i]) : '',
                            ]);
                            $snote = implode("\n", $parts);
                            $minS->execute([$plan_id, $user_id, 'Surgery: ' . $sname, $snote ?: 'Surgery assistance completed.']);
                        }
                    }

                    $updSet = ["workflow_status = 'monitoring'"];
                    if (tp_has_column($pdo, 'treatment_completed_at')) {
                        $updSet[] = 'treatment_completed_at = NOW()';
                    }
                    if (tp_has_column($pdo, 'monitoring_started_at')) {
                        $updSet[] = 'monitoring_started_at = NOW()';
                    }
                    $pdo->prepare('UPDATE treatment_plans SET ' . implode(', ', $updSet) . ' WHERE id = ?')->execute([$plan_id]);

                    $pdo->commit();
                    header('Location: monitor_patient.php?plan_id=' . $plan_id);
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Could not complete administration. Apply database migrations if columns are missing.';
                }
            }
        }
    }
}

$wfUi = strtolower(trim((string) ($plan['workflow_status'] ?? '')));
$forwardedShow = ($wfUi === 'forwarded');
$ongoingStrict = ($wfUi === 'ongoing_treatment');
$startedAt = tp_has_column($pdo, 'treatment_started_at') && trim((string) ($plan['treatment_started_at'] ?? '')) !== '';
$startedParam = !empty($_GET['started']);
$executionUi = $ongoingStrict || $startedAt || ($startedParam && ($forwardedShow || $wfUi === ''));
$showBeginTreatment = $forwardedShow && !$executionUi;

if (!empty($_GET['started'])) {
    if ($executionUi) {
        $success = 'Treatment is now active. Record dosage and observations below, then complete administration to move the patient into recovery monitoring.';
    } else {
        $error = 'Treatment start may not have saved correctly (workflow is still "' . htmlspecialchars($wfUi !== '' ? $plan['workflow_status'] : 'empty') . '"). Run the hospital workflow migration so workflow_status accepts ongoing_treatment, or contact support.';
    }
}

$current_page = 'administer.php';
require_once '../../includes/header.php';
?>

<div class="action-bar hide-on-print">
  <div>
    <h1 class="page-heading">Administer treatment</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Assistant <span>›</span> Administration</p>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($executionUi): ?>
<div class="alert alert-warning mb-4" style="border-left: 4px solid #c9a227;">
  <strong><i class='bx bx-injection'></i> Patient currently undergoing treatment.</strong>
  Reassignment and schedule changes are locked while treatment is in progress. Complete all required logs, then use the button at the bottom to enter recovery monitoring.
</div>
<?php endif; ?>

<div class="card printable-area">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h2><i class='bx bx-injection'></i> Treatment plan #<?= (int) $plan_id ?></h2>
      <span class="badge badge-primary"><?= htmlspecialchars(str_replace('_', ' ', (string) ($plan['workflow_status'] ?? ''))) ?></span>
  </div>

  <div class="grid grid-2" style="margin-bottom: 24px;">
    <div>
        <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><?= htmlspecialchars($plan['pet_name']) ?></span></div>
        <div class="info-row"><span class="info-label">Owner Name</span><span class="info-value"><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></span></div>
    </div>
    <div>
        <div class="info-row"><span class="info-label">Plan date</span><span class="info-value"><?= htmlspecialchars(date('M d, Y H:i', strtotime($plan['date']))) ?></span></div>
        <div class="info-row"><span class="info-label">Description</span><span class="info-value"><?= htmlspecialchars($plan['description']) ?></span></div>
    </div>
  </div>

  <?php if ($showBeginTreatment): ?>
  <div class="alert alert-info mb-4">
    <strong>Step 1.</strong> Confirm the patient is ready and treatment is beginning, then click <strong>Begin treatment</strong>. Execution fields (dosage, notes, procedures) appear below once treatment is active.
  </div>
  <form method="POST" class="mb-4">
    <button type="submit" name="start_treatment" class="btn btn-primary" onclick="return confirm('Mark this case as ongoing treatment?');"><i class='bx bx-play'></i> Begin treatment</button>
  </form>
  <?php endif; ?>

  <?php if ($executionUi): ?><form method="POST"><?php endif; ?>

  <?php if (!empty($medicines)): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary);"><i class='bx bx-capsule'></i> Medications (treatment plan)</h3>
    <?php foreach ($medicines as $i => $med): ?>
        <div style="border: 1px solid var(--color-border); padding: 16px; border-radius: 8px; margin-bottom: 16px; background: #fff;">
            <div class="grid grid-3">
                <div class="info-row"><span class="info-label">Medicine</span><span class="info-value" style="font-size:16px; font-weight:600;"><?= htmlspecialchars($med['medicine_name'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Dosage (prescribed)</span><span class="info-value"><?= htmlspecialchars($med['dosage'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Frequency</span><span class="info-value"><?= htmlspecialchars($med['frequency'] ?? '') ?></span></div>
            </div>
            <div class="grid grid-3 mt-2">
                <div class="info-row"><span class="info-label">Duration</span><span class="info-value"><?= htmlspecialchars($med['duration'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Schedule</span><span class="info-value">
                    <?= !empty($med['time_schedule']['am']) ? '<span class="badge badge-outline">AM</span> ' : '' ?>
                    <?= !empty($med['time_schedule']['pm']) ? '<span class="badge badge-outline">PM</span>' : '' ?>
                    <?= empty($med['time_schedule']['am']) && empty($med['time_schedule']['pm']) ? '—' : '' ?>
                </span></div>
                <div class="info-row"><span class="info-label">Instructions</span><span class="info-value"><?= htmlspecialchars($med['notes'] ?? 'None') ?></span></div>
            </div>

            <?php if ($executionUi): ?>
            <div style="margin-top: 16px; border-top: 1px dashed var(--color-border); padding-top: 16px;">
                <h4 class="mb-2" style="font-size:14px; color: var(--color-primary);">Administration (execution)</h4>
                <div class="form-group">
                    <label>Dosage given *</label>
                    <input type="text" name="dosage_given[<?= (int) $i ?>]" required class="form-control" placeholder="Amount / route actually given">
                </div>
                <div class="form-group">
                    <label>Administration notes *</label>
                    <input type="text" name="med_notes[<?= (int) $i ?>]" required class="form-control" placeholder="e.g. IM left thigh, tolerated well">
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Patient response</label>
                        <input type="text" name="patient_response[<?= (int) $i ?>]" class="form-control" placeholder="Alert, calm, etc.">
                    </div>
                    <div class="form-group">
                        <label>Adverse reactions</label>
                        <input type="text" name="reaction[<?= (int) $i ?>]" class="form-control" placeholder="None / describe">
                    </div>
                </div>
                <label class="form-check"><input type="checkbox" name="monitoring_required[<?= (int) $i ?>]" value="1"> Close monitoring required for this medication</label>
            </div>
            <?php elseif ($showBeginTreatment): ?>
            <p class="text-muted mt-2 mb-0" style="font-size:13px;"><i class='bx bx-info-circle'></i> Begin treatment to unlock dosage and administration fields.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
  <?php elseif ($executionUi): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px;"><i class='bx bx-capsule'></i> Medications</h3>
    <p class="text-muted">No medications on this plan. Describe what was performed below.</p>
    <div class="form-group">
        <label>General administration notes *</label>
        <textarea name="general_admin_notes" rows="3" required class="form-control" placeholder="Describe procedures or treatments performed…"></textarea>
    </div>
  <?php elseif ($showBeginTreatment): ?>
    <p class="text-muted mb-4">No medications on this plan.</p>
  <?php endif; ?>

      <?php if (!empty($surgeries)): ?>
        <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-cut'></i> Planned surgeries</h3>
        <?php foreach ($surgeries as $i => $surg): ?>
            <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
                <div class="grid grid-2">
                    <div class="info-row"><span class="info-label">Surgery Name</span><span class="info-value"><strong><?= htmlspecialchars($surg['name'] ?? '') ?></strong></span></div>
                    <div class="info-row"><span class="info-label">Scheduled Date & Time</span><span class="info-value"><?= htmlspecialchars(($surg['scheduled_date'] ?? $surg['date'] ?? 'N/A') . ' ' . ($surg['scheduled_time'] ?? '')) ?></span></div>
                    <div class="info-row"><span class="info-label">Est. Cost</span><span class="info-value"><?= htmlspecialchars($surg['cost'] ?: 'N/A') ?></span></div>
                    <div class="info-row"><span class="info-label">Status</span><span class="info-value"><?= htmlspecialchars($surg['status'] ?? 'N/A') ?></span></div>
                </div>
                <?php if ($executionUi): ?>
                <div style="margin-top:12px; border-top:1px dashed var(--color-border); padding-top:12px;">
                    <label class="form-check"><input type="checkbox" name="surg_complete[<?= (int) $i ?>]" value="1" required> Surgery assistance completed</label>
                    <div class="form-group mt-2">
                        <label>Surgery assistance notes</label>
                        <textarea name="surg_notes[<?= (int) $i ?>]" rows="2" class="form-control" placeholder="Role during procedure, counts, etc."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Recovery concerns</label>
                        <textarea name="surg_recovery[<?= (int) $i ?>]" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Post-op observation notes</label>
                        <textarea name="surg_postop[<?= (int) $i ?>]" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($procedures)): ?>
        <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-pulse'></i> Procedures</h3>
        <?php foreach ($procedures as $i => $proc): ?>
            <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
                <div class="grid grid-2">
                    <div class="info-row"><span class="info-label">Procedure Name</span><span class="info-value"><strong><?= htmlspecialchars($proc['name'] ?? '') ?></strong></span></div>
                    <div class="info-row"><span class="info-label">Scheduled Date & Time</span><span class="info-value"><?= htmlspecialchars(($proc['scheduled_date'] ?? 'N/A') . ' ' . ($proc['scheduled_time'] ?? '')) ?></span></div>
                    <div class="info-row"><span class="info-label">Cost</span><span class="info-value"><?= htmlspecialchars($proc['cost'] ?: 'N/A') ?></span></div>
                </div>
                <?php if (!empty($proc['notes'])): ?>
                <div class="info-row mt-2"><span class="info-label">Plan notes</span><span class="info-value"><?= nl2br(htmlspecialchars($proc['notes'])) ?></span></div>
                <?php endif; ?>
                <?php if ($executionUi): ?>
                <div style="margin-top:12px; border-top:1px dashed var(--color-border); padding-top:12px;">
                    <label class="form-check"><input type="checkbox" name="proc_complete[<?= (int) $i ?>]" value="1" required> Procedure completed</label>
                    <div class="form-group mt-2">
                        <label>Procedure notes</label>
                        <textarea name="proc_notes[<?= (int) $i ?>]" rows="2" class="form-control" placeholder="Completion details, specimens, etc."></textarea>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($data['monitoring_instructions'])): ?>
        <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-notepad'></i> Monitoring Instructions</h3>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fffafa;">
            <p><?= nl2br(htmlspecialchars($data['monitoring_instructions'])) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($executionUi): ?>
      <div class="action-bar mt-4 hide-on-print">
        <a href="index.php" class="btn btn-outline">Cancel</a>
        <button type="submit" name="complete_administration" class="btn btn-primary" onclick="return confirm('Complete administration and send this patient to recovery monitoring? You cannot skip monitoring.');"><i class='bx bx-pulse'></i> Complete administration & enter monitoring</button>
      </div>
      <?php endif; ?>

  <?php if ($executionUi): ?></form><?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
