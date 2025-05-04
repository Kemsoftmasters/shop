<?php
session_start();
require_once 'includes/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: login.php");
    exit();
}

// Check if the wishlist ID is set in the POST request
if (isset($_POST['wishlist_id']) && is_numeric($_POST['wishlist_id'])) {
    $wishlist_id_to_remove = $_POST['wishlist_id'];
    $user_id = $_SESSION['user_id'];

    // Prepare and execute the SQL query to delete the wishlist item
    $sql = "DELETE FROM wishlist WHERE wishlist_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $wishlist_id_to_remove, $user_id);

    if ($stmt->execute()) {
        $_SESSION['wishlist_message'] = "Product removed from wishlist.";
    } else {
        $_SESSION['wishlist_error'] = "Error removing product from wishlist.";
    }

    $stmt->close();

} else {
    // If wishlist ID is missing or invalid
    $_SESSION['wishlist_error'] = "Invalid wishlist item ID.";
}

// Redirect back to the wishlist page
header("Location: wishlist.php");
exit();

$conn->close();
?>