<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('pet_owner');
require_permission('view_dashboard');
$user_id = $_SESSION['user_id'];

// Fetch pets
$stmt = $pdo->prepare("SELECT * FROM pets WHERE owner_id = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll();

// Fetch bills
$stmt = $pdo->prepare("SELECT b.*, p.name as pet_name, pr.visit_date FROM bills b
                       JOIN pet_records pr ON b.visit_id = pr.id
                       JOIN pets p ON pr.pet_id = p.id
                       WHERE b.owner_id = ? ORDER BY b.date DESC");
$stmt->execute([$user_id]);
$bills = $stmt->fetchAll();

// Fetch rejected records
$stmt = $pdo->prepare("SELECT pr.*, p.name as pet_name
                       FROM pet_records pr
                       JOIN pets p ON pr.pet_id = p.id
                       WHERE p.owner_id = ? AND pr.status = 'rejected'");
$stmt->execute([$user_id]);
$rejected_records = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">My Dashboard</h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner</p>
  </div>
  <a href="/Petmate/dashboards/register_pet.php" class="btn btn-primary">
    <i class='bx bx-plus'></i> Register New Pet
  </a>
</div>

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
      <a href="/Petmate/dashboards/register_pet.php?edit_id=<?= $rej['id'] ?>" class="btn btn-danger btn-sm">
        <i class='bx bx-edit'></i> Edit & Resubmit
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <!-- My Pets -->
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-paw'></i> My Pets</h2>
      <span class="badge badge-validated"><?= count($pets) ?> registered</span>
    </div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Species</th>
            <th>Breed</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pets)): ?>
            <tr><td colspan="4"><div class="empty-state"><i class='bx bx-paw'></i><p>No pets registered yet.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($pets as $pet): ?>
            <tr>
              <td><strong><?= htmlspecialchars($pet['name']) ?></strong></td>
              <td><?= htmlspecialchars($pet['species']) ?></td>
              <td style="color:var(--color-muted);"><?= htmlspecialchars($pet['breed']) ?></td>
              <td><a href="#" class="btn btn-outline btn-sm"><i class='bx bx-history'></i> History</a></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Billing -->
  <div class="card">
    <div class="card-header">
      <h2><i class='bx bx-credit-card'></i> Billing & Payments</h2>
    </div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Pet</th>
            <th>Amount</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bills)): ?>
            <tr><td colspan="5"><div class="empty-state"><i class='bx bx-receipt'></i><p>No billing records found.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($bills as $bill): ?>
            <tr>
              <td style="font-size:12px; color:var(--color-muted);"><?= date('M d, Y', strtotime($bill['visit_date'])) ?></td>
              <td><?= htmlspecialchars($bill['pet_name']) ?></td>
              <td><strong>$<?= number_format($bill['amount'], 2) ?></strong></td>
              <td><span class="badge badge-<?= $bill['status'] ?>"><?= ucfirst($bill['status']) ?></span></td>
              <td>
                <?php if ($bill['status'] === 'unpaid'): ?>
                  <button class="btn btn-accent btn-sm"><i class='bx bx-credit-card'></i> Pay</button>
                <?php else: ?>
                  <span style="color:var(--color-success); font-size:13px;"><i class='bx bx-check'></i> Paid</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Visits -->
<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-history'></i> Recent Visits & Prescriptions</h2>
  </div>
  <div class="empty-state">
    <i class='bx bx-history'></i>
    <p>No recent visits found. Select a pet above to view detailed medical history.</p>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
