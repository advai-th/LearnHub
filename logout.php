<?php
require_once __DIR__ . '/db.php';
$_SESSION = [];
if (session_id() !== '') {
    session_destroy();
}
session_start();
$_SESSION['flash_success'] = 'You have been logged out.';
header('Location: login.php');
exit;
