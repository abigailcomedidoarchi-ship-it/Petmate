<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('csr');
require_permission('view_dashboard');

$stmtBilling = $pdo->query("SELECT tp.id AS plan_id, tp.description AS plan_description, p.name AS pet_name, u.name AS owner_name,
    (SELECT visit_date FROM pet_records WHERE pet_id = p.id ORDER BY id DESC LIMIT 1) AS visit_date
    FROM treatment_plans tp
    JOIN pets p ON p.id = tp.pet_id
    JOIN users u ON p.owner_id = u.id
    WHERE LOWER(TRIM(COALESCE(tp.workflow_status, ''))) = 'pending_billing'
      AND LOWER(TRIM(COALESCE(tp.consent_status, ''))) = 'approved'
    ORDER BY tp.date DESC");
$pending_billing = $stmtBilling->fetchAll();

$current_page = 'billing.php';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Billing</h1>
    <p class="breadcrumb">PetMate <span>›</span> CSR <span>›</span> Billing</p>
  </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class='bx bx-receipt'></i> Pending Billing</h2>
        <?php if(count($pending_billing) > 0): ?>
           <span class="badge badge-pending"><?= count($pending_billing) ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($pending_billing)): ?>
    <div class="empty-state">
        <i class='bx bx-receipt'></i>
        <p>No pending billing actions at this time.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Treatment plan</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_billing as $bill): ?>
                <tr>
                    <td style="font-size:12px; color:var(--color-muted);"><?= !empty($bill['visit_date']) ? htmlspecialchars(date('M d, Y', strtotime($bill['visit_date']))) : '—' ?></td>
                    <td><strong><?= htmlspecialchars($bill['pet_name']) ?></strong></td>
                    <td><?= htmlspecialchars($bill['owner_name']) ?></td>
                    <td style="max-width:280px; font-size:13px;">
                        <strong>#<?= (int)$bill['plan_id'] ?></strong>
                        <div style="color:var(--color-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($bill['plan_description'] ?? '') ?>">
                            <?= htmlspecialchars($bill['plan_description'] ?? '') ?>
                        </div>
                    </td>
                    <td>
                        <a href="compute_bill.php?plan_id=<?= $bill['plan_id'] ?>" class="btn btn-primary btn-sm">
                            <i class='bx bx-calculator'></i> Compute Bill
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// Fetch paid bills for the history section
$stmtPaid = $pdo->query("
    SELECT b.id AS bill_id, b.amount, b.date AS bill_date, b.status,
           p.name AS pet_name, u.name AS owner_name,
           (SELECT visit_date FROM pet_records WHERE id = b.visit_id LIMIT 1) AS visit_date
    FROM bills b
    JOIN users u ON b.owner_id = u.id
    JOIN pet_records pr ON b.visit_id = pr.id
    JOIN pets p ON pr.pet_id = p.id
    WHERE b.status = 'paid'
    ORDER BY b.date DESC
    LIMIT 50
");
$paid_bills = $stmtPaid->fetchAll();
?>

<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <h2 class="card-title"><i class='bx bx-check-circle'></i> Paid Bills</h2>
        <?php if(count($paid_bills) > 0): ?>
           <span class="badge badge-success"><?= count($paid_bills) ?></span>
        <?php endif; ?>
    </div>
    <?php if (empty($paid_bills)): ?>
    <div class="empty-state">
        <i class='bx bx-receipt'></i>
        <p>No paid bills yet.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Date</th>
                    <th>Pet</th>
                    <th>Owner</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paid_bills as $pb): ?>
                <tr>
                    <td><strong>#<?= str_pad($pb['bill_id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                    <td style="font-size:12px; color:var(--color-muted);"><?= htmlspecialchars(date('M d, Y', strtotime($pb['bill_date']))) ?></td>
                    <td><strong><?= htmlspecialchars($pb['pet_name']) ?></strong></td>
                    <td><?= htmlspecialchars($pb['owner_name']) ?></td>
                    <td style="font-weight:600;">₱ <?= number_format($pb['amount'], 2) ?></td>
                    <td><span class="badge badge-success">Paid</span></td>
                    <td>
                        <a href="/Petmate/dashboards/csr/print_receipt.php?bill_id=<?= $pb['bill_id'] ?>" class="btn btn-sm btn-outline" title="Print Receipt">
                            <i class='bx bx-printer'></i> Print
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>