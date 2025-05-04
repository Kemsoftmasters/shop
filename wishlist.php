<?php
session_start();
require_once 'includes/db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch wishlist items for the logged-in user
$sql = "SELECT w.wishlist_id, p.product_id, p.name, p.image_url, p.price
        FROM wishlist w
        JOIN products p ON w.product_id = p.product_id
        WHERE w.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .wishlist-container {
            padding: 30px;
            max-width: 960px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .wishlist-item {
            display: grid;
            grid-template-columns: 100px 1fr auto; /* Image | Details | Actions */
            gap: 20px;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .wishlist-item:last-child {
            border-bottom: none;
        }

        .wishlist-item img {
            width: 100%;
            height: auto;
            border-radius: 4px;
            object-fit: cover;
        }

        .wishlist-item-details {
            justify-self: start;
        }

        .wishlist-item-name {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.1em;
            color: #333;
        }

        .wishlist-item-price {
            color: #007bff; /* A more prominent price color */
            font-weight: bold;
        }

        .wishlist-actions {
            display: flex;
            gap: 10px;
            justify-self: end;
        }

        .wishlist-actions form {
            display: inline;
        }

        .add-to-cart-button,
        .remove-wishlist-button {
            background-color: #28a745; /* Green for Add to Cart */
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }

        .remove-wishlist-button {
            background-color: #dc3545; /* Red for Remove */
        }

        .add-to-cart-button:hover {
            background-color: #218838;
        }

        .remove-wishlist-button:hover {
            background-color: #c82333;
        }

        .wishlist-empty {
            text-align: center;
            padding: 30px;
            color: #777;
            font-size: 1.1em;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .wishlist-item {
                grid-template-columns: 80px 1fr auto;
                gap: 10px;
                padding: 10px;
            }

            .wishlist-item img {
                width: 80px;
            }

            .wishlist-actions {
                flex-direction: column;
                align-items: flex-end;
            }

            .wishlist-actions button {
                width: 100%;
                margin-bottom: 5px;
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
                <li><a href="wishlist.php">Wishlist</a></li>
            </ul>
        </nav>
    </header>

    <main class="wishlist-container">
        <h1>My Wishlist</h1>

        <?php if (isset($_SESSION['wishlist_message'])): ?>
            <p class="success"><?php echo htmlspecialchars($_SESSION['wishlist_message']); ?></p>
            <?php unset($_SESSION['wishlist_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['wishlist_error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION['wishlist_error']); ?></p>
            <?php unset($_SESSION['wishlist_error']); ?>
        <?php endif; ?>

        <?php if (!empty($wishlist_items)): ?>
            <?php foreach ($wishlist_items as $item): ?>
                <div class="wishlist-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="wishlist-item-details">
                        <div class="wishlist-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="wishlist-item-price">Ksh. <?php echo htmlspecialchars(number_format($item['price'], 2)); ?></div>
                    </div>
                    <div class="wishlist-actions">
                        <form action="add_to_cart.php" method="post">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="add-to-cart-button">Add to Cart</button>
                        </form>
                        <form action="remove_from_wishlist.php" method="post">
                            <input type="hidden" name="wishlist_id" value="<?php echo htmlspecialchars($item['wishlist_id']); ?>">
                            <button type="submit" class="remove-wishlist-button">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="wishlist-empty">Your wishlist is currently empty.</p>
        <?php endif; ?>

    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
</body>

</html>