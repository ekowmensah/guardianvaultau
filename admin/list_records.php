<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_users');
require_once __DIR__ . '/../db_conn.php';

$stmt = $pdo->query('SELECT p.user_id, p.nationality, p.married_status, p.has_child, p.child_name, p.address, u.username, u.first_name, u.last_name, u.status FROM userprofile p INNER JOIN users u ON u.id = p.user_id ORDER BY u.id DESC');
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$activeProfiles = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Active'));

include 'admin_header.php';
?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Profiles</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-address-card me-2"></i>User Profiles</h1>
                    <p class="mb-0">Compact profile cards linked to each customer account.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="user-list.php" class="btn btn-light px-4">Account List</a>
                    <?php if (admin_can('manage_users')): ?><a href="user-form.php?step=account" class="btn btn-warning px-4"><i class="fa fa-plus me-2"></i>Add</a><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="admin-card admin-card-body admin-stat-card">
                    <div class="admin-label mb-2">Total profiles</div>
                    <div class="admin-value"><?= count($records) ?></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="admin-card admin-card-body admin-stat-card">
                    <div class="admin-label mb-2">Active accounts</div>
                    <div class="admin-value"><?= $activeProfiles ?></div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <?php foreach ($records as $record): ?>
                <?php
                $name = trim((string) (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')));
                $statusClass = ($record['status'] ?? '') === 'Active' ? 'admin-badge-success' : (($record['status'] ?? '') === 'Suspended' ? 'admin-badge-warning' : 'admin-badge-muted');
                ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="admin-card h-100">
                        <div class="admin-card-body">
                            <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
                                <div>
                                    <h2 class="h5 fw-bold mb-1"><?= htmlspecialchars($name ?: $record['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                                    <div class="font-monospace small text-muted"><?= htmlspecialchars($record['username'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <span class="admin-badge <?= $statusClass ?>"><?= htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>

                            <div class="vstack gap-2 small">
                                <div class="d-flex justify-content-between gap-3"><span class="text-muted">Nationality</span><strong><?= htmlspecialchars($record['nationality'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
                                <div class="d-flex justify-content-between gap-3"><span class="text-muted">Marital status</span><strong><?= htmlspecialchars($record['married_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
                                <div class="d-flex justify-content-between gap-3"><span class="text-muted">Has child</span><strong><?= htmlspecialchars($record['has_child'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
                                <div>
                                    <div class="text-muted">Address</div>
                                    <div><?= nl2br(htmlspecialchars($record['address'] ?: '-', ENT_QUOTES, 'UTF-8')) ?></div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <a href="user-view.php?id=<?= (int) $record['user_id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">View</a>
                                <?php if (admin_can('manage_users')): ?><a href="user-form.php?id=<?= (int) $record['user_id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Edit</a><?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>

            <?php if (!$records): ?>
                <div class="col-12"><div class="admin-empty">No profiles found.</div></div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
