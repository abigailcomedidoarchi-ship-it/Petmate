<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('pet_owner');
require_permission('view_dashboard');
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM pets WHERE owner_id = ?");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll();

$current_page = 'my_pets';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">My Pets</h1>
    <p class="breadcrumb">PetMate <span>›</span> My Pets</p>
  </div>
  <a href="/Petmate/dashboards/pet_owner/register_pet.php" class="btn btn-primary"><i class='bx bx-plus'></i> Register New Pet</a>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class='bx bx-paw'></i> My Pets</h2>
    <span class="badge badge-validated"><?= count($pets) ?> registered</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Name</th><th>Species</th><th>Breed</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($pets)): ?>
          <tr><td colspan="4"><div class="empty-state"><i class='bx bx-paw'></i><p>No pets registered yet.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($pets as $pet): ?>
              <td><strong><?= htmlspecialchars($pet['name']) ?></strong></td>
              <td><?= htmlspecialchars($pet['species']) ?></td>
              <td style="color:var(--color-muted);"><?= htmlspecialchars($pet['breed']) ?></td>
              <td>
                  <div style="display: flex; gap: 8px;">
                      <a href="submit_visit.php?pet_id=<?= $pet['id'] ?>" class="btn btn-primary btn-sm"><i class='bx bx-calendar-plus'></i> Submit Visit</a>
                      <a href="#" class="btn btn-outline btn-sm"><i class='bx bx-history'></i> History</a>
                  </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>