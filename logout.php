<?php
require_once __DIR__ . '/auth_helpers.php';
logoutUser();
header('Location: index.php');
exit;
