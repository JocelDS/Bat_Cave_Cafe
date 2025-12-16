<?php
require('db.php'); // Assumes this file connects to the database and sets $con
session_start();

// --- 1. ADMIN AUTHENTICATION ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$page_title = "Admin Profile";
$message = [];
$error_message = [];
$admin_data = [];

// Helper functions (assuming they are not in db.php)
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function display_php_alert_messages($messages, $type) {
    foreach ($messages as $msg) {
        echo '<script>swal("' . ucfirst($type) . '", "' . addslashes($msg) . '", "' . $type . '");</script>';
    }
}

// === 2. FETCH CURRENT ADMIN DATA ===
$stmt_fetch = $con->prepare("SELECT name FROM admins WHERE admin_id = ?");
$stmt_fetch->bind_param("i", $admin_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();
if ($result_fetch->num_rows === 0) {
    // If admin ID is somehow invalid, log out
    session_destroy();
    header('Location: login.php?error=' . urlencode('Admin ID not found. Please log in again.'));
    exit();
}
$admin_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();


// === 3. HANDLE PROFILE UPDATE (Username/Password) ===
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $update_success = false;
    $fields_to_update = [];
    $bind_types = "";
    $bind_params = [];
    
    // --- Username Update ---
    if (!empty($new_username) && $new_username !== $admin_data['name']) {
        if (strlen($new_username) < 3) {
            $error_message[] = "Username must be at least 3 characters long.";
        } else {
            // Check if username is already taken
            $stmt_check = $con->prepare("SELECT admin_id FROM admins WHERE name = ? AND admin_id != ?");
            $stmt_check->bind_param("si", $new_username, $admin_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error_message[] = "Username '{$new_username}' is already taken.";
            } else {
                $fields_to_update[] = "name = ?";
                $bind_types .= "s";
                $bind_params[] = $new_username;
            }
            $stmt_check->close();
        }
    }

    // --- Password Update ---
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $error_message[] = "Password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message[] = "New password and confirmation password do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $fields_to_update[] = "admin_password = ?";
            $bind_types .= "s";
            $bind_params[] = $hashed_password;
        }
    }

    // --- Execute Update ---
    if (empty($error_message) && !empty($fields_to_update)) {
        $query = "UPDATE admins SET " . implode(', ', $fields_to_update) . " WHERE admin_id = ?";
        
        // Add admin_id to binding parameters
        $bind_types .= "i";
        $bind_params[] = $admin_id;

        $stmt = $con->prepare($query);
        if (!$stmt) {
             $error_message[] = "Prepare statement failed: " . $con->error;
        } else {
            // Bind parameters dynamically
            $stmt->bind_param($bind_types, ...$bind_params);
            
            if ($stmt->execute()) {
                $update_success = true;
                $message[] = "Profile updated successfully!";
                // Refresh data after successful update
                $admin_data['name'] = $new_username;
            } else {
                $error_message[] = "Failed to update profile: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (empty($fields_to_update) && empty($error_message)) {
        $message[] = "No changes were submitted.";
    }
    
    // Redirect on success to clear POST data and show message
    if ($update_success) {
         header('Location: profile.php?status=success&message=' . urlencode('Profile updated successfully!'));
         exit();
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
            <li><a href="menu_editor.php"><i class='bx bx-dish'></i><span>Menu Editor</span></a></li>
            <li><a href="best_seller_manager.php"><i class='bx bx-certification'></i><span>Best Sellers</span></a></li>
            <li class="active-item"><a href="profile.php" class="active"><i class='bx bx-user-circle'></i><span>Admin Profile</span></a></li>
            <li><a href="logout.php"><i class='bx bx-log-out'></i><span>Logout</span></a></li>
        </ul>
    </div>

    <section class="home-section">
        <nav style="display:flex; justify-content:space-between; align-items:center;">
            <div class="sidebar-button"><span class="dashboard" style="font-weight:700;"><?php echo h($page_title); ?></span></div>
            <div class="profile-details"><span class="admin_name">Welcome <?php echo h($admin_data['name']); ?>!</span><i class='bx bx-user'></i></div>
        </nav>

        <div class="home-content">
            <div class="content-box-menu" style="max-width:500px; margin: 20px auto;">
                <h2 style="margin-top:0;">Update Your Account</h2>
                <p>Use the form below to update your username and/or password. You must fill out the password fields if you wish to change your password.</p>

                <form method="POST" action="profile.php">
                    
                    <div class="form-group">
                        <label for="current_username">Current Username</label>
                        <input type="text" id="current_username" value="<?php echo h($admin_data['name']); ?>" disabled style="background:#444;">
                    </div>

                    <div class="form-group">
                        <label for="new_username">New Username (Leave blank to keep current)</label>
                        <input type="text" name="new_username" id="new_username" minlength="3" placeholder="Enter new username" value="<?php echo h($_POST['new_username'] ?? ''); ?>">
                    </div>
                    
                    <hr style="margin: 25px 0; border-color: #555;">

                    <div class="form-group">
                        <label for="new_password">New Password (Min 6 characters. Leave blank to keep current)</label>
                        <input type="password" name="new_password" id="new_password" minlength="6" placeholder="Enter new password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password">
                    </div>

                    <div class="form-actions" style="margin-top:20px;">
                        <button type="submit" name="update_profile" class="btn-submit"><i class='bx bx-refresh' style="margin-right:6px;"></i> Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

</body>
</html>