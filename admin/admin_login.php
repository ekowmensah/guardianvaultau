<?php
require_once __DIR__ . '/../app_security.php';
start_secure_session();
if (($_SESSION['auth_type'] ?? '') === 'admin' && !empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
if (($_SESSION['auth_type'] ?? '') !== '') {
    destroy_session();
    start_secure_session();
}
$errorMessages = [
    'invalid' => 'Invalid username or password.',
    'rate-limited' => 'Too many attempts. Please wait 15 minutes and try again.',
    'session-expired' => 'Your session expired. Please sign in again.',
    'account-unavailable' => 'This administrator account is not available.',
    'mfa-required' => 'Complete multi-factor authentication to continue.',
];
$error = $errorMessages[$_GET['error'] ?? ''] ?? '';
$message = ($_GET['message'] ?? '') === 'initial-admin-created' ? 'Initial administrator created. Sign in to continue.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Guardian Vault</title>
    <link rel="icon" type="image/png" href="../assets/img/thelogseclogo.png">
    <link href="../<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/fonts/fontawesome-all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        body {
            background:
                radial-gradient(circle at 16% 12%, rgba(13, 110, 253, .16), transparent 22rem),
                radial-gradient(circle at 84% 18%, rgba(255, 193, 7, .22), transparent 18rem),
                linear-gradient(135deg, #eef4fb 0%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #162033;
        }
        .login-card {
            max-width: 430px;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .16);
            background: rgba(255,255,255,.94);
            overflow: hidden;
        }
        .login-card::before {
            content: "";
            display: block;
            height: 7px;
            background: linear-gradient(90deg, #0d6efd, #ffc107);
        }
        .login-logo {
            width: 76px;
            height: auto;
        }
        .form-label {
            color: #334155;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .form-control {
            border-radius: 14px;
            border-color: #dbe3ef;
            padding: .75rem .9rem;
        }
        .login-btn {
            font-weight: 700;
            border-radius: 999px;
            padding: .75rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="login-card card border-0 w-100">
            <div class="card-header text-center pb-0 pt-4 d-flex flex-column align-items-center bg-transparent border-0">
                <img src="../assets/img/thelogseclogo.png" alt="Guardian Vault Logo" class="login-logo mb-2">
                <span class="badge bg-warning text-dark rounded-pill mb-2">Admin Panel</span>
                <h1 class="h3 fw-bold text-primary mb-1"><i class="fas fa-user-shield me-2"></i>Guardian Vault</h1>
                <div class="text-muted mb-2">Sign in to access the admin dashboard</div>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="alert alert-success text-center"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form action="admin_login_process.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 login-btn">Login</button>
                </form>
            </div>
        </div>
    </div>
    <script src="../<?= htmlspecialchars(asset_url('assets/bootstrap/js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
