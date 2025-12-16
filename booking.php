<?php 
// 1. Database Connection
require('db.php'); 

// --- Server-Side Pricing Constants ---
define('BASE_RATE', 50.00);      
define('MIN_FEE', 75.00);    
define('EQUIP_RATE', 150.00); 
define('MAX_CAPACITY', 20); // Max persons
define('MAX_HOURS', 12); // Max hours limit

// Set default timezone (Manila)
date_default_timezone_set('Asia/Manila'); // *** FIX: ensure consistent timezone for all time ops

// --- Initialization of variables for Modal Display ---
$message_type = '';
$message = '';
$message_status = 'none'; 

/**
 * Calculates the total estimated fee
 */
function calculate_server_fee($hours, $persons, $has_projector, $has_speaker_mic) {
    if ($hours < 1 || $persons < 1) return 0.00;

    $total = BASE_RATE * $persons * $hours; // Base rate is 50.00 PHP per person per hour

    // Minimum Fee Rule
    if ($hours < 2) {
        $total += MIN_FEE; // Minimum fee is 75.00 PHP
    }

    // Equipment Fee
    if ($has_projector) $total += EQUIP_RATE * $hours; // Equipment rate is 150.00 PHP per hour per item
    if ($has_speaker_mic) $total += EQUIP_RATE * $hours;
    
    return $total;
}

/**
 * Checks for time and capacity conflicts
 */
function check_room_availability($con, $date, $time, $hours, $persons, $purpose) {
    // Use DateTimeImmutable with timezone for robust parsing
    $tz = new DateTimeZone('Asia/Manila');
    $startDT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);

    if (!$startDT) return 'TIME_PARSE_ERROR';
    $startTs = $startDT->getTimestamp();
    $endDT = $startDT->modify("+{$hours} hours");
    $endTs = $endDT->getTimestamp();

    // Prepare DB query; ensure start_time is stored in 'H:i:s' or similar
    $sql = "SELECT num_persons, purpose, start_time, num_hours 
            FROM study_room_reservations 
            WHERE reservation_date = ? AND status IN ('Pending', 'Confirmed')";
             
    if (!($stmt = mysqli_prepare($con, $sql))) {
        return 'DB_ERROR';
    }
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        return 'DB_ERROR';
    }

    $existing_persons_in_overlap = 0;

    while ($r = mysqli_fetch_assoc($result)) {
        // Ensure row start_time is parseable
        $rowStartDT = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $r['start_time'], $tz);
        if (!$rowStartDT) {
            // Try without seconds (H:i)
            $rowStartDT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $r['start_time'], $tz);
        }
        if (!$rowStartDT) {
            // If still failing, skip this record but note a parse error could exist
            continue;
        }
        $rowEndDT = $rowStartDT->modify("+{$r['num_hours']} hours");

        // Overlap check: start < other_end && end > other_start
        $overlap = ($startDT < $rowEndDT) && ($endDT > $rowStartDT);

        if ($overlap) {
            // Gathering (Event) blocks all other bookings
            if ($r['purpose'] === "Gathering") return "BLOCKED_EVENT"; 
            // Gathering (Event) cannot be booked if any study booking exists in the overlap
            if ($purpose === "Gathering") return "BLOCKED_BY_STUDY";
            $existing_persons_in_overlap += (int)$r['num_persons'];
        }
    }
    
    $total_persons = $existing_persons_in_overlap + $persons;
    if ($total_persons > MAX_CAPACITY) { // Total capacity is 20
        $remaining = MAX_CAPACITY - $existing_persons_in_overlap;
        if ($remaining < 0) $remaining = 0;
        return "ONLY_{$remaining}_LEFT";
    }

    return "OK";
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $studentId = trim($_POST['studentId'] ?? '');
    $reservationDate = $_POST['reservationDate'] ?? '';
    $startTime = $_POST['startTime'] ?? '';
    $numHours = (int)($_POST['numHours'] ?? 0);
    $numPersons = (int)($_POST['numPersons'] ?? 0);
    $purpose = $_POST['reservationPurpose'] ?? '';
    
    $projector = isset($_POST['projector']) ? 1 : 0;
    $speakerMic = isset($_POST['speakerMic']) ? 1 : 0;

    // VALIDATION: Check for required fields, minimums, maximum hours, and maximum capacity
    $is_data_invalid = empty($fullName) || empty($email) || empty($reservationDate) || empty($startTime) || empty($phoneNumber) || 
                       $numHours < 1 || $numHours > MAX_HOURS || $numPersons < 1 || $numPersons > MAX_CAPACITY; // MAX_CAPACITY check added to is_data_invalid for consistency

    if ($purpose === 'Study' && empty($studentId)) $is_data_invalid = true;

    // Parse start datetime using DateTimeImmutable + timezone
    $tz = new DateTimeZone('Asia/Manila'); // *** FIX: consistent tz
    $dateTimeObj = DateTimeImmutable::createFromFormat('Y-m-d H:i', $reservationDate . ' ' . $startTime, $tz);
    $startTimeStamp = $dateTimeObj ? $dateTimeObj->getTimestamp() : false;
    $time_parse_error = ($startTimeStamp === false);

    if ($time_parse_error) $is_data_invalid = true; 

    if ($is_data_invalid) {
        $message_type = 'message-error';
        
        // --- FIX APPLIED HERE: Specific error messages for count limits ---
        if ($time_parse_error) {
             $message = '<p>❗ Invalid Time Format. Please use a valid time (e.g., 13:00 for 1 PM).</p>';
        } elseif ($numHours > MAX_HOURS) { 
             $message = '<p>❗ Reservation limit is ' . MAX_HOURS . ' hours. Please reduce the number of hours.</p>';
        } elseif ($numPersons > MAX_CAPACITY) { // *** NEW CHECK
             $message = '<p>❗ Maximum allowed persons is ' . MAX_CAPACITY . '. Please reduce the number of persons.</p>';
        } else {
             $message = '<p>❗ Please fill in all required fields and ensure counts are valid.</p>';
        }
        // --- END FIX ---
        
        $message_status = 'flex';
    } else {
        // --- Time Range Validation (1 PM - 1 AM next day) ---
        $tz = new DateTimeZone('Asia/Manila'); // just to be explicit
        $startDT = $dateTimeObj;
        $endDT = $startDT->modify("+{$numHours} hours");

        $is_time_invalid = false;
        $time_error_message = '';
        $hours_display = '1:00 PM - 1:00 AM next day'; // Operating hours

        // Check if reservation start time is in the past (compare with now in same TZ)
        $nowTs = (new DateTimeImmutable('now', $tz))->getTimestamp();
        if ($startDT->getTimestamp() < $nowTs) {
            $is_time_invalid = true;
            $time_error_message = 'Reservation must be for a future time.';
        }

        if (!$is_time_invalid) {
            // Build window: validStart = reservationDate 13:00 same day
            $validStartDT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $reservationDate . ' 13:00', $tz);
            // validEnd = next day 01:00 (i.e., reservationDate + 1 day at 01:00)
            $validEndDT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $reservationDate . ' 01:00', $tz)->modify('+1 day');

            // If start is earlier than 13:00 OR end is later than next-day 01:00
            if ($startDT < $validStartDT || $endDT > $validEndDT) {
                $is_time_invalid = true;
                // FIX: Consolidated the operating hours error message for clarity
                $time_error_message = "The requested time slot is outside the cafe's operating hours ({$hours_display}).";
            }

            // Also ensure hours are positive and within MAX_HOURS (already checked earlier)
        }
        
        if ($is_time_invalid) {
            $message_type = 'message-error';
            // Only use the specific error message, which is now clearer
            $message = '<p>❗ ' . $time_error_message . '</p>';
            $message_status = 'flex';
        } else {
            // --- Conflict Check ---
            $conflict_result = check_room_availability($con, $reservationDate, $startTime, $numHours, $numPersons, $purpose);

            if ($conflict_result === 'OK') {
                $estimatedFee = calculate_server_fee($numHours, $numPersons, $projector, $speakerMic);

                $sql = "INSERT INTO study_room_reservations (
                    full_name, email, phone_number, student_id, reservation_date, start_time, 
                    num_hours, num_persons, purpose, projector, speaker_mic, estimated_fee
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $dbStartTime = date('H:i:s', $startDT->getTimestamp()); 

                $stmt = mysqli_prepare($con, $sql);
                if (!$stmt) {
                    $message_type = 'message-error';
                    $message = '<p class="message-error">Database Error: Could not prepare statement. (' . mysqli_error($con) . ')</p>';
                    $message_status = 'flex';
                } else {
                    mysqli_stmt_bind_param($stmt, "ssssssiisiid", 
                        $fullName, $email, $phoneNumber, $studentId, $reservationDate, $dbStartTime, 
                        $numHours, $numPersons, $purpose, $projector, $speakerMic, $estimatedFee
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $message_type = 'message-confirm';
                        $message = "<p class='message-confirm'>Your reservation has been successfully submitted!</p>";
                        $message .= "<p><strong>Date:</strong> $reservationDate at $startTime for $numHours hour(s)</p>";
                        $message .= "<p><strong>Group Size:</strong> $numPersons persons</p>";
                        $message .= "<p><strong>Student ID:</strong> " . (empty($studentId) ? 'N/A' : htmlspecialchars($studentId)) . "</p>";
                        $message .= "<p><strong>Equipment:</strong> Projector: " . ($projector ? 'Yes' : 'No') . ", Speaker/Mic: " . ($speakerMic ? 'Yes' : 'No') . "</p>";
                        $message .= "<h3>Total Fee: PHP " . number_format($estimatedFee, 2) . "</h3>";
                        $message .= "<p class='message-warning'>A staff member will contact you at " . htmlspecialchars($phoneNumber) . " to confirm the booking and final price.</p>";
                        $message_status = 'flex';
                        unset($_POST);
                    } else {
                        $message_type = 'message-error';
                        $message = '<p class="message-error">Database Error: Could not save reservation. (' . mysqli_error($con) . ')</p>';
                        $message_status = 'flex';
                    }
                }
            } else {
                $message_type = 'message-error';
                $message_status = 'flex';
                
                if ($conflict_result === "BLOCKED_EVENT") $message = "This time is already reserved for an <b>event</b>. No bookings allowed.";
                elseif ($conflict_result === "BLOCKED_BY_STUDY") $message = "A study booking exists. <b>Events</b> cannot overlap.";
                elseif (strpos($conflict_result, "ONLY_") === 0) {
                    $left = explode("_", $conflict_result)[1];
                    $message = "Only <b>{$left}</b> person slots are available for this schedule.";
                } elseif ($conflict_result === 'DB_ERROR') $message = "A database error occurred during availability check.";
                elseif ($conflict_result === 'TIME_PARSE_ERROR') $message = "A time formatting error occurred during the availability check.";
                else $message = "An unknown conflict was detected.";
                $message = "❗ " . $message;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Malvar Bat Cave Cafe - Official Site</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <header>
        <nav class="container">

            <a href="index.php" class="logo">
                <img src="assets/image - Copy.png" alt="Cafe Logo">
            </a>

            <button class="menu-toggle" aria-controls="main-nav" aria-expanded="false">
                ☰
            </button>

            <div class="nav-links" id="main-nav">
                <a href="home.php" class="nav-link">Home</a>
                <a href="menu.php" class="nav-link">Menu</a>
                <a href="booking.php" class="nav-link active">Booking</a>
            </div>

        </nav>
    </header>

<section id="reserve" class="section container">
    <div class="card form-container">
        <h1>Study Room Reservation</h1>
        <p class="subtitle">Book your study spot or casual gathering at The Malvar Bat Cave Cafe.</p>

        <div id="priceModal" class="modal-overlay" style="display: <?php echo $message_status; ?>">
            <div class="modal-content <?php echo $message_type; ?>">
                <h2>Booking Summary</h2>
                <div id="modalMessage"><?php echo $message; ?></div>
                <button id="closeModal">OK</button>
            </div>
        </div>

        <form id="reservation-form" action="booking.php" method="POST">
            <div class="form-split">
                
                <div class="form-fields-column">
                    
                    <div class="form-section">
                        <h2>1. PERSONAL DETAILS</h2>
                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="fullName" required value="<?php echo htmlspecialchars($_POST['fullName'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phoneNumber">Phone Number (09xxxxxxxxx) *</label>
                            <input type="text" id="phoneNumber" name="phoneNumber" minlength="11" maxlength="11" required value="<?php echo htmlspecialchars($_POST['phoneNumber'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>2. RESERVATION DETAILS</h2>
                        <div class="reservation-grid">
                            <div class="form-group">
                                <label for="reservationDate">Date *</label>
                                <input type="date" id="reservationDate" name="reservationDate" required value="<?php echo htmlspecialchars($_POST['reservationDate'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="form-group">
                                <label for="startTime">Start Time (1:00 PM to 1:00 AM next day) *</label>
                                <input type="time" id="startTime" name="startTime" step="300" required value="<?php echo htmlspecialchars($_POST['startTime'] ?? '13:00'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="numHours">Number of Hours (Max <?php echo MAX_HOURS; ?>) *</label>
                                <input type="number" id="numHours" name="numHours" min="1" max="<?php echo MAX_HOURS; ?>" required value="<?php echo htmlspecialchars($_POST['numHours'] ?? ''); ?>" oninput="calculateEstimate()">
                            </div>
                            <div class="form-group">
                                <label for="numPersons">Number of Persons (Max <?php echo MAX_CAPACITY; ?>) *</label>
                                <input type="number" id="numPersons" name="numPersons" min="1" max="<?php echo MAX_CAPACITY; ?>" required value="<?php echo htmlspecialchars($_POST['numPersons'] ?? ''); ?>" oninput="calculateEstimate()">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>3. PURPOSE</h2>
                        <div class="reservation-grid">
                            <div class="form-group">
                                <label for="reservationPurpose">Purpose *</label>
                                <select id="reservationPurpose" name="reservationPurpose" required onchange="toggleStudentIdField(); calculateEstimate()">
                                    <option value="" disabled <?php echo (!isset($_POST['reservationPurpose']) || $_POST['reservationPurpose'] == '') ? 'selected' : ''; ?>>Select purpose</option>
                                    <option value="Study" <?php echo (isset($_POST['reservationPurpose']) && $_POST['reservationPurpose'] == 'Study') ? 'selected' : ''; ?>>Study</option>
                                    <option value="Gathering" <?php echo (isset($_POST['reservationPurpose']) && $_POST['reservationPurpose'] == 'Gathering') ? 'selected' : ''; ?>>Casual Gathering / Social (Event)</option>
                                </select>
                            </div>
                            <div class="form-group" id="studentIdGroup">
                                <label for="studentId">Student ID (Required for Study) *</label>
                                <input type="text" id="studentId" name="studentId" value="<?php echo htmlspecialchars($_POST['studentId'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>EQUIPMENT RENTAL (PHP <?php echo number_format(EQUIP_RATE, 2); ?> / hr per item)</h2>
                        <div class="equipment-options">
                            <div class="checkbox-group">
                                <input type="checkbox" id="projector" name="projector" value="1" <?php echo (isset($_POST['projector']) && $_POST['projector'] == '1') ? 'checked' : ''; ?> onchange="calculateEstimate()">
                                <label for="projector">Projector</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="speakerMic" name="speakerMic" value="1" <?php echo (isset($_POST['speakerMic']) && $_POST['speakerMic'] == '1') ? 'checked' : ''; ?> onchange="calculateEstimate()">
                                <label for="speakerMic">Speaker & Mic</label>
                            </div>
                        </div>
                    </div>
                    
                </div> 
                <div class="estimate-column">
        <div class="estimate-column">
        <div class="form-section sticky-estimate">


    <div class="estimate-container card">

        <div id="summary-container">
            <h2>YOUR RESERVATION SUMMARY</h2>
            <p class="summary-item">
                <span>Date:</span>
                <span id="summary-date">---</span>
            </p>

            <p class="summary-item">
                <span>Start Time:</span>
                <span id="summary-time">---</span>
            </p>

            <p class="summary-item">
                <span>Duration:</span>
                <span id="summary-hours">---</span>
            </p>

            <p class="summary-item">
                <span>Guests:</span>
                <span id="summary-persons">---</span>
            </p>

            <p class="summary-item">
                <span>Purpose:</span>
                <span id="summary-purpose">---</span>
            </p>

            <div class="divider"></div>

            <p class="summary-item">
                <span>Equipment:</span>
                <span id="summary-equipment">None</span>
            </p>

        </div> <div class="divider"></div>

        <p>Estimated Total:</p>
        <p class="price" id="total-fee">PHP 0.00</p>

    </div> </div>
        </div>
    </div>
    </div><button type="submit" class="submit-button" name="submitReservation">CONFIRM RESERVATION</button>
             </div>
              
        </form>
    </div>
</section>

    <footer class="footer">
        <div class="container footer-content">

            <div class="footer-section">
                <h4>Bat Cave Cafe</h4>
                <p>BSU Malvar Area</p>
                <p>Malvar, Batangas, PH</p>
                <p>&nbsp;</p>
                <p>The sanctuary for late-night success.</p>
            </div>

            <div class="footer-section">
                <h4>Contact & Hours</h4>
                <p>Phone: (043) 123-4567</p>
                <p>Email: info@batcavecafe.com</p>
                <p>&nbsp;</p>
                <p>Mon - Sun: 1:00 PM - 1:00 AM</p>
            </div>

            <div class="footer-section">
                <h4>Quick Links</h4>
                <div class="footer-links">
                    <a href="#">Home</a>
                    <a href="#">Menu</a>
                    <a href="#">Reserve A Room</a>
                </div>
            </div>
        </div>

        <div class="copyright container">
            &copy; <?php echo date('Y'); ?> The Malvar Bat Cave Cafe. All rights reserved.
        </div>
    </footer>

    <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY_HERE&callback=initMap"></script>
    
    <script src="script.js"></script>

</body>
</html>