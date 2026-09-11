<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_admins');
include_once("../db_conn.php");
$edit = false;
$username = '';
$id = '';
$role = 'operator';
$status = 'Active';
$errors = [];
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT id, username, role, status FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if ($admin) {
        $edit = true;
        $username = $admin['username'];
        $role = $admin['role'];
        $status = $admin['status'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'operator';
    $status = $_POST['status'] ?? 'Active';
    if ($username === '' || strlen($username) > 100) $errors[] = 'Enter a username of at most 100 characters.';
    if (!in_array($role, ['super_admin', 'operator', 'auditor'], true)) $errors[] = 'Choose a valid administrator role.';
    if (!in_array($status, ['Active', 'Suspended'], true)) $errors[] = 'Choose a valid status.';
    if (empty($_POST['id']) && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== '' && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ? AND id <> ?');
    $duplicate->execute([$username, (int) ($_POST['id'] ?? 0)]);
    if ((int) $duplicate->fetchColumn() > 0) $errors[] = 'That username is already in use.';

    if (!$errors && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        $current = $pdo->prepare('SELECT role FROM admin_users WHERE id = ?');
        $current->execute([$id]);
        $currentRole = $current->fetchColumn();
        if (!$currentRole) {
            $errors[] = 'Administrator not found.';
        } elseif ($currentRole === 'super_admin' && ($role !== 'super_admin' || $status !== 'Active')) {
            $superCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='super_admin' AND status='Active'")->fetchColumn();
            if ($superCount <= 1) $errors[] = 'The final active super administrator cannot be demoted or suspended.';
        }
    }
    if (!$errors && !empty($_POST['id'])) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET username=?, password=?, role=?, status=?, session_version=session_version+1 WHERE id=?");
            $stmt->execute([$username, $hash, $role, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin_users SET username=?, role=?, status=? WHERE id=?");
            $stmt->execute([$username, $role, $status, $id]);
        }
        log_admin_action('update_admin', null, 'Admin account ' . $id . ' updated');
    } elseif (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hash, $role, $status]);
        log_admin_action('create_admin', null, 'Admin account created: ' . $username);
    }
    if (!$errors) {
        header('Location: admin-list.php');
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
                    <div class="admin-eyebrow mb-1"><?= $edit ? 'Update access' : 'Create access' ?></div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-user-shield me-2"></i><?= $edit ? 'Edit' : 'Add' ?> Administrator</h1>
                    <p class="mb-0">Set the login identity, permission role, and account status.</p>
                </div>
                <a href="admin-list.php" class="btn btn-light px-4">
                    <i class="fa fa-arrow-left me-2"></i>Admin List
                </a>
            </div>
        </section>

        <div class="row g-3 justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <h2 class="h5 fw-bold mb-1">Admin details</h2>
                        <p class="small text-muted mb-0"><?= $edit ? 'Leave password blank to keep the current password.' : 'New administrators need a password of at least 8 characters.' ?></p>
                    </div>
                    <div class="admin-card-body">
                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>

                        <form method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" maxlength="100" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password"><?= $edit ? 'New Password' : 'Password' ?></label>
                                <input type="password" id="password" name="password" class="form-control" minlength="8" autocomplete="new-password" <?= $edit ? '' : 'required' ?>>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="role">Role</label>
                                    <select id="role" name="role" class="form-select" required>
                                        <?php foreach (['super_admin' => 'Super administrator', 'operator' => 'Operator', 'auditor' => 'Auditor'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $role === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="status">Status</label>
                                    <select id="status" name="status" class="form-select" required>
                                        <?php foreach (['Active', 'Suspended'] as $value): ?>
                                            <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $value ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="admin-list.php" class="btn btn-outline-secondary px-4">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fa fa-save me-2"></i><?= $edit ? 'Update Admin' : 'Create Admin' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
