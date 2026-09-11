<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_once __DIR__ . '/../db_conn.php';
start_secure_session();

$pendingAdminId = (int) ($_SESSION['pending_admin_id'] ?? 0);
if (!$pendingAdminId || time() > (int) ($_SESSION['pending_admin_until'] ?? 0)) {
    destroy_session();
    header('Location: admin_login.php?error=session-expired');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status, session_version, totp_secret FROM admin_users WHERE id = ? AND status = \'Active\' LIMIT 1');
    $stmt->execute([$pendingAdminId]);
    $admin = $stmt->fetch();
    $valid = $admin && !empty($admin['totp_secret']) && verify_totp(decrypt_secret($admin['totp_secret']), $code);
    if ($valid) {
        establish_admin_session($admin, true);
        log_security_event('admin', 'login_succeeded', (int) $admin['id'], (int) $admin['id'], 'Password and TOTP authentication');
        header('Location: index.php');
        exit;
    }
    $_SESSION['mfa_failures'] = (int) ($_SESSION['mfa_failures'] ?? 0) + 1;
    log_security_event('admin', 'mfa_challenge_failed', $pendingAdminId, $pendingAdminId);
    if ($_SESSION['mfa_failures'] >= 5) {
        destroy_session();
        header('Location: admin_login.php?error=rate-limited');
        exit;
    }
    $error = 'Invalid authentication code.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin verification</title><link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body class="bg-light"><main class="container py-5" style="max-width:520px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3">Two-step verification</h1><p class="text-muted">Enter the six-digit code from your authenticator app.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="code">Authentication code</label><input class="form-control form-control-lg mb-3" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><button class="btn btn-primary w-100">Verify</button></form>
</div></div></main></body></html>
