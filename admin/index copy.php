<?php
require_once __DIR__ . '/../app_security.php';
require_admin();
include_once("../db_conn.php");
$openDataIssues = (int) $pdo->query('SELECT COUNT(*) FROM data_quality_issues WHERE resolved_at IS NULL')->fetchColumn();
?><?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-4 mb-4">
                <div class="card flex-fill text-bg-primary shadow-sm" style="min-width:220px;">
                    <div class="card-body d-flex align-items-center">
                        <i class="fa fa-users fa-2x me-3"></i>
                        <div>
                            <h5 class="card-title mb-0">Accounts</h5>
                            <div class="display-6">
                                <?php echo (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card flex-fill text-bg-warning shadow-sm" style="min-width:220px;">
                    <div class="card-body d-flex align-items-center">
                        <i class="fa fa-user-shield fa-2x me-3"></i>
                        <div>
                            <h5 class="card-title mb-0">Admins</h5>
                            <div class="display-6">
                                <?php echo (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h3 class="mb-3"><i class="fa fa-home me-2"></i>Welcome, <?php echo htmlspecialchars($_SESSION['user']['first_name'] ?? 'Admin'); ?>!</h3>
                    <p class="lead">This is your secure admin dashboard. Use the sidebar to manage user accounts and admin accounts, or log out safely when finished.</p>
                </div>
            </div>
            <?php if ($openDataIssues > 0 && admin_can('manage_users')): ?>
                <div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i><?= $openDataIssues ?> unresolved data-quality issue<?= $openDataIssues === 1 ? '' : 's' ?> require administrator review before the final uniqueness migration can be applied.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>
<?php include 'admin_footer.php'; ?>
