<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');
$current_page = 'settings';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Settings</h1><p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Settings</p></div></div>
<div class="card">
  <div class="card-header"><h2><i class='bx bx-lock-alt'></i> Account Security</h2></div>
  <p class="text-muted mb-4">Manage your account password from Settings.</p>
  <a href="/Petmate/change_password.php" class="btn btn-primary"><i class='bx bx-lock-alt'></i> Change Password</a>
</div>
<?php require_once '../../includes/footer.php'; ?>