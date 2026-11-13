<?php
/**
 * One-time database setup helper.
 *
 * Loads database.sql into the database this app is configured to use, so a
 * fresh deploy can be brought up from a browser without psql or shell access.
 *
 * Guarded by the SETUP_KEY environment variable: without a matching key the
 * page reveals nothing and does nothing. Once the schema is loaded, delete
 * this file and remove SETUP_KEY from the environment.
 *
 * This deliberately does not include db.php: that answers 503 when the
 * database is unreachable, and here we need to show the actual error.
 */
require_once 'config.php';

$expected_tables = [
    'users', 'blogs', 'events', 'executives', 'gallery', 'media', 'quiz_questions',
    'quiz_results', 'quiz_settings', 'monthly_quiz_winners', 'reviews', 'social_links',
    'login_attempts', 'contact_messages', 'resources', 'announcements',
];

/**
 * Build the same connection db.php would, without its 503 short-circuit.
 *
 * @return array{0: PDO|null, 1: string}
 */
function setup_connect()
{
    $url = getenv('DATABASE_URL');
    $user = DB_USER;
    $pass = DB_PASS;

    if (!empty($url)) {
        $parts = parse_url($url);
        if ($parts === false) {
            return [null, 'DATABASE_URL could not be parsed.'];
        }
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $name = ltrim($parts['path'] ?? '', '/');
        $user = isset($parts['user']) ? rawurldecode($parts['user']) : $user;
        $pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : $pass;
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $sslmode = $query['sslmode'] ?? 'require';
        $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
    } else {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return [$pdo, ''];
    } catch (Throwable $e) {
        return [null, $e->getMessage()];
    }
}

$key = getenv('SETUP_KEY');
$provided = (string)($_POST['key'] ?? $_GET['key'] ?? '');
$allowed = $key !== false && $key !== '' && hash_equals($key, $provided);

$status = null;      // missing-table report
$error = '';
$done = false;

if ($allowed) {
    [$conn, $conn_error] = setup_connect();

    if ($conn === null) {
        $error = 'Could not connect: ' . $conn_error;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'load') {
        $sql = @file_get_contents(__DIR__ . '/database.sql');
        if ($sql === false || trim($sql) === '') {
            $error = 'database.sql could not be read.';
        } else {
            try {
                $conn->exec($sql); // libpq runs the whole script in one go
                $done = true;
            } catch (Throwable $e) {
                $error = 'Loading stopped: ' . $e->getMessage();
            }
        }
    }

    if ($conn !== null) {
        try {
            $rows = $conn->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
                ->fetchAll(PDO::FETCH_COLUMN);
            $status = [
                'present' => $rows,
                'missing' => array_values(array_diff($expected_tables, $rows)),
                'users' => (int)$conn->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            ];
        } catch (Throwable $e) {
            $status = ['present' => [], 'missing' => $expected_tables, 'users' => 0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Database setup</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1.2rem;
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
               background: linear-gradient(135deg, #1e3c72, #2a5298); color: #0f172a; }
        .card { background: #fff; width: 100%; max-width: 620px; border-radius: 16px; padding: 2rem;
                box-shadow: 0 24px 60px rgba(3,8,24,.35); }
        h1 { font-size: 1.3rem; margin: 0 0 .4rem; color: #1e3c72; }
        p { color: #475569; line-height: 1.6; }
        code { background: #f1f5f9; padding: .1rem .35rem; border-radius: 5px; font-size: .85em; }
        input[type=password] { width: 100%; padding: .75rem 1rem; border: 1px solid #cbd5e1;
                               border-radius: 10px; font-size: .95rem; box-sizing: border-box; }
        button { margin-top: 1rem; width: 100%; padding: .85rem 1rem; border: 0; border-radius: 999px;
                 background: linear-gradient(135deg, #1e3c72, #2a5298); color: #fff;
                 font-weight: 700; font-size: .95rem; cursor: pointer; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: .9rem 1rem;
              border-radius: 10px; margin: 1rem 0; }
        .bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: .9rem 1rem;
               border-radius: 10px; margin: 1rem 0; word-break: break-word; }
        ul { margin: .4rem 0 0; padding-left: 1.1rem; color: #475569; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; margin: .8rem 0; font-size: .88rem; }
        td { padding: .35rem 0; border-bottom: 1px solid #f1f5f9; color: #475569; }
        td:last-child { text-align: right; font-weight: 700; color: #1e3c72; }
    </style>
</head>
<body>
<div class="card">
<?php if (!$allowed): ?>
    <h1>Database setup</h1>
    <?php if ($key === false || $key === ''): ?>
        <div class="bad">No <code>SETUP_KEY</code> environment variable is set, so setup is disabled.
            Add one in Render under Environment, redeploy, then reload this page.</div>
    <?php else: ?>
        <p>Enter the setup key you configured to check the database and load the schema.</p>
        <form method="POST">
            <input type="password" name="key" placeholder="Setup key" autocomplete="off" required>
            <button type="submit">Continue</button>
        </form>
    <?php endif; ?>
<?php else: ?>
    <h1>Database setup</h1>

    <?php if ($error !== ''): ?>
        <div class="bad"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="ok">Schema loaded. Check the table counts below, then delete <code>setup.php</code>
            and remove <code>SETUP_KEY</code>.</div>
    <?php endif; ?>

    <?php if ($status !== null): ?>
        <table>
            <tr><td>Tables present</td><td><?php echo count($status['present']); ?></td></tr>
            <tr><td>Tables expected</td><td><?php echo count($expected_tables); ?></td></tr>
            <tr><td>Rows in <code>users</code></td><td><?php echo $status['users']; ?></td></tr>
        </table>

        <?php if ($status['missing']): ?>
            <div class="bad">Missing: <?php echo htmlspecialchars(implode(', ', $status['missing']), ENT_QUOTES); ?></div>
            <form method="POST">
                <input type="hidden" name="key" value="<?php echo htmlspecialchars($provided, ENT_QUOTES); ?>">
                <input type="hidden" name="action" value="load">
                <button type="submit">Load database.sql</button>
            </form>
        <?php else: ?>
            <div class="ok">All expected tables exist. Sign in at <code>login.php</code> with the seeded
                admin account, then change its password.</div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
