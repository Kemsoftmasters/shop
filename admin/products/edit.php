<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// --- Include Admin Header and Sidebar (Placeholder) ---
include __DIR__ . '/../includes/admin_header.php';
include __DIR__ . '/../includes/admin_sidebar.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$product_id = null;
$product = null;
$error_message = '';
$upload_error = '';
$success_message = '';

// --- Get Product ID from URL and Fetch Product Data ---
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];

    // Fetch the product data
    $sql_fetch = "SELECT product_id, name, description, price, image_url, category_id, stock_quantity FROM products WHERE product_id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $product_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();

    if ($result_fetch->num_rows === 1) {
        $product = $result_fetch->fetch_assoc();
    } else {
        // Product not found
        $_SESSION['error_message'] = "Product not found.";
        header('Location: index.php'); // Redirect back to products list
        exit();
    }
    $stmt_fetch->close();
} else if ($_SERVER["REQUEST_METHOD"] !== "POST") { // Only redirect if it's not a POST request (meaning no ID was provided on initial load)
    // No product ID provided in the URL
    $_SESSION['error_message'] = "No product ID provided.";
    header('Location: index.php'); // Redirect back to products list
    exit();
}


// --- Handle Form Submission (Update Product) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get product ID and updated form data
    $product_id = $_POST['product_id']; // Get the product ID from a hidden input field
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL; // Handle nullable category_id
    $stock_quantity = $_POST['stock_quantity'];
    $existing_image_url = $_POST['existing_image_url'] ?? null; // Get existing image URL from hidden field

    $image_url_to_save = $existing_image_url; // Start with the existing image URL

    // --- Image Upload Handling (Similar to Add, with Deletion of Old Image) ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) { // Check if a new file was submitted
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../images/products/'; // Directory to save product images

            // Ensure upload directory exists and is writable
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0775, true)) {
                    $upload_error = "Upload directory does not exist and could not be created.";
                }
            }

            if ($upload_error === '' && !is_writable($upload_dir)) {
                $upload_error = "Upload directory is not writable. Please check permissions.";
            }

            if ($upload_error === '') { // Proceed only if no directory errors
                $image_name = basename($_FILES['image']['name']);
                $target_file = $upload_dir . $image_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $upload_ok = 1;

                if (!in_array($imageFileType, $valid_extensions)) {
                    $upload_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                    $upload_ok = 0;
                }

                if ($upload_ok == 1) {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        // File uploaded successfully
                        $image_url_to_save = 'images/products/' . $image_name;

                        // *** Delete the old image file if it exists and is different from the new one ***
                        if ($existing_image_url && $existing_image_url !== $image_url_to_save) {
                            $old_image_path = '../../' . $existing_image_url; // Path to the old image file
                            if (file_exists($old_image_path) && is_file($old_image_path)) {
                                unlink($old_image_path); // Delete the file
                            }
                        }
                    } else {
                        $upload_error = "Sorry, there was an error moving the uploaded file.";
                    }
                }
            }
        } else {
            // Handle specific upload errors
            switch ($_FILES['image']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $upload_error = "Uploaded file exceeds maximum file size.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $upload_error = "File was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $upload_error = "Missing a temporary folder for uploads.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $upload_error = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $upload_error = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $upload_error = "Unknown file upload error.";
                    break;
            }
        }
    }
    // If no new file was uploaded (UPLOAD_ERR_NO_FILE), $image_url_to_save remains the $existing_image_url.


    // --- Update Product in Database ---
    if ($upload_error === '') { // Only update if there were no upload errors
        $sql_update = "UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, category_id = ?, stock_quantity = ? WHERE product_id = ?";

        $stmt_update = $conn->prepare($sql_update);

        // Bind parameters
        $stmt_update->bind_param(
            "ssdsiii", // data types: name, description, price, image_url, category_id, stock_quantity, product_id
            $name,
            $description,
            $price,
            $image_url_to_save, // Use the potentially new image URL
            $category_id,
            $stock_quantity,
            $product_id
        );

        if ($stmt_update->execute()) {
            // Product updated successfully
            $_SESSION['success_message'] = "Product updated successfully!";
            header('Location: index.php'); // Redirect back to products list
            exit();
        } else {
            // Error updating product
            $error_message = "Error updating product: " . $stmt_update->error;
            // Re-fetch product data in case of error to keep the form populated with latest (failed) submission
            $sql_fetch_again = "SELECT product_id, name, description, price, image_url, category_id, stock_quantity FROM products WHERE product_id = ?";
            $stmt_fetch_again = $conn->prepare($sql_fetch_again);
            $stmt_fetch_again->bind_param("i", $product_id);
            $stmt_fetch_again->execute();
            $result_fetch_again = $stmt_fetch_again->get_result();
            if ($result_fetch_again->num_rows === 1) {
                $product = $result_fetch_again->fetch_assoc();
            }
            $stmt_fetch_again->close();
        }
        $stmt_update->close();
    }
}


// --- Fetch Categories for Dropdown ---
$categories = [];
$sql_categories = "SELECT category_id, name FROM categories ORDER BY name ASC";
$result_categories = $conn->query($sql_categories);

if ($result_categories->num_rows > 0) {
    while ($row_category = $result_categories->fetch_assoc()) {
        $categories[] = $row_category;
    }
}

// Close the database connection if necessary (depends on db_connect.php)
// closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Basic form styling (can move to admin_style.css) */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            /* Allow vertical resizing */
            min-height: 100px;
        }

        .current-image {
            margin-top: 10px;
            max-width: 150px;
            /* Limit image size in the form */
            height: auto;
            display: block;
        }

        .button-group {
            margin-top: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 15px;
            background-color: #5cb85c;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .button-secondary {
            background-color: #ddd;
            color: #333;
        }

        .error-message {
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .success-message {
            color: #28a745;
            margin-bottom: 15px;
        }

        .upload-error {
            color: #e74c3c;
            margin-top: 5px;
            font-size: 0.9em;
        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar (Placeholder) ---
        // include __DIR__ . '/../includes/admin_header.php';
        // include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Edit Product</h1>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-message"><?php echo $_SESSION['error_message'];
                                            unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if (isset($upload_error)): ?>
                <div class="error-message"><?php echo $upload_error; ?></div>
            <?php endif; ?>


            <?php if ($product): ?>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['product_id']); ?>">
                    <input type="hidden" name="existing_image_url" value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>">


                    <div class="form-group">
                        <label for="name">Product Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price:</label>
                        <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="image">Product Image:</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <?php if ($product['image_url']): ?>
                            <p>Current Image:</p>
                            <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Current Product Image" class="current-image">
                        <?php endif; ?>
                        <?php if (isset($upload_error)): ?>
                            <div class="upload-error"><?php echo $upload_error; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="category_id">Category:</label>
                        <select id="category_id" name="category_id">
                            <option value="">Select a Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category_id']); ?>"
                                    <?php if ($product['category_id'] == $category['category_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity:</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="button">Update Product</button>
                        <a href="index.php" class="button button-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div> <!-- /.admin-container -->

    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

    </div>
</body>

</html>