<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['product_id']) && is_numeric($_POST['product_id'])) {
        $product_id = $_POST['product_id'];

        // Check if the product exists in the cart
        if (isset($_SESSION['cart'][$product_id])) {
            // Remove the product from the cart
            unset($_SESSION['cart'][$product_id]);
        }
    }
}

// Redirect the user back to the cart page
header("Location: cart.php");
exit();
?>