<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/logger.php';

requireLogin(); // Must be logged in
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation rules
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    $special   = preg_match('@[^\w]@', $new_password); // matches non-word characters

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8 || !$uppercase || !$lowercase || !$number || !$special) {
        $error = "Password must be at least 8 characters in length and must contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            // Hash and update
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update->execute([$hashed, $user_id])) {
                $success = "Password successfully changed.";
                log_audit($pdo, $user_id, "User changed their password", "users", $user_id);
            } else {
                $error = "An error occurred while updating the password.";
            }
        } else {
            $error = "Current password is incorrect.";
            log_audit($pdo, $user_id, "Failed password change attempt: incorrect current password");
        }
    }
}

require_once 'includes/header.php';
?>

<div class="flex justify-between items-center mb-4">
    <h1><i class='bx bx-lock-alt'></i> Change Password</h1>
    <a href="javascript:history.back()" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto; margin-top: 2rem;">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="current_password">Current Password *</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="form-group">
            <label for="new_password">New Password *</label>
            <input type="password" id="new_password" name="new_password" required>
            <small class="text-muted" style="display:block; margin-top:0.25rem;">
                Requirements:<br>
                - Minimum 8 characters<br>
                - At least one uppercase letter (A-Z)<br>
                - At least one lowercase letter (a-z)<br>
                - At least one number (0-9)<br>
                - At least one special character (!, @, #, $, etc.)
            </small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password *</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class='bx bx-save'></i> Update Password</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
