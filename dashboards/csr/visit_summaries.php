<?php
session_start();
// require_once '../../includes/db.php';
// require_once '../../includes/auth.php';
// require_once '../../includes/session_guard.php';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetMate - Client Service Rep | Visit Summaries</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class='bx bx-paw brand-icon'></i>
                <div class="brand-text">
                    <span class="brand-name">PetMate</span>
                    <span class="role-pill">Client Service Rep</span>
                </div>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>"><i class='bx bx-grid-alt'></i> Overview</a></li>
                <li><a href="pet_info.php" class="<?php echo $current_page == 'pet_info.php' ? 'active' : ''; ?>"><i class='bx bx-info-circle'></i> Pet Info</a></li>
                <li><a href="pet_records.php" class="<?php echo $current_page == 'pet_records.php' ? 'active' : ''; ?>"><i class='bx bx-folder'></i> Pet Records</a></li>
                <li><a href="billing.php" class="<?php echo $current_page == 'billing.php' ? 'active' : ''; ?>"><i class='bx bx-credit-card'></i> Billing</a></li>
                <li><a href="visit_summaries.php" class="<?php echo $current_page == 'visit_summaries.php' ? 'active' : ''; ?>"><i class='bx bx-file'></i> Visit Summaries</a></li>
                <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class='bx bx-message-square-dots'></i> Messages</a></li>
                <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class='bx bx-cog'></i> Settings</a></li>

            </ul>

            <div class="sidebar-footer">
                <div class="sidebar-avatar">
                    <?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                    <div class="sidebar-user-role">Client Service Rep</div>
                </div>
                <a href="../../logout.php" class="sidebar-logout" title="Logout"><i class='bx bx-log-out'></i></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">Visit Summaries</div>
                <div class="topbar-right">
                    <i class='bx bx-bell topbar-bell'></i>
                    <div class="topbar-avatar"><?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?></div>
                    <span class="topbar-role-pill">Client Service Rep</span>
                </div>
            </header>

            <div class="content-wrapper">
                <h1 class="page-heading">Visit Summaries</h1>
                <div class="breadcrumb">Dashboard <span>›</span> Visit Summaries</div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Visit Summaries Content</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">This page is under construction.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>