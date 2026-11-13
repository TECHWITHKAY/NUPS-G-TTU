<?php
/**
 * Database Connection (PostgreSQL)
 *
 * Replaces the previous MySQL PDO connection. Uses the `pgsql` PDO driver.
 * Automatically reads the Render `DATABASE_URL` environment variable when
 * deployed, and falls back to localhost PostgreSQL settings during local
 * development. No production credentials are hardcoded.
 */

require_once 'config.php';

/**
 * Parse a Render / standard DATABASE_URL into connection parameters.
 * Example: postgres://user:password@host:5432/dbname
 * Returns null when no DATABASE_URL is present (local development).
 */
function parseDatabaseUrl($url) {
    if (empty($url)) return null;
    $parts = parse_url($url);
    if ($parts === false) return null;

    // Render appends query parameters such as ?sslmode=require
    $query = isset($parts['query']) ? $parts['query'] : '';
    parse_str($query, $q);

    return array(
        'host' => isset($parts['host']) ? $parts['host'] : 'localhost',
        'port' => isset($parts['port']) ? $parts['port'] : 5432,
        'user' => isset($parts['user']) ? rawurldecode($parts['user']) : 'postgres',
        'pass' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
        'name' => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
        'sslmode' => isset($q['sslmode']) ? $q['sslmode'] : 'require',
    );
}

/**
 * Build the PostgreSQL PDO DSN.
 * Prefers DATABASE_URL (Render) and falls back to discrete config constants.
 */
function buildPgDsn() {
    $url = getenv('DATABASE_URL');
    $db = parseDatabaseUrl($url);

    if ($db !== null) {
        // Render requires SSL; honor the URL's sslmode or default to require.
        $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']};sslmode={$db['sslmode']};";
        // Stash credentials globally so getDbConnection() can use them
        $GLOBALS['_db_user'] = $db['user'];
        $GLOBALS['_db_pass'] = $db['pass'];
    } else {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";";
        $GLOBALS['_db_user'] = DB_USER;
        $GLOBALS['_db_pass'] = DB_PASS;
    }

    return $dsn;
}

/**
 * Open (and reuse) a PostgreSQL PDO connection.
 * On failure the exception is logged (without exposing credentials) and null
 * is returned so public pages can degrade gracefully.
 *
 * @return PDO|null
 */
function getDbConnection() {
    static $pdo = null;
    static $attempted = false;

    if ($attempted) return $pdo;
    $attempted = true;

    $dsn = buildPgDsn();

    try {
        $pdo = new PDO(
            $dsn,
            $GLOBALS['_db_user'] ?? DB_USER,
            $GLOBALS['_db_pass'] ?? DB_PASS,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // pgsql driver does not emulate prepares; keep it explicit
                PDO::ATTR_EMULATE_PREPARES => false
            )
        );
        // Use standard PostgreSQL quoting/identifiers
        $pdo->exec("SET client_encoding TO 'UTF8'");
        return $pdo;
    } catch (PDOException $e) {
        // Log the real error for administrators; never show it to users.
        error_log('PostgreSQL connection failed: ' . $e->getMessage());
        $GLOBALS['db_last_error'] = $e->getMessage();
        $pdo = null;
        return null;
    }
}

// Global connection handle used throughout the application.
$conn = getDbConnection();

/**
 * Answer with a clear 503 page when the database is unreachable.
 *
 * Pages across the site query $conn directly, so a null handle used to end in
 * "Call to a member function query() on null". This single choke point keeps
 * every page readable and tells the visitor what happened. CLI scripts (cron,
 * exports, the tools in this repo) are left alone and handle null themselves.
 */
function render_database_unavailable()
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 60');
    header('Cache-Control: no-store');

    // In development, show the driver's message to speed up diagnosis.
    // In production the reason stays in the server log only.
    $detail = '';
    if (!defined('APP_ENV') || APP_ENV !== 'production') {
        $error = isset($GLOBALS['db_last_error']) ? (string)$GLOBALS['db_last_error'] : '';
        if ($error !== '') {
            $detail = '<p style="margin-top:1.2rem;font-size:.8rem;color:#64748b;word-break:break-word;">'
                . htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
        }
    }

    $site = defined('SITE_NAME') ? SITE_NAME : 'NUPS-G TTU';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Service unavailable - ' . htmlspecialchars($site, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;'
        . 'font-family:"Plus Jakarta Sans",system-ui,-apple-system,sans-serif;'
        . 'background:linear-gradient(135deg,#1e3c72,#2a5298);color:#0f172a;padding:1.2rem}'
        . '.card{background:#fff;max-width:520px;width:100%;border-radius:18px;padding:2.4rem;'
        . 'text-align:center;box-shadow:0 24px 60px rgba(3,8,24,.35)}'
        . 'h1{margin:.6rem 0 .4rem;font-size:1.5rem;color:#1e3c72}'
        . 'p{color:#475569;line-height:1.7;margin:.4rem 0}'
        . 'a.btn{display:inline-block;margin-top:1.6rem;background:linear-gradient(135deg,#1e3c72,#2a5298);'
        . 'color:#fff;text-decoration:none;padding:.8rem 1.6rem;border-radius:999px;font-weight:700}'
        . '</style></head><body><div class="card">'
        . '<div style="font-size:2.2rem">⛪</div>'
        . '<h1>We can\'t load this page right now</h1>'
        . '<p>The website could not reach its database. Please try again in a minute.</p>'
        . $detail
        . '<a class="btn" href="index.php">Try again</a>'
        . '</div></body></html>';
    exit;
}

if ($conn === null) {
    render_database_unavailable();
}

/**
 * Check whether a table exists (PostgreSQL / information_schema).
 */
function tableExists($conn, $tableName) {
    if (!$conn) return false;
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = 'public' AND table_name = ? LIMIT 1"
    );
    $stmt->execute([$tableName]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Check whether a column exists in a table (PostgreSQL / information_schema).
 * Replaces the old MySQL `SHOW COLUMNS FROM table LIKE 'col'`.
 */
function columnExists($conn, $tableName, $columnName) {
    if (!$conn) return false;
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1"
    );
    $stmt->execute([$tableName, $columnName]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Split a SQL script into individual statements.
 * Respects PostgreSQL dollar-quoted blocks ($$ ... $$) and single-quoted
 * string literals so that CREATE FUNCTION / CREATE TRIGGER bodies are not
 * broken at interior semicolons.
 */
function splitSqlStatements($sql) {
    $statements = array();
    $current = '';
    $inDollar = false;
    $inSingle = false;
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($inDollar) {
            $current .= $ch;
            if ($ch === '$') {
                // Look for the closing $$ tag
                if (substr($sql, $i, 2) === '$$') {
                    $inDollar = false;
                    $current .= '$';
                    $i++; // consume the second $
                }
            }
        } elseif ($inSingle) {
            $current .= $ch;
            if ($ch === '\\' && $next !== '') {
                $current .= $next;
                $i++;
            } elseif ($ch === "'") {
                $inSingle = false;
            }
        } else {
            if ($ch === '$' && $next === '$') {
                $inDollar = true;
                $current .= '$$';
                $i++; // consume the second $
            } elseif ($ch === "'") {
                $inSingle = true;
                $current .= $ch;
            } elseif ($ch === ';') {
                if (trim($current) !== '') {
                    $statements[] = $current;
                }
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $i++;
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}

/**
 * Initialize the database from database.sql if it has not been set up yet.
 * PostgreSQL does not support running a multi-statement script through a
 * single PDO::exec(), so we split the file into statements first.
 */
function initializeDatabase($conn) {
    if (!$conn) return;

    // Only initialize if the core users table is missing
    if (tableExists($conn, 'users')) {
        return;
    }

    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        return;
    }

    $sql = file_get_contents($sqlFile);
    $statements = splitSqlStatements($sql);

    foreach ($statements as $statement) {
        try {
            $conn->exec($statement);
        } catch (PDOException $e) {
            error_log('Database initialization error: ' . $e->getMessage());
        }
    }
}

// Uncomment the line below to auto-initialize the database on first run.
// initializeDatabase($conn);
