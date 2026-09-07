<?php
require_once '../config.php';
require_once '../db.php';
require_once '../mailer.php';

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

// Gallery albums (folders)
$albums = [];
try {
    $stmt = $conn->query("SELECT a.*, COUNT(m.id) AS photo_count
                          FROM albums a
                          LEFT JOIN media m ON m.album_id = a.id AND m.file_type = 'image'
                          GROUP BY a.id ORDER BY a.created_at DESC");
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $albums = [];
}

// Sermons (for the audio manager)
$sermons = [];
try {
    $stmt = $conn->query("SELECT id, title, audio_file, audio_url FROM blogs WHERE post_type = 'sermon' ORDER BY created_at DESC");
    $sermons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sermons = [];
}

// Member reviews (pending / approved / rejected)
$reviews_all = [];
$pending_reviews_count = 0;
try {
    $stmt = $conn->query(
        "SELECT r.id, r.user_id, r.rating, r.content, r.status, r.created_at,
                u.first_name, u.last_name, u.email
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         ORDER BY r.created_at DESC"
    );
    $reviews_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reviews_all as $rv) {
        if ($rv['status'] === 'pending') { $pending_reviews_count++; }
    }
} catch (Exception $e) {
    $reviews_all = [];
}

// List queries for the management sections (so the admin can see & delete what was added)
$executives_all = [];
try {
    $executives_all = $conn->query("SELECT * FROM executives ORDER BY order_num ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $executives_all = []; }

$events_all = [];
try {
    $events_all = $conn->query("SELECT * FROM events ORDER BY event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $events_all = []; }

$blogs_all = [];
try {
    $blogs_all = $conn->query("SELECT * FROM blogs ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $blogs_all = []; }

$media_all = [];
try {
    $media_all = $conn->query("SELECT * FROM media ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $media_all = []; }

$quiz_questions_all = [];
try {
    $quiz_questions_all = $conn->query("SELECT * FROM quiz_questions WHERE quiz_date IS NULL ORDER BY id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $quiz_questions_all = []; }

// Current quiz settings (pre-fill the settings panel)
$quiz_settings = null;
try {
    $stmt = $conn->query("SELECT quiz_start, quiz_duration_hours, is_active FROM quiz_settings ORDER BY id DESC LIMIT 1");
    $settings_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings_row) { $quiz_settings = $settings_row; }
} catch (Exception $e) { $quiz_settings = null; }

// Defaults for the quiz settings form (fall back when no settings row exists)
$quiz_is_active = true;
if ($quiz_settings !== null) {
    $active_val = $quiz_settings['is_active'] ?? true;
    $quiz_is_active = is_bool($active_val) ? $active_val : in_array((string)$active_val, ['1', 't', 'true'], true);
}
$quiz_start_value = date('Y-m-d\TH:i');
if ($quiz_settings !== null && !empty($quiz_settings['quiz_start'])) {
    $ts = strtotime($quiz_settings['quiz_start']);
    if ($ts !== false) { $quiz_start_value = date('Y-m-d\TH:i', $ts); }
}
$quiz_duration_value = 24;
if ($quiz_settings !== null && isset($quiz_settings['quiz_duration_hours']) && (int)$quiz_settings['quiz_duration_hours'] > 0) {
    $quiz_duration_value = (int)$quiz_settings['quiz_duration_hours'];
}

// Registered members (the admin can see everyone who registered)
$members_all = [];
try {
    $members_all = $conn->query(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.total_points, u.created_at,
                (SELECT COUNT(*) FROM quiz_results qr WHERE qr.user_id = u.id) AS quiz_played,
                (SELECT COUNT(*) FROM reviews rv WHERE rv.user_id = u.id) AS reviews_count
         FROM users u ORDER BY u.created_at DESC, u.id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $members_all = []; }

// Quiz statistics
$stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM quiz_results");
$quiz_taken_count = $stmt->fetch();
$total_members_count = $members_count['count'] ?? 0;
$quiz_taken = $quiz_taken_count['count'] ?? 0;
$quiz_not_taken = max(0, $total_members_count - $quiz_taken);

// Donation statistics
$stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations WHERE payment_status = 'success'");
$donations_stats = $stmt->fetch();
$donations_count = $donations_stats['count'];
$donations_total = $donations_stats['total'];

// Newsletter subscriber statistics
$subscribers_count = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM subscribers WHERE is_active = TRUE");
    $subscribers_count = (int)($stmt->fetch()['count'] ?? 0);
} catch (Exception $e) {
    $subscribers_count = 0;
}

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
            $upload = handle_admin_image_upload($_FILES['image'], 'exec_', ROOT_PATH . '/uploads/');
            if ($upload['ok']) {
                $image = $upload['filename'];
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO executives (name, position, bio, image, order_num, email, phone, whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $position, $bio, $image, $order_num, $email, $phone, $whatsapp]);
        $exec_id = (int)$conn->lastInsertId('executives_id_seq');
        admin_audit_log($conn, $_SESSION['user_id'], 'create_executive', 'executive', $exec_id, "Created executive: $name");
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
        $image_path = ROOT_PATH . '/uploads/' . basename($exec['image']);
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM executives WHERE id = ?");
    $stmt->execute([$exec_id]);
    admin_audit_log($conn, $_SESSION['user_id'], 'delete_executive', 'executive', $exec_id);
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
            $upload = handle_admin_image_upload($_FILES['image'], 'event_', ROOT_PATH . '/uploads/');
            if ($upload['ok']) {
                $image = $upload['filename'];
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, location, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $event_date, $location, $image]);
        $event_id = (int)$conn->lastInsertId('events_id_seq');
        admin_audit_log($conn, $_SESSION['user_id'], 'create_event', 'event', $event_id, "Created event: $title");
        $message = 'Event added successfully!';

        // Notify newsletter subscribers about the new event
        $notify = notify_subscribers('event', [
            'title' => $title,
            'date' => formatDate($event_date),
            'location' => $location,
            'link' => SITE_URL . '/events.php',
        ]);
        $message .= notify_summary($notify);
    }
}

// Delete Event
if (isset($_POST['delete_event'])) {
    $event_id = (int)$_POST['event_id'];
    $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    if ($event && $event['image'] != 'default.png') {
        $image_path = ROOT_PATH . '/uploads/' . basename($event['image']);
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    admin_audit_log($conn, $_SESSION['user_id'], 'delete_event', 'event', $event_id);
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
        $post_type = ($_POST['post_type'] ?? 'article') === 'sermon' ? 'sermon' : 'article';
        $preacher = sanitize($_POST['preacher'] ?? '');
        $scripture_ref = sanitize($_POST['scripture_ref'] ?? '');
        if ($post_type !== 'sermon') {
            $preacher = '';
            $scripture_ref = '';
        }
        
        $image = 'default.png';
        if ($media_type === 'image') {
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $upload = handle_admin_image_upload($_FILES['image'], 'blog_', ROOT_PATH . '/uploads/');
                if ($upload['ok']) {
                    $image = $upload['filename'];
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
        $stmt = $conn->prepare("INSERT INTO blogs (title, content, author_id, status, image, media_type, post_type, preacher, scripture_ref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $_SESSION['user_id'] ?? null, $status, $image, $media_type, $post_type, $preacher, $scripture_ref]);
        try {
            $blog_id = (int)$conn->lastInsertId('blogs_id_seq');
        } catch (Exception $e) {
            $blog_id = 0;
        }
        $message = 'Blog post added successfully!';
        admin_audit_log($conn, $_SESSION['user_id'], 'create_blog', 'blog', $blog_id, "Created blog: $title (status: $status)");

        if ($status === 'published' && $blog_id > 0) {
            // Notify newsletter subscribers about the new blog post (or sermon)
            $notify = notify_subscribers('blog', [
                'title' => $title,
                'preview' => text_excerpt($content, 180),
                'link' => SITE_URL . '/blog-detail.php?id=' . $blog_id,
                'post_type' => $post_type,
                'preacher' => $preacher,
                'scripture' => $scripture_ref,
            ]);
            $message .= notify_summary($notify);
        } elseif ($status === 'draft') {
            $message .= ' (saved as draft; subscribers were not notified)';
        }
    }
}

// Delete Blog
if (isset($_POST['delete_blog'])) {
    $blog_id = (int)$_POST['blog_id'];
    $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
    $stmt->execute([$blog_id]);
    $blog = $stmt->fetch();
    if ($blog && $blog['image'] != 'default.png') {
        $image_path = ROOT_PATH . '/uploads/' . basename($blog['image']);
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->execute([$blog_id]);
    admin_audit_log($conn, $_SESSION['user_id'], 'delete_blog', 'blog', $blog_id);
    $message = 'Blog post deleted successfully!';
}

// Create Gallery Album (folder)
if (isset($_POST['create_album'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $album_title = sanitize($_POST['album_title'] ?? '');
        $album_desc = sanitize($_POST['album_description'] ?? '');
        if ($album_title === '') {
            $error = 'Album title is required.';
        } else {
            $cover = null;
            if (isset($_FILES['album_cover']) && $_FILES['album_cover']['error'] == 0) {
                $upload = handle_admin_image_upload($_FILES['album_cover'], 'cover_', ROOT_PATH . '/uploads/');
                if ($upload['ok']) {
                    $cover = $upload['filename'];
                }
            }
            $stmt = $conn->prepare("INSERT INTO albums (title, description, cover_image) VALUES (?, ?, ?)");
            $stmt->execute([$album_title, $album_desc, $cover]);
            $album_id = (int)$conn->lastInsertId('albums_id_seq');
            admin_audit_log($conn, $_SESSION['user_id'], 'create_album', 'album', $album_id, "Created album: $album_title");
            $message = 'Gallery folder "' . $album_title . '" created successfully!';
        }
    }
}

// Delete Gallery Album
if (isset($_POST['delete_album'])) {
    $album_id = (int)$_POST['album_id'];
    $stmt = $conn->prepare("SELECT cover_image FROM albums WHERE id = ?");
    $stmt->execute([$album_id]);
    $album = $stmt->fetch();
    // Only remove dedicated cover uploads (cover_*); never files that are also gallery photos.
    if ($album && $album['cover_image'] && strpos(basename($album['cover_image']), 'cover_') === 0) {
        $cover_path = ROOT_PATH . '/uploads/' . basename($album['cover_image']);
        if (file_exists($cover_path)) {
            unlink($cover_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM albums WHERE id = ?");
    $stmt->execute([$album_id]);
    admin_audit_log($conn, $_SESSION['user_id'], 'delete_album', 'album', $album_id);
    $message = 'Gallery folder deleted. Its photos were kept (now uncategorised).';
}

// Handle Media Upload
if (isset($_POST['upload_media'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $media_type = sanitize($_POST['media_type'] ?? 'image');
        $album_id = (int)($_POST['album_id'] ?? 0);
        $album_id = $album_id > 0 ? $album_id : null;
        
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
            $allowed_mimes = $media_type === 'video'
                ? ['video/mp4', 'video/webm']
                : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $upload = validate_uploaded_file($_FILES['media_file'], $allowed_mimes, 50 * 1024 * 1024);
            if ($upload['ok']) {
                $new_filename = 'media_' . time() . '_' . random_int(1000, 9999) . '.' . $upload['extension'];
                $upload_path = ROOT_PATH . '/uploads/' . $new_filename;
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $stmt = $conn->prepare("INSERT INTO media (title, file_name, file_type, file_path, uploaded_by, album_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $new_filename, $media_type, $new_filename, $_SESSION['user_id'] ?? null, $album_id]);
                    $media_id = (int)$conn->lastInsertId('media_id_seq');
                    admin_audit_log($conn, $_SESSION['user_id'], 'upload_media', 'media', $media_id, "Uploaded media: $title ($media_type)");
                    $message = 'Media uploaded successfully!';
                } else {
                    $error = 'Failed to move uploaded file.';
                }
            } else {
                $error = $upload['error'];
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
    admin_audit_log($conn, $_SESSION['user_id'], 'delete_media', 'media', $media_id);
    $message = 'Media deleted successfully!';
}

// Upload Resource (member downloads library)
if (isset($_POST['upload_resource'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // Store plain text (HTML is always escaped when rendered) so titles
        // like "Hymns & Songs" are not double-escaped.
        $res_title = trim(strip_tags((string)($_POST['res_title'] ?? '')));
        $res_category = trim(strip_tags((string)($_POST['res_category'] ?? '')));
        $res_description = trim(strip_tags((string)($_POST['res_description'] ?? '')));
        if ($res_title === '' || $res_category === '') {
            $error = 'Title and category are required.';
        } elseif (!isset($_FILES['res_file']) || $_FILES['res_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a document to upload.';
        } else {
            $upload = validate_uploaded_file($_FILES['res_file'], resource_allowed_mimes(), 25 * 1024 * 1024);
            if (!$upload['ok']) {
                $error = $upload['error'];
            } else {
                $stored = 'res_' . time() . '_' . random_int(1000, 9999) . '.' . $upload['extension'];
                if (!move_uploaded_file($_FILES['res_file']['tmp_name'], ROOT_PATH . '/uploads/' . $stored)) {
                    $error = 'Failed to save the uploaded file.';
                } else {
                    $original = basename($_FILES['res_file']['name']);
                    $stmt = $conn->prepare(
                        "INSERT INTO resources (title, category, description, file_name, file_path, file_size, uploaded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$res_title, $res_category, $res_description, $original, $stored, (int)$_FILES['res_file']['size'], $_SESSION['user_id'] ?? null]);
                    try {
                        $res_id = (int)$conn->lastInsertId('resources_id_seq');
                    } catch (Exception $e) {
                        $res_id = 0;
                    }
                    admin_audit_log($conn, $_SESSION['user_id'], 'upload_resource', 'resource', $res_id, "Uploaded resource: $res_title ($res_category)");
                    $message = 'Resource "' . $res_title . '" uploaded. Members can now download it from the Resources page.';
                }
            }
        }
    }
}

// Delete Resource
if (isset($_POST['delete_resource'])) {
    $resource_id = (int)$_POST['resource_id'];
    try {
        $stmt = $conn->prepare("SELECT title, file_path FROM resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $res_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res_row) {
            $res_file = ROOT_PATH . '/uploads/' . basename($res_row['file_path']);
            $stmt = $conn->prepare("DELETE FROM resources WHERE id = ?");
            $stmt->execute([$resource_id]);
            if (is_file($res_file)) {
                @unlink($res_file);
            }
            admin_audit_log($conn, $_SESSION['user_id'], 'delete_resource', 'resource', $resource_id, 'Deleted resource: ' . $res_row['title']);
            $message = 'Resource deleted successfully!';
        } else {
            $error = 'Resource not found.';
        }
    } catch (Exception $e) {
        $error = 'Could not delete that resource.';
    }
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
        // Bank mode (no date, rows go into the future pool) vs specific-date
        // mode (the classic admin-published paper for one named day).
        $bankMode = isset($_POST['quiz_questions_mode']) && $_POST['quiz_questions_mode'] === 'bank';
        $quiz_date = $bankMode ? null : ($_POST['quiz_date'] ?? date('Y-m-d'));
        
        if (!$bankMode && $quiz_date) {
            // Clear any already-published paper for this date before (re-)adding.
            $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_date = ?");
            $stmt->execute([$quiz_date]);
        }
        
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

// Review moderation: approve / reject / delete
if (isset($_POST['review_action']) && isset($_POST['review_id'])) {
    $rv_id = (int)$_POST['review_id'];
    $action = $_POST['review_action'];
    try {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE reviews SET status = 'approved', approved_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$rv_id]);
            $message = 'Review approved and is now live on the homepage testimonials.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$rv_id]);
            $message = 'Review rejected.';
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$rv_id]);
            $message = 'Review deleted.';
        } else {
            $error = 'Unknown review action.';
        }
        if ($message) {
            admin_audit_log($conn, $_SESSION['user_id'], 'review_' . $action, 'review', $rv_id);

            // Notify the member about the decision on their review
            if ($action === 'approve' || $action === 'reject') {
                try {
                    $stmt = $conn->prepare(
                        "SELECT r.content, u.email, u.first_name
                         FROM reviews r JOIN users u ON u.id = r.user_id
                         WHERE r.id = ?"
                    );
                    $stmt->execute([$rv_id]);
                    $rvw = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($rvw && !empty($rvw['email'])) {
                        $approved = ($action === 'approve');
                        $subject = $approved ? 'Your review is live on ' . SITE_NAME : 'Update on your ' . SITE_NAME . ' review';
                        $greeting = $rvw['first_name'] ? 'Hello ' . htmlspecialchars($rvw['first_name']) . ',' : 'Hello,';
                        $body  = '<p>' . $greeting . '</p>';
                        $body .= '<p>Thank you for sharing your review with ' . htmlspecialchars(SITE_NAME) . ':</p>';
                        $body .= '<blockquote style="margin:14px 0; padding:12px 16px; border-left:4px solid #f2a900; background:#faf6ea; color:#3c465c; border-radius:8px;">' . nl2br(htmlspecialchars($rvw['content'], ENT_QUOTES, 'UTF-8')) . '</blockquote>';
                        if ($approved) {
                            $body .= '<p><strong style="color:#16a34a;">Approved!</strong> Your review is now featured in the testimonials on our homepage.</p>';
                            $body .= '<p style="margin-top:18px;"><a href="' . htmlspecialchars(SITE_URL) . '/" style="background:#1e3a8a; color:#fff; padding:11px 24px; border-radius:999px; text-decoration:none; font-weight:700;">See it on the homepage</a></p>';
                        } else {
                            $body .= '<p>We’re sorry, but this review was <strong style="color:#dc2626;">not approved</strong> for public display.</p>';
                            $body .= '<p style="color:#8a94a8; font-size:13px;">This could be because it doesn’t quite fit our guidelines. You are welcome to submit another review anytime from your profile page.</p>';
                        }
                        $body .= '<p style="margin-top:16px;">God bless you,<br>' . htmlspecialchars(SITE_NAME) . ' Team</p>';
                        $mail_res = send_mail($rvw['email'], $subject, $body);
                        if (!empty($mail_res['sent'])) {
                            error_log('Review decision email sent to ' . $rvw['email']);
                        } else {
                            error_log('Review decision email failed for ' . $rvw['email'] . ': ' . ($mail_res['error'] ?? 'unknown'));
                        }
                    }
                } catch (Exception $e) {
                    error_log('Failed to fetch review for notification: ' . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Could not update the review.';
    }
}

// Attach / remove sermon audio (file upload or YouTube link)
if (isset($_POST['attach_sermon_audio']) || isset($_POST['remove_sermon_audio'])) {
    $audio_blog_id = (int)($_POST['blog_id'] ?? 0);
    try {
        $stmt = $conn->prepare("SELECT post_type, audio_file FROM blogs WHERE id = ?");
        $stmt->execute([$audio_blog_id]);
        $audio_blog = $stmt->fetch();
    } catch (Exception $e) {
        $audio_blog = false;
    }

    if (!$audio_blog || $audio_blog['post_type'] !== 'sermon') {
        $error = 'Audio can only be attached to sermons.';
    } elseif (isset($_POST['remove_sermon_audio'])) {
        if (!empty($audio_blog['audio_file'])) {
            $old = ROOT_PATH . '/uploads/' . basename($audio_blog['audio_file']);
            if (is_file($old)) {
                unlink($old);
            }
        }
        $stmt = $conn->prepare("UPDATE blogs SET audio_file = NULL, audio_url = NULL WHERE id = ?");
        $stmt->execute([$audio_blog_id]);
        admin_audit_log($conn, $_SESSION['user_id'], 'remove_sermon_audio', 'blog', $audio_blog_id);
        $message = 'Sermon audio removed.';
    } else {
        $mode = ($_POST['audio_mode'] ?? 'file') === 'youtube' ? 'youtube' : 'file';
        if ($mode === 'youtube') {
            $yt = trim($_POST['audio_url'] ?? '');
            if (!preg_match('~^https://(www\.)?(youtube\.com/watch\?v=[\w-]+|youtu\.be/[\w-]+|www\.youtube\.com/embed/[\w-]+)~i', $yt)) {
                $error = 'Please paste a valid YouTube link (youtube.com/watch?v=…, youtu.be/…, or an /embed/ URL).';
            } else {
                if (!empty($audio_blog['audio_file'])) {
                    $old = ROOT_PATH . '/uploads/' . basename($audio_blog['audio_file']);
                    if (is_file($old)) {
                        unlink($old);
                    }
                }
                $stmt = $conn->prepare("UPDATE blogs SET audio_file = NULL, audio_url = ? WHERE id = ?");
                $stmt->execute([$yt, $audio_blog_id]);
                admin_audit_log($conn, $_SESSION['user_id'], 'attach_sermon_audio', 'blog', $audio_blog_id, 'YouTube audio: ' . $yt);
                $message = 'YouTube audio attached to the sermon.';
            }
        } else {
            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $allowed_audio = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav'];
                $upload = validate_uploaded_file($_FILES['audio_file'], $allowed_audio, 50 * 1024 * 1024);
                if ($upload['ok']) {
                    $new_audio = 'audio_' . time() . '_' . random_int(1000, 9999) . '.' . $upload['extension'];
                    if (move_uploaded_file($_FILES['audio_file']['tmp_name'], ROOT_PATH . '/uploads/' . $new_audio)) {
                        if (!empty($audio_blog['audio_file'])) {
                            $old = ROOT_PATH . '/uploads/' . basename($audio_blog['audio_file']);
                            if (is_file($old)) {
                                unlink($old);
                        }
                        }
                        $stmt = $conn->prepare("UPDATE blogs SET audio_url = NULL, audio_file = ? WHERE id = ?");
                        $stmt->execute([$new_audio, $audio_blog_id]);
                        admin_audit_log($conn, $_SESSION['user_id'], 'attach_sermon_audio', 'blog', $audio_blog_id, 'Audio file: ' . $new_audio);
                        $message = 'Sermon audio uploaded.';
                    } else {
                        $error = 'Failed to save the uploaded audio file.';
                    }
                } else {
                    $error = $upload['error'];
                }
            } else {
                $error = 'Please choose an audio file (MP3, M4A, OGG or WAV, up to 50MB).';
            }
        }
    }
}

// Delete Quiz Question
if (isset($_POST['delete_quiz_question'])) {
    $question_id = (int)$_POST['question_id'];
    $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $message = 'Quiz question deleted successfully!';
}

// Delete Member (registered user)
if (isset($_POST['delete_member'])) {
    $member_id = (int)$_POST['member_id'];
    $member_email = '';
    try {
        $stmt = $conn->prepare("SELECT email, role FROM users WHERE id = ?");
        $stmt->execute([$member_id]);
        $member_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($member_row) { $member_email = $member_row['email']; }
    } catch (Exception $e) {}

    if ($member_id === (int)$_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } elseif (isset($member_row['role']) && $member_row['role'] === 'admin') {
        $error = 'You cannot delete another admin account.';
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'");
            $stmt->execute([$member_id]);
            admin_audit_log($conn, $_SESSION['user_id'], 'delete_member', 'user', $member_id, 'Deleted member: ' . $member_email);
            $message = 'Member deleted successfully. Their reviews, quiz results and donations were also removed.';
        } catch (Exception $e) {
            $error = 'Could not delete that member.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset & Base Styles - Matching Public Site */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
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
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            margin-bottom: 30px;
            padding: 26px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 26px rgba(30, 60, 114, 0.22);
        }
        
        .page-header h2 {
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header h2 i {
            color: #FFD700;
        }
        
        .page-header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
        }
        
        /* Stats Grid - modern tinted tiles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 36px;
        }

        .stat-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(15, 30, 66, 0.07);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(15, 30, 66, 0.13);
        }

        .stat-card i {
            width: 50px;
            height: 50px;
            flex: none;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 19px;
            margin: 0;
        }

        .stat-card h3 {
            font-size: 26px;
            line-height: 1.05;
            color: #16224a;
            margin: 0;
        }

        .stat-card p {
            color: #6b7691;
            font-size: 12.5px;
            font-weight: 600;
            margin: 5px 0 0;
        }

        /* Per-card accent colour */
        .stats-grid .stat-card:nth-of-type(1) i { background: #e3ebfa; color: #1e3a8a; }
        .stats-grid .stat-card:nth-of-type(2) i { background: #e0ecfe; color: #2563eb; }
        .stats-grid .stat-card:nth-of-type(3) i { background: #e6e4fb; color: #6d28d9; }
        .stats-grid .stat-card:nth-of-type(4) i { background: #d9f3ee; color: #0d9488; }
        .stats-grid .stat-card:nth-of-type(5) i { background: #f3e8fd; color: #a21caf; }
        .stats-grid .stat-card:nth-of-type(6) i { background: #dcf5e6; color: #16a34a; }
        .stats-grid .stat-card:nth-of-type(7) i { background: #fde3e8; color: #e11d48; }
        .stats-grid .stat-card:nth-of-type(8) i { background: #fdf0d3; color: #b7791f; }
        .stats-grid .stat-card:nth-of-type(9) i { background: #d9effb; color: #0284c7; }
        
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

        /* ---- Mobile pass (phone-first) ---- */
        @media (max-width: 600px) {
            /* Dashboard Overview: compact 2-column stat tiles */
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 24px;
            }

            .stat-card {
                display: flex;
                align-items: center;
                gap: 12px;
                text-align: left;
                padding: 13px 14px;
            }

            .stat-card i {
                font-size: 17px;
                margin: 0;
                flex: none;
                width: 42px;
                height: 42px;
                display: grid;
                place-items: center;
                background: #eef2f9;
                border-radius: 12px;
                color: #1e3c72;
            }

            .stat-card h3 {
                font-size: 21px;
                line-height: 1.1;
                margin: 0;
                white-space: nowrap;
            }

            .stat-card p {
                font-size: 11px;
                line-height: 1.35;
                margin-top: 4px;
                margin-bottom: 0;
            }

            .page-header {
                margin-bottom: 18px;
                padding: 20px 22px;
            }

            .page-header h2 {
                font-size: 20px;
                margin-bottom: 6px;
            }

            /* Long tables: swipe horizontally instead of squashing the page */
            .admin-content table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }
        }

        @media (max-width: 370px) {
            .stat-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 6px;
                padding: 12px 8px;
            }

            .stat-card i {
                width: 38px;
                height: 38px;
            }
        }

        /* ---------- Donors Management ---------- */
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .card-header h2 {
            margin-bottom: 0;
        }

        .donors-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin: 6px 0 22px;
        }

        .donor-stat-card {
            display: flex;
            align-items: center;
            gap: 13px;
            background: #f8fafd;
            border: 1px solid #e6eaf3;
            border-radius: 14px;
            padding: 14px;
        }

        .donor-stat-icon {
            width: 46px;
            height: 46px;
            flex: none;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-size: 18px;
            color: #fff;
            background: linear-gradient(135deg, #1e3a8a, #4164c6);
            box-shadow: 0 6px 14px rgba(30, 58, 138, 0.25);
        }

        .donor-stat-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .donor-stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: #16224a;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .donor-stat-label {
            color: #8a94a8;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e6eaf3;
            border-radius: 14px;
            background: #fff;
        }

        .donors-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }

        .donors-table thead th {
            background: #f4f7fc;
            color: #5a6b8c;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-align: left;
            padding: 13px 15px;
            border-bottom: 1px solid #e6eaf3;
            white-space: nowrap;
        }

        .donors-table thead th i {
            margin-right: 6px;
            opacity: 0.75;
        }

        .donors-table tbody td {
            padding: 12px 15px;
            font-size: 13.5px;
            color: #3c4a63;
            border-bottom: 1px solid #eef1f7;
            white-space: nowrap;
        }

        .donors-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .donors-table tbody tr {
            transition: background 0.15s ease;
        }

        .donors-table tbody tr:hover td {
            background: #f8fbfe;
        }

        .donor-id {
            display: inline-block;
            background: #eef2f9;
            color: #1e3a8a;
            font-weight: 800;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .donor-amount {
            font-weight: 800;
            color: #0d9488;
            font-variant-numeric: tabular-nums;
        }

        .donor-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 999px;
        }

        .donor-status::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-success {
            background: #dcf5e6;
            color: #16a34a;
        }

        .status-pending {
            background: #fdf0d3;
            color: #b7791f;
        }

        .status-failed {
            background: #fde3e3;
            color: #dc2626;
        }

        .donor-actions .action-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #eef2f9;
            color: #1e3a8a;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .donor-actions .action-btn:hover {
            background: #1e3a8a;
            color: #fff;
            transform: translateY(-1px);
        }

        .donors-table .empty-state {
            text-align: center;
            padding: 44px 20px !important;
            color: #8a94a8;
            white-space: normal;
        }

        .donors-table .empty-state i {
            display: block;
            font-size: 30px;
            color: #c3cce0;
            margin-bottom: 8px;
        }

        @media (max-width: 820px) {
            .donors-summary {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        /* ---------- Quiz Management ---------- */
        .quiz-intro {
            color: #8a94a8;
            font-size: 13.5px;
            margin: -6px 0 18px;
            max-width: 760px;
            line-height: 1.5;
        }

        .quiz-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            padding: 6px 14px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .quiz-status-pill i { font-size: 8px; }

        .quiz-status-pill.on { background: #dcf5e6; color: #16a34a; }

        .quiz-status-pill.off { background: #fde3e3; color: #dc2626; }

        /* Quiz destination picker (bank vs date) */
        .quiz-mode {
            border: 1px solid #e6eaf3;
            border-radius: 14px;
            padding: 12px 12px 14px;
            margin: 0 0 18px;
            background: #fbfcfe;
        }

        .quiz-mode legend {
            padding: 0 8px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #5a6b8c;
        }

        .quiz-mode legend i { color: #f2a900; }

        .quiz-mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .quiz-mode-card {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 42px 14px 14px;
            border: 1.5px solid #e5e9f2;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .quiz-mode-card:hover {
            border-color: #c3cee3;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.07);
        }

        .quiz-mode-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .quiz-mode-icon {
            flex: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 16px;
            color: #1e3c72;
            background: #eef2f9;
            transition: all 0.18s ease;
        }

        .quiz-mode-body { flex: 1; min-width: 0; }

        .quiz-mode-body b {
            display: block;
            color: #1e3c72;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .quiz-mode-body small {
            display: block;
            color: #6b7fa8;
            font-size: 12px;
            line-height: 1.55;
            font-weight: 500;
        }

        .quiz-mode-tick {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #d6ddeb;
            background: #fff;
            display: grid;
            place-items: center;
            font-size: 10px;
            color: transparent;
            transition: all 0.18s ease;
        }

        .quiz-mode-card input:checked ~ .quiz-mode-tick {
            background: #f2a900;
            border-color: #f2a900;
            color: #fff;
        }

        .quiz-mode-card input:checked ~ .quiz-mode-icon {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
        }

        .quiz-mode-card:has(input:checked) {
            border-color: #f2a900;
            background: #fffaf0;
            box-shadow: 0 0 0 1px rgba(242, 169, 0, 0.3), 0 6px 16px rgba(242, 169, 0, 0.12);
        }

        .quiz-mode-card:focus-within {
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.16);
        }

        .quiz-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 22px;
            padding: 12px;
            background: #f4f7fc;
            border: 1px solid #e6eaf3;
            border-radius: 14px;
        }

        .quiz-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            background: transparent;
            color: #5a6b8c;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quiz-tab:hover {
            background: #fff;
            color: #1e3c72;
            border-color: #e6eaf3;
        }

        .quiz-tab.active {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            box-shadow: 0 4px 14px rgba(30, 60, 114, 0.28);
        }

        .quiz-panel { display: none; }

        .quiz-panel[data-quiz-panel="add"] { display: block; }

        .quiz-panel-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .quiz-panel-head h3 {
            margin: 0;
            color: #1e3c72;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quiz-panel-head p {
            margin: 0;
            color: #8a94a8;
            font-size: 12.5px;
        }

        .quiz-question {
            border: 1px solid #e6eaf3;
            border-radius: 14px;
            background: #f8fafd;
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .quiz-question-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .quiz-question-num {
            width: 30px;
            height: 30px;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            font-size: 13px;
            font-weight: 800;
        }

        .quiz-question-head input {
            flex: 1;
            min-width: 0;
            padding: 11px 14px;
            border: 1px solid #dde3ef;
            border-radius: 9px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
            transition: all 0.25s ease;
        }

        .quiz-question-head input:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.12);
        }

        .quiz-question .form-group { margin-bottom: 13px; }

        .quiz-bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .quiz-bank-tile {
            background: #fff;
            border: 1px solid #e6eaf3;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .quiz-bank-tile .qb-label {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #8a94a8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .quiz-bank-tile .qb-label i { color: #1e3a8a; font-size: 12px; }

        .quiz-bank-tile .qb-num {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
            color: #1e3a8a;
            font-variant-numeric: tabular-nums;
        }

        .quiz-bank-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: #f8fafd;
            border: 1px dashed #dde3ef;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 0 0 12px;
            color: #5a6b8c;
            font-size: 13px;
            line-height: 1.55;
        }

        .quiz-bank-note i { color: #1e3a8a; margin-top: 2px; }

        .quiz-bank-warn {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: #fdf6ea;
            border: 1px solid #f3dfb8;
            border-radius: 12px;
            padding: 11px 14px;
            margin: 0;
            color: #8a4a1a;
            font-size: 13px;
            font-weight: 600;
        }

        .quiz-bank-warn i { margin-top: 2px; }

        .quiz-question-row {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e6eaf3;
            border-radius: 12px;
            padding: 10px 14px;
            background: #fafbfd;
            margin-bottom: 8px;
            transition: border-color 0.15s ease;
        }

        .quiz-question-row:hover { border-color: #c9d4ea; }

        .quiz-question-row .qq-body { flex: 1; min-width: 0; }

        .quiz-question-row .qq-body strong {
            display: block;
            color: #1e3c72;
            font-size: 13.5px;
            line-height: 1.45;
        }

        .quiz-question-row .qq-meta {
            color: #8a94a8;
            font-size: 12px;
            margin-top: 3px;
        }

        .quiz-empty {
            color: #8a94a8;
            font-size: 13.5px;
            background: #f8fafd;
            border: 1px dashed #dde3ef;
            border-radius: 12px;
            padding: 26px 20px;
            text-align: center;
            margin: 0;
        }

        .quiz-empty i {
            display: block;
            font-size: 22px;
            color: #c3cce0;
            margin-bottom: 8px;
        }

        @media (max-width: 576px) {
            .quiz-tabs { gap: 6px; padding: 10px; }
            .quiz-mode-grid { grid-template-columns: 1fr; }
            .quiz-tab { padding: 8px 12px; font-size: 12px; }
            .quiz-bank-grid { grid-template-columns: repeat(2, 1fr); }
            .quiz-question { padding: 13px 14px; }
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
                <a href="#members"><i class="fas fa-users"></i> Members</a>
                <a href="#executives"><i class="fas fa-user-tie"></i> Executives</a>
                <a href="#events"><i class="fas fa-calendar"></i> Events</a>
                <a href="#blogs"><i class="fas fa-blog"></i> Blogs</a>
                <a href="#media"><i class="fas fa-images"></i> Media</a>
                <a href="#resources"><i class="fas fa-file-download"></i> Resources</a>
                <a href="#sermon-audio"><i class="fas fa-headphones"></i> Sermon Audio</a>
                <a href="#quiz"><i class="fas fa-question-circle"></i> Quiz</a>
                <a href="#donors"><i class="fas fa-hand-holding-heart"></i> Donors</a>
                <a href="#reviews"><i class="fas fa-star"></i> Reviews<?php echo $pending_reviews_count > 0 ? ' <span style="background:#ffd700; color:#101b3d; border-radius:999px; padding:0 7px; font-size:11px;">' . (int)$pending_reviews_count . '</span>' : ''; ?></a>
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
                    <h2><i class="fas fa-user-shield"></i> Welcome back, <?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name'] ?? ''))[0] ?: 'Admin'); ?>!</h2>
                    <p>Manage members, events, blogs, media, resources, donors and more from one place.</p>
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
                    <div class="stat-card" style="border-left-color: #4cc9f0;">
                        <i class="fas fa-envelope-open-text"></i>
                        <h3><?php echo $subscribers_count; ?></h3>
                        <p>Newsletter Subscribers</p>
                    </div>
                </div>
            </section>

            <!-- Registered Members -->
            <section id="members" class="admin-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> Registered Members <span style="font-size:12px; color:#888; font-weight:500;">(<?php echo (int)$members_count['count'] ?? 0; ?> members)</span></h2>
                </div>
                <?php if (empty($members_all)): ?>
                    <div class="empty-state"><i class="fas fa-user-plus"></i><p>No members registered yet.</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="donors-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Name</th>
                                    <th><i class="fas fa-envelope"></i> Email</th>
                                    <th><i class="fas fa-phone"></i> Phone</th>
                                    <th><i class="fas fa-tag"></i> Role</th>
                                    <th><i class="fas fa-star"></i> Quiz Points</th>
                                    <th><i class="fas fa-clipboard-check"></i> Quizzes</th>
                                    <th><i class="fas fa-calendar-alt"></i> Joined</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members_all as $mb): ?>
                                <tr>
                                    <td><span class="donor-id">#<?php echo (int)$mb['id']; ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($mb['first_name'] . ' ' . $mb['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mb['email']); ?></td>
                                    <td><?php echo htmlspecialchars($mb['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($mb['role'] === 'admin'): ?>
                                            <span class="donor-status" style="background:#e6e4fb; color:#6d28d9;">Admin</span>
                                        <?php else: ?>
                                            <span class="donor-status" style="background:#dcf5e6; color:#16a34a;">Member</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:800; color:#1e3a8a;"><?php echo (int)$mb['total_points']; ?></td>
                                    <td><?php echo (int)$mb['quiz_played']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($mb['created_at'])); ?></td>
                                    <td>
                                        <?php if ((int)$mb['id'] === (int)$_SESSION['user_id'] || $mb['role'] === 'admin'): ?>
                                            <span style="color:#8a94a8; font-size:12px; font-weight:600;">N/A</span>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this member permanently? Their reviews, quiz results and donations will be removed too.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="member_id" value="<?php echo (int)$mb['id']; ?>">
                                                <button type="submit" name="delete_member" class="btn btn-danger" style="padding:5px 11px; font-size:12px;"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
                    <?php if (!empty($executives_all)): ?>
                    <hr style="border:none; border-top:2px solid #eee; margin:22px 0 14px;">
                    <h3 style="color:#1e3c72; font-size:16px; margin-bottom:12px;"><i class="fas fa-list"></i> Current Executives</h3>
                    <?php foreach ($executives_all as $ex): ?>
                    <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                        <span style="width:44px; height:44px; border-radius:10px; flex:none; background:#e9edf6; color:#6b7fa8; display:grid; place-items:center; overflow:hidden;">
                            <?php if (!empty($ex['image']) && $ex['image'] !== 'default.png' && file_exists('../uploads/' . basename($ex['image']))): ?>
                                <img src="../uploads/<?php echo htmlspecialchars(basename($ex['image'])); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-user-tie"></i>
                            <?php endif; ?>
                        </span>
                        <div style="flex:1; min-width:0;">
                            <strong style="color:#1e3c72;"><?php echo htmlspecialchars($ex['name']); ?></strong>
                            <small style="color:#888; display:block;"><?php echo htmlspecialchars($ex['position']); ?></small>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this executive?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="exec_id" value="<?php echo (int)$ex['id']; ?>">
                            <button type="submit" name="delete_executive" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
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
                    <?php if (!empty($events_all)): ?>
                    <hr style="border:none; border-top:2px solid #eee; margin:22px 0 14px;">
                    <h3 style="color:#1e3c72; font-size:16px; margin-bottom:12px;"><i class="fas fa-list"></i> Current Events</h3>
                    <?php foreach ($events_all as $ev): ?>
                    <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                        <span style="width:44px; height:44px; border-radius:10px; flex:none; background:#e9edf6; color:#6b7fa8; display:grid; place-items:center; overflow:hidden;">
                            <?php if (!empty($ev['image']) && $ev['image'] !== 'default.png' && file_exists('../uploads/' . basename($ev['image']))): ?>
                                <img src="../uploads/<?php echo htmlspecialchars(basename($ev['image'])); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-calendar"></i>
                            <?php endif; ?>
                        </span>
                        <div style="flex:1; min-width:0;">
                            <strong style="color:#1e3c72;"><?php echo htmlspecialchars($ev['title']); ?></strong>
                            <small style="color:#888; display:block;"><?php echo date('M d, Y', strtotime($ev['event_date'])); ?><?php echo $ev['location'] ? ' · ' . htmlspecialchars($ev['location']) : ''; ?></small>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this event?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="event_id" value="<?php echo (int)$ev['id']; ?>">
                            <button type="submit" name="delete_event" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </section>
                
                <!-- Add Blog -->
                <section class="admin-card" id="blogs">
                    <h2><i class="fas fa-pen-fancy"></i> Write Article / Sermon</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Article or sermon title">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Author</label>
                                <input type="text" name="author" placeholder="Author name">
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <select name="post_type" id="blogPostType" onchange="toggleSermonFields()">
                                    <option value="article">Article</option>
                                    <option value="sermon">Sermon</option>
                                </select>
                            </div>
                        </div>
                        <div id="sermonFields" style="display:none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Preacher</label>
                                    <input type="text" name="preacher" placeholder="e.g. Rev. Seth Ofori Sefa">
                                </div>
                                <div class="form-group">
                                    <label>Scripture Reference</label>
                                    <input type="text" name="scripture_ref" placeholder="e.g. Romans 10:17">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
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
                            <textarea name="content" rows="5" placeholder="Article or sermon content"></textarea>
                        </div>
                        <button type="submit" name="add_blog" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Publish
                        </button>
                    </form>
                    <?php if (!empty($blogs_all)): ?>
                    <hr style="border:none; border-top:2px solid #eee; margin:22px 0 14px;">
                    <h3 style="color:#1e3c72; font-size:16px; margin-bottom:12px;"><i class="fas fa-list"></i> Published Posts (<?php echo count($blogs_all); ?>)</h3>
                    <?php foreach ($blogs_all as $bl): ?>
                    <?php $bl_type = ($bl['post_type'] ?? 'article') === 'sermon' ? 'Sermon' : 'Article'; ?>
                    <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                        <span style="width:44px; height:44px; border-radius:10px; flex:none; background:#e9edf6; color:#6b7fa8; display:grid; place-items:center; overflow:hidden;">
                            <?php if (!empty($bl['image']) && $bl['image'] !== 'default.png' && file_exists('../uploads/' . basename($bl['image']))): ?>
                                <img src="../uploads/<?php echo htmlspecialchars(basename($bl['image'])); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-pen-fancy"></i>
                            <?php endif; ?>
                        </span>
                        <div style="flex:1; min-width:0;">
                            <strong style="color:#1e3c72;"><?php echo htmlspecialchars($bl['title']); ?></strong>
                            <small style="color:#888; display:block;"><?php echo $bl_type; ?> · <?php echo ucfirst(htmlspecialchars($bl['status'])); ?> · <?php echo date('M d, Y', strtotime($bl['created_at'])); ?></small>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="blog_id" value="<?php echo (int)$bl['id']; ?>">
                            <button type="submit" name="delete_blog" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
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
                            <label>Gallery Folder (optional)</label>
                            <select name="album_id">
                                <option value="">No folder</option>
                                <?php foreach ($albums as $al): ?>
                                    <option value="<?php echo (int)$al['id']; ?>"><?php echo htmlspecialchars($al['title']); ?></option>
                                <?php endforeach; ?>
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
                    <?php if (!empty($media_all)): ?>
                    <hr style="border:none; border-top:2px solid #eee; margin:22px 0 14px;">
                    <h3 style="color:#1e3c72; font-size:16px; margin-bottom:12px;"><i class="fas fa-list"></i> Media Library (<?php echo count($media_all); ?>)</h3>
                    <?php foreach ($media_all as $md): ?>
                    <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                        <span style="width:44px; height:44px; border-radius:10px; flex:none; background:#e9edf6; color:#6b7fa8; display:grid; place-items:center; overflow:hidden;">
                            <?php if ($md['file_type'] === 'image' && file_exists('../uploads/' . basename($md['file_name']))): ?>
                                <img src="../uploads/<?php echo htmlspecialchars(basename($md['file_name'])); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-file-video"></i>
                            <?php endif; ?>
                        </span>
                        <div style="flex:1; min-width:0;">
                            <strong style="color:#1e3c72;"><?php echo htmlspecialchars($md['title']); ?></strong>
                            <small style="color:#888; display:block;"><?php echo htmlspecialchars(ucfirst($md['file_type'])); ?> · <?php echo date('M d, Y', strtotime($md['created_at'])); ?></small>
                        </div>
                        <form method="POST" onsubmit="return confirm('Delete this media file?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="media_id" value="<?php echo (int)$md['id']; ?>">
                            <button type="submit" name="delete_media" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Gallery Folders (Albums) -->
                <section class="admin-card" id="albums">
                    <h2><i class="fas fa-folder-open"></i> Gallery Folders</h2>
                    <p style="color:#888; margin-top:-8px;">Each folder is one programme (e.g. “P-SICE Day 1”). Visitors open a folder to see and download its photos.</p>
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px; padding-bottom:20px; border-bottom:2px solid #eee;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Folder Name</label>
                            <input type="text" name="album_title" required placeholder="e.g. P-SICE Outreach Day 1">
                        </div>
                        <div class="form-group">
                            <label>Short Description</label>
                            <input type="text" name="album_description" placeholder="What happened at this programme?">
                        </div>
                        <div class="form-group">
                            <label>Cover Photo (optional)</label>
                            <input type="file" name="album_cover" accept="image/*">
                        </div>
                        <button type="submit" name="create_album" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Folder
                        </button>
                    </form>
                    <?php if (empty($albums)): ?>
                        <p style="color:#888;">No folders yet. Create one above, then upload photos into it.</p>
                    <?php else: ?>
                        <?php foreach ($albums as $al): ?>
                        <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                            <?php if (!empty($al['cover_image']) && file_exists('../uploads/' . basename($al['cover_image']))): ?>
                                <img src="../uploads/<?php echo htmlspecialchars(basename($al['cover_image'])); ?>" alt="" style="width:52px; height:52px; object-fit:cover; border-radius:8px;">
                            <?php else: ?>
                                <span style="width:52px; height:52px; border-radius:8px; background:#e9edf6; color:#6b7fa8; display:grid; place-items:center;"><i class="fas fa-images"></i></span>
                            <?php endif; ?>
                            <div style="flex:1; min-width:0;">
                                <strong style="color:#1e3c72;"><?php echo htmlspecialchars($al['title']); ?></strong>
                                <small style="color:#888; display:block;"><?php echo (int)$al['photo_count']; ?> photo<?php echo ((int)$al['photo_count']) === 1 ? '' : 's'; ?></small>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this folder? Its photos will be kept (uncategorised).');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="album_id" value="<?php echo (int)$al['id']; ?>">
                                <button type="submit" name="delete_album" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Member Resources (downloads library) -->
                <section class="admin-card" id="resources">
                    <h2><i class="fas fa-file-download"></i> Member Resources</h2>
                    <p style="color:#888; margin-top:-8px;">Upload documents for members to download from the public <b>Resources</b> page: the union constitution, hymn book, handbooks, forms, reports and more. PDF, Word, Excel, PowerPoint, RTF or text, up to 25MB.</p>
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom:22px; padding-bottom:22px; border-bottom:2px solid #eee;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="res_title" required placeholder="e.g. NUPS-G Union Constitution">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" name="res_category" list="res_category_options" required placeholder="e.g. Constitution">
                                <datalist id="res_category_options">
                                    <option value="Constitution"></option>
                                    <option value="Hymns &amp; Songs"></option>
                                    <option value="Handbook"></option>
                                    <option value="Devotional"></option>
                                    <option value="Forms"></option>
                                    <option value="Reports &amp; Minutes"></option>
                                    <option value="General"></option>
                                </datalist>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Short description (optional)</label>
                            <input type="text" name="res_description" placeholder="What members should know about this document">
                        </div>
                        <div class="form-group">
                            <label>Document file</label>
                            <input type="file" name="res_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.rtf,.txt,.csv" required>
                        </div>
                        <button type="submit" name="upload_resource" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Resource
                        </button>
                    </form>

                    <?php
                    $resources_all = [];
                    try {
                        $stmt = $conn->query("SELECT * FROM resources ORDER BY created_at DESC, id DESC");
                        $resources_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) { $resources_all = []; }
                    ?>
                    <h3 style="color:#1e3c72; font-size:16px; margin-bottom:12px;"><i class="fas fa-list"></i> Resource Library (<?php echo count($resources_all); ?>)</h3>
                    <?php if (empty($resources_all)): ?>
                        <p style="color:#888;">No resources yet. Upload the union constitution or hymn book above and it will appear on the public Resources page right away.</p>
                    <?php else: ?>
                        <?php foreach ($resources_all as $rs):
                            $rs_ico = resource_file_icon($rs['file_name']); ?>
                        <div style="display:flex; align-items:center; gap:12px; border:1px solid #e5e9f2; border-radius:10px; padding:8px 12px; background:#fafbfd; margin-bottom:8px;">
                            <span style="width:44px; height:44px; border-radius:10px; flex:none; display:grid; place-items:center; font-size:1.15rem; color:<?php echo $rs_ico['color']; ?>; background:<?php echo $rs_ico['color']; ?>1a;">
                                <i class="fas <?php echo $rs_ico['icon']; ?>"></i>
                            </span>
                            <div style="flex:1; min-width:0;">
                                <strong style="color:#1e3c72;"><?php echo htmlspecialchars($rs['title']); ?></strong>
                                <small style="color:#888; display:block;">
                                    <span style="display:inline-block; font-size:11px; font-weight:800; color:#1e3a8a; background:#e6edfb; padding:1px 9px; border-radius:999px; margin-right:4px;"><?php echo htmlspecialchars($rs['category']); ?></span>
                                    <?php echo $rs_ico['label']; ?> &middot; <?php echo human_filesize($rs['file_size'] ?? 0); ?> &middot; Added <?php echo date('M d, Y', strtotime($rs['created_at'])); ?> &middot; <i class="fas fa-download" style="font-size:.7rem;"></i> <?php echo (int)$rs['downloads']; ?>
                                </small>
                            </div>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete &ldquo;<?php echo htmlspecialchars(addslashes($rs['title'])); ?>&rdquo;? The file will be removed and members will no longer see it.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="resource_id" value="<?php echo (int)$rs['id']; ?>">
                                <button type="submit" name="delete_resource" class="btn btn-danger" style="padding:6px 12px; font-size:13px;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Member Reviews -->
                <section class="admin-card" id="reviews">
                    <h2><i class="fas fa-star"></i> Member Reviews <span style="font-size:12px; color:#888;">(<?php echo (int)$pending_reviews_count; ?> pending)</span></h2>
                    <p style="color:#888; margin-top:-8px;">Reviews are pending until you approve them. Only approved reviews show on the homepage testimonials.</p>
                    <?php if (empty($reviews_all)): ?>
                        <p style="color:#888;">No reviews yet. Members can submit reviews from their profile page.</p>
                    <?php else: ?>
                        <?php foreach ($reviews_all as $rv): ?>
                        <div style="border:1px solid #e5e9f2; border-radius:10px; padding:12px 14px; background:#fafbfd; margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="font-weight:800; color:#1e3c72;"><?php echo htmlspecialchars($rv['first_name'] . ' ' . $rv['last_name']); ?></span>
                                <span style="color:#b7791f; font-weight:700;"><?php echo str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']); ?></span>
                                <?php if ($rv['status'] === 'pending'): ?>
                                    <span style="font-size:12px; font-weight:800; color:#b7791f; background:#fdf0d3; padding:3px 10px; border-radius:999px;">Pending</span>
                                <?php elseif ($rv['status'] === 'approved'): ?>
                                    <span style="font-size:12px; font-weight:800; color:#16a34a; background:#dcf5e6; padding:3px 10px; border-radius:999px;">Approved · Live</span>
                                <?php else: ?>
                                    <span style="font-size:12px; font-weight:800; color:#dc2626; background:#fde3e3; padding:3px 10px; border-radius:999px;">Rejected</span>
                                <?php endif; ?>
                                <small style="color:#8a94a8; margin-left:auto;"><?php echo htmlspecialchars($rv['email']); ?> · <?php echo date('M d, Y', strtotime($rv['created_at'])); ?></small>
                            </div>
                            <p style="margin:8px 0 10px; color:#3c4a63; font-size:13.5px;"><?php echo nl2br(htmlspecialchars($rv['content'])); ?></p>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <?php if ($rv['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
                                    <input type="hidden" name="review_action" value="approve">
                                    <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:13px;"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($rv['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
                                    <input type="hidden" name="review_action" value="reject">
                                    <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:13px;"><i class="fas fa-times"></i> Reject</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
                                    <input type="hidden" name="review_action" value="delete">
                                    <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:13px; background:#6c757d;"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Sermon Audio Manager -->
                <section class="admin-card" id="sermon-audio">
                    <h2><i class="fas fa-headphones"></i> Sermon Audio</h2>
                    <p style="color:#888; margin-top:-8px;">Attach an MP3 upload or a YouTube link to any sermon. Members can listen right on the sermon page.</p>
                    <?php if (empty($sermons)): ?>
                        <p style="color:#888;">No sermons yet. Write one above (Type: Sermon), then attach its audio here.</p>
                    <?php else: ?>
                        <?php foreach ($sermons as $sn): ?>
                        <div style="border:1px solid #e5e9f2; border-radius:10px; padding:12px 14px; background:#fafbfd; margin-bottom:10px;">
                            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <strong style="color:#1e3c72; flex:1; min-width:200px;"><?php echo htmlspecialchars($sn['title']); ?></strong>
                                <?php if (!empty($sn['audio_file'])): ?>
                                    <span style="font-size:12px; font-weight:800; color:#0d9488; background:#dcf5e6; padding:3px 10px; border-radius:999px;"><i class="fas fa-file-audio"></i> Audio file</span>
                                <?php elseif (!empty($sn['audio_url'])): ?>
                                    <span style="font-size:12px; font-weight:800; color:#dc2626; background:#fde3e3; padding:3px 10px; border-radius:999px;"><i class="fab fa-youtube"></i> YouTube</span>
                                <?php else: ?>
                                    <span style="font-size:12px; font-weight:800; color:#8a94a8; background:#eef2f9; padding:3px 10px; border-radius:999px;">No audio</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="blog_id" value="<?php echo (int)$sn['id']; ?>">
                                <select name="audio_mode" style="padding:8px 10px; border:1px solid #ddd; border-radius:8px; font-size:13px;">
                                    <option value="file">Upload audio file</option>
                                    <option value="youtube">YouTube link</option>
                                </select>
                                <input type="file" name="audio_file" accept="audio/*" style="font-size:12px; max-width:220px;">
                                <input type="url" name="audio_url" placeholder="https://youtube.com/watch?v=..." style="padding:8px 10px; border:1px solid #ddd; border-radius:8px; font-size:13px; flex:1; min-width:180px;">
                                <button type="submit" name="attach_sermon_audio" class="btn btn-primary" style="padding:8px 16px; font-size:13px;">Attach</button>
                                <?php if (!empty($sn['audio_file']) || !empty($sn['audio_url'])): ?>
                                <button type="submit" name="remove_sermon_audio" class="btn btn-danger" style="padding:8px 16px; font-size:13px;">Remove</button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- Quiz Management -->
                <section class="admin-card" id="quiz">
                    <div class="card-header">
                        <h2><i class="fas fa-question-circle"></i> Manage Quiz</h2>
                        <span class="quiz-status-pill <?php echo $quiz_is_active ? 'on' : 'off'; ?>"><i class="fas fa-circle"></i> Quiz <?php echo $quiz_is_active ? 'Active' : 'Paused'; ?></span>
                    </div>
                    <p class="quiz-intro">Schedule quiz windows, manage a rolling question bank and publish daily papers: everything in one place.</p>

                    <div class="quiz-tabs" role="tablist" aria-label="Quiz management sections">
                        <button type="button" class="quiz-tab active" data-quiz-tab="add" role="tab" aria-selected="true"><i class="fas fa-plus-circle"></i> Add Questions</button>
                        <button type="button" class="quiz-tab" data-quiz-tab="bank" role="tab" aria-selected="false"><i class="fas fa-book-open"></i> Question Bank</button>
                        <button type="button" class="quiz-tab" data-quiz-tab="settings" role="tab" aria-selected="false"><i class="fas fa-cog"></i> Quiz Settings</button>
                        <button type="button" class="quiz-tab" data-quiz-tab="manage" role="tab" aria-selected="false"><i class="fas fa-list"></i> Manage Questions</button>
                    </div>
                    <?php
                    /* ---------- Question-bank status (for the panel just above the forms) ---------- */
                    $bank_total = 0;
                    $bank_unused = 0;
                    $bank_three_day_coverage = 0;
                    $bank_rotation_age = 0;
                    try {
                        $stmt = $conn->query(
                            "SELECT COUNT(*) AS total,
                                    SUM(CASE WHEN quiz_date IS NULL THEN 1 ELSE 0 END) AS unused,
                                    AVG((CURRENT_DATE - COALESCE(last_used_on, CURRENT_DATE))) AS avg_age
                             FROM quiz_questions
                             WHERE quiz_date IS NULL OR last_used_on IS NOT NULL"
                        );
                        $row = $stmt->fetch();
                        $bank_total = (int)($row['total'] ?? 0);
                        $bank_unused = (int)($row['unused'] ?? 0);
                        $avg = (float)($row['avg'] ?? 0);
                        $bank_rotation_age = $avg > 0 ? (int)round($avg) : 0;
                        // If the bank had at least 10 unused questions, the auto-publish
                        // could cover today, tomorrow and the day after (three full days).
                        $bank_three_day_coverage = ($bank_unused >= 10) ? 3 : 0;
                    } catch (Exception $e) {
                        $bank_total = 0;
                        $bank_unused = 0;
                    }
                    ?>
                    
                    <!-- Panel: Quiz Settings -->
                    <div class="quiz-panel" data-quiz-panel="settings" role="tabpanel">
                        <div class="quiz-panel-head">
                            <h3><i class="fas fa-cog"></i> Quiz Settings</h3>
                            <p>When the daily quiz opens and how long it stays live.</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Quiz Start Time</label>
                                    <input type="datetime-local" name="quiz_start" value="<?php echo htmlspecialchars($quiz_start_value); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Duration (hours)</label>
                                    <input type="number" name="quiz_duration" value="<?php echo (int)$quiz_duration_value; ?>" min="1" max="72">
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="is_active" style="width:auto; margin:0;" <?php echo $quiz_is_active ? 'checked' : ''; ?>>
                                    <span>Quiz Active</span>
                                </label>
                            </div>
                            <button type="submit" name="save_quiz_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Panel: Question Bank -->
                    <div class="quiz-panel" data-quiz-panel="bank" role="tabpanel">
                        <div class="quiz-panel-head">
                            <h3><i class="fas fa-book-open"></i> Question Bank</h3>
                            <p>Auto-built daily papers drawn from your pool of questions.</p>
                        </div>
                        <div class="quiz-bank-grid">
                            <div class="quiz-bank-tile">
                                <span class="qb-label"><i class="fas fa-database"></i> In the bank</span>
                                <span class="qb-num"><?php echo $bank_total; ?></span>
                            </div>
                            <div class="quiz-bank-tile">
                                <span class="qb-label"><i class="fas fa-layer-group"></i> Unused</span>
                                <span class="qb-num"><?php echo $bank_unused; ?></span>
                            </div>
                            <div class="quiz-bank-tile">
                                <span class="qb-label"><i class="fas fa-calendar-day"></i> Quizzes ahead</span>
                                <span class="qb-num"><?php echo $bank_three_day_coverage; ?></span>
                            </div>
                            <div class="quiz-bank-tile">
                                <span class="qb-label"><i class="fas fa-rotate"></i> Rotation age (days)</span>
                                <span class="qb-num"><?php echo $bank_rotation_age; ?></span>
                            </div>
                        </div>
                        <p class="quiz-bank-note"><i class="fas fa-info-circle"></i>
                            <span>When members open the quiz on a day that has no questions yet, today's paper is automatically built from the bank and the used questions are marked so the pool keeps rotating. Add questions whenever you want. No deadline-day logins needed.</span>
                        </p>
                        <?php if ($bank_unused < 10): ?>
                        <p class="quiz-bank-warn"><i class="fas fa-triangle-exclamation"></i>
                            <span>Only <?php echo $bank_unused; ?> unused bank questions remain. Add a few more so future quiz days don't run short.</span>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Panel: Add Questions -->
                    <div class="quiz-panel" data-quiz-panel="add" role="tabpanel">
                    <!-- Add Quiz Questions -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <h3 style="color: #1e3c72; font-size: 16px; margin-bottom: 15px;">
                            <i class="fas fa-plus-circle"></i> Add 5 Questions
                        </h3>
                        <fieldset class="quiz-mode">
                            <legend><i class="fas fa-sign-in-alt" style="margin-right:5px;"></i> Where should these go?</legend>
                            <div class="quiz-mode-grid">
                                <label class="quiz-mode-card">
                                    <input type="radio" name="quiz_questions_mode" value="bank" checked>
                                    <span class="quiz-mode-icon"><i class="fas fa-layer-group"></i></span>
                                    <span class="quiz-mode-body">
                                        <b>Add to the Question Bank</b>
                                        <small>Upload here whenever inspiration strikes. On the first quiz page load each day, the system automatically picks the best 10 for that day, so you don't need to publish them manually.</small>
                                    </span>
                                    <span class="quiz-mode-tick"><i class="fas fa-check"></i></span>
                                </label>
                                <label class="quiz-mode-card">
                                    <input type="radio" name="quiz_questions_mode" value="date">
                                    <span class="quiz-mode-icon"><i class="fas fa-calendar-day"></i></span>
                                    <span class="quiz-mode-body">
                                        <b>Publish for a Date</b>
                                        <small>Manually set the quiz paper for one specific date (old behaviour).</small>
                                    </span>
                                    <span class="quiz-mode-tick"><i class="fas fa-check"></i></span>
                                </label>
                            </div>
                        </fieldset>
                        <div class="form-group" id="quizDateGroup">
                            <label>Quiz Date</label>
                            <input type="date" name="quiz_date" id="quizDateInput" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <script>(function(){var dg=document.getElementById('quizDateGroup');var di=document.getElementById('quizDateInput');function sync(){
    var bank=document.querySelector('input[name=quiz_questions_mode][value=bank]').checked;
    dg.style.display=bank?('none'):('');
 di.required=!bank;}(function(){var radios=document.querySelectorAll('input[name=quiz_questions_mode]');
 radios.forEach(function(r){r.addEventListener('change',sync);});sync();})();</script>
                        
                        <!-- Question 1 -->
                        <div class="quiz-question">
                            <div class="quiz-question-head">
                                <span class="quiz-question-num">1</span>
                                <input type="text" name="question_1" required placeholder="Type question 1...">
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
                        <div class="quiz-question">
                            <div class="quiz-question-head">
                                <span class="quiz-question-num">2</span>
                                <input type="text" name="question_2" required placeholder="Type question 2...">
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
                        <div class="quiz-question">
                            <div class="quiz-question-head">
                                <span class="quiz-question-num">3</span>
                                <input type="text" name="question_3" required placeholder="Type question 3...">
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
                        <div class="quiz-question">
                            <div class="quiz-question-head">
                                <span class="quiz-question-num">4</span>
                                <input type="text" name="question_4" required placeholder="Type question 4...">
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
                        <div class="quiz-question">
                            <div class="quiz-question-head">
                                <span class="quiz-question-num">5</span>
                                <input type="text" name="question_5" required placeholder="Type question 5...">
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
                    </div><!-- /add panel -->

                    <!-- Panel: Manage Questions -->
                    <div class="quiz-panel" data-quiz-panel="manage" role="tabpanel">
                        <div class="quiz-panel-head">
                            <h3><i class="fas fa-list"></i> Bank Questions</h3>
                            <p><?php echo count($quiz_questions_all); ?> recent bank questions shown (newest first).</p>
                        </div>
                        <?php if (!empty($quiz_questions_all)): ?>
                        <?php foreach ($quiz_questions_all as $qq): ?>
                        <div class="quiz-question-row">
                            <div class="qq-body">
                                <strong><?php echo htmlspecialchars($qq['question']); ?></strong>
                                <span class="qq-meta">Answer: <?php echo htmlspecialchars($qq['correct_answer']); ?> · <?php echo (int)$qq['points']; ?> pts<?php echo !empty($qq['last_used_on']) ? ' · Last used ' . htmlspecialchars($qq['last_used_on']) : ' · Never used'; ?></span>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this quiz question?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="question_id" value="<?php echo (int)$qq['id']; ?>">
                                <button type="submit" name="delete_quiz_question" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Delete</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="quiz-empty">
                            <i class="fas fa-inbox"></i>
                            No bank questions yet. Add your first set of questions above.
                        </p>
                        <?php endif; ?>
                    </div>

                    <script>
                    (function(){
                        var quizSection = document.getElementById('quiz');
                        if (!quizSection) return;
                        var tabs = quizSection.querySelectorAll('.quiz-tab');
                        var panels = quizSection.querySelectorAll('.quiz-panel');
                        function showTab(name){
                            tabs.forEach(function(t){
                                var on = t.getAttribute('data-quiz-tab') === name;
                                t.classList.toggle('active', on);
                                t.setAttribute('aria-selected', on ? 'true' : 'false');
                            });
                            panels.forEach(function(p){
                                p.style.display = (p.getAttribute('data-quiz-panel') === name) ? 'block' : 'none';
                            });
                        }
                        tabs.forEach(function(t){
                            t.addEventListener('click', function(){ showTab(t.getAttribute('data-quiz-tab')); });
                        });
                        var hashTab = (location.hash || '').match(/^#quiz-(add|bank|settings|manage)$/);
                        showTab(hashTab ? hashTab[1] : 'add');
                    })();
                    </script>
                </section>

                <!-- Donors Section -->
                <section class="admin-card" id="donors">
                    <div class="card-header">
                        <h2><i class="fas fa-hand-holding-heart"></i> Donors Management</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        // Load donors once so the summary counts and the table agree
                        $donors = [];
                        try {
                            $stmt = $conn->query("SELECT * FROM donations ORDER BY created_at DESC LIMIT 50");
                            $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $donors = [];
                        }
                        ?>
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
                                    <span class="donor-stat-number">GHS <?php echo number_format(array_sum(array_column($donors ?? [], 'amount')), 2); ?></span>
                                    <span class="donor-stat-label">Total Amount</span>
                                </div>
                            </div>
                            <div class="donor-stat-card">
                                <div class="donor-stat-icon" style="background: linear-gradient(135deg, #f39c12, #f1c40f);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="donor-stat-info">
                                    <span class="donor-stat-number"><?php echo count(array_filter($donors ?? [], function($d) { return ($d['payment_status'] ?? '') === 'success'; })); ?></span>
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
                                        if (count($donors) > 0) {
                                            foreach ($donors as $donor) {
                                                $statusClass = ($donor['payment_status'] ?? '') === 'success' ? 'status-success' : (($donor['payment_status'] ?? '') === 'failed' ? 'status-failed' : 'status-pending');
                                                echo '<tr>';
                                                echo '<td><span class="donor-id">#' . htmlspecialchars($donor['id']) . '</span></td>';
                                                echo '<td><strong>' . htmlspecialchars($donor['donor_name']) . '</strong></td>';
                                                echo '<td>' . htmlspecialchars($donor['donor_email']) . '</td>';
                                                echo '<td>' . htmlspecialchars($donor['donor_phone'] ?? 'N/A') . '</td>';
                                                 echo '<td><span class="donor-amount">GHS ' . number_format($donor['amount'], 2) . '</span></td>';
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
        
        // Show preacher / scripture fields only for sermons
        function toggleSermonFields() {
            const type = document.getElementById('blogPostType');
            const box = document.getElementById('sermonFields');
            if (type && box) {
                box.style.display = (type.value === 'sermon') ? 'block' : 'none';
            }
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

