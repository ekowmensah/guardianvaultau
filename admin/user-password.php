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

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
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
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Account security</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-key me-2"></i>Change User Password</h1>
                    <p class="mb-0">
                        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-white-50">(<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    </p>
                </div>
                <a href="user-list.php" class="btn btn-light px-4"><i class="fa fa-arrow-left me-2"></i>Users</a>
            </div>
        </section>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <section class="admin-card">
                    <div class="admin-card-body">

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
                                <input type="password" id="password" name="password" class="form-control" minlength="8" required autofocus>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            <div class="mb-4">
                                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fa fa-save me-1"></i>Update Password</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
