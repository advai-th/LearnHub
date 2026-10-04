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

    if ($user['role'] === 'instructor') {
        header('Location: instructor_dashboard.php');
    } else {
        header('Location: progress.php');
    }
    exit;
}

header('Location: index.php');
exit;
