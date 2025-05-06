<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store validation or processing errors
$status_message = ''; // Variable to store general status message

// Initialize form variables with empty strings or defaults
$name = '';
$description = '';
$cost = '0.00'; // Default cost
$is_enabled = 1; // Default to enabled


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost = trim($_POST['cost'] ?? '0.00'); // Use default if empty
    $is_enabled = trim($_POST['is_enabled'] ?? 0);


    // Basic Validation
    if (empty($name)) {
        $errors[] = "Shipping method name is required.";
    }

    if ($cost === '' || !is_numeric($cost) || (float)$cost < 0) { // Allow 0 cost
        $errors[] = "Valid shipping cost is required and cannot be negative.";
    }

     if ($is_enabled === '' || !in_array((int)$is_enabled, [0, 1])) {
         $errors[] = "Invalid status selected for shipping method.";
     } else {
         $is_enabled = (int)$is_enabled; // Ensure it's an integer
     }


    // Check for duplicate name BEFORE inserting
    if (empty($errors)) {
        $sql_check_duplicate = "SELECT method_id FROM shipping_methods WHERE name = ?";
        $stmt_check_duplicate = $conn->prepare($sql_check_duplicate);
        if ($stmt_check_duplicate) {
            $stmt_check_duplicate->bind_param("s", $name);
            $stmt_check_duplicate->execute();
            $stmt_check_duplicate->store_result();
            if ($stmt_check_duplicate->num_rows > 0) {
                $errors[] = "A shipping method with this name already exists.";
            }
            $stmt_check_duplicate->close();
        } else {
            $errors[] = "Database error checking for duplicate name: " . $conn->error;
        }
    }


    // --- Insert Data into Database ---
    if (empty($errors)) {
        // Prepare an INSERT statement
        $sql = "INSERT INTO shipping_methods (name, description, cost, is_enabled)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
             // Bind parameters
             // s: name (string)
             // s: description (string)
             // d: cost (decimal)
             // i: is_enabled (tinyint)

            $stmt->bind_param(
                "ssdi",
                $name,
                $description,
                $cost,
                $is_enabled
            );


            // Execute the statement
            if ($stmt->execute()) {
                // Shipping method added successfully
                $_SESSION['success_message'] = "Shipping method '" . htmlspecialchars($name) . "' added successfully!";
                // Redirect back to the shipping methods list
                header("Location: index.php");
                exit();
            } else {
                // Handle database insertion error
                $errors[] = "Database error: " . $stmt->error;
            }

            $stmt->close(); // Close the statement
        } else {
            // Handle prepare statement error
            $errors[] = "Database error preparing insert statement: " . $conn->error;
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
    <title>Add New Shipping Method - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Reuse form styles from products/add.php and promotions/add.php */
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
             min-height: 80px; /* Adjust height for description */
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
         // Pass current page to sidebar for active state highlighting
        $current_page = 'shipping_methods'; // Set the current page variable
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Add New Shipping Method</h1>

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
                ?>

                 <form action="" method="POST">
                    <div class="form-group">
                        <label for="name">Method Name:</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                     <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea name="description" id="description"><?php echo htmlspecialchars($description); ?></textarea>
                        <small>Optional description for this shipping method.</small>
                    </div>

                    <div class="form-group">
                        <label for="cost">Cost:</label>
                        <input type="number" name="cost" id="cost" step="0.01" value="<?php echo htmlspecialchars($cost); ?>" required min="0.00">
                         <small>Enter 0.00 for free shipping.</small>
                    </div>

                      <div class="form-group">
                          <label for="is_enabled">Status:</label>
                          <select name="is_enabled" id="is_enabled" required>
                              <option value="1" <?php if ((string)$is_enabled === '1') echo 'selected'; ?>>Enabled</option>
                              <option value="0" <?php if ((string)$is_enabled === '0') echo 'selected'; ?>>Disabled</option>
                          </select>
                      </div>


                    <button type="submit">Add Shipping Method</button>
                </form>

                 <a href="index.php" class="back-link">Back to Shipping Methods List</a>

            </div> <!-- /.form-container -->

        </div> <!-- /.content-area -->
    </div> <!-- /.admin-container -->
    <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?> 

    </div> <script src="../../js/admin_script.js"></script>

</body>
</html>