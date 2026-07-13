<?php
// Load configuration
require_once 'config.php';

$page_title = 'Our History';

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Our History</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">The story of NUPS-G TTU Chapter</p>
    </div>
</section>

<style>
.history-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.history-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
    margin-bottom: 4rem;
}
.history-image {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}
.history-image img {
    width: 100%;
    height: auto;
    display: block;
}
.history-text h2 {
    font-size: 2rem;
    color: #1e3c72;
    margin-bottom: 1.5rem;
}
.history-text p {
    color: #555;
    line-height: 1.8;
    margin-bottom: 1rem;
}
.timeline {
    margin-top: 4rem;
}
.timeline h2 {
    text-align: center;
    font-size: 2rem;
    color: #1e3c72;
    margin-bottom: 3rem;
}
.timeline-items {
    position: relative;
    max-width: 800px;
    margin: 0 auto;
}
.timeline-items::before {
    content: '';
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 100%;
    background: #1e3c72;
}
.timeline-item {
    display: flex;
    justify-content: flex-end;
    padding-right: 50%;
    position: relative;
    margin-bottom: 2rem;
}
.timeline-item:nth-child(even) {
    justify-content: flex-start;
    padding-right: 0;
    padding-left: 50%;
}
.timeline-date {
    position: absolute;
    right: -80px;
    top: 0;
    background: #1e3c72;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}
.timeline-item:nth-child(even) .timeline-date {
    right: auto;
    left: -80px;
}
.timeline-content {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    max-width: 350px;
}
.timeline-content h3 {
    color: #1e3c72;
    margin-bottom: 0.5rem;
}
.timeline-content p {
    color: #666;
    margin: 0;
}
@media (max-width: 992px) {
    .history-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .timeline-items::before {
        left: 20px;
    }
    .timeline-item,
    .timeline-item:nth-child(even) {
        padding-left: 60px;
        padding-right: 0;
        justify-content: flex-start;
    }
    .timeline-date,
    .timeline-item:nth-child(even) .timeline-date {
        left: -40px;
        right: auto;
        transform: translateX(-100%);
    }
}
</style>

<section class="history-section">
    <div class="container">
        <div class="history-content">
            <div class="history-image">
                <img src="images/history.jpg" alt="Church History">
            </div>
            <div class="history-text">
                <h2>A Journey of Faith</h2>
                <p>The NUPS-G (National Union of Pentecostal Students of Ghana) TTU Chapter has been a beacon of spiritual growth and community service since its inception. Our journey began with a small group of dedicated students who sought to create a platform for Pentecostal students to fellowship, grow spiritually, and make a positive impact in their community.</p>
                
                <p>Over the years, we have grown from a small campus group to a vibrant community of believers, continually expanding our reach and impact. Through worship, biblical teaching, and community service, we remain committed to our mission of spreading the love and message of Jesus Christ.</p>
                
                <p>Today, the NUPS-G TTU Chapter continues to nurture spiritual growth, foster fellowship among students, and serve the broader community with love and dedication.</p>
            </div>
        </div>
        
        <div class="timeline">
            <h2>Milestones</h2>
            <div class="timeline-items">
                <div class="timeline-item">
                    <div class="timeline-date">Foundation</div>
                    <div class="timeline-content">
                        <h3>The Beginning</h3>
                        <p>NUPS-G TTU Chapter was established with a vision to unite Pentecostal students.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-date">Growth</div>
                    <div class="timeline-content">
                        <h3>Expansion</h3>
                        <p>The chapter experienced significant growth in membership and activities.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-date">Today</div>
                    <div class="timeline-content">
                        <h3>Current Status</h3>
                        <p>A thriving community continues to make impact in the university and beyond.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
