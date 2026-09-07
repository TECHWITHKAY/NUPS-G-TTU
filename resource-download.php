<?php
/**
 * Login-gated document download for the Resources library.
 *
 * Usage: resource-download.php?id=<resource_id>
 * Serves the stored file as an attachment only to logged-in users,
 * mirroring how gallery photo downloads work (download.php).
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

$storedName = basename($row['file_path']);
$file = __DIR__ . '/uploads/' . $storedName;
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Resource file is missing.');
}

// Serve under the human-friendly name the admin uploaded, never the
// internal stored name. The filename is URI-encoded so spaces and
// non-ASCII characters survive the Content-Disposition header.
$safeName = basename($row['file_name'] ?: $storedName);
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
$mime_map = resource_mime_map();

header('Pragma: public');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Content-Type: ' . ($mime_map[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Content-Disposition: attachment; filename="' . rawurlencode($safeName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($file);

// Count the download only after the file has actually been served.
try {
    $stmt = $conn->prepare("UPDATE resources SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$id]);
} catch (Exception $e) {
    // A failed counter must never break the download itself.
}
exit();
