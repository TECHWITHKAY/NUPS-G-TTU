<?php
require_once 'config.php';

// Try to connect to database
$social_links = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT * FROM social_links WHERE is_active = 1");
    $social_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Database not set up - use default empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon/favicon.svg" />
    <link rel="shortcut icon" href="favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="NUPS-G TTU" />
    <link rel="manifest" href="favicon/site.webmanifest" />
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c72;
            --primary-dark: #162b54;
            --primary-light: #4a6fa5;
            --secondary: #2a5298;
            --accent: #FFD700;
            --accent-dark: #E6C200;
            --text-primary: #1a1a2e;
            --text-secondary: #666;
            --bg-primary: #fff;
            --bg-secondary: #f8f9fa;
            --border: #e9ecef;
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-md: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius: 15px;
            --radius-lg: 25px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --spacing-xs: 0.5rem;
            --spacing-sm: 1rem;
            --spacing-md: 2rem;
            --spacing-lg: 4rem;
            --font-size-sm: 0.875rem;
            --font-size-lg: 1.125rem;
            --font-size-3xl: 2.25rem;
        }

        /* Reset & Base */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-primary);
        }

        a {
            text-decoration: none !important;
            color: inherit;
        }

        ul {
            list-style: none;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        /* Container */
        .container {
            max-width: min(1400px, 95vw);
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .top-bar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 8px 0;
            font-size: 14px;
        }

        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .contact-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .contact-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            color: white;
            font-size: 16px;
            transition: var(--transition);
        }

        .social-links a:hover {
            color: var(--accent);
            transform: scale(1.2);
        }

        /* Main Header */
        .main-header {
            background: white;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Desktop Navigation */
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-menu > li > a {
            font-weight: 500;
            color: var(--text-primary);
            padding: 8px 0;
            position: relative;
            transition: var(--transition);
        }

        .nav-menu > li > a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
        }

        .nav-menu > li > a:hover::after,
        .nav-menu > li > a.active::after {
            width: 100%;
        }

        .nav-menu > li > a:hover,
        .nav-menu > li > a.active {
            color: var(--primary);
        }

        /* Auth Buttons */
        .auth-buttons {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-donate {
            background: linear-gradient(135deg, #004279, #c0392b);
            color: white;
        }

        .btn-donate:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-menu > .btn {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            box-shadow: var(--shadow-md);
            border-radius: var(--radius);
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            z-index: 100;
        }

        .user-menu:hover .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .user-dropdown a:hover {
            background: var(--bg-secondary);
            color: var(--primary);
        }

        .user-dropdown a:first-child {
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .user-dropdown a:last-child {
            border-radius: 0 0 var(--radius) var(--radius);
        }

        /* Mobile Menu Button */
        .menu-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            padding: 10px;
            background: none;
            border: none;
            cursor: pointer;
            z-index: 1001;
        }

        .menu-toggle span {
            display: block;
            width: 25px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
            transition: var(--transition);
        }

        .menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        /* Mobile Navigation */
        .mobile-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: white;
            z-index: 999;
            overflow-y: auto;
            padding: 80px 20px 20px;
        }

        .mobile-nav.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .mobile-nav-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mobile-nav-menu li a {
            display: block;
            padding: 15px 20px;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .mobile-nav-menu li a:hover,
        .mobile-nav-menu li a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .mobile-auth-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .mobile-auth-buttons .btn {
            width: 100%;
            justify-content: center;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .nav-menu {
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                display: none;
            }

            .nav-menu,
            .auth-buttons {
                display: none;
            }

            .menu-toggle {
                display: flex;
            }

            .logo img {
                height: 40px;
            }

            .logo-text {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 15px;
            }

            .header-inner {
                padding: 10px 0;
            }

            .logo img {
                height: 35px;
            }
        }

        /* Overlay */
        .nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        .nav-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="contact-info">
                <span><i class="fas fa-envelope"></i> nupsgttulocal@gmail.com</span>
            </div>
            <div class="social-links">
                <?php foreach ($social_links as $link): ?>
                    <a href="<?php echo $link['url']; ?>" target="_blank" title="<?php echo $link['platform']; ?>">
                        <i class="<?php echo $link['icon']; ?>"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-inner">
                <a href="index.php" class="logo">
                    <img src="nupsglogo.png" alt="NUPS-G">
                    <span class="logo-text">NUPS-G TTU</span>
                </a>

                <!-- Desktop Navigation -->
                <ul class="nav-menu">
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">History</a></li>
                    <li><a href="executives.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'executives.php' ? 'active' : ''; ?>">Executives</a></li>
                    <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>">Events</a></li>
                    <li><a href="blogs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'blogs.php' ? 'active' : ''; ?>">Blogs</a></li>
                    <li><a href="gallery.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'gallery.php' ? 'active' : ''; ?>">Gallery</a></li>
                    <li><a href="quiz.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'quiz.php' ? 'active' : ''; ?>">Quiz</a></li>
                </ul>

                <!-- Auth Buttons (Desktop) -->
                <div class="auth-buttons">
                    <a href="donate.php" class="btn btn-donate">
                        <i class="fas fa-heart"></i> Donate
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <div class="user-menu">
                            <button class="btn btn-outline">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            </button>
                            <div class="user-dropdown">
                                <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                                <?php if (isAdmin()): ?>
                                    <a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin Dashboard</a>
                                <?php endif; ?>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <div class="nav-overlay" onclick="toggleMobileMenu()"></div>
    <nav class="mobile-nav">
        <ul class="mobile-nav-menu">
            <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Home</a></li>
            <li><a href="history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">History</a></li>
            <li><a href="executives.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'executives.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Executives</a></li>
            <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Events</a></li>
            <li><a href="blogs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'blogs.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Blogs</a></li>
            <li><a href="gallery.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'gallery.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Gallery</a></li>
            <li><a href="quiz.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'quiz.php' ? 'active' : ''; ?>" onclick="toggleMobileMenu()">Quiz</a></li>
        </ul>
        <div class="mobile-auth-buttons">
            <a href="donate.php" class="btn btn-donate" onclick="toggleMobileMenu()">
                <i class="fas fa-heart"></i> Donate
            </a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php" class="btn btn-outline" onclick="toggleMobileMenu()">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                </a>
                <?php if (isAdmin()): ?>
                    <a href="admin/dashboard.php" class="btn btn-outline" onclick="toggleMobileMenu()">
                        <i class="fas fa-cog"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline" onclick="toggleMobileMenu()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline" onclick="toggleMobileMenu()">Login</a>
                <a href="register.php" class="btn btn-primary" onclick="toggleMobileMenu()">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <script>
            function toggleMobileMenu() {
                const menuToggle = document.querySelector('.menu-toggle');
                const mobileNav = document.querySelector('.mobile-nav');
                const overlay = document.querySelector('.nav-overlay');
                
                menuToggle.classList.toggle('active');
                mobileNav.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = mobileNav.classList.contains('active') ? 'hidden' : '';
            }

            // Close menu on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    const menuToggle = document.querySelector('.menu-toggle');
                    const mobileNav = document.querySelector('.mobile-nav');
                    const overlay = document.querySelector('.nav-overlay');
                    
                    if (menuToggle.classList.contains('active')) {
                        menuToggle.classList.remove('active');
                        mobileNav.classList.remove('active');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });
        </script>
