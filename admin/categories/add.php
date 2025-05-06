<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$error_message = '';

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $category_name = trim($_POST['name']); // Use trim to remove leading/trailing whitespace

    // Basic validation
    if (empty($category_name)) {
        $error_message = "Category name cannot be empty.";
    } else {
        // Use prepared statements to prevent SQL injection
        $sql = "INSERT INTO categories (name) VALUES (?)";

        $stmt = $conn->prepare($sql);

        // Bind parameters (s = string)
        $stmt->bind_param("s", $category_name);

        if ($stmt->execute()) {
            // Category added successfully, redirect to categories list
            $_SESSION['success_message'] = "Category '" . htmlspecialchars($category_name) . "' added successfully!";
            header('Location: index.php');
            exit();
        } else {
            // Error adding category
            // Check for duplicate entry error (MySQL error code 1062)
            if ($conn->errno == 1062) {
                $error_message = "Category name '" . htmlspecialchars($category_name) . "' already exists.";
            } else {
                $error_message = "Error adding category: " . $stmt->error;
            }
        }

        $stmt->close();
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
    <title>Add New Category - Kemsoft Shop Admin</title>
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
            <h1>Add New Category</h1>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="name">Category Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="button">Add Category</button>
                    <a href="index.php" class="button button-secondary">Cancel</a>
                </div>
            </form>

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