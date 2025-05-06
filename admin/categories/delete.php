<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$category_id = null;
$error_message = '';
$success_message = '';

// --- Get Category ID from URL and Validate ---
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = $_GET['id'];

    // Start a database transaction
    $conn->begin_transaction();

    try {
        // --- 1. Set category_id to NULL for all products in this category ---
        $sql_update_products = "UPDATE products SET category_id = NULL WHERE category_id = ?";
        $stmt_update_products = $conn->prepare($sql_update_products);
        $stmt_update_products->bind_param("i", $category_id);
        $stmt_update_products->execute();
        $stmt_update_products->close();

        // --- 2. Delete the category from the categories table ---
        $sql_delete_category = "DELETE FROM categories WHERE category_id = ?";
        $stmt_delete_category = $conn->prepare($sql_delete_category);
        $stmt_delete_category->bind_param("i", $category_id);

        if ($stmt_delete_category->execute()) {
             if ($stmt_delete_category->affected_rows > 0) {
                 // Category was successfully deleted from the database
                 $conn->commit(); // Commit the transaction
                 $success_message = "Category with ID " . htmlspecialchars($category_id) . " deleted successfully. Associated products were uncategorized.";
             } else {
                 // No category found with that ID (or already deleted)
                 $conn->rollback(); // Rollback the transaction as no category was deleted
                 $error_message = "Category with ID " . htmlspecialchars($category_id) . " not found.";
             }
        } else {
            // Error executing category deletion query
            $conn->rollback(); // Rollback the transaction
            $error_message = "Error deleting category: " . $stmt_delete_category->error;
        }

        $stmt_delete_category->close();

    } catch (Exception $e) {
        // Catch any exceptions during the process and rollback
        $conn->rollback();
        $error_message = "An error occurred during deletion: " . $e->getMessage();
        error_log("Admin Category Deletion Error: " . $e->getMessage()); // Log the exception
    }

} else {
    // No category ID provided or invalid ID
    $error_message = "Invalid category ID provided.";
}

// Close the database connection if necessary
// closeDB($conn);


// --- Redirect back to the categories list with a status message ---
if ($success_message) {
    $_SESSION['success_message'] = $success_message;
} elseif ($error_message) {
     $_SESSION['error_message'] = $error_message;
}

header('Location: index.php');
exit();
?>