<?php
session_start();

$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total_price = 0;

if (empty($cart_items)) {
    header("Location: cart.php"); // Redirect to cart if empty
    exit();
}

// Calculate total price
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .checkout-page {
            padding: 40px 0;
            background-color: #f8f9fa;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px; /* Adjust width as needed */
            gap: 30px;
        }

        .billing-shipping-info {
            background-color: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .order-summary-section {
            background-color: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            position: sticky; /* Keep it visible on scroll */
            top: 20px;
        }

        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }

        #shipping_address_fields {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: #f9f9f9;
        }

        #shipping_address_fields.hidden {
            display: none;
        }

        /* Order Summary Styles */
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item .item-name {
            flex-grow: 1;
            color: #333;
        }

        .summary-item .item-price {
            width: 100px;
            text-align: right;
            color: #555;
            font-weight: bold;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .place-order-button {
            background-color: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }

        .place-order-button:hover {
            background-color: #218838;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary-section {
                order: -1; /* Move order summary above form on smaller screens */
                margin-bottom: 20px;
                position: static; /* Remove sticky on smaller screens */
            }
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

    <main class="checkout-page">
        <div class="container">
            <h1>Checkout</h1>
            <div class="checkout-grid">
                <div class="billing-shipping-info">
                    <form class="checkout-form" action="process_checkout.php" method="post">
                        <h2>Billing Information</h2>
                        <div class="form-group">
                            <label for="billing_name">Full Name</label>
                            <input type="text" id="billing_name" name="billing_name" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_address">Street Address</label>
                            <input type="text" id="billing_address" name="billing_address" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_apartment">Apartment, Suite, etc. (Optional)</label>
                            <input type="text" id="billing_apartment" name="billing_apartment">
                        </div>
                        <div class="form-group">
                            <label for="billing_city">City</label>
                            <input type="text" id="billing_city" name="billing_city" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_postal_code">Postal Code</label>
                            <input type="text" id="billing_postal_code" name="billing_postal_code" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_country">Country</label>
                            <input type="text" id="billing_country" name="billing_country" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_email">Email Address</label>
                            <input type="email" id="billing_email" name="billing_email" required>
                        </div>
                        <div class="form-group">
                            <label for="billing_phone">Phone Number</label>
                            <input type="tel" id="billing_phone" name="billing_phone" required>
                        </div>

                        <h2>Shipping Information</h2>
                        <div class="form-group">
                            <input type="checkbox" id="same_as_billing" name="same_as_billing" checked>
                            <label for="same_as_billing">Ship to the same address</label>
                        </div>

                        <div id="shipping_address_fields">
                            <div class="form-group">
                                <label for="shipping_name">Full Name</label>
                                <input type="text" id="shipping_name" name="shipping_name">
                            </div>
                            <div class="form-group">
                                <label for="shipping_address">Street Address</label>
                                <input type="text" id="shipping_address" name="shipping_address">
                            </div>
                            <div class="form-group">
                                <label for="shipping_apartment">Apartment, Suite, etc. (Optional)</label>
                                <input type="text" id="shipping_apartment" name="shipping_apartment">
                            </div>
                            <div class="form-group">
                                <label for="shipping_city">City</label>
                                <input type="text" id="shipping_city" name="shipping_city">
                            </div>
                            <div class="form-group">
                                <label for="shipping_postal_code">Postal Code</label>
                                <input type="text" id="shipping_postal_code" name="shipping_postal_code">
                            </div>
                            <div class="form-group">
                                <label for="shipping_country">Country</label>
                                <input type="text" id="shipping_country" name="shipping_country">
                            </div>
                        </div>

                        <div class="checkout-button-group">
                            <button type="submit" class="place-order-button">Place Order</button>
                        </div>
                    </form>
                </div>

                <aside class="order-summary-section">
                    <h2>Order Summary</h2>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-item">
                            <span class="item-name"><?php echo htmlspecialchars($item['name']) . ' (x' . htmlspecialchars($item['quantity']) . ')'; ?></span>
                            <span class="item-price">Ksh. <?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'], 2)); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total:</span>
                        <span>Ksh. <?php echo htmlspecialchars(number_format($total_price, 2)); ?></span>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script>
        const sameAsBillingCheckbox = document.getElementById('same_as_billing');
        const shippingAddressFields = document.getElementById('shipping_address_fields');

        sameAsBillingCheckbox.addEventListener('change', function() {
            shippingAddressFields.style.display = this.checked ? 'none' : 'block';
        });

        // Initially hide shipping fields if "same as billing" is checked
        if (sameAsBillingCheckbox.checked) {
            shippingAddressFields.style.display = 'none';
        }
    </script>
    <script src="js/script.js"></script>
</body>
</html>