<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, registration_date FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
} else {
    header("Location: logout.php");
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .account-container {
            width: 80%;
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .account-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .account-info {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 6px;
            background-color: #fff;
        }

        .account-info p {
            margin-bottom: 10px;
            color: #555;
            font-size: 16px;
        }

        .account-info p strong {
            font-weight: bold;
            color: #333;
        }

        .logout-button {
            background-color: #dc3545;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .logout-button:hover {
            background-color: #c82333;
        }

        .account-actions {
            margin-top: 30px;
            text-align: center;
        }

        .account-actions a {
            color: #007bff;
            text-decoration: none;
            margin: 0 15px;
            font-size: 16px;
        }

        .account-actions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Kemsoft Masters</div>
        <nav class="main-nav">
            <button class="hamburger-menu">☰</button>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="category.php">Categories</a></li>
                <li><a href="account.php">Account</a></li>
                <li><a href="wishlist.php">Wishlist</a></li>
                <li><a href="cart.php">Cart(<?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>)</a></li>
            </ul>
        </nav>
    </header>

    <main class="account-page">
        <div class="account-container">
            <h1>Your Account</h1>

            <div class="account-info">
                <h2>Personal Information</h2>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Email Address:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <?php if (!empty($user['first_name']) || !empty($user['last_name'])): ?>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <?php endif; ?>
                <p><strong>Registration Date:</strong> <?php echo date("F j, Y", strtotime($user['registration_date'])); ?></p>
            </div>

            <div class="account-actions">
                <a href="order_history.php">Order History</a>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="saved_addresses.php">Saved Addresses</a>
            </div>

            <div class="account-actions" style="margin-top: 20px;">
                <button class="logout-button" onclick="window.location.href='logout.php'">Logout</button>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>