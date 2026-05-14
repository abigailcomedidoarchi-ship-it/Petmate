<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireRole('pet_owner');
require_permission('view_dashboard');

$user_id = $_SESSION['user_id'];

// Fetch all visits with treatment plans and discharge summaries for this owner's pets
$stmt = $pdo->prepare("
    SELECT 
        pr.id AS visit_id, pr.visit_date, pr.primary_reason, pr.symptoms, pr.notes AS visit_notes, pr.status AS visit_status,
        p.id AS pet_id, p.name AS pet_name, p.species, p.breed,
        tp.id AS plan_id, tp.description AS plan_description, tp.date AS plan_date, 
        tp.workflow_status, tp.consent_status, tp.prescriptions,
        ds.id AS discharge_id, ds.discharge_notes, ds.home_care, ds.follow_up_date, ds.warnings, ds.created_at AS discharge_date,
        va.name AS discharge_by
    FROM pet_records pr
    JOIN pets p ON pr.pet_id = p.id
    LEFT JOIN treatment_plans tp ON tp.pet_id = p.id 
        AND tp.id = (
            SELECT tp2.id FROM treatment_plans tp2 
            WHERE tp2.pet_id = p.id 
            ORDER BY ABS(DATEDIFF(tp2.date, pr.visit_date)) ASC, tp2.id DESC 
            LIMIT 1
        )
    LEFT JOIN discharge_summaries ds ON ds.plan_id = tp.id
        AND ds.id = (
            SELECT ds2.id FROM discharge_summaries ds2
            WHERE ds2.plan_id = tp.id
            ORDER BY ds2.created_at DESC, ds2.id DESC
            LIMIT 1
        )
    LEFT JOIN users va ON va.id = ds.vet_assistant_id
    WHERE p.owner_id = ?
    ORDER BY pr.visit_date DESC, pr.id DESC
");
$stmt->execute([$user_id]);
$visits = $stmt->fetchAll();

$current_page = 'visit_records';
require_once '../../includes/header.php';
?>

<style>
.visit-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.visit-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.visit-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--color-border, #e5e7eb);
    flex-wrap: wrap;
    gap: 8px;
}
.visit-pet-name {
    font-weight: 700;
    font-size: 17px;
    color: var(--color-heading, #1e293b);
}
.visit-date {
    font-size: 13px;
    color: var(--color-muted, #94a3b8);
}
.visit-card-body {
    padding: 16px 20px;
}
.visit-section {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px dashed var(--color-border, #e5e7eb);
}
.visit-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.visit-section-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-primary, #6366f1);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.discharge-box {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #86efac;
    border-radius: 10px;
    padding: 16px;
}
.discharge-box .info-row {
    margin-bottom: 6px;
}
.homecare-box {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 10px;
}
.warning-box {
    background: #fff5f5;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 8px;
    color: #991b1b;
    font-size: 13px;
}
.followup-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--color-primary, #6366f1);
    color: #fff;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
}
</style>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Visit Records</h1>
    <p class="breadcrumb">PetMate <span>›</span> Pet Owner <span>›</span> Visit Records</p>
  </div>
</div>

<?php if (empty($visits)): ?>
<div class="card">
  <div class="empty-state">
    <i class='bx bx-file'></i>
    <p>No visit records found. Submit a visit request to get started.</p>
    <a href="submit_visit.php" class="btn btn-primary" style="margin-top:12px;"><i class='bx bx-plus'></i> Submit Visit</a>
  </div>
</div>
<?php else: ?>
  <?php foreach ($visits as $visit): ?>
  <div class="visit-card">
    <div class="visit-card-header">
      <div>
        <span class="visit-pet-name"><i class='bx bx-paw'></i> <?= htmlspecialchars($visit['pet_name']) ?></span>
        <span style="color:var(--color-muted); font-size:13px; margin-left:6px;">(<?= htmlspecialchars(($visit['species'] ?: '') . ($visit['breed'] ? ' / ' . $visit['breed'] : '')) ?>)</span>
        <div class="visit-date"><i class='bx bx-calendar'></i> Visit: <?= date('M d, Y', strtotime($visit['visit_date'])) ?></div>
      </div>
      <div style="display:flex; align-items:center; gap:8px;">
        <?php
          $vs = $visit['visit_status'] ?? '';
          $vsBadge = 'badge badge-outline';
          switch($vs) {
            case 'pending': $vsBadge = 'badge badge-warning'; break;
            case 'validated': $vsBadge = 'badge badge-primary'; break;
            case 'completed': $vsBadge = 'badge badge-success'; break;
            case 'pending_billing': $vsBadge = 'badge badge-accent'; break;
            case 'awaiting_payment': $vsBadge = 'badge badge-accent'; break;
            case 'discharged': $vsBadge = 'badge badge-success'; break;
          }
        ?>
        <span class="<?= $vsBadge ?>"><?= ucfirst(str_replace('_', ' ', $vs)) ?></span>
        <?php if ($visit['plan_id']): ?>
          <a href="view_treatment.php?id=<?= (int)$visit['plan_id'] ?>" class="btn btn-sm btn-outline"><i class='bx bx-file'></i> Treatment Plan</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="visit-card-body">
      <!-- Visit Reason & Symptoms -->
      <div class="visit-section">
        <div class="visit-section-title"><i class='bx bx-info-circle'></i> Visit Details</div>
        <div class="grid grid-2">
          <?php if (!empty($visit['primary_reason'])): ?>
          <div class="info-row"><span class="info-label">Reason</span><span class="info-value"><?= htmlspecialchars($visit['primary_reason']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($visit['symptoms'])): ?>
          <div class="info-row"><span class="info-label">Symptoms</span><span class="info-value"><?= htmlspecialchars($visit['symptoms']) ?></span></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($visit['visit_notes'])): ?>
        <div class="info-row mt-2"><span class="info-label">Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($visit['visit_notes'])) ?></span></div>
        <?php endif; ?>
      </div>

      <!-- Treatment Plan Summary -->
      <?php if ($visit['plan_id']): ?>
      <div class="visit-section">
        <div class="visit-section-title"><i class='bx bx-capsule'></i> Treatment Summary</div>
        <div class="info-row"><span class="info-label">Plan #<?= (int)$visit['plan_id'] ?></span><span class="info-value"><?= htmlspecialchars($visit['plan_description'] ?: '—') ?></span></div>
        <?php
          $rx = json_decode($visit['prescriptions'] ?? '', true) ?: [];
          $meds = $rx['medicines'] ?? [];
          $surgs = $rx['surgeries'] ?? [];
          $procs = $rx['procedures'] ?? [];
        ?>
        <?php if (!empty($meds)): ?>
        <div style="margin-top:8px;">
          <span class="info-label" style="display:block; margin-bottom:4px;">Medications</span>
          <?php foreach ($meds as $m): ?>
            <span style="display:inline-block; background:#f1f5f9; padding:4px 10px; border-radius:6px; font-size:12px; margin:2px 4px 2px 0; font-weight:500;">
              <?= htmlspecialchars($m['medicine_name'] ?? '') ?> — <?= htmlspecialchars($m['dosage'] ?? '') ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($surgs)): ?>
        <div style="margin-top:6px;">
          <span class="info-label" style="display:block; margin-bottom:4px;">Surgeries</span>
          <?php foreach ($surgs as $s): ?>
            <span style="display:inline-block; background:#fef2f2; padding:4px 10px; border-radius:6px; font-size:12px; margin:2px 4px 2px 0; font-weight:500;">
              <?= htmlspecialchars($s['name'] ?? '') ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($procs)): ?>
        <div style="margin-top:6px;">
          <span class="info-label" style="display:block; margin-bottom:4px;">Procedures</span>
          <?php foreach ($procs as $pr): ?>
            <span style="display:inline-block; background:#eff6ff; padding:4px 10px; border-radius:6px; font-size:12px; margin:2px 4px 2px 0; font-weight:500;">
              <?= htmlspecialchars($pr['name'] ?? '') ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Discharge Summary -->
      <?php if ($visit['discharge_id']): ?>
      <div class="visit-section">
        <div class="visit-section-title"><i class='bx bx-check-shield'></i> Discharge Summary</div>
        <div class="discharge-box">
          <div class="info-row"><span class="info-label">Discharged On</span><span class="info-value"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($visit['discharge_date']))) ?></span></div>
          <div class="info-row"><span class="info-label">Prepared By</span><span class="info-value"><?= htmlspecialchars($visit['discharge_by'] ?: '—') ?></span></div>
          
          <?php if (!empty($visit['discharge_notes'])): ?>
          <div class="info-row mt-2"><span class="info-label">Discharge Notes</span><span class="info-value"><?= nl2br(htmlspecialchars($visit['discharge_notes'])) ?></span></div>
          <?php endif; ?>

          <?php if (!empty($visit['home_care'])): ?>
          <div class="homecare-box">
            <strong style="font-size:13px; color:#92400e;"><i class='bx bx-home-heart'></i> Home Care Instructions</strong>
            <p style="margin-top:6px; font-size:14px; color:#78350f;"><?= nl2br(htmlspecialchars($visit['home_care'])) ?></p>
          </div>
          <?php endif; ?>

          <?php if (!empty($visit['warnings'])): ?>
          <div class="warning-box">
            <strong><i class='bx bx-error-triangle'></i> Signs to Watch For</strong><br>
            <?= nl2br(htmlspecialchars($visit['warnings'])) ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($visit['follow_up_date'])): ?>
          <div class="followup-badge">
            <i class='bx bx-calendar-check'></i> Follow-up: <?= date('M d, Y', strtotime($visit['follow_up_date'])) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>