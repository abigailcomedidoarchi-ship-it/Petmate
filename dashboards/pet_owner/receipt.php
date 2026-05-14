<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$bill_id) {
    header('Location: bills.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.*, p.name AS pet_name, p.species, p.breed, pr.visit_date, u.name AS owner_name, u.email AS owner_email
    FROM bills b
    JOIN pet_records pr ON b.visit_id = pr.id
    JOIN pets p ON pr.pet_id = p.id
    JOIN users u ON b.owner_id = u.id
    WHERE b.id = ? AND b.owner_id = ? AND b.status = 'paid'
");
$stmt->execute([$bill_id, $user_id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Receipt not available. The bill must be paid before a receipt can be generated.");
}

$items_stmt = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id ASC");
$items_stmt->execute([$bill_id]);
$items = $items_stmt->fetchAll();

// Try to get the treatment plan for additional context
$tp = null;
try {
    $tpStmt = $pdo->prepare("
        SELECT tp.id, tp.description, tp.date, v.name AS vet_name
        FROM treatment_plans tp
        LEFT JOIN users v ON v.id = tp.vet_id
        WHERE tp.pet_id = (SELECT pet_id FROM pet_records WHERE id = ?)
        ORDER BY tp.id DESC LIMIT 1
    ");
    $tpStmt->execute([$bill['visit_id']]);
    $tp = $tpStmt->fetch();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetMate Receipt #<?= $bill_id ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 24px;
        }

        .receipt-actions {
            max-width: 700px;
            margin: 0 auto 16px auto;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .receipt-actions button,
        .receipt-actions a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-print {
            background: #6366f1;
            color: #fff;
            border: none;
        }
        .btn-print:hover { background: #4f46e5; }
        .btn-back {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-back:hover { background: #f8fafc; }

        .receipt {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        .receipt-header {
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: #fff;
            padding: 32px;
            text-align: center;
        }
        .receipt-header .logo {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .receipt-header .logo i { margin-right: 6px; }
        .receipt-header .subtitle {
            font-size: 13px;
            opacity: 0.85;
            font-weight: 500;
        }
        .receipt-header .receipt-badge {
            display: inline-block;
            margin-top: 16px;
            background: rgba(255,255,255,0.2);
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .receipt-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 24px 32px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-item { font-size: 13px; }
        .meta-label {
            font-weight: 500;
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-value {
            font-weight: 600;
            color: #1e293b;
        }

        .receipt-body { padding: 24px 32px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            padding: 8px 0;
            border-bottom: 2px solid #e2e8f0;
        }
        .items-table th:last-child { text-align: right; }
        .items-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .items-table td:last-child {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table .item-number {
            color: #94a3b8;
            font-size: 12px;
            width: 30px;
        }

        .receipt-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 2px solid #86efac;
            border-radius: 12px;
            margin: 0 0 24px 0;
        }
        .total-label {
            font-size: 14px;
            font-weight: 600;
            color: #166534;
        }
        .total-amount {
            font-size: 28px;
            font-weight: 800;
            color: #166534;
            letter-spacing: -0.5px;
        }

        .paid-stamp {
            text-align: center;
            padding: 16px 0 8px 0;
        }
        .paid-stamp span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 800;
            color: #16a34a;
            border: 3px solid #16a34a;
            border-radius: 12px;
            padding: 10px 32px;
            text-transform: uppercase;
            letter-spacing: 2px;
            transform: rotate(-3deg);
        }

        .receipt-footer {
            text-align: center;
            padding: 20px 32px 28px;
            border-top: 1px dashed #e2e8f0;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }
        .receipt-footer strong { color: #64748b; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt-actions { display: none !important; }
            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
            .receipt-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .receipt-total {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 600px) {
            body { padding: 12px; }
            .receipt-meta { grid-template-columns: 1fr; }
            .receipt-header { padding: 24px 16px; }
            .receipt-body { padding: 16px; }
            .receipt-meta { padding: 16px; }
            .total-amount { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="receipt-actions">
    <a href="bills.php" class="btn-back"><i class='bx bx-arrow-back'></i> Back to Bills</a>
    <button class="btn-print" onclick="window.print()"><i class='bx bx-printer'></i> Print Receipt</button>
</div>

<div class="receipt">
    <div class="receipt-header">
        <div class="logo"><i class='bx bxs-dog'></i> PetMate</div>
        <div class="subtitle">Veterinary Clinic Management System</div>
        <div class="receipt-badge">✓ Payment Receipt</div>
    </div>

    <div class="receipt-meta">
        <div class="meta-item">
            <div class="meta-label">Receipt No.</div>
            <div class="meta-value">#<?= str_pad($bill_id, 6, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Date Paid</div>
            <div class="meta-value"><?= date('M d, Y', strtotime($bill['date'])) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Pet Name</div>
            <div class="meta-value"><?= htmlspecialchars($bill['pet_name']) ?> (<?= htmlspecialchars(($bill['species'] ?: '') . ($bill['breed'] ? ' / ' . $bill['breed'] : '')) ?>)</div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Owner</div>
            <div class="meta-value"><?= htmlspecialchars($bill['owner_name']) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Visit Date</div>
            <div class="meta-value"><?= date('M d, Y', strtotime($bill['visit_date'])) ?></div>
        </div>
        <?php if ($tp): ?>
        <div class="meta-item">
            <div class="meta-label">Attending Vet</div>
            <div class="meta-value"><?= htmlspecialchars($tp['vet_name'] ?: 'N/A') ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="receipt-body">
        <?php if (!empty($items)): ?>
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td class="item-number"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td>₱ <?= number_format($item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="receipt-total">
            <div class="total-label"><i class='bx bx-receipt'></i> Total Paid</div>
            <div class="total-amount">₱ <?= number_format($bill['amount'], 2) ?></div>
        </div>

        <div class="paid-stamp">
            <span><i class='bx bx-check-circle'></i> PAID</span>
        </div>
    </div>

    <div class="receipt-footer">
        <strong>Thank you for choosing PetMate!</strong><br>
        This is a computer-generated receipt and does not require a signature.<br>
        For questions, please contact the clinic directly.
    </div>
</div>

</body>
</html>
