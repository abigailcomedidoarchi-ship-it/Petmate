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
    <title>PetMate - Vet Assistant | Discharge</title>
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
                    <span class="role-pill">Vet Assistant</span>
                </div>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>"><i class='bx bx-grid-alt'></i> Overview</a></li>
                <li><a href="prepare_room.php" class="<?php echo $current_page == 'prepare_room.php' ? 'active' : ''; ?>"><i class='bx bx-spray-can'></i> Prepare Room</a></li>
                <li><a href="instructions.php" class="<?php echo $current_page == 'instructions.php' ? 'active' : ''; ?>"><i class='bx bx-task'></i> Instructions</a></li>
                <li><a href="administer.php" class="<?php echo $current_page == 'administer.php' ? 'active' : ''; ?>"><i class='bx bx-injection'></i> Administer</a></li>
                <li><a href="discharge_prep.php" class="<?php echo $current_page == 'discharge_prep.php' ? 'active' : ''; ?>"><i class='bx bx-box'></i> Discharge Prep</a></li>
                <li><a href="discharge.php" class="<?php echo $current_page == 'discharge.php' ? 'active' : ''; ?>"><i class='bx bx-exit'></i> Discharge</a></li>
                <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class='bx bx-cog'></i> Settings</a></li>

            </ul>

            <div class="sidebar-footer">
                <div class="sidebar-avatar">
                    <?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                    <div class="sidebar-user-role">Vet Assistant</div>
                </div>
                <a href="../../logout.php" class="sidebar-logout" title="Logout"><i class='bx bx-log-out'></i></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">Discharge</div>
                <div class="topbar-right">
                    <i class='bx bx-bell topbar-bell'></i>
                    <div class="topbar-avatar"><?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?></div>
                    <span class="topbar-role-pill">Vet Assistant</span>
                </div>
            </header>

            <div class="content-wrapper">
                <h1 class="page-heading">Discharge</h1>
                <div class="breadcrumb">Dashboard <span>›</span> Discharge</div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Discharge Content</h2>
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