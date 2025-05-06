<?php
// You might need session_start() here if it's not started in admin_auth.php
// session_start();

// Assume admin_auth.php is included before this on every page,
// and it sets $_SESSION['admin_username'] and handles authentication.

$loggedInAdminUsername = $_SESSION['admin_username'] ?? 'Admin User'; // Get logged-in username
?>
<header class="admin-header">
    <div class="header-left">
        <span class="admin-title">Kemsoft Shop Admin</span>
    </div>
    <div class="header-right">
        <span class="logged-in-user">Welcome, <?php echo htmlspecialchars($loggedInAdminUsername); ?></span>
        <a href="/kemsoft_shop/admin/logout.php" class="logout-link">Logout</a> </div>
</header>