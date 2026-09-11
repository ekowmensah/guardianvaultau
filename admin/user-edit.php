<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_users');

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
header('Location: ' . ($userId ? 'user-form.php?id=' . $userId : 'user-list.php'));
exit;
