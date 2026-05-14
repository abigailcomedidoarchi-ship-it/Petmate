<?php
require_once 'includes/db.php';
require_once 'includes/auth.php'; // This handles session_start()
require_once 'includes/session_guard.php';
require_once 'vendor/autoload.php';

// Load environment variables
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    try { $dotenv->load(); } catch (\Exception $e) {}
}

// Check if user is in the "choose role" phase
if (isset($_GET['action']) && $_GET['action'] === 'choose_role') {
    if (!isset($_SESSION['google_register_email'])) {
        header("Location: /Petmate/login.php");
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $role = $_POST['role'];
        $allowed_roles = ['pet_owner', 'csr', 'vet_assistant', 'vet_technician'];
        
        if (in_array($role, $allowed_roles)) {
            $name = $_SESSION['google_register_name'];
            $email = $_SESSION['google_register_email'];
            
            $random_password = bin2hex(random_bytes(8)) . 'A1!'; 
            $hashed = password_hash($random_password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed, $role])) {
                $new_id = $pdo->lastInsertId();
                
                $_SESSION['user_id'] = $new_id;
                $_SESSION['name'] = $name;
                $_SESSION['role'] = $role;
                $_SESSION['last_activity'] = time();
                
                unset($_SESSION['google_register_name']);
                unset($_SESSION['google_register_email']);
                
                require_once 'includes/logger.php';
                log_login_attempt($pdo, $email, true, $role);
                register_session($pdo, $new_id);
                
                redirectBasedOnRole($role);
            } else {
                die("Error creating account.");
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Complete Registration - Petmate</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/Petmate/assets/css/style.css">
    </head>
    <body style="display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5;">
        <div class="card" style="max-width: 450px; text-align: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Logo" style="width: 48px; margin-bottom: 1rem;">
            <h2>Complete Registration</h2>
            <p class="text-muted mt-2">Welcome, <strong><?= htmlspecialchars($_SESSION['google_register_name']) ?></strong>! You are logging in via Google for the first time. Please choose your account type to proceed.</p>
            
            <form method="POST">
                <div class="form-group" style="text-align: left; margin-top: 1.5rem;">
                    <label>Account Type *</label>
                    <select name="role" required>
                        <option value="pet_owner">Pet Owner</option>
                        <option value="csr">Client Service Representative</option>
                        <option value="vet_assistant">Veterinary Assistant</option>
                        <option value="vet_technician">Veterinary Technician</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Create Account & Continue</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? 'http://localhost/Petmate/google_auth.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    // Authenticate with Google
    $client->authenticate($_GET['code']);
    $token = $client->getAccessToken();
    $client->setAccessToken($token);
    
    // Get User Profile Info
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email =  $google_account_info->email;
    $name =  $google_account_info->name;
    
    if (empty($email)) {
        die("Google login failed: Email not provided.");
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    require_once 'includes/logger.php';
    
    if ($user) {
        if ($user['status'] === 'locked' || $user['status'] === 'suspended') {
            die("Your account is " . $user['status'] . ".");
        }
        
        // Log them in immediately
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
        // User doesn't exist. Ask for role.
        $_SESSION['google_register_name'] = $name;
        $_SESSION['google_register_email'] = $email;
        
        header("Location: /Petmate/google_auth.php?action=choose_role");
        exit;
    }
} else {
    // Generate Google Auth URL and Redirect
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}
?>
