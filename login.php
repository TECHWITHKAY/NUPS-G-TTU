<?php
require_once 'config.php';
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (is_login_rate_limited($conn, $ip, $email)) {
        $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'] ?? '';

            // Clear failed attempts for this IP/email on success
            try {
                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND email = ?");
                $stmt->execute([$ip, $email]);
            } catch (Exception $e) {}

            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
        } else {
            record_login_attempt($conn, $ip, $email);
            $error = 'Invalid email or password. Please try again.';
        }
    }

    // Periodic cleanup of old login attempts
    if (random_int(1, 20) === 1) {
        cleanup_old_login_attempts($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-shell">
        <!-- Brand panel -->
        <aside class="auth-brand">
            <a href="index.php" class="brand-top">
                <img src="nupsglogo.png" alt="NUPS-G TTU crest">
                <span><b>NUPS-G TTU</b><small>For Christ We Live</small></span>
            </a>
            <div class="verse">
                <p>&ldquo;Thy word is a lamp unto my feet, and a light unto my path.&rdquo; <em>- Psalm 119:105</em></p>
            </div>
            <ul class="brand-points">
                <li><i class="fas fa-check-circle"></i> Take the daily Bible quiz &amp; earn points</li>
                <li><i class="fas fa-check-circle"></i> Get notified about events &amp; programmes</li>
                <li><i class="fas fa-check-circle"></i> Grow in a community that cares</li>
            </ul>
        </aside>

        <!-- Form panel -->
        <main class="auth-form">
            <div class="auth-card">
                <div class="logo-mobile">
                    <img src="nupsglogo.png" alt="NUPS-G TTU crest">
                    <b>NUPS-G TTU</b>
                </div>

                <a href="index.php" class="auth-back"><i class="fas fa-arrow-left"></i> Back to Home</a>

                <div class="auth-head">
                    <p style="margin-bottom: 0;">Log in to continue your journey with NUPS-G.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <div class="field">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="you@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email">
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                            <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Log In</button>
                </form>

                <p class="auth-foot">New to NUPS-G? <a href="register.php">Create a free account</a> · <a href="forgot_password.php">Forgot password?</a></p>
            </div>
        </main>
    </div>

    <script>
        function togglePw(btn) {
            var input = btn.parentElement.querySelector('input');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = '<i class="fas fa-eye' + (show ? '-slash' : '') + '"></i>';
        }
    </script>
</body>
</html>
