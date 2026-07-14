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
define('ADMIN_EMAIL', env_value('ADMIN_EMAIL', 'admin@nupsgchurch.com'));

// Paths
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Africa/Lagos');

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
