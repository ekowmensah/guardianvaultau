<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_once __DIR__ . '/../db_conn.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

verify_csrf();
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    header('Location: admin_login.php?error=invalid');
    exit;
}
if (login_is_rate_limited('admin', $username)) {
    log_security_event('admin', 'login_rate_limited', null, null, 'Admin login temporarily blocked');
    header('Location: admin_login.php?error=rate-limited');
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, password, first_name, last_name, email, telephone_number, role, status, session_version, totp_secret FROM admin_users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$admin = $stmt->fetch();
$storedHash = $admin['password'] ?? '$2y$10$Bkyrtuk6B1o0V3FZKG5Gau4P/uaCZqbxei73Q71G2ndQZfskqqfly';
$passwordMatches = password_verify($password, $storedHash);
$valid = $admin && $admin['status'] === 'Active' && $passwordMatches;
record_login_attempt('admin', $username, (bool) $valid);

if (!$valid) {
    log_security_event('admin', 'login_failed', null, $admin ? (int) $admin['id'] : null, 'Invalid credentials or unavailable account');
    header('Location: admin_login.php?error=invalid');
    exit;
}

if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);
}

if (!empty($admin['totp_secret'])) {
    session_regenerate_id(true);
    $_SESSION = [
        'pending_admin_id' => (int) $admin['id'],
        'pending_admin_until' => time() + 300,
        'mfa_failures' => 0,
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    log_security_event('admin', 'mfa_challenge_started', (int) $admin['id'], (int) $admin['id']);
    header('Location: mfa_verify.php');
    exit;
}

establish_admin_session($admin, false);
log_security_event('admin', 'login_succeeded', (int) $admin['id'], (int) $admin['id'], 'Password authentication; MFA not enrolled');
header('Location: ' . (getenv('GUARDIAN_ADMIN_MFA_REQUIRED') === '1' ? 'mfa-setup.php?required=1' : 'index.php'));
exit;
