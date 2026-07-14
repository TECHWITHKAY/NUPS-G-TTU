<?php
require_once 'config.php';
require_once 'db.php';

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

// Ensure media_type column exists in blogs table
try {
    if (!columnExists($conn, 'blogs', 'media_type')) {
        // PostgreSQL: ENUM is not supported, use VARCHAR with a CHECK constraint.
        // ADD COLUMN IF NOT EXISTS keeps this safe for already-migrated schemas.
        $conn->exec("ALTER TABLE blogs ADD COLUMN IF NOT EXISTS media_type VARCHAR(20) DEFAULT 'image'");
        $conn->exec("ALTER TABLE blogs ADD CONSTRAINT IF NOT EXISTS chk_blogs_media_type CHECK (media_type IN ('image', 'video'))");
    }
} catch (Exception $e) {
    // Table might not exist yet
}

$stmt = $conn->query("SELECT COUNT(*) as count FROM media");
$media_count = $stmt->fetch();

// Get quiz statistics
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'member'");
$total_members = $stmt->fetch();

$stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM quiz_results");
$quiz_taken_count = $stmt->fetch();

$total_members_count = $total_members['count'] ?? 0;
$quiz_taken = $quiz_taken_count['count'] ?? 0;
$quiz_not_taken = $total_members_count - $quiz_taken;

// Quiz pass/fail (assuming pass is score >= 50% of total)
// NULLIF avoids division-by-zero errors (PostgreSQL is strict, unlike MySQL)
$stmt = $conn->query("SELECT 
    SUM(CASE WHEN (score * 100.0 / NULLIF(total_questions, 0)) >= 50 THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN (score * 100.0 / NULLIF(total_questions, 0)) < 50 THEN 1 ELSE 0 END) as failed
    FROM quiz_results");
$quiz_pass_fail = $stmt->fetch();
$quiz_passed = $quiz_pass_fail['passed'] ?? 0;
$quiz_failed = $quiz_pass_fail['failed'] ?? 0;

// Get donation statistics
$stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations WHERE payment_status = 'success'");
$donations_stats = $stmt->fetch();
$donations_count = $donations_stats['count'];
$donations_total = $donations_stats['total'];

// Get recent donations
$stmt = $conn->query("SELECT * FROM donations ORDER BY created_at DESC LIMIT 10");
$recent_donations = $stmt->fetchAll();

// Handle form submissions
$message = '';
$error = '';

// Add/Update Executive
if (isset($_POST['add_executive'])) {
    // Validate CSRF token
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
    
        // Handle image upload
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
        
        if (empty($name) || empty($position)) {
            $error = 'Name and position are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO executives (name, position, bio, order_num, image, email, phone, whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $position, $bio, $order_num, $image, $email, $phone, $whatsapp])) {
                $message = 'Executive added successfully!';
            } else {
                $error = 'Failed to add executive.';
            }
        }
    }
}

// Delete Executive
if (isset($_POST['delete_executive'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
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
        if ($stmt->execute([$exec_id])) {
            $message = 'Executive deleted successfully!';
        } else {
            $error = 'Failed to delete executive.';
        }
    }
}

// Add Event
if (isset($_POST['add_event'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $location = sanitize($_POST['location'] ?? '');
        $image = 'event-default.jpg';
    
        // Convert datetime-local format to PostgreSQL TIMESTAMP format
        if (!empty($event_date)) {
            // Validate and parse the datetime-local input (format: YYYY-MM-DDTHH:MM)
            $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
            
            if ($dateTime === false) {
                // Invalid date format - clear to trigger validation error below
                $event_date = '';
            } else {
                // Format to SQL TIMESTAMP (YYYY-MM-DD HH:MM:SS)
                $event_date = $dateTime->format('Y-m-d H:i:s');
            }
        }
        
        // Handle image upload
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
        
        if (empty($title) || empty($event_date)) {
            $error = 'Title and date are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, location, image, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $event_date, $location, $image, $_SESSION['user_id']])) {
                $message = 'Event added successfully!';
            } else {
                $error = 'Failed to add event.';
            }
        }
    }
}

// Delete Event
if (isset($_POST['delete_event'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $event_id = (int)$_POST['event_id'];
        
        $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if ($event && $event['image'] != 'event-default.jpg') {
            $image_path = '../uploads/' . $event['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        if ($stmt->execute([$event_id])) {
            $message = 'Event deleted successfully!';
        } else {
            $error = 'Failed to delete event.';
        }
    }
}

// Get existing executives
$existing_executives = [];
try {
    $stmt = $conn->query("SELECT * FROM executives ORDER BY order_num ASC, id DESC");
    $existing_executives = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existing_executives = [];
}

// Get existing events
$existing_events = [];
try {
    $stmt = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
    $existing_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existing_events = [];
}

// Add Blog
if (isset($_POST['add_blog'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $status = sanitize($_POST['status'] ?? 'published');
        $image = 'blog-default.jpg';
        $media_type = sanitize($_POST['media_type'] ?? 'image');
    
        // Handle media upload (image or video)
        $upload_error = '';
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0 && !empty($_FILES['media_file']['name'])) {
            $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_videos = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
            $filename = $_FILES['media_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Use absolute path for uploads (from admin folder go up one level)
            $upload_dir = dirname(__DIR__) . '/uploads/'; // This goes to parent of admin (root)
            
            // Check if it's an image
            if (in_array($ext, $allowed_images)) {
                $media_type = 'image';
                $new_filename = 'blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                } elseif (copy($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                } else {
                    $upload_error = 'Failed to upload image. Path: ' . $upload_path;
                }
            }
            // Check if it's a video
            elseif (in_array($ext, $allowed_videos)) {
                $media_type = 'video';
                $new_filename = 'blog_video_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                } elseif (copy($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $image = $new_filename;
                } else {
                    $upload_error = 'Failed to upload video. Path: ' . $upload_path;
                }
            } else {
                $upload_error = 'Invalid file type: ' . $ext . '. Allowed: ' . implode(', ', array_merge($allowed_images, $allowed_videos));
            }
        }
        
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } elseif (!empty($upload_error)) {
            $error = $upload_error;
        } else {
            // Check if media_type column exists, if not use old insert
            $column_exists = columnExists($conn, 'blogs', 'media_type');
            
            if ($column_exists) {
                $stmt = $conn->prepare("INSERT INTO blogs (title, content, image, media_type, author_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $image, $media_type, $_SESSION['user_id'], $status])) {
                    $message = 'Blog added successfully!';
                } else {
                    $error = 'Failed to add blog.';
                }
            } else {
                // Fallback for older schema without media_type
                $stmt = $conn->prepare("INSERT INTO blogs (title, content, image, author_id, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $image, $_SESSION['user_id'], $status])) {
                    $message = 'Blog added successfully!';
                } else {
                    $error = 'Failed to add blog.';
                }
            }
        }
    }
}

// Get existing blogs
$existing_blogs = [];
try {
    $stmt = $conn->query("SELECT b.*, (u.first_name || ' ' || u.last_name) AS username FROM blogs b LEFT JOIN users u ON b.author_id = u.id ORDER BY b.created_at DESC");
    $existing_blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existing_blogs = [];
}

// Delete Blog
if (isset($_POST['delete_blog'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $blog_id = (int)$_POST['blog_id'];
        
        $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
        $stmt->execute([$blog_id]);
        $blog = $stmt->fetch();
        
        if ($blog && $blog['image'] != 'blog-default.jpg') {
            $image_path = '../uploads/' . $blog['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
        if ($stmt->execute([$blog_id])) {
            $message = 'Blog deleted successfully!';
        } else {
            $error = 'Failed to delete blog.';
        }
    }
}

// Edit Blog
if (isset($_POST['edit_blog'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $blog_id = (int)$_POST['blog_id'];
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $status = sanitize($_POST['status'] ?? 'published');
        $media_type = sanitize($_POST['media_type'] ?? 'image');
        
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } else {
            // Check if a new file was uploaded
            $image = null;
            $media_type_current = $media_type;
            
            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0 && !empty($_FILES['media_file']['name'])) {
                $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowed_videos = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
                $filename = $_FILES['media_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                $upload_dir = dirname(__DIR__) . '/uploads/';
                
                if (in_array($ext, $allowed_images)) {
                    $media_type_current = 'image';
                    $new_filename = 'blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path) || copy($_FILES['media_file']['tmp_name'], $upload_path)) {
                        // Delete old image
                        $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
                        $stmt->execute([$blog_id]);
                        $old_blog = $stmt->fetch();
                        if ($old_blog && $old_blog['image'] != 'blog-default.jpg') {
                            $old_path = '../uploads/' . $old_blog['image'];
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }
                        $image = $new_filename;
                    }
                }
                elseif (in_array($ext, $allowed_videos)) {
                    $media_type_current = 'video';
                    $new_filename = 'blog_video_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path) || copy($_FILES['media_file']['tmp_name'], $upload_path)) {
                        // Delete old video
                        $stmt = $conn->prepare("SELECT image FROM blogs WHERE id = ?");
                        $stmt->execute([$blog_id]);
                        $old_blog = $stmt->fetch();
                        if ($old_blog && $old_blog['image'] != 'blog-default.jpg') {
                            $old_path = '../uploads/' . $old_blog['image'];
                            if (file_exists($old_path)) {
                                unlink($old_path);
                            }
                        }
                        $image = $new_filename;
                    }
                }
            }
            
            // Update blog
            if ($image !== null) {
                $stmt = $conn->prepare("UPDATE blogs SET title = ?, content = ?, image = ?, media_type = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $content, $image, $media_type_current, $status, $blog_id]);
            } else {
                $stmt = $conn->prepare("UPDATE blogs SET title = ?, content = ?, media_type = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $content, $media_type_current, $status, $blog_id]);
            }
            $message = 'Blog updated successfully!';
        }
    }
}

// Get blog for editing
$edit_blog = null;
if (isset($_GET['edit_blog'])) {
    $edit_id = (int)$_GET['edit_blog'];
    try {
        $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_blog = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $edit_blog = null;
    }
}

// Add Daily Quiz Questions (5 at once)
if (isset($_POST['add_daily_quiz'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $quiz_date = $_POST['quiz_date'] ?? '';
        $quiz_start = $_POST['quiz_start'] ?? '';
        
        // Convert datetime-local format to PostgreSQL TIMESTAMP format
        $quiz_start_datetime = '';
        if (!empty($quiz_start)) {
            $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $quiz_start);
            if ($dateTime !== false) {
                $quiz_start_datetime = $dateTime->format('Y-m-d H:i:s');
            }
        }
        
        $success_count = 0;
        $errors = [];
        
        // Process all 5 questions
        for ($i = 1; $i <= 5; $i++) {
            $question = sanitize($_POST['question_' . $i] ?? '');
            $option_a = sanitize($_POST['option_a_' . $i] ?? '');
            $option_b = sanitize($_POST['option_b_' . $i] ?? '');
            $option_c = sanitize($_POST['option_c_' . $i] ?? '');
            $option_d = sanitize($_POST['option_d_' . $i] ?? '');
            $correct_answer = sanitize($_POST['correct_answer_' . $i] ?? '');
            
            if (empty($question) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_answer)) {
                $errors[] = "Question $i: All fields are required.";
                continue;
            }
            
            // Check if quiz_date column exists
            $column_exists = columnExists($conn, 'quiz_questions', 'quiz_date');
            
            if ($column_exists) {
                $stmt = $conn->prepare("INSERT INTO quiz_questions (question, option_a, option_b, option_c, option_d, correct_answer, points, quiz_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$question, $option_a, $option_b, $option_c, $option_d, $correct_answer, 10, $quiz_date])) {
                    $success_count++;
                }
            } else {
                // Fallback for older schema
                $stmt = $conn->prepare("INSERT INTO quiz_questions (question, option_a, option_b, option_c, option_d, correct_answer, points) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$question, $option_a, $option_b, $option_c, $option_d, $correct_answer, 10])) {
                    $success_count++;
                }
            }
        }
        
        // Update quiz settings with the start time
        if (!empty($quiz_start_datetime)) {
            // Check if quiz_settings table exists
            try {
                if (tableExists($conn, 'quiz_settings')) {
                    $stmt = $conn->query("SELECT id FROM quiz_settings ORDER BY id DESC LIMIT 1");
                    $settings = $stmt->fetch();
                    
                    if ($settings && isset($settings['id'])) {
                        $stmt = $conn->prepare("UPDATE quiz_settings SET quiz_start = ? WHERE id = ?");
                        $stmt->execute([$quiz_start_datetime, $settings['id']]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO quiz_settings (quiz_start, quiz_duration_hours, is_active) VALUES (?, 24, TRUE)");
                        $stmt->execute([$quiz_start_datetime]);
                    }
                }
            } catch (Exception $e) {
                // Table doesn't exist, ignore
            }
        }
        
        if ($success_count == 5) {
            $message = 'All 5 quiz questions added successfully!';
        } elseif ($success_count > 0) {
            $message = "$success_count questions added successfully!";
            if (!empty($errors)) {
                $error = implode(' ', $errors);
            }
        } else {
            $error = 'Failed to add quiz questions. ' . implode(' ', $errors);
        }
    }
}

// Get existing quiz questions
$existing_quiz_questions = [];
try {
    $stmt = $conn->query("SELECT * FROM quiz_questions ORDER BY created_at DESC LIMIT 50");
    $existing_quiz_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existing_quiz_questions = [];
}

// Delete Quiz Question
if (isset($_POST['delete_quiz_question'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $question_id = (int)$_POST['question_id'];
        
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
        if ($stmt->execute([$question_id])) {
            $message = 'Quiz question deleted successfully!';
        } else {
            $error = 'Failed to delete quiz question.';
        }
    }
}

// Quiz Schedule Settings
$quiz_settings = null;
$has_quiz_settings = false;
try {
    $stmt = $conn->query("SELECT * FROM quiz_settings ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $quiz_settings = $result;
        $has_quiz_settings = true;
    }
} catch (Exception $e) {
    $has_quiz_settings = false;
}

if (isset($_POST['update_quiz_schedule'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $quiz_start = $_POST['quiz_start'] ?? '';
        $quiz_duration = (int)($_POST['quiz_duration'] ?? 24);
        $quiz_active = isset($_POST['quiz_active']) ? 1 : 0;
        
        // Handle empty start time (null means always available)
        $quiz_start_value = !empty($quiz_start) ? $quiz_start : null;
        
        if ($has_quiz_settings && isset($quiz_settings['id'])) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE quiz_settings SET quiz_start = ?, quiz_duration_hours = ?, is_active = ?::boolean WHERE id = ?");
            $stmt->execute([$quiz_start_value, $quiz_duration, $quiz_active, $quiz_settings['id']]);
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO quiz_settings (quiz_start, quiz_duration_hours, is_active) VALUES (?, ?, ?::boolean)");
            $stmt->execute([$quiz_start_value, $quiz_duration, $quiz_active]);
        }
        
        // Refresh settings
        $stmt = $conn->query("SELECT * FROM quiz_settings ORDER BY id DESC LIMIT 1");
        $quiz_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $has_quiz_settings = true;
        $message = 'Quiz schedule updated successfully!';
    }
}

// Set default values if no settings exist
if (!$quiz_settings) {
    $quiz_settings = [
        'quiz_start' => date('Y-m-d H:i:s'),
        'quiz_duration_hours' => 24,
        'is_active' => 1
    ];
}

// Upload Photo/Media
if (isset($_POST['upload_media'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $file_type = 'image';
    
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
            $filename = $_FILES['media_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = 'media_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = '../uploads/' . $new_filename;
                
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path)) {
                    $stmt = $conn->prepare("INSERT INTO media (title, file_name, file_type, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$title, $new_filename, $file_type, $new_filename, $_SESSION['user_id']])) {
                        $message = 'File uploaded successfully!';
                    } else {
                        $error = 'Failed to save file info.';
                    }
                } else {
                    $error = 'Failed to upload file.';
                }
            } else {
                $error = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    }
}

// Delete Media
if (isset($_POST['delete_media'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $media_id = (int)$_POST['media_id'];
        
        $stmt = $conn->prepare("SELECT file_name FROM media WHERE id = ?");
        $stmt->execute([$media_id]);
        $media = $stmt->fetch();
        
        if ($media) {
            $file_path = '../uploads/' . $media['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $stmt = $conn->prepare("DELETE FROM media WHERE id = ?");
            $stmt->execute([$media_id]);
            $message = 'File deleted successfully!';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .admin-header {
            background: #1e3c72;
            color: white;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .admin-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .admin-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 15px;
        }
        .admin-logo h1 {
            font-size: 20px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
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
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1001;
        }
        .mobile-menu-toggle:hover {
            background: rgba(255,255,255,0.2);
        }
        /* Mobile Navigation */
        .admin-mobile-nav {
            display: none;
            width: 100%;
            background: rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-top: 10px;
        }
        .admin-mobile-nav.show {
            display: block;
        }
        .admin-mobile-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .admin-mobile-nav a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .admin-mobile-nav a:hover,
        .admin-mobile-nav a.active {
            background: rgba(255,255,255,0.2);
            color: #FFD700;
        }
        .admin-mobile-nav a i {
            margin-right: 8px;
        }
        .admin-user-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* User Dropdown Styles */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .user-dropdown-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .user-dropdown-btn .user-name {
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
            top: 100%;
            margin-top: 5px;
        }
        .user-dropdown-content.show {
            display: block;
        }
        .user-dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            transition: background 0.2s;
        }
        .user-dropdown-content a:hover {
            background: #f5f5f5;
            color: #1e3c72;
        }
        .user-dropdown-content a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #eee;
            margin: 5px 0;
        }
        /* Desktop Navigation (Original) */
        .admin-nav {
            display: block;
        }
        /* Responsive Styles */
        @media (max-width: 991px) {
            .mobile-menu-toggle {
                display: block;
            }
            .admin-mobile-nav {
                display: none;
            }
            .admin-mobile-nav.show {
                display: block;
            }
            .admin-nav {
                display: none;
            }
            .admin-logo h1 {
                font-size: 18px;
            }
        }
        @media (max-width: 767px) {
            .admin-header {
                padding: 10px 0;
                padding-top: 50px;
            }
            .admin-header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            .admin-logo {
                width: 100%;
            }
            .admin-logo h1 {
                font-size: 16px;
            }
            .admin-user-section {
                position: absolute;
                top: 10px;
                right: 60px;
            }
            .mobile-menu-toggle {
                top: 10px;
                right: 10px;
                padding: 8px 12px;
            }
            .admin-mobile-nav {
                width: 100%;
            }
            .admin-mobile-nav ul {
                flex-direction: column;
            }
            .admin-mobile-nav a {
                padding: 12px 15px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            .user-dropdown-btn {
                padding: 8px 12px;
                font-size: 13px;
            }
            .user-dropdown-btn .user-name {
                display: none;
            }
            .user-dropdown-content {
                right: 0;
                min-width: 150px;
            }
        }
        /* Force responsive on all mobile devices */
        @media (max-width: 767px) {
            .admin-header {
                padding: 10px 0 !important;
            }
            .admin-header .container {
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                flex-wrap: wrap !important;
                gap: 5px !important;
            }
            .admin-header h1 {
                font-size: 16px !important;
                margin: 0 !important;
            }
            /* Hide old links on mobile, show dropdown */
            .admin-header .container > a {
                display: none !important;
            }
            .user-dropdown {
                width: 100%;
            }
            .user-dropdown-btn {
                width: 100%;
                justify-content: center;
                padding: 10px;
            }
            .user-dropdown-content {
                width: 100%;
                position: static;
                box-shadow: none;
                border-radius: 0;
                background: rgba(255,255,255,0.1);
            }
            .user-dropdown-content a {
                color: white;
                padding: 12px 20px;
            }
            .user-dropdown-content a:hover {
                background: rgba(255,255,255,0.2);
                color: #FFD700;
            }
            .dropdown-divider {
                background: rgba(255,255,255,0.2);
            }
        }
        @media (max-width: 480px) {
            .admin-header .container {
                flex-direction: column !important;
                text-align: center !important;
            }
            .admin-header h1 {
                font-size: 14px !important;
            }
            .admin-header a {
                font-size: 10px !important;
            }
        }
        .admin-header h1 {
            font-size: 24px;
        }
        .admin-header a {
            color: white;
            text-decoration: none;
        }
        .admin-header a:hover {
            color: #FFD700;
        }
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .user-dropdown-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .user-dropdown-btn .user-name {
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
            top: 100%;
            margin-top: 5px;
        }
        .user-dropdown-content.show {
            display: block;
        }
        .user-dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            transition: background 0.2s;
        }
        .user-dropdown-content a:hover {
            background: #f5f5f5;
            color: #1e3c72;
        }
        .user-dropdown-content a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .dropdown-divider {
            height: 1px;
            background: #eee;
            margin: 5px 0;
        }
        @media (max-width: 767px) {
            .user-dropdown {
                width: 100%;
            }
            .user-dropdown-btn {
                width: 100%;
                justify-content: center;
                padding: 10px;
            }
            .user-dropdown-content {
                width: 100%;
                position: static;
                box-shadow: none;
                border-radius: 0;
                background: rgba(255,255,255,0.1);
            }
            .user-dropdown-content a {
                color: white;
                padding: 12px 20px;
            }
            .user-dropdown-content a:hover {
                background: rgba(255,255,255,0.2);
                color: #FFD700;
            }
            .dropdown-divider {
                background: rgba(255,255,255,0.2);
            }
        }
        .admin-nav {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .admin-nav ul {
            display: flex;
            list-style: none;
            gap: 0;
            flex-wrap: nowrap;
            min-width: max-content;
        }
        .admin-nav a {
            display: block;
            padding: 15px 25px;
            color: #333;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: #f5f5f5;
            border-bottom-color: #1e3c72;
            color: #1e3c72;
        }
        .admin-container {
            padding: 40px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        /* Force grid to single column on mobile */
        @media (max-width: 767px) {
            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
                margin-bottom: 20px !important;
            }
            .admin-grid {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
            .stat-card {
                padding: 15px !important;
            }
            .stat-card i {
                font-size: 24px !important;
            }
            .stat-card h3 {
                font-size: 20px !important;
            }
            .stat-card p {
                font-size: 12px !important;
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                gap: 8px !important;
            }
            .stat-card {
                padding: 12px !important;
            }
            .stat-card i {
                font-size: 20px !important;
            }
            .stat-card h3 {
                font-size: 18px !important;
            }
        }
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
        }
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        .admin-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        /* Force admin-card to be responsive */
        @media (max-width: 767px) {
            .admin-card {
                padding: 15px !important;
                border-radius: 8px !important;
                margin-bottom: 15px !important;
            }
            .admin-card h2 {
                font-size: 16px !important;
                margin-bottom: 10px !important;
                padding-bottom: 10px !important;
            }
            .admin-card h3 {
                font-size: 14px !important;
                margin-top: 15px !important;
                margin-bottom: 10px !important;
            }
        }
        .admin-card h2 {
            color: #1e3c72;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn-admin {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(30, 60, 114, 0.3);
        }
        /* Force buttons to be full width on mobile */
        @media (max-width: 767px) {
            .btn-admin {
                width: 100% !important;
                padding: 12px 15px !important;
                font-size: 14px !important;
                margin-bottom: 10px !important;
            }
            .form-group {
                margin-bottom: 15px !important;
            }
            .form-group label {
                font-size: 13px !important;
                margin-bottom: 5px !important;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 10px 12px !important;
                font-size: 14px !important;
                width: 100% !important;
            }
            .form-row,
            .form-row-4 {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        @media (max-width: 576px) {
            .container {
                padding: 0 15px;
            }
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-row-single {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        .form-row-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 15px;
        }
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        /* Force media grid to single column on mobile */
        @media (max-width: 767px) {
            .media-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }
            .media-item {
                padding: 8px !important;
            }
            .media-item img {
                height: 60px !important;
            }
            .media-item-icon {
                height: 60px !important;
            }
            .media-item-icon i {
                font-size: 24px !important;
            }
            .media-item p {
                font-size: 10px !important;
                margin: 5px 0 3px !important;
            }
        }
        @media (max-width: 480px) {
            .media-grid {
                grid-template-columns: 1fr !important;
            }
        }
        .media-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .media-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
        .media-item-icon {
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            border-radius: 5px;
        }
        .blog-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        /* Tablet Landscape & Desktop */
        @media (max-width: 1200px) {
            .admin-grid {
                grid-template-columns: 1fr 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .container {
                padding: 0 15px;
            }
        }
        
        /* Tablet Portrait */
        @media (max-width: 992px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .form-row-4 {
                grid-template-columns: 1fr 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .admin-container {
                padding: 30px 0;
            }
        }
        
        /* Mobile Landscape & Large Phones */
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .stat-card {
                padding: 20px;
            }
            .stat-card i {
                font-size: 28px;
            }
            .stat-card h3 {
                font-size: 24px;
            }
            .stat-card p {
                font-size: 13px;
            }
            .admin-header {
                padding: 12px 0;
            }
            .admin-header h1 {
                font-size: 18px;
            }
            .admin-header .container {
                flex-direction: row;
                justify-content: space-between;
                gap: 10px;
            }
            .admin-header a {
                font-size: 13px;
            }
            .admin-nav {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .admin-nav ul {
                flex-wrap: nowrap;
                justify-content: flex-start;
            }
            .admin-nav a {
                padding: 12px 20px;
                font-size: 14px;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                width: 100%;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .form-row-4 {
                grid-template-columns: 1fr;
            }
            .media-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .admin-card {
                padding: 20px;
            }
            .admin-card h2 {
                font-size: 18px;
            }
            .btn-admin {
                width: 100%;
                padding: 14px 25px;
            }
            /* Executive list items */
            .exec-item {
                flex-direction: column;
                text-align: center;
            }
            .exec-item > div:first-child {
                margin: 0 auto;
            }
            /* Event items */
            .event-item {
                flex-direction: column;
            }
            .event-image {
                width: 100%;
                height: 200px;
            }
            /* Donation items */
            .donation-item {
                flex-direction: column;
                text-align: center;
            }
            .donation-item > div:last-child {
                text-align: center !important;
            }
            /* Blog items */
            .blog-item {
                flex-direction: column;
                text-align: center;
            }
            .filter-buttons {
                flex-direction: column;
                gap: 8px !important;
            }
            .filter-btn {
                font-size: 13px !important;
                padding: 6px 15px !important;
                width: 100%;
            }
            .media-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* Mobile Portrait & Small Phones */
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .stat-card {
                padding: 15px;
            }
            .stat-card i {
                font-size: 24px;
                margin-bottom: 10px;
            }
            .stat-card h3 {
                font-size: 20px;
            }
            .stat-card p {
                font-size: 12px;
            }
            .admin-header h1 {
                font-size: 16px;
            }
            .admin-header h1 i {
                display: none; /* Hide icon on very small screens */
            }
            .admin-header a {
                font-size: 12px;
                padding: 5px 10px;
            }
            .admin-nav a {
                padding: 10px 15px;
                font-size: 13px;
            }
            .admin-card {
                padding: 15px;
                border-radius: 8px;
            }
            .admin-card h2 {
                font-size: 16px;
                margin-bottom: 15px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                font-size: 14px;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                padding: 10px 12px;
                font-size: 16px;
            }
            .btn-admin {
                width: 100%;
                padding: 12px 20px;
                font-size: 14px;
            }
            .form-row-4 {
                grid-template-columns: 1fr;
            }
            .media-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .container {
                padding: 0 10px;
            }
            .admin-container {
                padding: 20px 0;
            }
            .alert {
                padding: 12px;
                font-size: 14px;
            }
            .filter-btn {
                font-size: 12px !important;
                padding: 5px 12px !important;
            }
            #execSearch {
                width: 100% !important;
                min-width: 100% !important;
                font-size: 14px !important;
            }
        }
        
        /* Extra Small Devices */
        @media (max-width: 360px) {
            .admin-header .container {
                flex-direction: column;
                text-align: center;
            }
            .admin-header h1 {
                font-size: 14px;
            }
            .stat-card {
                padding: 12px;
            }
            .stat-card h3 {
                font-size: 18px;
            }
            .admin-card {
                padding: 12px;
            }
        }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="admin-header">
        <div class="container">
            <div class="admin-header-content">
                <!-- Logo/Title -->
                <div class="admin-logo">
                    <h1><i class="fas fa-cog"></i> Admin</h1>
                </div>
                
                <!-- Mobile Navigation -->
                <nav class="admin-mobile-nav" id="mobileNav">
                    <ul>
                        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="#executives"><i class="fas fa-user-tie"></i> Executives</a></li>
                        <li><a href="#events"><i class="fas fa-calendar"></i> Events</a></li>
                        <li><a href="#blogs"><i class="fas fa-blog"></i> Blogs</a></li>
                        <li><a href="#quiz"><i class="fas fa-question-circle"></i> Quiz</a></li>
                        <li><a href="#media"><i class="fas fa-images"></i> Media</a></li>
                    </ul>
                </nav>
                
                <!-- User Actions -->
                <div class="admin-user-section">
                    <!-- User Dropdown -->
                    <div class="user-dropdown">
                        <button class="user-dropdown-btn" onclick="toggleUserDropdown()">
                            <i class="fas fa-user-circle"></i> <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                        </button>
                        <div class="user-dropdown-content" id="userDropdown">
                            <a href="../profile.php"><i class="fas fa-user"></i> Profile</a>
                            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            <div class="dropdown-divider"></div>
                            <a href="../index.php"><i class="fas fa-home"></i> View Site</a>
                            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $members_count['count']; ?></h3>
                    <p>Members</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-calendar-event"></i>
                    <h3><?php echo $events_count['count']; ?></h3>
                    <p>Events</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-blog"></i>
                    <h3><?php echo $blogs_count['count']; ?></h3>
                    <p>Blogs</p>
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
                <div class="stat-card" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="fas fa-clipboard-check"></i>
                    <h3><?php echo $quiz_taken; ?></h3>
                    <p>Quiz Taken</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #eb3349, #f45c43);">
                    <i class="fas fa-clipboard-times"></i>
                    <h3><?php echo $quiz_not_taken; ?></h3>
                    <p>Quiz Not Taken</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $quiz_passed; ?></h3>
                    <p>Quiz Passed</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo $quiz_failed; ?></h3>
                    <p>Quiz Failed</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                    <i class="fas fa-donate"></i>
                    <h3><?php echo $donations_count; ?></h3>
                    <p>Donations (<?php echo number_format($donations_total, 2); ?> GHS)</p>
                </div>
            </div>
            
            <div class="admin-grid">
                <div class="admin-card" id="executives">
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
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" placeholder="Short biography"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Order Number</label>
                            <input type="number" name="order_num" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="email@example.com">
                        </div>
                        <div class="form-group">
                            <label>Phone (Call)</label>
                            <input type="tel" name="phone" placeholder="+233xxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp</label>
                            <input type="tel" name="whatsapp" placeholder="+233xxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Photo</label>
                            <input type="file" name="image" accept="image/*">
                            <small style="color: #666;">Supported: jpg, jpeg, png, gif, webp</small>
                        </div>
                        <button type="submit" name="add_executive" class="btn-admin">Add Executive</button>
                    </form>
                    
                    <?php if (!empty($existing_executives)): ?>
                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #1e3c72;">
                            <i class="fas fa-list"></i> Existing Executives (<?php echo count($existing_executives); ?>)
                        </h3>
                        
                        <!-- Filter & Search -->
                        <div class="filter-buttons" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                            <button type="button" class="filter-btn active" data-filter="all" style="padding: 8px 20px; border: 2px solid #1e3c72; background: transparent; color: #1e3c72; border-radius: 20px; cursor: pointer; font-weight: 500;">All</button>
                            <button type="button" class="filter-btn" data-filter="national" style="padding: 8px 20px; border: 2px solid #1e3c72; background: transparent; color: #1e3c72; border-radius: 20px; cursor: pointer; font-weight: 500;">National</button>
                            <button type="button" class="filter-btn" data-filter="ttu" style="padding: 8px 20px; border: 2px solid #1e3c72; background: transparent; color: #1e3c72; border-radius: 20px; cursor: pointer; font-weight: 500;">TTU Branch</button>
                            <input type="text" id="execSearch" placeholder="Search executives..." style="padding: 8px 15px; border: 2px solid #eee; border-radius: 20px; flex: 1; min-width: 200px;">
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;" id="executivesList">
                            <?php foreach ($existing_executives as $exec): ?>
                                <div class="exec-item" style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 60px; height: 60px; background: #e9ecef; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if ($exec['image'] && $exec['image'] != 'default.png'): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($exec['image']); ?>" alt="<?php echo htmlspecialchars($exec['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-user" style="font-size: 24px; color: #1e3c72;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($exec['name']); ?></div>
                                        <div style="font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($exec['position']); ?>
                                            <?php if (!empty($exec['order_num'])): ?>
                                                | Order: <?php echo $exec['order_num']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this executive?');" style="display: inline; flex-shrink: 0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="exec_id" value="<?php echo $exec['id']; ?>">
                                        <button type="submit" name="delete_executive" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Donations Section -->
                <div class="admin-card" id="donations">
                    <h2><i class="fas fa-donate"></i> Recent Donations</h2>
                    <?php if (!empty($recent_donations)): ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($recent_donations as $donation): ?>
                                <div class="donation-item" style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #1e3c72; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($donation['donor_name']); ?></div>
                                        <div style="font-size: 12px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($donation['donor_email']); ?> | <?php echo htmlspecialchars($donation['donor_phone']); ?></div>
                                        <?php if ($donation['message']): ?>
                                            <div style="font-size: 12px; color: #888; margin-top: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">"<?php echo htmlspecialchars($donation['message']); ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0;">
                                        <div style="font-size: 18px; font-weight: bold; color: #11998e;">GHS <?php echo number_format($donation['amount'], 2); ?></div>
                                        <div style="font-size: 11px; color: #666;"><?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?></div>
                                        <span style="background: <?php echo $donation['payment_status'] == 'success' ? '#11998e' : '#eb3349'; ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px;">
                                            <?php echo ucfirst($donation['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 15px; text-align: center; padding: 15px; background: linear-gradient(135deg, #fa709a, #fee140); border-radius: 8px; color: white;">
                            <strong>Total Donations: </strong>GHS <?php echo number_format($donations_total, 2); ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center; padding: 30px;">No donations yet.</p>
                    <?php endif; ?>
                </div>
                
                <div class="admin-card" id="events">
                    <h2><i class="fas fa-calendar-plus"></i> Add New Event</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Event Title <span style="color: red;">*</span></label>
                            <input type="text" name="title" required placeholder="Enter event title">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="4" placeholder="Event description"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date & Time <span style="color: red;">*</span></label>
                                <input type="datetime-local" name="event_date">
                            </div>
                            <div class="form-group">
                                <label>End Date & Time</label>
                                <input type="datetime-local" name="event_end_date">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" placeholder="Event location (e.g., Church Hall)">
                        </div>
                        <div class="form-group">
                            <label>Registration Link (optional)</label>
                            <input type="url" name="registration_link" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Event Image</label>
                            <input type="file" name="image" accept="image/*">
                            <small style="color: #666;">Supported: jpg, jpeg, png, gif, webp</small>
                        </div>
                        <button type="submit" name="add_event" class="btn-admin">
                            <i class="fas fa-plus-circle"></i> Create Event
                        </button>
                    </form>
                    
                    <?php if (!empty($existing_events)): ?>
                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #1e3c72;">
                            <i class="fas fa-list"></i> Existing Events (<?php echo count($existing_events); ?>)
                        </h3>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($existing_events as $event): ?>
                                <div class="event-item" style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                                    <div class="event-image" style="width: 60px; height: 60px; background: #e9ecef; border-radius: 5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fas fa-calendar" style="font-size: 24px; color: #1e3c72;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div style="font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                                            <?php if (!empty($event['location'])): ?>
                                                | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this event?');" style="display: inline; flex-shrink: 0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" name="delete_event" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="admin-card" id="blogs">
                    <?php if ($edit_blog): ?>
                    <h2><i class="fas fa-edit"></i> Edit Blog Post</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="blog_id" value="<?php echo $edit_blog['id']; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Blog title" value="<?php echo htmlspecialchars($edit_blog['title']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" rows="5" placeholder="Blog content"><?php echo htmlspecialchars($edit_blog['content']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Media Type</label>
                            <select name="media_type" id="edit_media_type" onchange="toggleEditMediaUpload()">
                                <option value="image" <?php echo ($edit_blog['media_type'] ?? 'image') === 'image' ? 'selected' : ''; ?>>Image</option>
                                <option value="video" <?php echo ($edit_blog['media_type'] ?? 'image') === 'video' ? 'selected' : ''; ?>>Video</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="edit_media_label">Blog Media (leave empty to keep current)</label>
                            <input type="file" name="media_file" id="edit_media_file_input" accept="image/*">
                            <small id="edit_media_hint" style="color: #666;">Supported: jpg, jpeg, png, gif, webp, mp4, webm, mov, avi, mkv</small>
                            <?php if ($edit_blog['image'] && $edit_blog['image'] != 'blog-default.jpg'): ?>
                                <p style="margin-top: 10px; color: #666;">Current: <?php echo htmlspecialchars($edit_blog['image']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="published" <?php echo $edit_blog['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="draft" <?php echo $edit_blog['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            </select>
                        </div>
                        <button type="submit" name="edit_blog" class="btn-admin">Update Blog</button>
                        <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px; padding: 10px 20px; background: #6c757d; color: white; border-radius: 5px; text-decoration: none;">Cancel</a>
                    </form>
                    <script>
                        function toggleEditMediaUpload() {
                            var mediaType = document.getElementById('edit_media_type').value;
                            var fileInput = document.getElementById('edit_media_file_input');
                            var label = document.getElementById('edit_media_label');
                            var hint = document.getElementById('edit_media_hint');
                            
                            if (mediaType === 'video') {
                                fileInput.accept = 'video/*';
                                label.textContent = 'Blog Video (leave empty to keep current)';
                                hint.textContent = 'Supported: mp4, webm, mov, avi, mkv';
                            } else {
                                fileInput.accept = 'image/*';
                                label.textContent = 'Blog Image (leave empty to keep current)';
                                hint.textContent = 'Supported: jpg, jpeg, png, gif, webp';
                            }
                        }
                    </script>
                    <?php else: ?>
                    <h2><i class="fas fa-pen-square"></i> Add Blog Post</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required placeholder="Blog title">
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" rows="5" placeholder="Blog content"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Media Type</label>
                            <select name="media_type" id="media_type" onchange="toggleMediaUpload()">
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="media_label">Blog Image</label>
                            <input type="file" name="media_file" id="media_file_input" accept="image/*">
                            <small id="media_hint" style="color: #666;">Supported: jpg, jpeg, png, gif, webp</small>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <button type="submit" name="add_blog" class="btn-admin">Add Blog</button>
                    </form>
                    <script>
                        function toggleMediaUpload() {
                            var mediaType = document.getElementById('media_type').value;
                            var fileInput = document.getElementById('media_file_input');
                            var label = document.getElementById('media_label');
                            var hint = document.getElementById('media_hint');
                            
                            if (mediaType === 'video') {
                                fileInput.accept = 'video/*';
                                label.textContent = 'Blog Video';
                                hint.textContent = 'Supported: mp4, webm, mov, avi, mkv';
                            } else {
                                fileInput.accept = 'image/*';
                                label.textContent = 'Blog Image';
                                hint.textContent = 'Supported: jpg, jpeg, png, gif, webp';
                            }
                        }
                    </script>
                    <?php endif; ?>
                    
                    <?php if (!empty($existing_blogs)): ?>
                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #1e3c72;">
                            <i class="fas fa-list"></i> Existing Blogs (<?php echo count($existing_blogs); ?>)
                        </h3>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($existing_blogs as $blog): ?>
                                <div class="blog-item" style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                                    <div style="width: 60px; height: 60px; background: #e9ecef; border-radius: 5px; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <?php if ($blog['image'] && $blog['image'] != 'blog-default.jpg'): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($blog['image']); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-blog" style="font-size: 24px; color: #1e3c72;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($blog['title']); ?></div>
                                        <div style="font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($blog['username'] ?? 'Unknown'); ?>
                                            | <i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($blog['created_at'])); ?>
                                            | <span style="color: <?php echo $blog['status'] == 'published' ? '#28a745' : '#ffc107'; ?>;">
                                                <?php echo ucfirst($blog['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="?edit_blog=<?php echo $blog['id']; ?>" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; display: inline-block; text-decoration: none; flex-shrink: 0;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Delete this blog post?');" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
                                        <button type="submit" name="delete_blog" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="admin-card" id="quiz">
                    <h2><i class="fas fa-question-circle"></i> Add Daily Quiz Questions</h2>
                    <p style="margin-bottom: 15px; color: #666;">Add all 5 questions for a daily quiz at once. Set the quiz date and start time.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Quiz Date <span style="color: red;">*</span></label>
                            <input type="date" name="quiz_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Quiz Start Time <span style="color: red;">*</span></label>
                            <input type="datetime-local" name="quiz_start" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        
                        <h3 style="margin: 20px 0 15px; color: #1e3c72;">Question 1</h3>
                        <div class="form-group">
                            <label>Question</label>
                            <textarea name="question_1" placeholder="Enter question 1" required></textarea>
                        </div>
                        <div class="form-row-4">
                            <div class="form-group">
                                <label>Option A</label>
                                <input type="text" name="option_a_1" required placeholder="Option A">
                            </div>
                            <div class="form-group">
                                <label>Option B</label>
                                <input type="text" name="option_b_1" required placeholder="Option B">
                            </div>
                            <div class="form-group">
                                <label>Option C</label>
                                <input type="text" name="option_c_1" required placeholder="Option C">
                            </div>
                            <div class="form-group">
                                <label>Option D</label>
                                <input type="text" name="option_d_1" required placeholder="Option D">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer_1">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </div>
                        
                        <h3 style="margin: 20px 0 15px; color: #1e3c72;">Question 2</h3>
                        <div class="form-group">
                            <label>Question</label>
                            <textarea name="question_2" placeholder="Enter question 2" required></textarea>
                        </div>
                        <div class="form-row-4">
                            <div class="form-group">
                                <label>Option A</label>
                                <input type="text" name="option_a_2" required placeholder="Option A">
                            </div>
                            <div class="form-group">
                                <label>Option B</label>
                                <input type="text" name="option_b_2" required placeholder="Option B">
                            </div>
                            <div class="form-group">
                                <label>Option C</label>
                                <input type="text" name="option_c_2" required placeholder="Option C">
                            </div>
                            <div class="form-group">
                                <label>Option D</label>
                                <input type="text" name="option_d_2" required placeholder="Option D">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer_2">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </div>
                        
                        <h3 style="margin: 20px 0 15px; color: #1e3c72;">Question 3</h3>
                        <div class="form-group">
                            <label>Question</label>
                            <textarea name="question_3" placeholder="Enter question 3" required></textarea>
                        </div>
                        <div class="form-row-4">
                            <div class="form-group">
                                <label>Option A</label>
                                <input type="text" name="option_a_3" required placeholder="Option A">
                            </div>
                            <div class="form-group">
                                <label>Option B</label>
                                <input type="text" name="option_b_3" required placeholder="Option B">
                            </div>
                            <div class="form-group">
                                <label>Option C</label>
                                <input type="text" name="option_c_3" required placeholder="Option C">
                            </div>
                            <div class="form-group">
                                <label>Option D</label>
                                <input type="text" name="option_d_3" required placeholder="Option D">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer_3">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </div>
                        
                        <h3 style="margin: 20px 0 15px; color: #1e3c72;">Question 4</h3>
                        <div class="form-group">
                            <label>Question</label>
                            <textarea name="question_4" placeholder="Enter question 4" required></textarea>
                        </div>
                        <div class="form-row-4">
                            <div class="form-group">
                                <label>Option A</label>
                                <input type="text" name="option_a_4" required placeholder="Option A">
                            </div>
                            <div class="form-group">
                                <label>Option B</label>
                                <input type="text" name="option_b_4" required placeholder="Option B">
                            </div>
                            <div class="form-group">
                                <label>Option C</label>
                                <input type="text" name="option_c_4" required placeholder="Option C">
                            </div>
                            <div class="form-group">
                                <label>Option D</label>
                                <input type="text" name="option_d_4" required placeholder="Option D">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer_4">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </div>
                        
                        <h3 style="margin: 20px 0 15px; color: #1e3c72;">Question 5</h3>
                        <div class="form-group">
                            <label>Question</label>
                            <textarea name="question_5" placeholder="Enter question 5" required></textarea>
                        </div>
                        <div class="form-row-4">
                            <div class="form-group">
                                <label>Option A</label>
                                <input type="text" name="option_a_5" required placeholder="Option A">
                            </div>
                            <div class="form-group">
                                <label>Option B</label>
                                <input type="text" name="option_b_5" required placeholder="Option B">
                            </div>
                            <div class="form-group">
                                <label>Option C</label>
                                <input type="text" name="option_c_5" required placeholder="Option C">
                            </div>
                            <div class="form-group">
                                <label>Option D</label>
                                <input type="text" name="option_d_5" required placeholder="Option D">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer_5">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_daily_quiz" class="btn-admin">Add All 5 Questions</button>
                    </form>
                    
                    <?php if (!empty($existing_quiz_questions)): ?>
                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #1e3c72;">
                            <i class="fas fa-list"></i> Existing Quiz Questions (<?php echo count($existing_quiz_questions); ?>)
                        </h3>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($existing_quiz_questions as $question): ?>
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: flex-start; gap: 15px;">
                                    <div style="width: 40px; height: 40px; background: #1e3c72; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                                        Q
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #333; margin-bottom: 5px;"><?php echo htmlspecialchars($question['question']); ?></div>
                                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                                            <strong>Correct:</strong> <?php echo strtoupper($question['correct_answer']); ?>
                                            | <strong>Points:</strong> <?php echo $question['points']; ?>
                                            <?php if (!empty($question['quiz_date'])): ?>
                                                | <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($question['quiz_date'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 11px; color: #999;">
                                            Created: <?php echo date('M j, Y g:i A', strtotime($question['created_at'])); ?>
                                        </div>
                                    </div>
                                    <form method="POST" onsubmit="return confirm('Delete this quiz question?');" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" name="delete_quiz_question" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="admin-card" id="quiz-schedule">
                    <h2><i class="fas fa-clock"></i> Quiz Schedule</h2>
                    <p style="margin-bottom: 15px; color: #666;">Set when the daily quiz will be available. After the duration expires, users will see a message to come back next time.</p>
                    <?php
                    // Calculate quiz end time
                    $quiz_start = new DateTime($quiz_settings['quiz_start'] ?? date('Y-m-d H:i:s'));
                    $duration = $quiz_settings['quiz_duration_hours'] ?? 24;
                    $quiz_end = clone $quiz_start;
                    $quiz_end->modify("+$duration hours");
                    $now = new DateTime();
                    $is_quiz_available = is_array($quiz_settings) && ($quiz_settings['is_active'] ?? 1) && $now < $quiz_end;
                    ?>
                    <div style="background: <?php echo $is_quiz_available ? '#d4edda' : '#f8d7da'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <strong>Status:</strong> <?php echo $is_quiz_available ? '✅ Quiz is LIVE' : '⏰ Quiz ended'; ?><br>
                        <strong>Started:</strong> <?php echo $quiz_start->format('M j, Y g:i A'); ?><br>
                        <strong>Ends:</strong> <?php echo $quiz_end->format('M j, Y g:i A'); ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Quiz Start Time (leave empty for always available)</label>
                            <input type="datetime-local" name="quiz_start" value="<?php echo date('Y-m-d\TH:i', strtotime($quiz_settings['quiz_start'] ?? 'now')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Duration (hours)</label>
                            <input type="number" name="quiz_duration" value="<?php echo $quiz_settings['quiz_duration_hours'] ?? 24; ?>" min="1" max="168">
                        </div>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="quiz_active" <?php echo ($quiz_settings['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                Enable Quiz
                            </label>
                        </div>
                        <button type="submit" name="update_quiz_schedule" class="btn-admin">Update Schedule</button>
                    </form>
                </div>
                
                <?php
                // Get uploaded media files
                $media_files = [];
                try {
                    $stmt = $conn->query("SELECT * FROM media ORDER BY created_at DESC");
                    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $media_files = [];
                }
                ?>
                
                <div class="admin-card" id="media">
                    <h2><i class="fas fa-images"></i> Upload Photos & Files</h2>
                    <p style="margin-bottom: 15px; color: #666;">Upload photos and documents that users can view and download.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Title (optional)</label>
                            <input type="text" name="title" placeholder="Enter file title">
                        </div>
                        <div class="form-group">
                            <label>Select File</label>
                            <input type="file" name="media_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx">
                            <small style="color: #666;">Allowed: jpg, png, gif, webp, pdf, doc</small>
                        </div>
                        <button type="submit" name="upload_media" class="btn-admin">
                            <i class="fas fa-upload"></i> Upload File
                        </button>
                    </form>
                    
                    <?php if (!empty($media_files)): ?>
                        <h3 style="margin-top: 30px; margin-bottom: 15px;">Uploaded Files</h3>
                        <div class="media-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                            <?php foreach ($media_files as $media): ?>
                                <div class="media-item" style="background: #f8f9fa; border-radius: 8px; padding: 10px; text-align: center;">
                                    <?php $ext = pathinfo($media['file_name'], PATHINFO_EXTENSION); ?>
                                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($media['file_name']); ?>" alt="<?php echo htmlspecialchars($media['title']); ?>" style="width: 100%; height: 100px; object-fit: cover; border-radius: 5px;">
                                    <?php else: ?>
                                        <div class="media-item-icon" style="height: 100px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 5px;">
                                            <i class="fas fa-file-<?php echo $ext == 'pdf' ? 'pdf' : 'alt'; ?>" style="font-size: 40px; color: #6c757d;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <p style="margin: 10px 0 5px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($media['title'] ?: $media['file_name']); ?></p>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this file?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="media_id" value="<?php echo $media['id']; ?>">
                                        <button type="submit" name="delete_media" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin-top: 20px; color: #666; text-align: center;">No files uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Toggle Mobile Menu
    function toggleMobileMenu() {
        var nav = document.getElementById('mobileNav');
        nav.classList.toggle('show');
    }
    
    // Toggle User Dropdown
    function toggleUserDropdown() {
        var dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }
    
    // Close dropdown when clicking outside
    window.onclick = function(event) {
        if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown')) {
            var dropdowns = document.getElementsByClassName('user-dropdown-content');
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
    
    // Executive filtering and search
    document.addEventListener('DOMContentLoaded', function() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const searchInput = document.getElementById('execSearch');
        const execItems = document.querySelectorAll('[style*="background: #f8f9fa"][style*="border-radius: 8px"]');
        
        if (filterBtns.length > 0 && execItems.length > 0) {
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => {
                        b.style.background = 'transparent';
                        b.style.color = '#1e3c72';
                    });
                    this.style.background = '#1e3c72';
                    this.style.color = 'white';
                });
            });
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    execItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(term) ? 'flex' : 'none';
                    });
                });
            }
        }
    });
    </script>
</body>
</html>
