<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PetMate</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Petmate/assets/css/style.css">
</head>
<body>
<div class="app-container">

<?php if (isset($_SESSION['user_id'])): ?>
<?php
  $role      = $_SESSION['role'] ?? '';
  $userName  = $_SESSION['name'] ?? '';
  $initials  = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', trim($userName)), 0, 2))));
  $roleLabel = ucwords(str_replace('_', ' ', $role));

  // Nav items per role
  $navItems = [];
  if ($role === 'admin') {
    $navItems = [
      ['href' => '/Petmate/dashboards/admin/index.php?tab=overview',  'icon' => 'bx-grid-alt',       'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/admin/index.php?tab=users',     'icon' => 'bx-group',           'label' => 'User Management'],
      ['href' => '/Petmate/dashboards/admin/index.php?tab=audit',     'icon' => 'bx-file-find',       'label' => 'Audit Logs'],
      ['href' => '/Petmate/dashboards/admin/index.php?tab=logins',    'icon' => 'bx-log-in-circle',   'label' => 'Login Attempts'],
      ['href' => '/Petmate/dashboards/admin/index.php?tab=sessions',  'icon' => 'bx-desktop',         'label' => 'Active Sessions'],
    ];
  } elseif ($role === 'pet_owner') {
    $navItems = [
      ['href' => '/Petmate/dashboards/pet_owner/index.php',          'icon' => 'bx-home-heart',          'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/pet_owner/my_pets.php',        'icon' => 'bx-bone',                'label' => 'My Pets'],
      ['href' => '/Petmate/dashboards/pet_owner/register_pet.php',   'icon' => 'bx-plus-circle',         'label' => 'Register Pet'],
      ['href' => '/Petmate/dashboards/pet_owner/treatment_plans.php','icon' => 'bx-shield-quarter',      'label' => 'Treatment Plans'],
      ['href' => '/Petmate/dashboards/pet_owner/bills.php',          'icon' => 'bx-receipt',             'label' => 'Bills & Payments'],
      ['href' => '/Petmate/dashboards/pet_owner/visit_records.php',  'icon' => 'bx-folder-open',         'label' => 'Visit Records'],
      ['href' => '/Petmate/dashboards/pet_owner/messages.php',       'icon' => 'bx-message-square-dots', 'label' => 'Messages'],
      ['href' => '/Petmate/dashboards/pet_owner/settings.php',       'icon' => 'bx-cog',                 'label' => 'Settings'],
    ];
  } elseif ($role === 'csr') {
    $navItems = [
      ['href' => '/Petmate/dashboards/csr/index.php',            'icon' => 'bx-grid-alt',            'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/csr/pet_info.php',         'icon' => 'bx-info-circle',         'label' => 'Pet Information'],
      ['href' => '/Petmate/dashboards/csr/pet_records.php',      'icon' => 'bx-folder',              'label' => 'Pet Records'],
      ['href' => '/Petmate/dashboards/csr/review_record.php',    'icon' => 'bx-search-alt',          'label' => 'Review Record'],
      ['href' => '/Petmate/dashboards/csr/billing.php',          'icon' => 'bx-credit-card',         'label' => 'Billing'],
      ['href' => '/Petmate/dashboards/csr/visit_summaries.php',  'icon' => 'bx-file',                'label' => 'Visit Summaries'],
      ['href' => '/Petmate/dashboards/csr/messages.php',         'icon' => 'bx-message-square-dots', 'label' => 'Messages'],
      ['href' => '/Petmate/dashboards/csr/settings.php',         'icon' => 'bx-cog',                 'label' => 'Settings'],
    ];
  } elseif ($role === 'veterinarian') {
    // Veterinarian role is deprecated in this project; point to vet technician pages.
    $navItems = [
      ['href' => '/Petmate/dashboards/vet_technician/index.php',            'icon' => 'bx-grid-alt',    'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/vet_technician/exam_room.php',        'icon' => 'bx-clinic',      'label' => 'Exam Room'],
      ['href' => '/Petmate/dashboards/vet_technician/assessments.php',      'icon' => 'bx-search-alt-2','label' => 'Assessments'],
      ['href' => '/Petmate/dashboards/vet_technician/treatment_plan.php',   'icon' => 'bx-notepad',     'label' => 'Treatment Plan'],
      ['href' => '/Petmate/dashboards/vet_technician/prescriptions.php',    'icon' => 'bx-capsule',     'label' => 'Prescriptions'],
      ['href' => '/Petmate/dashboards/vet_technician/settings.php',         'icon' => 'bx-cog',         'label' => 'Settings'],
    ];
  } elseif ($role === 'vet_assistant') {
    $navItems = [
      ['href' => '/Petmate/dashboards/vet_assistant/index.php',          'icon' => 'bx-support', 'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/vet_assistant/prepare_room.php',   'icon' => 'bx-clinic',  'label' => 'Prepare Room'],
      ['href' => '/Petmate/dashboards/vet_assistant/room_status.php',    'icon' => 'bx-table',   'label' => 'Room Status'],
      ['href' => '/Petmate/dashboards/vet_assistant/instructions.php',   'icon' => 'bx-task',      'label' => 'Medical Instructions'],
      ['href' => '/Petmate/dashboards/vet_assistant/monitoring_queue.php', 'icon' => 'bx-pulse',    'label' => 'Monitoring Queue'],
      ['href' => '/Petmate/dashboards/vet_assistant/settings.php',       'icon' => 'bx-cog',       'label' => 'Settings'],
    ];
  } elseif ($role === 'vet_technician') {
    $navItems = [
      ['href' => '/Petmate/dashboards/vet_technician/index.php',            'icon' => 'bx-grid-alt',  'label' => 'Overview'],
      ['href' => '/Petmate/dashboards/vet_technician/exam_rooms.php',       'icon' => 'bx-door-open', 'label' => 'Exam Rooms'],
      ['href' => '/Petmate/dashboards/vet_technician/assessment_queue.php', 'icon' => 'bx-list-ul',   'label' => 'Assessment Queue'],
      ['href' => '/Petmate/dashboards/vet_technician/exam_room.php',        'icon' => 'bx-clinic',    'label' => 'Exam Room'],
      ['href' => '/Petmate/dashboards/vet_technician/assessments.php',      'icon' => 'bx-search-alt-2', 'label' => 'Assessments'],
      ['href' => '/Petmate/dashboards/vet_technician/treatment_details.php', 'icon' => 'bx-detail',   'label' => 'Treatment Details'],
      ['href' => '/Petmate/dashboards/vet_technician/approve_discharge.php', 'icon' => 'bx-check-shield', 'label' => 'Discharge Approval'],
      ['href' => '/Petmate/dashboards/vet_technician/settings.php',         'icon' => 'bx-cog',       'label' => 'Settings'],
    ];
  }

  $currentUri = strtok($_SERVER['REQUEST_URI'], '?') . (isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : '');
?>

<aside class="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <i class="bx bx-paw brand-icon"></i>
    <div class="brand-text">
      <span class="brand-name">PetMate</span>
      <span class="role-pill"><?= htmlspecialchars($roleLabel) ?></span>
    </div>
  </div>

  <!-- Nav -->
  <ul class="sidebar-nav">
    <li><span class="nav-section-label">Navigation</span></li>
    <?php foreach ($navItems as $item):
      $href    = $item['href'];
      $isActive = (strpos($_SERVER['REQUEST_URI'], strtok($href, '?')) !== false);
      if (isset($_GET['tab']) && strpos($href, 'tab=') !== false) {
        $isActive = ($href === '/Petmate/dashboards/admin/index.php?tab=' . ($_GET['tab'] ?? ''));
      }
    ?>
    <li>
      <a href="<?= $href ?>" class="<?= $isActive ? 'active' : '' ?>">
        <i class="bx <?= $item['icon'] ?>"></i>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    </li>
    <?php endforeach; ?>

  </ul>

  <!-- Footer user block -->
  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
      <div class="sidebar-user-role"><?= htmlspecialchars($roleLabel) ?></div>
    </div>
    <a href="/Petmate/logout.php" class="sidebar-logout" title="Logout">
      <i class="bx bx-log-out"></i>
    </a>
  </div>
</aside>
<?php endif; ?>

<!-- Main content -->
<main class="main-content <?= !isset($_SESSION['user_id']) ? 'full-width' : '' ?>">

  <?php if (isset($_SESSION['user_id'])): ?>
  <header class="topbar">
    <span class="topbar-title"><?= htmlspecialchars($roleLabel) ?> Dashboard</span>
    <div class="topbar-right">
      <i class="bx bx-bell topbar-bell"></i>
      <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
      <span class="topbar-role-pill"><?= htmlspecialchars($roleLabel) ?></span>
    </div>
  </header>
  <?php endif; ?>

  <div class="content-wrapper">
