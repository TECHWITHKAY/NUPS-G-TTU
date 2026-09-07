<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$page_title = 'Blogs & Sermons';
$page_description = 'Articles, sermons and testimonies from the NUPS-G TTU family.';

// Filter: all | article | sermon
$type_filter = $_GET['type'] ?? 'all';
if (!in_array($type_filter, ['all', 'article', 'sermon'], true)) {
    $type_filter = 'all';
}

// Fetch published posts only
$blogs = [];
if ($conn !== null) {
    try {
        if ($type_filter === 'all') {
            $stmt = $conn->query("SELECT * FROM blogs WHERE status = 'published' ORDER BY created_at DESC");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("SELECT * FROM blogs WHERE status = 'published' AND post_type = ? ORDER BY created_at DESC");
            $stmt->execute([$type_filter]);
        }
        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $blogs = [];
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-pen-nib"></i> The Blog</span>
            <h1>Articles &amp; Sermons</h1>
            <div class="divider"></div>
            <p>Insights, sermons and testimonies to encourage your walk with God</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <nav class="blog-tabs reveal in" aria-label="Filter posts" style="display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:2.2rem;">
                <a href="blogs.php" class="btn btn-sm <?php echo $type_filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>"><i class="fas fa-layer-group"></i> All Posts</a>
                <a href="blogs.php?type=article" class="btn btn-sm <?php echo $type_filter === 'article' ? 'btn-primary' : 'btn-outline'; ?>"><i class="fas fa-pen-fancy"></i> Articles</a>
                <a href="blogs.php?type=sermon" class="btn btn-sm <?php echo $type_filter === 'sermon' ? 'btn-primary' : 'btn-outline'; ?>"><i class="fas fa-bible"></i> Sermons</a>
            </nav>

            <?php if (empty($blogs)): ?>
                <div class="empty reveal">
                    <i class="fas fa-book-open"></i>
                    <h3><?php echo $type_filter === 'sermon' ? 'No sermons published yet' : 'No articles published yet'; ?></h3>
                    <p>Encouraging content is on the way. Please visit again soon.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($blogs as $i => $blog): ?>
                        <?php
                        $post_type = $blog['post_type'] ?? 'article';
                        $is_sermon = ($post_type === 'sermon');
                        ?>
                        <article class="card card--hover reveal" data-delay="<?php echo ($i % 3) + 1; ?>">
                            <a href="blog-detail.php?id=<?php echo (int)$blog['id']; ?>" class="card-media" aria-label="Read: <?php echo htmlspecialchars($blog['title']); ?>">
                                <?php
                                $media_type = $blog['media_type'] ?? 'image';
                                $image = $blog['image'] ?? '';
                                if (!empty($image) && file_exists('uploads/' . $image)):
                                    if ($media_type === 'video'):
                                ?>
                                        <video muted playsinline preload="metadata"><source src="uploads/<?php echo htmlspecialchars($image); ?>" type="video/mp4"></video>
                                    <?php else: ?>
                                        <img src="uploads/<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>" loading="lazy">
                                    <?php endif; ?>
                                <?php else: ?>
                                    <i class="fas fa-book-open icon-ph"></i>
                                <?php endif; ?>
                                <?php if ($media_type === 'video'): ?>
                                    <span class="chip-float"><i class="fas fa-video"></i> Video</span>
                                <?php elseif ($is_sermon): ?>
                                    <span class="chip-float"><i class="fas fa-bible"></i> Sermon</span>
                                <?php endif; ?>
                            </a>
                            <div class="card-body">
                                <div class="card-meta">
                                    <span><i class="fas fa-calendar-alt"></i><?php echo formatDate($blog['created_at']); ?></span>
                                    <span style="font-weight:800; color: var(--<?php echo $is_sermon ? 'gold-dark' : 'primary'; ?>);">
                                        <i class="fas fa-<?php echo $is_sermon ? 'bible' : 'pen-fancy'; ?>"></i> <?php echo $is_sermon ? 'Sermon' : 'Article'; ?>
                                    </span>
                                </div>
                                <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                                <?php if ($is_sermon && (!empty($blog['preacher']) || !empty($blog['scripture_ref']))): ?>
                                    <p class="sermon-byline" style="margin:-0.4rem 0 .7rem; font-size:.86rem; font-weight:700; color: var(--gold-dark);">
                                        <?php if (!empty($blog['preacher'])): ?><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($blog['preacher']); ?><?php endif; ?>
                                        <?php if (!empty($blog['preacher']) && !empty($blog['scripture_ref'])): ?> · <?php endif; ?>
                                        <?php if (!empty($blog['scripture_ref'])): ?><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($blog['scripture_ref']); ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <p><?php echo htmlspecialchars(text_excerpt(strip_tags($blog['content'] ?? ''), 150)); ?></p>
                                <div class="card-link">
                                    <a href="blog-detail.php?id=<?php echo (int)$blog['id']; ?>" class="btn-text">Read More <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
