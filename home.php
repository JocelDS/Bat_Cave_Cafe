<?php
// =======================================================
// DATABASE CONNECTION AND FETCHING BEST SELLERS
// =======================================================

// Require database connection (db.php should define $con)
require('db.php');

$db_error = null;
$items = [];

if (!isset($con) || !$con) { 
    $db_error = "Failed to connect to MySQL: " . (isset($con) ? mysqli_connect_error() : "Database connection object not available.");
} else {
    // FIX: Corrected SQL syntax (missing comma) and aliased price_small as 'price'.
    $query = "SELECT b.best_id, mi.name, mi.description, mi.price_small, mi.price_medium, mi.price_large, mi.image
              FROM best_sellers b
              JOIN menu_items mi ON b.item_id = mi.item_id
              ORDER BY b.created_at DESC
              LIMIT 8";
    $result = mysqli_query($con, $query);

    if ($result === false) {
        $db_error = "SQL Query Error: " . mysqli_error($con);
    } elseif (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        mysqli_free_result($result);
    }
    
    if (isset($con) && $con) {
        mysqli_close($con);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <!-- ================= Header / Navigation ================= -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">
                <img src="assets/image - Copy.png" alt="Cafe Logo">
            </a>

            <button class="menu-toggle" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                ☰
            </button>

            <div class="nav-links" id="main-nav">
                <a href="home.php" class="nav-link active">Home</a>
                <a href="menu.php" class="nav-link">Menu</a>
                <a href="booking.php" class="nav-link">Booking</a>
            </div>
        </nav>
    </header>

    <!-- ================= Main Content ================= -->
    <main class="container">

        <!-- ===== Hero Section ===== -->
        <section id="hero" class="hero">
            <img src="assets/image.png" alt="Batcave-logo" class="home-logo">
            <p>"Where Coffee meets Focus"</p>
            <div class="hero-buttons-container">
                <a href="menu.php" class="hero-button">View Menu</a>
                <a href="booking.php" class="hero-button secondary-hero-button">Book a Room</a>
            </div>
        </section>

        <!-- ===== Welcome Card ===== -->
        <div class="card">
            <h1>Welcome to the Malvar Bat Cave Cafe</h1>
            <p style="text-align: center;">The premier late-night study and coffee spot near Batangas State University. Enjoy our quiet, comfortable space, designed for focused work and creative thinking.</p>
        </div>

        <!-- ===== Best Sellers / Suggested Orders ===== -->
<section class="section">
            <h2>Suggested Order / Best Sellers!</h2>

            <?php if (isset($db_error)): ?>
                <p class="message-error" style="text-align:center; color: red;">
                    Database Error: <?php echo $db_error; ?>
                </p>
            <?php endif; ?>

            <div class="best-sellers-grid"> 
                <?php 
                if (count($items) > 0):
                    foreach ($items as $row):
                ?>
                        <div class="best-seller-item">
                            <img src="uploads/<?php echo !empty($row['image']) ? htmlspecialchars($row['image']) : 'default.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($row['name']); ?>" 
                                 class="product-image">
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="description"><?php echo htmlspecialchars($row['description']); ?></p>
                            
                            <div class="price-size-container">
                                <?php if (!empty($row['price_small'])): ?>
                                    <div class="price-size-box">
                                        <span class="size-label">S:</span> ₱<?php echo number_format($row['price_small'], 2); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($row['price_medium'])): ?>
                                    <div class="price-size-box">
                                        <span class="size-label">M:</span> ₱<?php echo number_format($row['price_medium'], 2); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($row['price_large'])): ?>
                                    <div class="price-size-box">
                                        <span class="size-label">L:</span> ₱<?php echo number_format($row['price_large'], 2); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            </div>
                <?php 
                    endforeach;
                else:
                    echo "<p style='text-align:center; width:100%;'>No best sellers found or database error. Check PHP connection.</p>";
                endif;
                ?>
            </div>
        </section>

        <!-- ===== Mission & Vision ===== -->
        <div class="mission-vision-container">
            <h2>Our Guiding Principles</h2>
            <div class="mission-vision-content">
                <div class="vision-section card">
                    <h3 class="feature-card-title">VISION STATEMENT</h3>
                    <p>To be the undisputed sanctuary and second home for the BSU community, recognized as the best late-night establishment that fuels academic success, fosters genuine connection, and elevates the local coffee culture in Malvar.</p>
                </div>

                <div class="mission-section card">
                    <h3 class="feature-card-title">MISSION STATEMENT</h3>
                    <p>The Malvar Bat Cave Cafe is dedicated to providing a consistently comfortable, secure, and inspiring environment where students can focus and socialize. We commit to serving high-quality coffee and nourishment, and offering a seamless, professional experience.</p>
                </div>
            </div>
        </div>

        <!-- ===== Booking Section ===== -->
        <div class="booking-section">
            <div class="booking-info">
                <h3>Enjoy a quiet, comfortable study room perfect for group work, late night sessions</h3>
                <ul>
                    <li>P 50/hr - P 75/hr per person</li>
                    <li>Projector or Speaker: P 150/hr each</li>
                </ul>
            </div>
            <div class="booking-image-box">
                <img src="assets/readytobook.jpg" class="booking-image" alt="Ready to Book">
            </div>
        </div>

        <!-- ===== Location Section ===== -->
        <section class="section location-section">
            <h3>Find Your Sanctuary</h3>
            <p style="text-align:center; color:#ccc;">Conveniently located just steps from BSU.</p>
            <div class="location-grid">
                <div class="location-details">
                    <h4>Our Address</h4>
                    <p class="address-line">
                        <span class="location-icon">📍</span> 
                        Gen. Malvar Avenue, near BSU Main Campus, Malvar, Batangas
                    </p>
                    
                    <h4>Operating Hours</h4>
                    <p class="hours-line">
                        <span class="location-icon">⏰</span> 
                        <span class="text-accent">1pm - 1am a Week</span>
                    </p>
                    <p class="hours-subtext">The ultimate late-night study spot for students.</p>
                </div>
                <div class="map-placeholder">
                    <div id="map" style="width:100%; height:300px; border-radius:10px;"></div> 
                </div>
            </div>
        </section>

    </main>

    <!-- ================= Footer ================= -->
    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-section">
                <h4>Bat Cave Cafe</h4>
                <p>BSU Malvar Area</p>
                <p>Malvar, Batangas, PH</p>
                <p style="margin-top: 15px; font-style: italic;">The sanctuary for late-night success.</p>
            </div>

            <div class="footer-section">
                <h4>Contact & Hours</h4>
                <p>Phone: (043) 123-4567</p>
                <p>Email: info@batcavecafe.com</p>
                <p>1pm - 1am a Week</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <div class="footer-links">
                    <a href="home.php">Home</a>
                    <a href="menu.php">Menu</a>
                    <a href="booking.php">Reserve A Room</a>
                </div>
            </div>
        </div>
        
        <div class="copyright container">
            © <?php echo date('Y'); ?> The Malvar Bat Cave Cafe. All rights reserved.
        </div>
    </footer>

    <!-- ================= Scripts ================= -->
    <script src="script.js"></script>

    <script async
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCc6CFOyp8XFwAJ-FtpZIe2gj3t7CkJjR8&callback=initMap">
    </script>

</body>
</html>
