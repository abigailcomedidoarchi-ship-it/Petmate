<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('pet_owner');
require_permission('view_dashboard');
$user_id = $_SESSION['user_id'];

// Fetch pets
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE owner_id = ?");
$stmt->execute([$user_id]);
$total_pets = $stmt->fetchColumn();

// Fetch unpaid bills
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE owner_id = ? AND status = 'unpaid'");
$stmt->execute([$user_id]);
$unpaid_bills = $stmt->fetchColumn();

// Fetch recent visits
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pet_records pr JOIN pets p ON pr.pet_id = p.id WHERE p.owner_id = ?");
$stmt->execute([$user_id]);
$recent_visits = $stmt->fetchColumn();

// Unread messages
$unread_messages = 0;

// Fetch rejected records
$stmt = $pdo->prepare("SELECT pr.*, p.name as pet_name
                       FROM pet_records pr
                       JOIN pets p ON pr.pet_id = p.id
                       WHERE p.owner_id = ? AND pr.status = 'rejected'");
$stmt->execute([$user_id]);
$rejected_records = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT tn.id, tp.date, p.name as pet_name FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE tn.role = 'pet_owner' AND tn.type IN ('ready_for_pickup', 'payment_received') AND tn.is_read = 0");
$stmt->execute();
$pickup_notifications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT tn.id, tp.date, p.name as pet_name, tp.id AS plan_id FROM treatment_notifications tn JOIN treatment_plans tp ON tn.plan_id = tp.id JOIN pets p ON p.id = tp.pet_id WHERE p.owner_id = ? AND tn.role = 'pet_owner' AND tn.type = 'discharge_ready' AND tn.is_read = 0");
$stmt->execute([$user_id]);
$discharge_ready_notifications = $stmt->fetchAll();

$current_page = 'index';
require_once '../../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">Pet Owner Overview</h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> Overview</p>
  </div>
</div>

<?php if (!empty($pickup_notifications)): ?>
<div class="card" style="border-left: 4px solid var(--color-success); margin-bottom: 24px;">
  <div class="card-header">
    <h2 style="color:var(--color-success);"><i class='bx bx-check-double'></i> Ready for Pickup</h2>
  </div>
  <p class="text-muted mb-4">Your pet's treatment is complete and they are ready to go home!</p>
  <?php foreach ($pickup_notifications as $notif): ?>
    <div class="alert alert-success flex justify-between items-center mb-2">
      <div>
        <strong><?= htmlspecialchars($notif['pet_name']) ?></strong> is ready for pickup.<br>
        <span style="color:var(--color-muted); font-size:12px;"> Treatment completed on <?= date('M d, Y', strtotime($notif['date'])) ?></span>
      </div>
      <form method="POST" action="mark_read.php" style="margin:0;">
        <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
        <button type="submit" class="btn btn-outline btn-sm"><i class='bx bx-check'></i> Dismiss</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($discharge_ready_notifications)): ?>
<div class="card" style="border-left: 4px solid var(--color-primary); margin-bottom: 24px;">
  <div class="card-header">
    <h2 style="color:var(--color-primary);"><i class='bx bx-home-heart'></i> Discharge approved</h2>
  </div>
  <p class="text-muted mb-4">The clinical team has cleared your pet for discharge preparation. You will receive pickup details when the discharge summary is finalized.</p>
  <?php foreach ($discharge_ready_notifications as $notif): ?>
    <div class="alert flex justify-between items-center mb-2" style="background:var(--color-bg); border:1px solid var(--color-border);">
      <div>
        <strong><?= htmlspecialchars($notif['pet_name']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — Plan #<?= (int)$notif['plan_id'] ?></span>
      </div>
      <div style="display:flex; gap:8px;">
        <a href="/Petmate/dashboards/pet_owner/view_treatment.php?id=<?= (int)$notif['plan_id'] ?>" class="btn btn-primary btn-sm"><i class='bx bx-detail'></i> View plan</a>
        <form method="POST" action="mark_read.php" style="margin:0;">
          <input type="hidden" name="notif_id" value="<?= (int)$notif['id'] ?>">
          <button type="submit" class="btn btn-outline btn-sm"><i class='bx bx-check'></i> Dismiss</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($bill_notifications)): ?>
<div class="card" style="border-left: 4px solid var(--color-accent); margin-bottom: 24px;">
  <div class="card-header">
    <h2 style="color:var(--color-accent);"><i class='bx bx-receipt'></i> Bill Ready</h2>
  </div>
  <p class="text-muted mb-4">Your pet's treatment is complete and the bill has been generated.</p>
  <?php foreach ($bill_notifications as $notif): ?>
    <div class="alert alert-info flex justify-between items-center mb-2">
      <div>
        A new bill is ready for <strong><?= htmlspecialchars($notif['pet_name']) ?></strong>.<br>
      </div>
      <div style="display: flex; gap: 8px;">
        <a href="/Petmate/dashboards/pet_owner/bills.php" class="btn btn-accent btn-sm"><i class='bx bx-credit-card'></i> Pay Now</a>
        <form method="POST" action="mark_read.php" style="margin:0;">
          <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
          <button type="submit" class="btn btn-outline btn-sm"><i class='bx bx-check'></i> Dismiss</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Rejected records alert -->
<?php if (!empty($rejected_records)): ?>
<div class="card" style="border-left: 4px solid var(--color-danger);">
  <div class="card-header">
    <h2 style="color:var(--color-danger);"><i class='bx bx-error-circle'></i> Action Required</h2>
  </div>
  <p class="text-muted mb-4">The following registrations were rejected by the CSR. Please review the remarks, edit the information, and resubmit.</p>
  <?php foreach ($rejected_records as $rej): ?>
    <div class="alert alert-error flex justify-between items-center mb-2">
      <div>
        <strong><?= htmlspecialchars($rej['pet_name']) ?></strong>
        <span style="color:var(--color-muted); font-size:12px;"> — Visit: <?= date('M d, Y', strtotime($rej['visit_date'])) ?></span><br>
        <small><strong>Remarks:</strong> <?= htmlspecialchars($rej['remarks']) ?></small>
      </div>
      <a href="/Petmate/dashboards/pet_owner/register_pet.php?edit_id=<?= $rej['id'] ?>" class="btn btn-danger btn-sm">
        <i class='bx bx-edit'></i> Edit & Resubmit
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-4 mb-6">
    <div class="stat-card">
        <span class="stat-label">Total Pets</span>
        <span class="stat-value"><?= $total_pets ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Unpaid Bills</span>
        <span class="stat-value danger"><?= $unpaid_bills ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Recent Visits</span>
        <span class="stat-value"><?= $recent_visits ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Unread Messages</span>
        <span class="stat-value accent"><?= $unread_messages ?></span>
    </div>
</div>