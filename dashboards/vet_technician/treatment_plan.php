<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$error = '';
$success = '';

$existing_plan_data = null;
$plan_id = 0;

if ($edit_id > 0) {
    $stmt = $pdo->prepare("
        SELECT tp.*, p.name AS pet_name, p.species, p.breed, u.name AS owner_name
        FROM treatment_plans tp
        JOIN pets p ON p.id = tp.pet_id
        LEFT JOIN users u ON u.id = p.owner_id
        WHERE tp.id = ?
    ");
    $stmt->execute([$edit_id]);
    $tp_row = $stmt->fetch();
    if ($tp_row) {
        $existing_plan_data = json_decode($tp_row['prescriptions'], true);
        $assessment_id = (int)($existing_plan_data['assessment_id'] ?? 0);
        $plan_id = $edit_id;
    } else {
        $error = "Treatment plan not found.";
    }
}

$assessment = null;
if ($assessment_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id AS assessment_id, a.pet_id, a.assessment_session_id, a.test_type, a.result,
               p.name AS pet_name, p.species, p.breed,
               o.name AS owner_name
        FROM assessments a
        JOIN pets p ON p.id = a.pet_id
        LEFT JOIN users o ON o.id = p.owner_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch();
}

if (!$assessment && !$error) {
    $error = 'Completed assessment not found. Open this page from Assessments > Treatment Plan button.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    // Process Medications
    $prescriptions_to_save = [];
    $med_names = $_POST['medicine_name'] ?? [];
    $med_prices = $_POST['medicine_price'] ?? [];
    $med_dosages = $_POST['dosage'] ?? [];
    $med_freqs = $_POST['frequency'] ?? [];
    $med_durs = $_POST['duration'] ?? [];
    $med_notes = $_POST['notes'] ?? [];

    foreach ($med_names as $i => $name) {
        $name = trim($name);
        if ($name === '') continue;
        $prescriptions_to_save[] = [
            'medicine_name' => $name,
            'medicine_price' => trim($med_prices[$i] ?? ''),
            'dosage' => trim($med_dosages[$i] ?? ''),
            'frequency' => trim($med_freqs[$i] ?? ''),
            'duration' => trim($med_durs[$i] ?? ''),
            'time_schedule' => [
                'am' => isset($_POST['time_am'][$i]),
                'pm' => isset($_POST['time_pm'][$i])
            ],
            'notes' => trim($med_notes[$i] ?? '')
        ];
    }

    // Process Surgeries
    $surgeries_to_save = [];
    $surg_names = $_POST['surgery_name'] ?? [];
    $surg_dates = $_POST['surgery_date'] ?? [];
    $surg_costs = $_POST['surgery_cost'] ?? [];
    $surg_statuses = $_POST['surgery_status'] ?? [];

    foreach ($surg_names as $i => $name) {
        $name = trim($name);
        if ($name === '') continue;
        $surgeries_to_save[] = [
            'name' => $name,
            'date' => trim($surg_dates[$i] ?? ''),
            'cost' => trim($surg_costs[$i] ?? ''),
            'status' => trim($surg_statuses[$i] ?? 'Scheduled')
        ];
    }

    // Process Procedures
    $procedures_to_save = [];
    $proc_names = $_POST['procedure_name'] ?? [];
    $proc_notes = $_POST['procedure_notes'] ?? [];
    $proc_costs = $_POST['procedure_cost'] ?? [];

    foreach ($proc_names as $i => $name) {
        $name = trim($name);
        if ($name === '') continue;
        $procedures_to_save[] = [
            'name' => $name,
            'notes' => trim($proc_notes[$i] ?? ''),
            'cost' => trim($proc_costs[$i] ?? '')
        ];
    }

    $full_record = [
        'assessment_id' => (int)$assessment['assessment_id'],
        'pet_name' => $assessment['pet_name'],
        'owner_name' => $assessment['owner_name'] ?? '',
        'species_breed' => trim(($assessment['species'] ?? '') . ' / ' . ($assessment['breed'] ?? '')),
        'created_at' => date('Y-m-d H:i:s'),
        'treatment_notes' => trim($_POST['treatment_notes'] ?? ''),
        'medicines' => $prescriptions_to_save,
        'surgeries' => $surgeries_to_save,
        'procedures' => $procedures_to_save
    ];

    $description_parts = [];
    if (!empty($prescriptions_to_save)) $description_parts[] = count($prescriptions_to_save) . ' Meds';
    if (!empty($surgeries_to_save)) $description_parts[] = count($surgeries_to_save) . ' Surgeries';
    if (!empty($procedures_to_save)) $description_parts[] = count($procedures_to_save) . ' Procs';
    $desc_str = empty($description_parts) ? 'General Plan' : implode(', ', $description_parts);
    $description = "Treatment plan for {$assessment['pet_name']} | {$desc_str}";

    if ($plan_id > 0) {
        // Update existing plan
        $update = $pdo->prepare("
            UPDATE treatment_plans 
            SET description = ?, prescriptions = ?, consent_status = 'not_submitted', consent_note = NULL, workflow_status = 'draft', date = NOW()
            WHERE id = ?
        ");
        $update->execute([
            $description,
            json_encode($full_record),
            $plan_id
        ]);
        $success = 'Treatment plan updated successfully. <a href="treatment_details.php" style="text-decoration:underline; font-weight:bold;">View Treatment Details to submit to owner</a>.';
    } else {
        // Insert new plan
        $insert = $pdo->prepare("
            INSERT INTO treatment_plans (pet_id, vet_id, description, prescriptions, workflow_status, date)
            VALUES (?, ?, ?, ?, 'draft', NOW())
        ");
        $insert->execute([
            (int)$assessment['pet_id'],
            (int)$_SESSION['user_id'],
            $description,
            json_encode($full_record)
        ]);
        $new_plan_id = $pdo->lastInsertId();
        $success = 'Treatment plan saved successfully. <a href="treatment_details.php" style="text-decoration:underline; font-weight:bold;">View Treatment Details to submit to owner</a>.';
    }
    
    // Refresh existing plan data to re-populate the form with new saved data
    $existing_plan_data = $full_record;
}

$current_page = 'treatment_plan'; // though removed from nav, still good to set
require_once '../../includes/header.php';

// Prepare data for rendering
$meds = $existing_plan_data['medicines'] ?? [[]];
if (empty($meds)) $meds = [[]];

$surgs = $existing_plan_data['surgeries'] ?? [[]];
if (empty($surgs)) $surgs = [[]];

$procs = $existing_plan_data['procedures'] ?? [[]];
if (empty($procs)) $procs = [[]];
$treatment_notes = $existing_plan_data['treatment_notes'] ?? '';
?>
<style>
  .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--color-border); flex-wrap: wrap; }
  .tab-btn { background: none; border: none; padding: 12px 24px; font-weight: 600; color: var(--color-muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
  .tab-btn:hover { color: var(--color-text); }
  .tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  .dynamic-block { border: 1px solid var(--color-border); padding: 16px; border-radius: 8px; margin-bottom: 16px; background: #fff; position: relative; }
  .remove-btn-container { text-align: right; margin-top: 8px; }
</style>

<div class="action-bar">
  <div>
    <h1 class="page-heading"><?= $plan_id > 0 ? "Edit Treatment Plan #$plan_id" : "Create Treatment Plan" ?></h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> <?= $plan_id > 0 ? "Edit" : "Create" ?> Treatment Plan</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= $success ?></div>
<?php endif; ?>

<?php if ($assessment): ?>
  <?php if($plan_id > 0 && !empty($tp_row['consent_note'])): ?>
    <div class="alert alert-error mb-4">
        <strong><i class='bx bx-message-square-error'></i> Owner's Reason for Declining:</strong> 
        <?= htmlspecialchars($tp_row['consent_note']) ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="treatment-plan-form">
      <div class="tabs">
          <button type="button" class="tab-btn active" data-target="tab-overview">Overview</button>
          <button type="button" class="tab-btn" data-target="tab-medications">Medications</button>
          <button type="button" class="tab-btn" data-target="tab-surgery">Surgery</button>
          <button type="button" class="tab-btn" data-target="tab-procedures">Procedures</button>
      </div>

      <!-- OVERVIEW TAB -->
      <div id="tab-overview" class="tab-content active">
          <div class="card">
            <div class="card-header"><h2><i class='bx bx-user'></i> Patient Information</h2></div>
            <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><?= htmlspecialchars($assessment['pet_name']) ?></span></div>
            <div class="info-row"><span class="info-label">Owner Name</span><span class="info-value"><?= htmlspecialchars($assessment['owner_name'] ?: 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Species/Breed</span><span class="info-value"><?= htmlspecialchars(($assessment['species'] ?: '-') . ' / ' . ($assessment['breed'] ?: '-')) ?></span></div>
            <div class="info-row"><span class="info-label">Date</span><span class="info-value"><?= htmlspecialchars(date('M d, Y')) ?></span></div>
            <div class="form-group mt-4">
              <label>Treatment notes (diagnosis, clinical rationale, scheduling notes)</label>
              <textarea name="treatment_notes" rows="4" placeholder="Summarize diagnosis, goals of therapy, and any scheduling or assignment notes for the team."><?= htmlspecialchars($treatment_notes) ?></textarea>
            </div>
          </div>
      </div>

      <!-- MEDICATIONS TAB -->
      <div id="tab-medications" class="tab-content">
          <div class="card">
            <div class="card-header"><h2><i class='bx bx-capsule'></i> Medications</h2></div>
            <div id="medicines-container">
              <?php foreach($meds as $i => $med): ?>
              <div class="dynamic-block medicine-block">
                <div class="grid grid-2">
                  <div class="form-group"><label>Medicine Name</label><input type="text" name="medicine_name[]" placeholder="e.g. Amoxicillin" value="<?= htmlspecialchars($med['medicine_name'] ?? '') ?>"></div>
                  <div class="form-group"><label>Medicine Price</label><input type="text" name="medicine_price[]" placeholder="e.g. 250.00" value="<?= htmlspecialchars($med['medicine_price'] ?? '') ?>"></div>
                </div>
                <div class="grid grid-2">
                  <div class="form-group"><label>Dosage</label><input type="text" name="dosage[]" placeholder="e.g. 1 tablet" value="<?= htmlspecialchars($med['dosage'] ?? '') ?>"></div>
                  <div class="form-group"><label>Frequency</label><input type="text" name="frequency[]" placeholder="e.g. twice daily" value="<?= htmlspecialchars($med['frequency'] ?? '') ?>"></div>
                </div>
                <div class="grid grid-2">
                  <div class="form-group"><label>Duration</label><input type="text" name="duration[]" placeholder="e.g. 7 days" value="<?= htmlspecialchars($med['duration'] ?? '') ?>"></div>
                  <div class="form-group">
                    <label>Time Schedule</label>
                    <div style="display:flex; gap:16px; margin-top:6px;">
                      <label><input type="checkbox" name="time_am[<?= $i ?>]" <?= !empty($med['time_schedule']['am']) ? 'checked' : '' ?>> AM</label>
                      <label><input type="checkbox" name="time_pm[<?= $i ?>]" <?= !empty($med['time_schedule']['pm']) ? 'checked' : '' ?>> PM</label>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label>Notes</label>
                  <textarea name="notes[]" rows="2" placeholder="Example: Take after meals"><?= htmlspecialchars($med['notes'] ?? '') ?></textarea>
                </div>
                <?php if($i > 0): ?>
                  <div class="remove-btn-container"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.medicine-block').remove()"><i class="bx bx-trash"></i> Remove</button></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline mt-2" onclick="addBlock('medicine')"><i class='bx bx-plus'></i> Add Medication</button>
          </div>
      </div>

      <!-- SURGERY TAB -->
      <div id="tab-surgery" class="tab-content">
          <div class="card">
            <div class="card-header"><h2><i class='bx bx-cut'></i> Surgery / Procedure</h2></div>
            <div id="surgeries-container">
              <?php foreach($surgs as $i => $surg): ?>
              <div class="dynamic-block surgery-block">
                <div class="grid grid-2">
                  <div class="form-group"><label>Surgery Name</label><input type="text" name="surgery_name[]" placeholder="e.g. Spay / Neuter" value="<?= htmlspecialchars($surg['name'] ?? '') ?>"></div>
                  <div class="form-group"><label>Scheduled Date</label><input type="date" name="surgery_date[]" value="<?= htmlspecialchars($surg['date'] ?? '') ?>"></div>
                </div>
                <div class="grid grid-2">
                  <div class="form-group"><label>Estimated Cost</label><input type="text" name="surgery_cost[]" placeholder="0.00" value="<?= htmlspecialchars($surg['cost'] ?? '') ?>"></div>
                  <div class="form-group">
                    <label>Surgery Status</label>
                    <select name="surgery_status[]" class="form-control">
                      <option value="Scheduled" <?= ($surg['status'] ?? '') === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                      <option value="Ongoing" <?= ($surg['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                      <option value="Completed" <?= ($surg['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                      <option value="Cancelled" <?= ($surg['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                  </div>
                </div>
                <?php if($i > 0): ?>
                  <div class="remove-btn-container"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.surgery-block').remove()"><i class="bx bx-trash"></i> Remove</button></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline mt-2" onclick="addBlock('surgery')"><i class='bx bx-plus'></i> Add Surgery</button>
          </div>
      </div>

      <!-- PROCEDURES TAB -->
      <div id="tab-procedures" class="tab-content">
          <div class="card">
            <div class="card-header"><h2><i class='bx bx-pulse'></i> Procedures & Other Treatments</h2></div>
            <div id="procedures-container">
              <?php foreach($procs as $i => $proc): ?>
              <div class="dynamic-block procedure-block">
                <div class="form-group"><label>Procedure Name</label><input type="text" name="procedure_name[]" placeholder="e.g. Vaccination, Deworming" value="<?= htmlspecialchars($proc['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Notes</label><input type="text" name="procedure_notes[]" placeholder="Details..." value="<?= htmlspecialchars($proc['notes'] ?? '') ?>"></div>
                <div class="form-group"><label>Cost</label><input type="text" name="procedure_cost[]" placeholder="0.00" value="<?= htmlspecialchars($proc['cost'] ?? '') ?>"></div>
                <?php if($i > 0): ?>
                  <div class="remove-btn-container"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.procedure-block').remove()"><i class="bx bx-trash"></i> Remove</button></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline mt-2" onclick="addBlock('procedure')"><i class='bx bx-plus'></i> Add Procedure</button>
          </div>
      </div>

      <div class="action-bar mt-4">
        <a href="<?= $plan_id > 0 ? 'treatment_details.php' : '/Petmate/dashboards/vet_technician/assessments.php?view_id=' . (int)$assessment['assessment_id'] ?>" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> <?= $plan_id > 0 ? "Resubmit Treatment Plan" : "Save Treatment Plan" ?></button>
      </div>
  </form>

  <script>
    // Tab Switching Logic
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.getAttribute('data-target')).classList.add('active');
        });
    });

    // Dynamic Blocks Logic
    let indexes = { 
        medicine: <?= count($meds) ?>, 
        surgery: <?= count($surgs) ?>, 
        procedure: <?= count($procs) ?> 
    };

    function addBlock(type) {
        const container = document.getElementById(type + 's-container');
        const firstBlock = container.querySelector('.' + type + '-block');
        const clone = firstBlock.cloneNode(true);
        
        clone.querySelectorAll('input[type="text"], input[type="date"], textarea').forEach(input => input.value = '');
        clone.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
        
        if (type === 'medicine') {
            clone.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
                if (cb.name.startsWith('time_am')) cb.name = 'time_am[' + indexes.medicine + ']';
                if (cb.name.startsWith('time_pm')) cb.name = 'time_pm[' + indexes.medicine + ']';
            });
        }
        
        // Remove existing remove buttons from clone if any
        clone.querySelectorAll('.remove-btn-container').forEach(el => el.remove());

        const removeContainer = document.createElement('div');
        removeContainer.className = 'remove-btn-container';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-danger';
        removeBtn.innerHTML = '<i class="bx bx-trash"></i> Remove';
        removeBtn.onclick = function() { clone.remove(); };
        
        removeContainer.appendChild(removeBtn);
        clone.appendChild(removeContainer);
        
        container.appendChild(clone);
        indexes[type]++;
    }
  </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
