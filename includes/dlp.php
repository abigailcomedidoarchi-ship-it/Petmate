<?php
// Session timeout (15 minutes)
$timeout_duration = 900; 

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    global $pdo;
    if (isset($pdo)) {
        require_once 'session_guard.php';
        destroy_session($pdo);
    }
    session_unset();
    session_destroy();
    header("Location: /Petmate/login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// Function to add DLP headers and scripts to prevent copy/paste/print on sensitive pages
function apply_dlp_protection() {
    echo "<style>
        @media print { body { display:none !important; } }
        .dlp-protected {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>";
    echo "<script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && (e.key === 'p' || e.key === 'c' || e.key === 's' || e.key === 'x')) {
                e.preventDefault();
                alert('Data Loss Prevention is active. This action is not allowed.');
            }
        });
    </script>";
}
?>
