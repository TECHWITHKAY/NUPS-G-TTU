<?php
require_once 'config.php';
require_once 'db.php';

$page_title = 'Home';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_name = $logged_in ? ($_SESSION['user_name'] ?? 'Member') : '';

// Get latest events (past and future)
$stmt = $conn->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 3");
$events = $stmt->fetchAll();

// Get upcoming events for logged-in users (current or nearest upcoming)
$upcoming_events = [];
try {
    $stmt = $conn->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
    $upcoming_events = $stmt->fetchAll();
} catch (Exception $e) {
    $upcoming_events = [];
}

// Get the next upcoming event for logged-in users
$next_event = null;
try {
    $stmt = $conn->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 1");
    $next_event = $stmt->fetch();
} catch (Exception $e) {
    $next_event = null;
}

// Get latest blogs
$stmt = $conn->query("SELECT * FROM blogs WHERE status = 'published' ORDER BY created_at DESC LIMIT 3");
$blogs = $stmt->fetchAll();

// Get executives count
$stmt = $conn->query("SELECT COUNT(*) as count FROM executives");
$exec_count = $stmt->fetch();

// Get quiz leaderboard
$stmt = $conn->query("SELECT id, first_name, last_name, total_points FROM users WHERE role = 'member' AND total_points > 0 ORDER BY total_points DESC LIMIT 5");
$leaderboard = $stmt->fetchAll();

// Get quiz settings for availability
$quiz_available = false;
$next_quiz_time = null;
try {
    $stmt = $conn->query("SELECT * FROM quiz_settings ORDER BY id DESC LIMIT 1");
    $quiz_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($quiz_settings) {
        $quiz_start = new DateTime($quiz_settings['quiz_start']);
        $duration = $quiz_settings['quiz_duration_hours'];
        $quiz_end = clone $quiz_start;
        $quiz_end->modify("+$duration hours");
        $now = new DateTime();
        $quiz_available = $quiz_settings['is_active'] && $now >= $quiz_start && $now <= $quiz_end;
        if (!$quiz_available) {
            if ($now < $quiz_start) {
                $next_quiz_time = $quiz_start;
            } else {
                $next_quiz = clone $quiz_end;
                $next_quiz->modify('+1 day');
                $next_quiz_time = $next_quiz;
            }
        }
    } else {
        $quiz_available = true;
    }
} catch (Exception $e) {
    $quiz_available = true;
}

include 'header.php';
?>
<main>

<style>
    .hero {
        background: linear-gradient(180deg, rgba(30,60,114,0.8) 0%, rgba(30,60,114,0.6) 100%), url('images/banner.jpg');
        background-size: cover;
        background-position: center center;
        background-attachment: fixed;
        min-height: clamp(60vh, 80vh, 100vh);
        padding: clamp(3rem, 10vh, 8rem) 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,215,0,0.15) 0%, transparent 50%),
                    radial-gradient(circle at 80% 50%, rgba(255,215,0,0.15) 0%, transparent 50%);
        z-index: 1;
    }
    
    .hero > * {
        position: relative;
        z-index: 2;
    }
    
    .hero h1 {
        font-size: clamp(2rem, 8vw, 4rem);
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: var(--spacing-sm);
        text-shadow: var(--shadow-sm);
    }
    
    .hero p {
        font-size: clamp(1rem, 4vw, 1.5rem);
        max-width: min(90vw, 600px);
        line-height: 1.6;
        margin-bottom: var(--spacing-md);
    }
    
    .hero-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        justify-content: center;
        align-items: center;
    }
    
    /* Responsive hero buttons */
    @media (max-width: 480px) {
        .hero-buttons {
            flex-direction: column;
            width: 100%;
        }
        .hero-buttons .btn {
            width: 100%;
            max-width: 280px;
        }
    }
    
    /* Section titles with vars */
    .section-title h2 {
        font-size: var(--font-size-3xl);
        color: var(--primary);
    }
    
    .section-title p {
        color: var(--text-secondary);
        font-size: var(--font-size-lg);
    }
    
    /* Events/Blogs Grids - uniform aspect */
    .events-grid, .blogs-grid, .features {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(min(22rem, 95vw), 1fr));
    }
    
    .event-card, .blog-card, .feature-card {
        aspect-ratio: 3 / 4;
        overflow: hidden;
    }
    
    .event-image, .blog-image {
        aspect-ratio: 16 / 9;
        height: unset;
    }
    
    /* Leaderboard mobile stack */
    .leaderboard-container {
        --leader-gap: clamp(1rem, 4vw, 2rem);
        display: grid;
        gap: var(--leader-gap);
        max-width: min(500px, 95vw);
        margin: 0 auto;
    }
    
    @container (min-width: 480px) {
        .leaderboard-top3 {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: var(--spacing-sm);
        }
    }
    
    /* Stats grid */
    .stats {
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: var(--spacing-lg);
    }
    
    .stat-item h3 {
        font-size: clamp(2.5rem, 10vw, 4rem);
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: var(--spacing-xs);
    }
    
    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    @keyframes glow {
        0%, 100% { box-shadow: 0 0 5px rgba(255, 215, 0, 0.3); }
        50% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.6); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.8s ease-out forwards;
    }
    
    .animate-fade-in {
        animation: fadeIn 0.6s ease-out forwards;
    }
    
    /* Links - Remove underlines */
    a {
        text-decoration: none !important;
    }
    
    .hero {
        background: linear-gradient(180deg, rgba(30,60,114,0.8) 0%, rgba(30,60,114,0.6) 100%), url('images/banner.jpg');
        background-size: cover;
        background-position: center;
        padding: 150px 0;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,215,0,0.1) 0%, transparent 50%),
                    radial-gradient(circle at 80% 50%, rgba(255,215,0,0.1) 0%, transparent 50%);
    }
    .hero h1 {
        font-size: 52px;
        margin-bottom: 20px;
        animation: fadeInUp 0.8s ease-out;
    }
    .hero p {
        font-size: 20px;
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        animation: fadeInUp 0.8s ease-out 0.2s backwards;
    }
    .hero-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        animation: fadeInUp 0.8s ease-out 0.4s backwards;
    }
    
    /* Welcome & Event Popup */
    .welcome-message {
        text-align: center;
        margin-bottom: 20px;
    }
    
    /* Animated Event Popup */
    .event-popup-wrapper {
        width: 100%;
        max-width: 350px;
        margin: 20px auto 0;
        z-index: 10;
    }
    @keyframes popupPulse {
        0%, 100% { 
            opacity: 0;
            transform: scale(0.9);
        }
        10% { 
            opacity: 1;
            transform: scale(1);
        }
        90% { 
            opacity: 1;
            transform: scale(1);
        }
    }
    .event-popup-item {
        background: transparent;
        border: 2px solid rgba(255,255,255,0.8);
        border-radius: 50px;
        padding: 15px 30px;
        text-align: center;
        box-shadow: 0 0 40px rgba(255,255,255,0.15);
        animation: popupPulse 6s ease-in-out infinite;
        max-width: 350px;
    }
    .event-popup-label {
        display: inline-block;
        background: linear-gradient(135deg, #4a6fa5, #6b8cbe);
        color: white;
        padding: 5px 15px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .event-popup-item h3 {
        color: #ffffff;
        font-size: 1.5rem;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .event-popup-item .event-link {
        display: inline-block;
        margin-top: 15px;
        color: #ffffff;
        font-weight: 600;
        font-size: 13px;
    }
    .event-popup-item .event-link i {
        margin-left: 5px;
        animation: float 1s ease-in-out infinite;
    }
    
    /* Hero adjustments for popup */
    .hero {
        position: relative;
        overflow: hidden;
    }
    .hero .container {
        position: relative;
        z-index: 5;
    }
    
    /* Motto & Slogan Banner */
    .motto-banner {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1e3c72 100%);
        padding: 50px 0;
        position: relative;
        overflow: hidden;
    }
    .motto-banner::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="crown" width="30" height="30" patternUnits="userSpaceOnUse"><path d="M15 30 L20 20 L25 30 L30 15 L35 30 L40 20 L45 30" stroke="rgba(255,215,0,0.1)" fill="none" stroke-width="2"/></pattern></defs><rect width="100" height="100" fill="url(%23crown)"/></svg>');
    }
    .motto-banner::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, #FFD700, transparent);
    }
    .motto-content {
        position: relative;
        z-index: 2;
        text-align: center;
    }
    .motto-main {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 25px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        letter-spacing: 1px;
    }
    .motto-main i {
        color: #6b8cbe;
        margin-right: 10px;
    }
    .slogan-grid {
        display: flex;
        justify-content: center;
        gap: 30px;
        flex-wrap: wrap;
    }
    .slogan-item {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255,255,255,0.2);
        padding: 15px 30px;
        border-radius: 15px;
        transition: all 0.3s ease;
    }
    .slogan-item:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-5px);
    }
    .slogan-item .slogan-label {
        color: #8fa8c8;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }
    .slogan-item .slogan-text {
        color: white;
        font-size: 1.1rem;
        font-weight: 500;
    }
    .slogan-item .slogan-abbr {
        color: #8fa8c8;
        font-weight: 700;
    }
    
    .welcome-message h2 {
        color: white;
        font-size: 2rem;
        margin-bottom: 10px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    .welcome-message p {
        color: rgba(255,255,255,0.9);
        font-size: 1.1rem;
    }
    .event-popup-card {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 20px;
        padding: 30px;
        max-width: 500px;
        margin: 0 auto;
        text-align: left;
        animation: fadeInUp 0.6s ease-out 0.3s backwards;
    }
    .event-popup-label {
        display: inline-block;
        background: #FFD700;
        color: #1e3c72;
        padding: 5px 15px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 15px;
    }
    .event-popup-card h3 {
        color: white;
        font-size: 1.4rem;
        margin-bottom: 10px;
    }
    .event-popup-card .event-date {
        color: #FFD700;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .event-popup-card .event-desc {
        color: rgba(255,255,255,0.9);
        font-size: 14px;
        margin-bottom: 15px;
    }
    .event-popup-card .btn {
        display: inline-block;
        background: #FFD700;
        color: #1e3c72;
        padding: 12px 25px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .event-popup-card .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(255,215,0,0.4);
    }
    .section {
        padding: clamp(3rem, 8vw, 6rem) 0;
    }
    .section-title {
        text-align: center;
        margin-bottom: 50px;
    }
    .section-title h2 {
        color: #1e3c72;
        font-size: 36px;
        margin-bottom: 15px;
        animation: fadeInUp 0.6s ease-out;
    }
    .section-title p {
        color: #666;
        max-width: 600px;
        margin: 0 auto;
        animation: fadeInUp 0.6s ease-out 0.1s backwards;
    }
    .features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
    }
    .feature-card {
        background: white;
        padding: 40px 30px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        transition: all 0.4s ease;
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    .feature-card:nth-child(1) { animation-delay: 0.1s; }
    .feature-card:nth-child(2) { animation-delay: 0.2s; }
    .feature-card:nth-child(3) { animation-delay: 0.3s; }
    .feature-card:nth-child(4) { animation-delay: 0.4s; }
    .feature-card:hover {
        transform: translateY(-15px);
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .feature-card i {
        font-size: 48px;
        color: #1e3c72;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    .feature-card:hover i {
        color: #6b8cbe;
        transform: scale(1.2);
    }
    .feature-card h3 {
        margin-bottom: 15px;
        color: #333;
    }
    .feature-card p {
        color: #666;
    }
    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }
    .event-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        transition: all 0.4s ease;
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    .event-card:nth-child(1) { animation-delay: 0.1s; }
    .event-card:nth-child(2) { animation-delay: 0.2s; }
    .event-card:nth-child(3) { animation-delay: 0.3s; }
    .event-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .event-image {
        height: 200px;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .event-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .event-card:hover .event-image {
        background: linear-gradient(135deg, #4a6fa5, #6b8cbe);
    }
    .event-image i {
        font-size: 48px;
        color: white;
        transition: color 0.3s ease;
    }
    .event-card:hover .event-image i {
        color: #1a1a2e;
    }
    .event-content {
        padding: 25px;
    }
    .event-date {
        color: #1e3c72;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
    }
    .event-content h3 {
        margin-bottom: 10px;
        color: #333;
    }
    .event-content p {
        color: #666;
        font-size: 14px;
    }
    .blogs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }
    .blog-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        transition: all 0.4s ease;
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    .blog-card:nth-child(1) { animation-delay: 0.1s; }
    .blog-card:nth-child(2) { animation-delay: 0.2s; }
    .blog-card:nth-child(3) { animation-delay: 0.3s; }
    .blog-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .blog-image {
        height: 200px;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .blog-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .blog-image video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .blog-card:hover .blog-image {
        background: linear-gradient(135deg, #4a6fa5, #6b8cbe);
    }
    .blog-image i {
        font-size: 48px;
        color: white;
        transition: color 0.3s ease;
    }
    .blog-card:hover .blog-image i {
        color: #1a1a2e;
    }
    .blog-content {
        padding: 25px;
    }
    .blog-meta {
        color: #999;
        font-size: 12px;
        margin-bottom: 10px;
    }
    .blog-content h3 {
        margin-bottom: 10px;
        color: #333;
    }
    .blog-content p {
        color: #666;
        font-size: 14px;
    }
    .cta {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        text-align: center;
        padding: 80px 0;
        position: relative;
        overflow: hidden;
    }
    .cta::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 30% 50%, rgba(255,215,0,0.15) 0%, transparent 50%),
                    radial-gradient(circle at 70% 50%, rgba(255,215,0,0.15) 0%, transparent 50%);
    }
    .cta h2 {
        font-size: 36px;
        margin-bottom: 20px;
        animation: fadeInUp 0.6s ease-out;
    }
    .cta p {
        font-size: 18px;
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        animation: fadeInUp 0.6s ease-out 0.1s backwards;
    }
    .cta .btn {
        animation: fadeInUp 0.6s ease-out 0.2s backwards;
    }
    
    /* Mission & Vision Section */
    .mission-section {
        position: relative;
        padding: 100px 0;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        overflow: hidden;
    }
    .mission-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="cross" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M10 0h2v20H10zM0 10h20v2H0z" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23cross)"/></svg>');
    }
    .mission-content {
        position: relative;
        z-index: 2;
    }
    .mission-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
    }
    .mission-card {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        transition: all 0.4s ease;
    }
    .mission-card:hover {
        background: rgba(255,255,255,0.15);
        transform: translateY(-10px);
    }
    .mission-card i {
        font-size: 50px;
        color: #FFD700;
        margin-bottom: 20px;
    }
    .mission-card h3 {
        color: white;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    .mission-card p {
        color: rgba(255,255,255,0.9);
        line-height: 1.7;
    }
    
    /* Timeline Section */
    .timeline-section {
        padding: 100px 0;
        background: linear-gradient(180deg, #f8f9ff 0%, #fff 100%);
    }
    .timeline-mini {
        position: relative;
        max-width: 900px;
        margin: 0 auto;
    }
    .timeline-mini::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #1e3c72, #2a5298, #FFD700);
        border-radius: 4px;
    }
    .timeline-mini-item {
        display: flex;
        justify-content: flex-end;
        padding-right: 50%;
        position: relative;
        margin-bottom: 40px;
    }
    .timeline-mini-item:nth-child(even) {
        justify-content: flex-start;
        padding-right: 0;
        padding-left: 50%;
    }
    .timeline-mini-item::after {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 20px;
        height: 20px;
        background: #6b8cbe;
        border: 4px solid #1e3c72;
        border-radius: 50%;
        top: 0;
    }
    .timeline-mini-content {
        background: white;
        padding: 25px 30px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        max-width: 400px;
        margin: 0 30px;
        transition: all 0.3s ease;
    }
    .timeline-mini-content:hover {
        transform: scale(1.02);
        box-shadow: 0 15px 50px rgba(0,0,0,0.15);
    }
    .timeline-mini-year {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 10px;
    }
    .timeline-mini-content h4 {
        color: #1e3c72;
        margin-bottom: 8px;
    }
    .timeline-mini-content p {
        color: #666;
        font-size: 14px;
        margin: 0;
    }
    
    /* Testimonial Section */
    .testimonial-section {
        padding: 100px 0;
        background: white;
    }
    .testimonial-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }
    .testimonial-card {
        background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
        border-radius: 20px;
        padding: 40px;
        position: relative;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    .testimonial-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    }
    .testimonial-card::before {
        content: '"';
        font-size: 120px;
        color: rgba(30,60,114,0.1);
        position: absolute;
        top: 10px;
        left: 20px;
        font-family: Georgia, serif;
        line-height: 1;
    }
    .testimonial-text {
        color: #666;
        line-height: 1.8;
        margin-bottom: 25px;
        position: relative;
        z-index: 1;
    }
    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .testimonial-avatar {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
    }
    .testimonial-info h5 {
        color: #1e3c72;
        margin-bottom: 3px;
    }
    .testimonial-info span {
        color: #999;
        font-size: 13px;
    }
    
    /* Newsletter Section */
    .newsletter-section {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        padding: 80px 0;
        position: relative;
        overflow: hidden;
    }
    .newsletter-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,215,0,0.1) 0%, transparent 50%);
        animation: float 10s linear infinite;
    }
    .newsletter-content {
        position: relative;
        z-index: 2;
        text-align: center;
        color: white;
    }
    .newsletter-content h2 {
        font-size: 2.5rem;
        margin-bottom: 15px;
    }
    .newsletter-content p {
        opacity: 0.9;
        margin-bottom: 30px;
    }
    .newsletter-form {
        display: flex;
        max-width: 500px;
        margin: 0 auto;
        gap: 10px;
    }
    .newsletter-form input {
        flex: 1;
        padding: 15px 25px;
        border: none;
        border-radius: 30px;
        font-size: 16px;
        outline: none;
    }
    .newsletter-form button {
        padding: 15px 35px;
        background: #FFD700;
        color: #1e3c72;
        border: none;
        border-radius: 30px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .newsletter-form button:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(255,215,0,0.3);
    }
    
    /* Enhanced Section Headers */
    .section-header-enhanced {
        text-align: center;
        margin-bottom: 60px;
    }
    .section-header-enhanced .section-tag {
        display: inline-block;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
    }
    .section-header-enhanced h2 {
        font-size: 2.5rem;
        color: #1e3c72;
        margin-bottom: 15px;
    }
    .section-header-enhanced .header-line {
        width: 80px;
        height: 4px;
        background: linear-gradient(135deg, #4a6fa5, #6b8cbe);
        margin: 0 auto 20px;
        border-radius: 2px;
    }
    .section-header-enhanced p {
        color: #666;
        max-width: 600px;
        margin: 0 auto;
        font-size: 1.1rem;
    }
    
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(var(--spacing-md), 1fr));
        gap: var(--spacing-lg);
        text-align: center;
    }
    .stat-item {
        opacity: 0;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    .stat-item:nth-child(1) { animation-delay: 0.1s; }
    .stat-item:nth-child(2) { animation-delay: 0.2s; }
    .stat-item:nth-child(3) { animation-delay: 0.3s; }
    .stat-item:nth-child(4) { animation-delay: 0.4s; }
    .stat-item h3 {
        font-size: 48px;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }
    .stat-item p {
        color: #666;
        font-size: 18px;
    }
    .btn {
        /* padding: 15px 30px; */
        border-radius: 30px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-block;
    }
    .btn-primary {
        background: linear-gradient(135deg, #4a6fa5, #6b8cbe);
        color: white;
    }
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(74, 111, 165, 0.4);
    }
    .btn {
        background: white;
        color: #1e3c72;
    }
    .btn:hover {
        transform: translateY(-3px);
    }
    /* .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    } */

    .btn-donate {
            background: linear-gradient(135deg, #004279, #c0392b);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .hero h1 {
            font-size: 48px;
        }
    }
    
    @media (max-width: 992px) {
        .hero h1 {
            font-size: 40px;
        }
        .hero p {
            font-size: 18px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .features-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .blog-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    /* Mobile overrides already handled by clamp/grid */
    
    @media (max-width: 576px) {
        .hero h1 {
            font-size: 26px;
        }
        .hero p {
            font-size: 14px;
        }
        .section-title h2 {
            font-size: 28px;
        }
        .btn {
            padding: 12px 24px;
            font-size: 14px;
        }
    }
    
    @media (max-width: 480px) {
        .hero h1 {
            font-size: 22px;
        }
        .hero {
            padding: 40px 0;
        }
        .stats-card {
            padding: 20px 15px;
        }
        .stats-number {
            font-size: 32px;
        }
    }
</style>

<section class="hero">
    <div class="container">
        <?php if ($logged_in): ?>
            <!-- Logged in view with animated event popup -->
            <div class="welcome-message">
                <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p>We're glad to have you as part of the NUPS-G family</p>
            </div>
            <?php if (!empty($upcoming_events)): ?>
            <div class="event-popup-wrapper">
                <div class="event-popup-item">
                    <span class="event-popup-label"><i class="fas fa-bullhorn"></i> UPCOMING EVENT</span>
                    <h3><?php echo htmlspecialchars($upcoming_events[0]['title']); ?></h3>
                    <a href="events.php" class="event-link">View All Events <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php else: ?>
            <div class="hero-buttons">
                <a href="events.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; padding: 15px 30px; font-size: 16px;">
                    <i class="fas fa-calendar"></i> View All Events
                </a>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Not logged in view -->
            <h1>Welcome to NUPS-G Church</h1>
            <p>Experience the love of God and fellowship with a community that cares. Join us as we grow together in faith.</p>
    <div class="hero-buttons">
                <a href="register.php" class="btn btn-primary touch-btn">
                    <i class="fas fa-user-plus"></i> Join Us Today
                </a>
                <a href="events.php" class="btn touch-btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.5);">
                    <i class="fas fa-calendar"></i> View Events
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Motto & Slogan Banner -->
<section class="motto-banner">
    <div class="container">
        <div class="motto-content">
            <p class="motto-main">
                <i class="fas fa-cross"></i> FOR TO ME TO LIVE IS CHRIST AND TO DIE IS GAIN - <i>Phil. 1:21</i>
            </p>
            <div class="slogan-grid">
                <div class="slogan-item">
                    <p class="slogan-label">NUPS-G</p>
                    <p class="slogan-text"><span class="slogan-abbr">FOR CHRIST</span></p>
                </div>
                 <div class="slogan-item">
                    <p class="slogan-label">FOR CHRIST</p>
                    <p class="slogan-text"><span class="slogan-abbr">WE LIVE</span></p>
                </div>
                <div class="slogan-item">
                    <p class="slogan-label">NUPS-G SPECIAL</p>
                    <p class="slogan-text"><span class="slogan-abbr">DEVINE MANIFESTORS</span></p>
                </div>
                <div class="slogan-item">
                    <p class="slogan-label">IRRESISTIBLE</p>
                    <p class="slogan-text"><span class="slogan-abbr">SOLUTION CARRIERS</span></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision Section -->
<section class="mission-section">
    <div class="container">
        <div class="mission-content">
            <div class="section-header-enhanced">
                <span class="section-tag">Who We Are</span>
                <h2 style="color: white;">Our Mission & Vision</h2>
                <div class="header-line"></div>
            </div>
            <div class="mission-grid">
                <div class="mission-card">
                    <i class="fas fa-bullseye"></i>
                    <h3>Our Mission</h3>
                    <p>To train and equip student members to become active participants in church services, fostering spiritual growth and leadership through biblical teaching, fellowship, and community service.</p>
                </div>
                <div class="mission-card">
                    <i class="fas fa-eye"></i>
                    <h3>Our Vision</h3>
                    <p>To be a vibrant union of Presbyterian students in Ghana, transforming lives through the Gospel of Christ and producing leaders who impact the church and society.</p>
                </div>
                <div class="mission-card">
                    <i class="fas fa-heart"></i>
                    <h3>Our Values</h3>
                    <p>Faith, Unity, Service, and Excellence. We believe in the power of God's word to transform lives and the importance of fellowship among believers.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Timeline Section -->
<section class="timeline-section">
    <div class="container">
        <div class="section-header-enhanced">
            <span class="section-tag">Our Journey</span>
            <h2>Milestones in Our History</h2>
            <div class="header-line"></div>
            <p>Highlights from over 60 years of ministry and service</p>
        </div>
        <div class="timeline-mini">
            <div class="timeline-mini-item">
                <div class="timeline-mini-content">
                    <span class="timeline-mini-year">1959</span>
                    <h4>The Beginning</h4>
                    <p>NUPS-G began at Akuafo Hall Chapel of University of Ghana with 39 students</p>
                </div>
            </div>
            <div class="timeline-mini-item">
                <div class="timeline-mini-content">
                    <span class="timeline-mini-year">1960</span>
                    <h4>Inaugural Ceremony</h4>
                    <p>Official inauguration held at Legon Hall with Rev. W.S. Robertson as chairman</p>
                </div>
            </div>
            <div class="timeline-mini-item">
                <div class="timeline-mini-content">
                    <span class="timeline-mini-year">1962</span>
                    <h4>National Expansion</h4>
                    <p>First National Conference held at Commonwealth Hall, idea spreads to other institutions</p>
                </div>
            </div>
            <div class="timeline-mini-item">
                <div class="timeline-mini-content">
                    <span class="timeline-mini-year">1985</span>
                    <h4>Leadership Training</h4>
                    <p>First leadership training programme held at Trinity College for coordinating branches</p>
                </div>
            </div>
            <div class="timeline-mini-item">
                <div class="timeline-mini-content">
                    <span class="timeline-mini-year">Today</span>
                    <h4>Continuing Legacy</h4>
                    <p>NUPS-G continues to grow and impact lives through various programs and activities</p>
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 50px;">
            <a href="history.php" class="btn btn-primary">Read Full History <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title">
            <h2>What We Offer</h2>
            <p>Join our community and experience the many blessings of being part of NUPS-G Church</p>
        </div>
        <div class="features">
            <div class="feature-card">
                <i class="fas fa-bible"></i>
                <h3>Biblical Teaching</h3>
                <p>Weekly sermons and Bible study sessions that help you grow in your faith and understanding of God's word.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Fellowship</h3>
                <p>Connect with other believers through our various groups and fellowship programs.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-hands-helping"></i>
                <h3>Community Service</h3>
                <p>Join us in serving our community and making a positive impact in the lives of others.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-pray"></i>
                <h3>Prayer Support</h3>
                <p>Our prayer team is always available to pray with you and support you in times of need.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: linear-gradient(180deg, #f9f9f9, #fff);">
    <div class="container">
        <div class="section-title">
            <h2>Church Statistics</h2>
            <p>Our growing community of faith</p>
        </div>
        <div class="stats">
            <div class="stat-item">
                <h3>500+</h3>
                <p>Members</p>
            </div>
            <div class="stat-item">
                <h3><?php echo $exec_count['count']; ?></h3>
                <p>Executives</p>
            </div>
            <div class="stat-item">
                <h3>50+</h3>
                <p>Events Hosted</p>
            </div>
            <div class="stat-item">
                <h3>100+</h3>
                <p>Blog Posts</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title">
            <h2>Upcoming Events</h2>
            <p>Don't miss out on our upcoming events and activities</p>
        </div>
            <div class="events-grid">
                <?php if (empty($events)): ?>
                    <p style="text-align: center; color: var(--text-secondary);">No upcoming events at the moment.</p>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event-card">
                            <div class="event-image">
                                <?php if (!empty($event['image']) && $event['image'] != 'event-default.jpg' && file_exists('uploads/' . $event['image'])): ?>
                                    <img class="responsive-img" src="uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-calendar-event"></i>
                                <?php endif; ?>
                            </div>
                        <div class="event-content">
                            <div class="event-date"><?php echo formatDateTime($event['event_date']); ?></div>
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                            <?php if ($event['location']): ?>
                                <p style="margin-top: 10px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="text-align: center; margin-top: 40px;">
            <a href="events.php" class="btn btn-primary">View All Events</a>
        </div>
    </div>
</section>

<section class="section" style="background: linear-gradient(180deg, #f9f9f9, #fff);">
    <div class="container">
        <div class="section-title">
            <h2>Latest Blogs</h2>
            <p>Read inspiring stories and spiritual teachings</p>
        </div>
    <div class="blogs-grid">
            <?php if (empty($blogs)): ?>
                <p style="text-align: center; color: var(--text-secondary);">No blogs available at the moment.</p>
            <?php else: ?>
                <?php foreach ($blogs as $blog): ?>
                    <div class="blog-card">
                        <div class="blog-image">
                            <?php 
                            $media_type = $blog['media_type'] ?? 'image';
                            $image = $blog['image'] ?? '';
                            
                            if (!empty($image) && file_exists('uploads/' . $image)): 
                                if ($media_type === 'video'):
                            ?>
                                <video controls playsinline preload="metadata" class="responsive-img">
                                    <source src="uploads/<?php echo htmlspecialchars($image); ?>" type="video/mp4">
                                </video>
                            <?php else: ?>
                                <img class="responsive-img" src="uploads/<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" loading="lazy">
                            <?php endif; ?>
                            <?php else: ?>
                                <i class="fas fa-book-open"></i>
                            <?php endif; ?>
                        </div>
                        <div class="blog-content">
                            <div class="blog-meta"><?php echo formatDate($blog['created_at']); ?></div>
                            <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($blog['content'], 0, 100)); ?>...</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="text-align: center; margin-top: 40px;">
            <a href="blogs.php" class="btn btn-primary">View All Blogs</a>
        </div>
    </div>
</section>

<!-- Leaderboard Section -->
<section class="section">
    <div class="container">
        <div class="section-title">
            <h2><i class="fas fa-trophy" style="color: #FFD700;"></i> Quiz Leaderboard</h2>
            <p>Top performers in our daily Bible quiz</p>
        </div>
        <?php if (!$quiz_available): ?>
            <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 10px; margin-bottom: 30px; color: #856404;">
                <i class="fas fa-clock"></i> The quiz is currently unavailable.
                <?php if ($next_quiz_time): ?>
                    <br><strong>Next quiz: <?php echo $next_quiz_time->format('M j, Y g:i A'); ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($leaderboard)): ?>
            <?php if (count($leaderboard) >= 2): ?>
                <?php 
                $top3 = array_slice($leaderboard, 0, 3);
                $maxPoints = max(array_column($top3, 'total_points'));
                if ($maxPoints == 0) $maxPoints = 1;
                $minHeight = 80;
                $maxHeight = 180;
                
                $heights = [];
                foreach ($top3 as $user) {
                    $ratio = $user['total_points'] / $maxPoints;
                    $heights[] = round($minHeight + ($maxHeight - $minHeight) * $ratio);
                }
                $colors = ['#FFD700', '#C0C0C0', '#CD7F32'];
                $labels = ['1st', '2nd', '3rd'];
                $displayOrder = [1, 0, 2];
                ?>
                <div class="leaderboard-top3" style="gap: clamp(1rem, 4vw, 2rem); margin-bottom: var(--spacing-lg); padding: var(--spacing-md);">
                    <?php foreach ($displayOrder as $i => $idx): 
                        if (!isset($top3[$idx])) continue;
                        $user = $top3[$idx];
                        $height = $heights[$idx];
                        $color = $colors[$idx];
                    ?>
                        <div style="text-align: center; flex: 1;">
                            <div style="font-weight: 800; color: var(--text-primary); margin-bottom: var(--spacing-xs); font-size: clamp(1rem, 4vw, 1.25rem);">
                                <?php echo $user['total_points']; ?> pts
                            </div>
                            <div style="width: clamp(60px, 20vw, 90px); height: <?php echo $height; ?>px; background: linear-gradient(180deg, <?php echo $color; ?>, <?php echo $color; ?>66); border-radius: var(--radius-lg) var(--radius-lg) 0 0; display: flex; align-items: flex-end; justify-content: center; padding-bottom: clamp(0.75rem, 3vw, 1rem); margin: 0 auto; box-shadow: var(--shadow-md);">
                                <i class="fas fa-crown" style="color: <?php echo $idx === 0 ? '#1a1a2e' : '#333'; ?>; font-size: clamp(1.25rem, 6vw, 1.5rem);"></i>
                            </div>
                            <div style="margin-top: var(--spacing-xs); font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($user['first_name'] . ' ' . substr($user['last_name'], 0, 1)); ?>.</div>
                            <div style="background: <?php echo $color; ?>; color: #1a1a2e; padding: clamp(0.375rem, 1.5vw, 0.5rem) clamp(0.75rem, 3vw, 1rem); border-radius: var(--radius-lg); font-weight: bold; margin-top: var(--spacing-xs); display: inline-block; font-size: var(--font-size-sm);"><?php echo $labels[$idx]; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($leaderboard) > 3): ?>
                <div style="max-width: 500px; margin: 0 auto;">
                    <h4 style="text-align: center; color: #666; margin-bottom: 20px;">Other Top Performers</h4>
                    <?php foreach (array_slice($leaderboard, 3) as $index => $user): ?>
                        <div style="display: flex; align-items: center; padding: 15px 20px; background: white; border-radius: 10px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <div style="width: 35px; height: 35px; border-radius: 50%; background: #f0f0f0; color: #666; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px;">
                                <?php echo $index + 4; ?>
                            </div>
                            <div style="flex: 1; font-weight: 600; color: #333;">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </div>
                            <div style="font-weight: 700; color: #1e3c72;">
                                <?php echo $user['total_points']; ?> pts
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div style="max-width: 500px; margin: 0 auto;">
                    <?php foreach ($leaderboard as $index => $user): ?>
                        <div style="display: flex; align-items: center; padding: 15px 20px; background: white; border-radius: 10px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <div style="width: 35px; height: 35px; border-radius: 50%; <?php echo $index === 0 ? 'background: #FFD700;' : ($index === 1 ? 'background: #C0C0C0;' : 'background: #CD7F32;'); ?> color: #1a1a2e; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px;">
                                <?php echo $index + 1; ?>
                            </div>
                            <div style="flex: 1; font-weight: 600; color: #333;">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </div>
                            <div style="font-weight: 700; color: #1e3c72;">
                                <?php echo $user['total_points']; ?> pts
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="quiz.php" class="btn btn-primary">
                    <i class="fas fa-play"></i> Take the Quiz
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 12px;">
                <i class="fas fa-trophy" style="font-size: 50px; color: #ccc; margin-bottom: 15px;"></i>
                <p style="color: #666; font-size: 16px;">No scores yet. Be the first to take the quiz and claim the top spot!</p>
                <a href="quiz.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-play"></i> Start Quiz
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cta">
    <div class="container">
        <h2><?php echo $quiz_available ? 'Ready to Take the Daily Quiz?' : 'Quiz Coming Soon!'; ?></h2>
        <p><?php echo $quiz_available ? 'Test your biblical knowledge daily and compete with other members on our leaderboard to earn points!' : 'Check back soon for the next quiz challenge!'; ?></p>
        <?php if ($quiz_available): ?>
            <a href="quiz.php" class="btn" style="background: #FFD700; color: #1a1a2e; padding: 15px 30px; font-size: 16px; font-weight: 600;">
                <i class="fas fa-star"></i> Start Daily Quiz
            </a>
        <?php elseif ($next_quiz_time): ?>
            <p style="margin-top: 15px;">Next quiz: <strong><?php echo $next_quiz_time->format('M j, Y g:i A'); ?></strong></p>
            <a href="quiz.php" class="btn" style="background: #6c757d; color: white; padding: 15px 30px; font-size: 16px; font-weight: 600;">
                <i class="fas fa-clock"></i> View Quiz Page
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonial-section">
    <div class="container">
        <div class="section-header-enhanced">
            <span class="section-tag">Testimonials</span>
            <h2>What Our Members Say</h2>
            <div class="header-line"></div>
        </div>
        <div class="testimonial-grid">
            <div class="testimonial-card">
                <p class="testimonial-text">NUPS-G has been a transformative experience for me. The leadership training and fellowship opportunities have helped me grow spiritually and develop crucial life skills.</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">KK</div>
                    <div class="testimonial-info">
                        <h5>Kwadwo Kwabena</h5>
                        <span>Member since 2025</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-text">The P-SICE program gave me the opportunity to share the Gospel with people in deprived areas. It's been a blessing to see lives transformed through our outreach efforts.</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">SOS</div>
                    <div class="testimonial-info">
                        <h5>Seth Ofori Sefa</h5>
                        <span>Executive Member</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-text">Being part of NUPS-G has connected me with amazing peers and mentors. The quiz competitions, events, and community service activities have made my university years truly memorable.</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">YB</div>
                    <div class="testimonial-info">
                        <h5>Yaw Boah</h5>
                        <span>Member of the Organizing Wing</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-content">
            <h2>Stay Connected</h2>
            <p>Subscribe to our newsletter for updates on events, programs, and spiritual content</p>
            <form class="newsletter-form" onsubmit="event.preventDefault(); alert('Thank you for subscribing!');">
                <input type="email" placeholder="nupsgttulocal@gmail.com" required>
                <button type="submit"><i class="fas fa-paper-plane"></i> Subscribe</button>
            </form>
        </div>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
