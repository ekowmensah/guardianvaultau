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
            background: linear-gradient(120deg, #f8fafc 0%, #f1f5f9 100%);
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.07);
            border-left: 6px solid #0d6efd;
            background: #fff;
        }
        .login-card .card-header {
            background: transparent;
            border-bottom: none;
        }
        .login-logo {
            width: 90px;
            height: auto;
        }
        .form-label {
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .login-btn {
            font-weight: 700;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="login-card card shadow border-0 w-100">
            <div class="card-header text-center pb-0 d-flex flex-column align-items-center">
                <img src="../assets/img/thelogseclogo.png" alt="Guardian Vault Logo" class="login-logo mb-2">
                <span class="badge bg-warning text-dark mb-2">Admin Panel</span>
                <h2 class="fw-bold text-primary mb-1"><i class="fas fa-user-shield me-2"></i> Guardian Vault Admin Login</h2>
                <div class="text-muted mb-2">Sign in to access the admin dashboard</div>
            </div>
            <div class="card-body">
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
