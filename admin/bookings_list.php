<?php
require('db.php');
session_start();

// --- SECURITY CHECK (Same as menu_editor.php) ---
if (!isset($_SESSION['seller_id']) && !isset($_COOKIE['seller_id'])) {
    header('Location: login.php');
    exit();
}
// ------------------------------------------------

$bookings = [];
$error_message = '';

// Kuhanin ang lahat ng reservations
$sql = "SELECT booking_id, full_name, date, time, persons, purpose, total_fee, created_at FROM reservations ORDER BY created_at DESC";
$result = $con->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
} else {
    $error_message = "Error fetching bookings: " . $con->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Admin</title>
    <link rel="stylesheet" href="admin_style.css"> 
    <style>
        .table-container {
            max-width: 90%;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .btn-view {
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <div class="table-container">
        <h1>Current Room Reservations</h1>
        <p><a href="dashboard.php">&larr; Back to Dashboard</a></p>

        <?php if (!empty($error_message)): ?>
            <div style="color: red; padding: 10px; border: 1px solid red;"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <p>No reservations found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Persons</th>
                        <th>Purpose</th>
                        <th>Fee</th>
                        <th>Date Booked</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($booking['date']); ?></td>
                        <td><?php echo htmlspecialchars($booking['time']); ?></td>
                        <td><?php echo htmlspecialchars($booking['persons']); ?></td>
                        <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                        <td>₱ <?php echo number_format($booking['total_fee'], 2); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($booking['created_at'])); ?></td>
                        <td>
                            <a href="booking_details.php?id=<?php echo $booking['booking_id']; ?>" class="btn-view">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</body>
</html>