<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");

if (isset($_SESSION['loggedin'])) {
    header('Location: ' . (($_SESSION['auth_type'] ?? '') === 'admin' ? 'admin/index.php' : 'home.php'));
    exit;
}
$errorMessages = [
    'invalid' => 'Invalid username or password.',
    'rate-limited' => 'Too many attempts. Please wait 15 minutes and try again.',
    'session-expired' => 'Your session expired. Please sign in again.',
    'account-unavailable' => 'This account is not currently available.',
];
$error = $errorMessages[$_GET['error'] ?? ''] ?? '';
$message = ($_GET['message'] ?? '') === 'password-changed' ? 'Your password was changed. Sign in with the new password.' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Guardian Vault</title>
    <link rel="icon" type="image/png" href="assets/img/thelogseclogo.png">
    <link href="<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/fonts/fontawesome-all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
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
        .login-card .fa-user-shield {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="login-card card shadow border-0 w-100">
            <div class="card-header text-center pb-0">
                <img src="assets/img/thelogseclogo.png" alt="Guardian Vault Logo" class="login-logo mb-2">
                <h2 class="fw-bold text-primary mb-1"><i class="fas fa-user-shield me-2"></i> Guardian Vault Login</h2>
                <div class="text-muted mb-2">Sign in to access your secure vault dashboard</div>
            </div>
            <div class="card-body pt-0">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="POST" action="login_process.php" class="login-form" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Account</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter your Account" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your Password" required>
                    </div>
                    <p class="small text-muted mb-3">Contact an authorised vault administrator if you need your password reset.</p>
                    <button type="submit" class="btn btn-primary login-btn w-100 py-2"><i class="fas fa-sign-in-alt me-1"></i>Sign in</button>
                </form>
         <!--       <div class="text-center mt-4">
                    <a href="admin/admin_login.php" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-user-gear me-1"></i>Admin Login</a>
                </div> -->
            </div>
        </div>
    </div>
    <script src="<?= htmlspecialchars(asset_url('assets/bootstrap/js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
