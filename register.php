<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/mailer.php';

if (isLoggedIn()) {
    redirectBasedOnRole($_SESSION['role']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    
    // Combine names
    $name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
    
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'pet_owner';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $allowed_roles = ['pet_owner', 'csr', 'vet_assistant', 'vet_technician'];

    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $special   = preg_match('@[^\w]@', $password);

    if (!in_array($role, $allowed_roles)) {
        $error = 'Invalid role selected.';
    } elseif (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8 || !$uppercase || !$lowercase || !$number || !$special) {
        $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, contact, address, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$name, $contact, $address, $email, $hashed_password, $role])) {
                $new_user_id = $pdo->lastInsertId();
                
                // Generate OTP for email verification
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                
                $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")->execute([$otp, $expires_at, $new_user_id]);
                
                send_otp_email($email, $otp);
                
                // Set pending session and redirect to verify
                $_SESSION['pending_2fa_user_id'] = $new_user_id;
                header("Location: /Petmate/verify_otp.php");
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Petmate</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="/Petmate/assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 600px;">
            <div class="text-center" style="margin-bottom: 1.5rem; color: var(--primary);">
                <i class='bx bx-user-plus' style="font-size: 3rem;"></i>
            </div>
            <h1>Create an Account</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-4">
                    <a href="/Petmate/login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="text" id="contact" name="contact">
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                            <small class="text-muted" style="display:block; margin-top:0.25rem; font-size: 0.75rem;">
                                Minimum 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character.
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="role">Account Type *</label>
                        <select id="role" name="role" required>
                            <option value="pet_owner">Pet Owner</option>
                            <option value="csr">Client Service Representative</option>
                            <option value="vet_assistant">Veterinary Assistant</option>
                            <option value="vet_technician">Veterinary Technician</option>
                        </select>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class='bx bx-check-circle'></i> Register
                        </button>
                    </div>
                </form>

                <div class="auth-footer">
                    <p>Already have an account? <a href="/Petmate/login.php">Login here</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
