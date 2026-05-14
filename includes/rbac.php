<?php
$role_permissions = [
    'admin' => ['manage_users', 'view_audit_logs', 'manage_system', 'view_dashboard'],
    'csr' => ['view_records', 'validate_records', 'manage_billing', 'view_dashboard'],
    'pet_owner' => ['view_own_records', 'create_visit', 'view_dashboard'],
    'vet_assistant' => ['view_validated_records', 'view_dashboard'],
    'vet_technician' => ['perform_assessments', 'create_treatment_plan', 'manage_exam_rooms', 'view_dashboard'],
    'veterinarian' => ['view_dashboard']
];

function has_permission($role, $permission) {
    global $role_permissions;
    return isset($role_permissions[$role]) && in_array($permission, $role_permissions[$role]);
}

function require_permission($permission) {
    if (!isset($_SESSION['role']) || !has_permission($_SESSION['role'], $permission)) {
        global $pdo;
        if (isset($pdo) && isset($_SESSION['user_id'])) {
            require_once 'logger.php';
            log_audit($pdo, $_SESSION['user_id'], "Unauthorized access attempt: $permission");
        }
        die("<div style='padding:2rem; font-family:sans-serif; color:red;'><h2>Access Denied</h2><p>You do not have permission to access this resource.</p><a href='/Petmate/login.php'>Return to Login</a></div>");
    }
}
?>
