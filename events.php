<?php
// Load configuration
require_once 'config.php';

$page_title = 'Events';

// Try to connect to database, but don't fail if database doesn't exist
$events = [];
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Database not set up - show empty state
}

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Upcoming Events</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Join us for worship, fellowship, and community activities</p>
    </div>
</section>

<style>
.events-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.events-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}
.event-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.event-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}
.event-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
}
.event-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.event-card:hover .event-image img {
    transform: scale(1.05);
}
.event-content {
    padding: 1.5rem;
}
.event-date {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #1e3c72;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}
.event-content h3 {
    font-size: 1.25rem;
    color: #333;
    margin-bottom: 0.75rem;
}
.event-content p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 1rem;
}
.event-location {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #888;
    font-size: 0.9rem;
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
    .events-list {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="events-section">
    <div class="container">
        <?php if (empty($events)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Events Scheduled</h3>
                <p>Check back soon for upcoming events and activities.</p>
            </div>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <?php if (!empty($event['image']) && file_exists('uploads/' . $event['image'])): ?>
                            <div class="event-image">
                                <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="event-content">
                            <div class="event-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </div>
                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p><?php echo htmlspecialchars($event['description']); ?></p>
                            <?php if (!empty($event['location'])): ?>
                                <div class="event-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </div>
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
