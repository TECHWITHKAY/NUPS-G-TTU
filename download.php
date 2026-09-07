<?php
/**
 * Login-gated image download endpoint for the gallery.
 *
 * Usage: download.php?id=<media_id>
 * Serves the stored image as an attachment only to logged-in users.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $stmt = $conn->prepare("SELECT * FROM media WHERE id = ? AND file_type = 'image'");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $row = false;
}

if (!$row) {
    http_response_code(404);
    exit('Photo not found.');
}

$safeName = basename($row['file_path']);
$file = __DIR__ . '/uploads/' . $safeName;
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Photo file is missing.');
}

// Never let browsers cache the download behind a login.
$mime_map = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
];
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
header('Pragma: public');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Content-Type: ' . ($mime_map[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . rawurlencode($safeName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit();
