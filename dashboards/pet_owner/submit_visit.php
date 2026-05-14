<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');
require_permission('view_dashboard');

$pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!$pet_id) {
    header('Location: my_pets.php');
    exit;
}

// Fetch pet to ensure it belongs to the current user
$stmt = $pdo->prepare("SELECT * FROM pets WHERE id = ? AND owner_id = ?");
$stmt->execute([$pet_id, $user_id]);
$pet = $stmt->fetch();

if (!$pet) {
    die("Pet not found or you don't have permission to access it.");
}

// Fetch the most recent visit to prepopulate anything if necessary (though the prompt only asks for reason and symptoms)
// The user prompt: "the rest of the record (from past visit ) should be automatically updated from the past visit and the owner will only put reason for visit for convenience"
// This likely means the pet's existing static data is carried over inherently (because they are just adding a new visit to the existing pet), 
// so they don't need to refill species, breed, etc.

$symptoms_list = [
    'Appetite loss', 'Behavioral changes', 'Breathing problem', 'Coughing',
    'Depression', 'Diarrhea', 'Eye Disorders', 'Gagging', 'Gums Bleeding',
    'Limping', 'Loss of balance', 'Scooting', 'Scratching', 'Shaking Head',
    'Sneezing', 'Thirst', 'Urination Increase', 'Vomiting', 'Weakness'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
    $primary_reason = trim($_POST['primary_reason'] ?? '');
    
    $selected_symptoms = $_POST['symptoms'] ?? [];
    $other_symptoms = trim($_POST['other_symptoms'] ?? '');
    
    if (!empty($other_symptoms)) {
        $selected_symptoms[] = "Other: " . $other_symptoms;
    }
    
    $symptoms_str = implode(', ', $selected_symptoms);

    if (empty($primary_reason)) {
        $error = "Primary Reason for Visit is required.";
    } else {
        $insert = $pdo->prepare("INSERT INTO pet_records (pet_id, visit_date, primary_reason, symptoms, status) VALUES (?, ?, ?, ?, 'pending')");
        if ($insert->execute([$pet_id, $visit_date, $primary_reason, $symptoms_str])) {
            $success = "Visit submitted successfully. It is now pending review by the clinic staff.";
        } else {
            $error = "Failed to submit visit.";
        }
    }
}

$current_page = 'my_pets';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Submit Visit</h1>
    <p class="breadcrumb">PetMate <span>›</span> My Pets <span>›</span> Submit Visit</p>
  </div>
  <a href="my_pets.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back to My Pets</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success">
      <i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?>
  </div>
<?php else: ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
        <h2><i class='bx bx-calendar-plus'></i> Schedule Visit for <?= htmlspecialchars($pet['name']) ?></h2>
    </div>
    
    <div style="background: #fafafa; border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
        <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 16px; color: var(--color-text); border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
            <i class='bx bx-id-card'></i> Pet Profile (Auto-filled from previous records)
        </h3>
        
        <div class="grid grid-3 mb-4">
            <div class="info-row"><span class="info-label">Name</span><span class="info-value"><strong><?= htmlspecialchars($pet['name']) ?></strong></span></div>
            <div class="info-row"><span class="info-label">Species</span><span class="info-value"><?= htmlspecialchars($pet['species']) ?></span></div>
            <div class="info-row"><span class="info-label">Breed</span><span class="info-value"><?= htmlspecialchars($pet['breed'] ?: 'Unknown') ?></span></div>
            <div class="info-row"><span class="info-label">Color</span><span class="info-value"><?= htmlspecialchars($pet['color'] ?: 'Unknown') ?></span></div>
            <div class="info-row"><span class="info-label">Sex</span><span class="info-value"><?= htmlspecialchars($pet['sex'] === 'M' ? 'Male' : 'Female') ?></span></div>
            <div class="info-row"><span class="info-label">Neutered/Spayed?</span><span class="info-value"><?= $pet['is_neutered'] ? 'Yes' : 'No' ?></span></div>
            <div class="info-row"><span class="info-label">Age</span><span class="info-value"><?= htmlspecialchars($pet['age'] ?: 'Unknown') ?> years</span></div>
            <div class="info-row"><span class="info-label">Weight</span><span class="info-value"><?= htmlspecialchars($pet['weight'] ?: 'Unknown') ?> kg</span></div>
        </div>

        <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; color: var(--color-text);">Medical History</h3>
        <div class="grid grid-2 mb-4">
            <div class="info-row"><span class="info-label">Current Medications</span><span class="info-value"><?= nl2br(htmlspecialchars($pet['current_medications'] ?: 'None')) ?></span></div>
            <div class="info-row"><span class="info-label">Prior Illnesses</span><span class="info-value"><?= nl2br(htmlspecialchars($pet['prior_illnesses'] ?: 'None')) ?></span></div>
            <div class="info-row"><span class="info-label">Prior Surgeries</span><span class="info-value"><?= nl2br(htmlspecialchars($pet['prior_surgeries'] ?: 'None')) ?></span></div>
        </div>

        <h3 style="margin-top: 0; margin-bottom: 12px; font-size: 14px; color: var(--color-text);">Vaccinations</h3>
        <div class="grid grid-3">
            <div class="info-row"><span class="info-label">Distemper</span><span class="info-value"><?= $pet['vaccine_distemper_date'] ? date('M d, Y', strtotime($pet['vaccine_distemper_date'])) : '<span class="text-muted">None</span>' ?></span></div>
            <div class="info-row"><span class="info-label">Parvovirus</span><span class="info-value"><?= $pet['vaccine_parvo_date'] ? date('M d, Y', strtotime($pet['vaccine_parvo_date'])) : '<span class="text-muted">None</span>' ?></span></div>
            <div class="info-row"><span class="info-label">Rabies</span><span class="info-value"><?= $pet['vaccine_rabies_date'] ? date('M d, Y', strtotime($pet['vaccine_rabies_date'])) : '<span class="text-muted">None</span>' ?></span></div>
        </div>
    </div>

    <form method="POST">
        <div class="grid grid-2">
            <div class="form-group">
                <label>Date of Visit *</label>
                <input type="date" name="visit_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Primary Reason for Visit *</label>
                <input type="text" name="primary_reason" placeholder="e.g., Annual checkup, Lethargy, Vaccinations" required>
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label>Symptoms (check all that apply)</label>
            <div class="grid grid-4" style="gap: 12px; margin-top: 8px;">
                <?php foreach ($symptoms_list as $symptom): ?>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                        <input type="checkbox" name="symptoms[]" value="<?= htmlspecialchars($symptom) ?>">
                        <?= htmlspecialchars($symptom) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group mt-4">
            <label>Other Symptoms (specify)</label>
            <input type="text" name="other_symptoms" placeholder="Type any other symptoms not listed above...">
        </div>

        <div class="action-bar mt-4">
            <button type="submit" class="btn btn-primary"><i class='bx bx-send'></i> Submit Visit Request</button>
        </div>
    </form>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
