<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$user_id = null;
$user = null;
$user_orders = [];
$user_addresses = [];
$error_message = '';
$success_message = '';
$total_user_orders = 0; // Initialize total user orders count

// --- Get User ID from URL and Validate ---
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];

    // --- Fetch User Details ---
    // Select user information based on the ID
    // Adjust column names (name, created_at) based on your 'users' table structure
    $sql_user = "SELECT user_id, first_name, email, created_at, updated_at FROM users WHERE user_id = ?"; // Using first_name based on screenshot
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
        $stmt_user->close(); // Close user fetch statement early

        // --- Fetch User's Addresses ---
        // Select addresses linked to this user
        // Use column names from user_addresses table (street_address1, street_address2, postal_code)
        $sql_addresses = "SELECT address_id, address_type, street_address1, street_address2, city, state, postal_code, country, is_default FROM user_addresses WHERE user_id = ?";
        $stmt_addresses = $conn->prepare($sql_addresses);
        $stmt_addresses->bind_param("i", $user_id);
        $stmt_addresses->execute();
        $result_addresses = $stmt_addresses->get_result();
        while ($row_address = $result_addresses->fetch_assoc()) {
            $user_addresses[] = $row_address;
        }
        $stmt_addresses->close();

        // --- Fetch User's Orders (with Filtering, Sorting, Pagination) ---

        // --- Filtering for User's Orders List ---
        $order_filter_id = trim($_GET['order_filter_id'] ?? '');
        $order_filter_payment_status = $_GET['order_filter_payment_status'] ?? '';
        $order_filter_delivery_status = $_GET['order_filter_delivery_status'] ?? '';
        // Add more filter parameters here (e.g., date range, product name - requires joining order_items)

        $order_where_clauses = [];
        $order_bind_params = [];
        $order_bind_types = '';

        // Always filter by the current user ID
        $order_where_clauses[] = "o.user_id = ?";
        $order_bind_params[] = $user_id;
        $order_bind_types .= 'i'; // User ID is integer

        if (!empty($order_filter_id) && is_numeric($order_filter_id)) {
            $order_where_clauses[] = "o.order_id = ?";
            $order_bind_params[] = (int)$order_filter_id; // Bind as integer
            $order_bind_types .= 'i';
        }

        if (!empty($order_filter_payment_status)) {
            $order_where_clauses[] = "o.payment_status = ?";
            $order_bind_params[] = $order_filter_payment_status;
            $order_bind_types .= 's'; // Status is string
        }

        if (!empty($order_filter_delivery_status)) {
            $order_where_clauses[] = "o.delivery_status = ?";
            $order_bind_params[] = $order_filter_delivery_status;
            $order_bind_types .= 's'; // Status is string
        }
        // Add date range filter logic here if implemented
        // Add product name filter logic here if implemented (requires JOIN)


        // Combine WHERE clauses for orders
        $order_where_sql = ' WHERE ' . implode(' AND ', $order_where_clauses);


        // --- Sorting for User's Orders List ---
        $allowed_order_sort_columns = ['order_id', 'order_date', 'total_amount', 'payment_status', 'delivery_status']; // Columns allowed for sorting
        $default_order_sort_column = 'order_date';
        $default_order_sort_direction = 'DESC';

        $order_sort_column = $_GET['order_sort'] ?? $default_order_sort_column;
        $order_sort_direction = $_GET['order_dir'] ?? $default_order_sort_direction;

        // Validate the sort column and direction
        if (!in_array($order_sort_column, $allowed_order_sort_columns)) {
            $order_sort_column = $default_order_sort_column;
        }
        if (!in_array(strtoupper($order_sort_direction), ['ASC', 'DESC'])) {
            $order_sort_direction = $default_order_sort_direction;
        }

        // Determine the column name in the database (prefix with o. for orders table)
        $order_db_sort_column = 'o.' . $order_sort_column;

        $order_by_sql = " ORDER BY " . $order_db_sort_column . " " . $order_sort_direction;


        // --- Pagination for User's Orders List ---
        $order_limit = 5; // Number of orders per page - Adjust as needed
        $order_page = $_GET['order_page'] ?? 1;
        $order_page = max(1, (int)$order_page); // Ensure page is at least 1 and is an integer


        // --- Count total orders for this user (with filters applied) ---
        // Need a separate query to count total rows *before* applying LIMIT for pagination calculation
        // This count query needs the same WHERE clauses as the main fetch query
        $sql_count_orders = "SELECT COUNT(*) AS total_user_orders FROM orders o" . $order_where_sql;

        $stmt_count_orders = $conn->prepare($sql_count_orders);

        // Bind parameters for the order count query (excluding limit/offset)
        // Create a new array containing only the filter parameters
        $order_count_bind_params = [];
        $order_count_bind_types = '';
        // Manually collect parameters used in the where clauses for count query
        // This is safer than relying on array_merge with limit/offset indices
        if (!empty($order_bind_params)) {
            // The first parameter is always user_id
            $order_count_bind_params[] = $order_bind_params[0]; // user_id
            $order_count_bind_types .= $order_bind_types[0];

            // Collect other filter parameters if they exist (skipping the first user_id param)
            for ($i = 1; $i < count($order_bind_params); $i++) {
                $order_count_bind_params[] = $order_bind_params[$i];
                $order_count_bind_types .= $order_bind_types[$i];
            }
        }


        if (!empty($order_count_bind_params)) {
            $stmt_count_orders->bind_param($order_count_bind_types, ...$order_count_bind_params);
        }


        $execute_count_orders_success = $stmt_count_orders->execute();
        if ($execute_count_orders_success) {
            $result_count_orders = $stmt_count_orders->get_result();
            $row_count_orders = $result_count_orders->fetch_assoc();
            $total_user_orders = $row_count_orders['total_user_orders'];
            $result_count_orders->free();
        } else {
            $error_message = "Database error counting user orders: " . $stmt_count_orders->error;
        }
        $stmt_count_orders->close();

        // Calculate total pages for user's orders
        $total_order_pages = ($total_user_orders > 0) ? ceil($total_user_orders / $order_limit) : 1; // Ensure at least 1 page if total > 0
        // Ensure current order page doesn't exceed total pages
        if ($order_page > $total_order_pages) {
            $order_page = $total_order_pages;
        }
        // Calculate offset AFTER potentially adjusting the page
        $order_offset = ($order_page - 1) * $order_limit;
        // Ensure offset is not negative
        $order_offset = max(0, $order_offset);


        // --- Fetch User's Orders (with filters, sorting, and pagination) ---
        $sql_orders = "SELECT
                           o.order_id, o.order_date, o.total_amount, o.payment_status, o.delivery_status
                       FROM orders o"
            . $order_where_sql // Add WHERE clause for orders
            . $order_by_sql    // Add ORDER BY clause for orders
            . " LIMIT ? OFFSET ?"; // Add LIMIT and OFFSET for pagination

        $stmt_orders = $conn->prepare($sql_orders);

        // Dynamically bind parameters for the main order fetch query
        // Bind types: order filter types + 'ii' for LIMIT and OFFSET
        $order_fetch_bind_types = $order_bind_types . 'ii';
        $order_fetch_bind_params = array_merge($order_bind_params, [$order_limit, $order_offset]);


        if (!empty($order_fetch_bind_params)) {
            $stmt_orders->bind_param($order_fetch_bind_types, ...$order_fetch_bind_params);
        }


        $execute_orders_success = $stmt_orders->execute();

        if ($execute_orders_success) {
            $result_orders = $stmt_orders->get_result();
            while ($row_order = $result_orders->fetch_assoc()) {
                $user_orders[] = $row_order;
            }
            $result_orders->free();
        } else {
            // Handle query execution error
            if (empty($error_message)) { // Don't overwrite a count error message
                $error_message = "Error fetching user's orders: " . $stmt_orders->error;
            }
            $user_orders = []; // Ensure array is empty on error
        }
        $stmt_orders->close();


        // --- Define possible status options for order filter dropdowns (consistent with orders list) ---
        $order_payment_status_options = ['' => 'All Payment Statuses', 'Pending' => 'Pending', 'Paid' => 'Paid', 'Refunded' => 'Refunded', 'Failed' => 'Failed']; // Add/Remove as needed
        $order_delivery_status_options = ['' => 'All Delivery Statuses', 'Pending' => 'Pending', 'Processing' => 'Processing', 'Shipped' => 'Shipped', 'Delivered' => 'Delivered', 'Cancelled' => 'Cancelled', 'Failed' => 'Failed']; // Add/Remove as needed


    } else {
        // User not found
        $_SESSION['error_message'] = "User not found.";
        header('Location: index.php'); // Redirect back to users list
        exit();
    }
} else {
    // No valid ID provided
    $_SESSION['error_message'] = "Invalid or missing user ID.";
    header('Location: index.php'); // Redirect back to users list
    exit();
}

// Close the database connection if necessary (depends on db_connect.php)
// closeDB($conn);


// --- Check for status messages from session (e.g., from edit/delete actions) ---
$session_status_message = '';
$session_message_type = '';

if (isset($_SESSION['success_message'])) {
    $session_status_message = $_SESSION['success_message'];
    $session_message_type = 'success';
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_SESSION['error_message'])) {
    // Don't overwrite internal error messages
    if (empty($error_message)) {
        $session_status_message = $_SESSION['error_message'];
        $session_message_type = 'error';
    }
    unset($_SESSION['error_message']); // Clear the message after displaying
}

// Prioritize internal messages
if (!empty($error_message)) {
    $session_status_message = $error_message;
    $session_message_type = 'error';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details (ID: <?php echo htmlspecialchars($user_id ?? 'N/A'); ?>) - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Specific styles for User Details page layout */
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            /* Responsive columns */
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .user-section h3 {
            margin-top: 0;
            color: #343a40;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .user-section p {
            margin-bottom: 10px;
            font-size: 1em;
        }

        .user-section p strong {
            color: #555;
        }

        .user-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .user-actions a {
            margin-right: 15px;
            text-decoration: none;
        }

        /* Styling for the filter form within the user orders section */
        .order-filter-form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            /* Smaller gap for inline filters */
            align-items: center;
            /* Align items vertically */
        }

        .order-filter-form label {
            font-weight: bold;
            margin-right: 5px;
            color: #555;
        }

        .order-filter-form .form-group {
            /* Style form groups within the order filter */
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-filter-form .form-group label {
            margin-bottom: 0;
            min-width: 60px;
            /* Adjust label width as needed */
            text-align: right;
        }

        .order-filter-form input[type="text"],
        .order-filter-form select,
        .order-filter-form button {
            padding: 6px 10px;
            /* Smaller padding */
            border-radius: 4px;
            border: 1px solid #ced4da;
            font-size: 0.95em;
        }

        .order-filter-form button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s ease-in-out;
        }

        .order-filter-form button:hover {
            background-color: #0056b3;
        }

        .order-filter-form a.button-secondary {
            /* Style the reset button/link */
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            background-color: #6c757d;
            color: white;
            border: none;
            font-size: 0.95em;
            margin-left: 5px;
            /* Space after filter button */
        }

        .order-filter-form a.button-secondary:hover {
            background-color: #5a6268;
        }


        .user-orders-list table,
        .user-addresses-list table {
            margin-top: 15px;
            width: 100%;
            /* Ensure nested tables take full width */
            border-collapse: collapse;
            background-color: #f8f9fa;
            /* Slightly different background for nested tables */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            /* Lighter shadow for nested tables */
            border-radius: 5px;
            /* Smaller rounded corners */
            overflow: hidden;
            /* Hide overflow for rounded corners */
        }

        .user-orders-list th,
        .user-orders-list td,
        .user-addresses-list th,
        .user-addresses-list td {
            padding: 8px 10px;
            /* Smaller padding for nested table cells */
            border: 1px solid #dee2e6;
            /* Lighter borders */
            font-size: 0.95em;
        }

        .user-orders-list th a {
            /* Style sortable headers in the nested table */
            text-decoration: none;
            color: #495057;
            /* Inherit header text color */
            display: block;
        }

        .user-orders-list th a:hover {
            color: #007bff;
            /* Highlight on hover */
        }

        .user-orders-list th span.sort-arrow {
            /* Style sort arrow in nested table */
            font-size: 0.7em;
            vertical-align: middle;
        }


        .user-orders-list th,
        .user-addresses-list th {
            background-color: #e9ecef;
            font-weight: bold;
            /* Ensure headers are bold */
            font-size: 0.85em;
            text-transform: uppercase;
            color: #495057;
        }

        .user-orders-list tbody tr:nth-child(even),
        .user-addresses-list tbody tr:nth-child(even) {
            background-color: #fff;
            /* White background for even rows in nested tables */
        }

        .user-orders-list tbody tr:hover,
        .user-addresses-list tbody tr:hover {
            background-color: #e2e6ea;
            /* Highlight on hover */
        }

        /* Status badge styling is already in admin_style.css */

        /* Pagination styling (reused, but scoped within user orders section if needed) */
        .user-orders-list .pagination {
            margin-top: 15px;
            /* Adjust margin above pagination in this section */
            margin-bottom: 0;
            /* Remove bottom margin */
        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        include __DIR__ . '/../includes/admin_header.php';
        // Pass current page to sidebar for active state highlighting
        $current_page = 'users'; // Set a variable for the current page (or 'view-user' if you add a specific link)
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>User Details (ID: <?php echo htmlspecialchars($user_id ?? 'N/A'); ?>)</h1>

            <?php if (!empty($session_status_message)): ?>
                <div class="<?php echo $session_message_type; ?>-message">
                    <?php echo $session_status_message; // HTML is allowed here for line breaks in error messages 
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($user): // Only display details if user data was fetched 
            ?>
                <div class="user-details-grid">
                    <div class="user-section">
                        <h3>User Information</h3>
                        <p><strong>User ID:</strong> <?php echo htmlspecialchars($user['user_id']); ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Registered At:</strong> <?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></p>
                        <p><strong>Last Updated At:</strong> <?php echo htmlspecialchars($user['updated_at'] ?? 'N/A'); ?></p>

                        <div class="user-actions">
                            <a href="edit.php?id=<?php echo urlencode($user['user_id']); ?>" class="button">Edit User</a>
                            <a href="delete.php?id=<?php echo urlencode($user['user_id']); ?>" class="button button-secondary" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete User</a>
                        </div>
                    </div>

                    <div class="user-section">
                        <h3>Addresses</h3>
                        <?php if (count($user_addresses) > 0): ?>
                            <div class="user-addresses-list">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Address</th>
                                            <th>Default</th>
                                            {/* Add Action links here later if you want to edit/delete addresses from here */}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_addresses as $address): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($address['address_type'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($address['street_address1'] ?? ''); ?><br>
                                                    <?php if (!empty($address['street_address2'])): ?>
                                                        <?php echo htmlspecialchars($address['street_address2']); ?><br>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($address['city'] ?? ''); ?>, <?php echo htmlspecialchars($address['state'] ?? ''); ?> <?php echo htmlspecialchars($address['postal_code'] ?? ''); ?><br>
                                                    <?php echo htmlspecialchars($address['country'] ?? ''); ?>
                                                </td>
                                                <td>
                                                    <?php if ($address['is_default']): ?>
                                                        Yes
                                                    <?php else: ?>
                                                        No
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No addresses found for this user.</p>
                        <?php endif; ?>
                    </div>

                    <div class="user-section" style="grid-column: 1 / -1;">
                        <h3>Orders Placed by User</h3>

                        <div class="order-filter-form">
                            <form action="" method="GET">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_id); ?>">

                                <div class="form-group">
                                    <label for="order_filter_id">Order ID:</label>
                                    <input type="text" name="order_filter_id" id="order_filter_id" value="<?php echo htmlspecialchars($order_filter_id); ?>" placeholder="Filter by ID">
                                </div>

                                <div class="form-group">
                                    <label for="order_filter_payment_status">Payment Status:</label>
                                    <select name="order_filter_payment_status" id="order_filter_payment_status">
                                        <?php foreach ($order_payment_status_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php if ($order_filter_payment_status === $value) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="order_filter_delivery_status">Delivery Status:</label>
                                    <select name="order_filter_delivery_status" id="order_filter_delivery_status">
                                        <?php foreach ($order_delivery_status_options as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php if ($order_filter_delivery_status === $value) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- {/* Add date range filter inputs here later */}
                                   {/* Add product name filter input here later (requires join) */} -->

                                <button type="submit" class="button">Filter Orders</button>
                                <!-- {/* Reset Filter Button for User's Orders */} -->
                                <?php if (!empty($order_filter_id) || !empty($order_filter_payment_status) || !empty($order_filter_delivery_status)): ?>
                                    <a href="view.php?id=<?php echo urlencode($user_id); ?>" class="button button-secondary">Reset Filter</a>
                                <?php endif; ?>
                            </form>
                        </div>


                        <?php if ($total_user_orders > 0): // Check total orders WITH filters 
                        ?>
                            <div class="user-orders-list">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>
                                                <!-- {/* Order ID Sort Link */} -->
                                                <a href="?id=<?php echo urlencode($user_id); ?>&order_sort=order_id&order_dir=<?php echo ($order_sort_column === 'order_id' && strtoupper($order_sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&order_filter_id=<?php echo htmlspecialchars($order_filter_id); ?>&order_filter_payment_status=<?php echo htmlspecialchars($order_filter_payment_status); ?>&order_filter_delivery_status=<?php echo htmlspecialchars($order_filter_delivery_status); ?>&order_page=<?php echo $order_page; ?>">
                                                    Order ID
                                                    <?php if ($order_sort_column === 'order_id'): ?>
                                                        <span class="sort-arrow"><?php echo (strtoupper($order_sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <!-- {/* Order Date Sort Link */} -->
                                                <a href="?id=<?php echo urlencode($user_id); ?>&order_sort=order_date&order_dir=<?php echo ($order_sort_column === 'order_date' && strtoupper($order_sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&order_filter_id=<?php echo htmlspecialchars($order_filter_id); ?>&order_filter_payment_status=<?php echo htmlspecialchars($order_filter_payment_status); ?>&order_filter_delivery_status=<?php echo htmlspecialchars($order_filter_delivery_status); ?>&order_page=<?php echo $order_page; ?>">
                                                    Date
                                                    <?php if ($order_sort_column === 'order_date'): ?>
                                                        <span class="sort-arrow"><?php echo (strtoupper($order_sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <!-- {/* Total Amount Sort Link */} -->
                                                <a href="?id=<?php echo urlencode($user_id); ?>&order_sort=total_amount&order_dir=<?php echo ($order_sort_column === 'total_amount' && strtoupper($order_sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&order_filter_id=<?php echo htmlspecialchars($order_filter_id); ?>&order_filter_payment_status=<?php echo htmlspecialchars($order_filter_payment_status); ?>&order_filter_delivery_status=<?php echo htmlspecialchars($order_filter_delivery_status); ?>&order_page=<?php echo $order_page; ?>">
                                                    Total
                                                    <?php if ($order_sort_column === 'total_amount'): ?>
                                                        <span class="sort-arrow"><?php echo (strtoupper($order_sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <!-- {/* Payment Status Sort Link */} -->
                                                <a href="?id=<?php echo urlencode($user_id); ?>&order_sort=payment_status&order_dir=<?php echo ($order_sort_column === 'payment_status' && strtoupper($order_sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&order_filter_id=<?php echo htmlspecialchars($order_filter_id); ?>&order_filter_payment_status=<?php echo htmlspecialchars($order_filter_payment_status); ?>&order_filter_delivery_status=<?php echo htmlspecialchars($order_filter_delivery_status); ?>&order_page=<?php echo $order_page; ?>">
                                                    Payment Status
                                                    <?php if ($order_sort_column === 'payment_status'): ?>
                                                        <span class="sort-arrow"><?php echo (strtoupper($order_sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <!-- {/* Delivery Status Sort Link */} -->
                                                <a href="?id=<?php echo urlencode($user_id); ?>&order_sort=delivery_status&order_dir=<?php echo ($order_sort_column === 'delivery_status' && strtoupper($order_sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&order_filter_id=<?php echo htmlspecialchars($order_filter_id); ?>&order_filter_payment_status=<?php echo htmlspecialchars($order_filter_payment_status); ?>&order_filter_delivery_status=<?php echo htmlspecialchars($order_filter_delivery_status); ?>&order_page=<?php echo $order_page; ?>">
                                                    Delivery Status
                                                    <?php if ($order_sort_column === 'delivery_status'): ?>
                                                        <span class="sort-arrow"><?php echo (strtoupper($order_sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_orders as $order): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                                <td>$<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                                <td>
                                                    <?php
                                                    $payment_status_value = $order['payment_status'] ?: 'N/A';
                                                    $payment_status_class = 'status-badge status-' . strtolower($order['payment_status'] ?: 'n-a');
                                                    echo "<span class='" . $payment_status_class . "'>" . htmlspecialchars($payment_status_value) . "</span>";
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $delivery_status_value = $order['delivery_status'] ?: 'N/A';
                                                    $delivery_status_class = 'status-badge status-' . strtolower($order['delivery_status'] ?: 'n-a');
                                                    echo "<span class='" . $delivery_status_class . "'>" . htmlspecialchars($delivery_status_value) . "</span>";
                                                    ?>
                                                </td>
                                                <td class="action-links">
                                                    <a href="../orders/view.php?id=<?php echo urlencode($order['order_id']); ?>">View Order</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- {/* Pagination Links for User's Orders */} -->
                                <?php if ($total_order_pages > 1): ?>
                                    <div class="pagination user-orders-list-pagination">
                                        <?php
                                        // Function to build pagination link URL for user's orders, preserving user ID, filters, and sort
                                        function buildUserOrderPaginationLink($user_id, $page_num, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction)
                                        {
                                            $url = "?id=" . urlencode($user_id) . "&order_page=" . urlencode($page_num);
                                            if (!empty($order_filter_id)) $url .= "&order_filter_id=" . urlencode($order_filter_id);
                                            if (!empty($order_filter_payment_status)) $url .= "&order_filter_payment_status=" . urlencode($order_filter_payment_status);
                                            if (!empty($order_filter_delivery_status)) $url .= "&order_filter_delivery_status=" . urlencode($order_filter_delivery_status);
                                            if (!empty($order_sort_column)) $url .= "&order_sort=" . urlencode($order_sort_column);
                                            if (!empty($order_sort_direction)) $url .= "&order_dir=" . urlencode($order_sort_direction);
                                            return $url;
                                        }
                                        ?>

                                        <?php if ($order_page > 1): ?>
                                            <a href="<?php echo buildUserOrderPaginationLink($user_id, $order_page - 1, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction); ?>">Previous</a>
                                        <?php else: ?>
                                            <span class="disabled">Previous</span>
                                        <?php endif; ?>

                                        <?php
                                        // Display page numbers (e.g., show a few pages around the current page)
                                        $order_start_page = max(1, $order_page - 2);
                                        $order_end_page = min($total_order_pages, $order_page + 2);

                                        if ($order_start_page > 1) {
                                            echo '<a href="' . buildUserOrderPaginationLink($user_id, 1, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction) . '">1</a>';
                                            if ($order_start_page > 2) {
                                                echo '<span>...</span>'; // Ellipsis
                                            }
                                        }

                                        for ($i = $order_start_page; $i <= $order_end_page; $i++):
                                            if ($i == $order_page): ?>
                                                <span class="current-page"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="<?php echo buildUserOrderPaginationLink($user_id, $i, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction); ?>"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php
                                        if ($order_end_page < $total_order_pages) {
                                            if ($order_end_page < $total_order_pages - 1) {
                                                echo '<span>...</span>'; // Ellipsis
                                            }
                                            echo '<a href="' . buildUserOrderPaginationLink($user_id, $total_order_pages, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction) . '">' . $total_order_pages . '</a>';
                                        }
                                        ?>

                                        <?php if ($order_page < $total_order_pages): ?>
                                            <a href="<?php echo buildUserOrderPaginationLink($user_id, $order_page + 1, $order_filter_id, $order_filter_payment_status, $order_filter_delivery_status, $order_sort_column, $order_sort_direction); ?>">Next</a>
                                        <?php else: ?>
                                            <span class="disabled">Next</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>


                            </div>
                        <?php else: ?>
                            <p>No orders found for this user matching your criteria.</p>
                        <?php endif; ?>
                    </div>

                </div>
                <!-- {/* /.user-details-grid */} -->

                <p><a href="index.php" class="button button-secondary">Back to Users List</a></p>


            <?php else: ?>
                <!-- {/* This message should only appear if redirects fail */} -->
                <p>User data could not be loaded.</p>
            <?php endif; ?>


        </div> <!-- {/* /.content-area */} -->

    </div> <!-- {/* /.admin-container */} -->

    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>