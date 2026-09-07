<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$page_title = 'Our Executives';
$page_description = 'Meet the executive team serving the NUPS-G TTU congregation.';

// Get all executives ordered by position
$executives = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM executives ORDER BY order_num ASC, id ASC");
        $executives = $stmt->fetchAll();
    } catch (Exception $e) {
        $executives = [];
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-user-tie"></i> Leadership</span>
            <h1>Our Executives</h1>
            <div class="divider"></div>
            <p>Meet the dedicated team serving our congregation this term</p>
        </div>
    </section>

    <style>
        .exec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr)); gap: clamp(1.2rem, 3vw, 1.8rem); }
        .exec-card .card-media { aspect-ratio: 4 / 5; }
        .exec-card .card-media .icon-ph { font-size: 4rem; color: rgba(255,255,255,0.7); }
        .exec-card .card-body { text-align: center; align-items: center; }
        .exec-card .card-body h3 { font-size: 1.15rem; }
        .exec-role { display: inline-block; background: var(--sky); color: var(--primary); font-weight: 700; font-size: var(--fs-xs); letter-spacing: 0.05em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: var(--r-pill); }
        .exec-bio { font-size: 0.92rem; }
        .exec-links { display: flex; gap: 0.6rem; margin-top: 0.4rem; }
        .exec-links a { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; border: 1.5px solid var(--line); color: var(--primary); transition: all .25s var(--ease); }
        .exec-links a:hover { background: var(--primary); border-color: var(--primary); color: #fff; transform: translateY(-3px); }
    </style>

    <section class="section">
        <div class="container">
            <?php if (empty($executives)): ?>
                <div class="empty reveal">
                    <i class="fas fa-users"></i>
                    <h3>Executive list coming soon</h3>
                    <p>Our new leadership team will be announced shortly. Please check back!</p>
                </div>
            <?php else: ?>
                <div class="exec-grid">
                    <?php foreach ($executives as $i => $exec): ?>
                        <article class="card card--hover exec-card reveal" data-delay="<?php echo ($i % 4) + 1; ?>">
                            <div class="card-media">
                                <?php if (!empty($exec['image']) && file_exists('uploads/' . $exec['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($exec['image']); ?>" alt="<?php echo htmlspecialchars($exec['name']); ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-user-circle icon-ph"></i>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h3><?php echo htmlspecialchars($exec['name']); ?></h3>
                                <span class="exec-role"><?php echo htmlspecialchars($exec['position']); ?></span>
                                <?php if (!empty($exec['bio'])): ?>
                                    <p class="exec-bio"><?php echo htmlspecialchars(text_excerpt($exec['bio'] ?? '', 130)); ?></p>
                                <?php endif; ?>
                                <div class="exec-links">
                                    <?php if (!empty($exec['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($exec['email']); ?>" title="Email" aria-label="Email <?php echo htmlspecialchars($exec['name']); ?>"><i class="fas fa-envelope"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($exec['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($exec['phone']); ?>" title="Call" aria-label="Call <?php echo htmlspecialchars($exec['name']); ?>"><i class="fas fa-phone-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($exec['whatsapp'])): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $exec['whatsapp']); ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp <?php echo htmlspecialchars($exec['name']); ?>"><i class="fab fa-whatsapp"></i></a>
                                    <?php endif; ?>
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
