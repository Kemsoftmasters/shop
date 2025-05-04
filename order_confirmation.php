<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .order-confirmation-page {
            padding: 60px 0;
            background-color: #f8f9fa;
            text-align: center;
        }

        .confirmation-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            font-size: 2.5em;
            color: #28a745; /* A success color */
            margin-bottom: 20px;
        }

        .thank-you-message {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 15px;
        }

        .order-id {
            font-weight: bold;
            color: #007bff;
        }

        .processing-message {
            color: #555;
            margin-bottom: 30px;
        }

        .continue-shopping-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .continue-shopping-button:hover {
            background-color: #0056b3;
        }

        .error-message {
            color: #dc3545;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .homepage-button {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .homepage-button:hover {
            background-color: #545b62;
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
                <li><a href="cart.php">
                        Cart(<?php
                            $total_quantity = 0;
                            if (isset($_SESSION['cart'])) {
                                foreach ($_SESSION['cart'] as $item) {
                                    $total_quantity += $item['quantity'];
                                }
                            }
                            echo $total_quantity;
                            ?>)</a></li>
            </ul>
        </nav>
    </header>

    <main class="order-confirmation-page">
        <div class="confirmation-container">
            <h1>Order Confirmed</h1>

            <?php if (isset($_GET['order_id'])): ?>
                <p class="thank-you-message">Thank you for your order!</p>
                <p class="order-id">Your order ID is: <?php echo htmlspecialchars($_GET['order_id']); ?></p>
                <p class="processing-message">We will process your order and send you a confirmation email shortly.</p>
                <p><a href="index.php" class="continue-shopping-button">Continue Shopping</a></p>
            <?php else: ?>
                <p class="error-message">Error: No order ID found.</p>
                <p><a href="index.php" class="homepage-button">Go to Homepage</a></p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>