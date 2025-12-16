<?php
require('db.php');
session_start();

$warning_msg = [];

if (isset($_POST['verify_email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Table updated from 'users' to 'admin', and column is admin_id
    $stmt = $con->prepare("SELECT admin_id FROM admins   WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Session key updated from 'reset_id' to 'admin_id' (or you can keep reset_id)
        $_SESSION['admin_reset_id'] = $row['admin_id'];

        header('Location: reset_password.php');
        exit();
    } else {
        $warning_msg[] = 'Email address not found in our records.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="wrapper">
        <form method="POST" action="">
            <h1>Forgot Password</h1>
            <p style="color:var(--color-text); text-align:center; margin-bottom: 25px;">Enter your email to reset your password.</p>
            
            <div class="input-box">
                <label class="login__label">Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
                <i class='bx bx-envelope login__icon'></i>
            </div>

            <button type="submit" name="verify_email" class="btn">Reset Password</button>

            <div class="register-link" style="margin-top: 25px;">
                <p>Remember your password? <a href="login.php">Log In</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <?php include 'alert.php';?>
</body>
</html>