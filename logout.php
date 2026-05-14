<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/session_guard.php';

destroy_session($pdo);

session_unset();
session_destroy();
header("Location: /Petmate/index.php");
exit();
?>
