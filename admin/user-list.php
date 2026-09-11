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
    echo "<div class='alert alert-danger'>Unable to load user records.</div>";
}
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4">
                            <h2 class="mb-2 mb-md-0"><i class="fa fa-users me-2"></i>User Management</h2>
                            <?php if (admin_can('manage_users')): ?><a href="user-form.php" class="btn btn-success"><i class="fa fa-plus me-1"></i> Add User</a><?php endif; ?>
                        </div>
                        <form method="get" class="row g-2 mb-4">
                            <div class="col-md-8">
                                <label for="userSearch" class="visually-hidden">Search users</label>
                                <input type="search" id="userSearch" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Search username, name, or email">
                            </div>
                            <div class="col-auto"><button type="submit" class="btn btn-outline-primary"><i class="fa fa-search me-1"></i>Search</button></div>
                            <?php if ($search !== ''): ?><div class="col-auto"><a href="user-list.php" class="btn btn-outline-secondary">Clear</a></div><?php endif; ?>
                        </form>
                        <div class="text-muted small mb-2">Showing <?= count($users) ?> of <?= $totalUsers ?> account<?= $totalUsers === 1 ? '' : 's' ?>.</div>
<?php if (!empty($users)): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Username</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['first_name']) ?></td>
                <td><?= htmlspecialchars($user['last_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                <td><span class="badge <?= $user['status'] === 'Active' ? 'bg-success' : ($user['status'] === 'Suspended' ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                <td>
                    <a href="user-view.php?id=<?= $user['id'] ?>" class="btn btn-outline-info btn-sm me-1" title="View"><i class="fa fa-eye"></i></a>
                    <?php if (admin_can('manage_users')): ?>
                    <a href="user-single-edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary btn-sm me-1" title="Edit"><i class="fa fa-edit"></i></a>
                    <a href="user-password.php?id=<?= (int) $user['id'] ?>" class="btn btn-outline-warning btn-sm me-1" title="Change password"><i class="fa fa-key"></i></a>
                    <form method="post" action="user-list.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="fa fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">No users found.</div>
<?php endif; ?>
<?php if (($totalPages ?? 1) > 1): ?>
    <nav aria-label="User pages" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $pageNumber ?>"><?= $pageNumber ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
                </div>
            </div>
        </div>
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
