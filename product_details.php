<?php
session_start(); // Ensure session_start() is at the very beginning

require_once 'includes/db_connect.php';

// Get the product ID from the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];

    // Prepare and execute the SQL query to fetch product details
    $sql = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id); // "i" indicates an integer parameter
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();

        // Fetch related products based on category
        $stmt_related = $conn->prepare("SELECT product_id, name, image_url, price
                                          FROM products
                                          WHERE category_id = ? AND product_id != ?
                                          LIMIT 4"); // Limit to 4 related products
        $stmt_related->bind_param("ii", $product['category_id'], $product_id);
        $stmt_related->execute();
        $result_related = $stmt_related->get_result();
        $related_products = $result_related->fetch_all(MYSQLI_ASSOC);
        $stmt_related->close();

    } else {
        // If no product is found with the given ID, we can display an error message
        $error_message = "Product not found.";
    }

    $stmt->close();
} else {
    // If the 'id' parameter is missing or not valid, display an error
    $error_message = "Invalid product ID.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($product['name']) ? htmlspecialchars($product['name']) : 'Product Details'; ?> - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .related-products {
            padding: 30px 20px;
            text-align: center;
            background-color: #f9f9f9;
            border-top: 1px solid #eee;
        }

        .related-products h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .related-product-card {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .related-product-card a {
            display: block;
            text-decoration: none;
            color: #333;
            padding: 15px;
        }

        .related-product-card img {
            width: 100%;
            height: auto;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
            object-fit: cover; /* To maintain aspect ratio */
            max-height: 150px;
        }

        .related-product-card h3 {
            font-size: 1em;
            margin-bottom: 5px;
            text-align: center;
        }

        .related-product-card p {
            color: #555;
            font-weight: bold;
            text-align: center;
        }

        .wishlist-button {
            background-color: #f0ad4e; /* A warm, inviting color */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px; /* Add some spacing */
        }

        .wishlist-button:hover {
            background-color: #e0952d;
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

    <main>
        <section class="product-details">
            <?php if (isset($_SESSION['wishlist_message'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['wishlist_message']); ?></p>
                <?php unset($_SESSION['wishlist_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['wishlist_error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['wishlist_error']); ?></p>
                <?php unset($_SESSION['wishlist_error']); ?>
            <?php endif; ?>

            <?php if (isset($_GET['cart_message']) && $_GET['cart_message'] === 'added'): ?>
                <?php
                $total_quantity = 0;
                if (isset($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $item) {
                        $total_quantity += $item['quantity'];
                    }
                }
                ?>
                <p class="success">Product added to cart! Total items: <?php echo $total_quantity; ?></p>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php elseif (isset($product)): ?>
                <div class="product-image">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                <div class="product-info">
                    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                    <p class="price">Ksh. <?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                    <p class="description"><?php echo htmlspecialchars($product['description']); ?></p>
                    <p>Stock: <?php echo htmlspecialchars($product['stock_quantity']); ?></p>

                    <form action="add_to_cart.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                        <div>
                            <label for="quantity">Quantity:</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['stock_quantity']); ?>">
                        </div>
                        <button type="submit" class="button">Add to Cart</button>
                    </form>

                    <form action="add_to_wishlist.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                        <button type="submit" class="wishlist-button">Add to Wishlist</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Loading product details...</p>
            <?php endif; ?>
        </section>

        <?php if (isset($related_products) && !empty($related_products)): ?>
            <section class="related-products">
                <h2>You Might Also Like</h2>
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related_product): ?>
                        <div class="related-product-card">
                            <a href="product_details.php?id=<?php echo $related_product['product_id']; ?>">
                                <img src="<?php echo htmlspecialchars($related_product['image_url']); ?>" alt="<?php echo htmlspecialchars($related_product['name']); ?>">
                                <h3><?php echo htmlspecialchars($related_product['name']); ?></h3>
                                <p>Ksh. <?php echo htmlspecialchars(number_format($related_product['price'], 2)); ?></p>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
</body>

</html>