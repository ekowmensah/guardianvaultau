<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/db_conn.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

verify_csrf();
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    header('Location: login.php?error=invalid');
    exit;
}

if (login_is_rate_limited('user', $username)) {
    log_security_event('user', 'login_rate_limited', null, null, 'Login temporarily blocked');
    header('Location: login.php?error=rate-limited');
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, password, first_name, last_name, email, telephone_number, role, status, session_version FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();
$storedHash = $user['password'] ?? '$2y$10$Bkyrtuk6B1o0V3FZKG5Gau4P/uaCZqbxei73Q71G2ndQZfskqqfly';
$passwordMatches = password_verify($password, $storedHash);
$valid = $user && $user['status'] === 'Active' && $passwordMatches;
record_login_attempt('user', $username, (bool) $valid);

if (!$valid) {
    log_security_event('user', 'login_failed', null, $user ? (int) $user['id'] : null, 'Invalid credentials or unavailable account');
    header('Location: login.php?error=invalid');
    exit;
}

if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
}

establish_user_session($user);
log_security_event('user', 'login_succeeded', (int) $user['id'], (int) $user['id']);
header('Location: home.php');
exit;
