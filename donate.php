<?php
// Load configuration
require_once 'config.php';

$page_title = 'Donate';

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">Support Our Mission</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Your generosity helps us serve the community</p>
    </div>
</section>

<style>
.donate-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.donate-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    margin-bottom: 4rem;
}
.donate-info h2 {
    font-size: 2rem;
    color: #1e3c72;
    margin-bottom: 1.5rem;
}
.donate-info > p {
    color: #555;
    line-height: 1.8;
    margin-bottom: 2rem;
}
.donation-methods {
    display: grid;
    gap: 1.5rem;
}
.method {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.method i {
    font-size: 2rem;
    color: #1e3c72;
}
.method h3 {
    color: #333;
    margin-bottom: 0.5rem;
}
.method p {
    color: #666;
    margin: 0;
}
.donate-form {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}
.donate-form h3 {
    color: #1e3c72;
    margin-bottom: 1.5rem;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    color: #333;
    font-weight: 500;
    margin-bottom: 0.5rem;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #1e3c72;
}
.thank-you-message {
    text-align: center;
    padding: 3rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 16px;
}
.thank-you-message i {
    font-size: 3rem;
    color: #e11d48;
    margin-bottom: 1rem;
}
.thank-you-message h3 {
    color: #1e3c72;
    margin-bottom: 0.5rem;
}
.thank-you-message p {
    color: #666;
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
@media (max-width: 992px) {
    .donate-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}
</style>

<section class="donate-section">
    <div class="container">
        <div class="donate-content">
            <div class="donate-info">
                <h2>Give Today</h2>
                <p>Your donations help us continue our work in worship, community service, and spreading the Word of God.</p>
                
                <div class="donation-methods">
                    <div class="method">
                        <i class="fas fa-university"></i>
                        <div>
                            <h3>Bank Transfer</h3>
                            <p>Account details will be provided upon request.</p>
                        </div>
                    </div>
                    <div class="method">
                        <i class="fas fa-mobile-alt"></i>
                        <div>
                            <h3>Mobile Money</h3>
                            <p>Send to: +233 XX XXX XXXX</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="donate-form">
                <h3>Contact Us to Donate</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (GHS)</label>
                        <input type="number" id="amount" name="amount" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message (Optional)</label>
                        <textarea id="message" name="message" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Donation Intent</button>
                </form>
            </div>
        </div>
        
        <div class="thank-you-message">
            <i class="fas fa-heart"></i>
            <h3>Thank You for Your Support!</h3>
            <p>Every donation makes a difference in our ministry.</p>
        </div>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
