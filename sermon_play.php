<?php
/**
 * Sermon play counter.
 *
 * POST { id: <blog id> }  ->  increments the sermon's play_count by one.
 * A visitor is counted once per sermon per browser session, so refreshes
 * and repeated plays in the same visit don't inflate the number.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$respond = function (bool $ok, $value) {
    echo json_encode($ok ? ['ok' => true, 'plays' => $value] : ['ok' => false, 'error' => $value]);
    exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 'POST required.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $respond(false, 'Missing sermon id.');
}

try {
    $stmt = $conn->prepare("SELECT post_type, audio_file, audio_url FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    $sermon = $stmt->fetch(PDO::FETCH_ASSOC);

    // Only sermons with something to listen to can be counted.
    if (!$sermon || $sermon['post_type'] !== 'sermon' || (empty($sermon['audio_file']) && empty($sermon['audio_url']))) {
        $respond(false, 'Sermon not found or has no audio.');
    }
} catch (Exception $e) {
    $respond(false, 'Could not look up the sermon.');
}

// One count per visitor per sermon, per session.
$_SESSION['sermon_plays'] = $_SESSION['sermon_plays'] ?? [];
if (!in_array($id, $_SESSION['sermon_plays'], true)) {
    $_SESSION['sermon_plays'][] = $id;
    try {
        $stmt = $conn->prepare("UPDATE blogs SET play_count = COALESCE(play_count, 0) + 1 WHERE id = ?");
        $stmt->execute([$id]);
    } catch (Exception $e) {
        $respond(false, 'Could not record the play.');
    }
}

try {
    $stmt = $conn->prepare("SELECT COALESCE(play_count, 0) FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    $respond(true, (int)$stmt->fetchColumn());
} catch (Exception $e) {
    $respond(true, 0);
}
