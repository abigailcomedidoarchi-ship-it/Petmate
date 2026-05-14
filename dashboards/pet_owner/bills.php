<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('pet_owner');
require_permission('view_dashboard');
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT b.*, p.name as pet_name, pr.visit_date FROM bills b
                       JOIN pet_records pr ON b.visit_id = pr.id
                       JOIN pets p ON pr.pet_id = p.id
                       WHERE b.owner_id = ? ORDER BY b.date DESC");
$stmt->execute([$user_id]);
$bills = $stmt->fetchAll();

// Fetch line items for each bill
$bill_items_map = [];
if (!empty($bills)) {
    $bill_ids = array_column($bills, 'id');
    $placeholders = implode(',', array_fill(0, count($bill_ids), '?'));
    $stmtItems = $pdo->prepare("SELECT * FROM bill_items WHERE bill_id IN ($placeholders) ORDER BY id ASC");
    $stmtItems->execute($bill_ids);
    foreach ($stmtItems->fetchAll() as $item) {
        $bill_items_map[$item['bill_id']][] = $item;
    }
}

$current_page = 'bills';
require_once '../../includes/header.php';
?>

<style>
.bill-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 12px;
    padding: 0;
    margin-bottom: 16px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.bill-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.bill-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--color-border, #e5e7eb);
    gap: 12px;
    flex-wrap: wrap;
}
.bill-card-header .bill-pet {
    font-weight: 700;
    font-size: 16px;
    color: var(--color-heading, #1e293b);
}
.bill-card-header .bill-date {
    font-size: 12px;
    color: var(--color-muted, #94a3b8);
}
.bill-card-header .bill-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.bill-items-table {
    width: 100%;
    border-collapse: collapse;
}
.bill-items-table td {
    padding: 10px 20px;
    border-bottom: 1px solid var(--color-border, #f1f5f9);
    font-size: 14px;
}
.bill-items-table tr:last-child td {
    border-bottom: none;
}
.bill-items-table .item-name {
    color: var(--color-text, #475569);
}
.bill-items-table .item-amount {
    text-align: right;
    font-weight: 600;
    color: var(--color-heading, #1e293b);
    white-space: nowrap;
}
.bill-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-top: 1px solid var(--color-border, #e5e7eb);
}
.bill-total {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-heading, #1e293b);
}
.bill-total-label {
    font-size: 12px;
    color: var(--color-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Bills &amp; Payments</h1>
    <p class="breadcrumb">PetMate <span>›</span> Bills &amp; Payments</p>
  </div>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<?php if (empty($bills)): ?>
<div class="card">
  <div class="empty-state">
    <i class='bx bx-receipt'></i>
    <p>No billing records found.</p>
  </div>
</div>
<?php else: ?>
  <?php foreach ($bills as $bill): ?>
  <div class="bill-card">
    <div class="bill-card-header">
      <div>
        <div class="bill-pet"><i class='bx bx-paw' style="margin-right:4px;"></i> <?= htmlspecialchars($bill['pet_name']) ?></div>
        <div class="bill-date">Visit: <?= date('M d, Y', strtotime($bill['visit_date'])) ?></div>
      </div>
      <div class="bill-meta">
        <span class="badge badge-<?= $bill['status'] ?>"><?= ucfirst($bill['status']) ?></span>
      </div>
    </div>

    <?php if (!empty($bill_items_map[$bill['id']])): ?>
    <table class="bill-items-table">
      <?php foreach ($bill_items_map[$bill['id']] as $item): ?>
      <tr>
        <td class="item-name"><?= htmlspecialchars($item['item_name']) ?></td>
        <td class="item-amount">₱ <?= number_format($item['amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <div class="bill-card-footer">
      <div>
        <div class="bill-total-label">Total Amount</div>
        <div class="bill-total">₱ <?= number_format($bill['amount'], 2) ?></div>
      </div>
      <?php if ($bill['status'] === 'unpaid'): ?>
        <a href="pay.php?bill_id=<?= $bill['id'] ?>" class="btn btn-accent"><i class='bx bx-credit-card'></i> Pay Now</a>
      <?php else: ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <span style="color:var(--color-success); font-size:14px; font-weight:600;"><i class='bx bx-check-circle'></i> Paid</span>
          <a href="receipt.php?bill_id=<?= $bill['id'] ?>" class="btn btn-sm btn-outline" title="Print Receipt"><i class='bx bx-printer'></i> Print Receipt</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>