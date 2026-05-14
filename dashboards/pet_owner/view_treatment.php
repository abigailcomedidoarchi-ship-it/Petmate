<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');
require_permission('view_dashboard');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: treatment_plans.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT tp.*, p.name AS pet_name, p.species, p.breed, u.name AS owner_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    WHERE tp.id = ? AND p.owner_id = ?
");
$stmt->execute([$id, $user_id]);
$plan = $stmt->fetch();

if (!$plan) {
    die("Treatment plan not found or you do not have permission to view it.");
}

$data = json_decode($plan['prescriptions'], true);

$current_page = 'treatment_plans';
require_once '../../includes/header.php';
?>

<div class="action-bar hide-on-print">
  <div>
    <h1 class="page-heading">Review Treatment Plan #<?= $id ?></h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> Treatment Plans <span>›</span> Review</p>
  </div>
  <div>
    <button type="button" class="btn btn-outline" onclick="window.print()"><i class='bx bx-printer'></i> Print</button>
    <a href="treatment_plans.php" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back to List</a>
  </div>
</div>

<div class="card printable-area">
  <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h2><i class='bx bx-file'></i> Treatment Plan Details</h2>
      <?php 
          $statusClass = '';
          switch($plan['consent_status']) {
              case 'pending': $statusClass = 'badge badge-warning'; break;
              case 'approved': $statusClass = 'badge badge-success'; break;
              case 'declined': $statusClass = 'badge badge-danger'; break;
          }
      ?>
      <span class="<?= $statusClass ?>">Consent: <?= ucfirst($plan['consent_status']) ?></span>
  </div>
  
  <div class="grid grid-2" style="margin-bottom: 24px;">
    <div>
        <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><?= htmlspecialchars($plan['pet_name']) ?></span></div>
        <div class="info-row"><span class="info-label">Species/Breed</span><span class="info-value"><?= htmlspecialchars(($plan['species'] ?: '-') . ' / ' . ($plan['breed'] ?: '-')) ?></span></div>
    </div>
    <div>
        <div class="info-row"><span class="info-label">Clinic Info</span><span class="info-value">PetMate Veterinary Clinic</span></div>
        <div class="info-row"><span class="info-label">Date Created</span><span class="info-value"><?= htmlspecialchars(date('M d, Y', strtotime($plan['date']))) ?></span></div>
    </div>
  </div>

  <?php if (!empty($data['treatment_notes'])): ?>
  <div class="card mb-4" style="margin-top: 16px;">
    <div class="card-header"><h3><i class='bx bx-notepad'></i> Treatment notes</h3></div>
    <div class="info-row"><span class="info-value"><?= nl2br(htmlspecialchars($data['treatment_notes'])) ?></span></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($data['medicines'])): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary);"><i class='bx bx-capsule'></i> Medications</h3>
    <?php foreach ($data['medicines'] as $med): ?>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
            <div class="grid grid-2">
                <div class="info-row"><span class="info-label">Name</span><span class="info-value"><strong><?= htmlspecialchars($med['medicine_name']) ?></strong></span></div>
                <div class="info-row"><span class="info-label">Price</span><span class="info-value"><?= htmlspecialchars($med['medicine_price'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Dosage</span><span class="info-value"><?= htmlspecialchars($med['dosage']) ?></span></div>
                <div class="info-row"><span class="info-label">Frequency</span><span class="info-value"><?= htmlspecialchars($med['frequency']) ?></span></div>
                <div class="info-row"><span class="info-label">Duration</span><span class="info-value"><?= htmlspecialchars($med['duration']) ?></span></div>
                <div class="info-row"><span class="info-label">Time</span><span class="info-value">
                    <?= ($med['time_schedule']['am'] ?? false) ? 'AM ' : '' ?>
                    <?= ($med['time_schedule']['pm'] ?? false) ? 'PM' : '' ?>
                </span></div>
            </div>
            <?php if (!empty($med['notes'])): ?>
            <div class="info-row mt-2"><span class="info-label">Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($med['notes'])) ?></span></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($data['surgeries'])): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-cut'></i> Surgery / Procedures</h3>
    <?php foreach ($data['surgeries'] as $surg): ?>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
            <div class="grid grid-2">
                <div class="info-row"><span class="info-label">Surgery Name</span><span class="info-value"><strong><?= htmlspecialchars($surg['name']) ?></strong></span></div>
                <div class="info-row"><span class="info-label">Scheduled Date</span><span class="info-value"><?= htmlspecialchars($surg['date'] ?: 'N/A') ?></span></div>
                <div class="info-row"><span class="info-label">Est. Cost</span><span class="info-value"><?= htmlspecialchars($surg['cost'] ?: 'N/A') ?></span></div>
            </div>
            <div class="info-row mt-2"><span class="info-label">Status</span><span class="info-value badge badge-outline"><?= htmlspecialchars($surg['status'] ?? 'N/A') ?></span></div>
        </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($data['procedures'])): ?>
    <h3 style="border-bottom: 1px solid var(--color-border); padding-bottom: 8px; margin-bottom: 16px; margin-top: 24px; color: var(--color-primary);"><i class='bx bx-pulse'></i> Other Treatments</h3>
    <?php foreach ($data['procedures'] as $proc): ?>
        <div style="border: 1px solid var(--color-border); padding: 12px; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
            <div class="grid grid-2">
                <div class="info-row"><span class="info-label">Procedure Name</span><span class="info-value"><strong><?= htmlspecialchars($proc['name']) ?></strong></span></div>
                <div class="info-row"><span class="info-label">Cost</span><span class="info-value"><?= htmlspecialchars($proc['cost'] ?: 'N/A') ?></span></div>
            </div>
            <?php if (!empty($proc['notes'])): ?>
            <div class="info-row mt-2"><span class="info-label">Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($proc['notes'])) ?></span></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($plan['consent_status'] === 'pending'): ?>
  <div class="hide-on-print" style="margin-top: 32px; text-align: center; background: #fffafa; border: 1px solid var(--color-danger); padding: 24px; border-radius: 8px;">
      <h3 style="color: var(--color-danger); margin-bottom: 16px;">This Treatment Plan Requires Your Consent</h3>
      <p style="margin-bottom: 24px;">Please review the details carefully. If you agree to proceed, click Approve.</p>
      <form id="approve-form" method="POST" action="treatment_plans.php" style="display:inline;">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
          <button type="button" class="btn btn-success" onclick="if(confirm('Are you sure you want to approve this treatment?')) document.getElementById('approve-form').submit();">
              <i class='bx bx-check'></i> I Consent and Approve
          </button>
      </form>
  </div>
  <?php endif; ?>

</div>

<style>
  @media print {
      body * { visibility: hidden; }
      .printable-area, .printable-area * { visibility: visible; }
      .printable-area { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
      .hide-on-print { display: none !important; }
      .sidebar, .topbar { display: none !important; }
      .main-content { margin-left: 0 !important; padding: 0 !important; }
  }
</style>

<?php require_once '../../includes/footer.php'; ?>
