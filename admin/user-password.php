<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_users');
require_once __DIR__ . '/../db_conn.php';

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    header('Location: user-list.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, username, first_name, last_name FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: user-list.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirmation'] ?? '';

    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        record_user_revision($pdo, (int) $userId, 'password_change', null);
        $pdo->commit();
        log_admin_action('change_user_password', $userId, 'User password changed');
        header('Location: user-list.php?password=updated');
        exit;
    }
}
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h2 class="mb-1"><i class="fa fa-key me-2"></i>Change Password</h2>
                                <div class="text-muted">
                                    <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="small">(<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                </div>
                            </div>
                            <a href="user-list.php" class="btn btn-outline-secondary" title="Back to users"><i class="fa fa-arrow-left"></i></a>
                        </div>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" id="password" name="password" class="form-control" minlength="12" required autofocus>
                            </div>
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" minlength="12" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fa fa-save me-1"></i>Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
