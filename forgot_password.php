<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Always show generic message to prevent account enumeration
        $message = 'If an account with that email exists, you will receive a password reset link shortly.';
        
        // Create reset token if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $token = create_password_reset($conn, $email);
            if ($token) {
                $reset_link = SITE_URL . '/reset_password.php?token=' . urlencode($token);
                $mail_subject = 'Reset your password';
                $mail_body = '<p>Hello,</p>' .
                    '<p>You requested to reset your password for your ' . SITE_NAME . ' account.</p>' .
                    '<p>Click the link below to reset your password:</p>' .
                    '<p><a href="' . $reset_link . '">Reset Password</a></p>' .
                    '<p>This link will expire in 1 hour.</p>' .
                    '<p>If you did not request this reset, please ignore this email.</p>';
                $result = send_mail($email, $mail_subject, $mail_body);

                if ($result['sent']) {
                    error_log('Password reset email sent to ' . $email);
                } else {
                    error_log('Password reset email failed for ' . $email . ': ' . ($result['error'] ?? 'unknown error'));
                    if (APP_ENV === 'development') {
                        $message .= ' (Email send failed: ' . htmlspecialchars($result['error'] ?? 'unknown') . '. Check server logs and SMTP settings.)';
                    }
                }

                if (APP_ENV === 'development') {
                    error_log('Password reset link for ' . $email . ': ' . $reset_link);
                }
            } else {
                error_log('Failed to create password reset token for ' . $email);
            }
        }
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-key"></i> Password Reset</span>
            <h1>Forgot Your Password?</h1>
            <div class="divider"></div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="auth-card" style="max-width: 470px; margin: 0 auto;">
                <?php if ($message): ?>
                    <div class="alert alert-success reveal in"><i class="fas fa-check-circle"></i><div><?php echo $message; ?></div></div>
                    <p style="margin-top: 1.5rem;"><a href="login.php" class="btn btn-primary">Back to Login</a></p>
                <?php else: ?>
                    <p style="margin-bottom: 1.5rem; color: var(--muted);">Enter your email address and we will send you a link to reset your password.</p>
                    <form method="POST" action="" novalidate>
                        <div class="field">
                            <label for="email">Email Address</label>
                            <div class="input-wrap">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" required placeholder="you@example.com" autocomplete="email">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
                    </form>
                    <p style="margin-top: 1.5rem;"><a href="login.php" class="btn btn-outline">Back to Login</a></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
