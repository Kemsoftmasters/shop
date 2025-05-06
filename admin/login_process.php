<?php
session_start(); // Start the session

// Include your existing database connection file
require_once __DIR__ . '/../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn
// If it's a function, call it here:
// $conn = connect_to_db(); // Replace with your actual function name

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prevent SQL injection by using prepared statements
    $stmt = $conn->prepare("SELECT admin_id, username, password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Verify the submitted password against the hashed password in the database
        if (password_verify($password, $admin['password'])) {
            // Password is correct, start a new session
            session_regenerate_id(true); // Regenerate session ID for security

            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];

            // Redirect to the admin dashboard
            header('Location: index.php');
            exit();
        } else {
            // Invalid password
            $_SESSION['login_error'] = "Invalid username or password.";
            header('Location: login.php');
            exit();
        }
    } else {
        // No user found with that username
        $_SESSION['login_error'] = "Invalid username or password.";
        header('Location: login.php');
        exit();
    }

    $stmt->close();
}

// Close the database connection if necessary
// closeDB($conn); // Assuming you have a closeDB function
?>