<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['product_id']) && is_numeric($_POST['product_id']) && isset($_POST['quantity']) && is_numeric($_POST['quantity']) && $_POST['quantity'] > 0) {
        $product_id = $_POST['product_id'];
        $quantity = (int)$_POST['quantity'];

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        }
    } elseif (isset($_POST['product_id']) && is_numeric($_POST['product_id']) && isset($_POST['quantity']) && is_numeric($_POST['quantity']) && $_POST['quantity'] <= 0) {
        // If quantity is zero or less, we can remove the item
        unset($_SESSION['cart'][$_POST['product_id']]);
    }
}

// Redirect the user back to the cart page
header("Location: cart.php");
exit();
?>