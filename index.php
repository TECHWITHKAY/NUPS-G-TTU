<?php
require_once 'config.php';
require_once 'db.php';

$page_title = 'Home';
$page_description = 'NUPS-G at Takoradi Technical University: a vibrant fellowship of Presbyterian students growing in faith through worship, Bible study, events and community service.';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$user_name = $logged_in ? ($_SESSION['user_name'] ?? 'Member') : '';

// Upcoming events (for cards + hero spotlight)
$upcoming_events = [];
$next_event = null;
$event_count = 0;
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3");
        $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 1");
        $next_event = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $conn->query("SELECT COUNT(*) FROM events");
        $event_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $upcoming_events = [];
        $next_event = null;
        $event_count = 0;
    }
}

// Published posts (mixed feed) - always make room for the newest sermon
$blogs = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT * FROM blogs WHERE status = 'published' ORDER BY created_at DESC LIMIT 4");
        $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Guarantee the newest sermon is featured even if it is not among the latest posts
        $has_sermon = false;
        foreach ($blogs as $b) {
            if (($b['post_type'] ?? 'article') === 'sermon') { $has_sermon = true; break; }
        }
        if (!$has_sermon) {
            $stmt = $conn->query("SELECT * FROM blogs WHERE status = 'published' AND post_type = 'sermon' ORDER BY created_at DESC LIMIT 1");
            $latest_sermon = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($latest_sermon) {
                $blogs[] = $latest_sermon;
                usort($blogs, function ($a, $b) {
                    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
                });
                array_splice($blogs, 4);
            }
        }
    } catch (Exception $e) {
        $blogs = [];
    }
}

// Counts for the stats band
$member_count = 0;
$blog_count = 0;
$exec_count = 0;
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
        $member_count = (int)$stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'");
        $blog_count = (int)$stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) FROM executives");
        $exec_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // keep zeros
    }
}

// Approved member reviews (shown as testimonials)
$approved_reviews = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query(
            "SELECT r.rating, r.content, u.first_name, u.last_name, u.created_at AS member_since
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.status = 'approved'
             ORDER BY r.approved_at DESC
             LIMIT 3"
        );
        $approved_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $approved_reviews = [];
    }
}

// Quiz leaderboard
$leaderboard = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("SELECT id, first_name, last_name, total_points FROM users WHERE role = 'member' AND total_points > 0 ORDER BY total_points DESC LIMIT 5");
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $leaderboard = [];
    }
}

// Monthly quiz champion + this month's standings
$this_month_key = date('Y-m');
$last_month_key = date('Y-m', strtotime('-1 month'));
$monthly_champion = null;
$this_month_top = [];
if ($conn !== null) {
    $monthly_champion = ensure_monthly_winner($conn, $last_month_key);
    $this_month_top = monthly_quiz_top($conn, $this_month_key, 3);
}

// Quiz availability (honours admin-scheduled windows; open when unset)
$quiz_available = true;
$next_quiz_time = null;
try {
    $stmt = $conn->query("SELECT * FROM quiz_settings ORDER BY id DESC LIMIT 1");
    $quiz_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($quiz_settings) {
        $quiz_start = new DateTime($quiz_settings['quiz_start']);
        $duration = (int)$quiz_settings['quiz_duration_hours'];
        $quiz_end = clone $quiz_start;
        $quiz_end->modify("+{$duration} hours");
        $now = new DateTime();
        $quiz_available = (bool)$quiz_settings['is_active'] && $now >= $quiz_start && $now <= $quiz_end;
        if (!$quiz_available) {
            $next_quiz_time = $now < $quiz_start ? $quiz_start : $quiz_end;
        }
    }
} catch (Exception $e) {
    $quiz_available = true;
}

include 'header.php';
?>

<main>
    <!-- ============ HERO ============ -->
    <section class="hero">
        <div class="container">
            <?php if ($logged_in): ?>
                <div class="hero-card reveal in">
                    <span class="eyebrow"><i class="fas fa-pray"></i> Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                    <h1>Glad to see you again!</h1>
                    <p style="color: rgba(255,255,255,0.85); margin-bottom: 1.6rem;">"For to me, to live is Christ and to die is gain." - Philippians 1:21</p>

                    <?php if ($next_event): ?>
                        <div class="mini-event">
                            <?php
                            $d = new DateTime($next_event['event_date']);
                            ?>
                            <span class="date-chip"><span class="d"><?php echo $d->format('j'); ?></span><span class="m"><?php echo $d->format('M'); ?></span></span>
                            <div>
                                <h3><?php echo htmlspecialchars($next_event['title']); ?></h3>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($next_event['location'] ?: 'TTU Campus'); ?></p>
                            </div>
                            <a href="events.php" class="btn btn-gold btn-sm">Details</a>
                        </div>
                    <?php else: ?>
                        <div class="hero-actions">
                            <a href="events.php" class="btn btn-gold"><i class="fas fa-calendar-alt"></i> Browse Events</a>
                            <?php if (isAdmin()): ?>
                                <a href="admin/dashboard.php#quiz" class="btn btn-glass"><i class="fas fa-plus-circle"></i> Manage Quiz</a>
                            <?php else: ?>
                                <a href="quiz.php" class="btn btn-glass"><i class="fas fa-star"></i> Daily Quiz</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h1>Welcome to <em style="color: var(--gold); font-style: normal;">NUPS-G</em> Church</h1>
                <p class="hero-sub">A community of faith, fellowship and purpose on the TTU campus.</p>
                <div class="hero-actions">
                    <a href="register.php" class="btn btn-gold btn-lg"><i class="fas fa-user-plus"></i> Join Us Today</a>
                    <a href="events.php" class="btn btn-glass btn-lg"><i class="fas fa-calendar-alt"></i> View Events</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ MOTTO BAND ============ -->
    <section class="motto-band">
        <div class="container">
            <p class="motto-verse"><i class="fas fa-cross"></i> For to me, to live is Christ and to die is gain - <em>Phil. 1:21</em></p>
            <div class="motto-chips">
                <div class="motto-chip"><b>NUPS-G</b><span>For Christ</span></div>
                <div class="motto-chip"><b>For Christ</b><span>We Live</span></div>
                <div class="motto-chip"><b>NUPS-G Special</b><span>Divine Manifestors</span></div>
                <div class="motto-chip"><b>Irresistible</b><span>Solution Carriers</span></div>
            </div>
        </div>
    </section>

    <!-- ============ MISSION & VISION ============ -->
    <section class="mission-band">
        <div class="container">
            <div class="section-head section-head--dark reveal">
                <span class="eyebrow">Who We Are</span>
                <h2>Our Mission &amp; Vision</h2>
                <div class="divider"></div>
                <p>Rooted in the Word, driven by love, and raised to lead.</p>
            </div>
            <div class="mission-grid">
                <article class="mission-card reveal" data-delay="1">
                    <div class="icon"><i class="fas fa-bullseye"></i></div>
                    <h3>Our Mission</h3>
                    <p>To train and equip student members to become active participants in church services, fostering spiritual growth and leadership through biblical teaching, fellowship, and community service.</p>
                </article>
                <article class="mission-card reveal" data-delay="2">
                    <div class="icon"><i class="fas fa-eye"></i></div>
                    <h3>Our Vision</h3>
                    <p>To be a vibrant union of Presbyterian students in Ghana, transforming lives through the Gospel of Christ and producing leaders who impact the church and society.</p>
                </article>
                <article class="mission-card reveal" data-delay="3">
                    <div class="icon"><i class="fas fa-heart"></i></div>
                    <h3>Our Values</h3>
                    <p>Faith, Unity, Service, and Excellence. We believe in the power of God's word to transform lives and the joy of fellowship among believers.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ TIMELINE ============ -->
    <section class="section section--soft">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Our Journey</span>
                <h2>Milestones in Our History</h2>
                <p>Over sixty years of ministry and service across Ghana's campuses</p>
            </div>
            <div class="mini-timeline reveal">
                <?php
                $milestones = [
                    ['1959', 'The Beginning', 'NUPS-G began at Akuafo Hall Chapel of the University of Ghana with just 39 students.'],
                    ['1960', 'Inaugural Ceremony', 'Official inauguration held at Legon Hall, with Rev. W.S. Robertson as chairman.'],
                    ['1962', 'National Expansion', 'First National Conference at Commonwealth Hall. The vision spreads to other institutions.'],
                    ['1985', 'Leadership Training', 'First leadership training programme held at Trinity College for coordinating branches.'],
                    ['Today', 'Continuing Legacy', 'NUPS-G keeps growing and impacting lives through programmes and community service.'],
                ];
                foreach ($milestones as $i => $m):
                ?>
                    <div class="tl-item">
                        <span class="tl-year"><?php echo $m[0]; ?></span>
                        <div class="tl-card">
                            <h4><?php echo $m[1]; ?></h4>
                            <p><?php echo $m[2]; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-2 reveal">
                <a href="history.php" class="btn btn-primary">Read Full History <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- ============ WHAT WE OFFER ============ -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">What We Offer</span>
                <h2>Grow With Us</h2>
                <p>Join our community and experience the many blessings of being part of NUPS-G Church</p>
            </div>
            <div class="grid grid-4">
                <article class="card card--hover feature-card reveal" data-delay="1">
                    <div class="icon"><i class="fas fa-bible"></i></div>
                    <h3>Biblical Teaching</h3>
                    <p>Weekly sermons and Bible studies that help you grow in faith and understanding of God's word.</p>
                </article>
                <article class="card card--hover feature-card reveal" data-delay="2">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h3>Fellowship</h3>
                    <p>Connect with other believers through our vibrant groups and fellowship programmes.</p>
                </article>
                <article class="card card--hover feature-card reveal" data-delay="3">
                    <div class="icon"><i class="fas fa-hands-helping"></i></div>
                    <h3>Community Service</h3>
                    <p>Serve alongside us as we make a positive impact in the lives of others, near and far.</p>
                </article>
                <article class="card card--hover feature-card reveal" data-delay="4">
                    <div class="icon"><i class="fas fa-praying-hands"></i></div>
                    <h3>Prayer Support</h3>
                    <p>Our prayer team is always available to stand with you and support you in every season.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ STATS ============ -->
    <section class="section section--mist">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Our Community at a Glance</span>
                <h2>Church Statistics</h2>
            </div>
            <div class="grid grid-4 reveal">
                <div class="stat"><div class="num">60+</div><div class="lbl">Years of Ministry</div></div>
                <div class="stat"><div class="num"><?php echo (int)$member_count; ?></div><div class="lbl">Registered Members</div></div>
                <div class="stat"><div class="num"><?php echo (int)$event_count; ?></div><div class="lbl">Events Hosted</div></div>
                <div class="stat"><div class="num"><?php echo (int)$blog_count; ?></div><div class="lbl">Articles Published</div></div>
            </div>
        </div>
    </section>

    <!-- ============ UPCOMING EVENTS ============ -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Don't Miss Out</span>
                <h2>Upcoming Events</h2>
                <p>Worship, fellowship and community activities waiting for you</p>
            </div>
            <?php if (empty($upcoming_events)): ?>
                <div class="empty reveal">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>No upcoming events yet</h3>
                    <p>We're planning something great. Check back soon!</p>
                    <a href="events.php" class="btn btn-primary">Explore the Calendar</a>
                </div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($upcoming_events as $event): $ed = new DateTime($event['event_date']); ?>
                        <article class="card card--hover reveal">
                            <div class="card-media">
                                <?php if (!empty($event['image']) && $event['image'] !== 'event-default.jpg' && file_exists('uploads/' . $event['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-calendar-check icon-ph"></i>
                                <?php endif; ?>
                                <span class="chip-float"><?php echo $ed->format('M j'); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="card-meta">
                                    <span><i class="fas fa-clock"></i><?php echo $ed->format('D, g:i A'); ?></span>
                                    <?php if (!empty($event['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($event['location']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p><?php echo htmlspecialchars(text_excerpt($event['description'] ?? '', 110)); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-2 reveal">
                    <a href="events.php" class="btn btn-outline">View All Events <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ LATEST BLOGS ============ -->
    <section class="section section--soft">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">From the Blog</span>
                <h2>Articles &amp; Sermons</h2>
                <p>Read inspiring stories, sermons and spiritual teachings</p>
            </div>
            <?php if (empty($blogs)): ?>
                <div class="empty reveal">
                    <i class="fas fa-book-open"></i>
                    <h3>No articles yet</h3>
                    <p>Encouraging words are on the way. Please visit again soon.</p>
                    <a href="blogs.php" class="btn btn-primary">Browse Blogs</a>
                </div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($blogs as $blog): ?>
                        <?php $is_sermon = ($blog['post_type'] ?? 'article') === 'sermon'; ?>
                        <article class="card card--hover reveal">
                            <a href="blog-detail.php?id=<?php echo (int)$blog['id']; ?>" class="card-media" style="cursor:pointer;">
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
                                    <p style="margin:-0.3rem 0 .6rem; font-size:.85rem; font-weight:700; color: var(--gold-dark);">
                                        <?php if (!empty($blog['preacher'])): ?><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($blog['preacher']); ?><?php endif; ?>
                                        <?php if (!empty($blog['preacher']) && !empty($blog['scripture_ref'])): ?> · <?php endif; ?>
                                        <?php if (!empty($blog['scripture_ref'])): ?><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($blog['scripture_ref']); ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <p><?php echo htmlspecialchars(text_excerpt(strip_tags($blog['content'] ?? ''), 120)); ?></p>
                                <div class="card-link">
                                    <a href="blog-detail.php?id=<?php echo (int)$blog['id']; ?>" class="btn-text"><?php echo $is_sermon ? 'Read Sermon' : 'Read Article'; ?> <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-2 reveal">
                    <a href="blogs.php" class="btn btn-outline">View All Blogs <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============ QUIZ LEADERBOARD (single quiz section + one CTA) ============ -->
    <section class="section" id="quiz">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow"><i class="fas fa-trophy" style="color: var(--gold-dark);"></i> Daily Bible Quiz</span>
                <h2>Quiz Leaderboard</h2>
                <p>Test your biblical knowledge daily and climb the leaderboard</p>
            </div>

            <?php if ($monthly_champion || !empty($this_month_top)): ?>
            <div class="month-grid reveal">
                <?php if ($monthly_champion): ?>
                <div class="month-champ">
                    <div class="champ-crown"><i class="fas fa-crown"></i></div>
                    <div>
                        <span class="champ-label"><i class="fas fa-medal"></i> <?php echo month_label($last_month_key); ?> Champion</span>
                        <h3><?php echo htmlspecialchars($monthly_champion['first_name'] . ' ' . substr($monthly_champion['last_name'], 0, 1) . '.'); ?></h3>
                        <p><i class="fas fa-star"></i> <?php echo (int)$monthly_champion['total_points']; ?> pts in <?php echo month_label($last_month_key); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($this_month_top)): ?>
                <div class="month-top">
                    <h3><i class="fas fa-trophy"></i> This Month's Top 3</h3>
                    <ol>
                        <?php foreach ($this_month_top as $i => $m): ?>
                        <li class="<?php echo $i === 0 ? 'gold' : ($i === 1 ? 'silver' : 'bronze'); ?>">
                            <span class="pos"><?php echo $i + 1; ?></span>
                            <span class="who"><?php echo htmlspecialchars($m['first_name'] . ' ' . substr($m['last_name'], 0, 1) . '.'); ?></span>
                            <span class="pts"><?php echo (int)$m['total']; ?> pts</span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$quiz_available && $next_quiz_time): ?>
                <div class="quiz-banner reveal">
                    <i class="fas fa-hourglass-half"></i>
                    The quiz is closed right now. It reopens in
                    <span class="countdown" data-countdown="<?php echo $next_quiz_time->format('c'); ?>"></span>
                </div>
            <?php endif; ?>

            <?php if (empty($leaderboard)): ?>
                <div class="empty reveal">
                    <i class="fas fa-trophy"></i>
                    <h3>The leaderboard is waiting</h3>
                    <p>Be the first to take the quiz and claim the top spot!</p>
                </div>
            <?php else: ?>
                <?php if (count($leaderboard) >= 2): ?>
                    <?php
                    $top3 = array_slice($leaderboard, 0, 3);
                    $maxPoints = max(array_column($top3, 'total_points'));
                    if ($maxPoints <= 0) $maxPoints = 1;
                    $displayOrder = [1, 0, 2]; // 2nd, 1st, 3rd
                    ?>
                    <div class="podium reveal">
                        <?php foreach ($displayOrder as $idx):
                            if (!isset($top3[$idx])) continue;
                            $u = $top3[$idx];
                            $height = max(84, round(190 * $u['total_points'] / $maxPoints));
                        ?>
                            <div class="podium-col place-<?php echo $idx + 1; ?>">
                                <div class="podium-bar" style="height: <?php echo $height; ?>px;">
                                    <span class="medal"><?php if ($idx === 0): ?><i class="fas fa-crown"></i><?php else: ?><?php echo $idx + 1; ?><?php endif; ?></span>
                                </div>
                                <p class="podium-name"><?php echo htmlspecialchars($u['first_name'] . ' ' . substr($u['last_name'], 0, 1) . '.'); ?><small><?php echo $idx === 0 ? '1st Place' : ($idx === 1 ? '2nd Place' : '3rd Place'); ?></small></p>
                                <span class="podium-pts"><?php echo (int)$u['total_points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="leader-list reveal" style="margin-bottom: 2rem;">
                        <?php foreach ($leaderboard as $index => $u): ?>
                            <div class="leader-row">
                                <span class="rank-dot gold"><i class="fas fa-crown"></i></span>
                                <span class="who"><b><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></b><small>Current leader</small></span>
                                <span class="pts"><?php echo (int)$u['total_points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($leaderboard) > 3): ?>
                    <div class="leader-list reveal">
                        <?php foreach (array_slice($leaderboard, 3) as $index => $u): ?>
                            <div class="leader-row">
                                <span class="rank-dot"><?php echo $index + 4; ?></span>
                                <span class="who"><b><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></b></span>
                                <span class="pts"><?php echo (int)$u['total_points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Share the quiz to the WhatsApp group -->
            <div class="quiz-share reveal">
                <a class="btn btn-whatsapp" href="<?php echo htmlspecialchars(whatsapp_quiz_link()); ?>" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Share Quiz on WhatsApp
                </a>
                <p>Post it in your church group so members can play and climb the leaderboard.</p>
            </div>

            <!-- Single quiz call-to-action -->
            <div class="cta-band mt-3 reveal">
                <div class="container">
                    <div>
                        <?php if (isAdmin()): ?>
                            <h2>Manage the member quiz</h2>
                            <p>Set and schedule the questions. Members play, score and climb the leaderboard.</p>
                        <?php else: ?>
                            <h2><?php echo $quiz_available ? "Ready for today's challenge?" : 'The next quiz is on the way!'; ?></h2>
                            <p><?php echo $quiz_available ? 'Answer questions from the Word, earn points, and watch your name rise to the top of the leaderboard.' : ($next_quiz_time ? 'The quiz reopens in ' . $next_quiz_time->format('M j, g:i A') . ". Mark your calendar." : 'Check back soon for the next quiz challenge.'); ?></p>
                         <?php endif; ?>
                    </div>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php#quiz" class="btn btn-gold btn-lg"><i class="fas fa-plus-circle"></i> Manage Quiz Questions</a>
                    <?php else: ?>
                        <a href="quiz.php" class="btn btn-gold btn-lg"><i class="fas fa-star"></i> <?php echo $quiz_available ? 'Take the Quiz' : 'Open Quiz Page'; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ TESTIMONIALS ============ -->
    <section class="section section--soft">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Testimonials</span>
                <h2>What Our Members Say</h2>
                <p>Real stories from the NUPS-G family</p>
            </div>
            <div class="grid grid-3">
                <?php if (!empty($approved_reviews)): ?>
                    <?php foreach ($approved_reviews as $ri => $rv): ?>
                        <?php
                        $initials = strtoupper(substr($rv['first_name'], 0, 1) . substr($rv['last_name'], 0, 1));
                        $member_since = date('Y', strtotime($rv['member_since']));
                        ?>
                        <article class="card testimonial-card reveal" data-delay="<?php echo $ri + 1; ?>">
                            <p class="quote"><?php echo htmlspecialchars($rv['content']); ?>
                                <span style="display:block; margin-top:.7rem; color: var(--gold-dark); font-size:.85rem; letter-spacing:.15em;"><?php echo str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']); ?></span>
                            </p>
                            <div class="testimonial-author">
                                <div class="avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                                <div class="who"><b><?php echo htmlspecialchars($rv['first_name'] . ' ' . $rv['last_name']); ?></b><span>Member since <?php echo htmlspecialchars($member_since); ?></span></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                <article class="card testimonial-card reveal" data-delay="1">
                    <p class="quote">NUPS-G has been a transformative experience for me. The leadership training and fellowship opportunities have helped me grow spiritually and develop crucial life skills.</p>
                    <div class="testimonial-author">
                        <div class="avatar-initials">KK</div>
                        <div class="who"><b>Kwadwo Kwabena</b><span>Member since 2025</span></div>
                    </div>
                </article>
                <article class="card testimonial-card reveal" data-delay="2">
                    <p class="quote">The P-SICE programme gave me the opportunity to share the Gospel in deprived communities. It's a blessing to see lives transformed through our outreach.</p>
                    <div class="testimonial-author">
                        <div class="avatar-initials">SS</div>
                        <div class="who"><b>Seth Ofori Sefa</b><span>Executive Member</span></div>
                    </div>
                </article>
                <article class="card testimonial-card reveal" data-delay="3">
                    <p class="quote">Being part of NUPS-G has connected me with amazing peers and mentors. The quiz competitions, events, and community service have made my university years memorable.</p>
                    <div class="testimonial-author">
                        <div class="avatar-initials">YB</div>
                        <div class="who"><b>Yaw Boah</b><span>Organizing Wing</span></div>
                    </div>
                </article>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
