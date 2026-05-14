<?php
function register_session($pdo, $user_id) {
    $session_id = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Remove old session for this user to ensure single session
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $stmt = $pdo->prepare("INSERT INTO active_sessions (session_id, user_id, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$session_id, $user_id, $ip]);
}

function update_session_activity($pdo) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE active_sessions SET last_activity = NOW() WHERE session_id = ?");
        $stmt->execute([session_id()]);
    }
}

function destroy_session($pdo) {
    if (session_id()) {
        $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?");
        $stmt->execute([session_id()]);
    }
}
?>
