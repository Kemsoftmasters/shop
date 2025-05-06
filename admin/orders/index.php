<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

// --- Filtering ---
// Get filter parameters from GET request
$filter_payment_status = $_GET['payment_status'] ?? ''; // Use empty string if not set
$filter_delivery_status = $_GET['delivery_status'] ?? '';

// Build the WHERE clause based on filter parameters
$where_clauses = [];
$bind_params = [];
$bind_types = '';

if (!empty($filter_payment_status)) {
    $where_clauses[] = "o.payment_status = ?";
    $bind_params[] = $filter_payment_status;
    $bind_types .= 's';
}

if (!empty($filter_delivery_status)) {
    $where_clauses[] = "o.delivery_status = ?";
    $bind_params[] = $filter_delivery_status;
    $bind_types .= 's';
}

// --- Date Range Filtering ---
$filter_from_date = $_GET['from_date'] ?? '';
$filter_to_date = $_GET['to_date'] ?? '';

if (!empty($filter_from_date)) {
    // Add start of day time to from_date for correct range comparison
    $where_clauses[] = "o.order_date >= ?";
    $bind_params[] = $filter_from_date . ' 00:00:00';
    $bind_types .= 's'; // Datetime values are often bound as strings
}

if (!empty($filter_to_date)) {
    // Add end of day time to to_date for correct range comparison
    $where_clauses[] = "o.order_date <= ?";
    $bind_params[] = $filter_to_date . ' 23:59:59';
    $bind_types .= 's'; // Datetime values are often bound as strings
}


// --- Customer Search Filtering ---
$filter_customer_name = $_GET['customer_name'] ?? '';

if (!empty($filter_customer_name)) {
    // Use LIKE for partial matching, and use prepared statement with wildcards
    // Assuming the customer's name is stored in the 'name' column of the 'users' table
    // If you use first_name and last_name, you might need to search on both or a concatenated value
    $where_clauses[] = "first_name LIKE ?"; // Adjust 'first_name' if using different column(s)
    $bind_params[] = '%' . $filter_customer_name . '%'; // Add wildcards for partial match
    $bind_types .= 's';
}

// --- Now the rest of the filtering logic (combining WHERE clauses) follows ---
// Combine WHERE clauses
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// ... rest of the PHP code (sorting, query execution, data display) ...

// Combine WHERE clauses
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}


// --- Sorting (Basic sorting by order_date for now) ---
// We'll add dynamic sorting in the next step
// $order_by_sql = " ORDER BY o.order_date DESC";
// --- Sorting ---
$allowed_sort_columns = ['order_id', 'order_date', 'total_amount', 'payment_status', 'delivery_status', 'customer_name']; // Columns allowed for sorting
$default_sort_column = 'order_date';
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
$db_sort_column = $sort_column;
if ($sort_column === 'customer_name') {
    $db_sort_column = 'first_name'; // Use the actual database column name for joining table
} else {
    $db_sort_column = 'o.' . $sort_column; // Prefix with 'o.' for orders table columns
}


$order_by_sql = " ORDER BY " . $db_sort_column . " " . $sort_direction;

// Determine the opposite direction for toggle links
$opposite_direction = (strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc';


// --- Fetch Orders Data ---
// Fetch orders and join with the users table to get customer name
$sql = "SELECT
            o.order_id,
            o.order_date,
            o.total_amount,
            o.payment_status,
            o.delivery_status,
            u.first_name AS customer_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id"
    . $where_sql // Add the dynamically built WHERE clause
    . $order_by_sql; // Add the ORDER BY clause

// Use prepared statement if there are any WHERE clauses
if (!empty($where_clauses)) {
    $stmt = $conn->prepare($sql);
    // Dynamically bind parameters
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params); // Use splat operator for dynamic binding
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Execute query directly if no WHERE clauses
    $result = $conn->query($sql);
}


// --- Prepare data for display ---
$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
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

// --- Define possible status options for dropdowns ---
$payment_status_options = ['' => 'All Payment Statuses', 'Pending' => 'Pending', 'Paid' => 'Paid', 'Refunded' => 'Refunded', 'Failed' => 'Failed']; // Add/Remove as needed
$delivery_status_options = ['' => 'All Delivery Statuses', 'Pending' => 'Pending', 'Processing' => 'Processing', 'Shipped' => 'Shipped', 'Delivered' => 'Delivered', 'Cancelled' => 'Cancelled', 'Failed' => 'Failed']; // Add/Remove as needed


// --- HTML Structure ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Basic table styling */
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

        /* Styling for status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: capitalize;
            /* Make status look nicer */
            display: inline-block;
            /* Ensure padding and margin work */
        }

        /* Basic status colors - adjust as needed */
        .status-pending {
            background-color: #ffc107;
            color: #333;
        }

        /* Warning yellow */
        .status-processing {
            background-color: #17a2b8;
            color: white;
        }

        /* Info cyan */
        .status-shipped {
            background-color: #007bff;
            color: white;
        }

        /* Primary blue */
        .status-delivered {
            background-color: #28a745;
            color: white;
        }

        /* Success green */
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }

        /* Danger red */
        .status-failed {
            background-color: #dc3545;
            color: white;
        }

        /* Danger red */
        .status-paid {
            background-color: #28a745;
            color: white;
        }

        /* Green - Example payment status color */
        .status-refunded {
            background-color: #6c757d;
            color: white;
        }

        /* Grey - Example payment status color */
        .status-n-a {
            background-color: #adb5bd;
            color: #333;
        }

        /* Light grey - For N/A status */

        /* Styling for filter form */
        .filter-form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
        }

        .filter-form label {
            font-weight: bold;
            margin-right: 10px;
        }

        .filter-form select,
        .filter-form button {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            margin-right: 10px;
        }

        .filter-form button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
            border: none;
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
            <h1>Orders Management</h1>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div class="filter-form">
                <form action="" method="GET">
                    <label for="payment_status">Payment Status:</label>
                    <select name="payment_status" id="payment_status">
                        <?php foreach ($payment_status_options as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"
                                <?php if ($filter_payment_status === $value) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="delivery_status">Delivery Status:</label>
                    <select name="delivery_status" id="delivery_status">
                        <?php foreach ($delivery_status_options as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"
                                <?php if ($filter_delivery_status === $value) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <br><br> <label for="from_date">From Date:</label>
                    <input type="date" name="from_date" id="from_date" value="<?php echo htmlspecialchars($filter_from_date); ?>">

                    <label for="to_date">To Date:</label>
                    <input type="date" name="to_date" id="to_date" value="<?php echo htmlspecialchars($filter_to_date); ?>">

                    <label for="customer_name">Customer Name:</label>
                    <input type="text" name="customer_name" id="customer_name" value="<?php echo htmlspecialchars($filter_customer_name); ?>" placeholder="Search customer name">

                    <button type="submit">Filter Orders</button>
                    <?php if (!empty($filter_payment_status) || !empty($filter_delivery_status) || !empty($filter_from_date) || !empty($filter_to_date) || !empty($filter_customer_name)): ?>
                        <a href="index.php" class="button button-secondary">Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>


            <?php if (count($orders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>
                                <a href="?sort=order_id&dir=<?php echo ($sort_column === 'order_id' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Order ID
                                    <?php if ($sort_column === 'order_id'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=customer_name&dir=<?php echo ($sort_column === 'customer_name' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Customer
                                    <?php if ($sort_column === 'customer_name'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=order_date&dir=<?php echo ($sort_column === 'order_date' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Order Date
                                    <?php if ($sort_column === 'order_date'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=total_amount&dir=<?php echo ($sort_column === 'total_amount' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Total Amount
                                    <?php if ($sort_column === 'total_amount'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=payment_status&dir=<?php echo ($sort_column === 'payment_status' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Payment Status
                                    <?php if ($sort_column === 'payment_status'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=delivery_status&dir=<?php echo ($sort_column === 'delivery_status' && $sort_direction === 'asc') ? 'desc' : 'asc'; ?>&payment_status=<?php echo htmlspecialchars($filter_payment_status); ?>&delivery_status=<?php echo htmlspecialchars($filter_delivery_status); ?>&from_date=<?php echo htmlspecialchars($filter_from_date); ?>&to_date=<?php echo htmlspecialchars($filter_to_date); ?>&customer_name=<?php echo htmlspecialchars($filter_customer_name); ?>">
                                    Delivery Status
                                    <?php if ($sort_column === 'delivery_status'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                <td>$<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td>
                                    <?php
                                    // Apply status styling to payment status
                                    $payment_status_value = $order['payment_status'] ?: 'N/A';
                                    $payment_status_class = 'status-badge status-' . strtolower($order['payment_status'] ?: 'n-a');
                                    echo "<span class='" . $payment_status_class . "'>" . htmlspecialchars($payment_status_value) . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    // Apply status styling to delivery status
                                    $delivery_status_value = $order['delivery_status'] ?: 'N/A';
                                    $delivery_status_class = 'status-badge status-' . strtolower($order['delivery_status'] ?: 'n-a');
                                    echo "<span class='" . $delivery_status_class . "'>" . htmlspecialchars($delivery_status_value) . "</span>";
                                    ?>
                                </td>
                                <td class="action-links">
                                    <a href="view.php?id=<?php echo urlencode($order['order_id']); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No orders found matching your criteria.</p> <?php endif; ?>

        </div>
    </div> <!-- /.admin-container -->

    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '../../includes/admin_footer.php';
    ?>

</body>

</html>