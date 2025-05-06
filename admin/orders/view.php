<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

$order_id = null;
$order = null;
$order_items = [];
$customer = null;
$shipping_address = null;
$billing_address = null;
$error_message = '';
$success_message = '';

// --- Get Order ID from URL and Validate (Handles initial page load) ---
// This block runs when the page is first accessed via a GET request with an ID
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = $_GET['id'];

    // Fetch order data after validation
    // Data fetching logic is moved below the POST handling
} else if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // No valid ID provided on initial GET request
    $_SESSION['error_message'] = "Invalid or missing order ID.";
    header('Location: index.php'); // Redirect back to orders list
    exit();
}


// --- Handle Form Submissions (Update Statuses) ---
// This block runs when a form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determine which form was submitted by checking the submit button name
    if (isset($_POST['update_payment_status'])) {
        // --- Handle Payment Status Update ---
        $order_id_to_update = $_POST['order_id_to_update_payment'];
        $new_payment_status = $_POST['payment_status'];

        // Basic validation for new payment status
        $valid_payment_statuses = ['Pending', 'Paid', 'Refunded', 'Failed']; // Example valid payment statuses - Adjust as needed (case-insensitive check)
        if (!in_array(ucfirst(strtolower($new_payment_status)), $valid_payment_statuses)) { // Validate against capitalized versions
            $error_message = "Invalid payment status value.";
        } else {
            // Use prepared statement to update the payment_status
            $sql_update_payment_status = "UPDATE orders SET payment_status = ? WHERE order_id = ?";
            $stmt_update_payment_status = $conn->prepare($sql_update_payment_status);
            $stmt_update_payment_status->bind_param("si", $new_payment_status, $order_id_to_update);

            if ($stmt_update_payment_status->execute()) {
                $success_message = "Payment status updated to '" . htmlspecialchars($new_payment_status) . "'.";
                // Set order_id for re-fetching data after update
                $order_id = $order_id_to_update;
            } else {
                $error_message = "Error updating payment status: " . $stmt_update_payment_status->error;
            }
            $stmt_update_payment_status->close();
        }
    } elseif (isset($_POST['update_status'])) {
        // --- Handle Delivery Status Update ---
        $order_id_to_update = $_POST['order_id_to_update'];
        $new_delivery_status = $_POST['delivery_status']; // Corrected variable name

        // Basic validation for new delivery status
        $valid_delivery_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Failed']; // Example valid delivery statuses - Adjust as needed (case-insensitive check)
        if (!in_array(ucfirst(strtolower($new_delivery_status)), $valid_delivery_statuses)) { // Validate against capitalized versions
            $error_message = "Invalid delivery status value.";
        } else {
            // Use prepared statement to update the delivery_status
            $sql_update_delivery_status = "UPDATE orders SET delivery_status = ? WHERE order_id = ?";
            $stmt_update_delivery_status = $conn->prepare($sql_update_delivery_status);
            $stmt_update_delivery_status->bind_param("si", $new_delivery_status, $order_id_to_update);

            if ($stmt_update_delivery_status->execute()) {
                $success_message = "Delivery status updated to '" . htmlspecialchars($new_delivery_status) . "'.";
                // Set order_id for re-fetching data after update
                $order_id = $order_id_to_update;
            } else {
                $error_message = "Error updating delivery status: " . $stmt_update_delivery_status->error;
            }
            $stmt_update_delivery_status->close();
        }
    }

    // If a form was submitted and an order_id was set for re-fetching, proceed to re-fetch data
    if ($order_id) {
        // Data re-fetching logic is below
    } else {
        // This might happen if a POST request is made without a valid update button name
        $error_message = "Invalid form submission.";
    }
}


// --- Fetch Order Data (Runs on GET with ID, or after successful POST update) ---
if ($order_id) { // Proceed with fetching only if a valid order_id is available
    $sql_order = "SELECT
                      order_id, user_id, order_date, total_amount, payment_status, delivery_status,
                      shipping_address, billing_address -- Use address IDs
                   FROM orders WHERE order_id = ?";
    $stmt_order = $conn->prepare($sql_order);
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows === 1) {
        $order = $result_order->fetch_assoc();

        // --- Fetch Order Items ---
        $sql_items = "SELECT
                          oi.item_id, oi.product_id, oi.quantity, oi.unit_price, oi.subtotal,
                          p.name AS product_name, p.image_url
                       FROM order_items oi
                       JOIN products p ON oi.product_id = p.product_id
                       WHERE oi.order_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        while ($row_item = $result_items->fetch_assoc()) {
            $order_items[] = $row_item;
        }
        $stmt_items->close();

        // --- Fetch Customer Details (if user_id exists) ---
        if ($order['user_id']) {
            $sql_customer = "SELECT user_id, first_name, email FROM users WHERE user_id = ?"; // Using 'name' based on your user table
            $stmt_customer = $conn->prepare($sql_customer);
            $stmt_customer->bind_param("i", $order['user_id']);
            $stmt_customer->execute();
            $result_customer = $stmt_customer->get_result();
            if ($result_customer->num_rows === 1) {
                $customer = $result_customer->fetch_assoc();
            }
            $stmt_customer->close();
        }

        // --- Fetch Shipping Address (if shipping_address exists) ---
        if ($order['shipping_address']) {
            $sql_shipping = "SELECT address_id, street_address1, street_address2, city, state, postal_code, country FROM user_addresses WHERE address_id = ?";
            $stmt_shipping = $conn->prepare($sql_shipping);
            $stmt_shipping->bind_param("i", $order['shipping_address']);
            $stmt_shipping->execute();
            $result_shipping = $stmt_shipping->get_result();
            if ($result_shipping->num_rows === 1) {
                $shipping_address = $result_shipping->fetch_assoc();
            }
            $stmt_shipping->close();
        }

        // --- Fetch Billing Address (if billing_address exists and is different from shipping) ---
        if ($order['billing_address'] && $order['billing_address'] !== $order['shipping_address']) {
            $sql_billing = "SELECT address_id, street_address1, street_address2, city, state, postal_code, country FROM user_addresses WHERE address_id = ?";
            $stmt_billing = $conn->prepare($sql_billing);
            $stmt_billing->bind_param("i", $order['billing_address']);
            $stmt_billing->execute();
            $result_billing = $stmt_billing->get_result();
            if ($result_billing->num_rows === 1) {
                $billing_address = $result_billing->fetch_assoc();
            }
            $stmt_billing->close();
        } else if ($order['billing_address'] === $order['shipping_address'] && $shipping_address) {
            // Billing is same as shipping, use the fetched shipping address
            $billing_address = $shipping_address;
        }
    } else {
        // Order not found (should be caught by the initial GET check, but good fallback)
        $_SESSION['error_message'] = "Order not found.";
        header('Location: index.php'); // Redirect back to orders list
        exit();
    }
    $stmt_order->close();
}


// Close the database connection if necessary (depends on db_connect.php)
// closeDB($conn);

// --- Check for session status messages ---
// These are messages set by redirects from other pages (like index.php)
$session_status_message = '';
$session_message_type = '';

if (isset($_SESSION['success_message'])) {
    $session_status_message = $_SESSION['success_message'];
    $session_message_type = 'success';
    unset($_SESSION['success_message']); // Clear the message after displaying
} elseif (isset($_SESSION['error_message'])) {
    $session_status_message = $_SESSION['error_message'];
    $session_message_type = 'error';
    unset($_SESSION['error_message']); // Clear the message after displaying
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Basic layout for order details */
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            /* Responsive columns */
            gap: 20px;
            margin-bottom: 20px;
        }

        .order-section {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .order-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .order-section p {
            margin-bottom: 10px;
        }

        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 15px;
        }

        .order-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .order-item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 4px;
        }

        .order-item-details {
            flex-grow: 1;
        }

        .order-item-details p {
            margin: 0 0 5px 0;
            font-size: 0.95em;
        }

        .order-item-details .item-name {
            font-weight: bold;
        }

        .order-status-form {
            margin-top: 10px;
            /* Reduce margin top */
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
        }

        .order-section .order-status-form:first-of-type {
            margin-top: 20px;
            /* Add margin top only to the first form within the section */
        }

        .order-status-form label {
            font-weight: bold;
            margin-right: 10px;
            display: block;
            /* Make label a block element */
            margin-bottom: 5px;
        }

        .order-status-form select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            margin-right: 10px;
            /* Space between select and button */
        }

        .order-status-form button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
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
            background-color: #6c757d;
            color: white;
        }

        /* Grey - For N/A delivery status */
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
            <h1>Order Details (ID: <?php echo htmlspecialchars($order_id ?? 'N/A'); ?>)</h1> <?php if (!empty($session_status_message)): ?>
                <div class="<?php echo $session_message_type; ?>-message">
                    <?php echo htmlspecialchars($session_status_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>


            <?php if ($order): // Only display details if order data was fetched 
            ?>
                <div class="order-details-grid">
                    <div class="order-section">
                        <h3>Order Summary</h3>
                        <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
                        <p><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                        <p><strong>Total Amount:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>

                        <p>
                            <strong>Payment Status:</strong>
                            <?php
                            $payment_status_class = 'status-badge status-' . strtolower($order['payment_status']);
                            echo "<span class='" . $payment_status_class . "'>" . htmlspecialchars($order['payment_status'] ?: 'N/A'); ?></span>
                        </p>

                        <p>
                            <strong>Delivery Status:</strong>
                            <?php
                            $delivery_status_value = $order['delivery_status'] ?: 'N/A'; // Display N/A if NULL
                            $delivery_status_class = 'status-badge status-' . strtolower($order['delivery_status'] ?: 'n-a'); // Use 'n-a' for styling NULL
                            echo "<span class='" . $delivery_status_class . "'>" . htmlspecialchars($delivery_status_value); ?></span>
                        </p>


                        <div class="order-status-form">
                            <form action="" method="POST">
                                <input type="hidden" name="order_id_to_update_payment" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                <label for="payment_status">Update Payment Status:</label>
                                <select name="payment_status" id="payment_status">
                                    <option value="Pending" <?php if (strtolower($order['payment_status']) === 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Paid" <?php if (strtolower($order['payment_status']) === 'paid') echo 'selected'; ?>>Paid</option>
                                    <option value="Refunded" <?php if (strtolower($order['payment_status']) === 'refunded') echo 'selected'; ?>>Refunded</option>
                                    <option value="Failed" <?php if (strtolower($order['payment_status']) === 'failed') echo 'selected'; ?>>Failed</option>
                                </select>
                                <button type="submit" name="update_payment_status" class="button">Update Payment</button>
                            </form>
                        </div>


                        <div class="order-status-form">
                            <form action="" method="POST">
                                <input type="hidden" name="order_id_to_update" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                <label for="delivery_status">Update Delivery Status:</label>
                                <select name="delivery_status" id="delivery_status">
                                    <option value="Pending" <?php if (strtolower($order['delivery_status']) === 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Processing" <?php if (strtolower($order['delivery_status']) === 'processing') echo 'selected'; ?>>Processing</option>
                                    <option value="Shipped" <?php if (strtolower($order['delivery_status']) === 'shipped') echo 'selected'; ?>>Shipped</option>
                                    <option value="Delivered" <?php if (strtolower($order['delivery_status']) === 'delivered') echo 'selected'; ?>>Delivered</option>
                                    <option value="Cancelled" <?php if (strtolower($order['delivery_status']) === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                    <option value="Failed" <?php if (strtolower($order['delivery_status']) === 'failed') echo 'selected'; ?>>Failed</option>
                                </select>
                                <button type="submit" name="update_status" class="button">Update Delivery</button>
                            </form>
                        </div>


                    </div>

                    <div class="order-section">
                        <h3>Customer Information</h3>
                        <?php if ($customer): ?>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['first_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                            <p><a href="../users/view.php?id=<?php echo htmlspecialchars($customer['user_id']); ?>">View Customer Profile</a></p>
                        <?php else: ?>
                            <p>Guest Customer</p>
                        <?php endif; ?>
                    </div>

                    <div class="order-section">
                        <h3>Shipping Address</h3>
                        <?php if ($shipping_address): ?>
                            <p><?php echo htmlspecialchars($shipping_address['street_address1']); ?></p>
                            <?php if (!empty($shipping_address['street_address2'])): ?>
                                <p><?php echo htmlspecialchars($shipping_address['street_address2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($shipping_address['city']); ?>, <?php echo htmlspecialchars($shipping_address['state']); ?> <?php echo htmlspecialchars($shipping_address['postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($shipping_address['country']); ?></p>
                        <?php else: ?>
                            <p>Shipping address not available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="order-section">
                        <h3>Billing Address</h3>
                        <?php if ($billing_address): ?>
                            <p><?php echo htmlspecialchars($billing_address['street_address1']); ?></p>
                            <?php if (!empty($billing_address['street_address2'])): ?>
                                <p><?php echo htmlspecialchars($billing_address['street_address2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($billing_address['city']); ?>, <?php echo htmlspecialchars($billing_address['state']); ?> <?php echo htmlspecialchars($billing_address['postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($billing_address['country']); ?></p>
                        <?php elseif ($order['billing_address'] === $order['shipping_address'] && $shipping_address): ?>
                            <p>Same as shipping address.</p>
                        <?php else: ?>
                            <p>Billing address not available.</p>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="order-section">
                    <h3>Order Items</h3>
                    <?php if (count($order_items) > 0): ?>
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <?php if ($item['image_url']): ?>
                                    <img src="../../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                <?php endif; ?>
                                <div class="order-item-details">
                                    <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                    <p>Price per item: $<?php echo htmlspecialchars(number_format($item['unit_price'], 2)); ?></p>
                                    <p>Subtotal: $<?php echo htmlspecialchars(number_format($item['subtotal'], 2)); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No items found for this order.</p>
                    <?php endif; ?>
                </div>


            <?php else: ?>
                <p>Order data could not be loaded.</p>
            <?php endif; ?>


            <p><a href="index.php" class="button button-secondary">Back to Orders List</a></p>


        </div>

    </div> <!-- /.admin-container -->

    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>