<?php
require_once 'config.php';
require_once 'db.php';

$page_title = 'Blog Detail';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ? AND status = 'published'");
$stmt->execute([$id]);
$blog = $stmt->fetch();

if (!$blog) {
    redirect('blogs.php');
}

include 'header.php';
?>

<style>
    .blog-detail-section {
        padding: 60px 0;
    }
    .blog-detail-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .blog-detail-image {
        width: 100%;
        height: 450px;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 40px;
        overflow: hidden;
        position: relative;
    }
    .blog-detail-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .blog-detail-image video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .blog-detail-image i {
        font-size: 100px;
        color: white;
    }
    .blog-detail-meta {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        color: #666;
        font-size: 14px;
    }
    .blog-detail-meta i {
        margin-right: 5px;
        color: #1e3c72;
    }
    .blog-detail-title {
        color: #1e3c72;
        font-size: 36px;
        margin-bottom: 30px;
        line-height: 1.3;
    }
    .blog-detail-content {
        color: #333;
        line-height: 2;
        font-size: 16px;
    }
    .blog-detail-content p {
        margin-bottom: 20px;
    }
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #1e3c72;
        text-decoration: none;
        margin-bottom: 30px;
        font-weight: 500;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    .section {
        padding: 80px 0;
    }
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    /* Responsive Design */
    @media (max-width: 992px) {
        .blog-detail-container {
            padding: 0 15px;
        }
    }
    
    @media (max-width: 768px) {
        .blog-detail-image {
            height: 300px;
        }
        .blog-detail-title {
            font-size: 28px;
        }
        .blog-detail-content {
            font-size: 15px;
        }
    }
    
    @media (max-width: 576px) {
        .blog-detail-section {
            padding: 40px 0;
        }
        .blog-detail-image {
            height: 220px;
            border-radius: 10px;
        }
        .blog-detail-image i {
            font-size: 60px;
        }
        .blog-detail-title {
            font-size: 24px;
        }
        .blog-detail-meta {
            flex-direction: column;
            gap: 8px;
        }
    }
    
    @media (max-width: 480px) {
        .blog-detail-image {
            height: 180px;
        }
        .blog-detail-title {
            font-size: 20px;
            margin-bottom: 20px;
        }
        .blog-detail-content {
            font-size: 14px;
            line-height: 1.8;
        }
    }
</style>

<section class="blog-detail-section">
    <div class="container">
        <div class="blog-detail-container">
            <a href="blogs.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Blogs
            </a>
            
            <div class="blog-detail-image">
                <?php 
                $media_type = $blog['media_type'] ?? 'image';
                $image = $blog['image'] ?? '';
                
                if (!empty($image) && file_exists('uploads/' . $image)): 
                    if ($media_type === 'video'):
                ?>
                    <video controls playsinline preload="metadata" style="width: 100%; max-height: 500px;">
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
            
            <div class="blog-detail-meta">
                <span><i class="fas fa-calendar"></i> <?php echo formatDate($blog['created_at']); ?></span>
                <?php if ($media_type === 'video'): ?>
                    <span><i class="fas fa-video"></i> Video</span>
                <?php endif; ?>
            </div>
            
            <h1 class="blog-detail-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
            
            <div class="blog-detail-content">
                <?php echo nl2br(htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8')); ?>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
