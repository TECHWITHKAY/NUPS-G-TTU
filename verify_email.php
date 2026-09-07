<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = trim($_GET['token'] ?? '');
    if ($token === '') {
        $error = 'Invalid verification link.';
    } else {
        $result = verify_email_token($conn, $token);
        if ($result) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$result['new_email'], $result['user_id']]);
            $message = 'Your email address has been verified and updated successfully! You can now log in with your new email.';
        } else {
            $error = 'Invalid or expired verification link. Please request a new one.';
        }
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-envelope"></i> Email Verification</span>
            <h1>Verify Your Email</h1>
            <div class="divider"></div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success reveal in"><i class="fas fa-check-circle"></i><div><?php echo $message; ?></div></div>
                <p style="margin-top: 1.5rem;"><a href="login.php" class="btn btn-primary">Log In</a></p>
            <?php elseif ($error): ?>
                <div class="alert alert-error reveal in"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
                <p style="margin-top: 1.5rem;"><a href="profile.php" class="btn btn-outline">Back to Profile</a></p>
            <?php else: ?>
                <p>Invalid request. <a href="profile.php">Back to Profile</a></p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
