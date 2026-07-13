<?php
// Load configuration
require_once 'config.php';

$page_title = 'Blogs';

// Try to connect to database
$blogs = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT * FROM blogs ORDER BY created_at DESC");
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Database not set up
}

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Blog Posts</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Insights, testimonies, and spiritual content</p>
    </div>
</section>

<style>
.blogs-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.blogs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}
.blog-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.blog-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}
.blog-image {
    width: 100%;
    height: 220px;
    overflow: hidden;
}
.blog-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.blog-card:hover .blog-image img {
    transform: scale(1.05);
}
.blog-content {
    padding: 1.5rem;
}
.blog-date {
    color: #1e3c72;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.75rem;
}
.blog-content h3 {
    font-size: 1.25rem;
    color: #333;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}
.blog-content p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 1rem;
}
.read-more {
    display: inline-block;
    color: #1e3c72;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.3s ease;
}
.read-more:hover {
    color: #2a5298;
}
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    grid-column: 1 / -1;
}
.empty-state i {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1.5rem;
}
.empty-state h3 {
    font-size: 1.5rem;
    color: #333;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #888;
}
@media (max-width: 768px) {
    .blogs-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="blogs-section">
    <div class="container">
        <?php if (empty($blogs)): ?>
            <div class="empty-state">
                <i class="fas fa-blog"></i>
                <h3>No Blog Posts Yet</h3>
                <p>Check back soon for new posts.</p>
            </div>
        <?php else: ?>
            <div class="blogs-grid">
                <?php foreach ($blogs as $blog): ?>
                    <article class="blog-card">
                        <?php if (!empty($blog['image']) && file_exists('uploads/' . $blog['image'])): ?>
                            <div class="blog-image">
                                <img src="uploads/<?php echo htmlspecialchars($blog['image']); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="blog-content">
                            <div class="blog-date">
                                <?php echo date('F j, Y', strtotime($blog['created_at'])); ?>
                            </div>
                            <h3><?php echo htmlspecialchars($blog['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($blog['content'], 0, 150)); ?>...</p>
                            <a href="blog-detail.php?id=<?php echo $blog['id']; ?>" class="read-more">Read More</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
