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

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT address_id, address_type, street_address, city, state, zip_code, country FROM user_addresses WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$addresses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Addresses - Kemsoft Masters</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Existing styles remain the same */
        .saved-addresses-container {
            width: 80%;
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .saved-addresses-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .address-list {
            margin-bottom: 20px;
        }

        .address-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            margin-bottom: 10px;
            background-color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .address-details {
            flex-grow: 1;
        }

        .address-details p {
            margin-bottom: 5px;
            color: #555;
        }

        .address-actions a {
            color: #007bff;
            text-decoration: none;
            margin-left: 10px;
            font-size: 14px;
        }

        .address-actions a:hover {
            text-decoration: underline;
        }

        .add-address-form {
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 6px;
            background-color: #fff;
            margin-bottom: 20px;
        }

        .add-address-form h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 1.5em;
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

        .add-button {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .add-button:hover {
            background-color: #1e7e34;
        }

        .error {
            color: red;
            margin-top: 10px;
        }

        .success {
            color: green;
            margin-top: 10px;
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
    </style>
    <script>
        function confirmDelete(addressId) {
            if (confirm("Are you sure you want to delete this address?")) {
                document.getElementById('deleteAddressForm_' + addressId).submit();
            }
        }
    </script>
</head>
<body>
    <header>
        <div class="logo">Kemsoft Masters</div>
        <nav class="main-nav">
            <button class="hamburger-menu">☰</button>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="#">Products</a></li>
                <li><a href="#">Categories</a></li>
                <li><a href="account.php">Account</a></li>
                <li><a href="cart.php">Cart(<?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>)</a></li>
            </ul>
        </nav>
    </header>

    <main class="saved-addresses-page">
        <div class="saved-addresses-container">
            <h1>Saved Addresses</h1>

            <?php if (isset($_SESSION['add_address_error'])): ?>
                <p class="error"><?php echo $_SESSION['add_address_error']; unset($_SESSION['add_address_error']); ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['add_address_success'])): ?>
                <p class="success"><?php echo $_SESSION['add_address_success']; unset($_SESSION['add_address_success']); ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['edit_address_error'])): ?>
                <p class="error"><?php echo $_SESSION['edit_address_error']; unset($_SESSION['edit_address_error']); ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['edit_address_success'])): ?>
                <p class="success"><?php echo $_SESSION['edit_address_success']; unset($_SESSION['edit_address_success']); ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['delete_address_error'])): ?>
                <p class="error"><?php echo $_SESSION['delete_address_error']; unset($_SESSION['delete_address_error']); ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['delete_address_success'])): ?>
                <p class="success"><?php echo $_SESSION['delete_address_success']; unset($_SESSION['delete_address_success']); ?></p>
            <?php endif; ?>

            <h2>Your Saved Addresses</h2>
            <?php if (!empty($addresses)): ?>
                <div class="address-list">
                    <?php foreach ($addresses as $address): ?>
                        <div class="address-item">
                            <div class="address-details">
                                <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($address['address_type'])); ?></p>
                                <p><?php echo htmlspecialchars($address['street_address']); ?></p>
                                <p><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['zip_code']); ?></p>
                                <p><?php echo htmlspecialchars($address['country']); ?></p>
                            </div>
                            <div class="address-actions">
                                <a href="edit_address.php?id=<?php echo $address['address_id']; ?>">Edit</a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $address['address_id']; ?>);">Delete</a>
                                <form id="deleteAddressForm_<?php echo $address['address_id']; ?>" action="delete_address_process.php" method="post" style="display:none;">
                                    <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No saved addresses yet.</p>
            <?php endif; ?>

            <h2>Add New Address</h2>
            <form action="add_address_process.php" method="post" class="add-address-form">
                <div class="form-group">
                    <label for="address_type">Address Type</label>
                    <select id="address_type" name="address_type" required>
                        <option value="shipping">Shipping</option>
                        <option value="billing">Billing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="street_address">Street Address</label>
                    <input type="text" id="street_address" name="street_address" required>
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" required>
                </div>
                <div class="form-group">
                    <label for="state">State/Province/Region</label>
                    <input type="text" id="state" name="state">
                </div>
                <div class="form-group">
                    <label for="zip_code">Zip/Postal Code</label>
                    <input type="text" id="zip_code" name="zip_code">
                </div>
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country">
                </div>
                <button type="submit" class="add-button">Add Address</button>
            </form>

            <a href="account.php" class="back-link">Back to Account</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Kemsoft Masters. All rights reserved.</p>
    </footer>
    <script src="js/script.js"></script>
</body>
</html>