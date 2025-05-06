<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

// --- Fetch Categories Data ---
$sql = "SELECT category_id, name FROM categories ORDER BY name ASC"; // Order categories alphabetically
$result = $conn->query($sql);

// --- Prepare data for display ---
$categories = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Close the database connection if necessary
// closeDB($conn);

// --- Check for status messages from session ---
$status_message = '';
$message_type = '';

if (isset($_SESSION['success_message'])) {
    $status_message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_SESSION['error_message'])) {
    $status_message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']); // Clear the message after displaying
}

// --- HTML Structure ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Basic table styling (can move to admin_style.css) */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .action-links a {
            margin-right: 10px;
            text-decoration: none;
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
            <h1>Categories Management</h1>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <p><a href="add.php" class="button">Add New Category</a></p> <?php if (count($categories) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td class="action-links">
                                    <a href="edit.php?id=<?php echo urlencode($category['category_id']); ?>">Edit</a> |
                                    <a href="delete.php?id=<?php echo urlencode($category['category_id']); ?>" class="delete-category-link">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No categories found.</p>
            <?php endif; ?>

        </div> <?php
                // --- Include Admin Footer (Optional) ---
                // include __DIR__ . '/../includes/admin_footer.php';
                ?>

    </div> <!-- /.admin-container -->
    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '../../includes/admin_footer.php';
    ?>

</body>

</html>