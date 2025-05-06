<?php
// Include the authentication check
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store errors
$status_message = ''; // Variable to store general status message
$message_type = '';


// --- Filtering ---
// Get filter parameters from GET request
$filter_name = trim($_GET['name'] ?? ''); // Filter by first_name or full name
$filter_email = trim($_GET['email'] ?? ''); // Filter by email
$filter_status = $_GET['status'] ?? ''; // Filter by status ('', 'active', 'inactive', 'banned')


// Build the WHERE clause based on filter parameters
$where_clauses = [];
$bind_params = [];
$bind_types = '';
$needs_where = false;

if (!empty($filter_name)) {
    // Search both first_name and last_name for 'name' filter
    $where_clauses[] = "(first_name LIKE ? OR last_name LIKE ?)";
    $bind_params[] = '%' . $filter_name . '%';
    $bind_params[] = '%' . $filter_name . '%'; // Bind twice for OR clause
    $bind_types .= 'ss';
    $needs_where = true;
}

if (!empty($filter_email)) {
    $where_clauses[] = "email LIKE ?";
    $bind_params[] = '%' . $filter_email . '%';
    $bind_types .= 's';
    $needs_where = true;
}

if ($filter_status !== '') {
    // Ensure the filter value is one of the allowed enum values or empty
    $allowed_statuses = ['active', 'inactive', 'banned'];
    if (in_array($filter_status, $allowed_statuses)) {
         $where_clauses[] = "status = ?";
         $bind_params[] = $filter_status;
         $bind_types .= 's';
         $needs_where = true;
    }
    // If filter_status is set but not a valid enum value, it's ignored
}


// Combine WHERE clauses
$where_sql = '';
if ($needs_where) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}


// --- Sorting ---
$allowed_sort_columns = ['user_id', 'first_name', 'last_name', 'email', 'phone_no', 'status', 'created_at', 'last_login_at']; // Added new sortable columns
$default_sort_column = 'created_at';
$default_sort_direction = 'DESC';

$sort_column = $_GET['sort'] ?? $default_sort_column;
$sort_direction = $_GET['dir'] ?? $default_sort_direction;

// Validate the sort column and direction
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = $default_sort_column;
}
if (!in_array(strtoupper($sort_direction), ['ASC', 'DESC'])) {
    $sort_direction = $default_sort_direction;
}

$order_by_sql = " ORDER BY " . $sort_column . " " . $sort_direction;


// --- Pagination ---
$limit = 10; // Number of users per page
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page);

$offset = ($page - 1) * $limit;


// --- Count total users (with filters applied) ---
$sql_count = "SELECT COUNT(*) AS total_users FROM users" . $where_sql;

$stmt_count = $conn->prepare($sql_count);

$count_bind_params = $bind_params; // Same parameters as the main WHERE clause
$count_bind_types = $bind_types;

$total_users = 0;
if ($stmt_count) {
    if (!empty($count_bind_params)) {
        $stmt_count->bind_param($count_bind_types, ...$count_bind_params);
    }

    $execute_count_success = $stmt_count->execute();
    if ($execute_count_success) {
        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();
        $total_users = $row_count['total_users'];
        $result_count->free();
    } else {
         $errors[] = "Database count query failed: " . $stmt_count->error;
    }
    $stmt_count->close();
} else {
     $errors[] = "Database error preparing count statement: " . $conn->error;
}


// Calculate total pages
$total_pages = ($total_users > 0) ? ceil($total_users / $limit) : 1;
// Ensure current page doesn't exceed total pages (unless no users found)
if ($total_users > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
} elseif ($total_users == 0) {
    $page = 1;
    $offset = 0;
}


// --- Fetch Users Data (with filters, sorting, and pagination) ---
$sql = "SELECT user_id, first_name, last_name, email, phone_no, status, created_at, last_login_at FROM users"
    . $where_sql
    . $order_by_sql
    . " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

// Dynamically bind parameters for the main fetch query
// Bind types: filter types + 'ii' for LIMIT and OFFSET
$fetch_bind_types = $bind_types . 'ii';
$fetch_bind_params = array_merge($bind_params, [$limit, $offset]);

$users = [];
if ($stmt) {
    if (!empty($fetch_bind_params)) {
        $stmt->bind_param($fetch_bind_types, ...$fetch_bind_params);
    }

    $execute_success = $stmt->execute();

    if ($execute_success) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $result->free(); // Free result set
        }
    } else {
        $errors[] = "Database query failed: " . $stmt->error;
        $users = []; // Ensure $users is empty on error
    }
    $stmt->close(); // Close statement
} else {
     $errors[] = "Database error preparing fetch statement: " . $conn->error;
     $users = []; // Ensure $users is empty on error
}


// Close the database connection if necessary (depends on db_connect.php)
// closeDB($conn); // If your db_connect.php has a closeDB function


// --- Check for status messages from session ---
if (isset($_SESSION['success_message']) && empty($errors)) {
    $status_message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message']) && empty($errors)) {
    $status_message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}

if (!empty($errors)) {
     $status_message = "Errors: " . implode('<br>', $errors);
     $message_type = 'error';
}


// Function to build pagination link URL, preserving filters and sort
function buildUserPaginationLink($page_num, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction) {
    $url = "?page=" . urlencode($page_num);
    if (!empty($filter_name)) $url .= "&name=" . urlencode($filter_name);
    if (!empty($filter_email)) $url .= "&email=" . urlencode($filter_email);
    if ($filter_status !== '') $url .= "&status=" . urlencode($filter_status); // Added status filter
    if (!empty($sort_column)) $url .= "&sort=" . urlencode($sort_column);
    if (!empty($sort_direction)) $url .= "&dir=" . urlencode($sort_direction);
    return $url;
}

// Function to format datetime strings
function formatDateTime($datetime_str) {
    if (empty($datetime_str) || $datetime_str === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $date = new DateTime($datetime_str);
        return $date->format('Y-m-d H:i'); // Adjust format as needed
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
         /* Add or keep your specific users table/form styling here or in admin_style.css */
         /* Reuse styles from previous index pages (filter-form, table, pagination) */
         .users-table {
             border-collapse: collapse;
             width: 100%;
             margin-top: 20px;
             background-color: #fff;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
             border-radius: 8px;
             overflow: hidden;
         }

         .users-table th,
         .users-table td {
             border: 1px solid #dee2e6;
             padding: 10px;
             text-align: left;
             font-size: 0.95em;
         }

         .users-table th {
             background-color: #e9ecef;
             font-weight: bold;
             text-transform: uppercase;
             color: #495057;
             font-size: 0.85em;
         }

         .users-table tbody tr:nth-child(even) {
             background-color: #f8f9fa;
         }

         .users-table tbody tr:hover {
             background-color: #e2e6ea;
         }

         .users-table .action-links a {
             margin-right: 10px;
             text-decoration: none;
         }

         /* Styling for filter form */
         .filter-form {
             margin-bottom: 20px;
             padding: 15px;
             background-color: #e9ecef;
             border-radius: 8px;
             display: flex;
             flex-wrap: wrap;
             gap: 15px;
             align-items: flex-end;
         }

         .filter-form .form-group {
             display: flex;
             align-items: center;
             gap: 5px;
         }

         .filter-form .form-group label {
             margin-bottom: 0;
             min-width: 80px;
             text-align: right;
             font-weight: bold;
             color: #555;
         }

         .filter-form input[type="text"],
         .filter-form input[type="email"],
         .filter-form select 
         {
             width: auto;
             max-width: 180px;
             padding: 8px 10px;
             border-radius: 4px;
             border: 1px solid #ced4da;
             box-sizing: border-box;
             font-size: 1em;
             flex-grow: 1;
         }

          .filter-form button,
          .filter-form a.button-secondary {
              padding: 8px 15px;
              margin-right: 0;
              margin-top: 0;
          }

           @media (max-width: 768px) {
               .filter-form { flex-direction: column; align-items: stretch; }
               .filter-form .form-group { flex-direction: column; align-items: stretch; gap: 5px; }
               .filter-form label { margin-bottom: 5px; display: block; min-width: auto; text-align: left; }
               .filter-form input[type="text"], .filter-form input[type="email"], .filter-form select { width: 100%; max-width: none; margin-right: 0; margin-bottom: 0; }
               .filter-form .button-group { flex-direction: column; gap: 10px; margin-top: 10px; }
               .filter-form button, .filter-form a.button-secondary { width: 100%; margin-right: 0; }
           }

         /* Styling for sort arrows */
         span.sort-arrow {
             font-size: 0.8em;
             vertical-align: middle;
         }

         /* Pagination styling */
         .pagination {
             margin-top: 20px;
             display: flex;
             justify-content: center;
             align-items: center;
             gap: 10px;
         }

         .pagination a, .pagination span {
             display: inline-block;
             padding: 8px 12px;
             border: 1px solid #ced4da;
             border-radius: 4px;
             text-decoration: none;
             color: #007bff;
             background-color: #fff;
             transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
         }

         .pagination a:hover { background-color: #e9ecef; color: #0056b3; }
         .pagination span.current-page { background-color: #007bff; color: white; border-color: #007bff; font-weight: bold; }
         .pagination span.disabled { opacity: 0.5; cursor: not-allowed; }

         /* Status message styling */
         .success-message { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
         .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
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
            <h1>Users Management</h1>

            <?php
            // Optional: Add a button to add a new user - assuming you have an add.php
            echo '<p><a href="add.php" class="button">Add New User</a></p>';
            ?>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div class="filter-form">
                <form action="" method="GET">
                    <div class="form-group"> <label for="filter_name">Name:</label>
                        <input type="text" name="name" id="filter_name" value="<?php echo htmlspecialchars($filter_name); ?>" placeholder="Filter by name">
                    </div>

                    <div class="form-group"> <label for="filter_email">Email:</label>
                        <input type="email" name="email" id="filter_email" value="<?php echo htmlspecialchars($filter_email); ?>" placeholder="Filter by email">
                    </div>

                    <div class="form-group"> 
                         <label for="filter_status">Status:</label>
                         <select name="status" id="filter_status">
                             <option value="" <?php if ($filter_status === '') echo 'selected'; ?>>All Statuses</option>
                             <option value="active" <?php if ($filter_status === 'active') echo 'selected'; ?>>Active</option>
                             <option value="inactive" <?php if ($filter_status === 'inactive') echo 'selected'; ?>>Inactive</option>
                             <option value="banned" <?php if ($filter_status === 'banned') echo 'selected'; ?>>Banned</option>
                         </select>
                     </div>


                    <div class="button-group"> <button type="submit" class="button">Filter</button>
                        <?php if (!empty($filter_name) || !empty($filter_email) || $filter_status !== ''): ?>
                            <a href="index.php" class="button button-secondary">Reset Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>


            <?php if ($total_users > 0): // Check total users WITH filters
            ?>
                <table class="users-table"> 
                    <thead>
                        <tr>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'user_id', ($sort_column === 'user_id' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                    ID
                                    <?php if ($sort_column === 'user_id'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'first_name', ($sort_column === 'first_name' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                    Full Name
                                    <?php if ($sort_column === 'first_name'): ?> 
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'email', ($sort_column === 'email' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                    Email
                                    <?php if ($sort_column === 'email'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'phone_no', ($sort_column === 'phone_no' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                     Phone
                                     <?php if ($sort_column === 'phone_no'): ?>
                                         <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                     <?php endif; ?>
                                 </a>
                             </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'status', ($sort_column === 'status' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                     Status
                                     <?php if ($sort_column === 'status'): ?>
                                         <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                     <?php endif; ?>
                                 </a>
                             </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'created_at', ($sort_column === 'created_at' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                    Registered At
                                    <?php if ($sort_column === 'created_at'): ?>
                                        <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, 'last_login_at', ($sort_column === 'last_login_at' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'); ?>">
                                     Last Login
                                     <?php if ($sort_column === 'last_login_at'): ?>
                                         <span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span>
                                     <?php endif; ?>
                                 </a>
                             </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php
                                     $full_name = htmlspecialchars($user['first_name'] ?? '');
                                     if (isset($user['last_name']) && !empty($user['last_name'])) {
                                          $full_name .= ' ' . htmlspecialchars($user['last_name']);
                                     } elseif (!isset($user['first_name']) || empty($user['first_name'])) {
                                          $full_name = 'N/A';
                                     }
                                     echo $full_name;
                                     ?>
                                 </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone_no'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($user['status'] ?? 'N/A')); ?></td> 
                                <td><?php echo formatDateTime($user['created_at'] ?? 'N/A'); ?></td> 
                                <td><?php echo formatDateTime($user['last_login_at'] ?? 'N/A'); ?></td> 
                                <td class="action-links">
                                    <a href="view.php?id=<?php echo urlencode($user['user_id']); ?>">View</a>
                                    | <a href="edit.php?id=<?php echo urlencode($user['user_id']); ?>">Edit</a>
                                    | <a href="delete.php?id=<?php echo urlencode($user['user_id']); ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        // Function to build pagination link URL, preserving filters and sort
                        // This function is already defined in the PHP section, so no need to redefine here
                        // function buildUserPaginationLink(...) { ... }
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildUserPaginationLink($page - 1, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction); ?>">Previous</a>
                        <?php else: ?>
                            <span class="disabled">Previous</span>
                        <?php endif; ?>

                        <?php
                        // Display page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<a href="' . buildUserPaginationLink(1, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction) . '">1</a>';
                            if ($start_page > 2) { echo '<span>...</span>'; } // Ellipsis
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo buildUserPaginationLink($i, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) { echo '<span>...</span>'; } // Ellipsis
                            echo '<a href="' . buildUserPaginationLink($total_pages, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction) . '">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo buildUserPaginationLink($page + 1, $filter_name, $filter_email, $filter_status, $sort_column, $sort_direction); ?>">Next</a>
                        <?php else: ?>
                            <span class="disabled">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p>No users found matching your criteria.</p>
            <?php endif; ?>

        </div> </div> <script src="../../js/admin_script.js"></script>

    <?php
    // --- Include Admin Footer ---
    include __DIR__ . '/../includes/admin_footer.php';
    ?>

</body>

</html>