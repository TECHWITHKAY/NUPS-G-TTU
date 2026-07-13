<?php
// Load configuration
require_once 'config.php';

$page_title = 'Bible Quiz';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Bible Knowledge Quiz</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Test your knowledge of the Bible</p>
    </div>
</section>

<style>
.quiz-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.quiz-container {
    max-width: 700px;
    margin: 0 auto;
}
.quiz-info {
    background: white;
    border-radius: 16px;
    padding: 3rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}
.quiz-info h2 {
    color: #1e3c72;
    font-size: 1.75rem;
    margin-bottom: 1rem;
}
.quiz-info > p {
    color: #666;
    margin-bottom: 2rem;
}
.quiz-stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
}
.quiz-stats .stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #555;
}
.quiz-stats .stat i {
    color: #1e3c72;
    font-size: 1.25rem;
}
.quiz-login-prompt {
    background: white;
    border-radius: 16px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    max-width: 500px;
    margin: 0 auto;
}
.quiz-login-prompt i {
    font-size: 4rem;
    color: #1e3c72;
    margin-bottom: 1.5rem;
}
.quiz-login-prompt h2 {
    color: #333;
    margin-bottom: 0.5rem;
}
.quiz-login-prompt p {
    color: #666;
    margin-bottom: 1.5rem;
}
.btn {
    display: inline-block;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    font-size: 1rem;
}
.btn-primary {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(30,60,114,0.3);
}
.btn-outline {
    background: transparent;
    color: #1e3c72;
    border: 2px solid #1e3c72;
}
.btn-outline:hover {
    background: #1e3c72;
    color: white;
}
</style>

<section class="quiz-section">
    <div class="container">
        <?php if (!$is_logged_in): ?>
            <div class="quiz-login-prompt">
                <i class="fas fa-lock"></i>
                <h2>Login Required</h2>
                <p>Please login to take the Bible quiz.</p>
                <a href="login.php" class="btn btn-primary">Login</a>
            </div>
        <?php else: ?>
            <div class="quiz-container">
                <div class="quiz-info">
                    <h2>Welcome to the Bible Quiz</h2>
                    <p>Answer questions about Biblical knowledge and track your progress.</p>
                    <div class="quiz-stats">
                        <div class="stat">
                            <i class="fas fa-question-circle"></i>
                            <span>Multiple Choice</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-clock"></i>
                            <span>Unlimited Time</span>
                        </div>
                    </div>
                    <button id="startQuiz" class="btn btn-primary">Start Quiz</button>
                </div>
                
                <div id="quizContent" class="quiz-content" style="display: none;">
                    <!-- Quiz questions will be loaded here -->
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
