<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store errors
$status_message = ''; // Variable to store general status message
$message_type = '';

// --- Filtering ---
$filter_title = trim($_GET['title'] ?? '');
$filter_slug = trim($_GET['slug'] ?? '');
$filter_published = $_GET['published'] ?? ''; // '' for all, '1' for published, '0' for draft

$where_clauses = [];
$bind_params = [];
$bind_types = '';
$needs_where = false;

if (!empty($filter_title)) {
    $where_clauses[] = "title LIKE ?";
    $bind_params[] = '%' . $filter_title . '%';
    $bind_types .= 's';
    $needs_where = true;
}

if (!empty($filter_slug)) {
    $where_clauses[] = "slug LIKE ?";
    $bind_params[] = '%' . $filter_slug . '%';
    $bind_types .= 's';
    $needs_where = true;
}


if ($filter_published !== '') {
     $where_clauses[] = "is_published = ?";
     $bind_params[] = (int)$filter_published;
     $bind_types .= 'i';
     $needs_where = true;
}

// Combine WHERE clauses
$where_sql = '';
if ($needs_where) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// --- Sorting ---
$allowed_sort_columns = ['page_id', 'slug', 'title', 'created_at', 'updated_at', 'is_published'];
$default_sort_column = 'created_at';
$default_sort_direction = 'DESC';

$sort_column = $_GET['sort'] ?? $default_sort_column;
$sort_direction = $_GET['dir'] ?? $default_sort_direction;

if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = $default_sort_column;
}
if (!in_array(strtoupper($sort_direction), ['ASC', 'DESC'])) {
    $sort_direction = $default_sort_direction;
}

$order_by_sql = " ORDER BY " . $sort_column . " " . $sort_direction;


// --- Pagination ---
$limit = 10; // Pages per page
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page);

$offset = ($page - 1) * $limit;


// --- Count total pages (with filters) ---
$sql_count = "SELECT COUNT(*) AS total_pages_count FROM information_pages" . $where_sql;

$stmt_count = $conn->prepare($sql_count);

$count_bind_params = $bind_params; // Same parameters as the main WHERE clause
$count_bind_types = $bind_types;

$total_pages_count = 0;
if ($stmt_count) {
    if (!empty($count_bind_params)) {
        $stmt_count->bind_param($count_bind_types, ...$count_bind_params);
    }
    $execute_count_success = $stmt_count->execute();
    if ($execute_count_success) {
        $result_count = $stmt_count->get_result();
        $row_count = $result_count->fetch_assoc();
        $total_pages_count = $row_count['total_pages_count'];
        $result_count->free();
    }
    $stmt_count->close();
}

// Calculate total pages
$total_pages = ($total_pages_count > 0) ? ceil($total_pages_count / $limit) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = max(0, ($page - 1) * $limit);
} elseif ($total_pages_count == 0) {
    $page = 1;
    $offset = 0;
}


// --- Fetch Page Data ---
$sql = "SELECT
            page_id,
            slug,
            title,
            is_published,
            created_at,
            updated_at
        FROM information_pages"
    . $where_sql
    . $order_by_sql
    . " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

// Dynamically bind parameters
$fetch_bind_types = $bind_types . 'ii'; // Add types for LIMIT and OFFSET
$fetch_bind_params = array_merge($bind_params, [$limit, $offset]);

$information_pages = [];
if ($stmt) {
    if (!empty($fetch_bind_params)) {
        $stmt->bind_param($fetch_bind_types, ...$fetch_bind_params);
    }

    $execute_success = $stmt->execute();
    if ($execute_success) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $information_pages[] = $row;
            }
            $result->free();
        }
    } else {
         // Handle query execution error
         $errors[] = "Database query failed: " . $stmt->error;
    }
    $stmt->close();
} else {
     // Handle prepare statement error
     $errors[] = "Database error preparing fetch statement: " . $conn->error;
}

// Close the database connection if necessary
// closeDB($conn);


// --- Check for status messages from session ---
if (isset($_SESSION['success_message'])) {
    $status_message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $status_message = $_SESSION['error_message'];
    $message_type = 'error';
    unset($_SESSION['error_message']);
}

// Function to build pagination link URL, preserving filters and sort
function buildPagePaginationLink($page_num, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction) {
    $url = "?page=" . urlencode($page_num);
    if (!empty($filter_title)) $url .= "&title=" . urlencode($filter_title);
     if (!empty($filter_slug)) $url .= "&slug=" . urlencode($filter_slug);
    if ($filter_published !== '') $url .= "&published=" . urlencode($filter_published);
    if (!empty($sort_column)) $url .= "&sort=" . urlencode($sort_column);
    if (!empty($sort_direction)) $url .= "&dir=" . urlencode($sort_direction);
    return $url;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Information Pages - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Reuse table, form, pagination styles from other index pages */
        .information-pages-table {
             border-collapse: collapse;
             width: 100%;
             margin-top: 20px;
             background-color: #fff;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
             border-radius: 8px;
             overflow: hidden;
         }

         .information-pages-table th,
         .information-pages-table td {
             border: 1px solid #dee2e6;
             padding: 10px;
             text-align: left;
             font-size: 0.95em;
         }

         .information-pages-table th {
             background-color: #e9ecef;
             font-weight: bold;
             text-transform: uppercase;
             color: #495057;
             font-size: 0.85em;
         }

         .information-pages-table tbody tr:nth-child(even) {
             background-color: #f8f9fa;
         }

         .information-pages-table tbody tr:hover {
             background-color: #e2e6ea;
         }

         .information-pages-table .action-links a {
             margin-right: 10px;
             text-decoration: none;
         }

          /* Styling for filter form (reuse or adapt from previous index pages) */
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
             min-width: 80px; /* Adjust label width as needed */
             text-align: right;
             font-weight: bold;
             color: #555;
         }

         .filter-form input[type="text"],
         .filter-form select {
             width: auto;
             max-width: 180px; /* Limit width */
             padding: 8px 10px;
             border-radius: 4px;
             border: 1px solid #ced4da;
             box-sizing: border-box;
             font-size: 1em;
             flex-grow: 1; /* Allow input to grow */
         }

          .filter-form button,
          .filter-form a.button-secondary {
              padding: 8px 15px;
              margin-right: 0;
              margin-top: 0;
          }

           @media (max-width: 768px) { /* Adjust breakpoint as needed */
               .filter-form { flex-direction: column; align-items: stretch; }
               .filter-form .form-group { flex-direction: column; align-items: stretch; gap: 5px; }
               .filter-form label { margin-bottom: 5px; display: block; min-width: auto; text-align: left; }
               .filter-form input[type="text"], .filter-form select { width: 100%; max-width: none; margin-right: 0; margin-bottom: 0; }
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
        include __DIR__ . '/../includes/admin_header.php';
        $current_page = 'information_pages'; // Set a unique current page variable
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Information Pages Management</h1>

            <p><a href="add.php" class="button">Add New Page</a></p>

            <?php if ($status_message): ?>
                <div class="<?php echo $message_type; ?>-message">
                    <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

             <?php
             // Display database errors during fetch
             if (!empty($errors)) {
                 echo '<div class="error-message"><ul>';
                 foreach ($errors as $error) {
                     echo '<li>' . htmlspecialchars($error) . '</li>';
                 }
                 echo '</ul></div>';
             }
             ?>

             <div class="filter-form">
                 <form action="" method="GET">
                     <div class="form-group">
                         <label for="filter_title">Title:</label>
                         <input type="text" name="title" id="filter_title" value="<?php echo htmlspecialchars($filter_title); ?>" placeholder="Filter by Title">
                     </div>

                      <div class="form-group">
                         <label for="filter_slug">Slug:</label>
                         <input type="text" name="slug" id="filter_slug" value="<?php echo htmlspecialchars($filter_slug); ?>" placeholder="Filter by Slug">
                     </div>


                      <div class="form-group">
                           <label for="filter_published">Status:</label>
                           <select name="published" id="filter_published">
                               <option value="" <?php if ($filter_published === '') echo 'selected'; ?>>All Statuses</option>
                               <option value="1" <?php if ($filter_published === '1') echo 'selected'; ?>>Published</option>
                               <option value="0" <?php if ($filter_published === '0') echo 'selected'; ?>>Draft</option>
                           </select>
                       </div>


                     <div class="button-group">
                         <button type="submit" class="button">Filter</button>
                         <?php if (!empty($filter_title) || !empty($filter_slug) || $filter_published !== ''): ?>
                             <a href="index.php" class="button button-secondary">Reset Filter</a>
                         <?php endif; ?>
                     </div>
                 </form>
             </div>


            <?php if ($total_pages_count > 0): ?>
                <table class="information-pages-table"> 
                    <thead>
                        <tr>
                             <th>
                                 <a href="?sort=page_id&dir=<?php echo ($sort_column === 'page_id' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     ID <?php if ($sort_column === 'page_id'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                              <th>
                                 <a href="?sort=slug&dir=<?php echo ($sort_column === 'slug' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     Slug <?php if ($sort_column === 'slug'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                             <th>
                                 <a href="?sort=title&dir=<?php echo ($sort_column === 'title' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     Title <?php if ($sort_column === 'title'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                             <th>
                                 <a href="?sort=is_published&dir=<?php echo ($sort_column === 'is_published' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     Status <?php if ($sort_column === 'is_published'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                             <th>
                                 <a href="?sort=created_at&dir=<?php echo ($sort_column === 'created_at' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     Created At <?php if ($sort_column === 'created_at'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                             <th>
                                 <a href="?sort=updated_at&dir=<?php echo ($sort_column === 'updated_at' && strtoupper($sort_direction) === 'ASC') ? 'desc' : 'asc'; ?>&title=<?php echo htmlspecialchars($filter_title); ?>&slug=<?php echo htmlspecialchars($filter_slug); ?>&published=<?php echo htmlspecialchars($filter_published); ?>&page=<?php echo $page; ?>">
                                     Updated At <?php if ($sort_column === 'updated_at'): ?><span class="sort-arrow"><?php echo (strtoupper($sort_direction) === 'ASC') ? ' &#x25B2;' : ' &#x25BC;'; ?></span><?php endif; ?>
                                 </a>
                             </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($information_pages as $page_item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($page_item['page_id']); ?></td>
                                <td><?php echo htmlspecialchars($page_item['slug']); ?></td>
                                <td><?php echo htmlspecialchars($page_item['title']); ?></td>
                                <td><?php echo $page_item['is_published'] ? 'Published' : 'Draft'; ?></td>
                                <td><?php echo htmlspecialchars($page_item['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($page_item['updated_at']); ?></td>
                                <td class="action-links">
                                    <a href="edit.php?id=<?php echo urlencode($page_item['page_id']); ?>">Edit</a> |
                                    <a href="delete.php?id=<?php echo urlencode($page_item['page_id']); ?>" onclick="return confirm('Are you sure you want to delete this page?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                 <?php if ($total_pages > 1): ?>
                     <div class="pagination">
                         <?php
                         $start_page = max(1, $page - 2);
                         $end_page = min($total_pages, $page + 2);

                         if ($page > 1): ?>
                             <a href="<?php echo buildPagePaginationLink($page - 1, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction); ?>">Previous</a>
                         <?php else: ?>
                             <span class="disabled">Previous</span>
                         <?php endif; ?>

                         <?php
                         if ($start_page > 1) {
                             echo '<a href="' . buildPagePaginationLink(1, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction) . '">1</a>';
                             if ($start_page > 2) { echo '<span>...</span>'; }
                         }

                         for ($i = $start_page; $i <= $end_page; $i++): ?>
                             <?php if ($i == $page): ?>
                                 <span class="current-page"><?php echo $i; ?></span>
                             <?php else: ?>
                                 <a href="<?php echo buildPagePaginationLink($i, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction); ?>"><?php echo $i; ?></a>
                             <?php endif; ?>
                         <?php endfor; ?>

                         <?php
                         if ($end_page < $total_pages) {
                             if ($end_page < $total_pages - 1) { echo '<span>...</span>'; }
                             echo '<a href="' . buildPagePaginationLink($total_pages, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction) . '">' . $total_pages . '</a>';
                         }
                         ?>

                         <?php if ($page < $total_pages): ?>
                             <a href="<?php echo buildPagePaginationLink($page + 1, $filter_title, $filter_slug, $filter_published, $sort_column, $sort_direction); ?>">Next</a>
                         <?php else: ?>
                             <span class="disabled">Next</span>
                         <?php endif; ?>
                     </div>
                 <?php endif; ?>


            <?php else: ?>
                <p>No information pages found.</p>
            <?php endif; ?>

        </div> <!-- /.content-area -->
    </div> <!-- /.admin-container -->   
    <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?>

    </div> <script src="../../js/admin_script.js"></script>

</body>
</html>