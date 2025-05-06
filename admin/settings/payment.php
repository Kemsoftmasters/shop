<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store validation or processing errors
$status_message = ''; // Variable to store general status message
$message_type = '';

// Define the supported payment gateways (key => display name)
$supported_payment_gateways = [
    'paypal' => 'PayPal',
    'stripe' => 'Stripe',
    'bank_transfer' => 'Direct Bank Transfer',
    // Add more gateways here as needed
];

// Array to store current payment gateway settings (key => enabled status 0 or 1)
$payment_settings = [];

// --- Fetch Current Payment Settings ---
if (!empty($supported_payment_gateways)) {
    $keys_to_fetch = [];
    $key_placeholders = [];
    $bind_params = [];
    $bind_types = '';

    foreach ($supported_payment_gateways as $key => $name) {
        $keys_to_fetch[] = 'payment_' . $key . '_enabled'; // e.g., 'payment_paypal_enabled'
        $key_placeholders[] = '?';
        $bind_params[] = 'payment_' . $key . '_enabled';
        $bind_types .= 's';
    }

    if (!empty($keys_to_fetch)) {
        $sql_fetch_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (" . implode(',', $key_placeholders) . ")";
        $stmt_fetch_settings = $conn->prepare($sql_fetch_settings);

        if ($stmt_fetch_settings) {
            $stmt_fetch_settings->bind_param($bind_types, ...$bind_params);
            $execute_fetch_success = $stmt_fetch_settings->execute();

            if ($execute_fetch_success) {
                $result_settings = $stmt_fetch_settings->get_result();
                while ($row = $result_settings->fetch_assoc()) {
                    // Store fetched settings, convert value to integer
                    $payment_settings[$row['setting_key']] = (int)$row['setting_value'];
                }
                $result_settings->free();
            } else {
                // Handle database fetch error
                $errors[] = "Database error fetching payment settings: " . $stmt_fetch_settings->error;
            }
            $stmt_fetch_settings->close();
        } else {
            // Prepare statement error
            $errors[] = "Database error preparing settings fetch statement: " . $conn->error;
        }
    }
}


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) { // Only process POST if no errors during fetch

    $settings_to_update = [];
    $settings_to_insert = [];

    foreach ($supported_payment_gateways as $key => $name) {
        $setting_key = 'payment_' . $key . '_enabled';
        // Get the submitted value (checkbox sends '1' if checked, is not set if not checked)
        $submitted_value = isset($_POST[$setting_key]) ? 1 : 0;

        // Check if the setting already exists in our fetched settings
        if (isset($payment_settings[$setting_key])) {
            // Setting exists, prepare for update if value changed
            if ($payment_settings[$setting_key] !== $submitted_value) {
                 $settings_to_update[$setting_key] = $submitted_value;
            }
        } else {
            // Setting does not exist, prepare for insert
            $settings_to_insert[$setting_key] = $submitted_value;
        }
    }

    // --- Perform Updates ---
    if (!empty($settings_to_update)) {
        $sql_update_settings = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
        $stmt_update_settings = $conn->prepare($sql_update_settings);

        if ($stmt_update_settings) {
            foreach ($settings_to_update as $key => $value) {
                $bind_value = (string)$value; // Bind as string
                $stmt_update_settings->bind_param("ss", $bind_value, $key);
                $stmt_update_settings->execute(); // Execute for each setting
                 // Consider checking execute success and error here
            }
            $stmt_update_settings->close();
             // Refresh fetched settings after update
            // In a real app, you might refetch or update the $payment_settings array here
        } else {
             $errors[] = "Database error preparing update settings: " . $conn->error;
        }
    }

     // --- Perform Inserts ---
     if (!empty($settings_to_insert)) {
         $sql_insert_settings = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
         $stmt_insert_settings = $conn->prepare($sql_insert_settings);

         if ($stmt_insert_settings) {
             foreach ($settings_to_insert as $key => $value) {
                 $bind_value = (string)$value; // Bind as string
                 $stmt_insert_settings->bind_param("ss", $key, $bind_value);
                 $stmt_insert_settings->execute(); // Execute for each setting
                 // Consider checking execute success and error here
             }
             $stmt_insert_settings->close();
              // Refresh fetched settings after insert
             // In a real app, you might refetch or update the $payment_settings array here
         } else {
              $errors[] = "Database error preparing insert settings: " . $conn->error;
         }
     }

    // Set success message if no errors occurred during processing
    if (empty($errors)) {
        $_SESSION['success_message'] = "Payment gateway settings updated successfully!";
        // Redirect to prevent form resubmission
        header("Location: payment.php");
        exit();
    } else {
         // Store errors in session and redirect, or display on current page
         $_SESSION['error_message'] = implode('<br>', $errors);
         header("Location: payment.php");
         exit();
    }
}

// Re-fetch settings after potential POST or for initial display
// This ensures the form shows the correct current state
$payment_settings = [];
if (!empty($supported_payment_gateways)) {
    $keys_to_fetch = [];
    $key_placeholders = [];
    $bind_params = [];
    $bind_types = '';

    foreach ($supported_payment_gateways as $key => $name) {
        $keys_to_fetch[] = 'payment_' . $key . '_enabled';
        $key_placeholders[] = '?';
        $bind_params[] = 'payment_' . $key . '_enabled';
        $bind_types .= 's';
    }

    if (!empty($keys_to_fetch)) {
        $sql_fetch_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (" . implode(',', $key_placeholders) . ")";
        $stmt_fetch_settings = $conn->prepare($sql_fetch_settings);

        if ($stmt_fetch_settings) {
            $stmt_fetch_settings->bind_param($bind_types, ...$bind_params);
            $execute_fetch_success = $stmt_fetch_settings->execute();

            if ($execute_fetch_success) {
                $result_settings = $stmt_fetch_settings->get_result();
                while ($row = $result_settings->fetch_assoc()) {
                    $payment_settings[$row['setting_key']] = (int)$row['setting_value'];
                }
                $result_settings->free();
            } else {
                $errors[] = "Database error re-fetching payment settings: " . $stmt_fetch_settings->error;
            }
            $stmt_fetch_settings->close();
        } else {
            $errors[] = "Database error preparing re-fetch statement: " . $conn->error;
        }
    }
}


// --- Check for status messages from session (after potential redirect) ---
if (isset($_SESSION['success_message'])) {
    $status_message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $status_message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}


// Close the database connection if necessary
// closeDB($conn); // If your db_connect.php has a closeDB function
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateways - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Reuse form styles from previous pages */
         .form-container {
             max-width: 600px;
             margin: 20px auto;
             padding: 25px;
             background-color: #fff;
             border-radius: 8px;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
         }

         .form-container h1 {
             text-align: center;
             margin-top: 0;
             color: #333;
         }

         .form-group {
             margin-bottom: 15px;
         }

         .form-group label {
             display: block;
             margin-bottom: 5px;
             font-weight: bold;
             color: #555;
         }

         /* Specific style for payment gateway checkboxes/toggles */
         .payment-gateway-option {
             display: flex;
             align-items: center;
             justify-content: space-between; /* Space out name and checkbox */
             padding: 10px;
             border: 1px solid #eee;
             margin-bottom: 10px;
             border-radius: 4px;
             background-color: #f9f9f9;
         }

         .payment-gateway-option label {
             margin-bottom: 0; /* Reset label margin */
             font-weight: normal; /* Lighter font weight for method name */
             color: #333;
         }

         .payment-gateway-option input[type="checkbox"] {
             margin-left: 10px; /* Space between name and checkbox */
             transform: scale(1.2); /* Make checkbox a bit larger */
         }


         /* Status/Error Message Styling */
         .success-message { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
         .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }


         .form-container button[type="submit"] {
             display: block;
             width: 100%;
             padding: 10px;
             background-color: #007bff;
             color: white;
             border: none;
             border-radius: 4px;
             font-size: 1.1em;
             cursor: pointer;
             transition: background-color 0.2s ease-in-out;
         }

         .form-container button[type="submit"]:hover {
              background-color: #0056b3;
         }


    </style>
</head>
<body>

    <div class="admin-container">

        <?php
        include __DIR__ . '/../includes/admin_header.php';
         // Pass current page to sidebar for active state highlighting
        $current_page = 'settings_payment'; // Set a unique current page for this sub-menu item
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Payment Gateway Settings</h1>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

             <?php
             // Display database errors during fetch
             if (!empty($errors)) {
                 echo '<div class="error-message"><ul>';
                 foreach ($errors as $error) {
                     echo '<li>' . htmlspecialchars($error) . '</li>';
                 }
                 echo '</ul></div>';
             }
             ?>


            <div class="form-container">

                 <p>Enable or disable the payment gateways available to customers.</p>

                 <form action="" method="POST">
                    <div class="form-group">
                        <label>Available Payment Gateways:</label>

                        <?php foreach ($supported_payment_gateways as $key => $name): ?>
                            <?php
                            // Construct the setting key for this gateway
                            $setting_key = 'payment_' . $key . '_enabled';
                            // Get the current enabled status (default to 0 if not found)
                            $is_enabled = $payment_settings[$setting_key] ?? 0;
                            ?>
                            <div class="payment-gateway-option">
                                <label for="<?php echo htmlspecialchars($setting_key); ?>"><?php echo htmlspecialchars($name); ?></label>
                                <input type="checkbox"
                                       name="<?php echo htmlspecialchars($setting_key); ?>"
                                       id="<?php echo htmlspecialchars($setting_key); ?>"
                                       value="1" {/* Checkboxes typically have a value of 1 when checked */}
                                       <?php if ($is_enabled): echo 'checked'; endif; ?>>
                            </div>
                        <?php endforeach; ?>

                    </div>

                    <button type="submit">Save Settings</button>
                </form>

            </div>  <!-- /.form-container -->

        </div> <!-- /.content-area --> 

    </div> <!-- /.admin-container -->
    <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?> 
    <script src="../../js/admin_script.js"></script>

</body>
</html>