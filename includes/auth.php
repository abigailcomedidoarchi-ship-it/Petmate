<?php
session_start();

require_once 'rbac.php';
require_once 'session_guard.php';

if (isLoggedIn()) {
    global $pdo;
    if (isset($pdo)) {
        update_session_activity($pdo);
    }
}

// DLP middleware handles session timeout
require_once 'dlp.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /Petmate/login.php");
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        // If they try to access a dashboard they don't have permission for, redirect to their own dashboard
        redirectBasedOnRole($_SESSION['role']);
    }
}

function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: /Petmate/dashboards/admin/index.php");
            break;
        case 'pet_owner':
            header("Location: /Petmate/dashboards/pet_owner/index.php");
            break;
        case 'csr':
            header("Location: /Petmate/dashboards/csr/index.php");
            break;
        case 'veterinarian':
            header("Location: /Petmate/dashboards/vet_technician/index.php");
            break;
        case 'vet_assistant':
            header("Location: /Petmate/dashboards/vet_assistant/index.php");
            break;
        case 'vet_technician':
            header("Location: /Petmate/dashboards/vet_technician/index.php");
            break;
        default:
            header("Location: /Petmate/login.php");
            break;
    }
    exit();
}
?>
