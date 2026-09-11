<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_users');
require_once __DIR__ . '/../db_conn.php';

$stmt = $pdo->query('SELECT p.user_id, p.nationality, p.married_status, p.has_child, p.child_name, p.address, u.username, u.first_name, u.last_name, u.status FROM userprofile p INNER JOIN users u ON u.id = p.user_id ORDER BY u.id DESC');
$records = $stmt->fetchAll();
include 'admin_header.php';
?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1"><i class="fa fa-address-card me-2 text-primary"></i>User Profiles</h1>
                <p class="text-muted mb-0">Profile information linked to each customer account.</p>
            </div>
            <a href="user-list.php" class="btn btn-outline-secondary">Account list</a>
        </div>
        <div class="row g-4">
            <?php foreach ($records as $record): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 card-title"><?= htmlspecialchars(trim($record['first_name'] . ' ' . $record['last_name']) ?: $record['username'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="text-muted small"><?= htmlspecialchars($record['username'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8') ?></p>
                            <dl class="mb-3">
                                <dt>Nationality</dt><dd><?= htmlspecialchars($record['nationality'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt>Marital status</dt><dd><?= htmlspecialchars($record['married_status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt>Has child</dt><dd><?= htmlspecialchars($record['has_child'] ?? '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt>Child name</dt><dd><?= htmlspecialchars($record['child_name'] ?: '-', ENT_QUOTES, 'UTF-8') ?></dd>
                                <dt>Address</dt><dd><?= nl2br(htmlspecialchars($record['address'] ?? '-', ENT_QUOTES, 'UTF-8')) ?></dd>
                            </dl>
                            <a href="user-view.php?id=<?= (int) $record['user_id'] ?>" class="btn btn-outline-primary btn-sm">View account</a>
                            <?php if (admin_can('manage_users')): ?><a href="user-form.php?id=<?= (int) $record['user_id'] ?>" class="btn btn-outline-secondary btn-sm">Edit</a><?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
            <?php if (!$records): ?><div class="col-12"><div class="alert alert-info">No profiles found.</div></div><?php endif; ?>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
