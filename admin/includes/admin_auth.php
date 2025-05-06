<?php
session_start(); // Start the session (if not already started on the page)

// Check if the admin is NOT logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to the admin login page
    header('Location: login.php');
    exit(); // Stop script execution
}

// Optionally, you can fetch admin user details here if needed on every page
// e.g., $loggedInAdminId = $_SESSION['admin_id'];
?>