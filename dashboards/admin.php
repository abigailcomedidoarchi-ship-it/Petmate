<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireRole('admin');
require_permission('manage_system');

$tab = $_GET['tab'] ?? 'overview';

// Handle User Management Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_status') {
    $target_id = $_POST['user_id'];
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $target_id]);

    require_once '../includes/logger.php';
    log_audit($pdo, $_SESSION['user_id'], "Updated user #$target_id status to $new_status", 'users', $target_id);

    header("Location: /Petmate/dashboards/admin.php?tab=users&msg=updated");
    exit;
}

// Fetch data depending on tab
if ($tab === 'overview') {
    $total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $failed_logins  = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND DATE(timestamp) = CURDATE()")->fetchColumn();
    $active_sessions= $pdo->query("SELECT COUNT(*) FROM active_sessions")->fetchColumn();
    $locked_accounts= $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'locked'")->fetchColumn();

    $recent_logs = $pdo->query("SELECT al.*, u.name as user_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY timestamp DESC LIMIT 6")->fetchAll();

    $roles = ['pet_owner', 'csr', 'veterinarian', 'vet_technician', 'vet_assistant'];
    $login_attempts_by_role = array_fill_keys($roles, 0);
    $fetched_logins = $pdo->query("SELECT role_attempted, COUNT(*) as count FROM login_attempts WHERE DATE(timestamp) = CURDATE() AND role_attempted IS NOT NULL GROUP BY role_attempted")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($fetched_logins as $role => $count) {
        if (isset($login_attempts_by_role[$role])) $login_attempts_by_role[$role] = $count;
    }
} elseif ($tab === 'users') {
    $users = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll();
} elseif ($tab === 'audit') {
    $audit_logs = $pdo->query("SELECT al.*, u.name as user_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY timestamp DESC LIMIT 100")->fetchAll();
} elseif ($tab === 'logins') {
    $logins = $pdo->query("SELECT * FROM login_attempts ORDER BY timestamp DESC LIMIT 100")->fetchAll();
} elseif ($tab === 'sessions') {
    if (isset($_GET['terminate'])) {
        $term_id = $_GET['terminate'];
        $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?")->execute([$term_id]);
        require_once '../includes/logger.php';
        log_audit($pdo, $_SESSION['user_id'], "Terminated session $term_id");
        header("Location: /Petmate/dashboards/admin.php?tab=sessions");
        exit;
    }
    $active_sess_list = $pdo->query("SELECT s.*, u.name, u.role FROM active_sessions s JOIN users u ON s.user_id = u.id ORDER BY last_activity DESC")->fetchAll();
}

require_once '../includes/header.php';
?>

<!-- Page heading -->
<div class="action-bar">
  <div>
    <h1 class="page-heading">Admin Panel</h1>
    <p class="breadcrumb">PetMate <span>›</span> Admin</p>
  </div>
</div>

<!-- Tab bar -->
<div class="tab-bar">
  <a href="?tab=overview"  class="tab-link <?= $tab === 'overview'  ? 'active' : '' ?>"><i class='bx bx-grid-alt'></i> Overview</a>
  <a href="?tab=users"     class="tab-link <?= $tab === 'users'     ? 'active' : '' ?>"><i class='bx bx-group'></i> Users</a>
  <a href="?tab=audit"     class="tab-link <?= $tab === 'audit'     ? 'active' : '' ?>"><i class='bx bx-file-find'></i> Audit Logs</a>
  <a href="?tab=logins"    class="tab-link <?= $tab === 'logins'    ? 'active' : '' ?>"><i class='bx bx-log-in-circle'></i> Login Attempts</a>
  <a href="?tab=sessions"  class="tab-link <?= $tab === 'sessions'  ? 'active' : '' ?>"><i class='bx bx-desktop'></i> Sessions</a>
</div>

<?php if ($tab === 'overview'): ?>

<!-- Stat cards -->
<div class="grid grid-4 mb-4">
  <div class="stat-card">
    <span class="stat-label">Total Users</span>
    <span class="stat-value"><?= $total_users ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Failed Logins Today</span>
    <span class="stat-value danger"><?= $failed_logins ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Active Sessions</span>
    <span class="stat-value success"><?= $active_sessions ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Locked Accounts</span>
    <span class="stat-value accent"><?= $locked_accounts ?></span>
  </div>
</div>

<div class="grid grid-2">
  <!-- Recent audit log -->
  <div class="card">
    <div class="card-header">
      <h2>Recent Audit Log</h2>
      <a href="?tab=audit" class="btn btn-outline btn-sm">See All</a>
    </div>
    <ul class="log-list">
      <?php foreach ($recent_logs as $log):
        $action = strtolower($log['action']);
        $dotClass = '';
        if (str_contains($action, 'fail') || str_contains($action, 'unauthorized')) $dotClass = 'danger';
        elseif (str_contains($action, 'create') || str_contains($action, 'login')) $dotClass = 'success';
        elseif (str_contains($action, 'update') || str_contains($action, 'export')) $dotClass = 'warning';
      ?>
      <li class="log-item">
        <div class="log-dot <?= $dotClass ?>"></div>
        <span class="log-text">
          <strong><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong>
          — <?= htmlspecialchars($log['action']) ?>
        </span>
        <span class="log-time"><?= date('H:i', strtotime($log['timestamp'])) ?></span>
      </li>
      <?php endforeach; ?>
      <?php if (empty($recent_logs)): ?>
      <li class="empty-state"><i class='bx bx-list-check'></i><p>No recent logs</p></li>
      <?php endif; ?>
    </ul>
  </div>

  <!-- Login attempts by role -->
  <div class="card">
    <div class="card-header">
      <h2>Login Attempts by Role</h2>
      <span class="text-muted" style="font-size:12px;">Today</span>
    </div>
    <?php
      $max_val = max($login_attempts_by_role) > 0 ? max($login_attempts_by_role) : 1;
      foreach ($login_attempts_by_role as $role => $count):
        $pct = round(($count / $max_val) * 100);
        $fillClass = ($count > 3) ? 'danger' : '';
    ?>
    <div class="bar-row">
      <span class="bar-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
      <div class="bar-track">
        <div class="bar-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
      </div>
      <span class="bar-count"><?= $count ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>

<?php if ($tab === 'users'): ?>
<div class="card">
  <div class="card-header">
    <h2>User Management</h2>
    <span class="text-muted"><?= count($users) ?> users</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))) ?></td>
          <td><span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="update_user_status">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <option value="" disabled selected>Change status…</option>
                <option value="active"    <?= $u['status'] === 'active'    ? 'disabled' : '' ?>>Set Active</option>
                <option value="locked"    <?= $u['status'] === 'locked'    ? 'disabled' : '' ?>>Lock Account</option>
                <option value="suspended" <?= $u['status'] === 'suspended' ? 'disabled' : '' ?>>Suspend</option>
              </select>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'audit'): ?>
<div class="card">
  <div class="card-header">
    <h2>Audit Logs</h2>
    <span class="text-muted">Last 100 entries</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>User</th>
          <th>Action</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($audit_logs as $log):
          $isDanger = stripos($log['action'], 'fail') !== false || stripos($log['action'], 'unauthorized') !== false;
        ?>
        <tr>
          <td style="font-size:12px; color:var(--color-muted);"><?= $log['timestamp'] ?></td>
          <td><strong><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong></td>
          <td style="<?= $isDanger ? 'color:var(--color-danger);' : '' ?>"><?= htmlspecialchars($log['action']) ?></td>
          <td style="font-size:12px; color:var(--color-muted);"><?= htmlspecialchars($log['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($audit_logs)): ?>
        <tr><td colspan="4"><div class="empty-state"><i class='bx bx-file-find'></i><p>No audit logs found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'logins'): ?>
<div class="card">
  <div class="card-header">
    <h2>Login Attempts</h2>
    <span class="text-muted">Last 100 entries</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Email</th>
          <th>Result</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logins as $login): ?>
        <tr>
          <td style="font-size:12px; color:var(--color-muted);"><?= $login['timestamp'] ?></td>
          <td><?= htmlspecialchars($login['email']) ?></td>
          <td>
            <?php if ($login['success']): ?>
              <span class="badge badge-validated">Success</span>
            <?php else: ?>
              <span class="badge badge-rejected">Failed</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px; color:var(--color-muted);"><?= htmlspecialchars($login['ip_address']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logins)): ?>
        <tr><td colspan="4"><div class="empty-state"><i class='bx bx-log-in-circle'></i><p>No login attempts found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'sessions'): ?>
<div class="card">
  <div class="card-header">
    <h2>Active Sessions</h2>
    <span class="text-muted"><?= count($active_sess_list) ?> active</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>IP Address</th>
          <th>Idle Time</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($active_sess_list as $sess):
          $idle_seconds = time() - strtotime($sess['last_activity']);
          $idle_minutes = floor($idle_seconds / 60);
          $isWarning    = $idle_minutes >= 10;
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($sess['name']) ?></strong></td>
          <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $sess['role']))) ?></td>
          <td style="font-size:12px; color:var(--color-muted);"><?= htmlspecialchars($sess['ip_address']) ?></td>
          <td>
            <?php if ($isWarning): ?>
              <span style="color:var(--color-danger); font-weight:600;"><?= $idle_minutes ?> min</span>
            <?php else: ?>
              <span style="color:var(--color-muted);"><?= $idle_minutes ?> min</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="?tab=sessions&terminate=<?= urlencode($sess['session_id']) ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Terminate this session?');">
              <i class='bx bx-power-off'></i> Terminate
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($active_sess_list)): ?>
        <tr><td colspan="5"><div class="empty-state"><i class='bx bx-desktop'></i><p>No active sessions.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
