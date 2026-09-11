<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_admins');
include_once("../db_conn.php");

$admins = $pdo->query("SELECT id, username, role, status, totp_secret IS NOT NULL AND totp_secret <> '' AS mfa_enabled, created_at FROM admin_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$totalAdmins = count($admins);
$activeAdmins = count(array_filter($admins, static fn (array $admin): bool => ($admin['status'] ?? '') === 'Active'));
$mfaAdmins = count(array_filter($admins, static fn (array $admin): bool => (bool) $admin['mfa_enabled']));
$superAdmins = count(array_filter($admins, static fn (array $admin): bool => ($admin['role'] ?? '') === 'super_admin'));
?>
<?php include_once("admin_header.php"); ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Access control</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-user-shield me-2"></i>Admin Management</h1>
                    <p class="mb-0">Manage administrator accounts, roles, status, and MFA coverage.</p>
                </div>
                <a href="admin-form.php" class="btn btn-light px-4">
                    <i class="fa fa-plus me-2"></i>Add Admin
                </a>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="admin-card admin-stat-card admin-card-body h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="admin-label mb-2">Total Admins</div>
                            <div class="admin-value"><?= $totalAdmins ?></div>
                        </div>
                        <span class="admin-stat-icon"><i class="fa fa-user-shield"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="admin-card admin-stat-card admin-card-body h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="admin-label mb-2">Active</div>
                            <div class="admin-value"><?= $activeAdmins ?></div>
                        </div>
                        <span class="admin-stat-icon"><i class="fa fa-check"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="admin-card admin-stat-card admin-card-body h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="admin-label mb-2">Super Admins</div>
                            <div class="admin-value"><?= $superAdmins ?></div>
                        </div>
                        <span class="admin-stat-icon"><i class="fa fa-crown"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="admin-card admin-stat-card admin-card-body h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="admin-label mb-2">MFA Enabled</div>
                            <div class="admin-value"><?= $mfaAdmins ?></div>
                        </div>
                        <span class="admin-stat-icon"><i class="fa fa-mobile-alt"></i></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-card overflow-hidden">
            <div class="admin-card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1"><i class="fa fa-list me-2 text-primary"></i>Administrator list</h2>
                    <p class="small text-muted mb-0">Keep at least one active super administrator.</p>
                </div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa fa-arrow-left me-1"></i>Dashboard
                </a>
            </div>
            <div class="table-responsive">
                <table class="table admin-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>MFA</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td class="text-muted">#<?= (int) $row['id'] ?></td>
                            <td>
                                <span class="fw-bold text-primary"><i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['role'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge <?= $row['status'] === 'Active' ? 'admin-badge-success' : 'admin-badge-warning' ?>">
                                    <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-badge <?= $row['mfa_enabled'] ? 'admin-badge-success' : 'admin-badge-warning' ?>">
                                    <?= $row['mfa_enabled'] ? 'Enabled' : 'Not enabled' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="admin-actions">
                                    <a href="admin-form.php?id=<?= (int) $row['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit Admin">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <form method="post" action="admin-delete.php" class="d-inline" onsubmit="return confirm('Delete this admin?')">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Admin">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$admins): ?>
                        <tr><td colspan="6"><div class="admin-empty">No administrators found.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
