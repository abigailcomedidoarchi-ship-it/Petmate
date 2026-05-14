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
    <title>PetMate - Pet Owner | Book Sitter</title>
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
                    <span class="role-pill">Pet Owner</span>
                </div>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>"><i class='bx bx-grid-alt'></i> Overview</a></li>
                <li><a href="my_pets.php" class="<?php echo $current_page == 'my_pets.php' ? 'active' : ''; ?>"><i class='bx bx-bone'></i> My Pets</a></li>
                <li><a href="book_sitter.php" class="<?php echo $current_page == 'book_sitter.php' ? 'active' : ''; ?>"><i class='bx bx-calendar-plus'></i> Book Sitter</a></li>
                <li><a href="my_bookings.php" class="<?php echo $current_page == 'my_bookings.php' ? 'active' : ''; ?>"><i class='bx bx-calendar-event'></i> My Bookings</a></li>
                <li><a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>"><i class='bx bx-message-square-dots'></i> Messages</a></li>
                <li><a href="bills.php" class="<?php echo $current_page == 'bills.php' ? 'active' : ''; ?>"><i class='bx bx-receipt'></i> Bills</a></li>
                <li><a href="visit_records.php" class="<?php echo $current_page == 'visit_records.php' ? 'active' : ''; ?>"><i class='bx bx-folder-open'></i> Visit Records</a></li>
                <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class='bx bx-cog'></i> Settings</a></li>

            </ul>

            <div class="sidebar-footer">
                <div class="sidebar-avatar">
                    <?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                    <div class="sidebar-user-role">Pet Owner</div>
                </div>
                <a href="../../logout.php" class="sidebar-logout" title="Logout"><i class='bx bx-log-out'></i></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">Book Sitter</div>
                <div class="topbar-right">
                    <i class='bx bx-bell topbar-bell'></i>
                    <div class="topbar-avatar"><?php echo isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'U'; ?></div>
                    <span class="topbar-role-pill">Pet Owner</span>
                </div>
            </header>

            <div class="content-wrapper">
                <h1 class="page-heading">Book Sitter</h1>
                <div class="breadcrumb">Dashboard <span>›</span> Book Sitter</div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Book Sitter Content</h2>
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