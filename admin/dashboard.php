<?php
require_once '../config.php';
require_once '../db.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Generate CSRF token for form submission prevention
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Admin Dashboard';

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'");
$members_count = $stmt->fetch();

$stmt = $conn->query("SELECT COUNT(*) as count FROM events");
$events_count = $stmt->fetch();

$stmt = $conn->query("SELECT COUNT(*) as count FROM blogs");
$blogs_count = $stmt->fetch();

$stmt = $conn->query("SELECT COUNT(*) as count FROM executives");
$executives_count = $stmt->fetch();

$stmt = $conn->query("SELECT COUNT(*) as count FROM media");
$media_count = $stmt->fetch();

// Quiz statistics
$stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM quiz_results");
$quiz_taken_count = $stmt->fetch();
$total_members_count = $members_count['count'] ?? 0;
$quiz_taken = $quiz_taken_count['count'] ?? 0;
$quiz_not_taken = $total_members_count - $quiz_taken;

// Donation statistics
$stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations WHERE payment_status = 'success'");
$donations_stats = $stmt->fetch();
$donations_count = $donations_stats['count'];
$donations_total = $donations_stats['total'];

// Handle form submissions
$message = '';
$error = '';

// Add/Update Executive
if (isset($_POST['add_executive'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $order_num = (int)($_POST['order_num'] ?? 0);
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $whatsapp = sanitize($_POST['whatsapp'] ?? '');
        $image = 'default.png';
    
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'exec_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = '../uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                }
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO executives (name, position, bio, image, order_num, email, phone, whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $position, $bio, $image, $order_num, $email, $phone, $whatsapp]);
        $message = 'Executive added successfully!';
    }
}

// Delete Executive
if (isset($_POST['delete_executive'])) {
    $exec_id = (int)$_POST['exec_id'];
    $stmt = $conn->prepare("SELECT image FROM executives WHERE id = ?");
    $stmt->execute([$exec_id]);
    $exec = $stmt->fetch();
    if ($exec && $exec['image'] != 'default.png') {
        $image_path = '../uploads/' . $exec['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM executives WHERE id = ?");
    $stmt->execute([$exec_id]);
    $message = 'Executive deleted successfully!';
}

// Add Event
if (isset($_POST['add_event'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $location = sanitize($_POST['location'] ?? '');
        
        $image = 'default.png';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'event_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = '../uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                }
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, location, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $event_date, $location, $image]);
        $message = 'Event added successfully!';
    }
}

// Delete Event
if (isset($_POST['delete_event'])) {
    $event_id = (int)$_POST['event_id'];
    $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if ($event && $event['image'] != 'default.png') {
        $image_path = '../uploads/' . $event['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $message = 'Event deleted successfully!';
}

// Add Blog
if (isset($_POST['add_blog'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $author = sanitize($_POST['author'] ?? '');
        $status = sanitize($_POST['status'] ?? 'draft');
        $media_type = sanitize($_POST['media_type'] ?? 'image');
        
        $image = 'default.png';
        if ($media_type === 'image') {
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $filename = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = 'blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = '../uploads/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                        $image = $new_filename;
                    }
                }
            }
        } else {
            // Video - store URL in image field
            $image = sanitize($_POST['video_url'] ?? '');
        }
        
        // Ensure media_type column exists (PostgreSQL: ENUM unsupported, use VARCHAR + CHECK)
        try {
            if (!columnExists($conn, 'blogs', 'media_type')) {
                $conn->exec("ALTER TABLE blogs ADD COLUMN media_type VARCHAR(20) DEFAULT 'image'");
                $conn->exec("ALTER TABLE blogs ADD CONSTRAINT IF NOT EXISTS chk_blogs_media_type CHECK (media_type IN ('image', 'video'))");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        // blogs table stores author_id (FK to users), not a free-text author
        $stmt = $conn->prepare("INSERT INTO blogs (title, content, author_id, status, image, media_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $_SESSION['user_id'] ?? null, $status, $image, $media_type]);
        $message = 'Blog post added successfully!';
    }
}

// Delete Blog
if (isset($_POST['delete_blog'])) {
    $blog_id = (int)$_POST['blog_id'];
    $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
    $stmt->execute([$blog_id]);
    $blog = $stmt->fetch();
    if ($blog && $blog['image'] != 'default.png') {
        $image_path = '../uploads/' . $blog['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->execute([$blog_id]);
    $message = 'Blog post deleted successfully!';
}

// Handle Media Upload
if (isset($_POST['upload_media'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $media_type = sanitize($_POST['media_type'] ?? 'image');
        
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
            $filename = $_FILES['media_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'media_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = '../uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                    // media table uses file_type (not media_type) and requires file_path
                    $stmt = $conn->prepare("INSERT INTO media (title, file_name, file_type, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $new_filename, $media_type, $new_filename, $_SESSION['user_id'] ?? null]);
                    $message = 'Media uploaded successfully!';
                }
            } else {
                $error = 'Invalid file type.';
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
}

// Delete Media
if (isset($_POST['delete_media'])) {
    $media_id = (int)$_POST['media_id'];
    $stmt = $conn->prepare("SELECT file_name FROM media WHERE id = ?");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch();
    if ($media) {
        $file_path = '../uploads/' . $media['file_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM media WHERE id = ?");
    $stmt->execute([$media_id]);
    $message = 'Media deleted successfully!';
}

// Handle Quiz Settings
if (isset($_POST['save_quiz_settings'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $quiz_start = $_POST['quiz_start'] ?? '';
        $quiz_duration = (int)($_POST['quiz_duration'] ?? 24);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($quiz_start) {
            // Update or insert quiz settings
            $stmt = $conn->query("SELECT id FROM quiz_settings ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE quiz_settings SET quiz_start = ?, quiz_duration_hours = ?, is_active = ?::boolean WHERE id = ?");
                $stmt->execute([$quiz_start, $quiz_duration, $is_active, $existing['id']]);
            } else {
                $stmt = $conn->prepare("INSERT INTO quiz_settings (quiz_start, quiz_duration_hours, is_active) VALUES (?, ?, ?::boolean)");
                $stmt->execute([$quiz_start, $quiz_duration, $is_active]);
            }
            $message = 'Quiz settings saved successfully!';
        }
    }
}

// Add Quiz Questions
if (isset($_POST['add_quiz_questions'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $quiz_date = $_POST['quiz_date'] ?? date('Y-m-d');
        
        // Delete existing questions for this date
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_date = ?");
        $stmt->execute([$quiz_date]);
        
        // Add 5 new questions
        for ($i = 1; $i <= 5; $i++) {
            $question = sanitize($_POST['question_' . $i] ?? '');
            $option_a = sanitize($_POST['option_a_' . $i] ?? '');
            $option_b = sanitize($_POST['option_b_' . $i] ?? '');
            $option_c = sanitize($_POST['option_c_' . $i] ?? '');
            $option_d = sanitize($_POST['option_d_' . $i] ?? '');
            $correct_answer = $_POST['correct_answer_' . $i] ?? 'A';
            $points = (int)($_POST['points_' . $i] ?? 10);
            
            if ($question && $option_a && $option_b && $option_c && $option_d) {
                $stmt = $conn->prepare("INSERT INTO quiz_questions (question, option_a, option_b, option_c, option_d, correct_answer, points, quiz_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points, $quiz_date]);
            }
        }
        $message = 'Quiz questions added successfully!';
    }
}

// Delete Quiz Question
if (isset($_POST['delete_quiz_question'])) {
    $question_id = (int)$_POST['question_id'];
    $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $message = 'Quiz question deleted successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset & Base Styles - Matching Public Site */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }
        
        a {
            text-decoration: none !important;
            transition: all 0.3s ease;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Admin Header - Matching Site Theme */
        .admin-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .admin-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .admin-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-logo h1 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-logo h1 i {
            color: #FFD700;
        }
        
        .admin-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-nav a {
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: rgba(255,255,255,0.2);
            color: #FFD700;
        }
        
        .admin-user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-user-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .admin-user-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
        }
        
        .mobile-menu-toggle:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Admin Content */
        .admin-content {
            padding: 40px 0;
            min-height: calc(100vh - 80px);
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #1e3c72;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #1e3c72;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-card i {
            font-size: 36px;
            color: #1e3c72;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 32px;
            color: #1e3c72;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Admin Grid */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Admin Card */
        .admin-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow-x: hidden;
        }
        
        /* Form Styles - Ensure inputs don't overflow */
        .admin-card form {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .admin-card input,
        .admin-card textarea,
        .admin-card select {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .admin-card h2 {
            color: #1e3c72;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-card h2 i {
            color: #1e3c72;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30,60,114,0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-card {
                padding: 15px;
                border-radius: 10px;
            }
            
            .admin-card h2 {
                font-size: 16px;
                flex-wrap: wrap;
            }
            
            .form-group input,
            .form-group textarea,
            .form-group select {
                font-size: 14px;
                padding: 10px 12px;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 13px;
                width: 100%;
                text-align: center;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
        }
        
        /* Buttons */
        .btn {
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(30, 60, 114, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #eb3349, #f45c43);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(235, 51, 73, 0.3);
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Data List */
        .data-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .data-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .data-item:hover {
            background: #f8f9fa;
        }
        
        .data-item:last-child {
            border-bottom: none;
        }
        
        .data-item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .data-item-info img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .data-item-info h4 {
            color: #333;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .data-item-info p {
            color: #666;
            font-size: 12px;
        }
        
        .data-item-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 5px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 40px;
            margin-bottom: 10px;
            color: #ddd;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .admin-nav {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #1e3c72, #2a5298);
                padding: 15px;
                flex-direction: column;
                gap: 5px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            }
            
            .admin-nav.show {
                display: flex;
            }
            
            .admin-nav a {
                width: 100%;
                text-align: left;
                padding: 12px 15px;
            }
            
            .mobile-menu-toggle {
                display: flex !important;
            }
        }
        
        @media (max-width: 768px) {
            .admin-header .container {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .admin-logo {
                order: 1;
            }
            
            .mobile-menu-toggle {
                order: 2;
            }
            
            .admin-nav {
                order: 3;
                width: 100%;
            }
            
            .admin-user-section {
                order: 4;
                width: 100%;
                justify-content: center;
            }
            
            .admin-content {
                padding: 20px 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card i {
                font-size: 24px;
            }
            
            .stat-card h3 {
                font-size: 24px;
            }
            
            .admin-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-card {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-header h2 {
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-logo h1 {
                font-size: 18px;
            }
            
            .admin-user-section {
                flex-wrap: wrap;
            }
            
            .admin-user-btn {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .stat-card h3 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div class="admin-logo">
                <h1><i class="fas fa-church"></i> Admin Dashboard</h1>
            </div>
            
            <nav class="admin-nav" id="adminNav">
                <a href="#overview" class="active"><i class="fas fa-tachometer-alt"></i> Overview</a>
                <a href="#executives"><i class="fas fa-user-tie"></i> Executives</a>
                <a href="#events"><i class="fas fa-calendar"></i> Events</a>
                <a href="#blogs"><i class="fas fa-blog"></i> Blogs</a>
                <a href="#media"><i class="fas fa-images"></i> Media</a>
                <a href="#quiz"><i class="fas fa-question-circle"></i> Quiz</a>
                <a href="#donors"><i class="fas fa-hand-holding-heart"></i> Donors</a>
            </nav>
            
            <div class="admin-user-section">
                <a href="../index.php" class="admin-user-btn">
                    <i class="fas fa-home"></i> View Site
                </a>
                <a href="../profile.php" class="admin-user-btn">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
                </a>
                <a href="../logout.php" class="admin-user-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>
    
    <!-- Admin Content -->
    <main class="admin-content">
        <div class="container">
            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Overview Section -->
            <section id="overview">
                <div class="page-header">
                    <h2><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h2>
                    <p>Welcome to the NUPS-G Church Admin Dashboard</p>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <h3><?php echo $members_count['count']; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-calendar-event"></i>
                        <h3><?php echo $events_count['count']; ?></h3>
                        <p>Events</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-blog"></i>
                        <h3><?php echo $blogs_count['count']; ?></h3>
                        <p>Blog Posts</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-user-tie"></i>
                        <h3><?php echo $executives_count['count']; ?></h3>
                        <p>Executives</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-images"></i>
                        <h3><?php echo $media_count['count']; ?></h3>
                        <p>Media Files</p>
                    </div>
                    <div class="stat-card" style="border-left-color: #38ef7d;">
                        <i class="fas fa-clipboard-check"></i>
                        <h3><?php echo $quiz_taken; ?></h3>
                        <p>Quiz Taken</p>
                    </div>
                    <div class="stat-card" style="border-left-color: #f45c43;">
                        <i class="fas fa-clipboard-times"></i>
                        <h3><?php echo $quiz_not_taken; ?></h3>
                        <p>Quiz Not Taken</p>
                    </div>
                    <div class="stat-card" style="border-left-color: #FFD700;">
                        <i class="fas fa-donate"></i>
                        <h3><?php echo $donations_count; ?></h3>
                        <p>Donations (<?php echo number_format($donations_total, 2); ?> GHS)</p>
                    </div>
                </div>
            </section>
            
            <!-- Admin Forms Grid -->
            <div class="admin-grid">
                <!-- Add Executive -->
                <section class="admin-card" id="executives">
                    <h2><i class="fas fa-user-tie"></i> Add Executive</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required placeholder="Executive name">
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" name="position" required placeholder="e.g., Senior Pastor">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="email@example.com">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" placeholder="+233...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>WhatsApp</label>
                            <input type="text" name="whatsapp" placeholder="WhatsApp number">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" placeholder="Short biography"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Order Number</label>
                            <input type="number" name="order_num" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Photo</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit" name="add_executive" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Executive
                        </button>
                    </form>
                </section>
                
                <!-- Add Event -->
                <section class="admin-card" id="events">
                    <h2><i class="fas fa-calendar-plus"></i> Add Event</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Event Title</label>
                            <input type="text" name="title" required placeholder="Event title">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="datetime-local" name="event_date" required>
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" placeholder="Event location">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" placeholder="Event description"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Event Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit" name="add_event" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                    </form>
                </section>
                
                <!-- Add Blog -->
                <section class="admin-card" id="blogs">
                    <h2><i class="fas fa-pen-fancy"></i> Add Blog Post</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Blog title">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Author</label>
                                <input type="text" name="author" placeholder="Author name">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Media Type</label>
                            <select name="media_type" id="blogMediaType" onchange="toggleBlogMedia()">
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="form-group" id="blogImageGroup">
                            <label>Featured Image</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <div class="form-group" id="blogVideoGroup" style="display: none;">
                            <label>Video URL</label>
                            <input type="url" name="video_url" placeholder="https://youtube.com/watch?v=...">
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" rows="5" placeholder="Blog content"></textarea>
                        </div>
                        <button type="submit" name="add_blog" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Blog Post
                        </button>
                    </form>
                </section>
                
                <!-- Upload Media -->
                <section class="admin-card" id="media">
                    <h2><i class="fas fa-cloud-upload-alt"></i> Upload Media</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Media title">
                        </div>
                        <div class="form-group">
                            <label>Media Type</label>
                            <select name="media_type">
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>File</label>
                            <input type="file" name="media_file" accept="image/*,video/*" required>
                        </div>
                        <button type="submit" name="upload_media" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Media
                        </button>
                    </form>
                </section>
                
                <!-- Quiz Management -->
                <section class="admin-card" id="quiz">
                    <h2><i class="fas fa-question-circle"></i> Manage Quiz</h2>
                    
                    <!-- Quiz Settings -->
                    <form method="POST" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <h3 style="color: #1e3c72; font-size: 16px; margin-bottom: 15px;"><i class="fas fa-cog"></i> Quiz Settings</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Quiz Start Time</label>
                                <input type="datetime-local" name="quiz_start" value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Duration (hours)</label>
                                <input type="number" name="quiz_duration" value="24" min="1" max="72">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="is_active" checked style="width: auto;">
                                <span>Quiz Active</span>
                            </label>
                        </div>
                        <button type="submit" name="save_quiz_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                    
                    <!-- Add Quiz Questions -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <h3 style="color: #1e3c72; font-size: 16px; margin-bottom: 15px;"><i class="fas fa-plus-circle"></i> Add 5 Daily Questions</h3>
                        <div class="form-group">
                            <label>Quiz Date</label>
                            <input type="date" name="quiz_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <!-- Question 1 -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="color: #333; margin-bottom: 10px;">Question 1</h4>
                            <div class="form-group">
                                <label>Question</label>
                                <input type="text" name="question_1" required placeholder="Enter question 1">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option A</label>
                                    <input type="text" name="option_a_1" required placeholder="Option A">
                                </div>
                                <div class="form-group">
                                    <label>Option B</label>
                                    <input type="text" name="option_b_1" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option C</label>
                                    <input type="text" name="option_c_1" required placeholder="Option C">
                                </div>
                                <div class="form-group">
                                    <label>Option D</label>
                                    <input type="text" name="option_d_1" required placeholder="Option D">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correct Answer</label>
                                    <select name="correct_answer_1">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points_1" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 2 -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="color: #333; margin-bottom: 10px;">Question 2</h4>
                            <div class="form-group">
                                <label>Question</label>
                                <input type="text" name="question_2" required placeholder="Enter question 2">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option A</label>
                                    <input type="text" name="option_a_2" required placeholder="Option A">
                                </div>
                                <div class="form-group">
                                    <label>Option B</label>
                                    <input type="text" name="option_b_2" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option C</label>
                                    <input type="text" name="option_c_2" required placeholder="Option C">
                                </div>
                                <div class="form-group">
                                    <label>Option D</label>
                                    <input type="text" name="option_d_2" required placeholder="Option D">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correct Answer</label>
                                    <select name="correct_answer_2">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points_2" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 3 -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="color: #333; margin-bottom: 10px;">Question 3</h4>
                            <div class="form-group">
                                <label>Question</label>
                                <input type="text" name="question_3" required placeholder="Enter question 3">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option A</label>
                                    <input type="text" name="option_a_3" required placeholder="Option A">
                                </div>
                                <div class="form-group">
                                    <label>Option B</label>
                                    <input type="text" name="option_b_3" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option C</label>
                                    <input type="text" name="option_c_3" required placeholder="Option C">
                                </div>
                                <div class="form-group">
                                    <label>Option D</label>
                                    <input type="text" name="option_d_3" required placeholder="Option D">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correct Answer</label>
                                    <select name="correct_answer_3">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points_3" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 4 -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="color: #333; margin-bottom: 10px;">Question 4</h4>
                            <div class="form-group">
                                <label>Question</label>
                                <input type="text" name="question_4" required placeholder="Enter question 4">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option A</label>
                                    <input type="text" name="option_a_4" required placeholder="Option A">
                                </div>
                                <div class="form-group">
                                    <label>Option B</label>
                                    <input type="text" name="option_b_4" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option C</label>
                                    <input type="text" name="option_c_4" required placeholder="Option C">
                                </div>
                                <div class="form-group">
                                    <label>Option D</label>
                                    <input type="text" name="option_d_4" required placeholder="Option D">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correct Answer</label>
                                    <select name="correct_answer_4">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points_4" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 5 -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <h4 style="color: #333; margin-bottom: 10px;">Question 5</h4>
                            <div class="form-group">
                                <label>Question</label>
                                <input type="text" name="question_5" required placeholder="Enter question 5">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option A</label>
                                    <input type="text" name="option_a_5" required placeholder="Option A">
                                </div>
                                <div class="form-group">
                                    <label>Option B</label>
                                    <input type="text" name="option_b_5" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Option C</label>
                                    <input type="text" name="option_c_5" required placeholder="Option C">
                                </div>
                                <div class="form-group">
                                    <label>Option D</label>
                                    <input type="text" name="option_d_5" required placeholder="Option D">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Correct Answer</label>
                                    <select name="correct_answer_5">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Points</label>
                                    <input type="number" name="points_5" value="10" min="1" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_quiz_questions" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Quiz Questions
                        </button>
                    </form>
                </section>

                <!-- Donors Section -->
                <section class="admin-card" id="donors">
                    <div class="card-header">
                        <h2><i class="fas fa-hand-holding-heart"></i> Donors Management</h2>
                    </div>
                    <div class="card-body">
                        <!-- Donors Summary -->
                        <div class="donors-summary">
                            <div class="donor-stat-card">
                                <div class="donor-stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="donor-stat-info">
                                    <span class="donor-stat-number"><?php echo count($donors ?? []); ?></span>
                                    <span class="donor-stat-label">Total Donors</span>
                                </div>
                            </div>
                            <div class="donor-stat-card">
                                <div class="donor-stat-icon" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="donor-stat-info">
                                    <span class="donor-stat-number">$<?php echo number_format(array_sum(array_column($donors ?? [], 'amount')), 2); ?></span>
                                    <span class="donor-stat-label">Total Amount</span>
                                </div>
                            </div>
                            <div class="donor-stat-card">
                                <div class="donor-stat-icon" style="background: linear-gradient(135deg, #f39c12, #f1c40f);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="donor-stat-info">
                                    <span class="donor-stat-number"><?php echo count(array_filter($donors ?? [], function($d) { return $d['payment_status'] ?? '' === 'success'; })); ?></span>
                                    <span class="donor-stat-label">Successful</span>
                                </div>
                            </div>
                        </div>
                        
                        <p style="margin: 20px 0; color: #666;">Manage and view all donors who have contributed to the church.</p>
                        
                        <div class="table-responsive">
                            <table class="donors-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> ID</th>
                                        <th><i class="fas fa-user"></i> Name</th>
                                        <th><i class="fas fa-envelope"></i> Email</th>
                                        <th><i class="fas fa-phone"></i> Phone</th>
                                        <th><i class="fas fa-dollar-sign"></i> Amount</th>
                                        <th><i class="fas fa-status"></i> Status</th>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-cogs"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $conn->query("SELECT * FROM donations ORDER BY created_at DESC LIMIT 50");
                                        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (count($donors) > 0) {
                                            foreach ($donors as $donor) {
                                                $statusClass = ($donor['payment_status'] ?? '') === 'success' ? 'status-success' : 'status-pending';
                                                echo '<tr>';
                                                echo '<td><span class="donor-id">#' . htmlspecialchars($donor['id']) . '</span></td>';
                                                echo '<td><strong>' . htmlspecialchars($donor['donor_name']) . '</strong></td>';
                                                echo '<td>' . htmlspecialchars($donor['donor_email']) . '</td>';
                                                echo '<td>' . htmlspecialchars($donor['donor_phone'] ?? 'N/A') . '</td>';
                                                echo '<td><span class="donor-amount">$' . number_format($donor['amount'], 2) . '</span></td>';
                                                echo '<td><span class="donor-status ' . $statusClass . '">' . htmlspecialchars(ucfirst($donor['payment_status'] ?? 'pending')) . '</span></td>';
                                                echo '<td>' . date('M d, Y', strtotime($donor['created_at'])) . '</td>';
                                                echo '<td class="donor-actions">';
                                                echo '<button class="action-btn view-btn" title="View Details" onclick="viewDonor(' . $donor['id'] . ', \'' . htmlspecialchars($donor['donor_name']) . '\', \'' . htmlspecialchars($donor['donor_email']) . '\', \'' . htmlspecialchars($donor['donor_phone'] ?? 'N/A') . '\', ' . $donor['amount'] . ', \'' . htmlspecialchars($donor['message'] ?? 'No message') . '\', \'' . htmlspecialchars($donor['payment_status']) . '\', \'' . date('M d, Y H:i', strtotime($donor['created_at'])) . '\')"><i class="fas fa-eye"></i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="8" class="empty-state"><i class="fas fa-inbox"></i><p>No donors found yet.</p></td></tr>';
                                        }
                                    } catch (Exception $e) {
                                        echo '<tr><td colspan="8" class="empty-state"><i class="fas fa-database"></i><p>Database not connected. Please set up the database.</p></td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
    
    <script>
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const nav = document.getElementById('adminNav');
            nav.classList.toggle('show');
        }
        
        // Toggle Blog Media Type
        function toggleBlogMedia() {
            const mediaType = document.getElementById('blogMediaType').value;
            const imageGroup = document.getElementById('blogImageGroup');
            const videoGroup = document.getElementById('blogVideoGroup');
            
            if (mediaType === 'image') {
                imageGroup.style.display = 'block';
                videoGroup.style.display = 'none';
            } else {
                imageGroup.style.display = 'none';
                videoGroup.style.display = 'block';
            }
        }
        
        // Smooth scroll for navigation
        document.querySelectorAll('.admin-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
                
                // Update active state
                document.querySelectorAll('.admin-nav a').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // View Donor Details
        function viewDonor(id, name, email, phone, amount, message, status, date) {
            alert('Donor: ' + name + '\nEmail: ' + email + '\nPhone: ' + phone + '\nAmount: $' + amount + '\nStatus: ' + status + '\nDate: ' + date + '\nMessage: ' + message);
        }
    </script>
</body>
</html>
