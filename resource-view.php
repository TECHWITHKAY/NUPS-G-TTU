<?php
/**
 * Login-gated in-browser preview for Resources documents.
 *
 * Usage: resource-view.php?id=<resource_id>
 * Serves the stored file inline (Content-Disposition: inline) so the
 * browser can render it - e.g. a PDF opens in the browser's built-in
 * viewer. Members still use resource-download.php to save the file.
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
    $stmt = $conn->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $row = false;
}

if (!$row) {
    http_response_code(404);
    exit('Resource not found.');
}

$safeName = basename($row['file_name'] ?: $row['file_path']);
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

// Only formats browsers can render inline get a preview; anything else is
// redirected to the normal (attachment) download endpoint.
if ($ext !== 'pdf') {
    redirect('resource-download.php?id=' . (int)$id);
}

$file = __DIR__ . '/uploads/' . basename($row['file_path']);
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Resource file is missing.');
}

$mime_map = resource_mime_map();

header('Pragma: public');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Content-Type: ' . ($mime_map[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . rawurlencode($safeName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit();
