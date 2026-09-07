<?php
require_once 'config.php';
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ? AND status = 'published'");
$stmt->execute([$id]);
$blog = $stmt->fetch();

if (!$blog) {
    redirect('blogs.php');
}

$page_title = $blog['title'];
$page_description = text_excerpt(strip_tags($blog['content'] ?? ''), 155);

$media_type = $blog['media_type'] ?? 'image';
$post_type = $blog['post_type'] ?? 'article';
$is_sermon = ($post_type === 'sermon');
$audio_file = $blog['audio_file'] ?? '';
$audio_url = $blog['audio_url'] ?? '';
$image = $blog['image'] ?? '';
$share_url = SITE_URL . '/blog-detail.php?id=' . (int)$blog['id'];
$share_text = urlencode($blog['title'] . ' - read this on the NUPS-G TTU blog');

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="index.php">Home</a><i class="fas fa-chevron-right"></i>
                <a href="blogs.php">Blogs</a><i class="fas fa-chevron-right"></i>
                <span><?php echo $is_sermon ? 'Sermon' : 'Article'; ?></span>
            </nav>
            <span class="eyebrow"><i class="fas fa-<?php echo $is_sermon ? 'bible' : 'pen-nib'; ?>"></i> <?php echo $media_type === 'video' ? 'Video Devotional' : ($is_sermon ? 'Sermon' : 'Article'); ?></span>
            <h1><?php echo htmlspecialchars($blog['title']); ?></h1>
            <div class="divider"></div>
            <p style="font-size: .98rem;">
                <i class="fas fa-calendar-alt"></i> <?php echo formatDate($blog['created_at']); ?>
            </p>
            <?php if ($is_sermon && (!empty($blog['preacher']) || !empty($blog['scripture_ref']))): ?>
            <p style="font-size: 1rem; font-weight:700; color: var(--gold); margin-top:.5rem;">
                <?php if (!empty($blog['preacher'])): ?><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($blog['preacher']); ?><?php endif; ?>
                <?php if (!empty($blog['preacher']) && !empty($blog['scripture_ref'])): ?> &nbsp;·&nbsp; <?php endif; ?>
                <?php if (!empty($blog['scripture_ref'])): ?><i class="fas fa-book-open"></i> <em><?php echo htmlspecialchars($blog['scripture_ref']); ?></em><?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <style>
        .article { max-width: 860px; margin-inline: auto; }
        .article-media { border-radius: var(--r-lg); overflow: hidden; box-shadow: var(--shadow-lg); margin-bottom: 2rem; background: linear-gradient(135deg, var(--navy-800), var(--secondary)); display: flex; align-items: center; justify-content: center; }
        .article-media img { width: 100%; max-height: 520px; object-fit: cover; }
        .article-media video { width: 100%; max-height: 520px; }
        .article-media i { font-size: 5rem; color: rgba(255,255,255,.8); padding: 3rem 0; }
        .article-body { color: var(--text); font-size: 1.07rem; line-height: 2; }
        .article-body p { margin-bottom: 1.4rem; }
        .share-row { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; border-top: 1px solid var(--line); padding-top: 1.4rem; margin-top: 2.4rem; }
        .share-row b { font-family: var(--font-body); color: var(--ink); margin-right: .4rem; }
        .share-row a, .share-row button { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; border: 1.5px solid var(--line); color: var(--primary); font-size: .98rem; transition: all .25s var(--ease); background: #fff; }
        .share-row a:hover, .share-row button:hover { background: var(--primary); border-color: var(--primary); color: #fff; transform: translateY(-3px); }
    </style>

    <section class="section">
        <div class="container">
            <article class="article">
                <div class="article-media reveal">
                    <?php
                    if (!empty($image) && file_exists('uploads/' . $image)):
                        if ($media_type === 'video'):
                    ?>
                        <video controls playsinline preload="metadata">
                            <source src="uploads/<?php echo htmlspecialchars($image); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php else: ?>
                        <img src="uploads/<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>">
                    <?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-book-open"></i>
                    <?php endif; ?>
                </div>

                <?php if ($is_sermon && ($audio_file !== '' || $audio_url !== '')): ?>
                <div class="listen-box reveal" data-delay="1" style="background: linear-gradient(135deg, var(--navy-800), var(--secondary)); border-radius: var(--r-lg); padding: 1.6rem 1.8rem; margin-bottom: 2rem; color: #fff;">
                    <p style="font-family: var(--font-body); font-weight:800; letter-spacing:.04em; text-transform:uppercase; font-size:.82rem; color: var(--gold); margin-bottom:.9rem;"><i class="fas fa-headphones"></i> Listen to this sermon <span id="playCountBadge" style="float:right; font-weight:700; text-transform:none; letter-spacing:0; font-size:.78rem; color: rgba(255,255,255,.85);"><i class="fas fa-play"></i> <?php echo (int)($blog['play_count'] ?? 0) > 0 ? (int)$blog['play_count'] . ' ' . ((int)$blog['play_count'] === 1 ? 'listen' : 'listens') : 'be the first to listen'; ?></span></p>
                    <?php if ($audio_file !== '' && is_file('uploads/' . basename($audio_file))): ?>
                        <audio id="sermonAudio" controls preload="metadata" style="width:100%; height:44px;">
                            <source src="uploads/<?php echo htmlspecialchars(basename($audio_file)); ?>">
                            Your browser does not support the audio element.
                        </audio>
                    <?php elseif ($audio_url !== ''): ?>
                        <?php
                        // Normalise a pasted YouTube URL into an embeddable nocookie URL
                        $embed = '';
                        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([\w-]{6,20})~i', $audio_url, $m)) {
                            $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
                        }
                        if ($embed !== ''):
                        ?>
                        <div style="position:relative; padding-top:56.25%; border-radius:12px; overflow:hidden;">
                            <iframe id="sermonYt" src="<?php echo htmlspecialchars($embed); ?>" title="Sermon audio player" style="position:absolute; inset:0; width:100%; height:100%; border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            <div id="ytPlayOverlay" title="Play and count this listen" style="position:absolute; inset:0; cursor:pointer;"></div>
                        </div>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($audio_url); ?>" target="_blank" rel="noopener" id="sermonYtLink" style="color: var(--gold); font-weight:700;">Open the sermon recording <i class="fas fa-external-link-alt"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="article-body reveal" data-delay="1">
                    <?php echo nl2br(htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8')); ?>
                </div>

                <div class="share-row reveal">
                    <b><i class="fas fa-share-alt" style="color: var(--gold-dark);"></i> Share</b>
                    <a href="https://wa.me/?text=<?php echo $share_text . '%20' . urlencode($share_url); ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo $share_text; ?>&url=<?php echo urlencode($share_url); ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fab fa-x-twitter"></i></a>
                    <button type="button" class="js-copy-link" data-link="<?php echo htmlspecialchars($share_url); ?>" aria-label="Copy link"><i class="fas fa-link"></i></button>
                    <a href="blogs.php" style="margin-left:auto; width:auto; padding:0 1.3rem; border-radius: var(--r-pill);" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Posts</a>
                </div>
            </article>

            <div class="cta-band mt-3 reveal">
                <div class="container">
                    <div>
                        <h2>Enjoyed this article?</h2>
                        <p>Join the NUPS-G family and grow with us in faith, fellowship and purpose.</p>
                    </div>
                    <div style="display:flex; gap:.8rem; flex-wrap:wrap;">
                        <a href="register.php" class="btn btn-gold"><i class="fas fa-user-plus"></i> Join Us</a>
                        <a href="blogs.php" class="btn btn-glass">More <?php echo $is_sermon ? 'Sermons' : 'Articles'; ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    document.querySelectorAll('.js-copy-link').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var link = btn.getAttribute('data-link');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(link).then(function () { window.toast('Link copied to clipboard!'); });
            } else {
                var t = document.createElement('textarea');
                t.value = link; document.body.appendChild(t); t.select();
                try { document.execCommand('copy'); window.toast('Link copied to clipboard!'); } catch (e) {}
                t.remove();
            }
        });
    });

    // ---- Sermon play counting ------------------------------------------
    var sermonId = <?php echo (int)$blog['id']; ?>;
    var counted = false;

    function updateBadge(plays) {
        var badge = document.getElementById('playCountBadge');
        if (badge && plays > 0) {
            badge.innerHTML = '<i class="fas fa-play"></i> ' + plays + (plays === 1 ? ' listen' : ' listens');
        }
    }

    function countPlay() {
        if (counted) return;
        counted = true;
        var body = new URLSearchParams();
        body.append('id', sermonId);
        fetch('sermon_play.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            keepalive: true
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (data.ok) updateBadge(data.plays); })
        .catch(function () {});
    }

    // Uploaded audio: count when playback actually starts
    var audio = document.getElementById('sermonAudio');
    if (audio) {
        audio.addEventListener('play', countPlay);
    }

    // YouTube embed: overlay click counts a listen, then passes the click through
    var overlay = document.getElementById('ytPlayOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            countPlay();
            overlay.remove();
        });
    }

    // External recording link: count on click
    var extLink = document.getElementById('sermonYtLink');
    if (extLink) {
        extLink.addEventListener('click', countPlay);
    }
})();
</script>

<?php include 'footer.php'; ?>
