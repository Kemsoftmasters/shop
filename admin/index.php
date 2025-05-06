<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name


// --- Fetch Dashboard Data ---

// 1. Basic Counts
$total_products = 0;
$total_orders = 0;
$total_customers = 0;

$result_products = $conn->query("SELECT COUNT(*) AS total FROM products");
if ($result_products) {
    $row_products = $result_products->fetch_assoc();
    $total_products = $row_products['total'];
    $result_products->free();
} else { /* Handle error */
}

$result_orders_count = $conn->query("SELECT COUNT(*) AS total FROM orders");
if ($result_orders_count) {
    $row_orders_count = $result_orders_count->fetch_assoc();
    $total_orders = $row_orders_count['total'];
    $result_orders_count->free();
} else { /* Handle error */
}

$result_users = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result_users) {
    $row_users = $result_users->fetch_assoc();
    $total_customers = $row_users['total'];
    $result_users->free();
} else { /* Handle error */
}


// 2. Sales Summary
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$last_7_days_start = date('Y-m-d 00:00:00', strtotime('-7 days'));


$total_sales_today = 0;
$sql_sales_today = "SELECT SUM(total_amount) AS total_sum FROM orders WHERE order_date BETWEEN ? AND ?";
$stmt_sales_today = $conn->prepare($sql_sales_today);
if ($stmt_sales_today) {
    $stmt_sales_today->bind_param("ss", $today_start, $today_end);
    $stmt_sales_today->execute();
    $result_sales_today = $stmt_sales_today->get_result();
    $row_sales_today = $result_sales_today->fetch_assoc();
    $total_sales_today = $row_sales_today['total_sum'] ?? 0; // Use ?? 0 for cases with no sales
    $result_sales_today->free();
    $stmt_sales_today->close();
} else { /* Handle error */
}


$total_sales_last_7_days = 0;
$sql_sales_last_7_days = "SELECT SUM(total_amount) AS total_sum FROM orders WHERE order_date >= ?";
$stmt_sales_last_7_days = $conn->prepare($sql_sales_last_7_days);
if ($stmt_sales_last_7_days) {
    $stmt_sales_last_7_days->bind_param("s", $last_7_days_start);
    $stmt_sales_last_7_days->execute();
    $result_sales_last_7_days = $stmt_sales_last_7_days->get_result();
    $row_sales_last_7_days = $result_sales_last_7_days->fetch_assoc();
    $total_sales_last_7_days = $row_sales_last_7_days['total_sum'] ?? 0; // Use ?? 0
    $result_sales_last_7_days->free();
    $stmt_sales_last_7_days->close();
} else { /* Handle error */
}


// 3. Order Status Summary
$order_status_summary = [];
// Adjust the GROUP BY and status values based on your 'payment_status' and 'delivery_status' columns
// This query counts statuses from both columns and aggregates them
$sql_status_summary = "SELECT status_name, SUM(count) as total_count
                       FROM (
                           SELECT payment_status as status_name, COUNT(*) as count
                           FROM orders
                           GROUP BY payment_status
                           UNION ALL
                           SELECT delivery_status as status_name, COUNT(*) as count
                           FROM orders
                           GROUP BY delivery_status
                       ) AS combined_statuses
                       WHERE status_name IS NOT NULL AND status_name != ''
                       GROUP BY status_name
                       ORDER BY status_name ASC"; // Order alphabetically

$result_status_summary = $conn->query($sql_status_summary);
if ($result_status_summary) {
    while ($row = $result_status_summary->fetch_assoc()) {
        $order_status_summary[$row['status_name']] = $row['total_count'];
    }
    $result_status_summary->free();
} else { /* Handle error */
}


// 4. Top Selling Products (by quantity)
$top_selling_products = [];
$top_n = 5; // Number of top products to show
$sql_top_selling = "SELECT
                        p.product_id, p.name, SUM(oi.quantity) AS total_quantity_sold
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.product_id
                    GROUP BY p.product_id, p.name -- Group by both id and name
                    ORDER BY total_quantity_sold DESC
                    LIMIT ?";
$stmt_top_selling = $conn->prepare($sql_top_selling);
if ($stmt_top_selling) {
    $stmt_top_selling->bind_param("i", $top_n);
    $stmt_top_selling->execute();
    $result_top_selling = $stmt_top_selling->get_result();
    if ($result_top_selling && $result_top_selling->num_rows > 0) {
        while ($row = $result_top_selling->fetch_assoc()) {
            $top_selling_products[] = $row;
        }
        $result_top_selling->free();
    }
    $stmt_top_selling->close();
} else { /* Handle error */
}


// 5. Recent Orders
$recent_orders = [];
$sql_recent_orders = "SELECT o.order_id, o.order_date, o.total_amount, o.payment_status, o.delivery_status, u.first_name AS customer_name
                      FROM orders o LEFT JOIN users u ON o.user_id = u.user_id
                      ORDER BY o.order_date DESC LIMIT 5"; // Get last 5 orders
$result_recent_orders = $conn->query($sql_recent_orders);
if ($result_recent_orders && $result_recent_orders->num_rows > 0) {
    while ($row = $result_recent_orders->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    $result_recent_orders->free();
} else { /* Handle error */
}


// 6. Low Stock Products
$low_stock_products = [];
$stock_threshold = 10; // Define your low stock threshold
$sql_low_stock = "SELECT product_id, name, stock_quantity FROM products WHERE stock_quantity <= ? ORDER BY stock_quantity ASC LIMIT 5";
$stmt_low_stock = $conn->prepare($sql_low_stock);
if ($stmt_low_stock) {
    $stmt_low_stock->bind_param("i", $stock_threshold);
    $stmt_low_stock->execute();
    $result_low_stock = $stmt_low_stock->get_result();
    if ($result_low_stock && $result_low_stock->num_rows > 0) {
        while ($row = $result_low_stock->fetch_assoc()) {
            $low_stock_products[] = $row;
        }
        $result_low_stock->free();
    }
    $stmt_low_stock->close();
} else { /* Handle error */
}


// --- Fetch Data for Charts ---

// 1. Sales Data by Day (Last 7 Days) for Line Chart
$sales_data_7_days = [];
$sql_daily_sales = "SELECT
                        DATE(order_date) as order_day, SUM(total_amount) as daily_sales
                    FROM orders
                    WHERE order_date >= DATE(?)
                    GROUP BY order_day
                    ORDER BY order_day ASC"; // Get daily sales for last 7 days
$stmt_daily_sales = $conn->prepare($sql_daily_sales);
if ($stmt_daily_sales) {
    $last_7_days = date('Y-m-d', strtotime('-7 days'));
    $stmt_daily_sales->bind_param("s", $last_7_days);
    $stmt_daily_sales->execute();
    $result_daily_sales = $stmt_daily_sales->get_result();
    while ($row = $result_daily_sales->fetch_assoc()) {
        $sales_data_7_days[] = $row;
    }
    $result_daily_sales->free();
    $stmt_daily_sales->close();
} else { /* Handle error */
}

// Prepare data for Chart.js Line Chart (Labels and Data)
$sales_chart_labels = [];
$sales_chart_data = [];
$period = new DatePeriod(
    new DateTime(date('Y-m-d', strtotime('-7 days'))),
    new DateInterval('P1D'),
    new DateTime(date('Y-m-d', strtotime('+1 day'))) // Go up to tomorrow to include today
);

// Initialize daily sales to 0 for the last 7 days
$daily_sales_initialized = array_fill_keys(array_map(function ($date) {
    return $date->format('Y-m-d');
}, iterator_to_array($period)), 0);

// Populate with actual sales data where available
foreach ($sales_data_7_days as $day_data) {
    if (isset($daily_sales_initialized[$day_data['order_day']])) {
        $daily_sales_initialized[$day_data['order_day']] = (float)$day_data['daily_sales']; // Cast to float
    }
}

// Format for Chart.js (Labels as 'Day Month', Data as values)
foreach ($daily_sales_initialized as $date_str => $sales_amount) {
    $sales_chart_labels[] = date('M d', strtotime($date_str)); // e.g., May 01
    $sales_chart_data[] = $sales_amount;
}


// 2. Order Status Data for Doughnut Chart
// Reuse the $order_status_summary data fetched earlier
// Ensure the keys (status names) and values (counts) are ready
// The existing $order_status_summary array from the reports section is suitable if it aggregates counts by unique status name.
// If you need to specifically target payment or delivery statuses for the chart, adjust the query.
// For a combined status chart, the existing $order_status_summary is perfect.

$status_chart_labels = array_keys($order_status_summary); // Status names
$status_chart_data = array_values($order_status_summary); // Counts


// --- Encode Data as JSON for JavaScript ---
$sales_chart_labels_json = json_encode($sales_chart_labels);
$sales_chart_data_json = json_encode($sales_chart_data);
$status_chart_labels_json = json_encode($status_chart_labels);
$status_chart_data_json = json_encode($status_chart_data);


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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Specific styles for the Dashboard page */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            /* Responsive stat boxes */
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-box h4 {
            margin-top: 0;
            color: #555;
            font-size: 1em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-box p {
            margin: 0;
            font-size: 2em;
            /* Larger font for the number */
            font-weight: bold;
            color: #007bff;
            /* Primary color */
        }

        .dashboard-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .dashboard-section h3 {
            margin-top: 0;
            color: #343a40;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        /* Style for lists or tables within dashboard sections */
        .dashboard-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dashboard-section ul li {
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }

        .dashboard-section ul li:last-child {
            border-bottom: none;
        }

        .dashboard-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            /* Remove default table margin */
        }

        .dashboard-section th,
        .dashboard-section td {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            text-align: left;
            font-size: 0.95em;
        }

        .dashboard-section th {
            background-color: #e9ecef;
            font-weight: bold;
            text-transform: uppercase;
            color: #495057;
            font-size: 0.85em;
        }

        .dashboard-section tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .dashboard-section tbody tr:hover {
            background-color: #e2e6ea;
        }

        .dashboard-section .action-links a {
            margin-right: 10px;
            text-decoration: none;
        }

        /* Specific style for status badges in dashboard tables */
        .dashboard-section .status-badge {
            font-size: 0.85em;
            /* Slightly smaller badges */
            padding: 3px 8px;
            border-radius: 12px;
            /* More rounded */
            display: inline-block;
            font-weight: bold;
        }

        /* Status colors (should match admin_style.css) */
        .dashboard-section .status-pending {
            background-color: #ffc107;
            color: #212529;
        }

        /* Warning */
        .dashboard-section .status-processing {
            background-color: #17a2b8;
            color: #fff;
        }

        /* Info */
        .dashboard-section .status-shipped {
            background-color: #007bff;
            color: #fff;
        }

        /* Primary */
        .dashboard-section .status-delivered {
            background-color: #28a745;
            color: #fff;
        }

        /* Success */
        .dashboard-section .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        /* Danger */
        .dashboard-section .status-failed {
            background-color: #6c757d;
            color: #fff;
        }

        /* Secondary */
        .dashboard-section .status-paid {
            background-color: #28a745;
            color: #fff;
        }

        /* Success */
        .dashboard-section .status-refunded {
            background-color: #ffc107;
            color: #212529;
        }

        /* Warning */
        .dashboard-section .status-n-a {
            background-color: #f8f9fa;
            color: #343a40;
        }

        /* Light */


        /* Styling for chart containers */
        .chart-section {
            /* Similar to dashboard-section for charts */
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .chart-section h3 {
            margin-top: 0;
            color: #343a40;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .chart-container {
            position: relative;
            /* Needed for chart.js responsiveness */
            margin: auto;
            height: 300px;
            /* Set a default height */
            width: 100%;
            /* Take full width of parent */
            max-width: 600px;
            /* Limit max width for charts */
        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        include __DIR__ . '/includes/admin_header.php';
        // Pass current page to sidebar for active state highlighting
        $current_page = 'dashboard'; // Set the current page variable
        include __DIR__ . '/includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Dashboard</h1>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-stats">
                <div class="stat-box">
                    <h4>Total Products</h4>
                    <p><?php echo htmlspecialchars($total_products); ?></p>
                </div>
                <div class="stat-box">
                    <h4>Total Orders</h4>
                    <p><?php echo htmlspecialchars($total_orders); ?></p>
                </div>
                <div class="stat-box">
                    <h4>Total Customers</h4>
                    <p><?php echo htmlspecialchars($total_customers); ?></p>
                </div>
                <!-- {/* Add more stat boxes here */} -->
            </div>

            <div class="dashboard-section">
                <h3>Sales Summary</h3>
                <p><strong>Total Sales Today:</strong> $<?php echo htmlspecialchars(number_format((float)$total_sales_today, 2)); ?></p>
                 <!-- {/* Cast to float for number_format */} -->
                <p><strong>Total Sales Last 7 Days:</strong> $<?php echo htmlspecialchars(number_format((float)$total_sales_last_7_days, 2)); ?></p>
                 <!-- {/* Cast to float for number_format */} -->
                <!-- {/* Add more sales metrics here */} -->
            </div>

            <div class="dashboard-section">
                <h3>Order Status Summary</h3>
                <?php if (!empty($order_status_summary)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_status_summary as $status_name => $count): ?>
                                <tr>
                                    <td>
                                        <?php
                                        // Apply status badge styling if applicable
                                        // Ensure valid class names (lowercase, replace spaces/special chars)
                                        $safe_status_name = strtolower(str_replace([' ', '-', '_'], '-', $status_name));
                                        $status_class = 'status-badge status-' . htmlspecialchars($safe_status_name ?: 'n-a');
                                        echo "<span class='" . $status_class . "'>" . htmlspecialchars($status_name ?: 'N/A') . "</span>";
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No order status data available.</p>
                <?php endif; ?>
            </div>

            <!-- {/* Add Chart Sections */} -->
            <div class="dashboard-section">
                <h3>Sales Over Last 7 Days</h3>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Order Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>


            <div class="dashboard-section">
                <h3>Recent Orders</h3>
                <?php if (!empty($recent_orders)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Payment Status</th> 
                                <!-- {/* Added status columns */} -->
                                <th>Delivery Status</th> 
                                <!-- {/* Added status columns */} -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format((float)$order['total_amount'], 2)); ?></td> 
                                    <!-- {/* Cast to float */} -->
                                    <td>
                                        <?php
                                        $payment_status_value = $order['payment_status'] ?: 'N/A';
                                        $payment_status_class = 'status-badge status-' . strtolower(str_replace([' ', '-', '_'], '-', $order['payment_status'] ?: 'n-a'));
                                        echo "<span class='" . $payment_status_class . "'>" . htmlspecialchars($payment_status_value) . "</span>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $delivery_status_value = $order['delivery_status'] ?: 'N/A';
                                        $delivery_status_class = 'status-badge status-' . strtolower(str_replace([' ', '-', '_'], '-', $order['delivery_status'] ?: 'n-a'));
                                        echo "<span class='" . $delivery_status_class . "'>" . htmlspecialchars($delivery_status_value) . "</span>";
                                        ?>
                                    </td>
                                    <td class="action-links">
                                        <a href="orders/view.php?id=<?php echo urlencode($order['order_id']); ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No recent orders.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <h3>Top <?php echo htmlspecialchars($top_n); ?> Selling Products (by Quantity)</h3>
                <?php if (!empty($top_selling_products)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Quantity Sold</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_selling_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['total_quantity_sold']); ?></td>
                                    <td class="action-links">
                                        <a href="products/edit.php?id=<?php echo urlencode($product['product_id']); ?>">View/Edit</a>
                                         <!-- {/* Link to product page */} -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No top selling products data available.</p>
                <?php endif; ?>
            </div>


            <div class="dashboard-section">
                <h3>Low Stock Products (Threshold: <?php echo htmlspecialchars($stock_threshold); ?>)</h3>
                <?php if (!empty($low_stock_products)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                                    <td class="action-links">
                                        <a href="products/edit.php?id=<?php echo urlencode($product['product_id']); ?>">Edit Stock</a> 
                                        <!-- {/* Link to edit product */} -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No products currently have low stock.</p>
                <?php endif; ?>
            </div>


            <!-- {/* Add more dashboard sections here */} -->


        </div> <!-- End of content-area -->
    </div> <!-- End of admin-container -->

    </div>
    <script src="../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/includes/admin_footer.php';
    ?>

    {/* --- Chart.js Script --- */}
    <script>
        // Get the JSON data passed from PHP
        const salesChartLabels = <?php echo $sales_chart_labels_json; ?>;
        const salesChartData = <?php echo $sales_chart_data_json; ?>;
        const statusChartLabels = <?php echo $status_chart_labels_json; ?>;
        const statusChartData = <?php echo $status_chart_data_json; ?>;

        // --- Sales Line Chart ---
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) { // Check if the canvas element exists
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesChartLabels,
                    datasets: [{
                        label: 'Daily Sales ($)',
                        data: salesChartData,
                        borderColor: '#007bff', // Primary color
                        backgroundColor: 'rgba(0, 123, 255, 0.2)', // Semi-transparent primary color
                        tension: 0.1, // Smooth the line
                        fill: true // Fill area under the line
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Allow controlling size with CSS
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales Amount ($)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: false, // Title is in the H3 above the chart
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }


        // --- Order Status Doughnut Chart ---
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) { // Check if the canvas element exists
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusChartLabels,
                    datasets: [{
                        data: statusChartData,
                        backgroundColor: [
                            '#ffc107', // Warning (e.g., Pending)
                            '#17a2b8', // Info (e.g., Processing)
                            '#007bff', // Primary (e.g., Shipped)
                            '#28a745', // Success (e.g., Delivered, Paid)
                            '#dc3545', // Danger (e.g., Cancelled, Failed)
                            '#6c757d', // Secondary (e.g., Refunded, N/A)
                            '#f8f9fa' // Light (e.g., N/A)
                            // Add more colors if you have more distinct statuses
                        ],
                        borderColor: '#fff', // White border between slices
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right', // Position legend to the right
                        },
                        title: {
                            display: false, // Title is in the H3
                        }
                    }
                }
            });
        }
    </script>

</body>

</html>