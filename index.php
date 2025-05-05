<?php
session_start(); // Ensure session_start() is at the very beginning
require_once 'includes/db_connect.php';

// Fetch featured products (first 8 for this example)
$sql_featured = "SELECT product_id, name, price, image_url FROM products LIMIT 8";
$result_featured = $conn->query($sql_featured);
$featured_products = [];
if ($result_featured->num_rows > 0) {
    while ($row = $result_featured->fetch_assoc()) {
        $featured_products[] = $row;
    }
}

// Fetch a few main categories for the overview
$sql_categories_overview = "SELECT category_id, name FROM categories LIMIT 3"; // Adjust limit as needed
$result_categories_overview = $conn->query($sql_categories_overview);
$categories_overview = [];
if ($result_categories_overview->num_rows > 0) {
    while ($row = $result_categories_overview->fetch_assoc()) {
        $categories_overview[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kemsoft Masters - Your Online Store</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Hero Section Styles */
        .hero {
            background-color: #f8f9fa; /* Light background */
            padding: 80px 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .hero h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 25px;
        }

        .hero .button {
            display: inline-block;
            background-color: #007bff; /* Primary button color */
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .hero .button:hover {
            background-color: #0056b3;
        }

        /* Featured Products Section */
        .featured-products {
            padding: 30px 20px;
            text-align: center;
            background-color: #fff;
            margin-bottom: 30px;
        }

        .featured-products h2 {
            font-size: 2em;
            color: #333;
            margin-bottom: 20px;
        }

        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-item {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .product-item img {
            max-width: 100%;
            height: auto;
            max-height: 150px;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .product-item h3 {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 5px;
        }

        .product-item p {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .product-item .button {
            display: inline-block;
            background-color: #28a745; /* Success button color */
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }

        .product-item .button:hover {
            background-color: #218838;
        }

        /* Categories Overview Section */
        .categories-overview {
            padding: 30px 20px;
            text-align: center;
            background-color: #fff;
            margin-bottom: 30px;
        }

        .categories-overview h2 {
            font-size: 2em;
            color: #333;
            margin-bottom: 20px;
        }

        .category-list {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .category-item {
            text-align: center;
        }

        .category-item a {
            text-decoration: none;
            color: #333;
        }

        .category-item img {
            max-width: 100px;
            height: auto;
            margin-bottom: 10px;
            border-radius: 50%; /* Circular images */
            border: 1px solid #ddd;
        }

        .category-item h3 {
            font-size: 1em;
            margin-top: 0;
        }

        /* Why Choose Us Section */
        .why-choose-us {
            padding: 40px 20px;
            background-color: #f8f9fa;
            text-align: center;
            margin-bottom: 30px;
        }

        .why-choose-us h2 {
            font-size: 2em;
            color: #333;
            margin-bottom: 20px;
        }

        .features-list {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .feature-item {
            text-align: center;
        }

        .feature-item h3 {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 10px;
        }

        .feature-item p {
            color: #666;
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
        <section class="hero">
            <h1>Welcome to Kemsoft Masters!</h1>
            <p>Your trusted online destination for quality products and exceptional service in Kenya.</p>
            <a href="products.php" class="button">Shop Now</a>
        </section>

        <section class="featured-products">
            <h2>Featured Products</h2>
            <div class="product-list">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product): ?>
                        <div class="product-item">
                            <img src="<?php echo htmlspecialchars($product["image_url"]); ?>" alt="<?php echo htmlspecialchars($product["name"]); ?>">
                            <h3><?php echo htmlspecialchars($product["name"]); ?></h3>
                            <p>Ksh. <?php echo htmlspecialchars(number_format($product["price"], 2)); ?></p>
                            <a href="product_details.php?id=<?php echo $product["product_id"]; ?>" class="button">View Details</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No featured products available at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($categories_overview)): ?>
        <section class="categories-overview">
            <h2>Explore Our Categories</h2>
            <div class="category-list">
                <?php foreach ($categories_overview as $category): ?>
                    <div class="category-item">
                        <a href="category.php?id=<?php echo $category['category_id']; ?>">
                            <img src="images/mouse.png" alt="<?php echo htmlspecialchars($category['name']); ?>"> <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="why-choose-us">
            <h2>Why Choose Kemsoft Masters?</h2>
            <div class="features-list">
                <div class="feature-item">
                    <h3>Quality Products</h3>
                    <p>We offer a curated selection of high-quality products.</p>
                </div>
                <div class="feature-item">
                    <h3>Excellent Service</h3>
                    <p>Our team is dedicated to providing exceptional customer support.</p>
                </div>
                <div class="feature-item">
                    <h3>Local Focus (Kenya)</h3>
                    <p>Serving the Kenyan market with reliable and timely delivery.</p>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
</body>

</html>

<?php
$conn->close();
?>