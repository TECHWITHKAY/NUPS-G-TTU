<?php
// Load configuration
require_once 'config.php';

$page_title = 'Our History';
$page_description = 'The story of the NUPS-G TTU chapter: from a small fellowship to a thriving campus ministry in Ghana.';

include 'header.php';
?>

<main>
    <section class="page-hero">
        <div class="container">
            <span class="eyebrow"><i class="fas fa-landmark"></i> Our Story</span>
            <h1>Our History</h1>
            <div class="divider"></div>
            <p>The journey of the NUPS-G TTU chapter: faith, growth and impact through the years</p>
        </div>
    </section>

    <style>
        .history-intro { display: grid; grid-template-columns: 1.05fr 1fr; gap: clamp(2rem, 5vw, 4rem); align-items: center; }
        .history-intro .art { position: relative; border-radius: var(--r-lg); overflow: hidden; box-shadow: var(--shadow-lg); }
        .history-intro .art img { width: 100%; height: 100%; object-fit: cover; aspect-ratio: 4 / 3; }
        .history-intro .art::after { content: ''; position: absolute; inset: auto -16px -16px auto; width: 110px; height: 110px; border-radius: var(--r-lg) 0 0 0; background: linear-gradient(135deg, transparent 48%, var(--gold) 49%); }
        .history-intro h2 { font-size: var(--fs-xl); margin-bottom: 1.2rem; }
        .history-intro p { color: var(--muted); line-height: 1.9; margin-bottom: 1rem; }
        .history-intro .lead-verse { font-family: var(--font-display); font-style: italic; color: var(--primary); font-size: 1.15rem; border-left: 3px solid var(--gold); padding-left: 1rem; margin-top: 1.6rem; }
        .mini-timeline { margin-top: clamp(2rem, 5vw, 3rem); }
        @media (max-width: 900px) { .history-intro { grid-template-columns: 1fr; } }
    </style>

    <section class="section">
        <div class="container">
            <div class="history-intro">
                <div class="art reveal"><img src="images/history.jpg" alt="NUPS-G history" loading="lazy"></div>
                <div class="reveal" data-delay="1">
                    <span class="eyebrow">A Journey of Faith</span>
                    <h2>From one campus to a movement</h2>
                    <p>The National Union of Presbyterian Students of Ghana began in 1959 at the University of Ghana and has grown into one of the country's most vibrant student fellowships. Our Takoradi Technical University chapter carries that same torch: a community of students devoted to Christ and committed to excellence.</p>
                    <p>Through worship, biblical teaching, leadership training and community service, we continue to nurture spiritual growth and shape students into servant leaders for the church and the nation.</p>
                    <p class="lead-verse">"Train up a child in the way he should go, and when he is old he will not depart from it." - Proverbs 22:6</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--soft">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Milestones</span>
                <h2>Key Moments on the Journey</h2>
                <p>Sixty-plus years of God's faithfulness</p>
            </div>
            <div class="mini-timeline reveal">
                <?php
                $milestones = [
                    ['1959', 'The Beginning', 'NUPS-G began at Akuafo Hall Chapel of the University of Ghana with 39 pioneering students.'],
                    ['1960', 'Inaugural Ceremony', 'Official inauguration held at Legon Hall, with Rev. W.S. Robertson as chairman.'],
                    ['1962', 'National Expansion', 'First National Conference at Commonwealth Hall. The vision spreads to other institutions.'],
                    ['1985', 'Leadership Training', 'First leadership training programme held at Trinity College for coordinating branches.'],
                    ['Today', 'The TTU Chapter', 'A thriving community of students growing in faith and making an impact in the university and beyond.'],
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
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-band reveal">
                <div class="container">
                    <div>
                        <h2>Become part of the story</h2>
                        <p>Whether you're a new student or returning, there's a place for you in the NUPS-G family.</p>
                    </div>
                    <a href="register.php" class="btn btn-gold btn-lg"><i class="fas fa-user-plus"></i> Join NUPS-G</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
