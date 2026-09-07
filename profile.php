<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';
require_once 'mailer.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$page_title = 'My Profile';
$page_description = 'Manage your NUPS-G account information and see your quiz history.';
$user_id = (int)$_SESSION['user_id'];

$message = '';
$error = '';
$pending_email_verification = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email is changing
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_email = $stmt->fetchColumn();

        if ($email !== $current_email) {
            // Email change requires current password verification
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $stored_hash = $stmt->fetchColumn();

            if (empty($current_password) || !password_verify($current_password, $stored_hash)) {
                $error = 'Current password is required to change your email address.';
            } else {
                // Create email verification token
                $token = create_email_verification($conn, $user_id, $email);
                if ($token) {
                    // Store verification link in session for display
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['email_verification_token'] = $token;
                    $pending_email_verification = true;

                    // Send verification email
                    $verify_link = SITE_URL . '/verify_email.php?token=' . urlencode($token);
                    $mail_subject = 'Verify your new email address';
                    $mail_body = '<p>Hello,</p>' .
                        '<p>You requested to change your email address for your ' . SITE_NAME . ' account.</p>' .
                        '<p>Please click the link below to verify your new email address:</p>' .
                        '<p><a href="' . $verify_link . '">Verify Email Address</a></p>' .
                        '<p>This link will expire in 24 hours.</p>' .
                        '<p>If you did not request this change, please ignore this email.</p>';
                    $mail_result = send_mail($email, $mail_subject, $mail_body);

                    if ($mail_result['sent']) {
                        error_log('Email verification sent to ' . $email);
                    } else {
                        error_log('Email verification failed for ' . $email . ': ' . ($mail_result['error'] ?? 'unknown'));
                    }

                    $message = 'A verification email has been sent to ' . htmlspecialchars($email) . '. Please check your inbox and click the link to confirm the change. Your old email remains active until verified.';
                } else {
                    $error = 'Failed to create email verification. Please try again.';
                }
            }
        } else {
            // No email change, proceed with other updates
            if (!empty($new_password)) {
                if (!is_strong_password($new_password)) {
                    $error = 'New password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New password and confirmation do not match.';
                } else {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                    $ok = $stmt->execute([$first_name, $last_name, $email, $phone, $hash, $user_id]);
                    if ($ok) {
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        $_SESSION['email'] = $email;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_phone'] = $phone;
                        $message = 'Profile and password updated successfully!';
                    } else {
                        $error = 'Failed to update profile. Please try again.';
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                $ok = $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
                if ($ok) {
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_phone'] = $phone;
                    $message = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    }
}

// Handle member review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)($_POST['rating'] ?? 5);
    $content = sanitize($_POST['review_content'] ?? '');
    if ($rating < 1 || $rating > 5) { $rating = 5; }
    if (mb_strlen($content) < 10) {
        $error = 'Please write a short review (at least 10 characters).';
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO reviews (user_id, rating, content, status)
                 VALUES (?, ?, ?, 'pending')
                 ON CONFLICT (user_id, content) DO NOTHING"
            );
            $stmt->execute([$user_id, $rating, $content]);
            if ($stmt->rowCount() > 0) {
                $message = 'Thank you! Your review has been submitted and will go live once approved by an admin.';
            } else {
                $error = 'You have already submitted that exact review.';
            }
        } catch (Exception $e) {
            $error = 'Could not submit your review right now. Please try again later.';
        }
    }
}

// Member's own reviews (to show status)
$my_reviews = [];
try {
    $stmt = $conn->prepare("SELECT id, rating, content, status, created_at FROM reviews WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $my_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $my_reviews = [];
}

// Always read the freshest row from the database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    redirect('logout.php');
}
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_phone'] = $user['phone'] ?? '';

// Quiz history
$quiz_history = [];
try {
    $stmt = $conn->prepare("SELECT score, total_questions, created_at FROM quiz_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $quiz_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $quiz_history = [];
}

$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$member_since = date('F Y', strtotime($user['created_at']));

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-user-circle"></i> My Account</span>
            <h1>My Profile</h1>
            <div class="divider"></div>
            <p>Manage your details, view your quiz history and grow your points</p>
        </div>
    </section>

    <style>
        .profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: clamp(1.5rem, 3.5vw, 2.5rem); align-items: start; }
        .profile-card { text-align: center; padding-block: 2.4rem; }
        .profile-card .avatar-big { width: 110px; height: 110px; margin: 0 auto 1.1rem; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; display: grid; place-items: center; font-family: var(--font-display); font-size: 2.6rem; font-weight: 700; box-shadow: var(--shadow-md); border: 4px solid #fff; }
        .profile-card h2 { font-family: var(--font-body); font-size: 1.4rem; }
        .profile-card .sub { color: var(--muted); font-size: .92rem; margin-bottom: .8rem; }
        .role-badge { display: inline-block; background: var(--gold-soft); color: var(--gold-deep); border: 1px solid var(--gold); font-weight: 800; font-size: var(--fs-xs); letter-spacing: .06em; text-transform: uppercase; padding: .35rem 1rem; border-radius: var(--r-pill); }
        .mini-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin-top: 1.4rem; }
        .mini-stat { background: var(--bg-soft); border: 1px solid var(--line); border-radius: var(--r); padding: .9rem; }
        .mini-stat b { display: block; font-family: var(--font-display); font-size: 1.5rem; color: var(--primary); line-height: 1.1; }
        .mini-stat small { color: var(--muted); font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .result-row { display: flex; align-items: center; gap: 1rem; padding: .9rem 0; border-bottom: 1px dashed var(--line); }
        .result-row:last-child { border-bottom: 0; }
        .result-row .score-chip { width: 52px; height: 52px; flex: none; border-radius: 14px; display: grid; place-items: center; font-weight: 800; font-size: 1.05rem; background: var(--sky); color: var(--primary); }
        .result-row .score-chip.good { background: var(--success-bg); color: var(--success-text); }
        .result-row b { display: block; color: var(--ink); font-size: .98rem; }
        .result-row small { color: var(--muted); }
        @media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } }
    </style>

    <section class="section">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><div><?php echo $message; ?></div></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Side card -->
                <aside class="card profile-card reveal">
                    <div class="avatar-big"><?php echo htmlspecialchars($initials); ?></div>
                    <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="sub"><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="role-badge"><?php echo ucfirst(htmlspecialchars($user['role'])); ?> Member</span>
                    <div class="mini-stats">
                        <div class="mini-stat"><b><?php echo (int)$user['total_points']; ?></b><small>Quiz Points</small></div>
                        <div class="mini-stat"><b style="font-size:1rem; padding-top:.35rem;"><?php echo htmlspecialchars($member_since); ?></b><small>Member Since</small></div>
                    </div>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin/dashboard.php#quiz" class="btn btn-primary btn-block" style="margin-top:1.4rem;"><i class="fas fa-plus-circle"></i> Manage Quiz Questions</a>
                        <a href="admin/dashboard.php" class="btn btn-outline btn-block" style="margin-top:.7rem;"><i class="fas fa-cog"></i> Admin Dashboard</a>
                    <?php else: ?>
                        <a href="quiz.php" class="btn btn-primary btn-block" style="margin-top:1.4rem;"><i class="fas fa-star"></i> Take the Quiz</a>
                    <?php endif; ?>
                </aside>

                <!-- Main column -->
                <div>
                    <div class="panel reveal" data-delay="1">
                        <h3 style="font-size: 1.45rem; margin-bottom: 1.6rem;"><i class="fas fa-user-edit" style="color: var(--gold-dark); margin-right:.5rem;"></i>Update Profile</h3>
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="field">
                                    <label for="first_name">First Name <span class="req">*</span></label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="field">
                                    <label for="last_name">Last Name <span class="req">*</span></label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label for="email">Email Address <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="field">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+233 ...">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label for="current_password">Current Password <span style="color: var(--muted); font-weight:500;">(required for email change)</span></label>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" autocomplete="current-password">
                                </div>
                            </div>
                            <h4 style="font-family: var(--font-body); font-size: 1rem; margin: .4rem 0 1rem; color: var(--ink); border-top: 1px dashed var(--line); padding-top: 1.2rem;"><i class="fas fa-lock" style="color: var(--gold-dark); margin-right:.5rem;"></i>Change Password <span style="color: var(--muted); font-weight:500;">(optional)</span></h4>
                            <div class="form-row">
                                <div class="field">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters with uppercase, lowercase, number, special char" autocomplete="new-password">
                                </div>
                                <div class="field">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" autocomplete="new-password">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                    </div>

                    <div class="panel reveal" data-delay="2" style="margin-top: 1.6rem;">
                        <h3 style="font-size: 1.45rem; margin-bottom: 1.2rem;"><i class="fas fa-star" style="color: var(--gold-dark); margin-right:.5rem;"></i>Send a Review</h3>
                        <p style="color: var(--muted); margin-bottom: 1.2rem;">Share your testimony about life in NUPS-G. Your review will be shown on the homepage once an admin approves it.</p>
                        <form method="POST" action="">
                            <div class="field" style="margin-bottom: 1rem;">
                                <label for="rating">Your rating</label>
                                <select id="rating" name="rating" style="width:100%; padding:.7rem .9rem; border:1px solid var(--line); border-radius:var(--r); font-family:inherit;">
                                    <option value="5">★★★★★ Excellent</option>
                                    <option value="4">★★★★☆ Great</option>
                                    <option value="3">★★★☆☆ Good</option>
                                    <option value="2">★★☆☆☆ Fair</option>
                                    <option value="1">★☆☆☆☆ Poor</option>
                                </select>
                            </div>
                            <div class="field" style="margin-bottom: 1.2rem;">
                                <label for="review_content">Your review</label>
                                <textarea id="review_content" name="review_content" rows="4" required minlength="10" placeholder="What has NUPS-G meant to you?" style="width:100%; padding:.7rem .9rem; border:1px solid var(--line); border-radius:var(--r); font-family:inherit;"></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Review</button>
                        </form>
                    </div>

                    <?php if (!empty($my_reviews)): ?>
                    <div class="panel reveal" data-delay="3" style="margin-top: 1.6rem;">
                        <h3 style="font-size: 1.45rem; margin-bottom: 1.2rem;"><i class="fas fa-history" style="color: var(--gold-dark); margin-right:.5rem;"></i>My Reviews</h3>
                        <?php foreach ($my_reviews as $rv): ?>
                            <div class="result-row" style="align-items:flex-start;">
                                <span class="score-chip" style="font-size:.95rem; width:auto; padding:0 .8rem; height:34px;"><?php echo str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']); ?></span>
                                <div style="flex:1; min-width:0;">
                                    <b style="font-weight:600; font-size:.95rem;"><?php echo nl2br(htmlspecialchars($rv['content'])); ?></b>
                                    <small><?php echo formatDateTime($rv['created_at']); ?></small>
                                </div>
                                <span class="pts" style="font-weight:800; font-size:.78rem; text-transform:uppercase; <?php echo $rv['status'] === 'approved' ? 'color: var(--success-text);' : ($rv['status'] === 'rejected' ? 'color: var(--danger-text, #dc2626);' : 'color: var(--gold-dark);'); ?>"><?php echo htmlspecialchars($rv['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="panel reveal" data-delay="4" style="margin-top: 1.6rem;">
                        <h3 style="font-size: 1.45rem; margin-bottom: 1.2rem;"><i class="fas fa-trophy" style="color: var(--gold-dark); margin-right:.5rem;"></i>My Quiz History</h3>
                        <?php if (empty($quiz_history)): ?>
                            <p style="color: var(--muted);">You haven't taken the Bible quiz yet. <a href="quiz.php" style="color: var(--primary); font-weight:700;">Start your first attempt</a> and begin earning points!</p>
                        <?php else: ?>
                            <?php foreach ($quiz_history as $r): ?>
                                <div class="result-row">
                                    <?php $pct = (int)$r['total_questions'] > 0 ? round(100 * (int)$r['score'] / (int)$r['total_questions']) : 0; ?>
                                    <span class="score-chip <?php echo $pct >= 70 ? 'good' : ''; ?>"><?php echo (int)$r['score']; ?>/<?php echo (int)$r['total_questions']; ?></span>
                                    <div style="flex:1;">
                                        <b><?php echo $pct >= 70 ? 'Great job!' : ($pct >= 40 ? 'Nice attempt!' : 'Keep practising!'); ?></b>
                                        <small><?php echo formatDateTime($r['created_at']); ?></small>
                                    </div>
                                    <span class="pts" style="font-weight:800; color: var(--primary);"><?php echo $pct; ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
