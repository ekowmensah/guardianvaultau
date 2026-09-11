<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';
require_user();
require_once __DIR__ . '/db_conn.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $currentHash = (string) $stmt->fetchColumn();
    if (!password_verify($currentPassword, $currentHash)) $errors[] = 'The current password is incorrect.';
    if (strlen($newPassword) < 12) $errors[] = 'The new password must be at least 12 characters.';
    if ($newPassword !== $confirmation) $errors[] = 'The new passwords do not match.';
    if (password_verify($newPassword, $currentHash)) $errors[] = 'Choose a password different from the current password.';
    if (!$errors) {
        $pdo->prepare('UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $_SESSION['user_id']]);
        log_security_event('user', 'password_changed', (int) $_SESSION['user_id'], (int) $_SESSION['user_id']);
        destroy_session();
        header('Location: login.php?message=password-changed');
        exit;
    }
}
include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content"><div class="container py-4" style="max-width:720px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3">Change password</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<div class="mb-3"><label class="form-label" for="current_password">Current password</label><input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required></div>
<div class="mb-3"><label class="form-label" for="new_password">New password</label><input type="password" class="form-control" id="new_password" name="new_password" minlength="12" autocomplete="new-password" required></div>
<div class="mb-3"><label class="form-label" for="password_confirmation">Confirm new password</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="12" autocomplete="new-password" required></div>
<button class="btn btn-primary">Update password</button> <a href="profile.php" class="btn btn-outline-secondary">Cancel</a></form>
</div></div></div></main>
<?php include 'user_footer.php'; ?>
