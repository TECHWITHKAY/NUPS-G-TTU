<?php
require_once 'config.php';
// $social_links is loaded by header.php; re-fetch only for pages without it.
if (!isset($social_links)) {
    require_once 'db.php';
    $social_links = [];
    if ($conn !== null) {
        try {
            $stmt = $conn->query("SELECT * FROM social_links WHERE is_active = TRUE ORDER BY id ASC");
            $social_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $social_links = [];
        }
    }
}
?>
<footer class="site-footer">
    <div class="footer-main">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="index.php"><img src="nupsglogo.png" alt="NUPS-G TTU crest"></a>
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p>NUPS-G TTU is a vibrant union of Presbyterian students at Takoradi Technical University, transformed by the Gospel and devoted to Christ.</p>
                    <div class="footer-social">
                        <?php foreach ($social_links as $link): ?>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($link['platform']); ?>" aria-label="<?php echo htmlspecialchars($link['platform']); ?>">
                                <i class="<?php echo htmlspecialchars($link['icon']); ?>"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <nav class="footer-links" aria-label="Footer">
                    <h3 class="footer-title">Quick Links</h3>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="history.php"><i class="fas fa-chevron-right"></i> Our History</a></li>
                        <li><a href="executives.php"><i class="fas fa-chevron-right"></i> Executives</a></li>
                        <li><a href="events.php"><i class="fas fa-chevron-right"></i> Events</a></li>
                        <li><a href="blogs.php"><i class="fas fa-chevron-right"></i> Blogs</a></li>
                        <li><a href="gallery.php"><i class="fas fa-chevron-right"></i> Gallery</a></li>
                        <li><a href="resources.php"><i class="fas fa-chevron-right"></i> Resources</a></li>
                        <li><a href="quiz.php"><i class="fas fa-chevron-right"></i> Daily Quiz</a></li>
                        <li><a href="donate.php"><i class="fas fa-chevron-right"></i> Give</a></li>
                    </ul>
                </nav>

                <div class="footer-contact">
                    <h3 class="footer-title">Contact</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i><span>NUPS-G Secretariat,<br>Takoradi Technical University, Ghana</span></li>
                        <li><i class="fas fa-phone-alt"></i><a href="tel:+233256484360">+233 256 4843 60</a></li>
                        <li><i class="fas fa-envelope"></i><a href="mailto:sethsefa18@gmail.com">sethsefa18@gmail.com</a></li>
                    </ul>
                </div>

                <div class="footer-letter">
                    <h3 class="footer-title">Stay Connected</h3>
                    <p style="color:#9fb0cc; line-height:1.8;">Subscribe for event updates, devotionals and ministry news.</p>
                    <form class="footer-form js-newsletter" action="subscribe.php" method="post" novalidate>
                        <input type="email" name="email" placeholder="Your email address" aria-label="Email address" required>
                        <button type="submit" class="btn btn-gold btn-sm" aria-label="Subscribe"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <p class="made-with">Made with <i class="fas fa-heart"></i> by the NUPS-G TTU media team</p>
        </div>
    </div>
</footer>

<button class="to-top" aria-label="Back to top"><i class="fas fa-chevron-up"></i></button>

<script src="assets/js/main.js"></script>
</body>
</html>
