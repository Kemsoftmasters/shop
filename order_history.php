<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT o.order_id, o.order_date, o.total_amount, o.payment_status, COUNT(oi.item_id) AS item_count
                        FROM orders o
                        LEFT JOIN order_items oi ON o.order_id = oi.order_id
                        WHERE o.user_id = ?
                        GROUP BY o.order_id
                        ORDER BY o.order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Existing styles */
        .order-history-container {
            width: 80%;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .order-history-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .order-list {
            margin-bottom: 20px;
        }

        .order-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
            background-color: #fff;
            cursor: pointer;
            /* Indicate it's clickable */
        }

        .order-item p {
            margin-bottom: 5px;
            color: #555;
        }

        .order-item .status {
            font-weight: bold;
        }

        .order-item .status.pending {
            color: orange;
        }

        .order-item .status.completed {
            color: green;
        }

        .order-item .status.shipped {
            color: blue;
        }

        .order-item .status.cancelled {
            color: red;
        }

        .no-orders {
            text-align: center;
            color: #777;
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
    </style>
    <script>
        function viewOrderDetails(orderId) {
            window.location.href = 'order_details.php?id=' + orderId;
        }
    </script>
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

    <main class="order-history-page">
        <div class="order-history-container">
            <h1>Order History</h1>

            <?php if (!empty($orders)): ?>
                <div class="order-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
                            <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date("F j, Y", strtotime($order['order_date']))); ?></p>
                            <p><strong>Total Amount:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                            <p><strong>Status:</strong> <span class="status <?php echo htmlspecialchars(strtolower($order['payment_status'])); ?>"><?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></span></p>
                            <p><strong>Items:</strong> <?php echo htmlspecialchars($order['item_count']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-orders">No order history available.</p>
            <?php endif; ?>

            <a href="account.php" class="back-link">Back to Account</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>
    <script src="js/script.js"></script>
</body>

</html>