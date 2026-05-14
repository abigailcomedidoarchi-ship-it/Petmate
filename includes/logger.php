<?php
function log_audit($pdo, $user_id, $action, $target_table = null, $target_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table, target_id, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $target_table, $target_id, $ip]);
}

function log_login_attempt($pdo, $email, $success, $role_attempted = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, success, role_attempted, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $success ? 1 : 0, $role_attempted, $ip]);
}
?>
