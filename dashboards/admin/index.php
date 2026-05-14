<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('admin');
require_permission('manage_system');

$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_status') {
    $target_id = $_POST['user_id'];
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $target_id]);
    require_once '../../includes/logger.php';
    log_audit($pdo, $_SESSION['user_id'], "Updated user #$target_id status to $new_status", 'users', $target_id);
    header("Location: /Petmate/dashboards/admin/index.php?tab=users");
    exit;
}

if ($tab === 'sessions' && isset($_GET['terminate'])) {
    $term_id = $_GET['terminate'];
    $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?")->execute([$term_id]);
    require_once '../../includes/logger.php';
    log_audit($pdo, $_SESSION['user_id'], "Terminated session $term_id");
    header("Location: /Petmate/dashboards/admin/index.php?tab=sessions");
    exit;
}

$current_page = 'index';
require_once '../../includes/header.php';
?>
<div class="action-bar"><div><h1 class="page-heading">Admin Panel</h1><p class="breadcrumb">PetMate <span>›</span> Admin</p></div></div>
<div class="card"><div class="empty-state"><i class='bx bx-shield-quarter'></i><p>Admin dashboard migrated to role-scoped path. Existing tab logic remains available.</p></div></div>
<?php require_once '../../includes/footer.php'; ?>
