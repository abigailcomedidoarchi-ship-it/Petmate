<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('csr');

if (!isset($_GET['id'])) {
    header('Location: /Petmate/dashboards/csr.php');
    exit;
}

$record_id = $_GET['id'];

// Fetch the record details
$stmt = $pdo->prepare("SELECT pr.*, 
                              p.name as pet_name, p.species, p.breed, p.color, p.sex, p.age, p.weight, p.is_neutered,
                              p.current_medications, p.vaccine_distemper_date, p.vaccine_parvo_date, p.vaccine_rabies_date,
                              p.prior_surgeries, p.prior_illnesses,
                              u.name as owner_name, u.contact, u.email, u.address, u.city, u.zip
                       FROM pet_records pr 
                       JOIN pets p ON pr.pet_id = p.id 
                       JOIN users u ON p.owner_id = u.id 
                       WHERE pr.id = ?");
$stmt->execute([$record_id]);
$record = $stmt->fetch();

if (!$record) {
    header('Location: /Petmate/dashboards/csr.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['validation_action'];
    $remarks = $_POST['remarks'] ?? '';

    if ($action === 'accept') {
        $stmtUpdate = $pdo->prepare("UPDATE pet_records SET status = 'validated', remarks = NULL WHERE id = ?");
        $stmtUpdate->execute([$record_id]);
        header("Location: /Petmate/dashboards/csr.php?msg=" . urlencode("Record validated successfully."));
        exit;
    } elseif ($action === 'reject') {
        $stmtUpdate = $pdo->prepare("UPDATE pet_records SET status = 'rejected', remarks = ? WHERE id = ?");
        $stmtUpdate->execute([$remarks, $record_id]);
        header("Location: /Petmate/dashboards/csr.php?msg=" . urlencode("Record rejected and returned to owner."));
        exit;
    }
}

require_once '../includes/header.php';
apply_dlp_protection();
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">Review Patient Record</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Review</p>
  </div>
  <a href="/Petmate/dashboards/csr.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
</div>

<div class="grid grid-2">
  <!-- Owner Details -->
  <div class="card">
    <div class="section-title">Owner Details</div>
    <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($record['owner_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?= htmlspecialchars($record['contact']) ?></span></div>
    <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($record['email']) ?></span></div>
    <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($record['address']) ?>, <?= htmlspecialchars($record['city']) ?>, <?= htmlspecialchars($record['zip']) ?></span></div>
  </div>

  <!-- Visit Information -->
  <div class="card">
    <div class="section-title">Visit Information</div>
    <div class="info-row"><span class="info-label">Visit Date</span><span class="info-value"><?= date('M d, Y', strtotime($record['visit_date'])) ?></span></div>
    <div class="info-row"><span class="info-label">Primary Reason</span><span class="info-value"><?= htmlspecialchars($record['primary_reason']) ?></span></div>
    <div class="info-row"><span class="info-label">Symptoms</span><span class="info-value"><?= htmlspecialchars($record['symptoms'] ?: 'None specified') ?></span></div>
  </div>

  <!-- Pet Health History -->
  <div class="card col-span-2">
    <div class="section-title">Pet Health History</div>
    <div class="grid grid-3" style="margin-bottom:16px;">
      <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><?= htmlspecialchars($record['pet_name']) ?></span></div>
      <div class="info-row"><span class="info-label">Species</span><span class="info-value"><?= htmlspecialchars($record['species']) ?></span></div>
      <div class="info-row"><span class="info-label">Breed</span><span class="info-value"><?= htmlspecialchars($record['breed']) ?></span></div>
      <div class="info-row"><span class="info-label">Color</span><span class="info-value"><?= htmlspecialchars($record['color']) ?></span></div>
      <div class="info-row"><span class="info-label">Age</span><span class="info-value"><?= htmlspecialchars($record['age']) ?></span></div>
      <div class="info-row"><span class="info-label">Weight</span><span class="info-value"><?= htmlspecialchars($record['weight']) ?> kg</span></div>
      <div class="info-row"><span class="info-label">Sex</span><span class="info-value"><?= $record['sex'] === 'M' ? 'Male' : 'Female' ?></span></div>
      <div class="info-row"><span class="info-label">Neutered/Spayed</span><span class="info-value"><?= $record['is_neutered'] ? 'Yes' : 'No' ?></span></div>
    </div>
    <div class="section-break"></div>
    <div class="info-row"><span class="info-label">Current Medications</span><span class="info-value"><?= htmlspecialchars($record['current_medications'] ?: 'None') ?></span></div>
    <div class="info-row"><span class="info-label">Prior Surgeries</span><span class="info-value"><?= htmlspecialchars($record['prior_surgeries'] ?: 'None') ?></span></div>
    <div class="info-row"><span class="info-label">Prior Illnesses</span><span class="info-value"><?= htmlspecialchars($record['prior_illnesses'] ?: 'None') ?></span></div>
    <div class="section-break"></div>
    <div class="section-title" style="margin-top:0;">Vaccinations</div>
    <div class="grid grid-3">
      <div class="info-row"><span class="info-label">Distemper</span><span class="info-value"><?= $record['vaccine_distemper_date'] ? date('M d, Y', strtotime($record['vaccine_distemper_date'])) : 'Not recorded' ?></span></div>
      <div class="info-row"><span class="info-label">Parvovirus</span><span class="info-value"><?= $record['vaccine_parvo_date'] ? date('M d, Y', strtotime($record['vaccine_parvo_date'])) : 'Not recorded' ?></span></div>
      <div class="info-row"><span class="info-label">Rabies</span><span class="info-value"><?= $record['vaccine_rabies_date'] ? date('M d, Y', strtotime($record['vaccine_rabies_date'])) : 'Not recorded' ?></span></div>
    </div>
  </div>
</div>

<?php if ($record['status'] === 'pending'): ?>
<div class="card" style="border-left: 4px solid var(--color-accent);">
  <div class="card-header">
    <h2>Validation Action</h2>
  </div>
  <form method="POST" action="">
    <div class="form-group" style="max-width: 420px;">
      <label>Decision *</label>
      <select name="validation_action" id="validation_action" required
        onchange="document.getElementById('remarks_group').style.display = this.value === 'reject' ? 'block' : 'none'; document.getElementById('remarks').required = this.value === 'reject';">
        <option value="">— Select Decision —</option>
        <option value="accept">Accept and Send to Vet Assistant</option>
        <option value="reject">Reject and Return to Owner</option>
      </select>
    </div>
    <div class="form-group" id="remarks_group" style="display:none; max-width: 600px;">
      <label>Reason for Rejection *</label>
      <input type="text" name="remarks" id="remarks" placeholder="Explain what needs to be fixed…">
    </div>
    <div class="mt-4">
      <button type="submit" class="btn btn-primary"><i class='bx bx-check'></i> Submit Validation</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="card">
  <div class="info-row">
    <span class="info-label">Status</span>
    <span class="info-value"><span class="badge badge-<?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span></span>
  </div>
  <?php if ($record['remarks']): ?>
    <div class="alert alert-error mt-3"><strong>Remarks:</strong> <?= htmlspecialchars($record['remarks']) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
