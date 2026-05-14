<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables if not already loaded
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    try { $dotenv->load(); } catch (\Exception $e) {}
}

define('AES_METHOD', 'aes-256-cbc');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'change-me-in-env-file');

function encrypt_data($data) {
    if (!$data) return $data;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(AES_METHOD));
    $encrypted = openssl_encrypt($data, AES_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decrypt_data($data) {
    if (!$data) return $data;
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) === 2) {
        return openssl_decrypt($parts[0], AES_METHOD, ENCRYPTION_KEY, 0, $parts[1]);
    }
    return $data;
}
?>
