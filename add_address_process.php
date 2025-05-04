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
    $user_id = $_SESSION['user_id'];
    $address_type = sanitize_input($_POST["address_type"]);
    $street_address = sanitize_input($_POST["street_address"]);
    $city = sanitize_input($_POST["city"]);
    $state = sanitize_input($_POST["state"]);
    $zip_code = sanitize_input($_POST["zip_code"]);
    $country = sanitize_input($_POST["country"]);

    // Basic validation
    if (empty($address_type) || empty($street_address) || empty($city) || empty($country)) {
        $_SESSION['add_address_error'] = "Please fill in all required fields (Address Type, Street Address, City, and Country).";
        header("Location: saved_addresses.php");
        exit();
    }

    // Insert the new address into the database
    $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, address_type, street_address, city, state, zip_code, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $user_id, $address_type, $street_address, $city, $state, $zip_code, $country);

    if ($stmt->execute()) {
        $_SESSION['add_address_success'] = "New address added successfully!";
        header("Location: saved_addresses.php");
        exit();
    } else {
        $_SESSION['add_address_error'] = "Error adding address. Please try again. (" . $stmt->errno . ") " . $stmt->error;
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