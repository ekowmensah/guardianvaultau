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
<main class="col-md-10 ms-sm-auto main-content">
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Security</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-mobile-alt me-2"></i>Multi-factor Authentication</h1>
                    <p class="mb-0">Protect administrator access with a six-digit authenticator code.</p>
                </div>
                <span class="btn btn-light disabled px-4"><?= $enrolled ? 'Enabled' : 'Setup required' ?></span>
            </div>
        </section>

        <div class="row g-3 justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <section class="admin-card">
                    <div class="admin-card-body">
                        <?php if ($enrolled): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fa fa-check-circle me-2"></i>TOTP multi-factor authentication is enabled for this administrator.
                            </div>
                        <?php else: ?>
                            <?php if (isset($_GET['required'])): ?>
                                <div class="alert alert-warning py-2">Multi-factor authentication is required before accessing the admin portal.</div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-lg-7">
                                    <h2 class="h5 fw-bold mb-3">Setup steps</h2>
                                    <ol class="small text-muted mb-0">
                                        <li class="mb-2">Add a new time-based account in your authenticator app.</li>
                                        <li class="mb-2">Enter the setup key shown on the right.</li>
                                        <li>Confirm the generated six-digit code below.</li>
                                    </ol>
                                </div>
                                <div class="col-lg-5">
                                    <div class="p-3 rounded-4 bg-light border">
                                        <div class="admin-label mb-2">Setup key</div>
                                        <code class="user-select-all text-break"><?= htmlspecialchars($secret, ENT_QUOTES, 'UTF-8') ?></code>
                                    </div>
                                </div>
                            </div>

                            <details class="my-3">
                                <summary class="small text-muted">Advanced setup URI</summary>
                                <code class="text-break small"><?= htmlspecialchars($otpUri, ENT_QUOTES, 'UTF-8') ?></code>
                            </details>

                            <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

                            <form method="post" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <label class="form-label" for="code">Authentication code</label>
                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    <input id="code" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                                    <button class="btn btn-primary px-4 rounded-pill">Enable MFA</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
