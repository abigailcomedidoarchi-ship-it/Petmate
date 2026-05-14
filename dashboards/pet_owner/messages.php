<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');
require_permission('view_dashboard');
$current_page = 'messages';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Messages</h1><p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> Messages</p></div></div>
<div class="card"><div class="empty-state"><i class='bx bx-message-square-dots'></i><p>Messages workspace is coming soon.</p></div></div>
<?php require_once '../../includes/footer.php'; ?>