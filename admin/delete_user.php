<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    header('Location: users.php');
    exit;
}
$userId = (int) $_POST['user_id'];
if ($userId < 1) {
    header('Location: users.php?error=invalid');
    exit;
}
adminDeleteUser($userId);
header('Location: users.php?deleted=1');
exit;
