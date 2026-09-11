<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin();
require_once __DIR__ . '/../db_conn.php';

$adminId = (int) $_SESSION['admin_id'];
$stmt = $pdo->prepare('SELECT username, totp_secret FROM admin_users WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();
$enrolled = !empty($admin['totp_secret']);
$error = '';

if (!$enrolled && empty($_SESSION['pending_totp_secret'])) {
    $_SESSION['pending_totp_secret'] = base32_encode_bytes(random_bytes(20));
}
$secret = (string) ($_SESSION['pending_totp_secret'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$enrolled) {
    verify_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    if (!verify_totp($secret, $code)) {
        $error = 'The code did not match. Check the device clock and try again.';
    } else {
        $pdo->prepare('UPDATE admin_users SET totp_secret = ?, session_version = session_version + 1 WHERE id = ?')->execute([encrypt_secret($secret), $adminId]);
        $versionStmt = $pdo->prepare('SELECT session_version FROM admin_users WHERE id = ?');
        $versionStmt->execute([$adminId]);
        $_SESSION['session_version'] = (int) $versionStmt->fetchColumn();
        $_SESSION['mfa_verified_at'] = time();
        unset($_SESSION['pending_totp_secret']);
        log_admin_action('enable_mfa', null, 'TOTP enabled for administrator ' . $adminId);
        log_security_event('admin', 'mfa_enrolled', $adminId, $adminId);
        header('Location: index.php?mfa=enabled');
        exit;
    }
}

$issuer = rawurlencode('Guardian Vault');
$label = rawurlencode('Guardian Vault:' . ($admin['username'] ?? 'admin'));
$otpUri = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
include 'admin_header.php';
?>
<main class="col-md-10 ms-sm-auto main-content"><div class="container" style="max-width:760px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3"><i class="fa fa-mobile-alt me-2"></i>Multi-factor authentication</h1>
<?php if ($enrolled): ?><div class="alert alert-success mb-0">TOTP multi-factor authentication is enabled for this administrator.</div>
<?php else: ?>
<?php if (isset($_GET['required'])): ?><div class="alert alert-warning">Multi-factor authentication is required before accessing the admin portal.</div><?php endif; ?>
<ol><li>Add a new time-based account in your authenticator app.</li><li>Enter this setup key: <code class="user-select-all"><?= htmlspecialchars($secret, ENT_QUOTES, 'UTF-8') ?></code></li><li>Confirm the generated six-digit code below.</li></ol>
<details class="mb-3"><summary>Advanced setup URI</summary><code class="text-break"><?= htmlspecialchars($otpUri, ENT_QUOTES, 'UTF-8') ?></code></details>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="code">Authentication code</label><input id="code" name="code" class="form-control mb-3" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button class="btn btn-primary">Enable MFA</button></form>
<?php endif; ?>
</div></div></div></main>
<?php include 'admin_footer.php'; ?>
