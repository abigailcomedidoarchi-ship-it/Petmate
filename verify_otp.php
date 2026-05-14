
<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';
require_once 'includes/session_guard.php';
require_once 'includes/mailer.php';

// verify_otp.php 

if (!isset($_SESSION['pending_2fa_user_id'])) {
    header("Location: /Petmate/login.php");
    exit;
}

$user_id = $_SESSION['pending_2fa_user_id'];
$error = '';
$success = '';

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: /Petmate/login.php");
    exit;
}

if ($user['is_verified'] == 1) {
    header("Location: /Petmate/login.php");
    exit;
}

if (isset($_GET['resend'])) {
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")->execute([$otp, $expires_at, $user_id]);
    
    // Send email using PHPMailer
    if (send_otp_email($user['email'], $otp)) {
        $_SESSION['otp_msg'] = 'A new OTP has been sent to your email.';
    } else {
        $_SESSION['otp_err'] = 'Failed to send OTP. Please check your email configuration.';
    }
    
    // Redirect to clear the ?resend=1 from the URL!
    header("Location: /Petmate/verify_otp.php");
    exit;
}

if (isset($_SESSION['otp_msg'])) {
    $success = $_SESSION['otp_msg'];
    unset($_SESSION['otp_msg']);
}
if (isset($_SESSION['otp_err'])) {
    $error = $_SESSION['otp_err'];
    unset($_SESSION['otp_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);
    
    // Re-fetch user to get latest OTP from DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $fresh_user = $stmt->fetch();
    
    if (empty($entered_otp)) {
        $error = 'Please enter the OTP code.';
    } elseif ($fresh_user['otp_code'] !== $entered_otp) {
        $error = 'Invalid OTP code.';
    } elseif (strtotime($fresh_user['otp_expires_at']) < time()) {
        $error = 'This OTP has expired. Please request a new one.';
    } else {
        // Success - Verify Email
        $pdo->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?")->execute([$user_id]);
        
        unset($_SESSION['pending_2fa_user_id']);
        
        $success = "Email verified! You can now log in. Redirecting...";
        echo "<script>setTimeout(function() { window.location.href = '/Petmate/login.php'; }, 2000);</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Petmate</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="/Petmate/assets/css/style.css">
    <style>
        .otp-input {
            font-size: 2rem;
            letter-spacing: 0.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center" style="margin-bottom: 1.5rem; color: var(--primary);">
                <i class='bx bx-check-shield' style="font-size: 3rem;"></i>
            </div>
            <h1>Two-Factor Authentication</h1>
            <p class="text-center text-muted mb-4">We've sent a 6-digit code to <strong><?= htmlspecialchars($user['email']) ?></strong></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" id="otp" name="otp" class="otp-input" maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus autocomplete="off">
                </div>
                
                <div class="text-center mb-4" style="font-size: 0.9rem;">
                    Time remaining: <strong id="timer" style="color: var(--danger);">05:00</strong>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Verify Code
                    </button>
                </div>
            </form>

            <div class="auth-footer text-center mt-4">
                <p>Didn't receive the code? <br><br>
                   <a href="?resend=1" id="resend-link" class="btn btn-outline" style="pointer-events: none; opacity: 0.5; font-size: 0.8rem; padding: 0.25rem 0.5rem;">Resend OTP</a>
                </p>
                <p class="mt-4"><a href="/Petmate/login.php">Back to Login</a></p>
                
                <?php if (empty($success)): ?>
                <p class="mt-4 text-muted"><small>For Dev Testing: Look in the database for the <code>otp_code</code>, or click Resend to see it here.</small></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // 5 minute timer
        let timeLeft = 300; // seconds
        const timerElement = document.getElementById('timer');
        const resendLink = document.getElementById('resend-link');
        
        // If there's an existing timestamp in local storage, we could use that, but for simplicity we reset on load.
        // Or better, we calculate time left based on expiration date from PHP.
        <?php 
            $expires_ts = strtotime($user['otp_expires_at']);
            $current_ts = time();
            $diff = $expires_ts - $current_ts;
            echo "timeLeft = " . max(0, $diff) . ";";
        ?>

        const countdown = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerElement.innerText = "Expired";
                resendLink.style.pointerEvents = 'auto';
                resendLink.style.opacity = '1';
            } else {
                let m = Math.floor(timeLeft / 60);
                let s = timeLeft % 60;
                timerElement.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                timeLeft--;
            }
        }, 1000);
    </script>
</body>
</html>
