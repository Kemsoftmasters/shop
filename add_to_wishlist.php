<?php
session_start();
require_once 'includes/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: login.php?redirect=product_details.php?id=" . $_POST['product_id']); // Redirect back after login
    exit();
}

// Check if the product ID is set in the POST request
if (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];

    // Prevent adding the same product multiple times
    $check_sql = "SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Product is already in the wishlist
        $_SESSION['wishlist_message'] = "Product is already in your wishlist.";
    } else {
        // Add the product to the wishlist
        $insert_sql = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $product_id);

        if ($insert_stmt->execute()) {
            $_SESSION['wishlist_message'] = "Product added to wishlist!";
        } else {
            $_SESSION['wishlist_error'] = "Error adding product to wishlist.";
        }

        $insert_stmt->close();
    }

    $check_stmt->close();

    // Redirect back to the product details page
    header("Location: product_details.php?id=" . $product_id);
    exit();

} else {
    // If product ID is missing or invalid
    $_SESSION['wishlist_error'] = "Invalid product ID.";
    header("Location: index.php"); // Or wherever you want to redirect in case of an error
    exit();
}

$conn->close();
?>