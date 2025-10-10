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
<script>
    // Global image fallback: replace any broken image with site logo
    (function(){
        function setFallback(img){
            try{ if(!img) return; if(img.dataset._fallbackApplied) return; img.dataset._fallbackApplied = '1'; img.src = 'img/logo.png'; }
            catch(e){ /* noop */ }
        }
        // Attach to existing images
        document.addEventListener('DOMContentLoaded', ()=>{
            document.querySelectorAll('img').forEach(img=>{
                if(img.complete && img.naturalWidth===0) setFallback(img);
                img.addEventListener('error', ()=> setFallback(img));
            });
        });
        // Also catch dynamically added images
        const mo = new MutationObserver(muts=>{
            muts.forEach(m=>{
                m.addedNodes && m.addedNodes.forEach(n=>{
                    if(n && n.tagName==='IMG'){
                        n.addEventListener('error', ()=> setFallback(n));
                        if(n.complete && n.naturalWidth===0) setFallback(n);
                    } else if(n && n.querySelectorAll){
                        n.querySelectorAll('img').forEach(img=>{ img.addEventListener('error', ()=> setFallback(img)); if(img.complete && img.naturalWidth===0) setFallback(img); });
                    }
                });
            });
        });
        try{ mo.observe(document.body, {childList:true, subtree:true}); } catch(e){ /* ignore if body not yet present */ }
    })();
</script>
