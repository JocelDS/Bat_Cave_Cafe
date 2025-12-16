<?php
require('db.php'); 
session_start();

// --- 1. ADMIN ACCESS & AUTHENTICATION ---
if (!isset($_SESSION['admin_id'])) { // Gumamit ng admin_id para maging consistent sa bookings.php
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
    $query_admin_name = "SELECT name FROM admins WHERE admin_id = '$admin_id'";
    $result_admin_name = mysqli_query($con, $query_admin_name);
    $admin_name = ($result_admin_name && mysqli_num_rows($result_admin_name) > 0) ? mysqli_fetch_assoc($result_admin_name)['name'] : 'Admin';

// --- 2. PHP LOGIC FOR MANAGEMENT ---
$success_message = '';
$error_message = [];

// Kuhanin ang listahan ng KASALUKUYANG Best Sellers
$current_best_sellers = [];
// FIX: Ensure price_small is fetched for consistency
$query_current = "
    SELECT b.best_id, m.name, m.price_small AS price, m.image 
    FROM best_sellers b
    JOIN menu_items m ON b.item_id = m.item_id
    ORDER BY b.created_at DESC
";
$result_current = $con->query($query_current);
if ($result_current) {
    while ($row = $result_current->fetch_assoc()) {
        $current_best_sellers[] = $row;
    }
}

// Kuhanin ang listahan ng LAHAT ng menu items (para sa dropdown)
$all_menu_items = [];
// FIX: Ensure price_small is used for the dropdown selection
$query_all = "SELECT item_id, name, price_small AS price FROM menu_items ORDER BY name ASC";
$result_all = $con->query($query_all);
if ($result_all) {
    while ($row = $result_all->fetch_assoc()) {
        $all_menu_items[] = $row;
    }
}

// --- 3. LOGIC FOR ADDING A BEST SELLER ---
if (isset($_POST['add_best_seller'])) {
    $item_id = (int)$_POST['item_id'];
    
    // FIX: Define the maximum limit as 4
    $max_best_sellers = 4;
    
    if ($item_id > 0) {
        // Tiyakin na hindi pa ito Best Seller
        $check_stmt = $con->prepare("SELECT COUNT(*) FROM best_sellers WHERE item_id = ?");
        $check_stmt->bind_param("i", $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_row()[0];
        $check_stmt->close();

        if ($check_result > 0) {
            $error_message[] = "The selected item is already a Best Seller.";
        } 
        // FIX: Check against the new limit of 4
        elseif (count($current_best_sellers) >= $max_best_sellers) {
            $error_message[] = "You can only have a maximum of {$max_best_sellers} Best Sellers. Please remove one first.";
        } else {
            // I-insert sa best_sellers table
            $insert_stmt = $con->prepare("INSERT INTO best_sellers (item_id, created_at) VALUES (?, NOW())");
            $insert_stmt->bind_param("i", $item_id);
            if ($insert_stmt->execute()) {
                $success_message = "Best Seller added successfully!";
                // I-refresh ang page para makita ang pagbabago
                header("Location: best_seller_manager.php");
                exit();
            } else {
                $error_message[] = "Error adding item: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
    } else {
        $error_message[] = "Please select a valid menu item.";
    }
}

// --- 4. LOGIC FOR REMOVING A BEST SELLER ---
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $best_id = (int)$_GET['id'];
    
    $delete_stmt = $con->prepare("DELETE FROM best_sellers WHERE best_id = ?");
    $delete_stmt->bind_param("i", $best_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "Best Seller removed successfully!";
        // I-refresh ang page para makita ang pagbabago
        header("Location: best_seller_manager.php");
        exit();
    } else {
        $error_message[] = "Error removing item: " . $delete_stmt->error;
    }
    $delete_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Best Sellers | Bat Cave Cafe Admin</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    
    <div class="dashboard-container">
        <div class="dashboard-container">
        
        <div class="sidebar">
            <div class="logo-details">
                <i class='bx bxs-bat'></i>
                <span class="logo_name">BatCave Admin</span>
            </div>
            
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class='bx bx-grid-alt' ></i><span class="link_name">Dashboard</span></a></li>
                
                <li><a href="bookings.php"><i class='bx bx-calendar-check' ></i><span class="link_name">Bookings</span></a></li>
                
                <li><a href="menu_editor.php"><i class='bx bx-dish' ></i><span class="link_name">Menu Editor</span></a></li>
                
                <li class="active-item"><a href="best_seller_manager.php" class="active"><i class='bx bx-certification' ></i><span class="link_name">Best Sellers</span></a></li>
                
                <li><a href="profile.php"><i class='bx bx-user-circle' ></i><span class="link_name">Admin Profile</span></a></li>

                <li><a href="logout.php"><i class='bx bx-log-out'></i><span class="link_name">Logout</span></a></li>
            </ul>
        </div>  
        <section class="home-section">
            <nav>
                <div class="sidebar-button">
                    <span class="dashboard">Best Seller Manager</span> 
                </div>
                <div class="profile-details">
                    <span class="admin_name">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                    <i class='bx bx-user'></i>
                </div>
            </nav>

            <div class="home-content">
                <h2>Manage Best Sellers / Recommendations</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert-error">
                        <?php foreach($error_message as $msg) echo "<p>{$msg}</p>"; ?>
                    </div>
                <?php endif; ?>

                <div class="manager-container">
                    
                    <h3>Add New Best Seller</h3>
                    <form method="POST" action="best_seller_manager.php" class="add-form">
                        <select name="item_id" required>
                            <option value="">-- Select a Menu Item to Promote --</option>
                            <?php foreach ($all_menu_items as $item): ?>
                                <option value="<?php echo $item['item_id']; ?>">
                                    <?php echo htmlspecialchars($item['name']) . ' (₱' . number_format($item['price'], 2) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="add_best_seller" class="btn">Add to Best Sellers</button>
                    </form>

                    <h3>Current Best Sellers (<?php echo count($current_best_sellers); ?>/4)</h3>
                    <div class="current-list">
                        <?php if (empty($current_best_sellers)): ?>
                            <p style="width: 100%; text-align: center; color: #718096;">No items currently promoted.</p>
                        <?php else: ?>
                            <?php foreach ($current_best_sellers as $item): ?>
                            <div class="list-item">
                                <img src="<?php echo htmlspecialchars('../uploads/' . $item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p>₱<?php echo number_format($item['price'], 2); ?></p>
                                
                                <a href="best_seller_manager.php?action=remove&id=<?php echo $item['best_id']; ?>" 
                                   class="btn-remove" 
                                   onclick="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($item['name']); ?> from Best Sellers?');">
                                    Remove
                                </a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>
    </div>

</body>
</html> 