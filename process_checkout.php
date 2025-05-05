<?php
session_start();

// Database connection details
require_once 'includes/db_connect.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function sanitize_input(string $data): string
{
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve billing and shipping information
    $billing_name = sanitize_input($_POST["billing_name"] ?? '');
    $billing_address = sanitize_input($_POST["billing_address"] ?? '');
    $billing_apartment = sanitize_input($_POST["billing_apartment"] ?? '');
    $billing_city = sanitize_input($_POST["billing_city"] ?? '');
    $billing_postal_code = sanitize_input($_POST["billing_postal_code"] ?? '');
    $billing_country = sanitize_input($_POST["billing_country"] ?? '');
    $billing_email = sanitize_input($_POST["billing_email"] ?? '');
    $billing_phone = sanitize_input($_POST["billing_phone"] ?? '');

    $shipping_name = sanitize_input($_POST["shipping_name"] ?? '');
    $shipping_address = sanitize_input($_POST["shipping_address"] ?? '');
    $shipping_apartment = sanitize_input($_POST["shipping_apartment"] ?? '');
    $shipping_city = sanitize_input($_POST["shipping_city"] ?? '');
    $shipping_postal_code = sanitize_input($_POST["shipping_postal_code"] ?? '');
    $shipping_country = sanitize_input($_POST["shipping_country"] ?? '');

    $cart_items = $_SESSION['cart'] ?? [];
    $total_amount = 0;
    $order_details = []; // Array to hold item details for insertion

    if (!empty($cart_items)) {
        foreach ($cart_items as $product_id => $item) {
            $quantity = $item['quantity'] ?? 0;
            $price = $item['price'] ?? 0;
            $subtotal = $price * $quantity;
            $total_amount += $subtotal;

            $order_details[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal
            ];
        }

        $conn->begin_transaction();

        try {
            // Create the order
            $sql_order = "INSERT INTO orders (
                user_id,
                billing_name, billing_address, billing_apartment, billing_city, billing_postal_code, billing_country, billing_email, billing_phone,
                shipping_name, shipping_address, shipping_apartment, shipping_city, shipping_postal_code, shipping_country,
                total_amount, order_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_order = $conn->prepare($sql_order);
            $stmt_order->bind_param(
                "issssssssssssssd",
                $user_id, $billing_name, $billing_address, $billing_apartment, $billing_city, $billing_postal_code, $billing_country, $billing_email, $billing_phone,
                $shipping_name, $shipping_address, $shipping_apartment, $shipping_city, $shipping_postal_code, $shipping_country, $total_amount
            );

            if ($stmt_order->execute()) {
                $order_id = $conn->insert_id;

                // Insert order items using prepared statement
                if (!empty($order_details)) {
                    $sql_order_item = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_order_item);

                    foreach ($order_details as $item) {
                        $stmt_item->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                        $stmt_item->execute();
                    }
                    $stmt_item->close();

                    $conn->commit();
                    unset($_SESSION['cart']);
                    header("Location: order_confirmation.php?order_id=$order_id");
                    exit();
                } else {
                    $conn->commit();
                    echo "No items in the cart to process.";
                }
            } else {
                throw new Exception("Error creating order: " . $stmt_order->error);
            }

            $stmt_order->close();

        } catch (Exception $e) {
            $conn->rollback();
            echo "An error occurred during checkout: " . $e->getMessage();
        }

    } else {
        echo "Your cart is empty.";
    }
} else {
    header("Location: checkout.php");
    exit();
}

$conn->close();
?>