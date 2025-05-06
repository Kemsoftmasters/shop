<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$category_id = null;
$category = null;
$error_message = '';
$success_message = '';

// --- Get Category ID from URL and Fetch Category Data ---
// Check if an ID is provided in the URL GET request
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = $_GET['id'];

    // Fetch the category data
    $sql_fetch = "SELECT category_id, name FROM categories WHERE category_id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $category_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();

    if ($result_fetch->num_rows === 1) {
        $category = $result_fetch->fetch_assoc();
    } else {
        // Category not found
        $_SESSION['error_message'] = "Category not found.";
        header('Location: index.php'); // Redirect back to categories list
        exit();
    }
    $stmt_fetch->close();
} else if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Only redirect if it's not a POST request (meaning no ID was provided on initial load)
    // No category ID provided in the URL
    $_SESSION['error_message'] = "No category ID provided.";
    header('Location: index.php'); // Redirect back to categories list
    exit();
}


// --- Handle Form Submission (Update Category) ---
// Check if the form has been submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the category ID from the hidden input field
    $category_id = $_POST['category_id'];
    // Get the updated category name from the form
    $category_name = trim($_POST['name']); // Use trim to remove leading/trailing whitespace

    // Basic validation
    if (empty($category_name)) {
        $error_message = "Category name cannot be empty.";
        // Re-populate $category variable for the form to show the submitted empty name
        $category = ['category_id' => $category_id, 'name' => $category_name];
    } else {
        // Use prepared statements to prevent SQL injection
        // Update the category where the category_id matches
        $sql_update = "UPDATE categories SET name = ? WHERE category_id = ?";

        $stmt_update = $conn->prepare($sql_update);

        // Bind parameters (s = string, i = integer)
        $stmt_update->bind_param("si", $category_name, $category_id);

        if ($stmt_update->execute()) {
            // Check if any rows were affected (category name was actually changed)
            if ($stmt_update->affected_rows > 0) {
                $_SESSION['success_message'] = "Category '" . htmlspecialchars($category_name) . "' updated successfully!";
            } else {
                // No rows affected, likely no change in the name
                $_SESSION['success_message'] = "Category name was not changed.";
            }

            // Redirect back to categories list after successful update (or no change)
            header('Location: index.php');
            exit();
        } else {
            // Error updating category
            // Check for duplicate entry error (MySQL error code 1062)
            if ($conn->errno == 1062) {
                $error_message = "Category name '" . htmlspecialchars($category_name) . "' already exists.";
            } else {
                $error_message = "Error updating category: " . $stmt_update->error;
            }
            // Re-populate $category variable for the form to show the submitted name in case of error
            $category = ['category_id' => $category_id, 'name' => $category_name];
        }

        $stmt_update->close();
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
    <title>Edit Category - Kemsoft Shop Admin</title>
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

        .form-group input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .button-group {
            margin-top: 20px;
        }

        /* Use existing button styles from admin_style.css */
        /* .button, .button-secondary {} */

        .error-message {
            color: #e74c3c;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        include __DIR__ . '/../includes/admin_header.php';
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Edit Category</h1>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($category): // Only display the form if category data was fetched 
            ?>
                <form action="" method="POST">
                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['category_id']); ?>">

                    <div class="form-group">
                        <label for="name">Category Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="button">Update Category</button>
                        <a href="index.php" class="button button-secondary">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <p>Category data could not be loaded.</p>
            <?php endif; ?>


        </div> <!-- End of content-area -->

    </div> <!-- End of admin-container -->
    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '../../includes/admin_footer.php';
    ?>


</body>

</html>