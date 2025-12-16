<?php
require('db.php'); 
session_start();

// --- 1. ADMIN ACCESS & AUTHENTICATION ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// --- 2. PHP LOGIC FOR FETCHING DETAILS ---
$booking_data = null;
$error_message = '';
$reservation_id = 0;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $reservation_id = (int)$_GET['id'];
    
    // Use Prepared Statements for security (SQL Injection protection)
    $sql = "SELECT * FROM study_room_reservations WHERE reservation_id = ?";
    $stmt = $con->prepare($sql);
    
    if ($stmt === false) {
        $error_message = "Database error: Failed to prepare statement. " . $con->error;
    } else {
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $booking_data = $result->fetch_assoc();
        } else {
            $error_message = "Reservation with ID #{$reservation_id} not found.";
        }
        $stmt->close();
    }
} else {
    $error_message = "Invalid or missing Reservation ID.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details | Bat Cave Cafe Admin</title>
    <link rel="stylesheet" href="adminstyle.css"> 
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --color-surface: #3A3A3A;
            --color-text: #F5EDE1;
            --color-base: #2B2B24;
            --color-accent: #DDA441;
        }
        .details-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            background: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            color: var(--color-text);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--color-base);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: var(--color-accent);
            flex: 1;
        }
        .value {
            text-align: right;
            flex: 2;
        }
        .fee-total {
            font-size: 1.6em;
            font-weight: bold;
            color: #fff;
        }
        .alert-error {
            background-color: #800000;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        h1 {
            color: var(--color-accent);
            border-bottom: 2px solid var(--color-accent);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .btn-action {
            display: inline-block;
            padding: 8px 15px;
            margin-top: 20px;
            text-decoration: none;
            background-color: var(--color-accent);
            color: var(--color-base);
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-action:hover {
            background-color: #f0c97b;
        }
    </style>
</head>
<body>

    <div class="details-container">
        <h1>Reservation Details (ID #<?php echo $reservation_id; ?>)</h1>
        <p><a href="bookings.php" class="btn-action" style="background-color: #555;">&larr; Back to Booking List</a></p>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                ❗ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php elseif ($booking_data): ?>
            
            <div class="detail-row">
                <span class="label">Full Name:</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['full_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="label">Contact Email:</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['email']); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Contact Phone:</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['phone_number']); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Reservation Date:</span>
                <span class="value"><?php echo date('F j, Y', strtotime($booking_data['reservation_date'])); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Time (Start):</span>
                <span class="value"><?php echo date('h:i A', strtotime($booking_data['start_time'])); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Duration (Hours):</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['num_hours']); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Number of Persons:</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['num_persons']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="label">Purpose:</span>
                <span class="value"><?php echo htmlspecialchars($booking_data['purpose']); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Projector Included:</span>
                <span class="value"><?php echo $booking_data['projector'] ? 'Yes' : 'No'; ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Speaker/Mic Included:</span>
                <span class="value"><?php echo $booking_data['speaker_mic'] ? 'Yes' : 'No'; ?></span>
            </div>
            
            <div class="detail-row" style="background-color: var(--color-base); border-radius: 4px; padding: 15px;">
                <span class="label fee-total">STATUS:</span>
                <span class="value fee-total status-<?php echo str_replace(' ', '', $booking_data['status']); ?>"><?php echo htmlspecialchars($booking_data['status']); ?></span>
            </div>

            <div class="detail-row">
                <span class="label">Date Booked (Record):</span>
                <span class="value"><?php echo date('Y-m-d h:i A', strtotime($booking_data['created_at'])); ?></span>
            </div>

            <div class="detail-row" style="margin-top: 20px;">
                <span class="label fee-total">ESTIMATED FEE:</span>
                <span class="value fee-total">₱ <?php echo number_format($booking_data['estimated_fee'], 2); ?></span>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <?php if ($booking_data['status'] === 'Pending'): ?>
                    <a href="action_booking.php?id=<?php echo $reservation_id; ?>&action=confirm" class="btn-action" style="background-color: #008000; color: white;">Confirm Booking</a>
                    <a href="action_booking.php?id=<?php echo $reservation_id; ?>&action=reject" class="btn-action" style="background-color: #800000; color: white;">Reject Booking</a>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

</body>
</html> 