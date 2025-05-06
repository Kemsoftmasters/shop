<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$product_id = null;
$error_message = '';
$success_message = '';

// --- Get Product ID from URL and Validate ---
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];

    // Start a database transaction
    $conn->begin_transaction();

    try {
        // --- 1. Fetch image URL before deleting the product ---
        $sql_fetch_image = "SELECT image_url FROM products WHERE product_id = ?";
        $stmt_fetch_image = $conn->prepare($sql_fetch_image);
        $stmt_fetch_image->bind_param("i", $product_id);
        $stmt_fetch_image->execute();
        $result_fetch_image = $stmt_fetch_image->get_result();
        $image_url_to_delete = null;
        if ($row_image = $result_fetch_image->fetch_assoc()) {
            $image_url_to_delete = $row_image['image_url'];
        }
        $stmt_fetch_image->close();


        // --- 2. Delete related entries in order_items table ---
        $sql_delete_order_items = "DELETE FROM order_items WHERE product_id = ?";
        $stmt_delete_order_items = $conn->prepare($sql_delete_order_items);
        $stmt_delete_order_items->bind_param("i", $product_id);
        $stmt_delete_order_items->execute();
        $stmt_delete_order_items->close();

        // --- 3. Delete related entries in wishlist table ---
        $sql_delete_wishlist = "DELETE FROM wishlist WHERE product_id = ?";
        $stmt_delete_wishlist = $conn->prepare($sql_delete_wishlist);
        $stmt_delete_wishlist->bind_param("i", $product_id);
        $stmt_delete_wishlist->execute();
        $stmt_delete_wishlist->close();

        // --- 4. Delete the product from the products table ---
        $sql_delete_product = "DELETE FROM products WHERE product_id = ?";
        $stmt_delete_product = $conn->prepare($sql_delete_product);
        $stmt_delete_product->bind_param("i", $product_id);

        if ($stmt_delete_product->execute()) {
             if ($stmt_delete_product->affected_rows > 0) {
                 // Product was successfully deleted from the database
                 $conn->commit(); // Commit the transaction

                 // --- 5. Delete the associated image file (only if database deletion was successful) ---
                 if ($image_url_to_delete) {
                     $image_file_path = '../../' . $image_url_to_delete; // Path to the image file
                     if (file_exists($image_file_path) && is_file($image_file_path)) {
                         // Attempt to delete the file
                         if (unlink($image_file_path)) {
                             // Image file deleted successfully
                         } else {
                             // Failed to delete image file (log this, but don't stop the product deletion)
                             error_log("Admin: Failed to delete product image file: " . $image_file_path);
                         }
                     }
                 }

                 $success_message = "Product with ID " . htmlspecialchars($product_id) . " deleted successfully.";
             } else {
                 // No product found with that ID (or already deleted)
                 $conn->rollback(); // Rollback the transaction as no product was deleted
                 $error_message = "Product with ID " . htmlspecialchars($product_id) . " not found.";
             }
        } else {
            // Error executing product deletion query
            $conn->rollback(); // Rollback the transaction
            $error_message = "Error deleting product: " . $stmt_delete_product->error;
        }

        $stmt_delete_product->close();

    } catch (Exception $e) {
        // Catch any exceptions during the process and rollback
        $conn->rollback();
        $error_message = "An error occurred during deletion: " . $e->getMessage();
        error_log("Admin Product Deletion Error: " . $e->getMessage()); // Log the exception
    }


} else {
    // No product ID provided or invalid ID
    $error_message = "Invalid product ID provided.";
}

// Close the database connection if necessary
// closeDB($conn);


// --- Redirect back to the products list with a status message ---
if ($success_message) {
    $_SESSION['success_message'] = $success_message;
} elseif ($error_message) {
     $_SESSION['error_message'] = $error_message;
}

header('Location: index.php');
exit();
?>