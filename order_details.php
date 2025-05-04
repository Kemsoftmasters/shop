<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: order_history.php");
    exit();
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$servername = "localhost"; // Replace with your server name if it's different
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "kemsoft_masters_shop"; // Your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the main order details
$stmt_order = $conn->prepare("SELECT order_id, order_date, total_amount, payment_status,
                                    billing_name, billing_address, billing_city, billing_postal_code, billing_country,
                                    shipping_name, shipping_address, shipping_city, shipping_postal_code, shipping_country
                             FROM orders
                             WHERE order_id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows !== 1) {
    $_SESSION['order_details_error'] = "Order not found or does not belong to you.";
    header("Location: order_history.php");
    exit();
}

$order = $result_order->fetch_assoc();
$stmt_order->close();

// Fetch the items in the order
$stmt_items = $conn->prepare("SELECT oi.product_id, p.name, oi.quantity, oi.unit_price
                                    FROM order_items oi
                                    JOIN products p ON oi.product_id = p.product_id
                                    WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$order_items = [];
if ($result_items->num_rows > 0) {
    while ($item = $result_items->fetch_assoc()) {
        $order_items[] = $item;
    }
}
$stmt_items->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .order-details-container {
            width: 80%;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .order-details-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .order-info {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            background-color: #fff;
        }

        .order-info p {
            margin-bottom: 8px;
            color: #555;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .order-items-table th,
        .order-items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .order-items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .order-items-table tr:last-child td {
            border-bottom: none;
        }

        .back-link {
            margin-top: 20px;
            display: block;
            text-align: center;
            color: #777;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">Kemsoft Masters</div>
        <nav class="main-nav">
            <button class="hamburger-menu">☰</button>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="category.php">Categories</a></li>
                <li><a href="account.php">Account</a></li>
                <li><a href="wishlist.php">Wishlist</a></li>
                <li><a href="cart.php">Cart(<?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>)</a></li>
            </ul>
        </nav>
    </header>

    <main class="order-details-page">
        <div class="order-details-container">
            <h1>Order Details</h1>

            <?php if (isset($_SESSION['order_details_error'])): ?>
                <p class="error"><?php echo $_SESSION['order_details_error'];
                                        unset($_SESSION['order_details_error']); ?></p>
            <?php endif; ?>

            <div class="order-info">
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
                <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($order['order_date']))); ?></p>
                <p><strong>Payment Status:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></p>
                <p><strong>Total Amount:</strong> Ksh. <?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>

                <h3>Billing Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['billing_name']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['billing_address']); ?>, <?php echo htmlspecialchars($order['billing_city']); ?>, <?php echo htmlspecialchars($order['billing_postal_code']); ?>, <?php echo htmlspecialchars($order['billing_country']); ?></p>

                <h3>Shipping Information</h3>
                <?php if (
                    empty($order['shipping_name']) &&
                    empty($order['shipping_address']) &&
                    empty($order['shipping_city']) &&
                    empty($order['shipping_postal_code']) &&
                    empty($order['shipping_country'])
                ): ?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['billing_name']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['billing_address']); ?>, <?php echo htmlspecialchars($order['billing_city']); ?>, <?php echo htmlspecialchars($order['billing_postal_code']); ?>, <?php echo htmlspecialchars($order['billing_country']); ?></p>
                <?php else: ?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?>, <?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_postal_code']); ?>, <?php echo htmlspecialchars($order['shipping_country']); ?></p>
                <?php endif; ?>
            </div>

            <h2>Order Items</h2>
            <?php if (!empty($order_items)): ?>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>Ksh. <?php echo htmlspecialchars(number_format($item['unit_price'], 2)); ?></td>
                                <td>Ksh. <?php echo htmlspecialchars(number_format($item['quantity'] * $item['unit_price'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No items in this order.</p>
            <?php endif; ?>

            <a href="order_history.php" class="back-link">Back to Order History</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>
    <script src="js/script.js"></script>
</body>

</html>