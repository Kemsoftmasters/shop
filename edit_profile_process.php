<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost"; // Replace with your server name if it's different
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "kemsoft_masters_shop"; // Your database name

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
    $first_name = sanitize_input($_POST["first_name"]);
    $last_name = sanitize_input($_POST["last_name"]);
    $email = sanitize_input($_POST["email"]);
    $user_id = $_SESSION['user_id'];

    // Basic validation (you can add more robust validation)
    if (empty($first_name) || empty($last_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_update_error'] = "Please fill in all fields with a valid email address.";
        header("Location: edit_profile.php");
        exit();
    }

    // Check if the new email address already exists for another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $_SESSION['profile_update_error'] = "This email address is already in use.";
        header("Location: edit_profile.php");
        exit();
    }

    // Update the user's information
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);

    if ($stmt->execute()) {
        $_SESSION['profile_update_success'] = "Profile updated successfully!";
        header("Location: account.php");
        exit();
    } else {
        $_SESSION['profile_update_error'] = "Error updating profile. Please try again. (" . $stmt->errno . ") " . $stmt->error;
        header("Location: edit_profile.php");
        exit();
    }

    $stmt->close();
} else {
    // If someone tries to access this page directly without submitting the form
    header("Location: account.php");
    exit();
}

$conn->close();
?>