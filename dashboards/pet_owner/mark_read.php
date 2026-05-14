<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_id'])) {
    $notif_id = (int)$_POST['notif_id'];
    $pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE id = ? AND role = 'pet_owner'")->execute([$notif_id]);
}

header('Location: index.php');
exit;
