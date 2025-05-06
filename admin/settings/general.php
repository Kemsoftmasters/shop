<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store validation or processing errors
$status_message = ''; // Variable to store general status message
$message_type = '';

// Define the general settings fields to manage (key => details)
$general_settings_fields = [
    'site_name' => [
        'label' => 'Shop Name',
        'type' => 'text',
        'required' => true,
        'description' => 'The name of your online shop.'
    ],
    'site_description' => [
        'label' => 'Shop Description',
        'type' => 'textarea',
        'required' => false,
        'description' => 'A short description of your shop.'
    ],
    'contact_email' => [
        'label' => 'Contact Email',
        'type' => 'email', // Use type 'email' for validation
        'required' => false,
        'description' => 'The primary email address for customer inquiries.'
    ],
    'phone_number' => [
        'label' => 'Phone Number',
        'type' => 'text', // Can be text to allow various formats
        'required' => false,
        'description' => 'The contact phone number.'
    ],
    'shop_currency' => [
        'label' => 'Currency',
        'type' => 'select', // Or 'text' if allowing any currency symbol
        'options' => ['USD' => '$ USD', 'EUR' => '€ EUR', 'GBP' => '£ GBP', 'KES' => 'KES'], // Example options
        'required' => true,
        'description' => 'The main currency used in the shop.'
    ],
    // Add more general settings here as needed:
    // 'items_per_page' => ['label' => 'Items Per Page', 'type' => 'number', 'required' => true, 'description' => 'Number of products/items to display per page.', 'min' => 1],
];

// Array to store current setting values (key => value)
$settings_values = [];

// --- Fetch Current General Settings ---
if (!empty($general_settings_fields)) {
    $keys_to_fetch = array_keys($general_settings_fields); // Get all keys from the array
    $key_placeholders = implode(',', array_fill(0, count($keys_to_fetch), '?')); // Create placeholders like ?,?,?,...
    $bind_types = str_repeat('s', count($keys_to_fetch)); // All keys are strings
    $bind_params = $keys_to_fetch; // Bind the keys themselves

    $sql_fetch_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (" . $key_placeholders . ")";
    $stmt_fetch_settings = $conn->prepare($sql_fetch_settings);

    if ($stmt_fetch_settings) {
        $stmt_fetch_settings->bind_param($bind_types, ...$bind_params);
        $execute_fetch_success = $stmt_fetch_settings->execute();

        if ($execute_fetch_success) {
            $result_settings = $stmt_fetch_settings->get_result();
            while ($row = $result_settings->fetch_assoc()) {
                // Store fetched setting values
                $settings_values[$row['setting_key']] = $row['setting_value'];
            }
            $result_settings->free();
        } else {
            // Handle database fetch error
            $errors[] = "Database error fetching general settings: " . $stmt_fetch_settings->error;
        }
        $stmt_fetch_settings->close();
    } else {
        // Prepare statement error
        $errors[] = "Database error preparing settings fetch statement: " . $conn->error;
    }
}


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)) { // Only process POST if no errors during fetch

    $settings_to_update = [];
    $settings_to_insert = [];

    foreach ($general_settings_fields as $key => $details) {
        $submitted_value = trim($_POST[$key] ?? ''); // Get submitted value by key

        // --- Validation based on field type and requirements ---
        if ($details['required'] && empty($submitted_value)) {
            $errors[] = htmlspecialchars($details['label']) . " is required.";
            continue; // Skip to next setting if required and empty
        }

        if (!empty($submitted_value)) { // Validate only if a value is submitted (and not required/empty)
            switch ($details['type']) {
                case 'email':
                    if (!filter_var($submitted_value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = htmlspecialchars($details['label']) . " must be a valid email address.";
                    }
                    break;
                case 'number':
                    if (!is_numeric($submitted_value)) {
                         $errors[] = htmlspecialchars($details['label']) . " must be a valid number.";
                     } elseif (isset($details['min']) && (float)$submitted_value < $details['min']) {
                          $errors[] = htmlspecialchars($details['label']) . " must be at least " . $details['min'] . ".";
                     }
                    // Add more number validations (max, etc.) if needed
                    break;
                 case 'select':
                      if (!isset($details['options'][$submitted_value])) {
                           // Check if the selected option is valid IF options are defined
                           // Note: For simple select, browser usually handles this, but server-side check is safer
                      }
                      break;
                // Add validation for other types if needed (e.g., url, date)
            }
        }
         // End Validation

        // Store the submitted value (even if empty for optional fields)
        // We'll decide whether to update or insert later
        $settings_values[$key] = $submitted_value; // Update the in-memory array with submitted values


        // Check if the setting already exists in the database
        // (Based on the $settings_values fetched initially)
        // We are now using the $settings_values array updated with POST data
        // A better check here is to see if the setting_key exists in the initial fetch result
        // Let's refactor slightly to use the initial fetch result for existence check

    }


     // Re-evaluate which settings to update/insert based on initial fetch vs submitted values
     $settings_to_update = [];
     $settings_to_insert = [];

     foreach ($general_settings_fields as $key => $details) {
         $submitted_value = $settings_values[$key] ?? ''; // Get value from array updated with POST

         // Check if this setting key was present in the initial database fetch
         $setting_exists_in_db = array_key_exists($key, $settings_values); // This check needs to be against the state *before* POST

         // To do this correctly, we need the values fetched *before* POST in a separate array
         // Let's refetch settings after handling POST to get the true current state
         // Alternatively, store initial fetch result in a separate variable


         // For simplicity in this code block, let's assume $settings_values holds the state *before* POST for this check
         // A more robust approach would separate pre-POST and post-POST data

         $sql_check_exists = "SELECT 1 FROM settings WHERE setting_key = ?";
         $stmt_check_exists = $conn->prepare($sql_check_exists);
         $setting_exists_in_db = false;
         if ($stmt_check_exists) {
             $stmt_check_exists->bind_param("s", $key);
             $stmt_check_exists->execute();
             $stmt_check_exists->store_result();
             if ($stmt_check_exists->num_rows > 0) {
                 $setting_exists_in_db = true;
             }
             $stmt_check_exists->close();
         }


         if (empty($errors)) { // Only proceed if no validation errors

             if ($setting_exists_in_db) {
                 // Setting exists, check if value changed before adding to update list
                 // This comparison needs the *original* value from the DB vs the submitted value
                 // Let's assume $settings_values initially holds DB values
                 // A more precise check would involve fetching the original value again if not stored separately
                  $original_db_value = null;
                  // You would need to fetch the original value for $key here if not stored in a separate array

                  // For this simplified example, we'll just add to update if it exists, assuming validation caught issues
                   $settings_to_update[$key] = $submitted_value;

             } else {
                 // Setting does not exist, prepare for insert
                 $settings_to_insert[$key] = $submitted_value;
             }
         }

     }


    // --- Perform Updates ---
    if (!empty($settings_to_update) && empty($errors)) {
        // $sql_update_settings = "UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
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
        } else {
             $errors[] = "Database error preparing update settings: " . $conn->error;
        }
    }

     // --- Perform Inserts ---
     if (!empty($settings_to_insert) && empty($errors)) {
         $sql_insert_settings = "INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?)"; // Assuming you want to set setting_type
         $stmt_insert_settings = $conn->prepare($sql_insert_settings);

         if ($stmt_insert_settings) {
             foreach ($settings_to_insert as $key => $value) {
                 $bind_value = (string)$value; // Bind as string
                  // You might set a default type for inserted settings here, or get it from the $general_settings_fields array
                  $setting_type_to_bind = $general_settings_fields[$key]['type'] ?? 'text'; // Use defined type or default
                 $stmt_insert_settings->bind_param("sss", $key, $bind_value, $setting_type_to_bind);
                 $stmt_insert_settings->execute(); // Execute for each setting
                 // Consider checking execute success and error here
             }
             $stmt_insert_settings->close();
         } else {
              $errors[] = "Database error preparing insert settings: " . $conn->error;
         }
     }


    // Set success message if no errors occurred during processing
    if (empty($errors)) {
        $_SESSION['success_message'] = "General settings updated successfully!";
        // Redirect to prevent form resubmission
        header("Location: general.php");
        exit();
    } else {
         // Store errors in session and redirect, or display on current page
         $_SESSION['error_message'] = "Error saving settings: " . implode('<br>', $errors);
         header("Location: general.php");
         exit();
    }
}

// Re-fetch settings after potential POST or for initial display
// This ensures the form shows the correct current state, including POSTed values if there were errors
$settings_values = []; // Reset and refetch
if (!empty($general_settings_fields)) {
    $keys_to_fetch = array_keys($general_settings_fields);
    $key_placeholders = implode(',', array_fill(0, count($keys_to_fetch), '?'));
    $bind_types = str_repeat('s', count($keys_to_fetch));
    $bind_params = $keys_to_fetch;

    $sql_fetch_settings = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (" . $key_placeholders . ")";
    $stmt_fetch_settings = $conn->prepare($sql_fetch_settings);

    if ($stmt_fetch_settings) {
        $stmt_fetch_settings->bind_param($bind_types, ...$bind_params);
        $execute_fetch_success = $stmt_fetch_settings->execute();

        if ($execute_fetch_success) {
            $result_settings = $stmt_fetch_settings->get_result();
            while ($row = $result_settings->fetch_assoc()) {
                $settings_values[$row['setting_key']] = $row['setting_value'];
            }
            $result_settings->free();
        } else {
            $errors[] = "Database error re-fetching general settings: " . $stmt_fetch_settings->error;
        }
        $stmt_fetch_settings->close();
    } else {
        $errors[] = "Database error preparing re-fetch statement: " . $conn->error;
    }
}

// If there were POST errors, overwrite the fetched values with POSTed values for redisplay
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($errors)) {
     foreach ($general_settings_fields as $key => $details) {
         $settings_values[$key] = $_POST[$key] ?? '';
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
    <title>General Settings - Kemsoft Shop Admin</title>
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

         .form-group input[type="text"],
         .form-group input[type="email"],
         .form-group input[type="number"],
         .form-group textarea,
         .form-group select {
             width: 100%;
             padding: 10px;
             border-radius: 4px;
             border: 1px solid #ced4da;
             box-sizing: border-box;
             font-size: 1em;
         }

         .form-group textarea {
             resize: vertical;
             min-height: 100px; /* Adjust height */
         }

         .form-group small {
              display: block;
              margin-top: 5px;
              color: #6c757d;
         }


          /* Specific style for number inputs if needed */
          .form-group input[type="number"] {
               appearance: textfield; /* Standard property for compatibility */
               -moz-appearance: textfield; /* Hide arrows in Firefox */
          }
          .form-group input[type="number"]::-webkit-outer-spin-button,
          .form-group input[type="number"]::-webkit-inner-spin-button {
              -webkit-appearance: none; /* Hide arrows in Chrome/Safari */
              margin: 0;
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
        $current_page = 'settings_general'; // Set a unique current page for this sub-menu item
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>General Settings</h1>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

             <?php
             // Display database errors during fetch or processing
             if (!empty($errors)) {
                 echo '<div class="error-message"><ul>';
                 foreach ($errors as $error) {
                     echo '<li>' . htmlspecialchars($error) . '</li>';
                 }
                 echo '</ul></div>';
             }
             ?>

            <div class="form-container">

                 <form action="" method="POST">

                    <?php foreach ($general_settings_fields as $key => $details): ?>
                        <div class="form-group">
                            <label for="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($details['label']); ?>:</label>

                            <?php
                            // Get the current value for this setting (either from DB fetch or POST on error)
                            $current_value = htmlspecialchars($settings_values[$key] ?? '');
                            ?>

                            <?php if ($details['type'] === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($key); ?>"
                                          id="<?php echo htmlspecialchars($key); ?>"
                                          <?php if ($details['required']) echo 'required'; ?>><?php echo $current_value; ?></textarea>

                            <?php elseif ($details['type'] === 'select' && isset($details['options'])): ?>
                                <select name="<?php echo htmlspecialchars($key); ?>"
                                        id="<?php echo htmlspecialchars($key); ?>"
                                        <?php if ($details['required']) echo 'required'; ?>>
                                    <?php foreach ($details['options'] as $option_value => $option_label): ?>
                                        <option value="<?php echo htmlspecialchars($option_value); ?>"
                                                <?php if ((string)$current_value === (string)$option_value) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($option_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php else: // Default to text or number input ?>
                                <input type="<?php echo htmlspecialchars($details['type']); ?>"
                                       name="<?php echo htmlspecialchars($key); ?>"
                                       id="<?php echo htmlspecialchars($key); ?>"
                                       value="<?php echo $current_value; ?>"
                                       <?php if ($details['required']) echo 'required'; ?>
                                       <?php if (isset($details['min'])) echo 'min="' . htmlspecialchars($details['min']) . '"'; ?>
                                       {/* Add max, step etc. attributes if needed based on type */}
                                >
                            <?php endif; ?>

                            <?php if (!empty($details['description'])): ?>
                                <small><?php echo htmlspecialchars($details['description']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>


                    <button type="submit">Save Settings</button>
                </form>

            </div>  <!-- End of form-container -->
        </div> <!-- End of content-area -->

        </div> <!-- End of admin-container -->
        <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?>

    </div> <script src="../../js/admin_script.js"></script>

</body>
</html>