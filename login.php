<?php
require_once __DIR__ . '/db.php';
$pageTitle = 'Login';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['name']) . '!';

            if ($user['role'] === 'instructor') {
                header('Location: instructor_dashboard.php');
            } else {
                header('Location: courses.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="max-width: 440px; margin: 40px auto;">
    <div class="form-card">
        <h2 style="font-size: 24px; margin-bottom: 8px; color: #111827;">Sign In</h2>
        <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">Enter your credentials to access your account.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-input" required placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" name="password" id="password" class="form-input" required placeholder="••••••••">
            </div>

            <button type="submit" class="primary-btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Sign In
            </button>
        </form>

        <div style="border-top: 1px solid #e5e7eb; margin: 25px 0 20px; padding-top: 20px;">
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 12px; text-align: center;">Or try instant one-click demo login:</p>
            <div style="display: flex; gap: 10px;">
                <a href="demo_switch.php?role=student" class="secondary-btn btn-secondary btn-sm" style="flex: 1; text-align: center;">
                    Student Demo
                </a>
                <a href="demo_switch.php?role=instructor" class="secondary-btn btn-secondary btn-sm" style="flex: 1; text-align: center;">
                    Instructor Demo
                </a>
            </div>
        </div>

        <p style="text-align: center; font-size: 14px; color: #6b7280; margin-top: 20px;">
            Don't have an account? <a href="signup.php" style="color: #2563eb; font-weight: 600;">Sign up</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
