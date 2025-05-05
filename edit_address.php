<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: saved_addresses.php");
    exit();
}

$address_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch the address details for the given address_id and user_id
$stmt = $conn->prepare("SELECT address_type, street_address, city, state, zip_code, country FROM user_addresses WHERE address_id = ? AND user_id = ?");
$stmt->bind_param("ii", $address_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // If the address doesn't exist or doesn't belong to the user
    $_SESSION['edit_address_error'] = "Invalid address ID.";
    header("Location: saved_addresses.php");
    exit();
}

$address = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Address - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .edit-address-container {
            width: 80%;
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .edit-address-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .save-button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .save-button:hover {
            background-color: #0056b3;
        }

        .back-link {
            margin-top: 20px;
            display: block;
            text-align: center;
            color: #777;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            margin-top: 10px;
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

    <main class="edit-address-page">
        <div class="edit-address-container">
            <h1>Edit Address</h1>

            <?php if (isset($_SESSION['edit_address_error'])): ?>
                <p class="error"><?php echo $_SESSION['edit_address_error']; unset($_SESSION['edit_address_error']); ?></p>
            <?php endif; ?>

            <form action="edit_address_process.php" method="post">
                <input type="hidden" name="address_id" value="<?php echo $address_id; ?>">
                <div class="form-group">
                    <label for="address_type">Address Type</label>
                    <select id="address_type" name="address_type" required>
                        <option value="shipping" <?php if ($address['address_type'] === 'shipping') echo 'selected'; ?>>Shipping</option>
                        <option value="billing" <?php if ($address['address_type'] === 'billing') echo 'selected'; ?>>Billing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="street_address">Street Address</label>
                    <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($address['street_address']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($address['city']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="state">State/Province/Region</label>
                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($address['state']); ?>">
                </div>
                <div class="form-group">
                    <label for="zip_code">Zip/Postal Code</label>
                    <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($address['zip_code']); ?>">
                </div>
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($address['country']); ?>" required>
                </div>
                <button type="submit" class="save-button">Save Changes</button>
            </form>

            <a href="saved_addresses.php" class="back-link">Back to Saved Addresses</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>