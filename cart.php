<?php
session_start();

$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total_price = 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <header>
        <div class="logo">Kemsoft Masters</div>
        <nav class="main-nav">
            <button class="hamburger-menu">
                ☰
            </button>
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

    <main class="cart-page">
        <h1>Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <p>Your cart is empty.</p>
            <p><a href="index.php" class="button">Continue shopping</a></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <?php
                        $subtotal = $item['price'] * $item['quantity'];
                        $total_price += $subtotal;
                        ?>
                        <tr>
                            <td data-title="Product">
                                <div class="cart-product">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-thumbnail">
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                            </td>
                            <td data-title="Price">Ksh. <?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
                            <td data-title="Quantity">
                                <form action="update_cart.php" method="post">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                                    <input type="number" name="quantity" value="<?php echo htmlspecialchars($item['quantity']); ?>" min="1">
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                            <td data-title="Subtotal">Ksh. <?php echo htmlspecialchars(number_format($subtotal, 2)); ?></td>
                            <td data-title="Action">
                                <form action="remove_from_cart.php" method="post">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                                    <button type="submit" class="remove-button">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                        <td><strong>Ksh. <?php echo htmlspecialchars(number_format($total_price, 2)); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="cart-actions">
                <a href="index.php" class="button continue-shopping">Continue Shopping</a>
                <a href="checkout.php" class="button primary checkout">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
</body>

</html>