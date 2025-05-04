<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['address_id']) || !is_numeric($_POST['address_id'])) {
    header("Location: saved_addresses.php");
    exit();
}

$address_id = $_POST['address_id'];
$user_id = $_SESSION['user_id'];

$servername = "localhost"; // Replace with your server name if it's different
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "kemsoft_masters_shop"; // Your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Delete the address from the database
    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $address_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['delete_address_success'] = "Address deleted successfully!";
        } else {
            $_SESSION['delete_address_error'] = "Could not delete address. It might not exist or not belong to you.";
        }
        header("Location: saved_addresses.php");
        exit();
    } else {
        $_SESSION['delete_address_error'] = "Error deleting address. Please try again. (" . $stmt->errno . ") " . $stmt->error;
        header("Location: saved_addresses.php");
        exit();
    }

    $stmt->close();
} else {
    // If someone tries to access this page directly
    header("Location: saved_addresses.php");
    exit();
}

$conn->close();
?>