<?php
require_once __DIR__ . '/../app_security.php';
start_secure_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}
verify_csrf();
$actorId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
if ($actorId) log_security_event('admin', 'logout', $actorId, $actorId);
destroy_session();
header('Location: admin_login.php');
exit;
