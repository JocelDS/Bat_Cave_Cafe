<?php
require('db.php');

// --- Initialize variables ---
$dbSuccess = false;
$menuData = [];

// --- Database connection ---
if ($con->connect_error) {
    $dbSuccess = false;
} else {
    $dbSuccess = true;

    // Fetch menu items grouped by category, including three price columns
    $sql = "
        SELECT mc.category_name, mi.item_id, mi.category_id, mi.name, mi.description, mi.price_small, mi.price_medium, mi.price_large, mi.image
        FROM menu_items mi
        JOIN menu_categories mc ON mi.category_id = mc.category_id
        ORDER BY mc.category_name, mi.name
    ";
    $result = $con->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Group data by category name
            $menuData[$row['category_name']][] = $row;
        }
    }
}

// Extract category names for the sidebar
$categories = array_keys($menuData);
$firstCategory = !empty($categories) ? $categories[0] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Malvar Bat Cave Cafe - Official Site</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
</head>

<body>
<header>
    <nav class="container">
        <a href="index.php" class="logo">
            <img src="assets/image - Copy.png" alt="Cafe Logo">
        </a>
        <button class="menu-toggle" aria-controls="main-nav" aria-expanded="false">☰</button>
        <div class="nav-links" id="main-nav">
            <a href="home.php" class="nav-link">Home</a>
            <a href="menu.php" class="nav-link active">Menu</a>
            <a href="booking.php" class="nav-link">Booking</a>
        </div>
    </nav>
</header>

<main class="container">
    <section id="menu-page" class="section">
        <h1 class="menu-title">Our Menu</h1>
        <p class="menu-intro">
            Fresh brews, warm meals, and your favorite study snacks.
            <?php if (!$dbSuccess): ?>
                <strong class="db-error-message">(Note: Database connection failed. Displaying MOCK DATA.)</strong>
            <?php endif; ?>
        </p>

        <div class="menu-main-grid">
            <aside class="menu-sidebar">
                <h2 class="sidebar-title">Menu</h2>
                <ul class="category-list">
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="#<?php echo strtolower(str_replace(' ', '-', $category)); ?>"
                               class="category-link <?php echo ($category == $firstCategory) ? 'active-category' : ''; ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <div class="menu-content">
                <p class="content-welcome">
                    Welcome to our selection of freshly brewed coffee and study-friendly snacks.
                </p>

                <?php if (!empty($menuData)): ?>
                    <?php foreach ($menuData as $category => $items): ?>
                        <h2 class="category-title" id="<?php echo strtolower(str_replace(' ', '-', $category)); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </h2>

                        <div class="menu-items-grid">
                            <?php foreach ($items as $item): ?>
                                <div class="menu-item-card card">
                                    <img src="<?php 
                                        echo !empty($item['image']) 
                                            ? 'uploads/' . htmlspecialchars($item['image']) 
                                            : 'assets/placeholder.png'; 
                                    ?>" 
                                        alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                        class="product-image">

                                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>

                                    <div class="item-prices">
                                        <?php if (!empty($item['price_small'])): ?>
                                            <p class="price-small">
                                                <span class="price-label">S:</span> ₱<?php echo number_format($item['price_small'], 2); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($item['price_medium'])): ?>
                                            <p class="price-medium">
                                                <span class="price-label">M:</span> ₱<?php echo number_format($item['price_medium'], 2); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($item['price_large'])): ?>
                                            <p class="price-large">
                                                <span class="price-label">L:</span> ₱<?php echo number_format($item['price_large'], 2); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No menu items available.</p>
                <?php endif; ?>
            </div>
        </div>

    </section>
</main>
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
                <a href="home.php">Home</a>
                <a href="menu.php">Menu</a>
                <a href="booking.php">Reserve A Room</a>
            </div>
        </div>
    </div>

    <div class="copyright container">
        &copy; <?php echo date('Y'); ?> The Malvar Bat Cave Cafe. All rights reserved.
    </div>
</footer>
 <script src="script.js"></script>
</body>
</html>