<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$settings = [];
$error_message = '';
$success_message = '';

// --- Handle POST Request (Saving Settings) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if the settings array is present in the POST data
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {

        // Start a transaction to ensure all updates are atomic
        $conn->begin_transaction();
        $update_success = true;
        $update_errors = []; // Collect errors during update

        // Iterate through the submitted settings array
        foreach ($_POST['settings'] as $key => $value) {
            // Validate the key (ensure it's a string and potentially matches expected keys)
            // For simplicity, we assume keys from the form are valid database keys.
            // A more robust approach would fetch allowed keys from the DB first.

            // Sanitize the value based on potential type (basic sanitization)
            // More specific validation/sanitization based on setting_type is recommended
            $sanitized_value = trim($value);

            // Handle checkbox values specifically (will only be in $_POST if checked)
            // If a checkbox setting key is NOT in $_POST['settings'], it means it was unchecked.
            // We'll handle this after the loop by comparing with all fetched settings.

            // For now, just prepare for the update
            $sql_update_setting = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            $stmt_update_setting = $conn->prepare($sql_update_setting);

            if ($stmt_update_setting) {
                $stmt_update_setting->bind_param("ss", $sanitized_value, $key);

                if (!$stmt_update_setting->execute()) {
                    $update_success = false;
                    $update_errors[] = "Error updating setting '" . htmlspecialchars($key) . "': " . $stmt_update_setting->error;
                    // Continue loop to find other potential errors, but mark as failed
                }
                $stmt_update_setting->close(); // Close statement after execution
            } else {
                $update_success = false;
                $update_errors[] = "Error preparing update statement for setting '" . htmlspecialchars($key) . "': " . $conn->error;
                // Continue loop to find other potential errors
            }
        }

        // --- Handle Checkboxes (Explicitly set unchecked ones to '0' or false) ---
        // Fetch all settings again to determine which ones are checkboxes and were not submitted
        $sql_fetch_all_settings = "SELECT setting_key, setting_type FROM settings";
        $result_all_settings = $conn->query($sql_fetch_all_settings);
        $all_settings_data = [];
        if ($result_all_settings) {
            while ($row = $result_all_settings->fetch_assoc()) {
                $all_settings_data[$row['setting_key']] = $row['setting_type'];
            }
            $result_all_settings->free();
        } else {
            // Handle error fetching all settings
            $update_success = false;
            $update_errors[] = "Error fetching all settings to process checkboxes: " . $conn->error;
        }


        if ($update_success) { // Only process checkboxes if previous updates were okay
            foreach ($all_settings_data as $key => $type) {
                if ($type === 'checkbox') {
                    // If the checkbox was NOT in the submitted 'settings' array, it was unchecked
                    if (!isset($_POST['settings'][$key])) {
                        $sql_uncheck_checkbox = "UPDATE settings SET setting_value = '0' WHERE setting_key = ?";
                        $stmt_uncheck_checkbox = $conn->prepare($sql_uncheck_checkbox);
                        if ($stmt_uncheck_checkbox) {
                            $stmt_uncheck_checkbox->bind_param("s", $key);
                            if (!$stmt_uncheck_checkbox->execute()) {
                                $update_success = false;
                                $update_errors[] = "Error unchecking setting '" . htmlspecialchars($key) . "': " . $stmt_uncheck_checkbox->error;
                            }
                            $stmt_uncheck_checkbox->close();
                        } else {
                            $update_success = false;
                            $update_errors[] = "Error preparing uncheck statement for setting '" . htmlspecialchars($key) . "': " . $conn->error;
                        }
                    }
                }
            }
        }


        if ($update_success && empty($update_errors)) {
            $conn->commit(); // Commit the transaction if all successful
            $_SESSION['success_message'] = "Settings updated successfully!";
            header("Location: index.php"); // Redirect to refresh
            exit();
        } else {
            $conn->rollback(); // Rollback the transaction on error
            $error_message = "Please correct the following errors:<br>" . implode("<br>", $update_errors);
            $_SESSION['error_message'] = $error_message;
            header("Location: index.php"); // Redirect to show errors
            exit();
        }
    } else {
        $error_message = "No settings data submitted.";
        $_SESSION['error_message'] = $error_message;
        header("Location: index.php"); // Redirect
        exit();
    }
}


// --- Handle GET Request (Displaying the Form) ---
// Fetch all settings from the database, including type
$sql_fetch_settings = "SELECT setting_key, setting_value, description, setting_type FROM settings ORDER BY setting_key ASC";
$result_settings = $conn->query($sql_fetch_settings);

if ($result_settings) {
    if ($result_settings->num_rows > 0) {
        while ($row = $result_settings->fetch_assoc()) {
            $settings[] = $row;
        }
        $result_settings->free();
    } else {
        // No settings found in the database
        $error_message = "No settings found in the database. Please add some settings.";
    }
} else {
    // Database query failed
    $error_message = "Database error fetching settings: " . $conn->error;
}


// Close the database connection if necessary
// closeDB($conn);


// --- Check for session status messages ---
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

// Prioritize internal messages if they exist after session check
if (!empty($error_message)) {
    $session_status_message = $error_message;
    $session_message_type = 'error';
}


// --- HTML Structure ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Kemsoft Shop Admin</title>
    <!-- Link to your refined admin CSS -->
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Specific styles for the Settings page */
        .settings-form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            /* Adjust width as needed */
            margin: 20px auto;
            /* Center the container */
        }

        .settings-form-container h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #343a40;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        /* Style for individual setting rows */
        .setting-item {
            margin-bottom: 20px;
            /* Space between setting rows */
            padding-bottom: 15px;
            border-bottom: 1px dashed #eee;
            /* Separator */
            display: flex;
            /* Use flexbox for alignment */
            align-items: flex-start;
            /* Align items to the top */
            flex-wrap: wrap;
            /* Allow wrapping on small screens */
            gap: 15px;
            /* Space between label/description and input */
        }

        .setting-item:last-child {
            border-bottom: none;
            /* No border on the last item */
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .setting-item label {
            font-weight: bold;
            color: #555;
            flex-basis: 200px;
            /* Fixed width for label/description area */
            flex-shrink: 0;
            /* Prevent shrinking */
        }

        .setting-item .setting-description {
            font-size: 0.9em;
            color: #777;
            margin-top: 5px;
        }

        .setting-item input[type="text"],
        .setting-item input[type="email"],
        .setting-item input[type="number"],
        .setting-item textarea,
        .setting-item select {
            /* Added select */
            flex-grow: 1;
            /* Allow input to take remaining space */
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
            max-width: 100%;
            /* Prevent overflow */
        }

        .setting-item textarea {
            min-height: 80px;
            /* Adjust height */
            resize: vertical;
        }

        .setting-item input[type="checkbox"] {
            margin-top: 10px;
            /* Align checkbox better */
            margin-right: 5px;
        }

        .setting-item .checkbox-container {
            /* Container for checkbox and label */
            display: flex;
            align-items: center;
        }

        .setting-item .checkbox-container label {
            font-weight: normal;
            /* Less bold for checkbox label */
            color: #333;
            margin-left: 5px;
            min-width: auto;
            /* Remove fixed width */
            text-align: left;
        }



        .settings-form-container .button-group {
            margin-top: 25px;
            /* Space above the save button */
        }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .setting-item {
                flex-direction: column;
                /* Stack items vertically */
                align-items: stretch;
                /* Stretch items to full width */
                gap: 5px;
                /* Smaller gap when stacked */
            }

            .setting-item label {
                flex-basis: auto;
                /* Remove fixed width */
                text-align: left;
            }

            .setting-item .checkbox-container {
                justify-content: flex-start;
                /* Align checkbox to the left */
            }

            .setting-item input[type="checkbox"] {
                margin-top: 0;
                /* Adjust margin */
            }

            .setting-item .setting-description {
                margin-top: 0;
            }

        }
    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        include __DIR__ . '/../includes/admin_header.php';
        // Pass current page to sidebar for active state highlighting
        $current_page = 'settings'; // Set a variable for the current page
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>General Settings</h1>

            <!-- Display status messages -->
            <?php if (!empty($session_status_message)): ?>
                <div class="<?php echo $session_status_message === $error_message ? 'error-message' : $session_message_type . '-message'; ?>"> 
                    <?php echo $session_status_message; // HTML is allowed here for line breaks 
                    ?>
                </div>
            <?php endif; ?>

            <div class="settings-form-container">
                <h2>Edit Settings</h2>
                <?php if (!empty($settings)): ?>
                    <form action="" method="POST">
                        <?php foreach ($settings as $setting): ?>
                            <div class="setting-item">
                                <div> 
                                    <label for="<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))); ?> 
                                    </label>
                                    <?php if (!empty($setting['description'])): ?>
                                        <p class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <?php
                                // Dynamically render input type based on setting_type
                                $setting_key = htmlspecialchars($setting['setting_key']);
                                $setting_value = htmlspecialchars($setting['setting_value'] ?? '');
                                $setting_type = strtolower($setting['setting_type'] ?? 'text'); // Default to text

                                switch ($setting_type) {
                                    case 'textarea':
                                        echo "<textarea name='settings[{$setting_key}]' id='{$setting_key}'>{$setting_value}</textarea>";
                                        break;
                                    case 'number':
                                        echo "<input type='number' name='settings[{$setting_key}]' id='{$setting_key}' value='{$setting_value}' >"; // Add min/max if needed
                                        break;
                                    case 'checkbox':
                                        // Checkboxes only send value if checked. Handle unchecking explicitly in POST.
                                        $checked = ($setting_value === '1' || strtolower($setting_value) === 'true') ? 'checked' : '';
                                        echo "<div class='checkbox-container'><input type='checkbox' name='settings[{$setting_key}]' id='{$setting_key}' value='1' {$checked}> <label for='{$setting_key}'>Enable</label></div>";
                                        break;
                                    // Add more types here (e.g., 'email', 'date', 'select')
                                    case 'email':
                                        echo "<input type='email' name='settings[{$setting_key}]' id='{$setting_key}' value='{$setting_value}' >";
                                        break;
                                    case 'select':
                                        // For select, you'd need options. Options could be stored in 'description' (e.g., JSON) or a new column.
                                        // Example assuming options are in description as JSON: {"option1":"Label 1", "option2":"Label 2"}
                                        $options = json_decode($setting['description'] ?? '[]', true);
                                        if (is_array($options) && !empty($options)) {
                                            echo "<select name='settings[{$setting_key}]' id='{$setting_key}'>";
                                            foreach ($options as $opt_value => $opt_label) {
                                                $selected = ($setting_value === $opt_value) ? 'selected' : '';
                                                echo "<option value='{$opt_value}' {$selected}>{$opt_label}</option>";
                                            }
                                            echo "</select>";
                                        } else {
                                            echo "<input type='text' name='settings[{$setting_key}]' id='{$setting_key}' value='{$setting_value}' >"; // Fallback to text if options missing
                                            echo "<p class='setting-description'>Error: Select options not defined.</p>";
                                        }
                                        break;

                                    case 'text': // Default case
                                    default:
                                        echo "<input type='text' name='settings[{$setting_key}]' id='{$setting_key}' value='{$setting_value}' >";
                                        break;
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="button-group">
                            <button type="submit" name="submit_settings" value="1" class="button">Save Settings</button>
                            <a href="index.php" class="button button-secondary">Cancel</a> 
                        </div>
                    </form>
                <?php else: ?>
                    <p><?php echo htmlspecialchars($session_status_message); ?></p> 
                <?php endif; ?>
            </div>


        </div> <!-- /.content-area -->

    </div> <!-- /.admin-container -->
    <!-- Link to your admin-specific JS -->
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>