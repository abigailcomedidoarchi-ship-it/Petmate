<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');
$current_page = 'prescriptions';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Prescriptions</h1><p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Prescriptions</p></div></div>
<div class="card"><div class="empty-state"><i class='bx bx-capsule'></i><p>Prescription workspace transferred from the former Vet module.</p></div></div>
<?php require_once '../../includes/footer.php'; ?>
