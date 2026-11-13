<?php
/**
 * NUPS-G Church Website Configuration
 *
 * PostgreSQL-friendly configuration.
 * Supports local development (default constants) and Render deployment
 * (DATABASE_URL environment variable) without hardcoding any credentials.
 */

// Load environment variables from .env if it exists (local development only).
// On Render, variables are injected via the real environment, so .env is ignored.
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and blank lines
        if (strpos($line, '#') === 0 || trim($line) === '') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Don't let .env override real environment variables
            if (getenv($key) === false && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Helper to read from environment (real env first, then $_ENV, then fallback)
function env_value($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    return $default;
}

// Application environment: 'development' | 'production'
// Controls error reporting visibility. Never expose errors to users in production.
define('APP_ENV', env_value('APP_ENV', 'development'));

// Database Configuration
// On Render, the DATABASE_URL environment variable is provided automatically
// (e.g. postgres://user:pass@host:5432/dbname). db.php parses it.
// Locally, fall back to discrete connection parameters.
define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', env_value('DB_PORT', '5432'));
define('DB_USER', env_value('DB_USER', 'postgres'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_NAME', env_value('DB_NAME', 'nupsg_church'));

// Site Configuration
define('SITE_NAME', env_value('SITE_NAME', 'NUPS-G Church'));
define('SITE_URL', env_value('SITE_URL', 'http://localhost'));
define('ADMIN_EMAIL', env_value('ADMIN_EMAIL', 'sethsefa18@gmail.com'));

// Optional WhatsApp group invite link (e.g. https://chat.whatsapp.com/XXXX).
// When set, the "Share Quiz" button on the leaderboard opens this group directly;
// otherwise it opens the WhatsApp share picker so the user chooses a chat/group.
define('WHATSAPP_GROUP_URL', env_value('WHATSAPP_GROUP_URL', ''));

// Paystack payment gateway (Ghana: cards + Mobile Money).
// Get your keys at https://dashboard.paystack.com - "Live" keys for production,
// "Test" keys (sk_test_...) while developing. When the secret key is empty the
// donate page silently falls back to the manual "giving intent" form.
define('PAYSTACK_SECRET_KEY', env_value('PAYSTACK_SECRET_KEY', ''));
define('PAYSTACK_PUBLIC_KEY', env_value('PAYSTACK_PUBLIC_KEY', ''));
define('PAYSTACK_CURRENCY', env_value('PAYSTACK_CURRENCY', 'GHS'));

// Paths
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session Configuration
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Africa/Lagos');

// Security Headers
function send_security_headers() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions policy
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    
    // Content Security Policy
    // Allows: self, Google Fonts, Font Awesome CDN, WhatsApp links
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
           "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
           "img-src 'self' data: https: http:; " .
           "connect-src 'self' https://api.paystack.co; " .
           "frame-src https://www.youtube.com https://www.youtube-nocookie.com; " .
           "object-src 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self' https://checkout.paystack.com;";
    header('Content-Security-Policy: ' . $csp);
    
    // HSTS only for HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Error Reporting
// In production we log everything but never display errors to the user.
if (APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatDateTime($date) {
    return date('F j, Y g:i A', strtotime($date));
}

/**
 * Build a wa.me share link for a pre-filled message.
 */
function whatsapp_share_url($text)
{
    return 'https://wa.me/?text=' . rawurlencode($text);
}

/**
 * The link the "Share Quiz" button should open: the configured group invite
 * when available, otherwise the WhatsApp share picker.
 */
function whatsapp_quiz_link()
{
    $text = 'Take the ' . SITE_NAME . ' Bible Quiz and climb the leaderboard!' . "\n" . SITE_URL . '/quiz.php';
    return WHATSAPP_GROUP_URL !== '' ? WHATSAPP_GROUP_URL : whatsapp_share_url($text);
}

/** 'YYYY-MM' style month key to a friendly label, e.g. '2026-08' -> 'August 2026'. */
function month_label($monthKey)
{
    $ts = strtotime($monthKey . '-01');
    return $ts ? date('F Y', $ts) : $monthKey;
}

/**
 * Top scorers within a calendar month (summed across attempts).
 * Returns rows with first_name, last_name, total.
 */
function monthly_quiz_top($conn, $monthKey, $limit = 3)
{
    try {
        $stmt = $conn->prepare(
            "SELECT u.first_name, u.last_name, SUM(r.score) AS total
             FROM quiz_results r
             JOIN users u ON u.id = r.user_id
             WHERE to_char(r.quiz_date, 'YYYY-MM') = ?
             GROUP BY u.id, u.first_name, u.last_name
             ORDER BY total DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $monthKey);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Lazily compute and store the champion of a finished month (called once per
 * month - a month has "ended" once a newer month has begun).
 * Returns the winner row (with first_name/last_name/total) or null.
 */
function ensure_monthly_winner($conn, $monthKey)
{
    try {
        $stmt = $conn->prepare("SELECT mw.total_points, u.first_name, u.last_name
                                FROM monthly_winners mw
                                JOIN users u ON u.id = mw.user_id
                                WHERE mw.month = ?");
        $stmt->execute([$monthKey]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return ['month' => $monthKey, 'total_points' => (int)$existing['total_points'], 'first_name' => $existing['first_name'], 'last_name' => $existing['last_name']];
        }

        $top = monthly_quiz_top($conn, $monthKey, 1);
        if (empty($top)) {
            return null; // nobody played that month - no champion
        }
        $winner = $top[0];
        $stmt = $conn->prepare(
            "INSERT INTO monthly_winners (month, user_id, total_points)
             SELECT ?, id, ? FROM users WHERE first_name = ? AND last_name = ?
             ON CONFLICT (month) DO NOTHING"
        );
        $stmt->execute([$monthKey, (int)$winner['total'], $winner['first_name'], $winner['last_name']]);
        return ['month' => $monthKey, 'total_points' => (int)$winner['total'], 'first_name' => $winner['first_name'], 'last_name' => $winner['last_name']];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Publish today's quiz questions when they do not yet exist.
 *
 * On the first quiz page load of a new day, the manager redistributes a
 * fresh set of questions for that day from the question bank. This keeps the
 * schedule continuous without whoever runs the church admin having to publish
 * each day manually.
 *
 * Questions that have been used in a real, dated quiz are marked so the
 * pool rotates and older questions resurface instead of the same ten
 * sitting permanently on top.
 *
 * Returns empty on success or a short reason when something prevents
 * publishing - in which case the quiz page will gracefully offer fewer or
 * no questions that day rather than erroring out.
 */
function publish_today_questions($conn, $todayKey)
{
    // Already have a dated quiz for this day - nothing to do.
    try {
        $stmt = $conn->prepare(
            "SELECT id FROM quiz_questions WHERE quiz_date = ? LIMIT 1"
        );
        $stmt->execute([$todayKey]);
        if ($stmt->fetchColumn()) {
            return '';
        }
    } catch (Exception $e) {
        return 'could not check whether today already has questions';
    }

    // Pick from the bank: prefer questions unused in any dated quiz, then the
    // least recently used, then oldest created - whichever leaves a healthy
    // rotation after the session is finished.
    try {
        $stmt = $conn->prepare(
            "SELECT id, question, option_a, option_b, option_c, option_d,
                    correct_answer, points
             FROM quiz_questions
             WHERE quiz_date IS NULL
             ORDER BY (last_used_on IS NULL) DESC, last_used_on ASC, created_at ASC
             LIMIT 10"
        );
        $stmt->execute();
        $pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return 'could not read the question bank';
    }

    // Nothing in the bank at all - there is no paper to publish.
    // Publish whatever the admins have entered (up to 10), so a short quiz is
    // still playable instead of being silently withheld.
    if (count($pool) === 0) {
        return 'no questions in the bank yet';
    }

    // Save today's paper and mark the used bank rows, all inside one
    // transaction so two members opening at once cannot create two different
    // papers.
    try {
        $conn->beginTransaction();

        // Re-assert there is still no paper for today - a racing reader may
        // have published between our check and our lock.
        $stmt = $conn->prepare(
            "SELECT id FROM quiz_questions WHERE quiz_date = ? LIMIT 1"
        );
        $stmt->execute([$todayKey]);
        if ($stmt->fetchColumn()) {
            $conn->rollBack();
            return '';
        }

        foreach ($pool as $q) {
            $stmt = $conn->prepare(
                "INSERT INTO quiz_questions
                    (question, option_a, option_b, option_c, option_d,
                     correct_answer, points, quiz_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $q['question'], $q['option_a'], $q['option_b'], $q['option_c'],
                $q['option_d'], $q['correct_answer'], $q['points'], $todayKey
            ]);
            // Note the bank row's last use date.
            $stmt = $conn->prepare(
                "UPDATE quiz_questions SET last_used_on = ? WHERE id = ?"
            );
            $stmt->execute([$todayKey, $q['id']]);
        }

        $conn->commit();
        return '';
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return 'could not publish today questions: ' . $e->getMessage();
    }
}

/**
 * Text helpers that do not hard-depend on the mbstring extension.
 */
function text_length($text) {
    return function_exists('mb_strlen') ? mb_strlen((string)$text, 'UTF-8') : strlen((string)$text);
}

function text_excerpt($text, $max = 120, $suffix = '…') {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $max, $suffix, 'UTF-8');
    }
    // Portable fallback (approximate): cut on bytes, then drop any partial UTF-8 tail.
    if (strlen($text) <= $max) return $text;
    $cut = substr($text, 0, $max);
    while ($cut !== '' && (ord(substr($cut, -1)) & 0xC0) === 0x80) {
        $cut = substr($cut, 0, -1);
    }
    return rtrim($cut) . $suffix;
}

/**
 * Low-level cURL call to the Paystack REST API.
 * Returns ['ok' => true, 'data' => decoded] or ['ok' => false, 'error' => reason].
 */
function paystack_api($method, $path, $payload = null)
{
    if (PAYSTACK_SECRET_KEY === '') {
        return ['ok' => false, 'error' => 'Paystack is not configured (missing secret key).'];
    }
    $ch = curl_init('https://api.paystack.co' . $path);
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }
    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === '') {
        return ['ok' => false, 'error' => 'Could not reach Paystack' . ($curlErr !== '' ? ': ' . $curlErr : '.')];
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Paystack returned an unreadable response.'];
    }
    return ['ok' => true, 'data' => $data];
}

/**
 * Initialize a Paystack payment.
 * amount_ghs: positive decimal in GHS (converted to pesewas internally).
 * reference:  unique transaction reference (also stored on the donation row).
 * metadata:   associative array of extra fields to attach to the transaction.
 * Returns ['ok' => true, 'authorization_url' => ...] or ['ok' => false, 'error' => reason].
 */
function paystack_initialize($amountGhs, $email, $reference, $metadata = [])
{
    $pesewas = (int)round((float)$amountGhs * 100);
    if ($pesewas < 100) {
        return ['ok' => false, 'error' => 'The minimum donation is 1 GHS.'];
    }
    $res = paystack_api('POST', '/transaction/initialize', [
        'amount' => $pesewas,
        'email' => $email,
        'currency' => PAYSTACK_CURRENCY,
        'reference' => $reference,
        'callback_url' => SITE_URL . '/donate.php',
        'metadata' => $metadata,
    ]);
    if (!$res['ok']) {
        return $res;
    }
    $data = $res['data'];
    if (!empty($data['status']) && !empty($data['data']['authorization_url'])) {
        return ['ok' => true, 'authorization_url' => $data['data']['authorization_url']];
    }
    return ['ok' => false, 'error' => $data['message'] ?? 'Paystack could not start the payment.'];
}

/**
 * Verify a Paystack transaction after the donor returns from checkout.
 * Returns ['ok' => true, 'status' => 'success'|'failed'|'abandoned'|..., 'data' => ...]
 * or ['ok' => false, 'error' => reason].
 */
function paystack_verify($reference)
{
    $res = paystack_api('GET', '/transaction/verify/' . rawurlencode($reference));
    if (!$res['ok']) {
        return $res;
    }
    $data = $res['data'];
    if (empty($data['status']) || !isset($data['data'])) {
        return ['ok' => false, 'error' => $data['message'] ?? 'Paystack could not verify the payment.'];
    }
    return [
        'ok' => true,
        'status' => strtolower((string)($data['data']['status'] ?? 'unknown')),
        'data' => $data['data'],
    ];
}

/**
 * Password strength validation.
 * Returns true if password meets minimum requirements.
 */
function is_strong_password($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

/**
 * Check if login is rate-limited for this IP/email combination.
 * Returns true if blocked, false if allowed.
 */
function is_login_rate_limited($conn, $ip, $email) {
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as attempts FROM login_attempts
             WHERE ip_address = ? AND email = ? AND attempted_at > NOW() - INTERVAL '15 minutes'"
        );
        $stmt->execute([$ip, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row['attempts'] ?? 0) >= 5;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Record a failed login attempt.
 */
function record_login_attempt($conn, $ip, $email) {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)"
        );
        $stmt->execute([$ip, $email]);
    } catch (Exception $e) {
        // Do not break login flow on logging failure
    }
}

/**
 * Clean up old login attempts (older than 1 hour).
 */
function cleanup_old_login_attempts($conn) {
    try {
        $conn->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL '1 hour'");
    } catch (Exception $e) {
        // Silent cleanup
    }
}

/**
 * Generate a cryptographically secure random token.
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Create a password reset token.
 * Returns the token on success, null on failure.
 */
function create_password_reset($conn, $email) {
    try {
        $token = generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare(
            "INSERT INTO password_resets (email, token, expires_at)
             VALUES (?, ?, ?)
             ON CONFLICT (email) DO UPDATE
                SET token = EXCLUDED.token,
                    expires_at = EXCLUDED.expires_at,
                    used = FALSE,
                    created_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$email, $token, $expires]);
        return $token;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Verify and consume a password reset token.
 * Returns the email if valid, null if invalid/expired/used.
 */
function verify_password_reset_token($conn, $token) {
    try {
        $stmt = $conn->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND used = FALSE AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Mark as used
        $stmt = $conn->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
        $stmt->execute([$token]);

        return $row['email'];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Create an email verification token for a user changing their email.
 * Returns the token on success, null on failure.
 */
function create_email_verification($conn, $user_id, $new_email) {
    try {
        $token = generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $conn->prepare(
            "INSERT INTO email_verifications (user_id, new_email, token, expires_at)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $new_email, $token, $expires]);
        return $token;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Verify and consume an email verification token.
 * Returns ['user_id' => ..., 'new_email' => ...] or null.
 */
function verify_email_token($conn, $token) {
    try {
        $stmt = $conn->prepare(
            "SELECT user_id, new_email FROM email_verifications
             WHERE token = ? AND used = FALSE AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $stmt = $conn->prepare("UPDATE email_verifications SET used = TRUE WHERE token = ?");
        $stmt->execute([$token]);

        return ['user_id' => (int)$row['user_id'], 'new_email' => $row['new_email']];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Log an admin action for audit purposes.
 */
function admin_audit_log($conn, $admin_id, $action, $resource_type = null, $resource_id = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $conn->prepare(
            "INSERT INTO admin_audit_log (admin_id, action, resource_type, resource_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$admin_id,
            $action,
            $resource_type,
            $resource_id !== null ? (int)$resource_id : null,
            $details !== null ? substr((string)$details, 0, 500) : null,
            $ip,
            substr((string)$ua, 0, 500)
        ]);
    } catch (Exception $e) {
        // Silent audit logging failure
    }
}

/**
 * Validate uploaded file for security.
 * Returns ['ok' => true] or ['ok' => false, 'error' => '...'].
 */
function validate_uploaded_file($file, $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/x-m4a'], $max_size = 5 * 1024 * 1024) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'No file uploaded or invalid upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error code: ' . $file['error']];
    }
    if ($file['size'] > $max_size) {
        return ['ok' => false, 'error' => 'File exceeds maximum size of ' . ($max_size / 1024 / 1024) . 'MB.'];
    }

    // Verify MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types, true)) {
        return ['ok' => false, 'error' => 'Invalid file type: ' . $mime];
    }

    // Verify extension matches MIME type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext_map = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'audio/mpeg' => ['mp3'],
        'audio/mp4' => ['m4a', 'mp4'],
        'audio/x-m4a' => ['m4a'],
        'audio/ogg' => ['ogg', 'oga'],
        'audio/wav' => ['wav'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/rtf' => ['rtf'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
    ];
    $allowed_exts = [];
    foreach ($allowed_types as $type) {
        $allowed_exts = array_merge($allowed_exts, $ext_map[$type] ?? []);
    }
    if (!in_array($ext, $allowed_exts, true)) {
        return ['ok' => false, 'error' => 'File extension does not match file type.'];
    }

    return ['ok' => true, 'mime' => $mime, 'extension' => $ext];
}

/**
 * Handle admin image upload with validation.
 * Returns ['ok' => true, 'filename' => '...'] or ['ok' => false, 'error' => '...'].
 */
function handle_admin_image_upload($file, $prefix = 'img_', $upload_dir = null) {
    if ($upload_dir === null) {
        $upload_dir = ROOT_PATH . '/uploads/';
    }
    $result = validate_uploaded_file($file);
    if (!$result['ok']) {
        return $result;
    }
    $new_filename = $prefix . time() . '_' . random_int(1000, 9999) . '.' . $result['extension'];
    $upload_path = $upload_dir . $new_filename;
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['ok' => true, 'filename' => $new_filename];
    }
    return ['ok' => false, 'error' => 'Failed to move uploaded file.'];
}

/**
 * MIME types allowed in the member Resources download library.
 * Only safe office/text documents - no executables or archives.
 */
function resource_allowed_mimes()
{
    return [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/rtf',
        'text/plain',
        'text/csv',
    ];
}

/**
 * MIME type map used when streaming a resource file to the browser.
 */
function resource_mime_map()
{
    return [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'rtf' => 'application/rtf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
    ];
}

/**
 * Font Awesome icon + colour for a resource file, keyed by extension.
 * Returns ['icon' => 'fa-file-pdf', 'color' => '#e11d48'].
 */
function resource_file_icon($file_name)
{
    $ext = strtolower(pathinfo((string)$file_name, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':  return ['icon' => 'fa-file-pdf', 'color' => '#e11d48', 'label' => 'PDF'];
        case 'doc':  return ['icon' => 'fa-file-word', 'color' => '#2563eb', 'label' => 'Word'];
        case 'docx': return ['icon' => 'fa-file-word', 'color' => '#2563eb', 'label' => 'Word'];
        case 'ppt':  return ['icon' => 'fa-file-powerpoint', 'color' => '#ea580c', 'label' => 'PowerPoint'];
        case 'pptx': return ['icon' => 'fa-file-powerpoint', 'color' => '#ea580c', 'label' => 'PowerPoint'];
        case 'xls':  return ['icon' => 'fa-file-excel', 'color' => '#16a34a', 'label' => 'Excel'];
        case 'xlsx': return ['icon' => 'fa-file-excel', 'color' => '#16a34a', 'label' => 'Excel'];
        case 'rtf':  return ['icon' => 'fa-file-alt', 'color' => '#64748b', 'label' => 'RTF'];
        case 'txt':  return ['icon' => 'fa-file-alt', 'color' => '#64748b', 'label' => 'Text'];
        case 'csv':  return ['icon' => 'fa-file-csv', 'color' => '#16a34a', 'label' => 'CSV'];
        default:     return ['icon' => 'fa-file', 'color' => '#64748b', 'label' => strtoupper($ext ?: 'FILE')];
    }
}

/**
 * Human-readable file size, e.g. 1536 -> '1.5 KB'.
 */
function human_filesize($bytes, $decimals = 1)
{
    $bytes = max(0, (float)$bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i === 0 ? 0 : $decimals) . ' ' . $units[$i];
}

