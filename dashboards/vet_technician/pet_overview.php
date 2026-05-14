<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/rbac.php';

requireRole('vet_technician');
require_permission('manage_exam_rooms');

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$error = '';

$overview = null;
if ($room_id > 0) {
    $stmt = $pdo->prepare("
        SELECT er.*, r.room_name,
               p.*, p.name AS pet_name,
               o.name AS owner_name, o.contact, o.email, o.address,
               pr.visit_date, pr.primary_reason, pr.symptoms
        FROM examination_rooms er
        JOIN rooms r ON r.id = er.room_id
        JOIN pets p ON p.id = er.pet_id
        JOIN users o ON o.id = p.owner_id
        JOIN pet_records pr ON pr.pet_id = p.id
        WHERE er.id = ? AND er.status = 'in_use'
        ORDER BY pr.visit_date DESC
        LIMIT 1
    ");
    $stmt->execute([$room_id]);
    $overview = $stmt->fetch();
}

if (!$overview) {
    $error = 'Active in-use room record not found.';
}

$current_page = 'exam_rooms';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Pet Overview</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Pet Overview</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php else: ?>
  <div class="card">
    <div class="card-header"><h2><i class='bx bx-clinic'></i> Room &amp; Visit Info</h2></div>
    <div class="info-row"><span class="info-label">Room</span><span class="info-value"><?= htmlspecialchars($overview['room_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Visit Date</span><span class="info-value"><?= htmlspecialchars(date('M d, Y', strtotime($overview['visit_date']))) ?></span></div>
    <div class="info-row"><span class="info-label">Primary Reason</span><span class="info-value"><?= htmlspecialchars($overview['primary_reason'] ?: 'Not recorded') ?></span></div>
    <div class="info-row">
      <span class="info-label">Symptoms</span>
      <span class="info-value">
        <?php
          $symptoms = array_filter(array_map('trim', explode(',', (string)$overview['symptoms'])));
          if (empty($symptoms)):
        ?>
          <span class="badge badge-ready">No symptoms listed</span>
        <?php else: foreach ($symptoms as $sym): ?>
          <span class="badge badge-pending"><?= htmlspecialchars($sym) ?></span>
        <?php endforeach; endif; ?>
      </span>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class='bx bx-user'></i> Owner Information</h2></div>
    <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($overview['owner_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?= htmlspecialchars($overview['contact'] ?: 'Not recorded') ?></span></div>
    <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($overview['email'] ?: 'Not recorded') ?></span></div>
    <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($overview['address'] ?: 'Not recorded') ?></span></div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class='bx bx-paw'></i> Pet Profile</h2></div>
    <div class="grid grid-2">
      <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($overview['pet_name']) ?></span></div>
      <div class="info-row"><span class="info-label">Species</span><span class="info-value"><?= htmlspecialchars($overview['species'] ?: 'Not recorded') ?></span></div>
      <div class="info-row"><span class="info-label">Breed</span><span class="info-value"><?= htmlspecialchars($overview['breed'] ?: 'Not recorded') ?></span></div>
      <div class="info-row"><span class="info-label">Color</span><span class="info-value"><?= htmlspecialchars($overview['color'] ?? 'Not recorded') ?></span></div>
      <div class="info-row"><span class="info-label">Age</span><span class="info-value"><?= htmlspecialchars((string)($overview['age'] ?? 'Not recorded')) ?></span></div>
      <div class="info-row"><span class="info-label">Weight</span><span class="info-value"><?= htmlspecialchars((string)($overview['weight'] ?? 'Not recorded')) ?></span></div>
      <div class="info-row"><span class="info-label">Sex</span><span class="info-value"><?= htmlspecialchars($overview['sex'] ?? 'Not recorded') ?></span></div>
      <div class="info-row"><span class="info-label">Neutered/Spayed</span><span class="info-value"><?= !empty($overview['is_neutered']) ? 'Yes' : 'No' ?></span></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class='bx bx-file'></i> Medical History</h2></div>
    <div class="info-row"><span class="info-label">Current Medications</span><span class="info-value"><?= htmlspecialchars($overview['current_medications'] ?? 'Not recorded') ?></span></div>
    <div class="info-row"><span class="info-label">Prior Surgeries</span><span class="info-value"><?= htmlspecialchars($overview['prior_surgeries'] ?? 'Not recorded') ?></span></div>
    <div class="info-row"><span class="info-label">Prior Illnesses</span><span class="info-value"><?= htmlspecialchars($overview['prior_illnesses'] ?? 'Not recorded') ?></span></div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class='bx bx-shield-plus'></i> Vaccination Records</h2></div>
    <div class="grid grid-3">
      <div class="info-row">
        <span class="info-label">Distemper</span>
        <span class="info-value">
          <?= !empty($overview['vaccine_distemper_date']) ? htmlspecialchars(date('M d, Y', strtotime($overview['vaccine_distemper_date']))) : '<span class="badge badge-ready">Not recorded</span>' ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Parvovirus</span>
        <span class="info-value">
          <?= !empty($overview['vaccine_parvo_date']) ? htmlspecialchars(date('M d, Y', strtotime($overview['vaccine_parvo_date']))) : '<span class="badge badge-ready">Not recorded</span>' ?>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Rabies</span>
        <span class="info-value">
          <?= !empty($overview['vaccine_rabies_date']) ? htmlspecialchars(date('M d, Y', strtotime($overview['vaccine_rabies_date']))) : '<span class="badge badge-ready">Not recorded</span>' ?>
        </span>
      </div>
    </div>
  </div>

  <div class="action-bar">
    <a href="/Petmate/dashboards/vet_technician/exam_rooms.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back to Exam Rooms</a>
    <a href="/Petmate/dashboards/vet_technician/assessment_form.php?room_id=<?= (int)$room_id ?>" class="btn btn-primary">Proceed to Assessment <i class='bx bx-right-arrow-alt'></i></a>
  </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
