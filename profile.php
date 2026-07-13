<?php
// Load configuration
require_once 'config.php';
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$page_title = 'My Profile';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            $error = 'Email is already used by another account.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, email = ?, phone = ? WHERE id = ?");
            $parts = explode(' ', $name, 2);
            $first_name = $parts[0];
            $last_name = $parts[1] ?? '';
            if ($stmt->execute([$first_name, $email, $phone, $user_id])) {
                // Update session
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $message = 'Profile updated successfully!';
            } else {
                $error = 'Failed to update profile.';
            }
        }
    }
}

include 'header.php';
?>

<main>
<section class="page-header" style="aspect-ratio: 3 / 1; padding: var(--spacing-lg) clamp(2rem, 8vw, 4rem); display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: clamp(2rem, 6vw, 3.5rem); color: white; margin-bottom: 1rem;">My Profile</h1>
        <p style="font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255,255,255,0.9); max-width: 600px;">Manage your account information</p>
    </div>
</section>

<style>
.profile-section {
    padding: clamp(3rem, 8vw, 6rem) 0;
}
.profile-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 3rem;
    align-items: start;
}
.profile-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}
.profile-avatar {
    width: 120px;
    height: 120px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-avatar i {
    font-size: 3.5rem;
    color: #1e3c72;
}
.profile-card h2 {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}
.profile-email {
    color: #666;
    margin-bottom: 0.5rem;
}
.profile-role {
    display: inline-block;
    background: #1e3c72;
    color: white;
    padding: 0.25rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}
.profile-form,
.profile-quiz {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}
.profile-form h3,
.profile-quiz h3 {
    color: #1e3c72;
    margin-bottom: 1.5rem;
}
.profile-quiz p {
    color: #666;
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
.form-group input {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}
.form-group input:focus {
    outline: none;
    border-color: #1e3c72;
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
@media (max-width: 992px) {
    .profile-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}
</style>

<section class="profile-section">
    <div class="container">
        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="profile-content">
            <div class="profile-card">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h2><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p class="profile-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'No email'); ?></p>
                <span class="profile-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'member')); ?></span>
            </div>
            
            <div>
                <div class="profile-form">
                    <h3>Update Profile</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <div class="profile-quiz" style="margin-top: 2rem;">
                    <h3>Quiz Results</h3>
                    <p>Take the Bible quiz to test your knowledge!</p>
                    <a href="quiz.php" class="btn btn-outline">Go to Quiz</a>
                </div>
            </div>
        </div>
    </div>
</section>
</main>

<?php include 'footer.php'; ?>
