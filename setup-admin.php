<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/db_conn.php';
start_secure_session();

$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($adminCount > 0) {
    http_response_code(404);
    exit('Initial setup is already complete.');
}

$errors = [];
$username = '';
$firstName = '';
$lastName = '';
$email = '';
$telephone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $telephone = trim((string) ($_POST['telephone_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($username === '' || strlen($username) > 100) {
        $errors[] = 'Enter an administrator username of at most 100 characters.';
    }
    if ($firstName === '' || strlen($firstName) > 100) {
        $errors[] = 'Enter a first name of at most 100 characters.';
    }
    if ($lastName === '' || strlen($lastName) > 100) {
        $errors[] = 'Enter a last name of at most 100 characters.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($telephone !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $telephone)) {
        $errors[] = 'Enter a valid telephone number.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (username, password, first_name, last_name, email, telephone_number, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $firstName,
            $lastName,
            $email,
            $telephone,
            'super_admin',
            'Active',
        ]);
        log_security_event('system', 'initial_admin_created', (int) $pdo->lastInsertId(), null, 'First administrator created');
        header('Location: admin/admin_login.php?message=initial-admin-created');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Initial Admin Setup | Guardian Vault</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/fonts/fontawesome-all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 640px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2"><i class="fa fa-user-shield me-2 text-primary"></i>Create Initial Administrator</h1>
            <p class="text-muted">This page is available only before the first administrator account exists.</p>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="first_name">First Name</label>
                        <input class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="telephone_number">Telephone</label>
                        <input class="form-control" id="telephone_number" name="telephone_number" value="<?= htmlspecialchars($telephone, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirm Password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                    </div>
                </div>
                <button class="btn btn-primary mt-4 w-100"><i class="fa fa-check me-1"></i>Create Administrator</button>
            </form>
        </div>
    </div>
</main>
<script src="<?= htmlspecialchars(asset_url('assets/bootstrap/js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
