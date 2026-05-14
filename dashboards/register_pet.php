<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('pet_owner');
$user_id = $_SESSION['user_id'];

// Fetch user info to pre-fill if possible
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$edit_id = $_GET['edit_id'] ?? null;
$edit_record = null;
$edit_pet = null;

if ($edit_id) {
    $stmt = $pdo->prepare("SELECT pr.* FROM pet_records pr JOIN pets p ON pr.pet_id = p.id WHERE pr.id = ? AND p.owner_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $edit_record = $stmt->fetch();
    if ($edit_record) {
        $stmtPet = $pdo->prepare("SELECT * FROM pets WHERE id = ?");
        $stmtPet->execute([$edit_record['pet_id']]);
        $edit_pet = $stmtPet->fetch();
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Update User info
        $stmtUser = $pdo->prepare("UPDATE users SET 
            name=?, contact=?, address=?, city=?, zip=?, birthdate=?, employer=?, number_of_pets=?, pet_types=? 
            WHERE id=?");
        $stmtUser->execute([
            $_POST['owner_name'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['city'],
            $_POST['zip'],
            $_POST['birthdate'] ?: null,
            $_POST['employer'],
            $_POST['number_of_pets'] ?: null,
            $_POST['pet_types'],
            $user_id
        ]);

        $is_neutered = ($_POST['is_neutered'] ?? '') === 'yes' ? 1 : 0;

        if ($edit_id && $edit_record && $edit_pet) {
            // Update Pet info
            $stmtPet = $pdo->prepare("UPDATE pets SET 
                name=?, species=?, breed=?, color=?, sex=?, age=?, weight=?, is_neutered=?, 
                current_medications=?, vaccine_distemper_date=?, vaccine_parvo_date=?, vaccine_rabies_date=?, 
                prior_surgeries=?, prior_illnesses=? WHERE id=?");
            
            $stmtPet->execute([
                $_POST['pet_name'],
                $_POST['pet_type'],
                $_POST['breed'],
                $_POST['color'],
                $_POST['sex'] ?? 'M',
                $_POST['age'] ?: null,
                $_POST['weight'] ?: null,
                $is_neutered,
                $_POST['current_medications'],
                $_POST['vaccine_distemper_date'] ?: null,
                $_POST['vaccine_parvo_date'] ?: null,
                $_POST['vaccine_rabies_date'] ?: null,
                $_POST['prior_surgeries'],
                $_POST['prior_illnesses'],
                $edit_pet['id']
            ]);
            
            // Update initial pet_record
            $symptoms = isset($_POST['symptoms']) ? implode(', ', $_POST['symptoms']) : '';
            if (!empty($_POST['other_symptoms'])) {
                $symptoms .= ($symptoms ? ', ' : '') . 'Other: ' . $_POST['other_symptoms'];
            }

            $stmtRecord = $pdo->prepare("UPDATE pet_records SET 
                visit_date=?, primary_reason=?, symptoms=?, status='pending', remarks=NULL WHERE id=?");
            
            $stmtRecord->execute([
                $_POST['visit_date'],
                $_POST['primary_reason'],
                $symptoms,
                $edit_id
            ]);
            
            $success = "Pet registration and visit details resubmitted successfully!";
            
            // Update variables so the form reflects the saved changes
            $edit_pet = array_merge($edit_pet, $_POST, ['species' => $_POST['pet_type'], 'name' => $_POST['pet_name']]);
            $edit_record = array_merge($edit_record, $_POST, ['symptoms' => $symptoms]);

        } else {
            // Check if pet already exists for this owner
            $stmtCheck = $pdo->prepare("SELECT id FROM pets WHERE owner_id = ? AND LOWER(name) = LOWER(?)");
            $stmtCheck->execute([$user_id, trim($_POST['pet_name'])]);
            $existing_pet = $stmtCheck->fetch();

            if ($existing_pet) {
                // Update existing pet
                $stmtPet = $pdo->prepare("UPDATE pets SET 
                    species=?, breed=?, color=?, sex=?, age=?, weight=?, is_neutered=?, 
                    current_medications=?, vaccine_distemper_date=?, vaccine_parvo_date=?, vaccine_rabies_date=?, 
                    prior_surgeries=?, prior_illnesses=? WHERE id=?");
                
                $stmtPet->execute([
                    $_POST['pet_type'],
                    $_POST['breed'],
                    $_POST['color'],
                    $_POST['sex'] ?? 'M',
                    $_POST['age'] ?: null,
                    $_POST['weight'] ?: null,
                    $is_neutered,
                    $_POST['current_medications'],
                    $_POST['vaccine_distemper_date'] ?: null,
                    $_POST['vaccine_parvo_date'] ?: null,
                    $_POST['vaccine_rabies_date'] ?: null,
                    $_POST['prior_surgeries'],
                    $_POST['prior_illnesses'],
                    $existing_pet['id']
                ]);
                $pet_id = $existing_pet['id'];
            } else {
                // 2. Insert Pet info
                $stmtPet = $pdo->prepare("INSERT INTO pets (
                    owner_id, name, species, breed, color, sex, age, weight, is_neutered, 
                    current_medications, vaccine_distemper_date, vaccine_parvo_date, vaccine_rabies_date, 
                    prior_surgeries, prior_illnesses
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmtPet->execute([
                    $user_id,
                    trim($_POST['pet_name']),
                    $_POST['pet_type'],
                    $_POST['breed'],
                    $_POST['color'],
                    $_POST['sex'] ?? 'M',
                    $_POST['age'] ?: null,
                    $_POST['weight'] ?: null,
                    $is_neutered,
                    $_POST['current_medications'],
                    $_POST['vaccine_distemper_date'] ?: null,
                    $_POST['vaccine_parvo_date'] ?: null,
                    $_POST['vaccine_rabies_date'] ?: null,
                    $_POST['prior_surgeries'],
                    $_POST['prior_illnesses']
                ]);
                
                $pet_id = $pdo->lastInsertId();
            }

            // 3. Create initial pet_record (Visit intent)
            $symptoms = isset($_POST['symptoms']) ? implode(', ', $_POST['symptoms']) : '';
            if (!empty($_POST['other_symptoms'])) {
                $symptoms .= ($symptoms ? ', ' : '') . 'Other: ' . $_POST['other_symptoms'];
            }

            $stmtRecord = $pdo->prepare("INSERT INTO pet_records (
                pet_id, visit_date, primary_reason, symptoms, status
            ) VALUES (?, ?, ?, ?, 'pending')");
            
            $stmtRecord->execute([
                $pet_id,
                $_POST['visit_date'],
                $_POST['primary_reason'],
                $symptoms
            ]);

            $success = "Pet registration and visit details submitted successfully!";
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving data: " . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading"><?= $edit_id ? 'Edit Pet Registration' : 'Register New Pet & Visit' ?></h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> <?= $edit_id ? 'Edit' : 'Register' ?></p>
  </div>
  <a href="/Petmate/dashboards/pet_owner.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($edit_record && $edit_record['remarks']): ?>
  <div class="alert alert-error">
    <strong><i class='bx bx-error-circle'></i> Reason for Rejection:</strong> <?= htmlspecialchars($edit_record['remarks']) ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="">

    <!-- Section 1: Owner Information -->
    <div class="section-title">Owner Information</div>
    <div class="grid grid-2">
      <div class="form-group">
        <label>Owner's Name *</label>
        <input type="text" name="owner_name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
        <small class="text-muted">Email cannot be changed.</small>
      </div>
      <div class="form-group">
        <label>Address *</label>
        <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>City *</label>
        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Zip Code *</label>
        <input type="text" name="zip" value="<?= htmlspecialchars($user['zip'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Phone *</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($user['contact'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Birthdate</label>
        <input type="date" name="birthdate" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Employer (Optional)</label>
        <input type="text" name="employer" value="<?= htmlspecialchars($user['employer'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Number of Pets</label>
        <input type="number" name="number_of_pets" value="<?= htmlspecialchars($user['number_of_pets'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Pet Types (e.g. 2 dogs, 1 cat)</label>
        <input type="text" name="pet_types" value="<?= htmlspecialchars($user['pet_types'] ?? '') ?>">
      </div>
    </div>

    <div class="section-break"></div>

    <!-- Section 2: Pet Health History -->
    <div class="section-title">Pet Health History</div>
    <div class="grid grid-2">
      <div class="form-group">
        <label>Pet's Name *</label>
        <input type="text" name="pet_name" value="<?= htmlspecialchars($edit_pet['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Type (e.g. Dog, Cat) *</label>
        <input type="text" name="pet_type" value="<?= htmlspecialchars($edit_pet['species'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Breed</label>
        <input type="text" name="breed" value="<?= htmlspecialchars($edit_pet['breed'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Color</label>
        <input type="text" name="color" value="<?= htmlspecialchars($edit_pet['color'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Age</label>
        <input type="number" name="age" step="0.1" value="<?= htmlspecialchars($edit_pet['age'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Weight (kg)</label>
        <input type="number" name="weight" step="0.01" value="<?= htmlspecialchars($edit_pet['weight'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Sex *</label>
        <div class="flex gap-3" style="padding: 6px 0;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
            <input type="radio" name="sex" value="M" <?= ($edit_pet['sex'] ?? 'M') === 'M' ? 'checked' : '' ?>> Male
          </label>
          <label style="display:inline-flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
            <input type="radio" name="sex" value="F" <?= ($edit_pet['sex'] ?? '') === 'F' ? 'checked' : '' ?>> Female
          </label>
        </div>
      </div>
      <div class="form-group">
        <label>Neutered/Spayed? *</label>
        <div class="flex gap-3" style="padding: 6px 0;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
            <input type="radio" name="is_neutered" value="yes" <?= ($edit_pet['is_neutered'] ?? 1) ? 'checked' : '' ?>> Yes
          </label>
          <label style="display:inline-flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
            <input type="radio" name="is_neutered" value="no" <?= !($edit_pet['is_neutered'] ?? 1) ? 'checked' : '' ?>> No
          </label>
        </div>
      </div>
      <div class="form-group col-span-2">
        <label>Current Medications</label>
        <textarea name="current_medications"><?= htmlspecialchars($edit_pet['current_medications'] ?? '') ?></textarea>
      </div>
      <div class="form-group col-span-2">
        <label>Prior Surgeries</label>
        <textarea name="prior_surgeries"><?= htmlspecialchars($edit_pet['prior_surgeries'] ?? '') ?></textarea>
      </div>
      <div class="form-group col-span-2">
        <label>Prior Illnesses</label>
        <textarea name="prior_illnesses"><?= htmlspecialchars($edit_pet['prior_illnesses'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="section-break"></div>

    <!-- Section 3: Vaccination History -->
    <div class="section-title">Vaccination History</div>
    <div class="grid grid-3">
      <div class="form-group">
        <label class="checklist">
          <input type="checkbox" <?= !empty($edit_pet['vaccine_distemper_date']) ? 'checked' : '' ?>
            onchange="document.getElementById('div_distemper').style.display = this.checked ? 'block' : 'none'; if(!this.checked) document.getElementById('vaccine_distemper_date').value = '';">
          Distemper
        </label>
        <div id="div_distemper" style="display:<?= !empty($edit_pet['vaccine_distemper_date']) ? 'block' : 'none' ?>; margin-top:8px;">
          <label style="font-size:12px; color:var(--color-muted);">Date Given</label>
          <input type="date" id="vaccine_distemper_date" name="vaccine_distemper_date" value="<?= htmlspecialchars($edit_pet['vaccine_distemper_date'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="checklist">
          <input type="checkbox" <?= !empty($edit_pet['vaccine_parvo_date']) ? 'checked' : '' ?>
            onchange="document.getElementById('div_parvo').style.display = this.checked ? 'block' : 'none'; if(!this.checked) document.getElementById('vaccine_parvo_date').value = '';">
          Parvovirus
        </label>
        <div id="div_parvo" style="display:<?= !empty($edit_pet['vaccine_parvo_date']) ? 'block' : 'none' ?>; margin-top:8px;">
          <label style="font-size:12px; color:var(--color-muted);">Date Given</label>
          <input type="date" id="vaccine_parvo_date" name="vaccine_parvo_date" value="<?= htmlspecialchars($edit_pet['vaccine_parvo_date'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="checklist">
          <input type="checkbox" <?= !empty($edit_pet['vaccine_rabies_date']) ? 'checked' : '' ?>
            onchange="document.getElementById('div_rabies').style.display = this.checked ? 'block' : 'none'; if(!this.checked) document.getElementById('vaccine_rabies_date').value = '';">
          Rabies
        </label>
        <div id="div_rabies" style="display:<?= !empty($edit_pet['vaccine_rabies_date']) ? 'block' : 'none' ?>; margin-top:8px;">
          <label style="font-size:12px; color:var(--color-muted);">Date Given</label>
          <input type="date" id="vaccine_rabies_date" name="vaccine_rabies_date" value="<?= htmlspecialchars($edit_pet['vaccine_rabies_date'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="section-break"></div>

    <!-- Section 4: Visit Details -->
    <div class="section-title">Reason for Visit</div>

    <div class="form-group" style="max-width:280px;">
      <label>Date of Visit *</label>
      <input type="date" name="visit_date" required value="<?= htmlspecialchars($edit_record['visit_date'] ?? date('Y-m-d')) ?>">
    </div>

    <div class="form-group">
      <label>Primary Reason for Visit *</label>
      <textarea name="primary_reason" required><?= htmlspecialchars($edit_record['primary_reason'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>Symptoms (check all that apply)</label>
      <div class="grid grid-3" style="margin-top:8px;">
        <?php
          $symptomsList = [
            'Appetite loss', 'Behavioral changes', 'Breathing problem',
            'Coughing', 'Depression', 'Diarrhea',
            'Eye Disorders', 'Gagging', 'Gums Bleeding',
            'Limping', 'Loss of balance', 'Scooting',
            'Scratching', 'Shaking Head', 'Sneezing',
            'Thirst', 'Urination Increase', 'Vomiting', 'Weakness'
          ];
          $savedSymptoms = $edit_record ? explode(', ', $edit_record['symptoms']) : [];
          foreach ($symptomsList as $sym):
            $checked = in_array($sym, $savedSymptoms) ? 'checked' : '';
        ?>
        <label style="display:flex; align-items:center; gap:6px; font-weight:400; font-size:13px; cursor:pointer;">
          <input type="checkbox" name="symptoms[]" value="<?= htmlspecialchars($sym) ?>" <?= $checked ?>>
          <?= htmlspecialchars($sym) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php
      $other_symptom = '';
      foreach ($savedSymptoms as $sym) {
        if (str_starts_with($sym, 'Other: ')) $other_symptom = substr($sym, 7);
      }
    ?>
    <div class="form-group" style="max-width:480px;">
      <label>Other Symptoms (specify)</label>
      <input type="text" name="other_symptoms" value="<?= htmlspecialchars($other_symptom) ?>">
    </div>

    <div class="mt-6">
      <button type="submit" class="btn btn-primary">
        <i class='bx bx-save'></i>
        <?= $edit_id ? 'Resubmit Information' : 'Submit Registration & Visit Info' ?>
      </button>
    </div>

  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
