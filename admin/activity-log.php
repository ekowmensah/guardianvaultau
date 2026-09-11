<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_audit');
require_once __DIR__ . '/../db_conn.php';

$logs = $pdo->query('SELECT l.*, a.username AS admin_username FROM admin_activity_log l LEFT JOIN admin_users a ON a.id = l.admin_id ORDER BY l.created_at DESC, l.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$securityEvents = $pdo->query('SELECT * FROM security_event_log ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Audit trail</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-history me-2"></i>Activity Log</h1>
                    <p class="mb-0">Review administrator actions, authentication events, and security records.</p>
                </div>
                <span class="btn btn-light disabled px-4">Last 100 rows each</span>
            </div>
        </section>

        <section class="admin-card overflow-hidden mb-3">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 fw-bold mb-1">Administrative actions</h2>
                    <p class="small text-muted mb-0">User/account changes made by admin users.</p>
                </div>
                <span class="admin-badge admin-badge-muted"><?= count($logs) ?> rows</span>
            </div>
            <div class="table-responsive">
                <table class="table admin-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small text-muted"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($log['admin_username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="admin-badge admin-badge-success"><?= htmlspecialchars(str_replace('_', ' ', $log['action']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $log['target_user_id'] === null ? '-' : '#' . (int) $log['target_user_id'] ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><tr><td colspan="6"><div class="admin-empty">No activity recorded yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card overflow-hidden">
            <div class="admin-card-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 fw-bold mb-1"><i class="fa fa-shield-alt me-2 text-primary"></i>Security events</h2>
                    <p class="small text-muted mb-0">Authentication, logout, MFA, and statement issuance events.</p>
                </div>
                <span class="admin-badge admin-badge-muted"><?= count($securityEvents) ?> rows</span>
            </div>
            <div class="table-responsive">
                <table class="table admin-table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Realm</th>
                            <th>Event</th>
                            <th>Actor</th>
                            <th>Subject</th>
                            <th>IP</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($securityEvents as $event): ?>
                        <tr>
                            <td class="small text-muted"><?= htmlspecialchars($event['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($event['realm'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="admin-badge admin-badge-muted"><?= htmlspecialchars(str_replace('_', ' ', $event['event_type']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $event['actor_id'] === null ? '-' : '#' . (int) $event['actor_id'] ?></td>
                            <td><?= $event['subject_id'] === null ? '-' : '#' . (int) $event['subject_id'] ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($event['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($event['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$securityEvents): ?><tr><td colspan="7"><div class="admin-empty">No security events recorded yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
