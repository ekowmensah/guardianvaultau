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
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin verification</title>
    <link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/fonts/fontawesome-all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background:
                radial-gradient(circle at 20% 12%, rgba(13, 110, 253, .16), transparent 22rem),
                radial-gradient(circle at 82% 18%, rgba(255, 193, 7, .22), transparent 18rem),
                linear-gradient(135deg, #eef4fb 0%, #f8fafc 100%);
        }

        .verify-card {
            max-width: 460px;
            border: 0;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .16);
            overflow: hidden;
        }

        .verify-card::before {
            content: "";
            display: block;
            height: 7px;
            background: linear-gradient(90deg, #0d6efd, #ffc107);
        }

        .form-control {
            border-radius: 14px;
            padding: .85rem 1rem;
            text-align: center;
            letter-spacing: .18em;
        }

        .btn {
            border-radius: 999px;
            padding: .75rem 1rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="container py-5" style="max-width:520px">
        <div class="card verify-card mx-auto">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <div class="text-primary fs-1 mb-2"><i class="fa fa-shield-alt"></i></div>
                    <h1 class="h3 fw-bold mb-1">Two-step verification</h1>
                    <p class="text-muted mb-0">Enter the six-digit code from your authenticator app.</p>
                </div>

                <?php if ($error): ?><div class="alert alert-danger text-center"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label class="form-label visually-hidden" for="code">Authentication code</label>
                    <input class="form-control form-control-lg mb-3" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
                    <button class="btn btn-primary w-100">Verify</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
