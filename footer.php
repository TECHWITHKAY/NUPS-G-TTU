<?php
require_once 'config.php';
require_once 'db.php';

// Try to connect to database (PostgreSQL). Degrade gracefully if unavailable.
$social_links = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM social_links WHERE is_active = TRUE");
        $social_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Database not set up - use default empty
        $social_links = [];
    }
}
?>
    
    <footer>
        <div class="footer-main">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-about">
                        <div class="footer-logo">
                            <!-- <img src="images/nupsg%20logo.png" alt="NUPS-G" class="footer-nupsg"> -->
                        </div>
                        <h3><?php echo SITE_NAME; ?></h3>
                        <p>Welcome to NUPS-G Church. We are a community of believers dedicated to spreading the love and message of Jesus Christ.</p>
                        <div class="footer-social">
                            <?php foreach ($social_links as $link): ?>
                                <a href="<?php echo $link['url']; ?>" target="_blank" title="<?php echo $link['platform']; ?>">
                                    <i class="<?php echo $link['icon']; ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="footer-links">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="index.php">Home</a></li>
                            <li><a href="history.php">History</a></li>
                            <li><a href="executives.php">Executives</a></li>
                            <li><a href="events.php">Events</a></li>
                            <li><a href="blogs.php">Blogs</a></li>
                            <li><a href="quiz.php">Quiz</a></li>
                            <li><a href="donate.php">Donate</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-contact">
                        <h3>Contact Us</h3>
                        <ul>
                            <li><i class="fas fa-map-marker-alt"></i> Church Address, City</li>
                            <li><i class="fas fa-phone"></i>+233 256 4843 60</li>
                            <li><i class="fas fa-envelope"></i> nupsgttu@gmail.com</li>
                        </ul>
                    </div>
                    
                    <div class="footer-newsletter">
                        <h3>Newsletter</h3>
                        <p>Subscribe to our newsletter for updates</p>
                        <form class="newsletter-form" method="POST" action="">
                            <input type="email" placeholder="Your email address" required>
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <style>
        /* Footer - Remove underlines from links */
        footer a {
            text-decoration: none !important;
        }
        
        footer {
            background: #1a1a2e;
            color: white;
            margin-top: 60px;
        }
        .footer-main {
            padding: 60px 0 40px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
        }
        .footer-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .footer-logo img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }
        .footer-presby {
            opacity: 0.9;
            filter: grayscale(20%);
        }
        .footer-nupsg {
            position: relative;
            z-index: 1;
            height: 60px;
            width: auto;
            transition: transform 0.3s ease;
        }
        .footer-logo:hover .footer-nupsg {
            transform: scale(1.05);
        }
        .footer-about h3, .footer-links h3, .footer-contact h3, .footer-newsletter h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .footer-about p {
            color: #ccc;
            margin-bottom: 20px;
            line-height: 1.8;
        }
        .footer-social {
            display: flex;
            gap: 15px;
        }
        .footer-social a {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .footer-social a:hover {
            background: #FFD700;
            color: #1a1a2e;
            transform: translateY(-5px);
        }
        .footer-links ul {
            list-style: none;
        }
        .footer-links li {
            margin-bottom: 12px;
        }
        .footer-links a {
            color: #ccc;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .footer-links a:hover {
            color: #FFD700;
            transform: translateX(5px);
        }
        .footer-contact ul {
            list-style: none;
        }
        .footer-contact li {
            margin-bottom: 15px;
            color: #ccc;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-contact i {
            color: #FFD700;
        }
        .newsletter-form {
            display: flex;
            gap: var(--spacing-xs);
            align-items: stretch;
        }
        .newsletter-form input {
            flex: 1;
            padding: var(--spacing-sm);
            border: none;
            border-radius: var(--radius-lg);
            background: #2a2a4a;
            color: white;
            font-size: var(--font-size-sm);
            transition: var(--transition);
        }
        .newsletter-form input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        .newsletter-form input:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--accent);
        }
        .newsletter-form button {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            white-space: nowrap;
            color: white;
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
        }
        .newsletter-form button:hover {
            background: var(--accent);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .footer-bottom {
            background: #16162a;
            padding: 20px 0;
            text-align: center;
            color: #ccc;
        }
        .container {
            max-width: min(1400px, 95vw);
            margin: 0 auto;
            padding: 0 clamp(1rem, 5vw, 2.5rem);
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-md);
            }
        }
        
        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }
            .footer-main {
                padding: var(--spacing-md) 0 var(--spacing-sm);
            }
            .footer-about, .footer-links, .footer-contact, .footer-newsletter {
                text-align: center;
            }
            .footer-social {
                justify-content: center;
                gap: var(--spacing-sm);
            }
            .footer-links li {
                text-align: center;
            }
        }
    </style>
</body>
</html>
