<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

// Get method ID from the URL
$method_id = trim($_GET['id'] ?? '');

// Redirect if no ID is provided or ID is not numeric
if (empty($method_id) || !is_numeric($method_id)) {
    $_SESSION['error_message'] = "Invalid shipping method ID provided for deletion.";
    header("Location: index.php");
    exit();
}

$method_id = (int)$method_id; // Cast to integer

// --- Delete Shipping Method from Database ---
$sql_delete = "DELETE FROM shipping_methods WHERE method_id = ?";

$stmt_delete = $conn->prepare($sql_delete);

if ($stmt_delete) {
    $stmt_delete->bind_param("i", $method_id);

    if ($stmt_delete->execute()) {
        // Check if any rows were affected
        if ($stmt_delete->affected_rows > 0) {
            $_SESSION['success_message'] = "Shipping method deleted successfully.";
        } else {
            // No rows affected, probably the ID didn't exist
            $_SESSION['error_message'] = "Shipping method with ID " . htmlspecialchars($method_id) . " not found or already deleted.";
        }
    } else {
        // Database deletion error
        $_SESSION['error_message'] = "Database error deleting shipping method: " . $stmt_delete->error;
    }

    $stmt_delete->close(); // Close the statement
} else {
    // Prepare statement error
    $_SESSION['error_message'] = "Database error preparing delete statement: " . $conn->error;
}

// Close the database connection if necessary
// closeDB($conn); // If your db_connect.php has a closeDB function

// Redirect back to the shipping methods list page
header("Location: index.php");
exit();
?>