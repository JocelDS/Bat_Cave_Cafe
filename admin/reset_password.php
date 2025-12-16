<?php
require('db.php');
session_start();

$warning_msg = [];
$success_msg = [];

// Check for the temporary reset session ID ('admin_reset_id')
if (!isset($_SESSION['admin_reset_id'])) {
    header('Location: forgot_password.php');
    exit();
}

$admin_id = $_SESSION['admin_reset_id'];

if (isset($_POST['reset_password'])) {
    $new_pass = trim($_POST['new_pass']);
    $c_pass = trim($_POST['c_pass']);

    if ($new_pass != $c_pass) {
        $warning_msg[] = 'Password confirmation does not match.';
    } elseif (strlen($new_pass) < 6) {
        $warning_msg[] = 'Password must be at least 6 characters long.';
    } else {
        $hashed_pass = sha1($new_pass);

        // Table updated from 'users' to 'admin', column is admin_id
        $stmt = $con->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
        $stmt->bind_param("si", $hashed_pass, $admin_id);

        if ($stmt->execute()) {
            $success_msg[] = 'Password successfully reset! Please log in.';
            
            // Clear the temporary reset session ID
            unset($_SESSION['admin_reset_id']);
            
            header('Refresh: 3; URL=login.php');
            exit();

        } else {
            $warning_msg[] = 'Database error during update.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <form method="POST" action="">
            <h1>Set New Password</h1>
            <p style="color:var(--color-text); text-align:center; margin-bottom: 25px;">Enter and confirm your new password.</p>
            
            <div class="input-box">
                <label class="login__label">New Password</label>
                <input type="password" name="new_pass" placeholder="Enter new password" minlength="6" required>
                <i class='bx bx-lock-alt login__icon'></i>
            </div>
            
            <div class="input-box">
                <label class="login__label">Confirm Password</label>
                <input type="password" name="c_pass" placeholder="Confirm new password" minlength="6" required>
                <i class='bx bx-lock login__icon'></i>
            </div>

            <button type="submit" name="reset_password" class="btn">Change Password</button>

            <div class="register-link" style="margin-top: 25px;">
                <p>Return to <a href="login.php">Log In</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <?php include 'alert.php';?>
    
    <?php 
    if (isset($success_msg)) {
        foreach ($success_msg as $msg) {
            echo '<script>swal("Success", "'.$msg.'", "success");</script>';
        }
    }
    ?>
</body>
</html>