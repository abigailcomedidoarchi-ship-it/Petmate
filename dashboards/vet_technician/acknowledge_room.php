<?php
require_once '../../includes/db.php';
require_once '../../includes/session_guard.php';
require_once '../../includes/rbac.php';
require_once '../../includes/logger.php';
require_once '../../includes/auth.php';

requireRole('vet_technician');
require_permission('manage_exam_rooms');

if (!function_exists('log_action')) {
    function log_action($pdo, $user_id, $role, $action, $target_table, $target_id) {
        log_audit($pdo, $user_id, $role . ':' . $action, $target_table, $target_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = (int) $_POST['room_id'];

    $stmt = $pdo->prepare("UPDATE examination_rooms SET status = 'in_use' WHERE id = ?");
    $stmt->execute([$room_id]);

    log_action($pdo, (int)$_SESSION['user_id'], 'vet_technician', 'room_acknowledged', 'examination_rooms', $room_id);

    header("Location: /Petmate/dashboards/vet_technician/pet_overview.php?room_id={$room_id}");
    exit;
}

header("Location: /Petmate/dashboards/vet_technician/exam_rooms.php");
exit;
?>
