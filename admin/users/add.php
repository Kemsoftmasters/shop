<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

// Initialize form variables with empty strings or defaults
$first_name = ''; // Renamed from $name
$last_name = ''; // Added last_name
$email = '';
$phone_no = ''; // Added phone_no
$status = 'active'; // Added status, default to 'active' for new user
$address = ''; // Added address
$password = '';
$confirm_password = '';

$errors = []; // Renamed from $form_errors for consistency
$status_message = '';
$message_type = '';


// --- Handle POST Request (Form Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get and sanitize form data
    $first_name = trim($_POST['first_name'] ?? ''); // Use first_name
    $last_name = trim($_POST['last_name'] ?? ''); // Added last_name
    $email = trim($_POST['email'] ?? '');
    $phone_no = trim($_POST['phone_no'] ?? ''); // Added phone_no
    $status = trim($_POST['status'] ?? 'active'); // Added status, default 'active' if not posted
    $address = trim($_POST['address'] ?? ''); // Added address
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    // Validate First Name
    if (empty($first_name)) {
        $errors[] = "First Name cannot be empty.";
    }

    // Validate Last Name (Optional, adjust required status based on needs)
    // if (empty($last_name)) { $errors[] = "Last Name cannot be empty."; }

    // Validate Email
    if (empty($email)) {
        $errors[] = "Email cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists in the database
        $sql_check_email = "SELECT user_id FROM users WHERE email = ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $errors[] = "Email already exists.";
            }
            $stmt_check_email->close();
        } else {
            $errors[] = "Database error checking email uniqueness: " . $conn->error;
        }
    }

     // Validate Phone Number (Optional, add format validation if needed)
     // if (!empty($phone_no) && !preg_match('/^\d{10}$/', $phone_no)) { $errors[] = "Invalid phone number format."; }


     // Validate Status (Must be one of the ENUM values)
    $allowed_statuses = ['active', 'inactive', 'banned'];
    if (empty($status)) {
        // Decide if status is required. Often it is.
        // $errors[] = "Status is required.";
    } elseif (!in_array($status, $allowed_statuses)) {
         $errors[] = "Invalid status selected.";
    }

     // Validate Address (Optional)
     // if (empty($address)) { $errors[] = "Address is required."; }


    // Validate Password
    if (empty($password)) {
        $errors[] = "Password cannot be empty.";
    } elseif (strlen($password) < 6) { // Example: Minimum password length
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Password and confirm password do not match.";
    }
    // You might want to add more password strength checks here

    // --- Process if no validation errors ---
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Get current timestamp for created_at and updated_at
        $current_timestamp = date('Y-m-d H:i:s');


        // Insert the new user into the database
        // Adjust column names to match your users table exactly
        $sql_insert_user = "INSERT INTO users (first_name, last_name, email, phone_no, status, address, password, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Added columns

        $stmt_insert_user = $conn->prepare($sql_insert_user);

        if ($stmt_insert_user) {
             // Bind parameters
             // s: first_name
             // s: last_name
             // s: email
             // s: phone_no
             // s: status
             // s: address
             // s: password (hashed)
             // s: created_at
             // s: updated_at
             $stmt_insert_user->bind_param(
                 "sssssssss", // 9 's' types
                 $first_name,
                 $last_name,
                 $email,
                 $phone_no,
                 $status,
                 $address,
                 $hashed_password, // Bind the hashed password
                 $current_timestamp,
                 $current_timestamp
             );


            if ($stmt_insert_user->execute()) {
                $new_user_id = $stmt_insert_user->insert_id; // Get the ID of the newly inserted user
                $_SESSION['success_message'] = "New user created successfully!";
                // Redirect to the users list or the view page of the new user
                 // Redirect to index.php for simplicity, or view.php if you create it
                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Error creating user: " . $stmt_insert_user->error;
            }
            $stmt_insert_user->close();
        } else {
            $errors[] = "Database error preparing insert statement: " . $conn->error;
        }
    } else {
        // If there are form errors, set status message for display
         $status_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
         $message_type = 'error';
        // Keep the submitted values to repopulate the form (password fields are cleared below)
    }
}


// --- Handle GET Request (Displaying the Form) ---
// This runs when the page is first accessed via GET or after a failed POST
// If there were form errors, the variables ($first_name, $last_name, etc.) are already set from $_POST

// Clear password fields on GET or error display for security
$password = '';
$confirm_password = '';

// Close the database connection if necessary
// closeDB($conn);


// --- Check for status messages from session ---
// Prioritize internal messages ($status_message) over session messages
if (empty($status_message)) { // Only use session message if no internal message is set
    if (isset($_SESSION['success_message'])) {
        $status_message = $_SESSION['success_message'];
        $message_type = 'success';
        unset($_SESSION['success_message']); // Clear the message after displaying
    } elseif (isset($_SESSION['error_message'])) {
        $status_message = $_SESSION['error_message'];
        $message_type = 'error';
        unset($_SESSION['error_message']); // Clear the message after displaying
    }
}


// --- HTML Structure ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
         /* Specific styles for the add new user form container */
         .add-user-form-container {
             background-color: #fff;
             padding: 20px;
             border-radius: 8px;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
             max-width: 600px; /* Limit form width */
             margin: 20px auto; /* Center the form */
         }

         .add-user-form-container h2 {
             margin-top: 0;
             margin-bottom: 20px;
             color: #343a40;
             border-bottom: 1px solid #eee;
             padding-bottom: 10px;
         }

         /* Reuse general form styles from admin_style.css */
         /* Ensure form-group, input[type=text/email/password], textarea, select, button styles are in admin_style.css */

          /* Small text for descriptions/hints */
         .form-group small {
              display: block;
              margin-top: 5px;
              color: #6c757d;
         }

         /* Status/Error Message Styling (should also be in admin_style.css) */
         .success-message { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
         .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }


          /* Button group styling (should also be in admin_style.css) */
          .button-group {
              display: flex;
              gap: 10px;
              margin-top: 20px;
              justify-content: flex-end; /* Align buttons to the right */
          }

           .button-group .button {
               margin-top: 0; /* Override default button margin */
           }


    </style>
</head>

<body>

    <div class="admin-container">

        <?php
        // --- Include Admin Header and Sidebar ---
        include __DIR__ . '/../includes/admin_header.php';
        // Pass current page to sidebar for active state highlighting
        // Decide if you want to highlight 'users' or a potential 'add-user' link
        $current_page = 'users'; // Or 'add-user' if you create a specific sidebar link
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Add New User</h1>

            <?php if (!empty($status_message)): ?> 
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo $status_message; // HTML is allowed here for line breaks
                    ?>
                </div>
            <?php endif; ?>

            <div class="add-user-form-container">
                <h2>New User Details</h2>
                <form action="" method="POST">

                    <div class="form-group">
                        <label for="first_name">First Name:</label> 
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>  
                    </div>

                     <div class="form-group">
                         <label for="last_name">Last Name:</label> 
                         <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($last_name); ?>">  
                     </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                     <div class="form-group">
                         <label for="phone_no">Phone Number:</label> 
                         <input type="text" name="phone_no" id="phone_no" value="<?php echo htmlspecialchars($phone_no); ?>">  
                     </div>

                     <div class="form-group">
                         <label for="status">Status:</label> 
                         <select name="status" id="status" required> 
                             <option value="active" <?php if (($status ?? '') === 'active') echo 'selected'; ?>>Active</option>
                             <option value="inactive" <?php if (($status ?? '') === 'inactive') echo 'selected'; ?>>Inactive</option>
                             <option value="banned" <?php if (($status ?? '') === 'banned') echo 'selected'; ?>>Banned</option>
                         </select>
                     </div>

                     <div class="form-group">
                         <label for="address">Address:</label> 
                         <textarea name="address" id="address"><?php echo htmlspecialchars($address); ?></textarea> 
                     </div>


                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" name="password" id="password" value="" required> 
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" name="confirm_password" id="confirm_password" value="" required> 
                    </div>

                    <div class="button-group">
                        <button type="submit" class="button">Create User</button>
                        <a href="index.php" class="button button-secondary">Cancel</a>
                    </div>
                </form>
            </div>


        </div> </div> <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>