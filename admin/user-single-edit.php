<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_users');

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    header('Location: user-list.php');
    exit;
}

header('Location: user-form.php?id=' . $userId);
exit;
