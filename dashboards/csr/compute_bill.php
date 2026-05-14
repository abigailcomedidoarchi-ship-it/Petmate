<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$error = '';
$success = '';

if (!$plan_id) {
    header('Location: billing.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT tp.*, p.name AS pet_name, p.owner_id, u.name AS owner_name, v.name AS vet_presenter_name
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    LEFT JOIN users u ON u.id = p.owner_id
    LEFT JOIN users v ON v.id = tp.vet_id
    WHERE tp.id = ?
      AND LOWER(TRIM(COALESCE(tp.workflow_status, ''))) = 'pending_billing'
      AND LOWER(TRIM(COALESCE(tp.consent_status, ''))) = 'approved'
");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    die('This plan is not available for billing. It must be the owner-approved treatment plan in “pending billing” after discharge (not a draft or pending consent).');
}

/**
 * CSR billing requires a pet_records row for bills.visit_id FK.
 * Discharge prep may not have updated any row (no matching status); resolve or create one.
 */
function csr_resolve_billing_visit_id(PDO $pdo, int $pet_id): int
{
    $q1 = $pdo->prepare("SELECT id FROM pet_records WHERE pet_id = ? AND status = 'pending_billing' ORDER BY id DESC LIMIT 1");
    $q1->execute([$pet_id]);
    $id = $q1->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $q2 = $pdo->prepare("SELECT id FROM pet_records WHERE pet_id = ? ORDER BY id DESC LIMIT 1");
    $q2->execute([$pet_id]);
    $id = $q2->fetchColumn();
    if ($id) {
        $vid = (int) $id;
        try {
            $pdo->prepare("UPDATE pet_records SET status = 'pending_billing' WHERE id = ?")->execute([$vid]);
        } catch (Throwable $e) {
        }

        return $vid;
    }
    $ins = $pdo->prepare("INSERT INTO pet_records (pet_id, visit_date, notes, status) VALUES (?, CURDATE(), ?, 'pending_billing')");
    $ins->execute([$pet_id, 'Visit record created for treatment plan billing.']);
    return (int) $pdo->lastInsertId();
}

try {
    $plan['visit_id'] = csr_resolve_billing_visit_id($pdo, (int) $plan['pet_id']);
} catch (Throwable $e) {
    die('Could not resolve or create a visit record for billing. Check pet_records table and database permissions.');
}

$pdo->prepare("UPDATE treatment_notifications SET is_read = 1 WHERE role = 'csr' AND type = 'discharge_pending_billing' AND plan_id = ?")->execute([$plan_id]);

// Parse the treatment plan prescriptions JSON to auto-populate billing
$prescriptions = json_decode($plan['prescriptions'], true);
$auto_items = [];

// Extract medicines with prices
if (!empty($prescriptions['medicines'])) {
    foreach ($prescriptions['medicines'] as $med) {
        $name = trim($med['medicine_name'] ?? '');
        $price = floatval($med['medicine_price'] ?? $med['price'] ?? $med['cost'] ?? 0);
        if ($name !== '') {
            $auto_items[] = [
                'label' => $name,
                'amount' => $price,
                'category' => 'Medication',
                'icon' => 'bx-capsule'
            ];
        }
    }
}

// Extract surgeries with costs
if (!empty($prescriptions['surgeries'])) {
    foreach ($prescriptions['surgeries'] as $surg) {
        $name = trim($surg['name'] ?? '');
        $cost = floatval($surg['cost'] ?? $surg['price'] ?? 0);
        if ($name !== '') {
            $auto_items[] = [
                'label' => $name,
                'amount' => $cost,
                'category' => 'Surgery',
                'icon' => 'bx-cut'
            ];
        }
    }
}

// Extract procedures with costs
if (!empty($prescriptions['procedures'])) {
    foreach ($prescriptions['procedures'] as $proc) {
        $name = trim($proc['name'] ?? '');
        $cost = floatval($proc['cost'] ?? $proc['price'] ?? 0);
        if ($name !== '') {
            $auto_items[] = [
                'label' => $name,
                'amount' => $cost,
                'category' => 'Procedure',
                'icon' => 'bx-pulse'
            ];
        }
    }
}

// Additional fixed billing fields (always shown, CSR can adjust)
$extra_fields = [
    ['key' => 'doctors_fee',     'label' => "Doctor's Fee",       'icon' => 'bx-user-plus',    'amount' => 0],
    ['key' => 'confinement_fee', 'label' => 'Confinement Fee',    'icon' => 'bx-bed',          'amount' => 0],
    ['key' => 'dextrose_set',    'label' => 'Dextrose Set',       'icon' => 'bx-droplet',      'amount' => 0],
    ['key' => 'cbc_bloodchem',   'label' => 'CBC and Blood Chem', 'icon' => 'bx-test-tube',    'amount' => 0],
    ['key' => 'others',          'label' => 'Others',             'icon' => 'bx-dots-horizontal-rounded', 'amount' => 0],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $total = 0;
    $items_to_save = [];

    // Collect auto items (from treatment plan)
    $auto_amounts = $_POST['auto_amount'] ?? [];
    $auto_labels = $_POST['auto_label'] ?? [];
    foreach ($auto_labels as $i => $label) {
        $val = floatval($auto_amounts[$i] ?? 0);
        if ($val > 0) {
            $items_to_save[] = ['name' => $label, 'amount' => $val];
            $total += $val;
        }
    }

    // Collect extra fields
    foreach ($extra_fields as $field) {
        $val = floatval($_POST[$field['key']] ?? 0);
        if ($val > 0) {
            $items_to_save[] = ['name' => $field['label'], 'amount' => $val];
            $total += $val;
        }
    }

    if ($total <= 0) {
        $error = "Please enter at least one billing item with a valid amount.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtBill = $pdo->prepare("INSERT INTO bills (owner_id, visit_id, amount, status) VALUES (?, ?, ?, 'unpaid')");
            $stmtBill->execute([$plan['owner_id'], $plan['visit_id'], $total]);
            $bill_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO bill_items (bill_id, item_name, amount) VALUES (?, ?, ?)");
            foreach ($items_to_save as $item) {
                $stmtItem->execute([$bill_id, $item['name'], $item['amount']]);
            }

            $pdo->prepare("UPDATE treatment_plans SET workflow_status = 'awaiting_payment' WHERE id = ?")->execute([$plan_id]);
            $pdo->prepare("UPDATE pet_records SET status = 'awaiting_payment' WHERE id = ?")->execute([$plan['visit_id']]);
            $pdo->prepare("INSERT INTO treatment_notifications (plan_id, role, type) VALUES (?, 'pet_owner', 'bill_ready')")->execute([$plan_id]);

            $pdo->commit();
            $success = "Bill of ₱" . number_format($total, 2) . " computed successfully. It has been forwarded to the pet owner for payment.";
            $plan['workflow_status'] = 'awaiting_payment';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to compute bill: " . $e->getMessage();
        }
    }
}

$current_page = 'billing.php';
require_once '../../includes/header.php';
?>

<style>
.billing-section-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-muted, #64748b);
    margin: 24px 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.billing-section-title:first-of-type { margin-top: 0; }
.billing-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 8px;
}
.billing-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--color-surface, #f9fafb);
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 10px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.billing-item:focus-within {
    border-color: var(--color-primary, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.billing-item.auto-filled {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border-color: #86efac;
}
.billing-item .item-icon {
    font-size: 20px;
    color: var(--color-primary, #6366f1);
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 8px;
    flex-shrink: 0;
}
.billing-item.auto-filled .item-icon {
    color: #16a34a;
    background: rgba(22, 163, 74, 0.1);
}
.billing-item .item-info {
    flex: 1;
    min-width: 0;
}
.billing-item .item-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-heading, #1e293b);
    margin-bottom: 2px;
}
.billing-item .item-category {
    font-size: 11px;
    color: var(--color-muted, #94a3b8);
}
.billing-item input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--color-border, #d1d5db);
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
}
.billing-item input:focus { outline: none; border-color: var(--color-primary, #6366f1); }
.billing-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, var(--color-primary, #6366f1), #818cf8);
    border-radius: 12px;
    color: #fff;
    margin: 24px 0;
}
.billing-total .total-label { font-size: 16px; font-weight: 600; }
.billing-total .total-value { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
.auto-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    background: #dcfce7;
    color: #166534;
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 600;
}
@media (max-width: 768px) { .billing-grid { grid-template-columns: 1fr; } }
</style>

<div class="action-bar hide-on-print">
    <div>
        <h1 class="page-heading">Compute Bill</h1>
        <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Billing <span>›</span> Compute Bill</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?>
    <br><br>
    <a href="billing.php" class="btn btn-primary btn-sm">Return to Billing List</a>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (strtolower(trim((string)($plan['workflow_status'] ?? ''))) === 'pending_billing'): ?>
<div class="card">
    <div class="card-header">
        <h2><i class='bx bx-calculator'></i> Invoice for <?= htmlspecialchars($plan['pet_name']) ?></h2>
    </div>

    <div class="grid grid-2" style="margin-bottom: 24px; background: #fafafa; padding: 16px; border-radius: 8px; border: 1px solid var(--color-border);">
        <div>
            <div class="info-row"><span class="info-label">Pet Name</span><span class="info-value"><strong><?= htmlspecialchars($plan['pet_name']) ?></strong></span></div>
            <div class="info-row"><span class="info-label">Owner Name</span><span class="info-value"><?= htmlspecialchars($plan['owner_name'] ?: 'N/A') ?></span></div>
            <div class="info-row"><span class="info-label">Owner consent</span><span class="info-value"><span class="badge badge-success">Approved</span></span></div>
        </div>
        <div>
            <div class="info-row"><span class="info-label">Plan ID</span><span class="info-value">#<?= $plan_id ?></span></div>
            <div class="info-row"><span class="info-label">Visit ID</span><span class="info-value">#<?= (int) $plan['visit_id'] ?></span></div>
            <?php if (!empty($plan['vet_presenter_name'])): ?>
            <div class="info-row"><span class="info-label">Vet / presenter</span><span class="info-value"><?= htmlspecialchars($plan['vet_presenter_name']) ?></span></div>
            <?php endif; ?>
            <div class="info-row"><span class="info-label">Plan date</span><span class="info-value"><?= htmlspecialchars(date('M d, Y H:i', strtotime($plan['date']))) ?></span></div>
        </div>
    </div>
    <div class="info-row mb-4" style="padding:12px; background:#fff; border-radius:8px; border:1px solid var(--color-border);">
        <span class="info-label" style="display:block; margin-bottom:6px;">Treatment summary (from approved plan)</span>
        <span class="info-value" style="white-space:pre-wrap;"><?= htmlspecialchars($plan['description']) ?></span>
    </div>

    <form method="POST" id="billingForm">

        <?php if (!empty($auto_items)): ?>
        <div class="alert alert-info" style="margin-bottom:16px; font-size:14px;">
            <strong>Owner-approved plan.</strong> Line items below are medications, surgeries, and procedures from treatment plan #<?= (int) $plan_id ?> (pet owner consent: <strong>approved</strong><?php if (!empty($plan['vet_presenter_name'])): ?>, prepared by <?= htmlspecialchars($plan['vet_presenter_name']) ?><?php endif; ?>). Adjust amounts as needed before submitting.
        </div>
        <div class="billing-section-title"><i class='bx bx-check-shield'></i> From Treatment Plan <span class="auto-tag"><i class='bx bx-bolt-circle'></i> Auto-filled</span></div>
        <div class="billing-grid">
            <?php foreach ($auto_items as $i => $item): ?>
            <div class="billing-item auto-filled">
                <div class="item-icon"><i class='bx <?= htmlspecialchars($item['icon'] ?? 'bx-circle') ?>'></i></div>
                <div class="item-info">
                    <div class="item-label"><?= htmlspecialchars($item['label']) ?></div>
                    <div class="item-category"><?= htmlspecialchars($item['category']) ?></div>
                    <input type="hidden" name="auto_label[]" value="<?= htmlspecialchars($item['label']) ?>">
                    <input type="number" step="0.01" min="0" name="auto_amount[]" class="billing-amount" value="<?= number_format((float) ($item['amount'] ?? 0), 2, '.', '') ?>" placeholder="₱ 0.00">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
            <i class='bx bx-info-circle'></i> No medications, surgeries, or procedures were recorded in the treatment plan. Please enter fees manually below.
        </div>
        <?php endif; ?>

        <div class="billing-section-title"><i class='bx bx-edit'></i> Additional Fees</div>
        <div class="billing-grid">
            <?php foreach ($extra_fields as $field): ?>
            <div class="billing-item">
                <div class="item-icon"><i class='bx <?= $field['icon'] ?>'></i></div>
                <div class="item-info">
                    <div class="item-label"><?= htmlspecialchars($field['label']) ?></div>
                    <input type="number" step="0.01" min="0" name="<?= $field['key'] ?>" class="billing-amount" value="0" placeholder="₱ 0.00">
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="billing-total">
            <span class="total-label"><i class='bx bx-receipt'></i> Total Amount</span>
            <span class="total-value" id="totalDisplay">₱ 0.00</span>
        </div>

        <div class="action-bar mt-4">
            <a href="billing.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" id="submitBtn" onclick="return confirm('Are you sure you want to submit this bill of ' + document.getElementById('totalDisplay').textContent + '? The pet owner will be notified to pay.');">
                <i class='bx bx-send'></i> Submit Bill
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.billing-amount');
    const totalDisplay = document.getElementById('totalDisplay');

    function updateTotal() {
        let total = 0;
        inputs.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val) && val > 0) total += val;
        });
        totalDisplay.textContent = '₱ ' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    inputs.forEach(input => {
        input.addEventListener('input', updateTotal);
        input.addEventListener('focus', function() {
            if (this.value === '0' || this.value === '0.00') this.value = '';
        });
        input.addEventListener('blur', function() {
            if (this.value === '') this.value = '0';
        });
    });

    // Calculate initial total from auto-filled items
    updateTotal();
});
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
