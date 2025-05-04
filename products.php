<?php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct and establishes $conn (mysqli connection)

// --- Configuration ---
$products_per_page = 9; // Number of products displayed per page

// --- Get Parameters ---
// Fetch and sanitize parameters from the URL
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null; // Category ID filter
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default'; // Sorting preference (e.g., 'price_asc')
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number for pagination
if ($current_page < 1) $current_page = 1; // Ensure page number is not less than 1
$search_term = isset($_GET['search']) ? trim($_GET['search']) : null; // Search query term

// --- Fetch Categories for Sidebar ---
// Retrieve all categories to display in the navigation sidebar
$sql_categories = "SELECT category_id, name FROM categories ORDER BY name ASC";
$result_categories = $conn->query($sql_categories);
$categories = [];
if ($result_categories && $result_categories->num_rows > 0) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// --- Build Product Query Dynamically ---

// Define base SQL query parts
$sql_select = "SELECT product_id, name, image_url, price "; // Columns to fetch
$sql_from = " FROM products "; // Table to query
$sql_where = ""; // Initialize WHERE clause
$sql_order_by = ""; // Initialize ORDER BY clause
$sql_limit_offset = ""; // Initialize LIMIT/OFFSET clause

// Prepare arrays for WHERE conditions, parameters, and their types for prepared statements
$where_clauses = []; // Stores individual WHERE conditions (e.g., "category_id = ?")
$params = []; // Stores parameter values to bind (e.g., $category_filter, $like_term)
$types = "";  // Stores parameter types for bind_param (e.g., "iss")

// 1. Apply Category Filter Condition
if ($category_filter) {
    $where_clauses[] = "category_id = ?"; // Add category condition
    $params[] = $category_filter;         // Add category ID to parameters
    $types .= "i";                         // Specify integer type
}

// 2. Apply Search Term Filter Condition
if ($search_term !== null && $search_term !== '') {
    // Add condition to search in 'name' and 'description' columns using LIKE
    $where_clauses[] = "(name LIKE ? OR description LIKE ?)";
    $like_term = "%" . $search_term . "%"; // Prepare search term with wildcards for partial matching
    $params[] = $like_term;                // Add search term parameter (for name)
    $params[] = $like_term;                // Add search term parameter (for description)
    $types .= "ss";                        // Specify two string types
}

// Combine all WHERE conditions using 'AND' if any exist
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Count Total Products (with filters/search applied) for Pagination ---
// This query counts matching products to determine the total number of pages needed
$sql_count = "SELECT COUNT(product_id) as total" . $sql_from . $sql_where;
$total_products = 0; // Initialize total product count

// Prepare and execute the count query
if ($stmt_count = $conn->prepare($sql_count)) {
    // Bind parameters (category filter, search term) if they exist
    if (!empty($types)) {
         // Use spread operator (...) for PHP 5.6+; use call_user_func_array for older versions
         // call_user_func_array([$stmt_count, 'bind_param'], array_merge([$types], $params));
        $stmt_count->bind_param($types, ...$params);
    }
    // Execute the prepared statement
    if ($stmt_count->execute()) {
        $result_count = $stmt_count->get_result(); // Get the result set
        if ($result_count) {
            $total_products = $result_count->fetch_assoc()['total']; // Fetch the total count
        }
    } else {
        // Log error if execution fails
        error_log("Error executing count statement: " . $stmt_count->error);
    }
    $stmt_count->close(); // Close the statement
} else {
    // Log error if preparation fails
    error_log("Error preparing count statement: " . $conn->error);
}

// --- Calculate Pagination Details ---
$total_pages = ($total_products > 0) ? ceil($total_products / $products_per_page) : 1; // Calculate total pages needed
if ($current_page > $total_pages) {
    $current_page = $total_pages; // Correct current page if it exceeds total pages
}
$offset = ($current_page - 1) * $products_per_page; // Calculate the offset for the SQL LIMIT clause

// --- Determine Sorting Order ---
// Define the ORDER BY clause based on the 'sort' parameter
$sql_order_by = " ORDER BY ";
switch ($sort_order) {
    case 'price_asc':
        $sql_order_by .= "price ASC"; // Sort by price low to high
        break;
    case 'price_desc':
        $sql_order_by .= "price DESC"; // Sort by price high to low
        break;
    case 'name_asc':
        $sql_order_by .= "name ASC"; // Sort by name A to Z
        break;
    case 'name_desc':
        $sql_order_by .= "name DESC"; // Sort by name Z to A
        break;
    default:
        $sql_order_by .= "created_at DESC"; // Default sort: newest products first (based on table structure)
        break;
}

// --- Determine Limit and Offset for Query ---
// Add LIMIT and OFFSET parameters to the $params array and $types string
$sql_limit_offset = " LIMIT ? OFFSET ?";
$params[] = $products_per_page; // Add LIMIT value
$types .= "i";                  // Specify integer type for LIMIT
$params[] = $offset;            // Add OFFSET value
$types .= "i";                  // Specify integer type for OFFSET

// --- Assemble the Final Product Query ---
// Combine all SQL parts: SELECT, FROM, WHERE, ORDER BY, LIMIT/OFFSET
$sql_products = $sql_select . $sql_from . $sql_where . $sql_order_by . $sql_limit_offset;

// --- Fetch Products for the Current Page ---
$products = []; // Initialize array to hold product data

// Prepare and execute the main product query
if ($stmt_products = $conn->prepare($sql_products)) {
    // Bind all accumulated parameters (category, search, limit, offset) if any exist
    if (!empty($types)) {
         // Use spread operator (...) for PHP 5.6+; use call_user_func_array for older versions
         // call_user_func_array([$stmt_products, 'bind_param'], array_merge([$types], $params));
        $stmt_products->bind_param($types, ...$params);
    }
    // Execute the prepared statement
    if ($stmt_products->execute()) {
        $result_products = $stmt_products->get_result(); // Get the result set
        // Fetch products if results exist
        if ($result_products && $result_products->num_rows > 0) {
            while ($row = $result_products->fetch_assoc()) {
                $products[] = $row; // Add each product row to the $products array
            }
        }
    } else {
        // Log error if execution fails
        error_log("Error executing product statement: " . $stmt_products->error);
    }
    $stmt_products->close(); // Close the statement
} else {
    // Log error if preparation fails
    error_log("Error preparing product statement: " . $conn->error);
}

// --- Helper Function to Build URLs ---
// Creates URLs for links (pagination, categories, sorting) preserving relevant current filters/parameters
function build_url($base_params = []) {
    $current_params = $_GET; // Get all current URL parameters
    // Merge provided parameters ($base_params) with current ones, letting $base_params overwrite
    $merged_params = array_merge($current_params, $base_params);

    // Clean up parameters for cleaner URLs:
    // Remove 'page' if it's 1 or less
    if (isset($merged_params['page']) && $merged_params['page'] <= 1) {
        unset($merged_params['page']);
    }
    // Remove 'sort' if it's the default value
    if (isset($merged_params['sort']) && $merged_params['sort'] == 'default') {
        unset($merged_params['sort']);
    }
     // Remove 'category' if it's empty or null (when linking to 'All Products')
    if (isset($merged_params['category']) && empty($merged_params['category'])) {
        unset($merged_params['category']);
    }
     // Remove 'search' if it's empty or null
    if (isset($merged_params['search']) && ($merged_params['search'] === null || $merged_params['search'] === '')) {
        unset($merged_params['search']);
    }

    // Build the query string from the cleaned parameters
    $query_string = http_build_query($merged_params);
    // Return the base file name with the query string (if any)
    return 'products.php' . ($query_string ? '?' . $query_string : '');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        // Generate a dynamic page title based on the current view
        if ($search_term) {
            echo 'Search Results for "' . htmlspecialchars($search_term) . '"';
        } elseif ($category_filter) {
            $cat_name = "Products"; // Default category name
            // Find the name of the currently selected category
            foreach ($categories as $cat) { if ($cat['category_id'] == $category_filter) { $cat_name = htmlspecialchars($cat['name']); break; } }
            echo $cat_name;
        } else {
            echo "All Products"; // Default title
        }
        ?> - Kemsoft Masters
    </title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Enhancements */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa; /* Light grey background */
            color: #333;
            line-height: 1.6;
        }

        .container { /* Centered wrapper for main content */
             max-width: 1300px;
             margin: 0 auto;
             padding: 20px;
        }

        /* Visually hidden class for accessibility */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        /* --- Search Bar Styles --- */
        .search-container {
            margin-bottom: 25px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .product-search-form {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 10px;
            align-items: center; /* Align items vertically */
        }

        .product-search-form label {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .product-search-form input[type="search"] {
            flex-grow: 1; /* Take available space */
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 1em;
            font-family: inherit;
            min-width: 200px; /* Minimum width */
        }

        .product-search-form button[type="submit"] {
            padding: 10px 20px;
            background-color: #007bff; /* Primary button blue */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.2s ease;
            font-family: inherit;
            line-height: 1.5; /* Match input height */
        }

        .product-search-form button[type="submit"]:hover {
            background-color: #0056b3; /* Darker blue on hover */
        }

        .product-search-form a.clear-search-button { /* Style for the 'Clear' link */
             padding: 10px 15px;
             background-color: #6c757d; /* Secondary grey color */
             color: white;
             border-radius: 5px;
             text-decoration: none;
             font-size: 1em;
             font-weight: 500;
             display:inline-block;
             line-height: 1.5; /* Match button height */
             transition: background-color 0.2s ease;
        }
        .product-search-form a.clear-search-button:hover {
            background-color: #5a6268; /* Darker grey on hover */
        }

        /* --- Main Layout: Sidebar + Product Grid --- */
        .product-page-wrapper {
            display: flex;
            flex-wrap: wrap; /* Stack on smaller screens */
            gap: 30px;
            margin-top: 20px;
        }

        /* --- Sidebar Styles --- */
        .sidebar {
            width: 250px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            align-self: flex-start; /* Keep at top */
            flex-shrink: 0; /* Prevent shrinking */
        }

        .sidebar h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #343a40; /* Dark grey heading */
            font-size: 1.4em;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin-bottom: 5px;
        }

        .sidebar ul li a {
            text-decoration: none;
            color: #007bff; /* Link blue */
            display: block;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.2s ease, color 0.2s ease;
            font-weight: 500;
            font-size: 0.95em;
        }

        .sidebar ul li a:hover {
            background-color: #e9ecef; /* Light grey background on hover */
            color: #0056b3; /* Darker blue text on hover */
        }
        .sidebar ul li a.active { /* Style for active category link */
            background-color: #007bff; /* Blue background */
            color: #fff; /* White text */
            font-weight: 600;
        }

        /* --- Product Grid Area (Right Side) --- */
        .product-grid-area {
            flex-grow: 1; /* Take remaining horizontal space */
            min-width: 0; /* Prevent flexbox overflow issues */
        }

        /* Header within the grid area (Title + Sorting) */
        .grid-header {
            display: flex;
            justify-content: space-between; /* Title left, Sort right */
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap; /* Wrap on smaller screens */
            gap: 15px; /* Space between title and sort */
        }

        .grid-header h1 {
            margin: 0;
            font-size: 1.8em;
            color: #343a40;
            font-weight: 600;
        }
         .grid-header h1 small { /* Style for the '(x found)' text */
             font-size: 0.7em;
             font-weight: 400;
             color: #6c757d;
         }


        .sort-options label {
            margin-right: 8px;
            font-weight: 500;
            color: #495057;
        }
        .sort-options select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 0.9em;
            cursor: pointer;
            background-color: #fff;
            font-family: inherit;
        }

        /* Product Grid Layout */
        .product-grid {
            display: grid;
            /* Responsive grid: creates columns between 260px and 1fr wide */
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px; /* Space between grid items */
        }

        /* Individual Product Card Styling */
        .product-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden; /* Clip image zoom/content */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            display: flex;
            flex-direction: column; /* Stack image, details */
        }

        .product-card:hover {
            transform: translateY(-5px); /* Lift card on hover */
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12); /* Increase shadow */
        }

        .product-image-link { /* Make image clickable */
             display: block;
             text-decoration: none;
        }

        .product-image-wrapper { /* Container for consistent image height */
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-bottom: 1px solid #eee;
        }

        .product-card img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Scale image nicely within wrapper */
            display: block;
            transition: transform 0.3s ease; /* Smooth zoom effect */
        }

        .product-card:hover img {
             transform: scale(1.05); /* Zoom image slightly on card hover */
        }

        .product-details {
            padding: 18px;
            text-align: left; /* Align text left */
            display: flex;
            flex-direction: column; /* Stack info and actions */
            flex-grow: 1; /* Allow details section to fill card height */
        }

        .product-info { /* Group name and price */
            margin-bottom: 15px;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #343a40;
            font-size: 1.05em;
            line-height: 1.3;
             /* Optional: Limit product name to 2 lines with ellipsis */
             /* display: -webkit-box;
             -webkit-line-clamp: 2;
             -webkit-box-orient: vertical;
             overflow: hidden;
             text-overflow: ellipsis;
             height: 2.6em; Height based on line-height * lines */
        }
        .product-name a { /* Ensure link inherits color and has no underline */
             text-decoration: none;
             color: inherit;
        }

        .product-price {
            color: #28a745; /* Success green for price */
            font-weight: 700;
            font-size: 1.2em;
            margin-bottom: 0;
        }

         .product-actions { /* Container for buttons */
            margin-top: auto; /* Push actions to the bottom */
            display: flex; /* Arrange buttons horizontally */
            gap: 10px; /* Space between buttons */
        }

        .view-details-button { /* Button styling */
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease;
            text-align: center;
            flex-grow: 1; /* Allow button to grow if needed */
        }

        .view-details-button:hover {
            background-color: #0056b3;
        }

        /* Styling for "No products found" message */
        .no-products {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d; /* Muted text color */
            background-color: #fff;
            border-radius: 8px;
            grid-column: 1 / -1; /* Span full width of the grid */
            font-size: 1.1em;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* --- Pagination Styles --- */
        .pagination {
            margin-top: 40px; /* Space above pagination */
            display: flex;
            justify-content: center; /* Center pagination links */
            list-style: none;
            padding: 0;
        }

        .pagination .page-item {
            margin: 0 3px; /* Small gap between page links */
        }

        .pagination .page-link {
            display: block;
            padding: 8px 14px;
            color: #007bff; /* Link blue */
            background-color: #fff; /* White background */
            border: 1px solid #dee2e6; /* Light grey border */
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .pagination .page-link:hover {
            background-color: #e9ecef; /* Light grey background on hover */
            color: #0056b3; /* Darker blue text on hover */
            border-color: #ced4da;
        }

        .pagination .page-item.active .page-link { /* Style for active page */
            background-color: #007bff; /* Blue background */
            color: #fff; /* White text */
            border-color: #007bff;
            z-index: 1;
        }

        .pagination .page-item.disabled .page-link { /* Style for disabled Prev/Next */
            color: #6c757d; /* Muted text */
            pointer-events: none; /* Disable clicks */
            background-color: #fff;
            border-color: #dee2e6;
        }


        /* --- Responsive Adjustments --- */
        @media (max-width: 992px) { /* Medium screens / Tablets */
            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }
             .product-image-wrapper {
                 height: 180px; /* Adjust image height */
             }
        }

        @media (max-width: 768px) { /* Small screens / Tablets */
            .product-page-wrapper {
                flex-direction: column; /* Stack sidebar above grid */
                gap: 20px;
            }
            .sidebar {
                width: 100%; /* Full width sidebar */
                margin-bottom: 0;
                 padding: 15px;
            }
             .sidebar ul { /* Make categories horizontally scrollable */
                 display: flex;
                 overflow-x: auto; /* Enable horizontal scroll */
                 padding-bottom: 10px; /* Space for potential scrollbar */
                 white-space: nowrap; /* Prevent category links from wrapping */
                 -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
                 border-bottom: 1px solid #eee; /* Separator */
                 margin-bottom: 10px; /* Space below scrollable list */
            }
             .sidebar ul li {
                 margin-bottom: 0; /* Remove bottom margin */
                 margin-right: 10px; /* Space between horizontal items */
                 flex-shrink: 0; /* Prevent items from shrinking */
            }
             .sidebar ul li:last-child {
                 margin-right: 0;
             }
             .sidebar ul li a {
                 padding: 8px 12px;
             }

            .grid-header {
                flex-direction: column; /* Stack title and sort */
                align-items: flex-start; /* Align left */
            }
             .grid-header h1 {
                 font-size: 1.6em;
             }
             .sort-options {
                 width: 100%; /* Full width sort dropdown */
             }
             .sort-options select {
                 width: 100%; /* Make select full width */
             }

            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            .product-card {
                border-radius: 8px;
            }
            .product-image-wrapper {
                 height: 160px; /* Further adjust image height */
            }
            .product-details {
                padding: 15px;
            }
            .product-name {
                font-size: 0.95em;
            }
            .product-price {
                font-size: 1.1em;
            }
            .view-details-button {
                padding: 9px 12px;
                font-size: 0.85em;
            }
        }

        @media (max-width: 576px) { /* Small screens / Phones */
             .product-grid {
                 /* Can switch to 1 column if needed: grid-template-columns: 1fr; */
                 grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); /* Adjust minmax further */
                 gap: 10px;
            }
             .product-image-wrapper {
                 height: 140px; /* Smallest image height */
             }
              .product-search-form {
                 flex-direction: column; /* Stack search input and button */
                 align-items: stretch; /* Make input/button full width */
             }
        }

    </style>
</head>
<body>
    <header>
        <div class="logo">Kemsoft Masters</div>
        <nav class="main-nav">
            <button class="hamburger-menu">☰</button> <ul class="nav-links">
                 <li><a href="index.php">Home</a></li>
                <li><a href="products.php" class="active">Products</a></li> <li><a href="category.php">Categories</a></li>
                <li><a href="account.php">Account</a></li>
                <li><a href="cart.php">
                        Cart (<?php // Display dynamic cart quantity
                            $total_quantity = 0;
                            if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                foreach ($_SESSION['cart'] as $item) { if (isset($item['quantity'])) { $total_quantity += $item['quantity']; } }
                            } echo $total_quantity;
                        ?>)</a></li>
                <li><a href="wishlist.php">Wishlist</a></li>
            </ul>
        </nav>
    </header>

    <main class="container">

        <div class="search-container">
            <form action="products.php" method="get" class="product-search-form">
                <label for="search-input" class="visually-hidden">Search Products</label>
                <input type="search" id="search-input" name="search" placeholder="Search for products..."
                       value="<?php echo htmlspecialchars($search_term ?? ''); // Pre-fill search box ?>" >
                <button type="submit">Search</button>
                 <?php if ($search_term): // Show clear search button only if a search is active ?>
                    <a href="<?php
                       // Generate URL to clear search, keeping other relevant filters like category/sort
                       $clear_params = $_GET;
                       unset($clear_params['search']); // Remove search term
                       unset($clear_params['page']);   // Reset to page 1
                       echo 'products.php?' . http_build_query($clear_params); // Build URL
                    ?>" class="clear-search-button">Clear</a>
                 <?php endif; ?>
            </form>
        </div>
        <div class="product-page-wrapper">

            <aside class="sidebar">
                 <h2>Categories</h2>
                <ul>
                    <li>
                        <a href="<?= build_url(['category' => null, 'page' => 1]) // Clear category, reset page ?>"
                           class="<?= !$category_filter ? 'active' : '' // Highlight if no category is selected ?>">
                            All Products
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                             <a href="<?= build_url(['category' => $cat['category_id'], 'page' => 1]) // Set category, reset page ?>"
                               class="<?= ($category_filter == $cat['category_id']) ? 'active' : '' // Highlight if this category is selected ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <div class="product-grid-area">

                <div class="grid-header">
                     <h1><?php
                        // Display dynamic heading based on context
                        if ($search_term) {
                            echo 'Search Results for "' . htmlspecialchars($search_term) . '"';
                            // Optionally show category if filtering within search
                            // if ($category_filter) { foreach ($categories as $cat) { if ($cat['category_id'] == $category_filter) { echo ' in ' . htmlspecialchars($cat['name']); break; } } }
                            echo ' <small>(' . $total_products . ' found)</small>'; // Show result count
                        } elseif ($category_filter) {
                            $page_title = "Products"; // Default category title
                            // Find and display the current category name
                            foreach ($categories as $cat) { if ($cat['category_id'] == $category_filter) { $page_title = htmlspecialchars($cat['name']); break; } }
                            echo $page_title;
                        } else {
                            echo "All Products"; // Default heading
                        }
                     ?></h1>
                     <div class="sort-options">
                        <form action="products.php" method="get" id="sortForm">
                             <?php if ($category_filter): ?>
                                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                            <?php endif; ?>
                             <?php if ($search_term): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            <?php endif; ?>
                            <label for="sort">Sort By:</label>
                            <select name="sort" id="sort" onchange="document.getElementById('sortForm').submit();">
                                <option value="default" <?php if ($sort_order == 'default') echo 'selected'; ?>>Default</option>
                                <option value="name_asc" <?php if ($sort_order == 'name_asc') echo 'selected'; ?>>Name (A-Z)</option>
                                <option value="name_desc" <?php if ($sort_order == 'name_desc') echo 'selected'; ?>>Name (Z-A)</option>
                                <option value="price_asc" <?php if ($sort_order == 'price_asc') echo 'selected'; ?>>Price (Low to High)</option>
                                <option value="price_desc" <?php if ($sort_order == 'price_desc') echo 'selected'; ?>>Price (High to Low)</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="product-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): // Loop through fetched products ?>
                            <div class="product-card">
                                <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="product-image-link">
                                    <div class="product-image-wrapper">
                                         <img src="<?php echo htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                </a>
                                <div class="product-details">
                                    <div class="product-info">
                                        <h3 class="product-name">
                                            <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h3>
                                        <p class="product-price">Ksh. <?php echo htmlspecialchars(number_format((float)$product['price'], 2)); ?></p>
                                    </div>
                                    <div class="product-actions">
                                        <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="view-details-button">View Details</a>
                                        </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: // Display message if no products are found ?>
                         <p class="no-products">
                            <?php
                            // Generate specific "no products" message based on filters
                            if ($search_term) {
                                echo 'No products found matching your search term "' . htmlspecialchars($search_term) . '".';
                            } elseif ($category_filter) {
                                echo 'No products found in this category.';
                            } else {
                                echo 'No products currently available.';
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($total_pages > 1): // Show pagination only if there's more than one page ?>
                <nav aria-label="Product navigation">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; // Disable if on first page ?>">
                            <a class="page-link" href="<?php echo ($current_page <= 1) ? '#' : build_url(['page' => $current_page - 1]); // Link to previous page ?>">Previous</a>
                        </li>

                        <?php
                         // Simple pagination link loop (Consider adding logic for large number of pages e.g., ellipses ...)
                         for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; // Highlight current page ?>">
                                <a class="page-link" href="<?php echo build_url(['page' => $i]); // Link to specific page ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; // Disable if on last page ?>">
                            <a class="page-link" href="<?php echo ($current_page >= $total_pages) ? '#' : build_url(['page' => $current_page + 1]); // Link to next page ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                </div> </div> </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>

    <script src="js/script.js"></script>
    </body>
</html>

<?php
// Close the database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>