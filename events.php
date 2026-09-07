<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

$page_title = 'Events';
$page_description = 'Upcoming events, worship services and community activities at NUPS-G TTU.';

// Fetch events (don't fail if the database is not set up yet)
$events = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $events = [];
    }
}

// Split into upcoming vs past
$upcoming = [];
$past = [];
$now = new DateTime();
foreach ($events as $event) {
    $d = new DateTime($event['event_date']);
    if ($d >= $now) {
        $upcoming[] = $event;
    } else {
        $past[] = $event;
    }
}

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-calendar-alt"></i> Calendar</span>
            <h1>Events &amp; Programmes</h1>
            <div class="divider"></div>
            <p>Worship with us, fellowship with us. Never miss what God is doing at TTU</p>
        </div>
    </section>

    <style>
        .event-feature { display: grid; grid-template-columns: 1.1fr 1fr; gap: clamp(1.6rem, 4vw, 3rem); align-items: center; }
        .event-feature .card-media { aspect-ratio: 16/10; border-radius: var(--r-lg) 0 0 var(--r-lg); }
        .event-feature .feature-body { padding: clamp(1.4rem, 3vw, 2rem); }
        .event-feature .feature-body h2 { font-size: var(--fs-xl); margin: .6rem 0 .8rem; }
        .event-feature .feature-body p { color: var(--muted); margin-bottom: 1.2rem; }
        .meta-chips { display: flex; flex-wrap: wrap; gap: .5rem 1.4rem; color: var(--muted); font-size: var(--fs-sm); font-weight: 600; margin-bottom: 1.3rem; }
        .meta-chips i { color: var(--gold-dark); margin-right: .35rem; }
        .past-card { display: flex; align-items: center; gap: 1.2rem; padding: 1.1rem 1.4rem; }
        .past-card .card-media { flex: none; width: 110px; aspect-ratio: 1; border-radius: var(--r-sm); }
        .past-card .card-body { padding: 0; flex: 1; }
        .past-card .card-body h3 { font-size: 1.05rem; }
        @media (max-width: 900px) { .event-feature { grid-template-columns: 1fr; } .event-feature .card-media { border-radius: var(--r-lg) var(--r-lg) 0 0; } }
    </style>

    <section class="section">
        <div class="container">
            <?php if (empty($events)): ?>
                <div class="empty reveal">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No events scheduled yet</h3>
                    <p>We're planning something great. Check back soon for upcoming programmes.</p>
                </div>
            <?php else: ?>

                <!-- Next featured event -->
                <?php if (!empty($upcoming)): $feat = $upcoming[0]; $fd = new DateTime($feat['event_date']); ?>
                    <div class="section-head reveal" style="margin-bottom:2.2rem;">
                        <span class="eyebrow">Next Up</span>
                        <h2>Featured Event</h2>
                    </div>
                    <article class="card reveal" style="overflow:hidden;">
                        <div class="event-feature">
                            <div class="card-media">
                                <?php if (!empty($feat['image']) && $feat['image'] !== 'event-default.jpg' && file_exists('uploads/' . $feat['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($feat['image']); ?>" alt="<?php echo htmlspecialchars($feat['title']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-calendar-check icon-ph"></i>
                                <?php endif; ?>
                            </div>
                            <div class="feature-body">
                                <div class="meta-chips">
                                    <span><i class="fas fa-calendar-alt"></i><?php echo $fd->format('l, F j, Y'); ?></span>
                                    <span><i class="fas fa-clock"></i><?php echo $fd->format('g:i A'); ?></span>
                                    <?php if (!empty($feat['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($feat['location']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2><?php echo htmlspecialchars($feat['title']); ?></h2>
                                <p><?php echo nl2br(htmlspecialchars(text_excerpt($feat['description'] ?? '', 320))); ?></p>
                                <?php if (!empty($feat['description']) && text_length($feat['description']) > 320): ?>
                                    <p><strong style="color: var(--primary); font-size:.95rem;">Full details coming soon. Stay tuned!</strong></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endif; ?>

                <!-- Remaining upcoming -->
                <?php if (count($upcoming) > 1): ?>
                    <div class="section-head reveal" style="margin-top:4rem;">
                        <span class="eyebrow">Don't Miss Out</span>
                        <h2>More Upcoming Events</h2>
                    </div>
                    <div class="grid grid-3">
                        <?php foreach (array_slice($upcoming, 1) as $event): $ed = new DateTime($event['event_date']); ?>
                            <article class="card card--hover reveal">
                                <div class="card-media">
                                    <?php if (!empty($event['image']) && $event['image'] !== 'event-default.jpg' && file_exists('uploads/' . $event['image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <i class="fas fa-calendar-alt icon-ph"></i>
                                    <?php endif; ?>
                                    <span class="chip-float"><?php echo $ed->format('M j'); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="card-meta">
                                        <span><i class="fas fa-clock"></i><?php echo $ed->format('D, g:i A'); ?></span>
                                        <?php if (!empty($event['location'])): ?><span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($event['location']); ?></span><?php endif; ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <p><?php echo htmlspecialchars(text_excerpt($event['description'] ?? '', 110)); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Past events -->
                <?php if (!empty($past)): ?>
                    <div class="section-head reveal" style="margin-top:4rem;">
                        <span class="eyebrow">Gallery of Memories</span>
                        <h2>Past Events</h2>
                    </div>
                    <div class="grid" style="max-width:820px; margin-inline:auto;">
                        <?php foreach (array_slice(array_reverse($past), 0, 6) as $event): $pd = new DateTime($event['event_date']); ?>
                            <article class="card card--hover past-card reveal">
                                <div class="card-media">
                                    <?php if (!empty($event['image']) && $event['image'] !== 'event-default.jpg' && file_exists('uploads/' . $event['image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <i class="fas fa-calendar-check icon-ph"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="card-meta"><span><i class="fas fa-calendar-alt"></i><?php echo $pd->format('F j, Y'); ?></span></div>
                                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <?php if (!empty($event['location'])): ?>
                                        <p style="font-size:.88rem;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>

    <section class="section" style="padding-top:0;">
        <div class="container">
            <div class="cta-band reveal">
                <div class="container">
                    <div>
                        <h2>Never miss an event</h2>
                        <p>Subscribe to our updates and be the first to know about services, outreaches and fellowship meetings.</p>
                    </div>
                    <a href="register.php" class="btn btn-gold btn-lg"><i class="fas fa-bell"></i> Get Notified</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
