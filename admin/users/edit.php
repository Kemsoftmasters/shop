<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$user_id = null;
$user = null;
$errors = []; // Renamed from $form_errors for consistency
$status_message = '';
$message_type = '';

// --- Handle POST Request (Form Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_id_to_update = $_POST['user_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? ''); // Use first_name
    $last_name = trim($_POST['last_name'] ?? ''); // Added last_name
    $email = trim($_POST['email'] ?? '');
    $phone_no = trim($_POST['phone_no'] ?? ''); // Added phone_no
    $status = trim($_POST['status'] ?? ''); // Added status
    $address = trim($_POST['address'] ?? ''); // Added address
    $password = $_POST['password'] ?? ''; // New password (optional)
    $confirm_password = $_POST['confirm_password'] ?? ''; // Confirm new password


    // Validate user ID
    if (empty($user_id_to_update) || !is_numeric($user_id_to_update)) {
        $errors[] = "Invalid user ID for update.";
    } else {
        $user_id_to_update = (int)$user_id_to_update;
    }

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
        // Check if email already exists for another user
        $sql_check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        if ($stmt_check_email) {
             $stmt_check_email->bind_param("si", $email, $user_id_to_update);
             $stmt_check_email->execute();
             $stmt_check_email->store_result();
             if ($stmt_check_email->num_rows > 0) {
                 $errors[] = "Email already exists for another user.";
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


    // Validate Password (if provided)
    if (!empty($password)) {
        if (strlen($password) < 6) { // Example: Minimum password length
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Password and confirm password do not match.";
        }
        // You might want to add more password strength checks here
    }

    // If there are no validation errors
    if (empty($errors)) {
        // Build the UPDATE query dynamically
        $sql_update = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_no = ?, status = ?, address = ?, updated_at = CURRENT_TIMESTAMP"; // Added columns
        $update_types = "ssssss"; // Initial types for first_name, last_name, email, phone_no, status, address
        $update_params = [$first_name, $last_name, $email, $phone_no, $status, $address]; // Initial parameters

        if (!empty($password)) {
            // Hash the new password before updating
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_update .= ", password_hash = ?"; // Assuming password_hash column exists
            $update_types .= "s"; // Add type for password_hash
            $update_params[] = $hashed_password; // Add hashed password parameter
        }

        $sql_update .= " WHERE user_id = ?"; // Add WHERE clause
        $update_types .= "i"; // Add type for user_id
        $update_params[] = $user_id_to_update; // Add user_id parameter


        $stmt_update = $conn->prepare($sql_update);

        if ($stmt_update) {
             // Dynamically bind parameters
             if (count($update_params) > 0) {
                  $stmt_update->bind_param($update_types, ...$update_params);
             }

            if ($stmt_update->execute()) {
                $_SESSION['success_message'] = "User updated successfully!";
                // Redirect back to the view page or list page
                // Redirecting back to edit page with ID is common after successful edit
                header("Location: edit.php?id=" . urlencode($user_id_to_update));
                exit();
            } else {
                $errors[] = "Error updating user: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors[] = "Database error preparing update statement: " . $conn->error;
        }
    } else {
        // If there are form errors, set status message for display
         $status_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
         $message_type = 'error';
        // Keep the submitted values to repopulate the form
        // Form fields will be populated from the $_POST values directly below
    }
}


// --- Handle GET Request (Displaying the Form) ---
// This runs when the page is first accessed or after a failed POST submission
// If GET, fetch existing data. If POST failed, form fields are populated from $_POST.
if ($_SERVER["REQUEST_METHOD"] == "GET" || !empty($errors)) { // Also run GET logic if there are form errors

    // Get user ID from GET request (or from POST if there were errors)
    $user_id = $_GET['id'] ?? $_POST['user_id'] ?? null; // Get from GET or POST[user_id]

    // Validate user ID for fetching
    if (empty($user_id) || !is_numeric($user_id)) {
        $_SESSION['error_message'] = "Invalid or missing user ID.";
        header('Location: index.php'); // Redirect back to users list
        exit();
    }

    // Fetch the user's existing data to pre-fill the form (if not a failed POST)
    // If it's a failed POST, the variables ($first_name, $last_name, etc.) are already populated from $_POST above
    if ($_SERVER["REQUEST_METHOD"] == "GET" || empty($_POST)) {
         $sql_fetch_user = "SELECT user_id, first_name, last_name, email, phone_no, status, address FROM users WHERE user_id = ?"; // Fetch all necessary columns
         $stmt_fetch_user = $conn->prepare($sql_fetch_user);
         if ($stmt_fetch_user) {
             $stmt_fetch_user->bind_param("i", $user_id);
             $stmt_fetch_user->execute();
             $result_fetch_user = $stmt_fetch_user->get_result();

             if ($result_fetch_user->num_rows === 1) {
                 $user = $result_fetch_user->fetch_assoc();

                 // Populate form variables with fetched data
                 $first_name = $user['first_name'];
                 $last_name = $user['last_name'];
                 $email = $user['email'];
                 $phone_no = $user['phone_no'];
                 $status = $user['status'];
                 $address = $user['address'];
                 $password = ''; // Never pre-fill password fields
                 $confirm_password = '';

             } else {
                 // User not found (should be caught by initial validation, but good fallback)
                 $_SESSION['error_message'] = "User not found.";
                 header('Location: index.php'); // Redirect back to users list
                 exit();
             }
             $stmt_fetch_user->close();
         } else {
              $errors[] = "Database error preparing fetch statement: " . $conn->error;
         }
    }
     // If it was a failed POST, the variables are already set from $_POST at the top
     // The $user variable might still be null if the initial fetch failed, handle display below
     // It's better to re-fetch $user here after POST if needed for page title etc.
      if ($user === null && isset($user_id) && is_numeric($user_id)) {
          $sql_re_fetch_user = "SELECT user_id, first_name FROM users WHERE user_id = ?";
          $stmt_re_fetch_user = $conn->prepare($sql_re_fetch_user);
          if ($stmt_re_fetch_user) {
              $stmt_re_fetch_user->bind_param("i", $user_id);
              $stmt_re_fetch_user->execute();
              $result_re_fetch_user = $stmt_re_fetch_user->get_result();
              if ($result_re_fetch_user->num_rows === 1) {
                  $user = $result_re_fetch_user->fetch_assoc();
              }
              $stmt_re_fetch_user->close();
          }
      }
}


// Close the database connection if necessary
// closeDB($conn);


// --- Check for session status messages (e.g., from delete action) ---
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
    <title>Edit User (ID: <?php echo htmlspecialchars($user_id ?? 'N/A'); ?>) - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
         /* Specific styles for the edit user form container */
         .edit-user-form-container {
             background-color: #fff;
             padding: 20px;
             border-radius: 8px;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
             max-width: 600px; /* Limit form width */
             margin: 20px auto; /* Center the form */
         }

         .edit-user-form-container h2 {
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
        $current_page = 'users'; // Set a variable for the current page
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Edit User (ID: <?php echo htmlspecialchars($user_id ?? 'N/A'); ?>)</h1>

            <?php if (!empty($status_message)): ?> 
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo $status_message; // HTML is allowed here for line breaks in error messages
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($user): // Only display form if user data was fetched (or re-fetched after failed POST)
            ?>
                <div class="edit-user-form-container">
                    <h2>User Details</h2> 
                    <form action="" method="POST">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

                        <div class="form-group">
                            <label for="first_name">First Name:</label> 
                            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required> 
                        </div>

                         <div class="form-group">
                             <label for="last_name">Last Name:</label> 
                             <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>"> 
                         </div>


                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>

                         <div class="form-group">
                             <label for="phone_no">Phone Number:</label> 
                             <input type="text" name="phone_no" id="phone_no" value="<?php echo htmlspecialchars($phone_no ?? ''); ?>"> 
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
                             <textarea name="address" id="address"><?php echo htmlspecialchars($address ?? ''); ?></textarea> 
                         </div>


                        <div class="form-group">
                            <label for="password">New Password (optional):</label>
                            <input type="password" name="password" id="password" value=""> 
                            <small>Leave blank if you don't want to change the password.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" name="confirm_password" id="confirm_password" value=""> 
                        </div>

                        <div class="button-group">
                            <button type="submit" class="button">Save Changes</button>
                            <a href="index.php" class="button button-secondary">Back to Users List</a> 
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <p>User data could not be loaded for editing. Please check the user ID.</p> 
            <?php endif; ?>


        </div> </div>
    <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>