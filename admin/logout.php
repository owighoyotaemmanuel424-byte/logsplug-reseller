<?php
require_once __DIR__ . '/../admin_helpers.php';
adminLogout();
header('Location: login.php');
exit;
