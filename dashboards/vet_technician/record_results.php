<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/rbac.php';

requireRole('vet_technician');
require_permission('view_dashboard');

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($session_id <= 0 && isset($_GET['id'])) {
    $legacy_id = (int)$_GET['id'];
    $legacyStmt = $pdo->prepare("SELECT assessment_session_id FROM assessments WHERE id = ? LIMIT 1");
    $legacyStmt->execute([$legacy_id]);
    $legacy = $legacyStmt->fetch();
    if (!empty($legacy['assessment_session_id'])) {
        $session_id = (int)$legacy['assessment_session_id'];
    }
}
$error = '';
$success = '';

$sessionStmt = $pdo->prepare("
    SELECT s.*, p.name AS pet_name, rm.room_name
    FROM assessment_sessions s
    JOIN pets p ON p.id = s.pet_id
    LEFT JOIN examination_rooms er ON er.id = s.room_id
    LEFT JOIN rooms rm ON rm.id = er.room_id
    WHERE s.id = ?
");
$sessionStmt->execute([$session_id]);
$session = $sessionStmt->fetch();

$assessments = [];
if ($session) {
    $rowsStmt = $pdo->prepare("SELECT * FROM assessments WHERE assessment_session_id = ? ORDER BY id ASC");
    $rowsStmt->execute([$session_id]);
    $assessments = $rowsStmt->fetchAll();
}

if (!$session || empty($assessments)) {
    $error = 'Assessment session not found.';
}

function postf($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        $pdo->beginTransaction();
        $updateStmt = $pdo->prepare("UPDATE assessments SET result_data = ?, result = ?, status = 'completed', updated_at = NOW() WHERE id = ?");

        foreach ($assessments as $test) {
            $id = (int)$test['id'];
            $type = $test['test_type'];
            $resultData = [];
            $summary = '';

            if ($type === 'cbc') {
                $fields = ['ccrp','wbc','lym','eos','rbc','hemoglobin','hematocrit','mcv','mch','mchc','platelets','interpretation'];
                foreach ($fields as $f) {
                    $resultData[$f] = postf("{$f}_{$id}");
                }
                $summary = "CBC WBC {$resultData['wbc']}, RBC {$resultData['rbc']}, Hgb {$resultData['hemoglobin']}";
            } elseif ($type === 'chemistry') {
                $fields = ['total_protein','albumin','glob','alt','alp','bun','creatinine','glucose','interpretation'];
                foreach ($fields as $f) {
                    $resultData[$f] = postf("{$f}_{$id}");
                }
                $summary = "Chem ALT {$resultData['alt']}, BUN {$resultData['bun']}, Creatinine {$resultData['creatinine']}";
            } elseif ($type === 'microscopy') {
                $fields = ['sample_type','findings','parasites_found','parasites_specify','abnormal_cells','abnormal_cells_desc','slide_notes'];
                foreach ($fields as $f) {
                    $resultData[$f] = postf("{$f}_{$id}");
                }
                $summary = "Microscopy {$resultData['sample_type']} - " . ($resultData['findings'] ?: 'No findings');
            } elseif ($type === 'test_kit') {
                $kitTypes = $_POST["kit_type_{$id}"] ?? [];
                $kitResults = $_POST["kit_result_{$id}"] ?? [];
                $kitNotes = $_POST["kit_notes_{$id}"] ?? [];
                $kits = [];
                for ($i = 0; $i < count($kitTypes); $i++) {
                    if (trim((string)$kitTypes[$i]) === '') {
                        continue;
                    }
                    $kits[] = [
                        'kit_type' => trim((string)$kitTypes[$i]),
                        'result' => trim((string)($kitResults[$i] ?? '')),
                        'notes' => trim((string)($kitNotes[$i] ?? ''))
                    ];
                }
                $resultData['kits'] = $kits;
                $summary = empty($kits) ? 'No kit entries' : ('Kit ' . $kits[0]['kit_type'] . ': ' . $kits[0]['result']);
            }

            $updateStmt->execute([json_encode($resultData), $summary, $id]);
        }

        $pdo->prepare("UPDATE assessment_sessions SET status = 'submitted' WHERE id = ?")->execute([$session_id]);
        $pdo->prepare("UPDATE pet_records SET status = 'assessed' WHERE pet_id = ? AND status = 'validated' ORDER BY visit_date DESC LIMIT 1")
            ->execute([(int)$session['pet_id']]);
        $pdo->commit();

        header("Location: /Petmate/dashboards/vet_technician/assessments.php?session_id={$session_id}");
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Failed to save results: ' . $e->getMessage();
    }
}

$current_page = 'record_results';
require_once '../../includes/header.php';
?>
<div class="action-bar">
  <div>
    <h1 class="page-heading">Record Results</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Record Results</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<style>
.section-card { background: var(--color-background-primary); border-radius: var(--border-radius-lg); border: 0.5px solid var(--color-border-tertiary); margin-bottom: 1rem; overflow: hidden; }
.card-header { padding: 14px 18px; border-bottom: 0.5px solid var(--color-border-tertiary); display: flex; align-items: center; gap: 10px; }
.card-header-icon { width: 32px; height: 32px; border-radius: var(--border-radius-md); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.icon-blue { background: #E6F1FB; color: #0C447C; }
.icon-amber { background: #FAEEDA; color: #633806; }
.icon-teal { background: #E1F5EE; color: #085041; }
.icon-purple { background: #EEEDFE; color: #3C3489; }
.card-title { font-size: 15px; font-weight: 500; color: var(--color-text-primary); }
.card-subtitle { font-size: 12px; color: var(--color-text-secondary); margin-top: 1px; }
.card-body { padding: 14px 18px; }
.section-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .06em; color: var(--color-text-secondary); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 0.5px solid var(--color-border-tertiary); }
.field-label { font-size: 12px; color: var(--color-text-secondary); display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.ref-range { font-size: 10px; font-family: var(--font-mono); opacity: .7; }
.field-input-wrap { position: relative; }
.field-input { width: 100%; border: 0.5px solid var(--color-border-secondary); border-radius: var(--border-radius-md); padding: 7px 44px 7px 10px; font-size: 13px; }
.field-unit { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 11px; color: var(--color-text-secondary); font-family: var(--font-mono); pointer-events: none; }
.ref-bar-track { height: 4px; background: var(--color-border-tertiary); border-radius: 2px; position: relative; margin-top: 6px; }
.ref-bar-range { height: 4px; background: #E1F5EE; position: absolute; border-radius: 2px; left: 0; width: 100%; }
.ref-bar-indicator { width: 8px; height: 8px; border-radius: 50%; position: absolute; top: -2px; transform: translateX(-50%); border: 1.5px solid var(--color-background-primary); display: none; }
.ind-normal { background: #1D9E75; } .ind-low { background: #185FA5; } .ind-high { background: #E24B4A; }
.status-pill { display: none; align-items: center; gap: 4px; font-size: 10px; padding: 2px 7px; border-radius: 99px; font-weight: 500; margin-top: 4px; }
.sp-normal { background: #E1F5EE; color: #085041; } .sp-low { background: #E6F1FB; color: #0C447C; } .sp-high { background: #FCEBEB; color: #791F1F; }
.ref-legend { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--color-text-secondary); }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.separator { height: 1px; background: var(--color-border-tertiary); margin: 16px 0; }
.pat-banner { background: var(--color-background-secondary); border-radius: var(--border-radius-lg); border: 0.5px solid var(--color-border-tertiary); padding: 12px 16px; display: flex; gap: 24px; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
.pat-label { font-size: 11px; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: .05em; display: block; }
.pat-val { font-size: 14px; font-weight: 500; color: var(--color-text-primary); display: block; }
.micro-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.kit-grid { display: grid; grid-template-columns: 1fr 1fr 1.5fr 44px; gap: 8px; align-items: end; margin-bottom: 8px; }
.kit-remove-btn { width: 28px; height: 28px; border-radius: 8px; border: 0.5px solid var(--color-border-secondary); background: transparent; cursor: pointer; }
.bottom-action-bar { border-top: 0.5px solid var(--color-border-tertiary); display: flex; justify-content: space-between; align-items: center; padding-top: 12px; margin-top: 10px; }
.btn-pill { border-radius: 999px; }
</style>

<?php if (!$error && $session): ?>
  <div class="pat-banner">
    <div><span class="pat-label">Patient</span><span class="pat-val"><?= htmlspecialchars($session['pet_name']) ?></span></div>
    <div><span class="pat-label">Room</span><span class="pat-val"><?= htmlspecialchars($session['room_name'] ?: 'N/A') ?></span></div>
    <div><span class="pat-label">Session ID</span><span class="pat-val">#AS-<?= (int)$session['id'] ?></span></div>
    <div><span class="pat-label">Technician</span><span class="pat-val"><?= htmlspecialchars($_SESSION['name'] ?? 'N/A') ?></span></div>
  </div>

  <form method="POST">
    <?php foreach ($assessments as $test): $id = (int)$test['id']; ?>
      <div class="section-card">
        <div class="card-header">
          <?php if ($test['test_type'] === 'cbc'): ?>
            <div class="card-header-icon icon-blue"><i class='bx bx-test-tube'></i></div>
            <div><div class="card-title">CBC - Complete Blood Count</div><div class="card-subtitle">reference ranges for adult canine</div></div>
          <?php elseif ($test['test_type'] === 'chemistry'): ?>
            <div class="card-header-icon icon-amber"><i class='bx bx-droplet'></i></div>
            <div><div class="card-title">Blood Chemistry Profile</div><div class="card-subtitle">reference ranges for adult canine</div></div>
          <?php elseif ($test['test_type'] === 'microscopy'): ?>
            <div class="card-header-icon icon-purple"><i class='bx bx-search-alt'></i></div>
            <div><div class="card-title">Microscopy Findings</div><div class="card-subtitle">qualitative microscopy observations</div></div>
          <?php else: ?>
            <div class="card-header-icon icon-teal"><i class='bx bx-first-aid'></i></div>
            <div><div class="card-title">Rapid Test Kit Results</div><div class="card-subtitle">capture kit type and result per row</div></div>
          <?php endif; ?>
        </div>

        <div class="card-body">
        <?php if ($test['test_type'] === 'cbc'): ?>
          <div class="ref-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#1D9E75"></span>within range</span>
            <span class="legend-item"><span class="legend-dot" style="background:#185FA5"></span>below range</span>
            <span class="legend-item"><span class="legend-dot" style="background:#E24B4A"></span>above range</span>
          </div>

          <div class="section-label">1 · Inflammatory marker</div>
          <div class="grid grid-3">
            <div class="form-group">
              <div class="field-label">cCRp <span class="ref-range">0.00 - 10.00</span></div>
              <div class="field-input-wrap"><input name="ccrp_<?= $id ?>" class="field-input ref-field" data-min="0" data-max="10" data-bar="ind_ccrp_<?= $id ?>" data-pill="pill_ccrp_<?= $id ?>"><span class="field-unit">mg/L</span></div>
              <div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_ccrp_<?= $id ?>"></div></div><span class="status-pill" id="pill_ccrp_<?= $id ?>"></span>
            </div>
          </div>

          <div class="separator"></div>
          <div class="section-label">2 · White blood cells</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">WBC <span class="ref-range">5.05 - 16.75</span></div><div class="field-input-wrap"><input name="wbc_<?= $id ?>" class="field-input ref-field" data-min="5.05" data-max="16.75" data-bar="ind_wbc_<?= $id ?>" data-pill="pill_wbc_<?= $id ?>"><span class="field-unit">×10³/µL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_wbc_<?= $id ?>"></div></div><span class="status-pill" id="pill_wbc_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">LYM <span class="ref-range">1.05 - 5.10</span></div><div class="field-input-wrap"><input name="lym_<?= $id ?>" class="field-input ref-field" data-min="1.05" data-max="5.10" data-bar="ind_lym_<?= $id ?>" data-pill="pill_lym_<?= $id ?>"><span class="field-unit">×10³/µL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_lym_<?= $id ?>"></div></div><span class="status-pill" id="pill_lym_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">EOS <span class="ref-range">0.06 - 1.23</span></div><div class="field-input-wrap"><input name="eos_<?= $id ?>" class="field-input ref-field" data-min="0.06" data-max="1.23" data-bar="ind_eos_<?= $id ?>" data-pill="pill_eos_<?= $id ?>"><span class="field-unit">×10³/µL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_eos_<?= $id ?>"></div></div><span class="status-pill" id="pill_eos_<?= $id ?>"></span></div>
          </div>

          <div class="separator"></div>
          <div class="section-label">3 · Red blood cells</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">RBC <span class="ref-range">1.05 - 5.10</span></div><div class="field-input-wrap"><input name="rbc_<?= $id ?>" class="field-input ref-field" data-min="1.05" data-max="5.10" data-bar="ind_rbc_<?= $id ?>" data-pill="pill_rbc_<?= $id ?>"><span class="field-unit">×10⁶/µL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_rbc_<?= $id ?>"></div></div><span class="status-pill" id="pill_rbc_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">Hemoglobin <span class="ref-range">13.10 - 20.50</span></div><div class="field-input-wrap"><input name="hemoglobin_<?= $id ?>" class="field-input ref-field" data-min="13.10" data-max="20.50" data-bar="ind_hemoglobin_<?= $id ?>" data-pill="pill_hemoglobin_<?= $id ?>"><span class="field-unit">g/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_hemoglobin_<?= $id ?>"></div></div><span class="status-pill" id="pill_hemoglobin_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">Hematocrit <span class="ref-range">37.30 - 61.70</span></div><div class="field-input-wrap"><input name="hematocrit_<?= $id ?>" class="field-input ref-field" data-min="37.30" data-max="61.70" data-bar="ind_hematocrit_<?= $id ?>" data-pill="pill_hematocrit_<?= $id ?>"><span class="field-unit">%</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_hematocrit_<?= $id ?>"></div></div><span class="status-pill" id="pill_hematocrit_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">MCV <span class="ref-range">61.60 - 73.50</span></div><div class="field-input-wrap"><input name="mcv_<?= $id ?>" class="field-input ref-field" data-min="61.60" data-max="73.50" data-bar="ind_mcv_<?= $id ?>" data-pill="pill_mcv_<?= $id ?>"><span class="field-unit">fL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_mcv_<?= $id ?>"></div></div><span class="status-pill" id="pill_mcv_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">MCH <span class="ref-range">21.20 - 25.90</span></div><div class="field-input-wrap"><input name="mch_<?= $id ?>" class="field-input ref-field" data-min="21.20" data-max="25.90" data-bar="ind_mch_<?= $id ?>" data-pill="pill_mch_<?= $id ?>"><span class="field-unit">pg</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_mch_<?= $id ?>"></div></div><span class="status-pill" id="pill_mch_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">MCHC <span class="ref-range">32.00 - 37.90</span></div><div class="field-input-wrap"><input name="mchc_<?= $id ?>" class="field-input ref-field" data-min="32.00" data-max="37.90" data-bar="ind_mchc_<?= $id ?>" data-pill="pill_mchc_<?= $id ?>"><span class="field-unit">g/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_mchc_<?= $id ?>"></div></div><span class="status-pill" id="pill_mchc_<?= $id ?>"></span></div>
          </div>

          <div class="separator"></div>
          <div class="section-label">4 · Platelets</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">Platelets <span class="ref-range">148.00 - 484.00</span></div><div class="field-input-wrap"><input name="platelets_<?= $id ?>" class="field-input ref-field" data-min="148" data-max="484" data-bar="ind_platelets_<?= $id ?>" data-pill="pill_platelets_<?= $id ?>"><span class="field-unit">×10³/µL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_platelets_<?= $id ?>"></div></div><span class="status-pill" id="pill_platelets_<?= $id ?>"></span></div>
          </div>
          <div class="form-group"><label>Clinical interpretation</label><textarea name="interpretation_<?= $id ?>" rows="3"></textarea></div>

        <?php elseif ($test['test_type'] === 'chemistry'): ?>
          <div class="ref-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#1D9E75"></span>within range</span>
            <span class="legend-item"><span class="legend-dot" style="background:#185FA5"></span>below range</span>
            <span class="legend-item"><span class="legend-dot" style="background:#E24B4A"></span>above range</span>
          </div>
          <div class="section-label">Protein panel</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">Total Protein <span class="ref-range">5.31 - 7.92</span></div><div class="field-input-wrap"><input name="total_protein_<?= $id ?>" class="field-input ref-field" data-min="5.31" data-max="7.92" data-bar="ind_total_protein_<?= $id ?>" data-pill="pill_total_protein_<?= $id ?>"><span class="field-unit">g/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_total_protein_<?= $id ?>"></div></div><span class="status-pill" id="pill_total_protein_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">Albumin <span class="ref-range">2.34 - 4.00</span></div><div class="field-input-wrap"><input name="albumin_<?= $id ?>" class="field-input ref-field" data-min="2.34" data-max="4.00" data-bar="ind_albumin_<?= $id ?>" data-pill="pill_albumin_<?= $id ?>"><span class="field-unit">g/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_albumin_<?= $id ?>"></div></div><span class="status-pill" id="pill_albumin_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">GLOB <span class="ref-range">2.54 - 5.20</span></div><div class="field-input-wrap"><input name="glob_<?= $id ?>" class="field-input ref-field" data-min="2.54" data-max="5.20" data-bar="ind_glob_<?= $id ?>" data-pill="pill_glob_<?= $id ?>"><span class="field-unit">g/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_glob_<?= $id ?>"></div></div><span class="status-pill" id="pill_glob_<?= $id ?>"></span></div>
          </div>
          <div class="separator"></div>
          <div class="section-label">Liver panel</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">ALT <span class="ref-range">10.1 - 100.3</span></div><div class="field-input-wrap"><input name="alt_<?= $id ?>" class="field-input ref-field" data-min="10.1" data-max="100.3" data-bar="ind_alt_<?= $id ?>" data-pill="pill_alt_<?= $id ?>"><span class="field-unit">U/L</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_alt_<?= $id ?>"></div></div><span class="status-pill" id="pill_alt_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">ALP <span class="ref-range">15.5 - 212.0</span></div><div class="field-input-wrap"><input name="alp_<?= $id ?>" class="field-input ref-field" data-min="15.5" data-max="212.0" data-bar="ind_alp_<?= $id ?>" data-pill="pill_alp_<?= $id ?>"><span class="field-unit">U/L</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_alp_<?= $id ?>"></div></div><span class="status-pill" id="pill_alp_<?= $id ?>"></span></div>
          </div>
          <div class="separator"></div>
          <div class="section-label">Kidney panel</div>
          <div class="grid grid-3">
            <div class="form-group"><div class="field-label">BUN <span class="ref-range">7.02 - 27.45</span></div><div class="field-input-wrap"><input name="bun_<?= $id ?>" class="field-input ref-field" data-min="7.02" data-max="27.45" data-bar="ind_bun_<?= $id ?>" data-pill="pill_bun_<?= $id ?>"><span class="field-unit">mg/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_bun_<?= $id ?>"></div></div><span class="status-pill" id="pill_bun_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">Creatinine <span class="ref-range">0.23 - 1.40</span></div><div class="field-input-wrap"><input name="creatinine_<?= $id ?>" class="field-input ref-field" data-min="0.23" data-max="1.40" data-bar="ind_creatinine_<?= $id ?>" data-pill="pill_creatinine_<?= $id ?>"><span class="field-unit">mg/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_creatinine_<?= $id ?>"></div></div><span class="status-pill" id="pill_creatinine_<?= $id ?>"></span></div>
            <div class="form-group"><div class="field-label">Glucose <span class="ref-range">68.5 - 135.2</span></div><div class="field-input-wrap"><input name="glucose_<?= $id ?>" class="field-input ref-field" data-min="68.5" data-max="135.2" data-bar="ind_glucose_<?= $id ?>" data-pill="pill_glucose_<?= $id ?>"><span class="field-unit">mg/dL</span></div><div class="ref-bar-track"><div class="ref-bar-range"></div><div class="ref-bar-indicator" id="ind_glucose_<?= $id ?>"></div></div><span class="status-pill" id="pill_glucose_<?= $id ?>"></span></div>
          </div>
          <div class="form-group"><label>Clinical interpretation</label><textarea name="interpretation_<?= $id ?>" rows="3"></textarea></div>

        <?php elseif ($test['test_type'] === 'microscopy'): ?>
          <div class="micro-grid">
            <div class="form-group"><label>Sample type</label><select name="sample_type_<?= $id ?>"><option>Blood Film</option><option>Urine</option><option>Fecal</option><option>Skin Scraping</option><option>Other</option></select></div>
            <div class="form-group"><label>Findings</label><textarea name="findings_<?= $id ?>" rows="2"></textarea></div>
            <div class="form-group"><label>Parasites found?</label><select name="parasites_found_<?= $id ?>"><option>No</option><option>Yes</option></select></div>
            <div class="form-group"><label>If yes, specify</label><input name="parasites_specify_<?= $id ?>"></div>
            <div class="form-group"><label>Abnormal cells present?</label><select name="abnormal_cells_<?= $id ?>"><option>No</option><option>Yes</option></select></div>
            <div class="form-group"><label>If yes, describe</label><textarea name="abnormal_cells_desc_<?= $id ?>" rows="2"></textarea></div>
            <div class="form-group" style="grid-column:1 / -1;"><label>Additional slide notes</label><textarea name="slide_notes_<?= $id ?>" rows="2"></textarea></div>
          </div>

        <?php else: ?>
          <div id="kitRows_<?= $id ?>">
            <div class="kit-grid kit-row">
              <div class="form-group"><label>Kit type</label><select name="kit_type_<?= $id ?>[]"><option>Parvovirus</option><option>Distemper</option><option>FeLV</option><option>FIV</option><option>Heartworm</option><option>Other</option></select></div>
              <div class="form-group"><label>Result</label><select name="kit_result_<?= $id ?>[]" class="kit-result"><option>Positive</option><option>Negative</option><option>Invalid</option></select></div>
              <div class="form-group"><label>Notes</label><input name="kit_notes_<?= $id ?>[]"></div>
              <button type="button" class="kit-remove-btn" title="Remove"><i class='bx bx-x'></i></button>
            </div>
          </div>
          <button type="button" class="btn btn-outline btn-sm btn-pill" onclick="addKitRow(<?= $id ?>)">Add another kit</button>
        <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="bottom-action-bar">
      <a href="/Petmate/dashboards/vet_technician/assessment_form.php?room_id=<?= (int)$session['room_id'] ?>" class="btn btn-outline btn-pill"><i class='bx bx-arrow-back'></i> Back</a>
      <button type="submit" class="btn btn-pill" style="background:#3D2B1F;color:#FAF0E6;"><i class='bx bx-save'></i> Save &amp; submit</button>
    </div>
  </form>
<?php endif; ?>

<script>
function updateFieldStatus(inputEl, min, max, barId, pillId) {
  const val = parseFloat(inputEl.value);
  const dot = document.getElementById(barId);
  const pill = document.getElementById(pillId);
  if (!dot || !pill) return;
  if (isNaN(val)) {
    dot.style.display = 'none';
    pill.style.display = 'none';
    return;
  }
  const pct = Math.min(100, Math.max(0, ((val - min) / (max - min)) * 100));
  dot.style.display = 'block';
  pill.style.display = 'inline-flex';
  dot.style.left = pct + '%';
  if (val < min) {
    dot.className = 'ref-bar-indicator ind-low';
    pill.className = 'status-pill sp-low';
    pill.innerHTML = '↓ low';
  } else if (val > max) {
    dot.className = 'ref-bar-indicator ind-high';
    pill.className = 'status-pill sp-high';
    pill.innerHTML = '↑ high';
  } else {
    dot.className = 'ref-bar-indicator ind-normal';
    pill.className = 'status-pill sp-normal';
    pill.innerHTML = '✓ normal';
  }
}

function applyKitResultStyle(selectEl) {
  if (!selectEl) return;
  if (selectEl.value === 'Positive') {
    selectEl.style.borderColor = '#F09595';
    selectEl.style.background = '#FCEBEB';
    selectEl.style.color = '#791F1F';
  } else if (selectEl.value === 'Negative') {
    selectEl.style.borderColor = '#5DCAA5';
    selectEl.style.background = '#E1F5EE';
    selectEl.style.color = '#085041';
  } else {
    selectEl.style.borderColor = '';
    selectEl.style.background = '';
    selectEl.style.color = '';
  }
}

function addKitRow(testId) {
  const host = document.getElementById('kitRows_' + testId);
  const row = document.createElement('div');
  row.className = 'kit-grid kit-row';
  row.innerHTML = '' +
    '<div class="form-group"><label>Kit type</label><select name="kit_type_' + testId + '[]"><option>Parvovirus</option><option>Distemper</option><option>FeLV</option><option>FIV</option><option>Heartworm</option><option>Other</option></select></div>' +
    '<div class="form-group"><label>Result</label><select name="kit_result_' + testId + '[]" class="kit-result"><option>Positive</option><option>Negative</option><option>Invalid</option></select></div>' +
    '<div class="form-group"><label>Notes</label><input name="kit_notes_' + testId + '[]"></div>' +
    '<button type="button" class="kit-remove-btn" title="Remove"><i class="bx bx-x"></i></button>';
  host.appendChild(row);
  const resultSelect = row.querySelector('.kit-result');
  resultSelect.addEventListener('change', function () { applyKitResultStyle(resultSelect); });
  applyKitResultStyle(resultSelect);
}

document.addEventListener('input', function (e) {
  const field = e.target.closest('.ref-field');
  if (!field) return;
  updateFieldStatus(field, parseFloat(field.dataset.min), parseFloat(field.dataset.max), field.dataset.bar, field.dataset.pill);
});

document.addEventListener('change', function (e) {
  if (e.target.matches('.kit-result')) {
    applyKitResultStyle(e.target);
  }
});

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.kit-remove-btn');
  if (!btn) return;
  const row = btn.closest('.kit-row');
  if (row) row.remove();
});

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ref-field').forEach(function (field) {
    updateFieldStatus(field, parseFloat(field.dataset.min), parseFloat(field.dataset.max), field.dataset.bar, field.dataset.pill);
  });
  document.querySelectorAll('.kit-result').forEach(function (sel) {
    applyKitResultStyle(sel);
  });
});
</script>

<?php require_once '../../includes/footer.php'; ?>