<?php
    require('db.php');
    session_start();

    // Check if admin is logged in, otherwise redirect to login page
    if(!isset($_SESSION['admin_id'])){
        header('Location: login.php');
        exit;
    }

    $admin_id = $_SESSION['admin_id'];

    // Fetch admin name for display
    $query_admin_name = "SELECT name FROM admins WHERE admin_id = '$admin_id'";
    $result_admin_name = mysqli_query($con, $query_admin_name);
    // Use 'Admin' as default name if fetch fails
    $admin_name = ($result_admin_name && mysqli_num_rows($result_admin_name) > 0) ? mysqli_fetch_assoc($result_admin_name)['name'] : 'Admin';

    // --- Filtering Logic ---
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $filter_clause = '';
    $page_title = 'All Reservations';

    if($filter === 'pending'){
        $filter_clause = "WHERE status='Pending'";
        $page_title = 'Pending Reservations';
    } elseif($filter === 'confirmed'){
        $filter_clause = "WHERE status='Confirmed'";
        $page_title = 'Confirmed Reservations';
    } elseif($filter === 'rejected'){
        $filter_clause = "WHERE status='Rejected'";
        $page_title = 'Rejected Reservations';
    } elseif($filter === 'done'){
        $filter_clause = "WHERE status='Done'";
        $page_title = 'Completed Reservations';
    }

    // --- Sorting Logic (FIX 1) ---
    // Custom sorting: Pending, Confirmed first, then others by date/time
    if($filter === 'all'){
        $order_by = "ORDER BY FIELD(status,'Pending','Confirmed','Rejected','Cancelled','Done'), reservation_date DESC, start_time ASC";
    } else {
        // For specific filters, sort by date/time only
        $order_by = "ORDER BY reservation_date DESC, start_time ASC";
    }

    // Full query to fetch reservations
    $query = "SELECT * FROM study_room_reservations $filter_clause $order_by";
    $result = mysqli_query($con, $query);
    $bookings = [];

    // Fetch all results into the $bookings array
    if($result){
        while($row = mysqli_fetch_assoc($result)){
            $bookings[] = $row;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?> | Bat Cave Cafe Admin</title>
<link rel="stylesheet" href="admin_style.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo-details">
            <i class='bx bxs-bat'></i>
            <span class="logo_name">BatCave Admin</span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class='bx bx-grid-alt'></i><span class="link_name">Dashboard</span></a></li>
            <li class="active-item"><a href="bookings.php" class="active"><i class='bx bx-calendar-check'></i><span class="link_name">Bookings</span></a></li>
            <li><a href="menu_editor.php"><i class='bx bx-dish'></i><span class="link_name">Menu Editor</span></a></li>
            <li><a href="best_seller_manager.php"><i class='bx bx-certification'></i><span class="link_name">Best Sellers</span></a></li>
            <li><a href="profile.php"><i class='bx bx-user-circle'></i><span class="link_name">Admin Profile</span></a></li>
            <li><a href="logout.php"><i class='bx bx-log-out'></i><span class="link_name">Logout</span></a></li>
        </ul>
    </div>
    
    <section class="home-section">
        <nav>
            <div class="sidebar-button">
                <span class="dashboard">Booking Management</span>
            </div>
            <div class="profile-details">
                <span class="admin_name">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                <i class='bx bx-user'></i>
            </div>
        </nav>

        <div class="home-content">
            <div class="heading-with-button">
                <h2><?php echo $page_title; ?></h2>
                <a href="../booking.php" class="btn-create-booking" title="Manually create a new reservation">
                    <i class='bx bx-plus-circle'></i> New Booking
                </a>
            </div>

            <div class="filter-controls">
                <a href="bookings.php?filter=all" class="<?php echo ($filter === 'all' ? 'active' : ''); ?>">All</a>
                <a href="bookings.php?filter=pending" class="<?php echo ($filter === 'pending' ? 'active' : ''); ?>">Pending</a>
                <a href="bookings.php?filter=confirmed" class="<?php echo ($filter === 'confirmed' ? 'active' : ''); ?>">Confirmed</a>
                <a href="bookings.php?filter=rejected" class="<?php echo ($filter === 'rejected' ? 'active' : ''); ?>">Rejected</a>
                <a href="bookings.php?filter=done" class="<?php echo ($filter === 'done' ? 'active' : ''); ?>">Done</a>
            </div>

            <div class="bookings-table-container">
                <?php if (count($bookings) > 0) : ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client Name</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Persons</th>
                                <th>Hours</th>
                                <th>Purpose</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking) :
                                // Determine CSS class for status tag
                                $status_class = 'status-' . str_replace(' ', '', strtolower($booking['status']));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['reservation_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['reservation_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['start_time']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['num_persons']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['num_hours']) . ' hrs'; ?></td>
                                    <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                                    <td>₱<?php echo number_format($booking['estimated_fee'], 2); ?></td>
                                    <td><span class="status-tag <?php echo $status_class; ?>"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                    <td class="action-btns" id="action-cell-<?php echo htmlspecialchars($booking['reservation_id']); ?>">
                                        <a href="view_booking.php?id=<?php echo $booking['reservation_id']; ?>" class="btn-view" title="View Full Details"><i class='bx bx-search'></i></a>
                                        
                                        <?php if ($booking['status'] === 'Pending') : ?>
                                            <a href="action_booking.php?id=<?php echo $booking['reservation_id']; ?>&action=confirm&redirect=bookings.php" class="btn-approve" title="Confirm Booking"><i class='bx bx-check'></i></a>
                                            <a href="action_booking.php?id=<?php echo $booking['reservation_id']; ?>&action=reject&redirect=bookings.php" class="btn-reject" title="Reject Booking"><i class='bx bx-x'></i></a>
                                        <?php elseif ($booking['status'] === 'Confirmed') : ?>
                                            <a href="action_booking.php?id=<?php echo $booking['reservation_id']; ?>&action=done&redirect=bookings.php" class="btn-done" title="Mark as Done"><i class='bx bx-check-double'></i></a>
                                            <a href="action_booking.php?id=<?php echo $booking['reservation_id']; ?>&action=cancel&redirect=bookings.php" class="btn-cancel" title="Cancel Booking"><i class='bx bx-x-circle'></i></a>
                                        <?php elseif ($booking['status'] === 'Done') : ?>
                                            <span class="action-text">Completed</span>
                                        <?php else : ?>
                                            <span class="action-text">No actions</span>
                                        <?php endif; ?>
                                        
                                        <a href="action_booking.php?id=<?php echo $booking['reservation_id']; ?>&action=delete&redirect=bookings.php" class="btn-reject" style="background-color:#555;" title="Delete Record" onclick="return confirm('Are you sure you want to permanently delete this record?');"><i class='bx bx-trash'></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p style="padding:20px;text-align:center;color:#ccc;">No reservations found for the '<?php echo htmlspecialchars($filter); ?>' filter.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
</body>
</html>