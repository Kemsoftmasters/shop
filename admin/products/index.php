<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

// --- Filtering ---
// Get filter parameters from GET request
$filter_id = trim($_GET['id'] ?? '');
$filter_name = trim($_GET['name'] ?? '');
$filter_category = $_GET['category'] ?? ''; // Using 'category' for filter parameter name for clarity
$filter_stock_min = trim($_GET['stock_min'] ?? '');
$filter_stock_max = trim($_GET['stock_max'] ?? '');


// Build the WHERE clause based on filter parameters
$where_clauses = [];
$bind_params = [];
$bind_types = '';

// Use a flag for whether WHERE is needed
$needs_where = false;

if (!empty($filter_id) && is_numeric($filter_id)) {
    $where_clauses[] = "p.product_id = ?";
    $bind_params[] = (int)$filter_id;
    $bind_types .= 'i';
    $needs_where = true;
}

if (!empty($filter_name)) {
    $where_clauses[] = "p.name LIKE ?";
    $bind_params[] = '%' . $filter_name . '%';
    $bind_types .= 's';
    $needs_where = true;
}

// Handle category filter: '' for all, 'uncategorized' for NULL category_id, or a specific category_id
if ($filter_category !== '') { // If not 'All Categories'
    if ($filter_category === 'uncategorized') {
        // Assuming NULL category_id means uncategorized
        $where_clauses[] = "p.category_id IS NULL";
        // No bind parameters for IS NULL
        $needs_where = true;
    } elseif (is_numeric($filter_category)) {
        // Filter by a specific category ID
        $where_clauses[] = "p.category_id = ?";
        $bind_params[] = (int)$filter_category;
        $bind_types .= 'i';
        $needs_where = true;
    }
    // If filter_category is not '', 'uncategorized', or numeric, it's ignored.
}


if (!empty($filter_stock_min) || (string)$filter_stock_min === '0') { // Check for empty string OR '0'
    if (is_numeric($filter_stock_min)) {
        $where_clauses[] = "p.stock_quantity >= ?";
        $bind_params[] = (int)$filter_stock_min;
        $bind_types .= 'i';
        $needs_where = true;
    }
}

if (!empty($filter_stock_max) || (string)$filter_stock_max === '0') { // Check for empty string OR '0'
    if (is_numeric($filter_stock_max)) {
        $where_clauses[] = "p.stock_quantity <= ?";
        $bind_params[] = (int)$filter_stock_max;
        $bind_types .= 'i';
        $needs_where = true;
    }
}


// Combine WHERE clauses
$where_sql = '';
if ($needs_where) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}


// --- Sorting ---
// Allowed sort columns - Note that category_name comes from the joined table (c.name)
$allowed_sort_columns = ['product_id', 'name', 'price', 'stock_quantity', 'category_name'];
$default_sort_column = 'product_id'; // Default sort by ID or created_at
$default_sort_direction = 'DESC';

$sort_column = $_GET['sort'] ?? $default_sort_column;
$sort_direction = $_GET['dir'] ?? $default_sort_direction;

// Validate the sort column and direction to prevent SQL injection
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = $default_sort_column; // Fallback to default if invalid
}
if (!in_array(strtoupper($sort_direction), ['ASC', 'DESC'])) {
    $sort_direction = $default_sort_direction; // Fallback to default if invalid
}

// Determine the column name in the database based on the sort key
$db_sort_column = ($sort_column === 'category_name') ? 'c.name' : 'p.' . $sort_column;

$order_by_sql = " ORDER BY " . $db_sort_column . " " . $sort_direction;


// --- Pagination ---
$limit = 10; // Number of products per page - Adjust as needed
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page); // Ensure page is at least 1 and is an integer

$offset = ($page - 1) * $limit;


// --- Count total products (with filters applied) ---
// Need a separate query to count total rows *before* applying LIMIT for pagination calculation
// Use the same JOIN and WHERE clauses as the main fetch query
$sql_count = "SELECT COUNT(*) AS total_products
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.category_id"
    . $where_sql; // Apply filters to count

$stmt_count = $conn->prepare($sql_count);

// // Remove or comment out the var_dump and exit here once the count query is prepared correctly
// echo "<pre>";
// var_dump($sql_count);
// echo "</pre>";
// exit;

// Bind parameters for the count query (same params as main query's WHERE)
// Need to pass the bind types and params for the WHERE clause only
$count_bind_params = $bind_params;
$count_bind_types = $bind_types;

// ... rest of the count query execution code ...


if ($stmt_count) { // Check if prepare was successful
    if (!empty($count_bind_params)) {
        $stmt_count->bind_param($count_bind_types, ...$count_bind_params);
    }

    $execute_count_success = $stmt_count->execute();
    $total_products = 0;
    if ($execute_count_success) {
        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();
        $total_products = $row_count['total_products'];
        $result_count->free();
    } else {
        // Handle query error
        // $error_message = "Database error counting products: " . $stmt_count->error;
    }
    $stmt_count->close();
} else {
    // Handle prepare statement error
    // $error_message = "Database error preparing count query: " . $conn->error;
    $total_products = 0; // Ensure total is 0 on error
}


// Calculate total pages
$total_pages = ($total_products > 0) ? ceil($total_products / $limit) : 1;
// Ensure current page doesn't exceed total pages (unless no products found)
if ($page > $total_pages) {
    $page = $total_pages;
    // Recalculate offset for adjusted page, ensuring it's not negative
    $offset = max(0, ($page - 1) * $limit);
} elseif ($total_products == 0) {
    $page = 1;
    $offset = 0;
}


// --- Fetch Product Data (with filters, sorting, and pagination) ---
// Also fetch description and image_url based on screenshot columns
$sql = "SELECT
            p.product_id,
            p.name,
            p.description,
            p.price,
            p.stock_quantity,
            p.image_url,
            c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id"
    . $where_sql // Add the dynamically built WHERE clause
    . $order_by_sql // Add the dynamically built ORDER BY clause
    . " LIMIT ? OFFSET ?"; // Add LIMIT and OFFSET for pagination

// echo "<pre>"; // Use preformatted text for better readability
// var_dump($sql); // Dump the variable content and type
// echo "</pre>";
// exit; // Stop script execution
// $stmt = $conn->prepare($sql); // Comment out the original line

// Dynamically bind parameters for the main fetch query
// Bind types: filter types + 'ii' for LIMIT and OFFSET
$fetch_bind_types = $bind_types . 'ii';
$fetch_bind_params = array_merge($bind_params, [$limit, $offset]);

// echo "<pre>";
// var_dump($sql);
// var_dump($fetch_bind_types); // See the data types being bound
// var_dump($fetch_bind_params); // See the actual values being bound
// echo "</pre>";
// exit; // This stops the script so you only see the debug info
$stmt = $conn->prepare($sql);

if ($stmt) { // Check if prepare was successful
    if (!empty($fetch_bind_params)) {
        $stmt->bind_param($fetch_bind_types, ...$fetch_bind_params);
    }

    $execute_success = $stmt->execute();

    $products = []; // Initialize as empty array
    if ($execute_success) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            $result->free(); // Free result set
        }
    } else {
        // Handle query execution error
        // Only set error message if no count error already occurred
        // if (empty($error_message)) {
        //      $error_message = "Database query failed: " . $stmt->error;
        // }
        $products = []; // Ensure $products is empty on error
    }
    $stmt->close(); // Close statement
} else {
    // Handle prepare statement error
    // $error_message = "Database error preparing product fetch query: " . $conn->error;
    $products = []; // Ensure $products is empty on error
}


// --- Fetch Categories for Filter Dropdown ---
// Fetch all categories to populate the filter dropdown
$categories_for_filter = [];
// Add an option for "All Categories" with an empty value
$categories_for_filter[] = ['category_id' => '', 'name' => 'All Categories'];
// Add an option for "Uncategorized" if your schema uses NULL for category_id
// If category_id 0 means uncategorized, use '0' as the value. Adjust based on your DB.
$categories_for_filter[] = ['category_id' => 'uncategorized', 'name' => 'Uncategorized']; // Use a distinct string value

$sql_fetch_categories = "SELECT category_id, name FROM categories ORDER BY name ASC";
$result_categories = $conn->query($sql_fetch_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        // Avoid adding 'Uncategorized' from DB if we added it manually
        if ((string)$row['category_id'] !== '0') { // Assuming 0 might be uncategorized
            $categories_for_filter[] = $row;
        }
    }
    $result_categories->free();
} else {
    // Handle error fetching categories for filter
    // $error_message = "Error fetching categories for filter: " . $conn->error;
}


// Close the database connection if necessary (depends on db_connect.php)
// closeDB($conn);


// --- Check for status messages from session ---
$status_message = '';
$message_type = '';

if (isset($_SESSION['success_message'])) {
    $status_message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_SESSION['error_message'])) {
    // Don't overwrite internal error messages (if any were set during query execution)
    // if (empty($error_message)) {
    $status_message = $_SESSION['error_message'];
    $message_type = 'error';
    // }
    unset($_SESSION['error_message']); // Clear the message after displaying
}

// If an internal error occurred during data fetch, prioritize it for display
// if (!empty($error_message) && empty($status_message)) {
//     $status_message = $error_message;
//     $message_type = 'error';
// }


// --- HTML Structure ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Specific styles for the products list page */

        /* Styling for filter/search form (reused from Users/Orders) */
        .filter-form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
            display: flex;
            /* Use flexbox for layout */
            flex-wrap: wrap;
            /* Allow items to wrap */
            gap: 15px;
            /* Space between items */
            align-items: flex-end;
            /* Align items to the bottom */
        }

        .filter-form .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-form .form-group label {
            margin-bottom: 0;
            min-width: 80px;
            /* Adjust label width as needed */
            text-align: right;
            font-weight: bold;
            color: #555;
        }

        .filter-form input[type="text"],
        .filter-form input[type="number"],
        .filter-form select {
            /* Added number and select types */
            width: auto;
            max-width: 180px;
            /* Limit width */
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            box-sizing: border-box;
            font-size: 1em;
            flex-grow: 1;
            /* Allow input to grow */
        }

        .filter-form input[type="number"] {
            max-width: 100px;
            /* Smaller width for stock numbers */
        }

        .filter-form .button-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 5px;
        }

        .filter-form button,
        .filter-form a.button-secondary {
            padding: 8px 15px;
            margin-right: 0;
            margin-top: 0;
        }

        /* Small screen adjustments for filter form */
        @media (max-width: 768px) {

            /* Adjust breakpoint as needed */
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-form .form-group {
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
            }

            .filter-form label {
                margin-bottom: 5px;
                display: block;
                min-width: auto;
                text-align: left;
            }

            .filter-form input[type="text"],
            .filter-form input[type="number"],
            .filter-form select {
                width: 100%;
                max-width: none;
                margin-right: 0;
                margin-bottom: 0;
            }

            .filter-form .button-group {
                flex-direction: column;
                gap: 10px;
                margin-top: 10px;
            }

            .filter-form button,
            .filter-form a.button-secondary {
                width: 100%;
                margin-right: 0;
            }
        }


        /* Styling for sort arrows */
        span.sort-arrow {
            font-size: 0.8em;
            vertical-align: middle;
        }

        /* Pagination styling (reused from Users/Orders) */
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            /* Center pagination links */
            align-items: center;
            gap: 10px;
            /* Space between links */
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
            background-color: #fff;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        }

        .pagination a:hover {
            background-color: #e9ecef;
            color: #0056b3;
        }

        .pagination span.current-page {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            font-weight: bold;
        }

        .pagination span.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Product Image Thumbnail Styling */
        .products-table .product-image-thumbnail {
            max-width: 50px;
            /* Adjust size as needed */
            height: auto;
            display: block;
            /* Remove extra space below image */
            margin: 0 auto;
            /* Center the image in the cell */
        }

        /* Adjust padding for cells containing images */
        .products-table td.image-cell {
            padding: 5px;
            /* Smaller padding around image */
            text-align: center;
            /* Center image */
        }

        /* Basic table styling (can move to admin_style.css) */
        /* These styles were in your original code, included here for completeness */
        .products-table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            /* Adjust spacing below filter form */
            background-color: #fff;
            /* White background for table body */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            /* Add shadow */
            border-radius: 8px;
            /* Rounded corners */
            overflow: hidden;
            /* Hide content outside rounded corners */
        }

        .products-table th,
        .products-table td {
            border: 1px solid #dee2e6;
            /* Lighter borders */
            padding: 10px;
            text-align: left;
            font-size: 0.95em;
        }

        .products-table th {
            background-color: #e9ecef;
            /* Lighter header background */
            font-weight: bold;
            text-transform: uppercase;
            /* Uppercase headers */
            color: #495057;
            /* Darker header text color */
            font-size: 0.85em;
            /* Smaller header text */
        }

        .products-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
            /* Slightly different background for even rows */
        }

        .products-table tbody tr:hover {
            background-color: #e2e6ea;
            /* Highlight row on hover */
        }


        .products-table .action-links a {
            margin-right: 10px;
            text-decoration: none;
        }


        /* Status message styling (from your original code) */
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        // Ensure these files exist and contain your header and sidebar HTML
        include __DIR__ . '/../includes/admin_header.php';
        // Pass current page to sidebar for active state highlighting
        $current_page = 'products'; // Set the current page variable
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Products Management</h1>

            <p><a href="add.php" class="button">Add New Product</a></p>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div class="filter-form">
                <form action="" method="GET">
                    <div class="form-group">
                        <label for="filter_id">Product ID:</label>
                        <input type="text" name="id" id="filter_id" value="<?php echo htmlspecialchars($filter_id); ?>" placeholder="Filter by ID">
                    </div>

                    <div class="form-group">
                        <label for="filter_name">Name:</label>
                        <input type="text" name="name" id="filter_name" value="<?php echo htmlspecialchars($filter_name); ?>" placeholder="Filter by name">
                    </div>

                    <div class="form-group">
                        <label for="filter_category">Category:</label>
                        <select name="category" id="filter_category"> {/* Changed name to 'category' */}
                            <?php foreach ($categories_for_filter as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category_id']); ?>"
                                    <?php if ((string)$filter_category === (string)$category['category_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="filter_stock_min">Stock Min:</label>
                        <input type="number" name="stock_min" id="filter_stock_min" value="<?php echo htmlspecialchars($filter_stock_min); ?>" placeholder="Min Stock">
                    </div>

                    <div class="form-group">
                        <label for="filter_stock_max">Stock Max:</label>
                        <input type="number" name="stock_max" id="filter_stock_max" value="<?php echo htmlspecialchars($filter_stock_max); ?>" placeholder="Max Stock">
                    </div>


                    <div class="button-group">
                        <button type="submit" class="button">Filter</button>
                        <?php if (!empty($filter_id) || !empty($filter_name) || $filter_category !== '' || !empty($filter_stock_min) || (string)$filter_stock_min === '0' || !empty($filter_stock_max) || (string)$filter_stock_max === '0'): ?>
                            <a href="index.php" class="button button-secondary">Reset Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>


            <?php if ($total_products > 0): // Check total products WITH filters 
            ?>
                <table class="products-table">
                    <!-- {/* Added a specific class for this table */} -->
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td class="image-cell">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-thumbnail">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['description'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td> 
                                <td>$<?php echo htmlspecialchars(number_format((float)$product['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                <td class="action-links">
                                    <a href="edit.php?id=<?php echo urlencode($product['product_id']); ?>">Edit</a> |
                                    <a href="delete.php?id=<?php echo urlencode($product['product_id']); ?>" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <!-- {/* Reuse pagination class */} -->
                        <?php
                        // Function to build pagination link URL, preserving filters and sort
                        function buildProductPaginationLink($page_num, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction)
                        {
                            $url = "?page=" . urlencode($page_num);
                            if (!empty($filter_id)) $url .= "&id=" . urlencode($filter_id);
                            if (!empty($filter_name)) $url .= "&name=" . urlencode($filter_name);
                            // Use strict comparison for category filter
                            if ($filter_category !== '') $url .= "&category=" . urlencode($filter_category);
                            if (!empty($filter_stock_min) || (string)$filter_stock_min === '0') $url .= "&stock_min=" . urlencode($filter_stock_min);
                            if (!empty($filter_stock_max) || (string)$filter_stock_max === '0') $url .= "&stock_max=" . urlencode($filter_stock_max);
                            if (!empty($sort_column)) $url .= "&sort=" . urlencode($sort_column);
                            if (!empty($sort_direction)) $url .= "&dir=" . urlencode($sort_direction);
                            return $url;
                        }
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildProductPaginationLink($page - 1, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction); ?>">Previous</a>
                        <?php else: ?>
                            <span class="disabled">Previous</span>
                        <?php endif; ?>

                        <?php
                        // Display page numbers (e.g., show a few pages around the current page)
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<a href="' . buildProductPaginationLink(1, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction) . '">1</a>';
                            if ($start_page > 2) {
                                echo '<span>...</span>'; // Ellipsis
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++):
                            if ($i == $page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo buildProductPaginationLink($i, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span>...</span>'; // Ellipsis
                            }
                            echo '<a href="' . buildProductPaginationLink($total_pages, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction) . '">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo buildProductPaginationLink($page + 1, $filter_id, $filter_name, $filter_category, $filter_stock_min, $filter_stock_max, $sort_column, $sort_direction); ?>">Next</a>
                        <?php else: ?>
                            <span class="disabled">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


            <?php else: // No products found based on filters 
            ?>
                <p>No products found matching your criteria.</p>
            <?php endif; ?>

        </div> <!-- End of content area -->


    </div> <!-- End of admin-container -->
    <script src="../../js/admin_script.js"></script>
    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>
    <script>
        // Add JavaScript for delete confirmation if needed (optional, as onclick is used)
        // You could use this approach if you want a more custom confirmation box
        // const deleteLinks = document.querySelectorAll('.delete-product-link');
        // deleteLinks.forEach(link => {
        //     link.addEventListener('click', function(event) {
        //         if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        //             event.preventDefault();
        //         }
        //     });
        // });
    </script>

</body>

</html>