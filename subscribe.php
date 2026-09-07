<?php
/**
 * Newsletter subscribe / unsubscribe endpoint.
 *
 * POST (email)        -> saves the subscriber (fetch JSON or plain form POST).
 * GET ?unsubscribe=1  -> deactivates the subscriber (link sent in emails).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function wants_json()
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($accept, 'application/json') !== false
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function respond_json($ok, $message)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit();
}

// ---- Unsubscribe via emailed link: subscribe.php?unsubscribe=1&email=..&token=.. ----
if (isset($_GET['unsubscribe'])) {
    $email = strtolower(trim($_GET['email'] ?? ''));
    $token = trim($_GET['token'] ?? '');
    $updated = 0;

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $token !== '') {
        try {
            $stmt = $conn->prepare("UPDATE subscribers SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE email = ? AND token = ? AND is_active = TRUE");
            $stmt->execute([$email, $token]);
            $updated = $stmt->rowCount();
        } catch (Exception $e) {
            // fall through to the confirmation page
        }
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsubscribed - <?php echo htmlspecialchars(SITE_NAME); ?></title>
<style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(135deg, #101b3d, #1e3a8a); font-family: 'Segoe UI', Arial, sans-serif; color: #fff; text-align: center; }
    .card { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.18); border-radius: 20px; padding: 2.5rem 2rem; max-width: 400px; margin: 1rem; backdrop-filter: blur(6px); }
    .icon { width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%; background: rgba(242,169,0,0.9); color: #101b3d; display: grid; place-items: center; font-size: 1.8rem; line-height: 1; }
    h1 { font-size: 1.4rem; margin: 0 0 .6rem; }
    p { color: rgba(255,255,255,0.85); line-height: 1.7; margin: 0 0 1.4rem; }
    a.btn { display: inline-block; padding: .7rem 1.6rem; border-radius: 999px; background: #fff; color: #1e3a8a; font-weight: 700; text-decoration: none; }
</style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1><?php echo $updated ? 'You have been unsubscribed' : 'Subscription not found'; ?></h1>
        <p><?php echo $updated
            ? 'You will no longer receive event and blog notifications from ' . htmlspecialchars(SITE_NAME) . '. Sorry to see you go. You can subscribe again anytime from our website.'
            : 'This unsubscribe link is invalid or this email was already removed. If you think this is a mistake, contact us.'; ?></p>
        <a class="btn" href="<?php echo htmlspecialchars(SITE_URL . '/'); ?>">Back to <?php echo htmlspecialchars(SITE_NAME); ?></a>
    </div>
</body>
</html><?php
    exit();
}

// ---- Subscribe ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_json(false, 'Please enter a valid email address.');
    }

    try {
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare(
            "INSERT INTO subscribers (email, token, is_active)
             VALUES (?, ?, TRUE)
             ON CONFLICT (email) DO UPDATE
                SET token = EXCLUDED.token,
                    is_active = TRUE,
                    updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$email, $token]);
    } catch (Exception $e) {
        error_log('subscribe.php: ' . $e->getMessage());
        respond_json(false, 'Could not subscribe right now. Please try again later.');
    }

    if (wants_json()) {
        respond_json(true, 'Thank you! You are subscribed. God bless you!');
    }

    // Plain form POST fallback (no JavaScript): bounce back to the referring page.
    $back = $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/';
    $sep = (strpos($back, '?') !== false) ? '&' : '?';
    redirect($back . $sep . 'subscribed=1');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscribe - <?php echo htmlspecialchars(SITE_NAME); ?></title>
</head>
<body>
<p>This page accepts newsletter subscriptions via POST.</p>
</body>
</html>
