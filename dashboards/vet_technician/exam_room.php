<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('vet_technician');
require_permission('view_dashboard');
$current_page = 'exam_room';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Exam Room</h1><p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Exam Room</p></div></div>
<div class="card"><div class="empty-state"><i class='bx bx-clinic'></i><p>Exam room workspace is ready for technician actions.</p></div></div>
<?php require_once '../../includes/footer.php'; ?>
