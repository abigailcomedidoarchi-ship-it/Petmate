<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env only if the class exists (avoids crashes)
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    try {
        $dotenv->load();
    } catch (\Exception $e) {}
}

function send_otp_email($to_email, $otp_code) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
        $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['MAIL_PORT'] ?? 587;

        $mail->SMTPDebug = 0;

        // Sender & Receiver
        $mail->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@petmate.com', $_ENV['MAIL_FROM_NAME'] ?? 'Petmate System');
        $mail->addAddress($to_email);

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Petmate OTP';

        $mail->Body = "
            <div style='font-family: Arial; text-align:center; padding:20px; border:1px solid #ddd; border-radius:10px;'>
                <h2>Petmate Verification</h2>
                <p>Your OTP code is:</p>
                <h1 style='letter-spacing:5px;'>$otp_code</h1>
                <p>This code will expire in 5 minutes.</p>
            </div>
        ";

        $mail->AltBody = "Your OTP is: $otp_code. It expires in 5 minutes.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>