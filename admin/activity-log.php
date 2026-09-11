<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_audit');
require_once __DIR__ . '/../db_conn.php';

$logs = $pdo->query('SELECT l.*, a.username AS admin_username FROM admin_activity_log l LEFT JOIN admin_users a ON a.id = l.admin_id ORDER BY l.created_at DESC, l.id DESC LIMIT 100')->fetchAll();
$securityEvents = $pdo->query('SELECT * FROM security_event_log ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll();
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1"><i class="fa fa-history me-2"></i>Activity Log</h2>
                        <p class="text-muted mb-0">Recent administrative actions.</p>
                    </div>
                    <span class="badge bg-secondary">Last 100 actions</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Admin</th><th>Action</th><th>Target User</th><th>Details</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['admin_username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= $log['target_user_id'] === null ? '-' : (int) $log['target_user_id'] ?></td>
                                <td><?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="h4 mb-1"><i class="fa fa-shield-alt me-2"></i>Security Events</h2><p class="text-muted mb-0">Authentication, logout, and statement issuance events.</p></div><span class="badge bg-secondary">Last 100 events</span></div>
                <div class="table-responsive"><table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Date</th><th>Realm</th><th>Event</th><th>Actor</th><th>Subject</th><th>IP</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php foreach ($securityEvents as $event): ?><tr>
                        <td><?= htmlspecialchars($event['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($event['realm'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= $event['actor_id'] === null ? '-' : (int) $event['actor_id'] ?></td>
                        <td><?= $event['subject_id'] === null ? '-' : (int) $event['subject_id'] ?></td>
                        <td><?= htmlspecialchars($event['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($event['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr><?php endforeach; ?>
                    <?php if (!$securityEvents): ?><tr><td colspan="7" class="text-center text-muted py-4">No security events recorded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
