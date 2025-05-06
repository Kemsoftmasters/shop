<?php
// Include the authentication check - MUST be at the very top
require_once __DIR__ . '/../includes/admin_auth.php';

// Include your existing database connection file
require_once __DIR__ . '/../../includes/db_connect.php';

// Assume db_connect.php establishes a connection and makes it available in $conn

$errors = []; // Array to store validation or processing errors
$status_message = ''; // Variable to store general status message

// Initialize form variables with empty strings or defaults
$slug = '';
$title = '';
$content = '';
$is_published = 1; // Default to published


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $slug = trim($_POST['slug'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? ''; // Content might contain HTML, handle carefully later if using rich editor
    $is_published = trim($_POST['is_published'] ?? 0);


    // Basic Validation
    if (empty($slug)) {
        $errors[] = "Page slug is required.";
    } else {
         // Validate slug format (lowercase letters, numbers, hyphens)
         if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
             $errors[] = "Page slug can only contain lowercase letters, numbers, and hyphens.";
         }
         // Ensure slug does not start or end with a hyphen, and no consecutive hyphens
         if (strpos($slug, '--') !== false || substr($slug, 0, 1) === '-' || substr($slug, -1) === '-') {
             $errors[] = "Page slug format is invalid (e.g., no consecutive hyphens, cannot start or end with hyphen).";
         }
    }

    if (empty($title)) {
        $errors[] = "Page title is required.";
    }

     // Decide if content is required. Let's make it required for now.
    if (empty($content)) {
        $errors[] = "Page content is required.";
    }


     if ($is_published === '' || !in_array((int)$is_published, [0, 1])) {
         $errors[] = "Invalid status selected for page.";
     } else {
         $is_published = (int)$is_published; // Ensure it's an integer
     }


    // Check for duplicate slug BEFORE inserting
    if (empty($errors)) {
        $sql_check_duplicate = "SELECT page_id FROM information_pages WHERE slug = ?";
        $stmt_check_duplicate = $conn->prepare($sql_check_duplicate);
        if ($stmt_check_duplicate) {
            $stmt_check_duplicate->bind_param("s", $slug);
            $stmt_check_duplicate->execute();
            $stmt_check_duplicate->store_result();
            if ($stmt_check_duplicate->num_rows > 0) {
                $errors[] = "A page with this slug already exists.";
            }
            $stmt_check_duplicate->close();
        } else {
            $errors[] = "Database error checking for duplicate slug: " . $conn->error;
        }
    }


    // --- Insert Data into Database ---
    if (empty($errors)) {
        // Prepare an INSERT statement
        $sql = "INSERT INTO information_pages (slug, title, content, is_published)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
             // Bind parameters
             // s: slug (string)
             // s: title (string)
             // s: content (string, even for LONGTEXT)
             // i: is_published (tinyint)

            $stmt->bind_param(
                "sssi",
                $slug,
                $title,
                $content,
                $is_published
            );


            // Execute the statement
            if ($stmt->execute()) {
                // Page added successfully
                $_SESSION['success_message'] = "Information page '" . htmlspecialchars($title) . "' added successfully!";
                // Redirect back to the pages list
                header("Location: index.php");
                exit();
            } else {
                // Handle database insertion error
                $errors[] = "Database error: " . $stmt->error;
            }

            $stmt->close(); // Close the statement
        } else {
            // Handle prepare statement error
            $errors[] = "Database error preparing insert statement: " . $conn->error;
        }
    }
}

// Close the database connection if necessary
// closeDB($conn); // If your db_connect.php has a closeDB function
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Information Page - Kemsoft Shop Admin</title>
    <link rel="stylesheet" href="../../css/admin_style.css">
    <style>
        /* Reuse form styles from previous add pages */
         .form-container {
             max-width: 800px; /* Slightly wider form for content */
             margin: 20px auto;
             padding: 25px;
             background-color: #fff;
             border-radius: 8px;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
         }

         .form-container h1 {
             text-align: center;
             margin-top: 0;
             color: #333;
         }

         .form-group {
             margin-bottom: 15px;
         }

         .form-group label {
             display: block;
             margin-bottom: 5px;
             font-weight: bold;
             color: #555;
         }

         .form-group input[type="text"],
         .form-group textarea,
         .form-group select {
             width: 100%;
             padding: 10px;
             border-radius: 4px;
             border: 1px solid #ced4da;
             box-sizing: border-box;
             font-size: 1em;
         }

         .form-group textarea {
             resize: vertical;
             min-height: 300px; /* Larger height for content */
         }

         .form-group small {
              display: block;
              margin-top: 5px;
              color: #6c757d;
         }


         /* Status/Error Message Styling */
         .success-message { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
         .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }


         .form-container button[type="submit"] {
             display: block;
             width: 100%;
             padding: 10px;
             background-color: #007bff;
             color: white;
             border: none;
             border-radius: 4px;
             font-size: 1.1em;
             cursor: pointer;
             transition: background-color 0.2s ease-in-out;
         }

         .form-container button[type="submit"]:hover {
              background-color: #0056b3;
         }

         .back-link {
              display: block;
              text-align: center;
              margin-top: 20px;
              color: #007bff;
              text-decoration: none;
         }
          .back-link:hover {
               text-decoration: underline;
          }

    </style>
</head>
<body>

    <div class="admin-container">

        <?php
        include __DIR__ . '/../includes/admin_header.php';
         // Pass current page to sidebar for active state highlighting
        $current_page = 'information_pages'; // Set the current page variable
        include __DIR__ . '/../includes/admin_sidebar.php';
        ?>

        <div class="content-area">
            <h1>Add New Information Page</h1>

            <div class="form-container">

                <?php
                // Display errors
                if (!empty($errors)) {
                    echo '<div class="error-message"><ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul></div>';
                }
                ?>

                 <form action="" method="POST">
                    <div class="form-group">
                        <label for="title">Page Title:</label>
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>

                     <div class="form-group">
                         <label for="slug">Page Slug:</label>
                         <input type="text" name="slug" id="slug" value="<?php echo htmlspecialchars($slug); ?>" required>
                         <small>Unique identifier for the URL (e.g., "about-us", "privacy-policy"). Use only lowercase letters, numbers, and hyphens.</small>
                     </div>

                    <div class="form-group">
                        <label for="content">Page Content:</label>
                        <textarea name="content" id="content" required><?php echo htmlspecialchars($content); ?></textarea>
                        <small>Enter the HTML content for the page.</small> 
                    </div>

                      <div class="form-group">
                          <label for="is_published">Status:</label>
                          <select name="is_published" id="is_published" required>
                              <option value="1" <?php if ((string)$is_published === '1') echo 'selected'; ?>>Published</option>
                              <option value="0" <?php if ((string)$is_published === '0') echo 'selected'; ?>>Draft</option>
                          </select>
                      </div>


                    <button type="submit">Add Page</button>
                </form>

                 <a href="index.php" class="back-link">Back to Pages List</a>

            </div> <!-- End form-container -->

        </div> <!-- End content-area -->
    </div> <!-- End admin-container -->
    <?php
        include __DIR__ . '/../includes/admin_footer.php';
        ?> 

    </div> <script src="../../js/admin_script.js"></script>

     <script>
         document.getElementById('title').addEventListener('input', function() {
             const titleInput = this;
             const slugInput = document.getElementById('slug');
             // Only auto-generate slug if the slug field is empty
             if (slugInput.value.trim() === '') {
                 const slug = titleInput.value
                     .toLowerCase()
                     .replace(/[^a-z0-9]+/g, '-') // Replace non-alphanumeric characters with hyphens
                     .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
                 slugInput.value = slug;
             }
         });
     </script>


</body>
</html>