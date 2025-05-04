<?php

$servername = "localhost"; // Replace with your server name if it's different
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "kemsoft_masters_shop"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optionally, you can set the character set to UTF-8 for proper encoding
$conn->set_charset("utf8");

// You can also define constants for common database operations if you like
// define('DB_HOST', 'localhost');
// define('DB_USER', 'your_db_username');
// define('DB_PASS', 'your_db_password');
// define('DB_NAME', 'kemsoft_masters_shop');

// $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }
// $conn->set_charset("utf8");

?>