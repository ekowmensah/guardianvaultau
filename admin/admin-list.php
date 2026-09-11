<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_admins');
include_once("../db_conn.php");
?>
<?php include_once("admin_header.php"); ?>
<main class="col-md-10 ms-sm-auto main-content">
<div class="container">
    <div class="dashboard-header bg-dark text-white rounded p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="fa fa-user-shield me-2"></i>Admin Management</h2>
            <a href="admin-form.php" class="btn btn-success btn-lg"><i class="fa fa-plus me-1"></i>Add New Admin</a>
        </div>
        <p class="mt-2 mb-0">Manage all admin accounts for the vault system. Edit, add, or remove admin privileges below.</p>
    </div>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-bg-primary mb-3">
                <div class="card-body d-flex align-items-center">
                    <i class="fa fa-user-shield fa-2x me-3"></i>
                    <div>
                        <div class="fs-5">Total Admins</div>
                        <div class="fs-3 fw-bold">
                            <?php 
                            $count = 0;
                            $count = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
                            echo $count;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fs-5 fw-semibold"><i class="fa fa-list me-2 text-primary"></i>Admin List</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>MFA</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT id, username, role, status, totp_secret IS NOT NULL AS mfa_enabled FROM admin_users ORDER BY id") as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><span class="fw-semibold text-primary"><i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($row['username']) ?></span></td>
                        <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($row['role']))) ?></td>
                        <td><span class="badge <?= $row['status'] === 'Active' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td><span class="badge <?= $row['mfa_enabled'] ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $row['mfa_enabled'] ? 'Enabled' : 'Not enabled' ?></span></td>
                        <td class="text-center">
                            <a href="admin-form.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Admin"><i class="fa fa-edit"></i></a>
                            <form method="post" action="admin-delete.php" class="d-inline" onsubmit="return confirm('Delete this admin?')">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Admin"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-end mt-4">
        <a href="index.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>
</div>
</main>
<?php include 'admin_footer.php'; ?>
