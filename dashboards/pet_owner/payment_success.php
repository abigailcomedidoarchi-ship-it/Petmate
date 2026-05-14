<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('pet_owner');

if (!isset($_GET['bill_id'])) {
    die("No bill ID provided.");
}

$bill_id = (int)$_GET['bill_id'];
$user_id = $_SESSION['user_id'];

// Check if bill exists and belongs to user
$stmt = $pdo->prepare("SELECT b.*, pr.pet_id, tp.id as plan_id 
                       FROM bills b 
                       JOIN pet_records pr ON b.visit_id = pr.id 
                       JOIN treatment_plans tp ON tp.pet_id = pr.pet_id AND tp.workflow_status = 'awaiting_payment'
                       WHERE b.id = ? AND b.owner_id = ? AND b.status = 'unpaid'");
$stmt->execute([$bill_id, $user_id]);
$bill = $stmt->fetch();

if ($bill) {
    try {
        $pdo->beginTransaction();
        
        // Update bill status
        $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?")->execute([$bill_id]);
        
        // Update workflow status to paid
        $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'completed' WHERE id = ?")->execute([$bill['plan_id']]);
        
        // Update pet record status to completed or discharged
        $pdo->prepare("UPDATE pet_records SET status = 'completed' WHERE id = ?")->execute([$bill['visit_id']]);
        
        // Notify owner that their pet is ready for pickup or they are done.
        $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'pet_owner', 'payment_received')")->execute([$bill['plan_id']]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating payment status: " . $e->getMessage());
    }
}

// Redirect to the receipt page so the owner can print immediately
header("Location: receipt.php?bill_id=" . $bill_id);
exit;
