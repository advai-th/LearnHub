<?php
require_once __DIR__ . '/db.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - LearnHub' : 'LearnHub - Online Learning Platform' ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- Quick Demo Switcher -->

<!-- Navigation -->
<header class="navbar">
    <div class="container nav-container">
        <a href="index.php" class="logo">Learn<span>Hub</span></a>

        <nav>
            <a href="courses.php">Courses</a>
            <a href="progress.php">My Progress</a>
            <a href="quizzes.php">Quizzes</a>
            <?php if ($currentUser && $currentUser['role'] === 'instructor'): ?>
                <a href="instructor_dashboard.php" style="color: #2563eb; font-weight: bold;">Instructor Portal</a>
            <?php else: ?>
                <a href="instructor_dashboard.php">Instructor</a>
            <?php endif; ?>
        </nav>

        <div class="nav-buttons">
            <?php if ($currentUser): ?>
                <div class="user-menu">
                    <span class="user-badge">
                        <?= htmlspecialchars($currentUser['name']) ?>
                        <span class="badge-role <?= $currentUser['role'] ?>"><?= $currentUser['role'] ?></span>
                    </span>
                    <a href="logout.php" class="secondary-btn btn-sm">Logout</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
                <a href="signup.php" class="signup-btn">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php 
$isHomePage = (basename($_SERVER['PHP_SELF']) === 'index.php');
?>

<?php if ($isHomePage): ?>
    <?php if (isset($_SESSION['flash_success']) || isset($_SESSION['flash_error'])): ?>
        <div class="container" style="padding-top: 20px;">
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <main class="page-body">
        <div class="container">
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
<?php endif; ?>

