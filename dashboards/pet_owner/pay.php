<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

// Load environment variables
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    try { $dotenv->load(); } catch (\Exception $e) {}
}

requireRole('pet_owner');

if (!isset($_GET['bill_id'])) {
    die("No bill ID provided.");
}

$bill_id = (int)$_GET['bill_id'];
$user_id = $_SESSION['user_id'];

// Get bill
$stmt = $pdo->prepare("SELECT b.*, p.name as pet_name 
                       FROM bills b 
                       JOIN pet_records pr ON b.visit_id = pr.id 
                       JOIN pets p ON pr.pet_id = p.id 
                       WHERE b.id = ? AND b.owner_id = ? AND b.status = 'unpaid'");
$stmt->execute([$bill_id, $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Bill not found or already paid.");
}

// PayMongo secret key from environment
$paymongo_secret_key = $_ENV['PAYMONGO_SECRET_KEY'] ?? '';

// Fetch itemized line items from the database
$stmtItems = $pdo->prepare("SELECT item_name, amount FROM bill_items WHERE bill_id = ? ORDER BY id ASC");
$stmtItems->execute([$bill_id]);
$bill_items = $stmtItems->fetchAll();

// Build PayMongo line items from bill_items
$line_items = [];
if (!empty($bill_items)) {
    foreach ($bill_items as $item) {
        $line_items[] = [
            'currency' => 'PHP',
            'amount' => (int)round($item['amount'] * 100),
            'description' => 'For ' . $bill['pet_name'],
            'name' => $item['item_name'],
            'quantity' => 1
        ];
    }
} else {
    // Fallback: single line item if no bill_items exist
    $line_items[] = [
        'currency' => 'PHP',
        'amount' => (int)round($bill['amount'] * 100),
        'description' => 'Veterinary Services for ' . $bill['pet_name'],
        'name' => 'Vet Bill #' . $bill['id'],
        'quantity' => 1
    ];
}

$payload = [
    'data' => [
        'attributes' => [
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'line_items' => $line_items,
            'payment_method_types' => ['card', 'gcash', 'paymaya'],
            'success_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Petmate/dashboards/pet_owner/payment_success.php?bill_id=' . $bill['id'],
            'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Petmate/dashboards/pet_owner/bills.php'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json",
    "Authorization: Basic " . base64_encode($paymongo_secret_key . ":")
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    die("cURL Error: " . $err);
}

$responseData = json_decode($response, true);

if (isset($responseData['data']['attributes']['checkout_url'])) {
    header("Location: " . $responseData['data']['attributes']['checkout_url']);
    exit;
} else {
    // If the API call fails (e.g. invalid key), we provide a simulation fallback for the demo
    // so the user can still proceed with the flow without an actual PayMongo key.
    echo "<h2>PayMongo API Error</h2>";
    if (isset($responseData['errors'])) {
        echo "<pre>" . print_r($responseData['errors'], true) . "</pre>";
    } else {
        echo "<p>Could not create checkout session.</p>";
    }
    echo "<br>";
    echo "<p><em>Demo Fallback:</em> <a href='payment_success.php?bill_id=" . $bill['id'] . "'>Simulate Successful Payment</a></p>";
}
