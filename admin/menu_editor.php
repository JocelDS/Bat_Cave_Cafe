<?php
require('db.php'); // expects $con as mysqli connection
session_start();

// --- 1. ADMIN AUTHENTICATION ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
    $query_admin_name = "SELECT name FROM admins WHERE admin_id = '$admin_id'";
    $result_admin_name = mysqli_query($con, $query_admin_name);
    $admin_name = ($result_admin_name && mysqli_num_rows($result_admin_name) > 0) ? mysqli_fetch_assoc($result_admin_name)['name'] : 'Admin';

// --- CONFIG / INITIALIZE ---
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$page_title = "Menu Editor";
$message = [];
$error_message = [];
$menu_item_data = null;

$upload_dir = '../uploads/'; 
$allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Helper: SweetAlert messages (for inline non-redirect messages)
function display_php_alert_messages($messages, $type) {
    foreach ($messages as $msg) {
        echo '<script>swal("' . ucfirst($type) . '", "' . addslashes($msg) . '", "' . $type . '");</script>';
    }
}

// Helper: sanitize output
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// === 3. CATEGORY MANAGEMENT (Add/Delete) ===
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    if ($category_name === '') {
        $error_message[] = "Category name cannot be empty.";
    } else {
        $stmt_check = $con->prepare("SELECT category_id FROM menu_categories WHERE category_name = ?");
        $stmt_check->bind_param("s", $category_name);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        if ($res && $res->num_rows == 0) {
            $stmt = $con->prepare("INSERT INTO menu_categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) {
                $message[] = "Category '{$category_name}' added successfully.";
            } else {
                $error_message[] = "Failed to add category: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message[] = "Category '{$category_name}' already exists.";
        }
        $stmt_check->close();
    }
}

if (isset($_GET['delete_category_id'])) {
    $cat_id = intval($_GET['delete_category_id']);
    // prevent deleting non-empty categories
    $stmt_check = $con->prepare("SELECT COUNT(*) AS count FROM menu_items WHERE category_id = ?");
    $stmt_check->bind_param("i", $cat_id);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_check->close();

    if ($count > 0) {
        header("Location: menu_editor.php?status=error&message=" . urlencode("Cannot delete category ID $cat_id. It still contains $count menu items. Delete the items first."));
        exit();
    } else {
        $stmt = $con->prepare("DELETE FROM menu_categories WHERE category_id = ?");
        $stmt->bind_param("i", $cat_id);
        if ($stmt->execute()) {
            header("Location: menu_editor.php?status=success&message=" . urlencode("Category ID $cat_id deleted successfully."));
        } else {
            header("Location: menu_editor.php?status=error&message=" . urlencode("Failed to delete category: " . $stmt->error));
        }
        $stmt->close();
        exit();
    }
}

// === 4. ITEM SAVE / UPDATE (with image upload) ===
if (isset($_POST['save_item']) || isset($_POST['update_item'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $current_image = $_POST['current_image'] ?? '';
    $image_path = $current_image;

    $price_small = isset($_POST['price_small']) && is_numeric($_POST['price_small']) ? floatval($_POST['price_small']) : NULL;
    $price_medium = isset($_POST['price_medium']) && is_numeric($_POST['price_medium']) ? floatval($_POST['price_medium']) : NULL;
    $price_large = isset($_POST['price_large']) && is_numeric($_POST['price_large']) ? floatval($_POST['price_large']) : NULL;

    // Normalize price NULL/0 -> NULL
    $price_small = ($price_small === NULL || $price_small <= 0) ? NULL : $price_small;
    $price_medium = ($price_medium === NULL || $price_medium <= 0) ? NULL : $price_medium;
    $price_large = ($price_large === NULL || $price_large <= 0) ? NULL : $price_large;

    // Convert empty description to NULL
    $description = ($description === '') ? NULL : $description;

    // Basic validation
    if ($name === '' || $category_id <= 0 || ($price_small === NULL && $price_medium === NULL && $price_large === NULL)) {
        $error_message[] = "Please fill in the Item Name, Category, and at least one Price tier.";
    } else {
        $upload_success = true;

        // Image upload handling
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            if ($file['size'] > $max_file_size) {
                $error_message[] = "Image file is too large (max 5MB).";
                $upload_success = false;
            } elseif (!in_array($file['type'], $allowed_image_types)) {
                $error_message[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                $upload_success = false;
            } else {
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_file_name = uniqid('menu_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (!is_dir($upload_dir)) {
                    // try to create upload dir (best-effort)
                    @mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $image_path = basename($destination);
                    // delete old file if updating
                    if ($item_id > 0 && !empty($current_image) && $current_image !== $image_path && $current_image !== 'placeholder.png') {
                        if (file_exists($upload_dir . $current_image)) {
                            @unlink($upload_dir . $current_image);
                        }
                    }
                } else {
                    $error_message[] = "Failed to move uploaded file.";
                    $upload_success = false;
                }
            }
        } elseif (isset($_POST['save_item']) && empty($current_image)) {
            // default placeholder if inserting and no upload
            $image_path = 'placeholder.png';
        }

        if ($upload_success && empty($error_message)) {
            if (isset($_POST['save_item'])) {
                // INSERT
                $query = "INSERT INTO menu_items (category_id, name, description, price_small, price_medium, price_large, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $con->prepare($query);
                if (!$stmt) {
                    $error_message[] = "Prepare failed: " . $con->error;
                } else {
                    // types: i (category_id), s (name), s (description), d (price_small), d (price_medium), d (price_large), s (image)
                    $stmt->bind_param("issddds", $category_id, $name, $description, $price_small, $price_medium, $price_large, $image_path);
                    if ($stmt->execute()) {
                        header('Location: menu_editor.php?status=success&message=' . urlencode('New item added successfully!'));
                        exit();
                    } else {
                        $error_message[] = "Failed to add item: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } elseif (isset($_POST['update_item']) && $item_id > 0) {
                // UPDATE
                $query = "UPDATE menu_items SET category_id = ?, name = ?, description = ?, price_small = ?, price_medium = ?, price_large = ?, image = ? WHERE item_id = ?";
                $stmt = $con->prepare($query);
                if (!$stmt) {
                    $error_message[] = "Prepare failed: " . $con->error;
                } else {
                    // types: i, s, s, d, d, d, s, i
                    $stmt->bind_param("issdddsi", $category_id, $name, $description, $price_small, $price_medium, $price_large, $image_path, $item_id);
                    if ($stmt->execute()) {
                        header('Location: menu_editor.php?status=success&message=' . urlencode('Item updated successfully!'));
                        exit();
                    } else {
                        $error_message[] = "Failed to update item: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// === 5. DELETE ITEM ===
if ($action == 'delete' && isset($_GET['id'])) {
    $item_id = intval($_GET['id']);

    $stmt_fetch = $con->prepare("SELECT image FROM menu_items WHERE item_id = ?");
    $stmt_fetch->bind_param("i", $item_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    $item = $result_fetch->fetch_assoc();
    $image_to_delete = $item['image'] ?? null;
    $stmt_fetch->close();

    $stmt = $con->prepare("DELETE FROM menu_items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    if ($stmt->execute()) {
        if (!empty($image_to_delete) && $image_to_delete !== 'placeholder.png' && file_exists($upload_dir . $image_to_delete)) {
            @unlink($upload_dir . $image_to_delete);
        }
        header('Location: menu_editor.php?status=success&message=' . urlencode("Item ID $item_id deleted successfully."));
        exit();
    } else {
        header('Location: menu_editor.php?status=error&message=' . urlencode("Failed to delete item: " . $stmt->error));
        exit();
    }
    $stmt->close();
}

// === 6. FETCH CATEGORIES ===
$categories = [];
$cat_query = "SELECT * FROM menu_categories ORDER BY category_name ASC";
$cat_result = mysqli_query($con, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[$row['category_id']] = $row;
    }
}

// === 7. FETCH ITEM DATA FOR EDIT MODE ===
if ($action == 'edit' && isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    $page_title = "Edit Menu Item ID $item_id";
    $stmt = $con->prepare("SELECT item_id, category_id, name, description, price_small, price_medium, price_large, image FROM menu_items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $menu_item_data = $result->fetch_assoc();
    } else {
        $error_message[] = "Item not found.";
        $action = 'list';
    }
    $stmt->close();
}

// === 8. FETCH ALL MENU ITEMS FOR LIST VIEW ===
$menu_data_grouped = [];
if ($action == 'list') {
    $page_title = "Menu Items List";
    $sql = "SELECT mi.item_id, mi.category_id, mi.name, mi.description, mi.price_small, mi.price_medium, mi.price_large, mi.image, mc.category_name 
            FROM menu_items mi
            JOIN menu_categories mc ON mi.category_id = mc.category_id
            ORDER BY mc.category_name, mi.name";
    $result = mysqli_query($con, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $menu_data_grouped[$row['category_name']][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title); ?> | Bat Cave Cafe Admin</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>  
    
</head>
<body>

<?php
// show inline messages
display_php_alert_messages($message, 'success');
display_php_alert_messages($error_message, 'error');

// Handle redirect messages
if (isset($_GET['status']) && isset($_GET['message'])) {
    $type = ($_GET['status'] === 'success') ? 'success' : 'error';
    $title = ($type === 'success') ? 'Success' : 'Error';
    $msg = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
    echo '<script>swal("' . $title . '", "' . addslashes($msg) . '", "' . $type . '");</script>';
}
?>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo-details"><i class='bx bxs-bat' style="color:#ffd27a;"></i><span class="logo_name">BatCave Admin</span></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class='bx bx-grid-alt'></i><span>Dashboard</span></a></li>
            <li><a href="bookings.php"><i class='bx bx-calendar-check'></i><span>Bookings</span></a></li>
            <li class="active-item"><a href="menu_editor.php" class="active"><i class='bx bx-dish'></i><span>Menu Editor</span></a></li>
            <li><a href="best_seller_manager.php"><i class='bx bx-certification'></i><span>Best Sellers</span></a></li>
            <li><a href="profile.php"><i class='bx bx-user-circle'></i><span>Admin Profile</span></a></li>
            <li><a href="logout.php"><i class='bx bx-log-out'></i><span>Logout</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav style="display:flex; justify-content:space-between; align-items:center;">
            <div class="sidebar-button"><span class="dashboard" style="font-weight:700;"><?php echo h('Menu Management'); ?></span></div>
            <div class="profile-details">
                    <span class="admin_name">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                    <i class='bx bx-user'></i>
                </div>
        </nav>

        <div class="home-content">
            <?php if ($action == 'list'): ?>
                <div class="heading-with-button">
                    <h2><?php echo h($page_title); ?></h2>
                    <a href="?action=add" class="btn-create-booking" title="Add a new menu item">
                        <i class='bx bx-plus-circle'></i> Add New Item
                    </a>
                </div>
                <div class="content-box-menu">
                    <div class="category-management" style="margin-bottom:20px;">
                        <h4 style="margin-top:0;color:var(--color-accent);">Manage Categories</h4>
                        <form method="POST" action="menu_editor.php" style="display:flex; gap:10px;">
                            <input type="text" name="category_name" placeholder="New Category Name (e.g., Coffee, Pastries)" required style="flex-grow:1;">
                            <button type="submit" name="add_category" class="btn-submit">Add Category</button>
                        </form>
                        <div style="margin-top:12px;">
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <span style="display:inline-flex; gap:8px; align-items:center; padding:6px 10px; background:#2b2b2b; border-radius:4px; margin-right:8px; margin-top:8px;">
                                        <?php echo h($cat['category_name']); ?> 
                                        <a href="menu_editor.php?delete_category_id=<?php echo $cat['category_id']; ?>" onclick="return confirm('WARNING! Deleting a category will FAIL if it still has items. Are you sure?');" title="Delete Category" style="color:#ff6666; text-decoration:none; margin-left:6px;"><i class='bx bx-trash'></i></a>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="font-style:italic;">No categories found. Please add one first.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3>Menu Items</h3>
                    <?php if (count($menu_data_grouped) > 0): ?>
                        <div style="overflow-x:auto;">
                        <table class="item-list-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Prices (S/M/L)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_data_grouped as $category_name => $items): ?>
                                    <tr><td colspan="5" class="category-header"><?php echo h($category_name); ?></td></tr>
                                    <?php foreach ($items as $item): ?>
                                        
                                            <td>
                                                <img src="../uploads/<?php echo h($item['image']); ?>" alt="<?php echo h($item['name']); ?>" class="item-image" onerror="this.onerror=null;this.src='../uploads/placeholder.png';">
                                            </td>
                                            <td><?php echo h($item['name']); ?></td>
                                            <td><?php 
                                                $description_display = $item['description'] ?? '';
                                                $short = mb_substr($description_display, 0, 70);
                                                echo h($short) . (mb_strlen($description_display) > 70 ? '...' : '');
                                            ?></td>
                                            <td class="price-display">
                                                <?php 
                                                    $prices = [
                                                        'S' => $item['price_small'],
                                                        'M' => $item['price_medium'],
                                                        'L' => $item['price_large'],
                                                    ];
                                                    foreach ($prices as $label => $price) {
                                                        if ($price !== null && $price !== '' && is_numeric($price)) {
                                                            echo "<p><strong>" . h($label) . ":</strong> ₱" . number_format((float)$price, 2) . "</p>";
                                                        }
                                                    }
                                                ?>
                                            </td>
                                            <td class="item-actions">
                                                <a href="?action=edit&id=<?php echo $item['item_id']; ?>" class="btn-edit-item" title="Edit Item"><i class='bx bx-edit' style="margin-right:6px;"></i>Edit</a>
                                                <a href="?action=delete&id=<?php echo $item['item_id']; ?>" class="btn-delete-item" onclick="return confirm('Are you sure you want to delete <?php echo h(addslashes($item['name'])); ?>?');" title="Delete Item"><i class='bx bx-trash' style="margin-right:6px;"></i>Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center;">No menu items found. Start by adding a category and an item.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($action == 'add' || $action == 'create' || $action == 'edit'):
                $is_edit = ($action == 'edit' && $menu_item_data);
                $form_title = $is_edit ? "Edit Menu Item" : "Add New Menu Item";
                $submit_name = $is_edit ? 'update_item' : 'save_item';

                $small_val = $is_edit && !is_null($menu_item_data['price_small']) ? h($menu_item_data['price_small']) : '';
                $medium_val = $is_edit && !is_null($menu_item_data['price_medium']) ? h($menu_item_data['price_medium']) : '';
                $large_val = $is_edit && !is_null($menu_item_data['price_large']) ? h($menu_item_data['price_large']) : '';
            ?>
                <h2><?php echo h($form_title); ?></h2>
                <div class="content-box-menu" style="max-width:600px; margin-left:auto; margin-right:auto;">
                    <form method="POST" action="menu_editor.php" enctype="multipart/form-data">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="item_id" value="<?php echo h($menu_item_data['item_id']); ?>">
                            <input type="hidden" name="current_image" value="<?php echo h($menu_item_data['image']); ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select name="category_id" id="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php if ($is_edit && $menu_item_data['category_id'] == $cat['category_id']) echo 'selected'; ?>><?php echo h($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="name">Item Name</label>
                            <input type="text" name="name" id="name" required value="<?php echo $is_edit ? h($menu_item_data['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Description (Optional)</label>
                            <textarea name="description" id="description"><?php echo $is_edit ? h($menu_item_data['description'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Prices (Leave blank or zero for unavailable size)</label>
                            <div class="price-group">
                                <div>
                                    <label for="price_small" style="font-weight:normal; font-size:0.9em;">Small (₱)</label>
                                    <input type="number" name="price_small" id="price_small" step="0.01" min="0" placeholder="e.g., 50.00" value="<?php echo $small_val; ?>">
                                </div>
                                <div>
                                    <label for="price_medium" style="font-weight:normal; font-size:0.9em;">Medium (₱)</label>
                                    <input type="number" name="price_medium" id="price_medium" step="0.01" min="0" placeholder="e.g., 75.00" value="<?php echo $medium_val; ?>">
                                </div>
                                <div>
                                    <label for="price_large" style="font-weight:normal; font-size:0.9em;">Large (₱)</label>
                                    <input type="number" name="price_large" id="price_large" step="0.01" min="0" placeholder="e.g., 100.00" value="<?php echo $large_val; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="image">Image File (Max 5MB, JPG/PNG/GIF) - Optional</label>
                            <input type="file" name="image" id="image">
                            <?php if ($is_edit && !empty($menu_item_data['image'])): ?>
                                <p style="margin-top:10px;">
                                    Current Image: <img src="../uploads/<?php echo h($menu_item_data['image']); ?>" alt="Current Image" style="width:80px; height:80px; object-fit:cover; border-radius:4px;">
                                </p>
                                <p style="font-size:0.9em; color:#ccc;">Upload a new file to replace the current image.</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $submit_name; ?>" class="btn-submit"><i class='bx bx-save' style="margin-right:6px;"></i> <?php echo $is_edit ? 'Update Item' : 'Save Item'; ?></button>
                            <a href="menu_editor.php" class="btn-cancel"><i class='bx bx-x' style="margin-right:6px;"></i> Cancel</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="content-box-menu">
                    <h2>Invalid Action</h2>
                    <p>The requested action is not valid. <a href="menu_editor.php">Go back to Menu List</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

</body>
</html>