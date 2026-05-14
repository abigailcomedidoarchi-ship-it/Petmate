<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';
require_once 'includes/session_guard.php';
require_once 'includes/mailer.php';

if (isLoggedIn()) {
    redirectBasedOnRole($_SESSION['role']);
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'Your session has timed out due to inactivity.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['status'] === 'locked') {
                $error = 'Your account has been locked. Please contact the administrator.';
                log_login_attempt($pdo, $email, false, $user['role']);
            } elseif ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended.';
                log_login_attempt($pdo, $email, false, $user['role']);
            } elseif (password_verify($password, $user['password'])) {
                if ($user['is_verified'] == 0) {
                    // Redirect to verification page
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    header("Location: /Petmate/verify_otp.php");
                    exit;
                }
                
                // Success - Log user in directly
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Reset failed attempts
                $pdo->prepare("UPDATE users SET failed_login_attempts = 0 WHERE id = ?")->execute([$user['id']]);
                
                log_login_attempt($pdo, $email, true, $user['role']);
                register_session($pdo, $user['id']);
                
                redirectBasedOnRole($user['role']);
            } else {
                // Failed password
                $failed = $user['failed_login_attempts'] + 1;
                if ($failed >= 5) {
                    $pdo->prepare("UPDATE users SET status = 'locked', failed_login_attempts = ? WHERE id = ?")->execute([$failed, $user['id']]);
                    $error = 'Account locked due to too many failed attempts.';
                } else {
                    $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?")->execute([$failed, $user['id']]);
                    $error = 'Invalid email or password.';
                }
                log_login_attempt($pdo, $email, false, $user['role']);
            }
        } else {
            $error = 'Invalid email or password.';
            log_login_attempt($pdo, $email, false, 'unknown');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Petmate</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="/Petmate/assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center" style="margin-bottom: 1.5rem; color: var(--primary);">
                <i class='bx bx-plus-medical' style="font-size: 3rem;"></i>
            </div>
            <h1>Welcome to Petmate</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        <i class='bx bx-log-in'></i> Sign In
                    </button>
                    
                    <div style="text-align: center; margin: 1rem 0; color: var(--text-muted); position: relative;">
                        <hr style="border: 0; border-top: 1px solid var(--border-color); position: absolute; width: 100%; top: 50%; z-index: 1;">
                        <span style="background: var(--card-bg); padding: 0 10px; position: relative; z-index: 2; font-size: 0.9rem;">or</span>
                    </div>

                    <a href="/Petmate/google_auth.php" class="btn btn-outline" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 0.5rem; border-color: #dadce0; color: #3c4043;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Logo" style="width: 18px; height: 18px;">
                        Sign in with Google
                    </a>
                </div>
            </form>

            <div class="auth-footer">
                <p>Don’t have an account? <a href="/Petmate/register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
