<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['product_id']) && is_numeric($_POST['product_id']) && isset($_POST['quantity']) && is_numeric($_POST['quantity']) && $_POST['quantity'] > 0) {
        $product_id = $_POST['product_id'];
        $quantity = (int)$_POST['quantity'];

        // Include database connection
        require_once 'includes/db_connect.php';

        $sql = "SELECT product_id, name, price, image_url FROM products WHERE product_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $product = $result->fetch_assoc();
        
            // Initialize the cart if it doesn't exist
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
        
            // Check if the product is already in the cart
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'product_id' => $product_id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image_url' => $product['image_url'], // Store the image URL
                    'quantity' => $quantity
                ];
            }
        

            // Redirect the user back to the product details page or to the cart page
            header("Location: product_details.php?id=" . $product_id . "&cart_message=added"); // You can modify the redirection
            exit();

        } else {
            // Product not found
            header("Location: index.php?error=product_not_found"); // Redirect with an error message
            exit();
        }

        $stmt->close();
        $conn->close();

    } else {
        // Invalid product ID or quantity
        header("Location: index.php?error=invalid_data"); // Redirect with an error message
        exit();
    }
} else {
    // If the page is accessed directly without a POST request
    header("Location: index.php");
    exit();
}
?>