<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}
verify_csrf();
$actorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if ($actorId) log_security_event('user', 'logout', $actorId, $actorId);
destroy_session();
header('Location: login.php');
exit;
?>
