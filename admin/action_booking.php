<?php
require('db.php'); 
session_start();

// --- 1. ADMIN AUTHENTICATION ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// --- 2. INPUT VALIDATION ---
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Stop if required parameters are missing
if ($reservation_id <= 0 || empty($action)) {
    // FIX: Redirect to dashboard.php for invalid request
    header('Location: $redirect_url?msg=invalid_request');
    exit();
}

// Prepare for database queries
$table = 'study_room_reservations';
// FIX: Pinalitan mula 'bookings.php' sa 'dashboard.php'
$redirect_url = 'bookings.php';
$query = "";
$message = "";

// --- 3. ACTION LOGIC ---
switch ($action) {
    case 'confirm':
        // FIX: Changed 'Approved' to 'Confirmed' to match bookings.php display/filter logic
        $query = "UPDATE $table SET status = 'Confirmed' WHERE reservation_id = ?";
        $message = "Reservation ID $reservation_id confirmed successfully!";
        break;

    case 'reject':
        // Update the status to 'Rejected'
        $query = "UPDATE $table SET status = 'Rejected' WHERE reservation_id = ?";
        $message = "Reservation ID $reservation_id rejected.";
        break;

    case 'cancel':
        // Update the status to 'Cancelled'
        $query = "UPDATE $table SET status = 'Cancelled' WHERE reservation_id = ?"; // Removed 'FROM'
        $message = "Reservation ID $reservation_id cancelled successfully.";
        break;

    case 'done':
        $query = "UPDATE $table SET status = 'Done' WHERE reservation_id = ?";
        // FIX: Corrected success message (dati ay "rejected.")
        $message = "Reservation ID $reservation_id marked as done.";
        break;

    case 'delete':
        // ADDED: Logic for the 'Delete' action
        $query = "DELETE FROM $table WHERE reservation_id = ?";
        $message = "Reservation ID $reservation_id permanently deleted.";
        break;

    default:
        // Invalid action specified
        // FIX: Redirect to dashboard.php
        header('Location: $redirect_url?msg=invalid_action');
        exit();
}

// --- 4. EXECUTE QUERY ---
if (!empty($query)) {
    // Use prepared statement for security (prevents SQL injection)
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $reservation_id); 

    if ($stmt->execute()) {
        // Successful execution (mag-reredirect na sa dashboard.php)
        header("Location: $redirect_url?status=success&message=" . urlencode($message));
        exit();
    } else {
        // Query failed
        $error_message = "Database Error: " . $stmt->error;
        header("Location: $redirect_url?status=error&message=" . urlencode($error_message));
        exit();
    }

    $stmt->close();
}
// mysqli_close($con); 
?>