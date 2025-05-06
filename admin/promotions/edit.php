<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store validation or processing errors
$status_message = ''; // Variable to store general status message

// Get promotion ID from the URL
$promotion_id = trim($_GET['id'] ?? '');

// Redirect if no ID is provided or ID is not numeric
if (empty($promotion_id) || !is_numeric($promotion_id)) {
    $_SESSION['error_message'] = "Invalid promotion ID provided.";
    header("Location: index.php");
    exit();
}

$promotion_id = (int)$promotion_id; // Cast to integer

// --- Fetch Existing Promotion Data ---
$sql_fetch = "SELECT
                promotion_id,
                code,
                type,
                value,
                minimum_amount,
                usage_limit,
                uses,
                valid_from,
                valid_until,
                is_active
              FROM promotions WHERE promotion_id = ?";

$stmt_fetch = $conn->prepare($sql_fetch);

$promotion = null;
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $promotion_id);
    $execute_fetch_success = $stmt_fetch->execute();

    if ($execute_fetch_success) {
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows > 0) {
            $promotion = $result_fetch->fetch_assoc();
            $result_fetch->free();

            // Populate form variables with existing data for display
            $code = $promotion['code'];
            $type = $promotion['type'];
            $value = $promotion['value'];
            $minimum_amount = $promotion['minimum_amount'];
            $usage_limit = $promotion['usage_limit'];
            // Format dates for datetime-local input (YYYY-MM-DDTHH:MM)
            $valid_from = $promotion['valid_from'] ? (new DateTime($promotion['valid_from']))->format('Y-m-d\TH:i') : '';
            $valid_until = $promotion['valid_until'] ? (new DateTime($promotion['valid_until']))->format('Y-m-d\TH:i') : '';
            $is_active = $promotion['is_active'];

        } else {
            // Promotion not found
            $_SESSION['error_message'] = "Promotion with ID " . htmlspecialchars($promotion_id) . " not found.";
            header("Location: index.php");
            exit();
        }
    } else {
        // Database fetch error
        $_SESSION['error_message'] = "Database error fetching promotion: " . $stmt_fetch->error;
        header("Location: index.php");
        exit();
    }
    $stmt_fetch->close();
} else {
    // Prepare statement error
    $_SESSION['error_message'] = "Database error preparing fetch statement: " . $conn->error;
    header("Location: index.php");
    exit();
}


// --- Handle Form Submission (Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $promotion !== null) { // Only process POST if promotion was found

    // Get and sanitize form data (same as add.php)
    $code = trim($_POST['code'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $minimum_amount = trim($_POST['minimum_amount'] ?? '0.00');
    $usage_limit = trim($_POST['usage_limit'] ?? '');
    $valid_from = trim($_POST['valid_from'] ?? '');
    $valid_until = trim($_POST['valid_until'] ?? '');
    $is_active = trim($_POST['is_active'] ?? 0);


    // Basic Validation (similar to add.php)
    if (empty($code)) {
        $errors[] = "Promotion code is required.";
    }

    if (empty($type) || !in_array($type, ['percentage', 'fixed'])) {
        $errors[] = "Invalid promotion type selected.";
    }

    if (empty($value) || !is_numeric($value) || (float)$value <= 0) {
        $errors[] = "Valid promotion value is required and must be greater than zero.";
    }

     if (!empty($minimum_amount) && (!is_numeric($minimum_amount) || (float)$minimum_amount < 0)) {
         $errors[] = "Valid minimum amount is required and cannot be negative.";
     }

     if (!empty($usage_limit) && (!is_numeric($usage_limit) || (int)$usage_limit < 0)) { // Allow 0 for limit? Based on table schema, NULL is unlimited
         $errors[] = "Valid usage limit is required and must be a non-negative integer or empty.";
     } elseif ($usage_limit === '') {
         $usage_limit = NULL; // Set to NULL for unlimited if empty
     } else {
         $usage_limit = (int)$usage_limit;
     }

    // Date/Time Validation
    $valid_from_datetime = null;
    if (!empty($valid_from)) {
        try {
            $valid_from_datetime = new DateTime($valid_from);
        } catch (Exception $e) {
            $errors[] = "Invalid 'Valid From' date/time format.";
        }
    }

    $valid_until_datetime = null;
    if (!empty($valid_until)) {
         try {
            $valid_until_datetime = new DateTime($valid_until);
        } catch (Exception $e) {
            $errors[] = "Invalid 'Valid Until' date/time format.";
        }
    }

     if ($valid_from_datetime && $valid_until_datetime && $valid_from_datetime > $valid_until_datetime) {
         $errors[] = "'Valid Until' date/time cannot be before 'Valid From' date/time.";
     }


    // Check for duplicate code, EXCLUDING the current promotion
    if (empty($errors)) {
        $sql_check_duplicate = "SELECT promotion_id FROM promotions WHERE code = ? AND promotion_id != ?";
        $stmt_check_duplicate = $conn->prepare($sql_check_duplicate);
        if ($stmt_check_duplicate) {
            $stmt_check_duplicate->bind_param("si", $code, $promotion_id);
            $stmt_check_duplicate->execute();
            $stmt_check_duplicate->store_result();
            if ($stmt_check_duplicate->num_rows > 0) {
                $errors[] = "A promotion with this code already exists.";
            }
            $stmt_check_duplicate->close();
        } else {
            $errors[] = "Database error checking for duplicate code: " . $conn->error;
        }
    }


    // --- Update Data in Database ---
    if (empty($errors)) {
        // Prepare an UPDATE statement
        $sql_update = "UPDATE promotions SET
                         code = ?,
                         type = ?,
                         value = ?,
                         minimum_amount = ?,
                         usage_limit = ?,
                         valid_from = ?,
                         valid_until = ?,
                         is_active = ?
                       WHERE promotion_id = ?";

        $stmt_update = $conn->prepare($sql_update);

        if ($stmt_update) {
             // Bind parameters (same types as INSERT, plus the ID for the WHERE clause)
             $valid_from_db = $valid_from_datetime ? $valid_from_datetime->format('Y-m-d H:i:s') : NULL;
             $valid_until_db = $valid_until_datetime ? $valid_until_datetime->format('Y-m-d H:i:s') : NULL;

            $stmt_update->bind_param(
                "ssddissii", // s,s,d,d,i,s,s,i,i
                $code,
                $type,
                $value,
                $minimum_amount,
                $usage_limit,
                $valid_from_db,
                $valid_until_db,
                $is_active,
                $promotion_id // Bind the ID for the WHERE clause
            );


            // Execute the statement
            if ($stmt_update->execute()) {
                // Promotion updated successfully
                $_SESSION['success_message'] = "Promotion code '" . htmlspecialchars($code) . "' updated successfully!";
                // Redirect back to the promotions list
                header("Location: index.php");
                exit();
            } else {
                // Handle database update error
                $errors[] = "Database error: " . $stmt_update->error;
            }

            $stmt_update->close(); // Close the statement
        } else {
            // Handle prepare statement error
            $errors[] = "Database error preparing update statement: " . $conn->error;
        }
    }
}

// Close the database connection if necessary
// closeDB($conn); // If your db_connect.php has a closeDB function
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Promotion - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Reuse form styles from add.php */
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
         .form-group input[type="number"],
         .form-group input[type="datetime-local"],
         .form-group select {
             width: 100%;
             padding: 10px;
             border-radius: 4px;
             border: 1px solid #ced4da;
             box-sizing: border-box;
             font-size: 1em;
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

         .back-link {
              display: block;
              text-align: center;
              margin-top: 20px;
              color: #007bff;
              text-decoration: none;
         }
          .back-link:hover {
               text-decoration: underline;
          }

    </style>
</head>
<body>

    <div class="admin-container">

        <?php
        include __DIR__ . '/../includes/admin_header.php';
        $current_page = 'promotions'; // Set the current page variable
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Edit Promotion</h1>

            <div class="form-container">

                <?php
                // Display errors
                if (!empty($errors)) {
                    echo '<div class="error-message"><ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul></div>';
                }
                 // Note: Success messages are typically handled via session and redirection
                ?>

                 <form action="" method="POST">
                     <input type="hidden" name="promotion_id" value="<?php echo htmlspecialchars($promotion_id); ?>">

                    <div class="form-group">
                        <label for="code">Promotion Code:</label>
                        <input type="text" name="code" id="code" value="<?php echo htmlspecialchars($code); ?>" required>
                    </div>

                     <div class="form-group">
                         <label for="type">Discount Type:</label>
                         <select name="type" id="type" required>
                             <option value="percentage" <?php if ($type === 'percentage') echo 'selected'; ?>>Percentage (%)</option>
                             <option value="fixed" <?php if ($type === 'fixed') echo 'selected'; ?>>Fixed Amount ($)</option>
                         </select>
                     </div>

                    <div class="form-group">
                        <label for="value">Discount Value:</label>
                        <input type="number" name="value" id="value" step="0.01" value="<?php echo htmlspecialchars($value); ?>" required min="0.01">
                    </div>

                     <div class="form-group">
                         <label for="minimum_amount">Minimum Order Amount:</label>
                         <input type="number" name="minimum_amount" id="minimum_amount" step="0.01" value="<?php echo htmlspecialchars($minimum_amount); ?>" min="0.00">
                         <small>Enter 0.00 if no minimum is required.</small>
                     </div>

                     <div class="form-group">
                         <label for="usage_limit">Usage Limit (Total):</label>
                         <input type="number" name="usage_limit" id="usage_limit" step="1" value="<?php echo htmlspecialchars($usage_limit); ?>" min="0"> 
                         <small>Leave empty for unlimited usage.</small>
                     </div>

                     <div class="form-group">
                         <label for="valid_from">Valid From:</label>
                         <input type="datetime-local" name="valid_from" id="valid_from" value="<?php echo htmlspecialchars($valid_from); ?>">
                         <small>Optional start date and time.</small>
                     </div>

                     <div class="form-group">
                         <label for="valid_until">Valid Until:</label>
                         <input type="datetime-local" name="valid_until" id="valid_until" value="<?php echo htmlspecialchars($valid_until); ?>">
                         <small>Optional expiry date and time.</small>
                     </div>

                      <div class="form-group">
                          <label for="is_active">Status:</label>
                          <select name="is_active" id="is_active" required>
                              <option value="1" <?php if ((string)$is_active === '1') echo 'selected'; ?>>Active</option>
                              <option value="0" <?php if ((string)$is_active === '0') echo 'selected'; ?>>Inactive</option>
                          </select>
                      </div>


                    <button type="submit">Update Promotion</button>
                </form>

                 <a href="index.php" class="back-link">Back to Promotions List</a>

            </div> <!-- /.form-container -->


        </div> <!-- /.content-area -->
    </div> <!-- /.admin-container -->
    <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?>

    </div> <script src="../../js/admin_script.js"></script>

</body>
</html>