<?php
require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();

$role = $_GET['role'] ?? 'student';

if ($role === 'instructor') {
    $user = $pdo->query("SELECT * FROM users WHERE role = 'instructor' LIMIT 1")->fetch();
} else {
    $user = $pdo->query("SELECT * FROM users WHERE role = 'student' LIMIT 1")->fetch();
}

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['flash_success'] = "Logged in as " . $user['name'] . " (" . ucfirst($user['role']) . ").";

    $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : ($user['role'] === 'instructor' ? 'instructor_dashboard.php' : 'progress.php');
    header('Location: ' . $redirect);
    exit;
}

header('Location: index.php');
exit;
