<?php
require_once 'config.php';
require_once 'db.php';

send_security_headers();

// Load social links once per request - footer.php reuses $social_links.
$social_links = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM social_links WHERE is_active = TRUE ORDER BY id ASC");
        $social_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $social_links = [];
    }
}

// The top bar and footer must never show an empty gap: fall back to the
// fellowship's own handles when the table is empty or has not been seeded yet.
if (empty($social_links)) {
    $social_links = [
        ['platform' => 'Facebook', 'url' => 'https://www.facebook.com/Nups-g-Ttu', 'icon' => 'fab fa-facebook'],
        ['platform' => 'Tiktok', 'url' => 'https://www.tiktok.com/@nupsg.ttu', 'icon' => 'fab fa-tiktok'],
        ['platform' => 'Instagram', 'url' => 'https://www.instagram.com/nupsg_ttu', 'icon' => 'fab fa-instagram'],
        ['platform' => 'YouTube', 'url' => '#', 'icon' => 'fab fa-youtube'],
    ];
}

// Current page for nav highlighting (blog detail highlights "Blogs").
$current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
function nav_is_active($page, $current, $group = []) {
    return in_array($current, array_merge([$page], $group), true) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? htmlspecialchars($page_description) : SITE_NAME . ' - Presbyterian students fellowship serving the Takoradi Technical University community in Ghana.'; ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon/favicon.svg" />
    <link rel="shortcut icon" href="favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="NUPS-G TTU" />
    <link rel="manifest" href="favicon/site.webmanifest" />

    <!-- Fonts & icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Shared design system -->
    <link rel="stylesheet" href="assets/css/main.css">

    <?php
    // Page-specific styles (optional): pages may define $page_css before include.
    if (!empty($page_css)) {
        echo '<style>' . $page_css . '</style>';
    }
    ?>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var main = document.querySelector('main');
    if (main && !main.id) {
        main.id = 'main-content';
    }
});
</script>
<script>document.documentElement.classList.add('js');</script>

<!-- Top bar -->
<div class="site-topbar">
    <div class="container">
        <div class="topbar-contact">
            <span><i class="fas fa-envelope"></i> <a href="mailto:sethsefa18@gmail.com">sethsefa18@gmail.com</a></span>
            <span class="phone-hidden"><i class="fas fa-phone-alt"></i> <a href="tel:+233256484360">+233 256 4843 60</a></span>
        </div>
        <div class="topbar-social">
            <?php foreach ($social_links as $link): ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($link['platform']); ?>" aria-label="<?php echo htmlspecialchars($link['platform']); ?>">
                    <i class="<?php echo htmlspecialchars($link['icon']); ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Main header -->
<header class="site-header">
    <div class="container">
        <a href="index.php" class="brand" aria-label="NUPS-G TTU home">
            <img src="nupsglogo.png" alt="NUPS-G TTU crest">
            <span class="brand-name">
                <b>NUPS-G TTU</b>
                <small>For Christ We Live</small>
            </span>
        </a>

        <nav class="main-nav" aria-label="Primary">
            <a class="nav-link <?php echo nav_is_active('index.php', $current_page); ?>" href="index.php">Home</a>
            <a class="nav-link <?php echo nav_is_active('history.php', $current_page); ?>" href="history.php">History</a>
            <a class="nav-link <?php echo nav_is_active('executives.php', $current_page); ?>" href="executives.php">Executives</a>
            <a class="nav-link <?php echo nav_is_active('events.php', $current_page); ?>" href="events.php">Events</a>
            <a class="nav-link <?php echo nav_is_active('blogs.php', $current_page, ['blog-detail.php']); ?>" href="blogs.php">Blogs</a>
            <a class="nav-link <?php echo nav_is_active('gallery.php', $current_page); ?>" href="gallery.php">Gallery</a>
            <a class="nav-link <?php echo nav_is_active('resources.php', $current_page); ?>" href="resources.php">Resources</a>
            <a class="nav-link <?php echo nav_is_active('quiz.php', $current_page); ?>" href="quiz.php">Quiz</a>
        </nav>

        <div class="header-actions">
            <a href="donate.php" class="btn btn-donate nav-cta"><i class="fas fa-heart"></i> Donate</a>

            <?php if (isLoggedIn()): ?>
                <div class="user-chip auth-desktop">
                    <button aria-haspopup="true" aria-expanded="false">
                        <span class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
                        <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Member')[0]); ?>
                        <i class="fas fa-chevron-down" style="font-size: .7rem; color: var(--muted);"></i>
                    </button>
                    <div class="user-dropdown">
                        <div class="dd-head">
                            <b><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Member'); ?></b>
                            <span><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                        </div>
                        <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin Dashboard</a>
                        <?php endif; ?>
                        <div class="dd-sep"></div>
                        <a href="logout.php" class="danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-desktop">
                    <a href="login.php" class="btn btn-outline btn-sm">Login</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Join Us</a>
                </div>
            <?php endif; ?>

            <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile drawer -->
<div class="overlay" aria-hidden="true"></div>
<aside class="drawer" aria-label="Mobile navigation">
    <div class="drawer-top">
        <a href="index.php" class="brand"><img src="nupsglogo.png" alt="NUPS-G TTU"><span class="brand-name"><b>NUPS-G TTU</b></span></a>
        <button class="drawer-close" aria-label="Close menu"><i class="fas fa-times"></i></button>
    </div>
    <nav class="drawer-nav">
        <a class="<?php echo nav_is_active('index.php', $current_page); ?>" href="index.php">Home <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('history.php', $current_page); ?>" href="history.php">History <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('executives.php', $current_page); ?>" href="executives.php">Executives <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('events.php', $current_page); ?>" href="events.php">Events <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('blogs.php', $current_page, ['blog-detail.php']); ?>" href="blogs.php">Blogs <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('gallery.php', $current_page); ?>" href="gallery.php">Gallery <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('resources.php', $current_page); ?>" href="resources.php">Resources <i class="fas fa-chevron-right"></i></a>
        <a class="<?php echo nav_is_active('quiz.php', $current_page); ?>" href="quiz.php">Quiz <i class="fas fa-chevron-right"></i></a>
    </nav>
    <div class="drawer-actions">
        <a href="donate.php" class="btn btn-donate"><i class="fas fa-heart"></i> Donate</a>
        <?php if (isLoggedIn()): ?>
            <a href="profile.php" class="btn btn-outline"><i class="fas fa-user"></i> <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Member')[0]); ?>'s Profile</a>
            <?php if (isAdmin()): ?>
                <a href="admin/dashboard.php" class="btn btn-outline"><i class="fas fa-cog"></i> Admin Dashboard</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline" style="color: var(--danger-text); border-color: var(--danger-text);"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline">Login</a>
            <a href="register.php" class="btn btn-primary">Create Account</a>
        <?php endif; ?>
    </div>
    <p class="drawer-foot"><i class="fas fa-cross" style="color: var(--gold);"></i> &ldquo;For to me, to live is Christ&hellip;&rdquo; - Phil. 1:21</p>
</aside>

<div class="toast-wrap" aria-live="polite"></div>
