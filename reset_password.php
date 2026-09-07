<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$message = '';
$error = '';
$token = trim($_GET['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $error = 'Invalid reset link.';
    } elseif (empty($password) || empty($confirm_password)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!is_strong_password($password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    } else {
        $email = verify_password_reset_token($conn, $token);
        if ($email) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            if ($stmt->execute([$hash, $email])) {
                $message = 'Your password has been reset successfully! You can now log in.';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-key"></i> Password Reset</span>
            <h1>Reset Your Password</h1>
            <div class="divider"></div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="auth-card" style="max-width: 470px; margin: 0 auto;">
                <?php if ($message): ?>
                    <div class="alert alert-success reveal in"><i class="fas fa-check-circle"></i><div><?php echo $message; ?></div></div>
                    <p style="margin-top: 1.5rem;"><a href="login.php" class="btn btn-primary">Log In</a></p>
                <?php elseif ($error): ?>
                    <div class="alert alert-error reveal in"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
                    <p style="margin-top: 1.5rem;"><a href="forgot_password.php" class="btn btn-outline">Request New Link</a></p>
                <?php elseif (empty($token)): ?>
                    <p>Invalid reset link. <a href="forgot_password.php">Request a new one</a>.</p>
                <?php else: ?>
                    <form method="POST" action="" novalidate>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="field">
                            <label for="password">New Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" required placeholder="Min. 8 characters" autocomplete="new-password">
                                <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password" autocomplete="new-password">
                                <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Reset Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
    function togglePw(btn) {
        var input = btn.parentElement.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = '<i class="fas fa-eye' + (show ? '-slash' : '') + '"></i>';
    }
</script>

<?php include 'footer.php'; ?>
