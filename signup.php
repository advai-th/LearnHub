<?php
require_once __DIR__ . '/db.php';
$pageTitle = 'Sign Up';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    if (!in_array($role, ['student', 'instructor'])) {
        $role = 'student';
    }

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered. Please login.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed, $role]);
            $userId = $pdo->lastInsertId();

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;
            $_SESSION['flash_success'] = 'Account created successfully! Welcome to LearnHub.';

            if ($role === 'instructor') {
                header('Location: instructor_dashboard.php');
            } else {
                header('Location: courses.php');
            }
            exit;
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div style="max-width: 480px; margin: 40px auto;">
    <div class="form-card">
        <h2 style="font-size: 24px; margin-bottom: 8px; color: #111827;">Create an Account</h2>
        <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">Join LearnHub to start learning or teaching today.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="signup.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" name="name" id="name" class="form-input" required placeholder="Alex Morgan" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-input" required placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" name="password" id="password" class="form-input" required placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label class="form-label" for="role">I want to:</label>
                <select name="role" id="role" class="form-select">
                    <option value="student" <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : '' ?>>Learn as a Student</option>
                    <option value="instructor" <?= (isset($_POST['role']) && $_POST['role'] === 'instructor') ? 'selected' : '' ?>>Teach as an Instructor</option>
                </select>
            </div>

            <button type="submit" class="primary-btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                Create Account
            </button>
        </form>

        <p style="text-align: center; font-size: 14px; color: #6b7280; margin-top: 25px;">
            Already have an account? <a href="login.php" style="color: #2563eb; font-weight: 600;">Sign in</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
