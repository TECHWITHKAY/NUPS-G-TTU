<?php
require_once 'config.php';
require_once 'db.php';

$error = '';
$success = '';
$old = $_POST; // retain values on validation failure

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!is_strong_password($password)) {
        $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $error = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)");

            if ($stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password])) {
                $success = 'Registration successful! You can now log in.';
                $old = [];
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join NUPS-G - <?php echo SITE_NAME; ?></title>
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
                <p>&ldquo;I am the vine, ye are the branches: He that abideth in me, and I in him, the same bringeth forth much fruit.&rdquo; <em>- John 15:5</em></p>
            </div>
            <ul class="brand-points">
                <li><i class="fas fa-check-circle"></i> Fellowship with hundreds of believers on campus</li>
                <li><i class="fas fa-check-circle"></i> Daily devotionals &amp; Bible quiz challenges</li>
                <li><i class="fas fa-check-circle"></i> Leadership &amp; service opportunities</li>
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
                    <h1>Join the family</h1>
                    <p>Create your free account in under a minute.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo $success; ?> <a href="login.php" style="font-weight:800; color:var(--success-text); text-decoration:underline;">Log in now &rarr;</a></div>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST" action="" novalidate>
                    <div class="form-row">
                        <div class="field">
                            <label for="first_name">First Name <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="fas fa-user"></i>
                                <input type="text" id="first_name" name="first_name" required placeholder="First name" value="<?php echo htmlspecialchars($old['first_name'] ?? ''); ?>" autocomplete="given-name">
                            </div>
                        </div>
                        <div class="field">
                            <label for="last_name">Last Name <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="fas fa-user"></i>
                                <input type="text" id="last_name" name="last_name" required placeholder="Last name" value="<?php echo htmlspecialchars($old['last_name'] ?? ''); ?>" autocomplete="family-name">
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="you@example.com" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" autocomplete="email">
                        </div>
                    </div>

                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <div class="input-wrap">
                            <i class="fas fa-phone-alt"></i>
                            <input type="tel" id="phone" name="phone" placeholder="+233 ..." value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>" autocomplete="tel">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="password">Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" required placeholder="Min. 8 characters with uppercase, lowercase, number, special char" autocomplete="new-password">
                                <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password" autocomplete="new-password">
                                <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password" onclick="togglePw(this)"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Account</button>
                    <p class="auth-terms">By joining you agree to be part of a loving, Christ-centred community. <i class="fas fa-heart" style="color: var(--rose);"></i></p>
                </form>

                <p class="auth-foot">Already have an account? <a href="login.php">Log in</a></p>
                <?php endif; ?>
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
