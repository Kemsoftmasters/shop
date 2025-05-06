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

// --- Handle Form Submission ---
// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    // FIX: Handle empty category_id for nullable foreign key
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : NULL;
    $stock_quantity = $_POST['stock_quantity'];

    // --- Image Upload Handling (Basic Example) ---
    // --- Image Upload Handling ---
    $image_url = null; // Default image URL to null
    $upload_error = ''; // Initialize upload error message

    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) { // Check if a file was submitted
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../images/products/'; // Directory to save product images

            // Ensure upload directory exists and is writable
            if (!is_dir($upload_dir)) {
                // Attempt to create the directory if it doesn't exist
                if (!mkdir($upload_dir, 0775, true)) { // Use 0775 or 0755 depending on your server setup
                    $upload_error = "Upload directory does not exist and could not be created.";
                }
            }

            // Check if the directory is writable
            if ($upload_error === '' && !is_writable($upload_dir)) {
                $upload_error = "Upload directory is not writable. Please check permissions.";
            }


            if ($upload_error === '') { // Proceed only if no directory errors
                $image_name = basename($_FILES['image']['name']);
                $target_file = $upload_dir . $image_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Basic checks (you'll want more robust validation)
                $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $upload_ok = 1;

                // Check if file extension is allowed
                if (!in_array($imageFileType, $valid_extensions)) {
                    $upload_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                    $upload_ok = 0;
                }

                // You might add checks for file size here as well
                // if ($_FILES["image"]["size"] > 5000000) { // Example: 5MB limit
                //     $upload_error = "Sorry, your file is too large.";
                //     $upload_ok = 0;
                // }


                // If upload_ok is 1, try to upload file
                if ($upload_ok == 1) {
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        // File uploaded successfully
                        // Store the path relative to your site root for the database
                        $image_url = 'images/products/' . $image_name;
                    } else {
                        // move_uploaded_file failed
                        $upload_error = "Sorry, there was an error moving the uploaded file. Check server logs for details.";
                        // You could add more specific error checking here if needed
                        // error_log("File upload failed for " . $_FILES['image']['name'] . " to " . $target_file);
                    }
                }
            }
        } else {
            // Handle specific upload errors from $_FILES['image']['error']
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
    // If no file was uploaded (UPLOAD_ERR_NO_FILE), $image_url remains null, which is correct.

    // ... rest of the form submission handling (INSERT query) ...


    // --- Insert Product into Database ---
    // Use prepared statements to prevent SQL injection
    $sql = "INSERT INTO products (name, description, price, image_url, category_id, stock_quantity) VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Bind parameters (s = string, d = double/decimal, i = integer)
    // Note: mysqli::bind_param requires variables, not literal values for NULL
    // We pass the $category_id variable, which will be NULL if no category was selected.
    $stmt->bind_param(
        "ssdsis", // data types for name, description, price, image_url, category_id, stock_quantity
        $name,
        $description,
        $price,
        $image_url, // Pass the image_url variable (can be NULL)
        $category_id, // Pass the category_id variable (can be NULL)
        $stock_quantity
    );


    if ($stmt->execute()) {
        // Product added successfully, redirect to products list
        header('Location: index.php?success=added');
        exit();
    } else {
        // Error adding product
        // Check for specific errors, e.g., foreign key constraint if category_id is somehow invalid despite the check
        $error_message = "Error adding product: " . $stmt->error;
    }

    $stmt->close();
}
// ... rest of your code for fetching categories and displaying the HTML form ...

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
    <title>Add New Product - Kemsoft Shop Admin</title>
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
            <h1>Add New Product</h1>

            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <?php if (isset($upload_error)): ?>
                <div class="error-message"><?php echo $upload_error; ?></div>
            <?php endif; ?>


            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Price:</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                </div>

                <div class="form-group">
                    <label for="image">Product Image:</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <?php if (isset($upload_error)): ?>
                        <div class="upload-error"><?php echo $upload_error; ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="category_id">Category:</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select a Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="stock_quantity">Stock Quantity:</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="button">Add Product</button>
                    <a href="index.php" class="button button-secondary">Cancel</a>
                </div>
            </form>

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