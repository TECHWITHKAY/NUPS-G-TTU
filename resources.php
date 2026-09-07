<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$can_download = isLoggedIn();

$page_title = 'Resources';
$page_description = 'Download the union constitution, hymn book and other official documents of NUPS-G TTU.';

// Group resources by category so members can find what they need quickly.
$groups = [];
$total_resources = 0;
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM resources ORDER BY category ASC, created_at DESC, id DESC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groups[$row['category']][] = $row;
            $total_resources++;
        }
    } catch (Exception $e) {
        $groups = [];
        $total_resources = 0;
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-file-download"></i> Member Library</span>
            <h1>Resources</h1>
            <div class="divider"></div>
            <p>Official documents of NUPS-G TTU: the union constitution, hymn book, handbooks and more, ready for you to download.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if (!$can_download): ?>
                <div class="gallery-note reveal">
                    <i class="fas fa-lock"></i>
                    <p><b>Downloads are for members.</b><br>Browse the library freely. Log in to download any document.</p>
                    <a class="note-cta" href="login.php">Log In <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>

            <?php if ($total_resources === 0): ?>
                <div class="empty reveal">
                    <i class="fas fa-book-open"></i>
                    <h3>No resources uploaded yet</h3>
                    <p>The library is being prepared. The union constitution, hymn book and other documents will appear here soon.</p>
                </div>
            <?php else: ?>

                <?php if (count($groups) > 1): ?>
                    <div class="res-chips reveal" role="list" aria-label="Resource categories">
                        <?php $cat_i = 0; foreach ($groups as $category => $items): ?>
                            <a class="res-chip" href="#cat-<?php echo $cat_i; ?>" role="listitem">
                                <?php echo htmlspecialchars($category); ?>
                                <span><?php echo count($items); ?></span>
                            </a>
                        <?php $cat_i++; endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $cat_i = 0; foreach ($groups as $category => $items): ?>
                    <div class="res-group reveal" id="cat-<?php echo $cat_i; ?>">
                        <div class="res-group-head">
                            <h2><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($category); ?></h2>
                            <span><?php echo count($items); ?> document<?php echo count($items) === 1 ? '' : 's'; ?></span>
                        </div>

                        <?php foreach ($items as $i => $row):
                            $ico = resource_file_icon($row['file_name']);
                            $exists = file_exists('uploads/' . basename($row['file_path'])); ?>
                            <div class="res-card" data-delay="<?php echo (($cat_i + $i) % 4) + 1; ?>">
                                <span class="res-ico" style="color: <?php echo $ico['color']; ?>; background: <?php echo $ico['color']; ?>1a;">
                                    <i class="fas <?php echo $ico['icon']; ?>"></i>
                                </span>
                                <div class="res-body">
                                    <b><?php echo htmlspecialchars($row['title']); ?></b>
                                    <?php if (!empty($row['description'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                    <?php endif; ?>
                                    <small>
                                        <span><?php echo $ico['label']; ?></span>
                                        <span class="res-dot">&middot;</span>
                                        <span><?php echo human_filesize($row['file_size'] ?? 0); ?></span>
                                        <span class="res-dot">&middot;</span>
                                        <span>Added <?php echo formatDate($row['created_at']); ?></span>
                                        <?php if ((int)($row['downloads'] ?? 0) > 0): ?>
                                            <span class="res-dot">&middot;</span>
                                            <span><i class="fas fa-download"></i> <?php echo (int)$row['downloads']; ?></span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="res-act">
                                    <?php if (!$exists): ?>
                                        <span class="res-miss" title="This file is temporarily unavailable"><i class="fas fa-exclamation-triangle"></i> Unavailable</span>
                                    <?php elseif ($can_download): ?>
                                        <?php if (strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                            <a class="btn btn-outline btn-sm res-dl" href="resource-view.php?id=<?php echo (int)$row['id']; ?>" target="_blank" rel="noopener" title="Preview in your browser">
                                                <i class="fas fa-eye"></i> Preview
                                            </a>
                                        <?php endif; ?>
                                        <a class="btn btn-gold btn-sm res-dl" href="resource-download.php?id=<?php echo (int)$row['id']; ?>">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-outline btn-sm res-dl" href="login.php" title="Members only. Log in to download">
                                            <i class="fas fa-lock"></i> Members Only
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php $cat_i++; endforeach; ?>

            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
