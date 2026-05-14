<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/rbac.php';

requireRole('vet_technician');
require_permission('manage_exam_rooms');

$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$error = '';

$roomStmt = $pdo->prepare("
    SELECT er.id AS examination_room_id, er.pet_id, r.room_name, p.name AS pet_name
    FROM examination_rooms er
    JOIN rooms r ON r.id = er.room_id
    JOIN pets p ON p.id = er.pet_id
    WHERE er.id = ? AND er.status = 'in_use'
    LIMIT 1
");
$roomStmt->execute([$room_id]);
$roomData = $roomStmt->fetch();

if (!$roomData) {
    $error = 'Room session not found or no longer active.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $temperature = ($_POST['temperature'] ?? '') !== '' ? (float)$_POST['temperature'] : null;
    $heart_rate = ($_POST['heart_rate'] ?? '') !== '' ? (int)$_POST['heart_rate'] : null;
    $respiratory_rate = ($_POST['respiratory_rate'] ?? '') !== '' ? (int)$_POST['respiratory_rate'] : null;
    $weight_on_arrival = ($_POST['weight_on_arrival'] ?? '') !== '' ? (float)$_POST['weight_on_arrival'] : null;

    $mucous_membrane_color = trim($_POST['mucous_membrane_color'] ?? '');
    $capillary_refill_time = trim($_POST['capillary_refill_time'] ?? '');
    $pain_score = ($_POST['pain_score'] ?? '') !== '' ? (int)$_POST['pain_score'] : null;
    $body_condition_score = trim($_POST['body_condition_score'] ?? '');

    $selected_tests = array_values(array_filter($_POST['tests'] ?? []));
    if (empty($selected_tests)) {
        $error = 'Please select at least one diagnostic test.';
    } else {
        $notes = [
            'mucous_membrane_color' => $mucous_membrane_color,
            'capillary_refill_time' => $capillary_refill_time,
            'pain_score' => $pain_score,
            'body_condition_score' => $body_condition_score
        ];

        try {
            $pdo->beginTransaction();

            $sessionStmt = $pdo->prepare("
                INSERT INTO assessment_sessions (
                    pet_id, room_id, technician_id, temperature, heart_rate, respiratory_rate, weight_on_arrival, overall_notes, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $sessionStmt->execute([
                (int)$roomData['pet_id'],
                (int)$roomData['examination_room_id'],
                (int)$_SESSION['user_id'],
                $temperature,
                $heart_rate,
                $respiratory_rate,
                $weight_on_arrival,
                json_encode($notes)
            ]);

            $session_id = (int)$pdo->lastInsertId();
            $insertAssessment = $pdo->prepare("
                INSERT INTO assessments (
                    pet_id, assessment_session_id, equipment_used, test_type, status, technician_id, date
                ) VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");

            foreach ($selected_tests as $test) {
                $insertAssessment->execute([
                    (int)$roomData['pet_id'],
                    $session_id,
                    $test,
                    $test,
                    (int)$_SESSION['user_id']
                ]);
            }

            $pdo->commit();
            header("Location: /Petmate/dashboards/vet_technician/record_results.php?session_id={$session_id}");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to start assessment session. ' . $e->getMessage();
        }
    }
}

$current_page = 'assessment_queue';
require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Assessment Form</h1>
    <p class="breadcrumb">PetMate <span>›</span> Vet Technician <span>›</span> Assessment Form</p>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($roomData): ?>
  <div class="card">
    <div class="info-row"><span class="info-label">Room</span><span class="info-value"><?= htmlspecialchars($roomData['room_name']) ?></span></div>
    <div class="info-row"><span class="info-label">Pet</span><span class="info-value"><?= htmlspecialchars($roomData['pet_name']) ?></span></div>
  </div>

  <form method="POST" id="assessmentForm">
    <div class="card">
      <div class="card-header"><h2><i class='bx bx-clipboard'></i> Physical Examination Vitals</h2></div>
      <div class="section-title">Vitals</div>
      <div class="grid grid-2">
        <div class="form-group"><label>Temperature (°C)</label><input type="number" step="0.1" name="temperature"></div>
        <div class="form-group"><label>Heart Rate (bpm)</label><input type="number" name="heart_rate"></div>
        <div class="form-group"><label>Respiratory Rate (breaths/min)</label><input type="number" name="respiratory_rate"></div>
        <div class="form-group"><label>Weight on Arrival (kg)</label><input type="number" step="0.01" name="weight_on_arrival"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group">
          <label>Mucous Membrane Color</label>
          <select name="mucous_membrane_color">
            <option value="">Select</option>
            <option>Pink (normal)</option>
            <option>Pale</option>
            <option>White</option>
            <option>Yellow (jaundiced)</option>
            <option>Blue (cyanotic)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Capillary Refill Time</label>
          <select name="capillary_refill_time">
            <option value="">Select</option>
            <option>&lt;2 seconds (normal)</option>
            <option>2-3 seconds</option>
            <option>&gt;3 seconds</option>
          </select>
        </div>
      </div>
      <div class="grid grid-2">
        <div class="form-group">
          <label>Pain Score: <span id="painScoreValue">0</span></label>
          <input type="range" min="0" max="10" value="0" name="pain_score" id="painScoreRange">
        </div>
        <div class="form-group">
          <label>Body Condition Score</label>
          <select name="body_condition_score">
            <option value="">Select</option>
            <option value="1">1 - Emaciated</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5 - Ideal</option>
            <option value="6">6</option>
            <option value="7">7</option>
            <option value="8">8</option>
            <option value="9">9 - Obese</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2><i class='bx bx-test-tube'></i> Diagnostic Tests</h2></div>
      <div class="section-title">Select Tests to Run</div>
      <div class="grid grid-2">
        <label class="card" style="cursor:pointer;">
          <input type="checkbox" name="tests[]" value="cbc"> <strong><i class='bx bx-test-tube'></i> CBC Machine</strong>
          <p class="text-muted">Complete Blood Count — WBC, RBC, Hemoglobin, Hematocrit, Platelets</p>
        </label>
        <label class="card" style="cursor:pointer;">
          <input type="checkbox" name="tests[]" value="chemistry"> <strong><i class='bx bx-droplet'></i> Blood Chemistry</strong>
          <p class="text-muted">Liver &amp; Kidney profile — ALT, AST, BUN, Creatinine, Glucose</p>
        </label>
        <label class="card" style="cursor:pointer;">
          <input type="checkbox" name="tests[]" value="microscopy"> <strong><i class='bx bx-search-alt'></i> Microscopy</strong>
          <p class="text-muted">Slide examination — blood film, urine, fecal, skin scraping</p>
        </label>
        <label class="card" style="cursor:pointer;">
          <input type="checkbox" name="tests[]" value="test_kit"> <strong><i class='bx bx-first-aid'></i> Test Kits</strong>
          <p class="text-muted">Rapid antigen tests — Parvo, Distemper, FeLV, FIV, Heartworm</p>
        </label>
      </div>
    </div>

    <div class="action-bar">
      <a href="/Petmate/dashboards/vet_technician/pet_overview.php?room_id=<?= (int)$room_id ?>" class="btn btn-outline"><i class='bx bx-arrow-back'></i> Back</a>
      <button type="submit" class="btn btn-primary">Continue to Results <i class='bx bx-right-arrow-alt'></i></button>
    </div>
  </form>
<?php endif; ?>

<script>
  (function () {
    const painRange = document.getElementById('painScoreRange');
    const painValue = document.getElementById('painScoreValue');
    const form = document.getElementById('assessmentForm');
    if (painRange && painValue) {
      painRange.addEventListener('input', function () {
        painValue.textContent = painRange.value;
      });
    }
    if (form) {
      form.addEventListener('submit', function (e) {
        const selected = form.querySelectorAll('input[name="tests[]"]:checked');
        if (!selected.length) {
          e.preventDefault();
          alert('Please select at least one diagnostic test.');
        }
      });
    }
  })();
</script>

<?php require_once '../../includes/footer.php'; ?>
