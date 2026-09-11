<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_users');
require_once __DIR__ . '/../db_conn.php';

function gv_view_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gv_view_display(mixed $value, bool $multiline = false): string
{
    if ($value === null || $value === '') {
        return '<span class="admin-badge admin-badge-muted">N/A</span>';
    }

    $escaped = gv_view_h($value);
    return $multiline ? nl2br($escaped) : $escaped;
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header('Location: user-list.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status, created_at, updated_at FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $profileStmt = $pdo->prepare('SELECT * FROM userprofile WHERE user_id = ?');
    $profileStmt->execute([$user_id]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $itemStmt = $pdo->prepare('SELECT * FROM item_details WHERE user_id = ?');
    $itemStmt->execute([$user_id]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stateStmt = $pdo->prepare('SELECT * FROM state_of_items WHERE user_id = ?');
    $stateStmt->execute([$user_id]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $kinStmt = $pdo->prepare('SELECT * FROM next_of_kin WHERE user_id = ?');
    $kinStmt->execute([$user_id]);
    $kin = $kinStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('Admin user view error: ' . $e->getMessage());
    include 'admin_header.php';
    ?>
    <main class="col-md-10 ms-sm-auto main-content">
        <div class="admin-shell">
            <div class="alert alert-danger">Unable to load the user record.</div>
            <a href="user-list.php" class="btn btn-outline-secondary rounded-pill px-4">Back to users</a>
        </div>
    </main>
    <?php
    include 'admin_footer.php';
    exit;
}

$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$fullName = $fullName !== '' ? $fullName : 'Unnamed customer';
$statusClass = $user['status'] === 'Active' ? 'admin-badge-success' : ($user['status'] === 'Suspended' ? 'admin-badge-warning' : 'admin-badge-muted');

$sections = [
    'Account holder' => [
        'icon' => 'fa-user',
        'data' => [
            'Full name' => $fullName,
            'Username' => $user['username'] ?? '',
            'Email' => $user['email'] ?? '',
            'Telephone' => $user['telephone_number'] ?? '',
            'Role' => $user['role'] ?? '',
            'Created' => $user['created_at'] ?? '',
            'Updated' => $user['updated_at'] ?? '',
        ],
    ],
    'Profile' => [
        'icon' => 'fa-address-card',
        'data' => [
            'Nationality' => $profile['nationality'] ?? null,
            'Marital status' => $profile['married_status'] ?? null,
            'Has child' => $profile['has_child'] ?? null,
            'Child name' => $profile['child_name'] ?? null,
            'Address' => $profile['address'] ?? null,
        ],
        'multiline' => ['Address'],
    ],
    'Deposit' => [
        'icon' => 'fa-box-open',
        'data' => [
            'Insurance number' => $item['insurance_number'] ?? null,
            'Reference code' => $item['reference_code'] ?? null,
            'Transaction code' => $item['transaction_code'] ?? null,
            'Box dimension' => $item['box_dimension'] ?? null,
            'Deposited item' => $item['deposited_item'] ?? null,
            'Package type' => $item['package_type'] ?? null,
            'Package quantity' => $item['package_quantity'] ?? null,
            'Total weight' => $item['total_weight'] ?? null,
            'Deposit date' => $item['deposit_date'] ?? null,
            'Monthly charges' => $item['monthly_charges'] ?? null,
            'Amount paid' => $item['amount_paid'] ?? null,
            'Currency' => $item['currency'] ?? null,
        ],
        'multiline' => ['Deposited item'],
    ],
    'Value & safe keeping' => [
        'icon' => 'fa-warehouse',
        'data' => [
            'Current gold worth' => $state['current_gold_worth'] ?? null,
            'Price per kilogram' => $state['price_per_kilogram'] ?? null,
            'Cost of safe keeping' => $state['cost_of_safe_keeping'] ?? null,
            'Date of safe keeping' => $state['date_of_safe_keeping'] ?? null,
            'Quantity' => $state['quantity'] ?? null,
            'Currency' => $state['currency'] ?? null,
        ],
    ],
    'Beneficiary' => [
        'icon' => 'fa-users',
        'data' => [
            'Name' => $kin['name_of_beneficial'] ?? null,
            'Relationship' => $kin['relation_with_user'] ?? null,
            'Date of birth' => $kin['date_of_birth'] ?? null,
            'Email' => $kin['email_address'] ?? null,
            'Telephone' => $kin['telephone_number_kin'] ?? null,
            'Address' => $kin['address'] ?? null,
        ],
        'multiline' => ['Address'],
    ],
];

include 'admin_header.php';
?>
<main class="col-md-10 ms-sm-auto main-content">
    <style>
        .record-section {
            break-inside: avoid;
        }

        .record-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .65rem;
        }

        .record-field {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 14px;
            background: #fff;
            padding: .75rem;
            min-width: 0;
        }

        .record-field-value {
            overflow-wrap: anywhere;
        }

        @media (max-width: 767.98px) {
            .record-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .sidebar,
            .navbar,
            .print-hide {
                display: none !important;
            }

            .main-content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .admin-page-header,
            .admin-card {
                box-shadow: none !important;
            }
        }
    </style>

    <div class="admin-shell">
        <section class="admin-page-header mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                <div>
                    <div class="admin-eyebrow mb-1">Current account record</div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa fa-user-circle me-2"></i><?= gv_view_h($fullName) ?></h1>
                    <p class="mb-0">
                        <span class="font-monospace"><?= gv_view_h($user['username']) ?></span>
                        <span class="text-white-50 ms-2">Account ID: USR-<?= str_pad((string) $user['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 print-hide">
                    <span class="btn btn-light disabled px-4"><?= gv_view_h($user['status']) ?></span>
                    <button type="button" class="btn btn-warning px-4" id="printBtn"><i class="fa fa-print me-2"></i>Print</button>
                    <?php if (admin_can('manage_users')): ?><a href="user-form.php?id=<?= (int) $user['id'] ?>" class="btn btn-light px-4"><i class="fa fa-edit me-2"></i>Edit</a><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="admin-card admin-card-body h-100">
                    <div class="admin-label mb-2">Status</div>
                    <span class="admin-badge <?= $statusClass ?>"><?= gv_view_h($user['status']) ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card admin-card-body h-100">
                    <div class="admin-label mb-2">Email</div>
                    <div class="fw-bold text-truncate"><?= gv_view_h($user['email'] ?: '-') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card admin-card-body h-100">
                    <div class="admin-label mb-2">Reference</div>
                    <div class="fw-bold font-monospace text-truncate"><?= gv_view_h($item['reference_code'] ?? '-') ?></div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <?php foreach ($sections as $title => $section): ?>
                <div class="col-xl-6">
                    <article class="admin-card record-section h-100">
                        <div class="admin-card-header">
                            <h2 class="h5 fw-bold mb-0"><i class="fa <?= gv_view_h($section['icon']) ?> me-2 text-primary"></i><?= gv_view_h($title) ?></h2>
                        </div>
                        <div class="admin-card-body">
                            <div class="record-grid">
                                <?php foreach ($section['data'] as $label => $value): ?>
                                    <div class="record-field">
                                        <div class="admin-label mb-1"><?= gv_view_h($label) ?></div>
                                        <div class="record-field-value"><?= gv_view_display($value, in_array($label, $section['multiline'] ?? [], true)) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="d-flex justify-content-between align-items-center gap-2 mt-3 print-hide">
            <div class="small text-muted">This is the live database record, not an immutable account statement.</div>
            <a href="user-list.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
</main>
<script>
document.getElementById('printBtn')?.addEventListener('click', function () {
    window.print();
});
</script>
<?php include 'admin_footer.php'; ?>
