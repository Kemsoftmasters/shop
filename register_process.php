<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "kemsoft_masters_shop";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function sanitize_input($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST["username"]);
    $email = sanitize_input($_POST["email"]);
    $first_name = sanitize_input($_POST["first_name"]);
    $last_name = sanitize_input($_POST["last_name"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if ($password !== $confirm_password) {
        $_SESSION['registration_error'] = "Passwords do not match.";
        header("Location: register.php");
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $_SESSION['registration_error'] = "Username or email already exists.";
        header("Location: register.php");
        exit();
    }

    // Insert new user with all fields
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $hashed_password, $email, $first_name, $last_name);

    if ($stmt->execute()) {
        // Registration successful, set success message and redirect to login
        $_SESSION['registration_success_message'] = "Thank you for registering with us! Your account has been created successfully. You can now log in.";
        header("Location: login.php");
        exit();
    } else {
        // Check for duplicate entry error specifically (as a fallback)
        if ($stmt->errno == 1062) {
            $_SESSION['registration_error'] = "Username or email already exists.";
        } else {
            $_SESSION['registration_error'] = "Registration failed. Please try again. (" . $stmt->errno . ") " . $stmt->error;
        }
        header("Location: register.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>