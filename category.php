<?php
session_start();
require_once 'includes/db_connect.php';

$category_name = null;
$products = [];
$error_message = null;

// Get the category ID from the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = $_GET['id'];

    // Fetch the category name, image URL, and description
    $sql_category = "SELECT name, image_url, description FROM categories WHERE category_id = ?";
    $stmt_category = $conn->prepare($sql_category);
    $stmt_category->bind_param("i", $category_id);
    $stmt_category->execute();
    $result_category = $stmt_category->get_result();

    if ($result_category->num_rows === 1) {
        $category = $result_category->fetch_assoc();
        $category_name = htmlspecialchars($category['name']);
        $category_image_url = htmlspecialchars($category['image_url']);
        $category_description = htmlspecialchars($category['description']); // Fetch description

        // Fetch products for the selected category
        $sql_products = "SELECT product_id, name, image_url, price
                         FROM products
                         WHERE category_id = ?";
        $stmt_products = $conn->prepare($sql_products);
        $stmt_products->bind_param("i", $category_id);
        $stmt_products->execute();
        $result_products = $stmt_products->get_result();
        $products = $result_products->fetch_all(MYSQLI_ASSOC);
        $stmt_products->close();

    } else {
        // Invalid category ID
        $error_message = "Invalid category selected.";
    }

    $stmt_category->close();

} else {
    // No category ID provided, show all categories with images and descriptions
    $sql_all_categories = "SELECT category_id, name, image_url, description FROM categories";
    $result_all_categories = $conn->query($sql_all_categories);
    $all_categories = [];
    if ($result_all_categories->num_rows > 0) {
        while ($row = $result_all_categories->fetch_assoc()) {
            $all_categories[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($category_name) ? $category_name : 'Product Categories'; ?> - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .category-page-container {
            padding: 30px;
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .category-list-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Adjusted min width */
            gap: 30px; /* Increased gap */
            margin-top: 20px;
        }

        .category-card {
            background-color: #f9f9f9;
            border-radius: 12px; /* More rounded cards */
            padding: 20px;
            text-align: center;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08); /* More pronounced shadow */
            transition: transform 0.2s ease-in-out;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-card a {
            text-decoration: none;
            color: #333;
            display: block;
        }

        .category-card img {
            max-width: 70%;
            height: auto;
            margin-bottom: 15px;
            border-radius: 10px; /* Rounded images */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06); /* Subtle image shadow */
        }

        .category-card h3 {
            font-size: 1.4em; /* More prominent title */
            margin-bottom: 10px;
            color: #444;
        }

        .category-card p {
            color: #777;
            font-size: 0.95em;
            margin-bottom: 15px;
            min-height: 40px; /* Ensure some consistent height for descriptions */
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .product-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.2s ease-in-out;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-card img {
            width: 100%;
            height: 220px;
            border-bottom: 1px solid #eee;
            object-fit: cover;
        }

        .product-details {
            padding: 20px;
            text-align: center;
        }

        .product-name {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 1.1em;
        }

        .product-price {
            color: #28a745;
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 12px;
        }

        .view-details-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .view-details-button:hover {
            background-color: #0056b3;
        }

        .error-message {
            color: red;
            text-align: center;
            padding: 20px;
            font-size: 1.1em;
        }

        .empty-category {
            text-align: center;
            padding: 20px;
            color: #777;
            font-size: 1.1em;
        }

        .view-all-button-container {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px;
        }

        .view-all-button {
            display: inline-block;
            background-color: #6c757d; /* Gray button */
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .view-all-button:hover {
            background-color: #545b62;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .category-list-overview {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Adjusted min width */
                gap: 20px;
            }

            .category-card img {
                max-width: 60%;
            }

            .category-card h3 {
                font-size: 1.2em;
            }

            .category-card p {
                font-size: 0.9em;
                min-height: auto;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                padding: 15px;
                gap: 15px;
            }

            .product-card img {
                height: 180px;
            }

            .product-details {
                padding: 10px;
            }

            .product-name {
                font-size: 1em;
                margin-bottom: 5px;
            }

            .product-price {
                font-size: 1.1em;
                margin-bottom: 8px;
            }

            .view-details-button {
                padding: 8px 12px;
                font-size: 0.9em;
                border-radius: 6px;
            }

            .view-all-button {
                padding: 10px 20px;
                font-size: 0.9em;
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

    <main class="category-page-container">
        <?php if (isset($error_message)): ?>
            <h1 class="error-message"><?php echo htmlspecialchars($error_message); ?></h1>
        <?php elseif (isset($category_name)): ?>
            <h1><?php echo htmlspecialchars($category_name); ?></h1>
            <?php if (!empty($category_image_url)): ?>
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo $category_image_url; ?>" alt="<?php echo htmlspecialchars($category_name); ?>" style="max-width: 300px; height: auto; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.06);">
                </div>
            <?php endif; ?>
            <?php if (!empty($category_description)): ?>
                <p style="text-align: center; color: #666; margin-bottom: 30px;"><?php echo htmlspecialchars($category_description); ?></p>
            <?php endif; ?>
            <div class="product-grid">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="product-details">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-price">Ksh. <?php echo htmlspecialchars(number_format($product['price'], 2)); ?></p>
                                <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="view-details-button">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-category">No products found in this category.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1>Product Categories</h1>
            <p style="text-align: center; margin-bottom: 20px; color: #666;">Browse products by category:</p>
            <?php if (!empty($all_categories)): ?>
                <div class="category-list-overview">
                    <?php foreach ($all_categories as $cat): ?>
                        <div class="category-card">
                            <a href="category.php?id=<?php echo $cat['category_id']; ?>">
                                <?php if (!empty($cat['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($cat['image_url']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php else: ?>
                                    <img src="images/category_placeholder.png" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($cat['name']); ?></h3>
                                <?php if (!empty($cat['description'])): ?>
                                    <p><?php echo htmlspecialchars($cat['description']); ?></p>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="view-all-button-container">
                    <a href="products.php" class="view-all-button">View All Products</a>
                </div>
            <?php else: ?>
                <p class="empty-category">No categories available.</p>
            <?php endif; ?>
        <?php endif; ?>
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