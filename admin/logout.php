<?php
session_start();

// 1. Unset Session
$_SESSION = array();
session_destroy();

// 2. Destroy Cookie (Set time to past)
if (isset($_COOKIE['seller_id'])) {
    unset($_COOKIE['seller_id']); 
    setcookie('seller_id', '', time() - 3600, '/'); 
}

// 3. Redirect
header("Location: login.php");
exit();
?>