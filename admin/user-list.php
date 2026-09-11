<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_users');
include_once("../db_conn.php");

$users = [];
$totalUsers = 0;
$totalPages = 1;
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$loadError = '';
try {
    // Handle Delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && isset($_POST['id'])) {
        if (!admin_can('manage_users')) {
            http_response_code(403);
            exit('You do not have permission to delete users.');
        }
        verify_csrf();
        $user_id = intval($_POST['id']);
        $pdo->beginTransaction();
        $snapshotStmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status FROM users WHERE id = ? FOR UPDATE');
        $snapshotStmt->execute([$user_id]);
        $snapshot = $snapshotStmt->fetch();
        if (!$snapshot) {
            throw new RuntimeException('User not found.');
        }
        record_user_revision($pdo, $user_id, 'delete', $snapshot);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
        $pdo->commit();
        log_admin_action('delete_user', $user_id, 'User account and related records deleted');
        echo '<div class="alert alert-success">User deleted successfully!</div>';
    }
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE u.username LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search';
        $params['search'] = '%' . $search . '%';
    }
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$where}");
    $countStmt->execute($params);
    $totalUsers = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $listStmt = $pdo->prepare("SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, u.status FROM users u {$where} ORDER BY u.id DESC LIMIT {$perPage} OFFSET {$offset}");
    $listStmt->execute($params);
    $users = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin user list error: ' . $e->getMessage());
    $loadError = 'Unable to load user records.';
}
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Customer records</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-users me-2"></i>Account Management</h1>
                    <p class="mb-0">Search, review, edit, and maintain customer vault accounts.</p>
                </div>
                <?php if (admin_can('manage_users')): ?>
                    <a href="user-form.php?step=account" class="btn btn-light px-4"><i class="fa fa-plus me-2"></i>Add Account</a>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($loadError): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="admin-card overflow-hidden">
            <div class="admin-card-header">
                <form method="get" class="row g-2 align-items-center">
                    <div class="col-lg-8">
                        <label for="userSearch" class="visually-hidden">Search users</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                            <input type="search" id="userSearch" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" class="form-control border-start-0" placeholder="Search username, name, or email">
                        </div>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-primary rounded-pill px-4">Search</button></div>
                    <?php if ($search !== ''): ?><div class="col-auto"><a href="user-list.php" class="btn btn-outline-secondary rounded-pill px-4">Clear</a></div><?php endif; ?>
                    <div class="col-lg text-lg-end text-muted small">Showing <?= count($users) ?> of <?= $totalUsers ?> account<?= $totalUsers === 1 ? '' : 's' ?></div>
                </form>
            </div>

            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table admin-table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                            $statusClass = $user['status'] === 'Active' ? 'admin-badge-success' : ($user['status'] === 'Suspended' ? 'admin-badge-warning' : 'admin-badge-muted');
                            ?>
                            <tr>
                                <td class="text-muted">#<?= (int) $user['id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($fullName ?: 'Unnamed customer', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($user['email'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="admin-badge admin-badge-muted"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="admin-badge <?= $statusClass ?>"><?= htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="text-end">
                                    <div class="admin-actions justify-content-end">
                                        <a href="user-view.php?id=<?= (int) $user['id'] ?>" class="btn btn-outline-info btn-sm" title="View"><i class="fa fa-eye"></i></a>
                                        <?php if (admin_can('manage_users')): ?>
                                            <a href="user-single-edit.php?id=<?= (int) $user['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fa fa-edit"></i></a>
                                            <a href="user-password.php?id=<?= (int) $user['id'] ?>" class="btn btn-outline-warning btn-sm" title="Change password"><i class="fa fa-key"></i></a>
                                            <form method="post" action="user-list.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="fa fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-card-body"><div class="admin-empty">No users found.</div></div>
            <?php endif; ?>

            <?php if (($totalPages ?? 1) > 1): ?>
                <div class="admin-card-body border-top">
                    <nav aria-label="User pages">
                        <ul class="pagination justify-content-center mb-0">
                            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                                <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $pageNumber ?>"><?= $pageNumber ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php if (($_GET['password'] ?? '') === 'updated'): ?>
<div class="modal fade" id="passwordUpdatedModal" tabindex="-1" aria-labelledby="passwordUpdatedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="passwordUpdatedModalLabel"><i class="fa fa-check-circle me-2"></i>Password Updated</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">The account password was updated successfully.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('passwordUpdatedModal')).show();
});
</script>
<?php endif; ?>
<?php include 'admin_footer.php'; ?>
