<?php
    require('db.php');
    session_start();

    // --- I. ADMIN ACCESS & AUTHENTICATION (Session Management) ---

    if (!isset($_SESSION['admin_id']) && isset($_COOKIE['admin_id'])) {
        $_SESSION['admin_id'] = $_COOKIE['admin_id'];
    }

    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }

    $admin_id = $_SESSION['admin_id'];
    $query_admin_name = "SELECT name FROM admins WHERE admin_id = '$admin_id'";
    $result_admin_name = mysqli_query($con, $query_admin_name);
    $admin_name = ($result_admin_name && mysqli_num_rows($result_admin_name) > 0) ? mysqli_fetch_assoc($result_admin_name)['name'] : 'Admin';

    // --- II. KPI DATA FETCHING (Live Data from Database) ---
    $today = date('Y-m-d');
    $max_capacity = 20; // Set the maximum capacity

    // 1. Total Bookings of the Day
    $query_today = "SELECT COUNT(*) AS total FROM study_room_reservations WHERE reservation_date = '$today'";
    $result_today = mysqli_query($con, $query_today);
    $total_today = ($result_today && mysqli_num_rows($result_today) > 0) ? mysqli_fetch_assoc($result_today)['total'] : 0;

    // 2. Total Pending Reservations
    $query_pending = "SELECT COUNT(*) AS total FROM study_room_reservations WHERE status = 'Pending'";
    $result_pending = mysqli_query($con, $query_pending);
    $total_pending = ($result_pending && mysqli_num_rows($result_pending) > 0) ? mysqli_fetch_assoc($result_pending)['total'] : 0;

    // 3. Peak Booking Time (Linis na ang spacing)
    $last_week = date('Y-m-d', strtotime('-7 days'));
    $query_peak = "SELECT start_time, COUNT(*) AS count 
                FROM study_room_reservations 
                WHERE reservation_date >= '$last_week' 
                GROUP BY start_time 
                ORDER BY count DESC 
                LIMIT 1";
    $result_peak = mysqli_query($con, $query_peak);
    $peak_time = ($result_peak && mysqli_num_rows($result_peak) > 0) ? date('g:i A', strtotime(mysqli_fetch_assoc($result_peak)['start_time'])) : 'N/A';

    // 4. Total People for the Day (Excluding 'Rejected' AND 'Cancelled' status)
    $query_total_persons = "SELECT SUM(num_persons) AS total_persons FROM study_room_reservations WHERE reservation_date = '$today' AND status != 'Rejected' AND status != 'Cancelled'";
    $result_total_persons = mysqli_query($con, $query_total_persons);
    $total_persons_today = ($result_total_persons && mysqli_num_rows($result_total_persons) > 0) ? (int)mysqli_fetch_assoc($result_total_persons)['total_persons'] : 0;

    // Capacity calculation and styling logic
    $capacity_indicator = $total_persons_today > 0 
                        ? "Occupancy: " . round(($total_persons_today / $max_capacity) * 100) . "%" 
                        : "Room Capacity: {$max_capacity} pax";

    // Determine CSS class for the total people number
    $capacity_class = ($total_persons_today >= $max_capacity) 
                        ? 'critical-capacity' 
                        : '';

    // --- III. UPDATED DATA FETCHING: Today's Reservations ---
    $query_today_reservations = "SELECT reservation_id, full_name, start_time, num_hours, num_persons, purpose, status 
                                FROM study_room_reservations 
                                WHERE reservation_date = '$today'
                                ORDER BY FIELD(status, 'Pending', 'Confirmed', 'Rejected', 'Cancelled', 'Done'), start_time ASC";
    $result_today_res = mysqli_query($con, $query_today_reservations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Bat Cave Cafe Admin</title>
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
                <li><a href="dashboard.php" class="active"><i class='bx bx-grid-alt' ></i><span class="link_name">Dashboard</span></a></li>
                <li class="active-item"><a href="bookings.php"><i class='bx bx-calendar-check' ></i><span class="link_name">Bookings</span></a></li>
                <li><a href="menu_editor.php"><i class='bx bx-dish' ></i><span class="link_name">Menu Editor</span></a></li>
                <li><a href="best_seller_manager.php"><i class='bx bx-certification' ></i><span class="link_name">Best Sellers</span></a></li>
                <li><a href="profile.php"><i class='bx bx-user-circle' ></i><span class="link_name">Admin Profile</span></a></li>
                <li><a href="logout.php"><i class='bx bx-log-out'></i><span class="link_name">Logout</span></a></li>
            </ul>
        </div>

        <section class="home-section">
            <nav>
                <div class="sidebar-button">
                    <span class="dashboard">Dashboard (Main Control Center)</span>
                </div>
                <div class="profile-details">
                    <span class="admin_name">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                    <i class='bx bx-user'></i>
                </div>
            </nav>

            <div class="home-content">
                <h2>Dashboard Displays & Summary</h2>
                
                <div class="overview-boxes">
                    <div class="box">
                        <div class="right-side">
                            <div class="box-topic">Today's Bookings</div>
                            <div class="number"><?php echo $total_today; ?></div> 
                            <div class="indicator"><span class="text">Date: <?php echo $today; ?></span></div>
                        </div>
                        <i class='bx bx-calendar-event cart'></i>
                    </div>

                    <div class="box">
                        <div class="right-side">
                            <div class="box-topic">Pending Reservations</div>
                            <div class="number"><?php echo $total_pending; ?></div>
                            <div class="indicator"><span class="text">Awaiting Approval</span></div>
                        </div>
                        <i class='bx bx-loader-circle cart two'></i>
                    </div>

                    <div class="box">
                        <div class="right-side">
                            <div class="box-topic">Peak Booking Time</div>
                            <div class="number"><?php echo $peak_time; ?></div>
                            <div class="indicator"><span class="text">Most frequent time slot</span></div>
                        </div>
                        <i class='bx bx-time cart three'></i>
                    </div>

                    <div class="box">
                        <div class="right-side">
                            <div class="box-topic">Today's Total People</div>
                            <div class="number <?php echo $capacity_class; ?>"><?php echo $total_persons_today; ?></div>
                            <div class="indicator"><span class="text"><?php echo $capacity_indicator; ?></span></div>
                        </div>
                        <i class='bx bx-group cart four'></i> 
                    </div>
                </div>

                <div class="sales-boxes">
                    <div class="recent-sales box full-width-box">
                        <div class="title">Today's Study Room Bookings (<?php echo date('F j, Y'); ?>)</div>
                        <div class="sales-details">
                            <table class="recent-reservations-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Time</th>
                                        <th>Hours</th>
                                        <th>Pax</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Action</th> 
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($result_today_res && mysqli_num_rows($result_today_res) > 0) {
                                        while ($row = mysqli_fetch_assoc($result_today_res)) {
                                            // Format Time
                                            $res_time = date('g:i A', strtotime($row['start_time']));
                                            $status_class = strtolower($row['status']); 

                                            echo "<tr id='row-" . htmlspecialchars($row['reservation_id']) . "'>"; 
                                            echo "<td>" . htmlspecialchars($row['reservation_id']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                                            echo "<td>{$res_time}</td>";
                                            echo "<td>" . htmlspecialchars($row['num_hours']) . "h</td>"; 
                                            echo "<td>" . htmlspecialchars($row['num_persons']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
                                            echo "<td><span class='status-badge {$status_class}' id='status-" . htmlspecialchars($row['reservation_id']) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                            
                                            // --- UPDATED ACTION BUTTONS ---
                                            echo "<td class='action-cell' id='action-cell-" . htmlspecialchars($row['reservation_id']) . "'>";


                                        if ($row['status'] === 'Pending') {
                                            // 1. View, Approve, and Reject buttons for Pending
                                            echo "<a href='view_booking.php?id=" . htmlspecialchars($row['reservation_id']) . "' class='action-btn view' title='View Details'><i class='bx bx-search'></i></a>";
                                            echo "<a href='action_booking.php?id=" . $row['reservation_id'] . "&action=confirm' class='btn-approve' title='Confirm Booking'><i class='bx bx-check'></i></a>";
                                            echo "<a href='action_booking.php?id=" . $row['reservation_id'] . "&action=reject' class='btn-reject' title='Reject Booking'><i class='bx bx-x'></i></a>";
                                            } elseif ($row['status'] === 'Confirmed') {
                                            // 2. View, Done, and Cancel buttons for Approved bookings
                                            echo "<a href='view_booking.php?id=" . htmlspecialchars($row['reservation_id']) . "' class='action-btn view' title='View Details'><i class='bx bx-search'></i></a>";
                                            // --- START: ADDED 'DONE' BUTTON ---
                                            echo "<a href='action_booking.php?id=" . $row['reservation_id'] . "&action=done' class='btn-done' title='Mark as Done (Completed)'><i class='bx bx-party'></i></a>";
                                            // --- END: ADDED 'DONE' BUTTON ---
                                            echo "<a href='action_booking.php?id=" . $row['reservation_id'] . "&action=cancel' class='btn-cancel' title='Cancel Booking'><i class='bx bx-x-circle'></i></a>";
                                            } elseif ($row['status'] === 'Done') {
                                            // 3. View for Done bookings
                                            echo "<a href='view_booking.php?id=" . htmlspecialchars($row['reservation_id']) . "' class='action-btn view' title='View Details'><i class='bx bx-search'></i></a>";
                                            echo "<span class='action-text'>Completed</span>"; // Use a span for completed items
                                            } else {
                                            echo "<a href='view_booking.php?id=" . htmlspecialchars($row['reservation_id']) . "' class='action-btn view' title='View Details'><i class='bx bx-search'></i></a>";
                                            echo "<span class='action-text'>No Actions</span>";
                                            }
                                            echo "</td>";

                                        echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8'>No bookings scheduled for today.</td></tr>"; 
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                    </div>
                </div>

                <div class="sales-boxes" style="margin-top: 30px;">
                    <div class="recent-sales box full-width-box">
                        <div class="title">Quick Management Links</div>
                        
                        <div class="sales-details quick-links-grid">
                            
                            <a href="bookings.php" class="quick-link-item"><i class='bx bx-check-square'></i> Manage All Reservations</a>
                            <a href="bookings.php?filter=pending" class="quick-link-item"><i class='bx bx-filter'></i> Review Pending Requests (<?php echo $total_pending; ?>)</a>
                            
                            <a href="menu_editor.php?action=add" class="quick-link-item"><i class='bx bx-plus-circle'></i> Add New Menu Item</a>
                            <a href="menu_editor.php" class="quick-link-item"><i class='bx bx-edit'></i> Edit/Delete Existing Menu</a>

                            <a href="profile.php" class="quick-link-item"><i class='bx bx-user-circle'></i> Manage Admin Profile</a>
                            <a href="logout.php" class="quick-link-item logout-link"><i class='bx bx-log-out'></i> System Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</body>
</html>