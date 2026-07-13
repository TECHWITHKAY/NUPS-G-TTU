<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$page_title = 'Our Executives';

// Get all executives ordered by position
try {
    $stmt = $conn->query("SELECT * FROM executives ORDER BY order_num ASC");
    $executives = $stmt->fetchAll();
} catch (Exception $e) {
    $executives = [];
}

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Our Church Executives</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Meet the dedicated team serving our congregation</p>
    </div>
</section>

<style>
.executives-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.executives-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}
.executive-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.executive-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}
.executive-image {
    width: 100%;
    height: 280px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}
.executive-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.executive-card:hover .executive-image img {
    transform: scale(1.05);
}
.executive-info {
    padding: 1.5rem;
    text-align: center;
}
.executive-info h3 {
    font-size: 1.25rem;
    color: #1e3c72;
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.executive-info .position {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 1rem;
}
.exec-contact {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f0f4f8;
    color: #1e3c72;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}
.exec-contact:hover {
    background: #1e3c72;
    color: white;
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
    .executives-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}
</style>

<section class="executives-section">
    <div class="container">
        <?php if (empty($executives)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No Executives Found</h3>
                <p>Executive information will be available soon.</p>
            </div>
        <?php else: ?>
            <div class="executives-grid">
                <?php foreach ($executives as $exec): ?>
                    <div class="executive-card">
                        <?php if (!empty($exec['image']) && file_exists('uploads/' . $exec['image'])): ?>
                            <div class="executive-image">
                                <img src="uploads/<?php echo htmlspecialchars($exec['image']); ?>" alt="<?php echo htmlspecialchars($exec['name']); ?>">
                            </div>
                        <?php else: ?>
                            <div class="executive-image">
                                <i class="fas fa-user" style="font-size: 4rem; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="executive-info">
                            <h3><?php echo htmlspecialchars($exec['name']); ?></h3>
                            <p class="position"><?php echo htmlspecialchars($exec['position']); ?></p>
                            <?php if (!empty($exec['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($exec['email']); ?>" class="exec-contact" title="Email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($exec['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($exec['phone']); ?>" class="exec-contact" title="Call">
                                    <i class="fas fa-phone"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
