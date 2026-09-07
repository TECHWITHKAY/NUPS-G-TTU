<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$gallery_can_download = isLoggedIn();

$album_id = isset($_GET['album']) ? (int)$_GET['album'] : -1; // >0 folder, 0 = General photos, -1 = folder index

$page_title = 'Gallery';
$page_description = 'Moments of worship, fellowship and community life at NUPS-G TTU.';

$active_album = null;
$album_images = [];
$albums = [];
$general_count = 0;
$total_any = false;

if ($conn !== null) {
    try {
        // Album detail view
        if ($album_id > 0) {
            $stmt = $conn->prepare("SELECT * FROM albums WHERE id = ?");
            $stmt->execute([$album_id]);
            $active_album = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($active_album) {
                $stmt = $conn->prepare("SELECT * FROM media WHERE album_id = ? AND file_type = 'image' ORDER BY created_at ASC");
                $stmt->execute([$album_id]);
                $album_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $page_title = $active_album['title'] . ' - Gallery';
            }
        } elseif ($album_id === 0) {
            // General (uncategorised) photos
            $stmt = $conn->query("SELECT * FROM media WHERE album_id IS NULL AND file_type = 'image' ORDER BY created_at ASC");
            $album_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $page_title = 'General Photos - Gallery';
        } else {
            // Folder index
            $stmt = $conn->query("SELECT a.*, COUNT(m.id) AS photo_count
                                  FROM albums a
                                  LEFT JOIN media m ON m.album_id = a.id AND m.file_type = 'image'
                                  GROUP BY a.id ORDER BY a.created_at DESC");
            $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->query("SELECT COUNT(*) AS c FROM media WHERE album_id IS NULL AND file_type = 'image'");
            $general_count = (int)($stmt->fetch()['c'] ?? 0);

            $total_any = count($albums) > 0 || $general_count > 0;
        }
    } catch (Exception $e) {
        $albums = []; $album_images = []; $general_count = 0; $total_any = false;
    }
}

// Resolve a display cover file for each album (explicit cover, else first photo)
$cover_map = [];
if (!empty($albums)) {
    foreach ($albums as $al) {
        $cover_map[$al['id']] = null;
    }
    try {
        $stmt = $conn->query("SELECT DISTINCT ON (album_id) album_id, file_path
                              FROM media
                              WHERE album_id IS NOT NULL AND file_type = 'image'
                              ORDER BY album_id, created_at ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cover_map[$row['album_id']] = $row['file_path'];
        }
    } catch (Exception $e) {
        // fall back to explicit covers only
    }
}

function gallery_cover_file($album, $cover_map)
{
    $explicit = $album['cover_image'] ?? '';
    if ($explicit !== '' && file_exists('uploads/' . basename($explicit))) {
        return 'uploads/' . basename($explicit);
    }
    $first = $cover_map[$album['id']] ?? null;
    if ($first !== null && file_exists('uploads/' . $first)) {
        return 'uploads/' . $first;
    }
    return null;
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-images"></i> Moments</span>
            <h1><?php echo $active_album ? htmlspecialchars($active_album['title']) : ($album_id === 0 ? 'General Photos' : 'Photo Gallery'); ?></h1>
            <div class="divider"></div>
            <p><?php
                if ($active_album) {
                    echo htmlspecialchars($active_album['description'] ?: 'Capturing worship, fellowship and community: one picture at a time');
                } else {
                    echo 'Browse each programme’s photo folder, or open one to view and download its pictures';
                }
            ?></p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if (!$gallery_can_download): ?>
                <div class="gallery-note reveal">
                    <i class="fas fa-lock"></i>
                    <p><b>Photos are free to view.</b><br>Log in as a member to download them.</p>
                    <a class="note-cta" href="login.php">Log In <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>

            <?php if ($album_id === 0 || $active_album): ?>
                <?php if ($album_id !== 0 && !$active_album): ?>
                    <div class="empty reveal">
                        <i class="fas fa-folder-open"></i>
                        <h3>Folder not found</h3>
                        <p>This gallery folder may have been removed.</p>
                        <a href="gallery.php" class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-arrow-left"></i> Back to Gallery</a>
                    </div>
                <?php elseif (empty($album_images)): ?>
                    <div class="empty reveal">
                        <i class="fas fa-images"></i>
                        <h3>No photos in this folder yet</h3>
                        <p>Check back soon. Pictures will appear here after the programme.</p>
                        <a href="gallery.php" class="btn btn-outline" style="margin-top:1rem;"><i class="fas fa-arrow-left"></i> All Folders</a>
                    </div>
                <?php else: ?>
                    <p class="crumb-back reveal"><a href="gallery.php"><i class="fas fa-arrow-left"></i> All gallery folders</a>
                        <span>· <?php echo count($album_images); ?> photo<?php echo count($album_images) === 1 ? '' : 's'; ?></span></p>
                    <div class="gallery-grid">
                        <?php foreach ($album_images as $i => $item):
                            $file = 'uploads/' . basename($item['file_path']);
                            if (!file_exists($file)) continue; ?>
                            <div class="gallery-tile reveal" data-delay="<?php echo ($i % 4) + 1; ?>">
                                <button type="button"
                                        class="gallery-item js-lightbox"
                                        data-caption="<?php echo htmlspecialchars($item['title'] ?? ''); ?>"
                                        aria-label="View <?php echo htmlspecialchars($item['title'] ?? 'photo'); ?>">
                                    <img src="<?php echo htmlspecialchars($file); ?>" alt="<?php echo htmlspecialchars($item['title'] ?? 'Gallery photo'); ?>" loading="lazy">
                                    <?php if (!empty($item['title'])): ?>
                                        <span class="cap"><?php echo htmlspecialchars($item['title']); ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php if ($gallery_can_download): ?>
                                    <a class="tile-dl" href="download.php?id=<?php echo (int)$item['id']; ?>"
                                       title="Download photo" aria-label="Download <?php echo htmlspecialchars($item['title'] ?? 'photo'); ?>">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($albums) && $general_count === 0): ?>
                <div class="empty reveal">
                    <i class="fas fa-images"></i>
                    <h3>No photos yet</h3>
                    <p>Our gallery is being prepared. Check back soon to relive the moments.</p>
                </div>
            <?php else: ?>
                <div class="album-grid">
                    <?php foreach ($albums as $i => $al):
                        $cover = gallery_cover_file($al, $cover_map); ?>
                        <a class="album-card reveal" data-delay="<?php echo ($i % 4) + 1; ?>" href="gallery.php?album=<?php echo (int)$al['id']; ?>">
                            <span class="album-cover">
                                <?php if ($cover): ?>
                                    <img src="<?php echo htmlspecialchars($cover); ?>" alt="<?php echo htmlspecialchars($al['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <span class="album-ph"></span>
                                <?php endif; ?>
                                <span class="album-folder"><i class="fas fa-folder"></i></span>
                                <span class="album-count"><i class="fas fa-camera"></i> <?php echo (int)$al['photo_count']; ?></span>
                                <span class="album-meta">
                                    <b><?php echo htmlspecialchars($al['title']); ?></b>
                                    <?php if (!empty($al['description'])): ?>
                                        <small><?php echo htmlspecialchars(text_excerpt($al['description'], 70)); ?></small>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($general_count > 0): ?>
                        <?php
                        $general_cover = null;
                        try {
                            $stmt = $conn->query("SELECT file_path FROM media WHERE album_id IS NULL AND file_type = 'image' ORDER BY created_at ASC LIMIT 1");
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($row && file_exists('uploads/' . basename($row['file_path']))) {
                                $general_cover = 'uploads/' . basename($row['file_path']);
                            }
                        } catch (Exception $e) { /* keep null */ }
                        ?>
                        <a class="album-card reveal" href="gallery.php?album=0">
                            <span class="album-cover">
                                <?php if ($general_cover): ?>
                                    <img src="<?php echo htmlspecialchars($general_cover); ?>" alt="General photos" loading="lazy">
                                <?php else: ?>
                                    <span class="album-ph"></span>
                                <?php endif; ?>
                                <span class="album-folder"><i class="fas fa-folder"></i></span>
                                <span class="album-count"><i class="fas fa-camera"></i> <?php echo (int)$general_count; ?></span>
                                <span class="album-meta"><b>General Photos</b></span>
                            </span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Lightbox -->
<div class="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <button class="lb-close" aria-label="Close"><i class="fas fa-times"></i></button>
    <figure>
        <img src="" alt="">
        <figcaption></figcaption>
    </figure>
</div>

<?php include 'footer.php'; ?>
