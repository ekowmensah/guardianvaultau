<?php
require_once __DIR__ . '/../app_security.php';
require_admin();
include_once("../db_conn.php");

function gv_dashboard_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gv_dashboard_scalar(PDO $pdo, string $sql, array $params = [], mixed $fallback = 0): mixed
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? $fallback : $value;
    } catch (Throwable) {
        return $fallback;
    }
}

function gv_dashboard_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function gv_dashboard_number(float|int|string|null $value, int $decimals = 0): string
{
    return number_format((float) ($value ?? 0), $decimals);
}

function gv_dashboard_money(float|int|string|null $value): string
{
    return '$' . number_format((float) ($value ?? 0), 2);
}

function gv_dashboard_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTime($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return '—';
    }
}

$adminUser = $_SESSION['user'] ?? [];
$adminName = trim((string) (($adminUser['first_name'] ?? '') . ' ' . ($adminUser['last_name'] ?? '')));
$adminName = $adminName !== '' ? $adminName : (string) ($adminUser['username'] ?? 'Admin');
$adminRole = str_replace('_', ' ', (string) ($_SESSION['admin_role'] ?? ($adminUser['role'] ?? 'admin')));

$totalAccounts = (int) gv_dashboard_scalar($pdo, 'SELECT COUNT(*) FROM users');
$activeAccounts = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'Active'");
$suspendedAccounts = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'Suspended'");
$closedAccounts = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'Closed'");
$completeProfiles = (int) gv_dashboard_scalar(
    $pdo,
    'SELECT COUNT(*)
       FROM users u
       INNER JOIN userprofile up ON up.user_id = u.id
       INNER JOIN item_details i ON i.user_id = u.id
       INNER JOIN state_of_items s ON s.user_id = u.id
       INNER JOIN next_of_kin kin ON kin.user_id = u.id'
);
$totalAdmins = (int) gv_dashboard_scalar($pdo, 'SELECT COUNT(*) FROM admin_users');
$activeAdmins = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM admin_users WHERE status = 'Active'");
$mfaAdmins = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM admin_users WHERE totp_secret IS NOT NULL AND totp_secret <> ''");
$openDataIssues = (int) gv_dashboard_scalar($pdo, 'SELECT COUNT(*) FROM data_quality_issues WHERE resolved_at IS NULL');
$vaultItems = (int) gv_dashboard_scalar($pdo, 'SELECT COUNT(*) FROM item_details');
$totalWeight = (float) gv_dashboard_scalar($pdo, 'SELECT COALESCE(SUM(total_weight), 0) FROM item_details');
$totalCurrentValue = (float) gv_dashboard_scalar($pdo, 'SELECT COALESCE(SUM(current_gold_worth), 0) FROM state_of_items');
$totalSafeKeeping = (float) gv_dashboard_scalar($pdo, 'SELECT COALESCE(SUM(cost_of_safe_keeping), 0) FROM state_of_items');
$statementsIssued = (int) gv_dashboard_scalar($pdo, 'SELECT COUNT(*) FROM account_statements');
$failedLogins24h = (int) gv_dashboard_scalar($pdo, "SELECT COUNT(*) FROM login_attempts WHERE successful = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");

$profileCompletion = $totalAccounts > 0 ? (int) round(($completeProfiles / $totalAccounts) * 100) : 0;
$activeAccountRate = $totalAccounts > 0 ? (int) round(($activeAccounts / $totalAccounts) * 100) : 0;
$mfaCoverage = $totalAdmins > 0 ? (int) round(($mfaAdmins / $totalAdmins) * 100) : 0;

$recentAccounts = gv_dashboard_rows(
    $pdo,
    'SELECT id, username, first_name, last_name, email, status, created_at
       FROM users
      ORDER BY created_at DESC, id DESC
      LIMIT 5'
);

$recentActivity = admin_can('view_audit') ? gv_dashboard_rows(
    $pdo,
    'SELECT aal.action, aal.target_user_id, aal.details, aal.created_at, admins.username AS admin_username
       FROM admin_activity_log aal
       LEFT JOIN admin_users admins ON admins.id = aal.admin_id
      ORDER BY aal.created_at DESC
      LIMIT 5'
) : [];

$quickLinks = [
    [
        'title' => 'New Account',
        'description' => 'Create a customer with deposit and beneficiary details.',
        'href' => 'user-form.php?step=account',
        'icon' => 'fa-user-plus',
        'capability' => 'manage_users',
    ],
    [
        'title' => 'View Accounts',
        'description' => 'Search, update, and review customer vault records.',
        'href' => 'user-list.php',
        'icon' => 'fa-users',
        'capability' => 'view_users',
    ],
    [
        'title' => 'List Records',
        'description' => 'Open the compact record listing for quick checks.',
        'href' => 'list_records.php',
        'icon' => 'fa-table',
        'capability' => null,
    ],
    [
        'title' => 'Manage Admins',
        'description' => 'Add administrators and review access levels.',
        'href' => 'admin-list.php',
        'icon' => 'fa-user-shield',
        'capability' => 'manage_admins',
    ],
    [
        'title' => 'Activity Log',
        'description' => 'Audit recent admin actions and changes.',
        'href' => 'activity-log.php',
        'icon' => 'fa-history',
        'capability' => 'view_audit',
    ],
    [
        'title' => 'Security',
        'description' => 'Set up or review multi-factor authentication.',
        'href' => 'mfa-setup.php',
        'icon' => 'fa-mobile-alt',
        'capability' => null,
    ],
];

$visibleQuickLinks = array_values(array_filter($quickLinks, static function (array $link): bool {
    return $link['capability'] === null || admin_can((string) $link['capability']);
}));
?>
<?php include 'admin_header.php'; ?>
</nav>
<main class="col-md-10 ms-sm-auto main-content dashboard-page">
    <style>
        .dashboard-page {
            color: #162033;
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, 0.12), transparent 34rem),
                linear-gradient(180deg, #f6f8fc 0%, #eef3f9 100%);
            min-height: 100vh;
            padding: 1.25rem !important;
        }

        .dashboard-shell {
            max-width: 1480px;
            margin: 0 auto;
        }

        .dashboard-hero {
            position: relative;
            overflow: hidden;
            border: 0;
            border-radius: 22px;
            background:
                linear-gradient(135deg, rgba(10, 28, 58, 0.96), rgba(19, 68, 140, 0.94)),
                radial-gradient(circle at 92% 15%, rgba(255, 193, 7, 0.45), transparent 16rem);
            color: #fff;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
        }

        .dashboard-hero::after {
            content: "";
            position: absolute;
            inset: auto -8rem -12rem auto;
            width: 25rem;
            height: 25rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.11);
        }

        .dashboard-hero > * {
            position: relative;
            z-index: 1;
        }

        .dashboard-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .13);
            color: rgba(255, 255, 255, .86);
            font-size: .74rem;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .hero-metric {
            min-width: 7.75rem;
            padding: .8rem .9rem;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 16px;
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(12px);
        }

        .hero-metric span {
            display: block;
            color: rgba(255, 255, 255, .74);
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .hero-metric strong {
            display: block;
            margin-top: .25rem;
            font-size: 1.05rem;
            line-height: 1;
            max-width: 9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-card {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 18px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 10px 28px rgba(15, 23, 42, .065);
        }

        .stat-card {
            min-height: 116px;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 48px rgba(15, 23, 42, .12);
        }

        .stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.45rem;
            height: 2.45rem;
            border-radius: 13px;
            color: #fff;
            background: linear-gradient(135deg, #0d6efd, #3b82f6);
            box-shadow: 0 12px 26px rgba(13, 110, 253, .25);
        }

        .stat-icon.gold {
            background: linear-gradient(135deg, #b7791f, #f59e0b);
            box-shadow: 0 12px 26px rgba(245, 158, 11, .24);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #047857, #10b981);
            box-shadow: 0 12px 26px rgba(16, 185, 129, .22);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #6d28d9, #8b5cf6);
            box-shadow: 0 12px 26px rgba(139, 92, 246, .22);
        }

        .stat-copy {
            min-width: 0;
            padding-right: .75rem;
        }

        .stat-value {
            max-width: 100%;
            overflow: hidden;
            color: #0f172a;
            font-size: clamp(1.3rem, 1.65vw, 1.85rem);
            font-weight: 800;
            line-height: 1.04;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .muted-label {
            color: #64748b;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .quick-link {
            display: flex;
            gap: .85rem;
            height: 100%;
            padding: .8rem;
            color: inherit;
            text-decoration: none;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 15px;
            background: #fff;
            transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
        }

        .quick-link:hover {
            color: inherit;
            transform: translateY(-2px);
            border-color: rgba(13, 110, 253, .32);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .08);
        }

        .quick-link-icon {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 13px;
            color: #0d6efd;
            background: #eef5ff;
        }

        .progress-thin {
            height: .55rem;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .progress-thin .progress-bar {
            border-radius: inherit;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .25rem .55rem;
            font-weight: 700;
            font-size: .72rem;
        }

        .status-pill.active {
            color: #047857;
            background: #dcfce7;
        }

        .status-pill.suspended {
            color: #b45309;
            background: #fef3c7;
        }

        .status-pill.closed {
            color: #b91c1c;
            background: #fee2e2;
        }

        .activity-dot {
            width: .7rem;
            height: .7rem;
            border-radius: 999px;
            background: #0d6efd;
            box-shadow: 0 0 0 .28rem rgba(13, 110, 253, .12);
            margin-top: .45rem;
        }

        .table-dashboard th {
            color: #64748b;
            font-size: .78rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table-dashboard td,
        .table-dashboard th {
            padding-top: .65rem;
            padding-bottom: .65rem;
        }

        .empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            background: #f8fafc;
        }

        .dashboard-page h1 {
            font-size: clamp(1.6rem, 2.5vw, 2.45rem);
        }

        .dashboard-page .lead {
            font-size: .98rem;
        }

        .dashboard-page .h4 {
            font-size: 1.08rem;
        }

        .compact-pad {
            padding: 1rem !important;
        }

        .compact-panel-head {
            padding: 1rem 1rem .5rem !important;
        }

        @media (max-width: 767.98px) {
            .dashboard-page {
                padding: 1rem !important;
            }

            .dashboard-hero {
                border-radius: 22px;
            }
        }
    </style>

    <div class="dashboard-shell">
        <section class="dashboard-hero p-3 p-xl-4 mb-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7">
                    <div class="dashboard-eyebrow mb-3">
                        <i class="fa fa-shield-alt"></i>
                        Guardian Vault Admin
                    </div>
                    <h1 class="display-6 fw-bold mb-2">Welcome back, <?= gv_dashboard_h($adminName) ?></h1>
                    <p class="lead mb-0 text-white-50">Monitor accounts, deposits, admin access, and security signals from one workspace.</p>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex flex-wrap gap-3 justify-content-lg-end">
                        <div class="hero-metric">
                            <span>Role</span>
                            <strong><?= gv_dashboard_h(ucwords($adminRole)) ?></strong>
                        </div>
                        <div class="hero-metric">
                            <span>Active accounts</span>
                            <strong><?= $activeAccountRate ?>%</strong>
                        </div>
                        <div class="hero-metric">
                            <span>Today</span>
                            <strong><?= gv_dashboard_h(date('M j')) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($openDataIssues > 0 && admin_can('manage_users')): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-3 py-3">
                <div class="d-flex gap-3">
                    <i class="fa fa-exclamation-triangle fs-4 mt-1"></i>
                    <div>
                        <div class="fw-bold"><?= $openDataIssues ?> unresolved data-quality issue<?= $openDataIssues === 1 ? '' : 's' ?></div>
                        <div class="small">Review duplicate or incomplete records before applying final uniqueness migrations.</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="dashboard-card stat-card compact-pad h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-copy">
                            <div class="muted-label mb-2">Total Accounts</div>
                            <div class="stat-value mb-1"><?= gv_dashboard_number($totalAccounts) ?></div>
                            <div class="small text-muted"><?= gv_dashboard_number($activeAccounts) ?> active &middot; <?= gv_dashboard_number($suspendedAccounts + $closedAccounts) ?> inactive</div>
                        </div>
                        <span class="stat-icon"><i class="fa fa-users"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="dashboard-card stat-card compact-pad h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-copy">
                            <div class="muted-label mb-2">Vault Value</div>
                            <div class="stat-value mb-1" title="<?= gv_dashboard_h(gv_dashboard_money($totalCurrentValue)) ?>"><?= gv_dashboard_money($totalCurrentValue) ?></div>
                            <div class="small text-muted"><?= gv_dashboard_number($totalWeight, 2) ?> kg total recorded weight</div>
                        </div>
                        <span class="stat-icon gold"><i class="fa fa-coins"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="dashboard-card stat-card compact-pad h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-copy">
                            <div class="muted-label mb-2">Deposit Records</div>
                            <div class="stat-value mb-1"><?= gv_dashboard_number($vaultItems) ?></div>
                            <div class="small text-muted"><?= gv_dashboard_number($completeProfiles) ?> complete customer profile<?= $completeProfiles === 1 ? '' : 's' ?></div>
                        </div>
                        <span class="stat-icon green"><i class="fa fa-box-open"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="dashboard-card stat-card compact-pad h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-copy">
                            <div class="muted-label mb-2">Security</div>
                            <div class="stat-value mb-1"><?= gv_dashboard_number($failedLogins24h) ?></div>
                            <div class="small text-muted">failed login<?= $failedLogins24h === 1 ? '' : 's' ?> in 24 hours</div>
                        </div>
                        <span class="stat-icon purple"><i class="fa fa-lock"></i></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="dashboard-card compact-pad h-100">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-3">
                        <div>
                            <div class="muted-label mb-1">Operations</div>
                            <h2 class="h4 fw-bold mb-1">Quick actions</h2>
                            <p class="text-muted mb-0">Shortcuts for the work admins use most.</p>
                        </div>
                        <?php if (admin_can('manage_users')): ?>
                            <a href="user-form.php?step=account" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-plus me-2"></i>Add Account
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($visibleQuickLinks as $link): ?>
                            <div class="col-md-6 col-xxl-4">
                                <a class="quick-link" href="<?= gv_dashboard_h($link['href']) ?>">
                                    <span class="quick-link-icon"><i class="fa <?= gv_dashboard_h($link['icon']) ?>"></i></span>
                                    <span>
                                        <span class="fw-bold d-block"><?= gv_dashboard_h($link['title']) ?></span>
                                        <span class="small text-muted"><?= gv_dashboard_h($link['description']) ?></span>
                                    </span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="dashboard-card compact-pad h-100">
                    <div class="muted-label mb-1">Health</div>
                    <h2 class="h4 fw-bold mb-3">System snapshot</h2>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-semibold mb-2">
                            <span>Profile completion</span>
                            <span><?= $profileCompletion ?>%</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar bg-success" style="width: <?= $profileCompletion ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-semibold mb-2">
                            <span>Active account rate</span>
                            <span><?= $activeAccountRate ?>%</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar" style="width: <?= $activeAccountRate ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-semibold mb-2">
                            <span>Admin MFA coverage</span>
                            <span><?= $mfaCoverage ?>%</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar bg-warning" style="width: <?= $mfaCoverage ?>%"></div>
                        </div>
                    </div>

                    <div class="row g-3 pt-2">
                        <div class="col-6">
                            <div class="p-2 rounded-4 bg-light">
                                <div class="small text-muted">Admins</div>
                                <div class="fs-4 fw-bold"><?= gv_dashboard_number($activeAdmins) ?>/<?= gv_dashboard_number($totalAdmins) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-4 bg-light">
                                <div class="small text-muted">Statements</div>
                                <div class="fs-4 fw-bold"><?= gv_dashboard_number($statementsIssued) ?></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 rounded-4 bg-light">
                                <div class="small text-muted">Safe keeping value</div>
                                <div class="fs-4 fw-bold"><?= gv_dashboard_money($totalSafeKeeping) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-xl-7">
                <div class="dashboard-card overflow-hidden h-100">
                    <div class="compact-panel-head d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div class="muted-label mb-1">Accounts</div>
                            <h2 class="h4 fw-bold mb-0">Recently added</h2>
                        </div>
                        <?php if (admin_can('view_users')): ?>
                            <a href="user-list.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">View all</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($recentAccounts): ?>
                        <div class="table-responsive">
                            <table class="table table-dashboard align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Customer</th>
                                        <th>Account</th>
                                        <th>Status</th>
                                        <th class="pe-4">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAccounts as $account): ?>
                                        <?php
                                        $customerName = trim((string) (($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')));
                                        $customerName = $customerName !== '' ? $customerName : 'Unnamed customer';
                                        $status = strtolower((string) ($account['status'] ?? ''));
                                        $statusClass = in_array($status, ['active', 'suspended', 'closed'], true) ? $status : 'closed';
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?= gv_dashboard_h($customerName) ?></div>
                                                <div class="small text-muted"><?= gv_dashboard_h($account['email'] ?? 'No email') ?></div>
                                            </td>
                                            <td class="font-monospace small"><?= gv_dashboard_h($account['username'] ?? '') ?></td>
                                            <td><span class="status-pill <?= gv_dashboard_h($statusClass) ?>"><?= gv_dashboard_h($account['status'] ?? 'Unknown') ?></span></td>
                                            <td class="pe-4 small text-muted"><?= gv_dashboard_h(gv_dashboard_datetime($account['created_at'] ?? null)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3">
                            <div class="empty-state p-3 text-center text-muted">
                                <i class="fa fa-users fs-2 mb-3 d-block"></i>
                                No customer accounts yet.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="dashboard-card compact-pad h-100">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                        <div>
                            <div class="muted-label mb-1">Audit</div>
                            <h2 class="h4 fw-bold mb-0">Recent activity</h2>
                        </div>
                        <?php if (admin_can('view_audit')): ?>
                            <a href="activity-log.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">Open log</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!admin_can('view_audit')): ?>
                        <div class="empty-state p-3 text-center text-muted">
                            <i class="fa fa-lock fs-2 mb-3 d-block"></i>
                            Activity is available to audit-enabled admins.
                        </div>
                    <?php elseif ($recentActivity): ?>
                        <div class="vstack gap-3">
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="d-flex gap-3">
                                    <span class="activity-dot flex-shrink-0"></span>
                                    <div>
                                        <div class="fw-bold"><?= gv_dashboard_h(ucwords(str_replace('_', ' ', (string) $activity['action']))) ?></div>
                                        <div class="small text-muted">
                                            <?= gv_dashboard_h($activity['details'] ?: 'No details recorded') ?>
                                            <?php if (!empty($activity['target_user_id'])): ?>
                                                · User #<?= (int) $activity['target_user_id'] ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= gv_dashboard_h($activity['admin_username'] ?? 'System') ?> · <?= gv_dashboard_h(gv_dashboard_datetime($activity['created_at'] ?? null)) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state p-3 text-center text-muted">
                            <i class="fa fa-history fs-2 mb-3 d-block"></i>
                            No recent admin activity recorded.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>
<?php include 'admin_footer.php'; ?>
