<?php
// Reusable footer with dynamic services list
if (!isset($servicesFooter)) {
    $servicesFooter = [];
    if (!isset($conn)) {
        require_once __DIR__ . '/database.php';
    }
    if ($conn && !$conn->connect_error) {
        if ($chk = $conn->query("SHOW TABLES LIKE 'services'")) {
            if ($chk->num_rows > 0) {
                if ($res = $conn->query("SELECT name FROM services ORDER BY name ASC")) {
                    while ($row = $res->fetch_assoc()) { $servicesFooter[] = $row['name']; }
                    $res->free();
                }
            }
            $chk->free();
        }
    }
}
?>
<footer id="footer">
    <div class="footer-container">
        <div class="footer-column">
            <h4>Customer Service</h4>
            <p>Available 7am to 12pm</p>
            <p>+63 917 123 4567</p>
            <p>Zamoras St., Ozamis City, Misamis Occidental</p>
        </div>
        <div class="footer-column">
            <h4>Information</h4>
            <ul>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Services</h4>
            <ul>
                <?php if (!empty($servicesFooter)): foreach ($servicesFooter as $srvName): $slug = strtolower(preg_replace('/\s+/', '-', $srvName)); ?>
                    <li><a href="products.php#<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($srvName) ?></a></li>
                <?php endforeach; else: ?>
                    <li style="font-style:italic;color:#666;">No services yet</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Follow Us</h4>
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
</footer>
