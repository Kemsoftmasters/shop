<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$user_id_to_delete = $_GET['id'] ?? null;

// Validate user ID
if (empty($user_id_to_delete) || !is_numeric($user_id_to_delete)) {
    $_SESSION['error_message'] = "Invalid or missing user ID for deletion.";
    header('Location: index.php'); // Redirect back to users list
    exit();
}

// --- Deletion Logic ---

// IMPORTANT: Decide how to handle related data (orders, addresses).
// Option A (Cascade Delete): Requires foreign key constraints ON DELETE CASCADE set up in your database.
// Option B (Anonymize): Set user_id to NULL in related tables (orders, user_addresses). Requires user_id in these tables to be NULLABLE.
// Option C (Prevent Deletion): Check for related data and deny deletion if found.

// We will implement Option B (Anonymize) here as a balance.
// If you need Cascade Delete (Option A), set it up in your database structure.
// If you need to Prevent Deletion (Option C), add SELECT queries before the DELETE/UPDATE statements.

$conn->begin_transaction(); // Start a transaction

try {
    // 1. Anonymize related data (set user_id to NULL)
    // Ensure the 'user_id' columns in 'orders' and 'user_addresses' are nullable (ALLOW NULL in database)

    // Anonymize Orders
    $sql_anonymize_orders = "UPDATE orders SET user_id = NULL WHERE user_id = ?";
    $stmt_anonymize_orders = $conn->prepare($sql_anonymize_orders);
    $stmt_anonymize_orders->bind_param("i", $user_id_to_delete);
    if (!$stmt_anonymize_orders->execute()) {
        throw new Exception("Error anonymizing user's orders: " . $stmt_anonymize_orders->error);
    }
    $stmt_anonymize_orders->close();

    // Anonymize Addresses
    $sql_anonymize_addresses = "UPDATE user_addresses SET user_id = NULL WHERE user_id = ?";
    $stmt_anonymize_addresses = $conn->prepare($sql_anonymize_addresses);
    $stmt_anonymize_addresses->bind_param("i", $user_id_to_delete);
    if (!$stmt_anonymize_addresses->execute()) {
        throw new Exception("Error anonymizing user's addresses: " . $stmt_anonymize_addresses->error);
    }
    $stmt_anonymize_addresses->close();


    // 2. Delete the user record
    $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
    $stmt_delete_user = $conn->prepare($sql_delete_user);
    $stmt_delete_user->bind_param("i", $user_id_to_delete);

    if ($stmt_delete_user->execute()) {
        $conn->commit(); // Commit the transaction if all successful
        $_SESSION['success_message'] = "User deleted successfully!";
        header('Location: index.php'); // Redirect back to users list
        exit();
    } else {
        throw new Exception("Error deleting user: " . $stmt_delete_user->error);
    }
    $stmt_delete_user->close();

} catch (Exception $e) {
    $conn->rollback(); // Rollback the transaction on error
    $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
    // Redirect back to the view page or list page depending on where deletion was initiated
    header('Location: index.php'); // Redirect back to users list
    exit();
}

// Close the database connection if necessary
// closeDB($conn);
?>