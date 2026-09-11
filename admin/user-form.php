<?php
declare(strict_types=1);

require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_users');
require_once __DIR__ . '/../db_conn.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = db_connection();

function gv_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gv_redirect(string $step, ?int $userId = null): void
{
    $location = 'user-form.php?step=' . rawurlencode($step);
    if ($userId !== null && $userId > 0) {
        $location .= '&id=' . $userId;
    }
    header('Location: ' . $location);
    exit;
}

function gv_blank_user_form(): array
{
    return [
        'username' => 'ACC' . strtoupper(bin2hex(random_bytes(6))),
        'password_hash' => '',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'telephone_number' => '',
        'role' => 'User',
        'status' => 'Active',
        'nationality' => '',
        'married_status' => 'Single',
        'has_child' => 'No',
        'child_name' => '',
        'address' => '',
        'insurance_number' => 'INS' . strtoupper(bin2hex(random_bytes(8))),
        'reference_code' => 'REF' . strtoupper(bin2hex(random_bytes(8))),
        'transaction_code' => 'TRX' . strtoupper(bin2hex(random_bytes(8))),
        'box_dimension' => '',
        'deposited_item' => '',
        'package_type' => '',
        'package_quantity' => '',
        'total_weight' => '',
        'deposit_date' => '',
        'monthly_charges' => '',
        'amount_paid' => '',
        'quantity' => '',
        'current_gold_worth' => '',
        'price_per_kilogram' => '',
        'cost_of_safe_keeping' => '',
        'date_of_safe_keeping' => '',
        'name_of_beneficial' => '',
        'relation_with_user' => '',
        'date_of_birth' => '',
        'email_address' => '',
        'telephone_number_kin' => '',
    ];
}

function gv_form_from_database(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        exit('User not found.');
    }

    $form = gv_blank_user_form();
    $form['password_hash'] = '__existing__';

    foreach ($user as $key => $value) {
        if ($key !== 'id') {
            $form[$key] = $value ?? '';
        }
    }

    foreach (['userprofile', 'item_details', 'state_of_items', 'next_of_kin'] as $table) {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($row as $key => $value) {
            if (!in_array($key, ['id', 'user_id', 'created_at', 'updated_at'], true)) {
                $form[$key] = $value ?? '';
            }
        }
    }

    return $form;
}

function gv_value(string $field): string
{
    return (string) ($_SESSION['user_form'][$field] ?? '');
}

function gv_error(array $errors, string $field): string
{
    return empty($errors[$field]) ? '' : '<span class="text-danger small ms-2">' . gv_h($errors[$field]) . '</span>';
}

function gv_required_fields(string $step, bool $editMode): array
{
    $fields = [
        'account' => ['username', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status', 'nationality', 'married_status', 'has_child', 'address'],
        'item' => ['insurance_number', 'reference_code', 'transaction_code', 'box_dimension', 'deposited_item', 'package_type', 'package_quantity', 'total_weight', 'deposit_date', 'monthly_charges', 'amount_paid', 'quantity', 'current_gold_worth', 'price_per_kilogram', 'cost_of_safe_keeping', 'date_of_safe_keeping'],
        'kin' => ['name_of_beneficial', 'relation_with_user', 'date_of_birth', 'email_address', 'telephone_number_kin'],
    ];

    if (!$editMode) {
        $fields['account'][] = 'password';
    }

    if (gv_value('has_child') === 'Yes') {
        $fields['account'][] = 'child_name';
    }

    return $fields[$step] ?? [];
}

function gv_step_for_field(string $field): string
{
    if (in_array($field, gv_required_fields('item', true), true)) {
        return 'item';
    }
    if (in_array($field, gv_required_fields('kin', true), true)) {
        return 'kin';
    }
    return 'account';
}

function gv_collect_posted_fields(string $step): void
{
    $fieldsByStep = [
        'account' => ['username', 'password', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status', 'nationality', 'married_status', 'has_child', 'child_name', 'address'],
        'item' => ['insurance_number', 'reference_code', 'transaction_code', 'box_dimension', 'deposited_item', 'package_type', 'package_quantity', 'total_weight', 'deposit_date', 'monthly_charges', 'amount_paid', 'quantity', 'current_gold_worth', 'price_per_kilogram', 'cost_of_safe_keeping', 'date_of_safe_keeping'],
        'kin' => ['name_of_beneficial', 'relation_with_user', 'date_of_birth', 'email_address', 'telephone_number_kin'],
    ];

    foreach ($fieldsByStep[$step] ?? [] as $field) {
        if ($field === 'password') {
            $password = (string) ($_POST['password'] ?? '');
            if ($password !== '') {
                $_SESSION['user_form']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $_SESSION['user_form']['_last_password_length'] = strlen($password);
            }
            continue;
        }

        $_SESSION['user_form'][$field] = is_string($_POST[$field] ?? null)
            ? trim((string) $_POST[$field])
            : ($_POST[$field] ?? '');
    }

    $_SESSION['user_form']['role'] = 'User';

    if (isset($_SESSION['user_form']['email'])) {
        $_SESSION['user_form']['email'] = strtolower(trim((string) $_SESSION['user_form']['email']));
    }
    if (isset($_SESSION['user_form']['email_address'])) {
        $_SESSION['user_form']['email_address'] = strtolower(trim((string) $_SESSION['user_form']['email_address']));
    }
    if (gv_value('has_child') === 'No') {
        $_SESSION['user_form']['child_name'] = '';
    }
}

function gv_validate_steps(PDO $pdo, array $steps, bool $editMode, ?int $editId): array
{
    $errors = [];
    $data = $_SESSION['user_form'] ?? [];

    foreach ($steps as $step) {
        foreach (gv_required_fields($step, $editMode) as $field) {
            if ($field === 'password') {
                if (empty($data['password_hash'])) {
                    $errors['password'] = 'Password is required.';
                } elseif ((int) ($data['_last_password_length'] ?? 0) < 8) {
                    $errors['password'] = 'Password must be at least 8 characters.';
                    unset($_SESSION['user_form']['password_hash']);
                }
                continue;
            }

            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
    }

    foreach ([
        'username' => 255,
        'first_name' => 255,
        'last_name' => 255,
        'email' => 255,
        'telephone_number' => 30,
        'nationality' => 100,
        'child_name' => 255,
        'insurance_number' => 255,
        'reference_code' => 255,
        'transaction_code' => 255,
        'box_dimension' => 100,
        'package_type' => 100,
        'name_of_beneficial' => 255,
        'relation_with_user' => 100,
        'email_address' => 255,
        'telephone_number_kin' => 30,
        'address' => 5000,
        'deposited_item' => 5000,
    ] as $field => $limit) {
        if (isset($data[$field]) && mb_strlen((string) $data[$field], 'UTF-8') > $limit) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be {$limit} characters or fewer.";
        }
    }

    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid account email address.';
    }
    if (!empty($data['email_address']) && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
        $errors['email_address'] = 'Enter a valid beneficiary email address.';
    }
    if (($data['role'] ?? '') !== 'User') {
        $errors['role'] = 'Choose a valid account role.';
    }
    if (!in_array($data['status'] ?? '', ['Active', 'Suspended', 'Closed'], true)) {
        $errors['status'] = 'Choose a valid account status.';
    }
    if (!in_array($data['married_status'] ?? '', ['Single', 'Married', 'Divorced'], true)) {
        $errors['married_status'] = 'Choose a valid marital status.';
    }
    if (!in_array($data['has_child'] ?? '', ['Yes', 'No'], true)) {
        $errors['has_child'] = 'Choose Yes or No for child status.';
    }

    foreach (['package_quantity', 'total_weight', 'monthly_charges', 'amount_paid', 'quantity', 'current_gold_worth', 'price_per_kilogram', 'cost_of_safe_keeping'] as $field) {
        if (isset($data[$field]) && trim((string) $data[$field]) !== '' && (!is_numeric($data[$field]) || (float) $data[$field] < 0)) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a non-negative number.';
        }
    }
    foreach (['package_quantity', 'quantity'] as $field) {
        if (isset($data[$field]) && trim((string) $data[$field]) !== '' && filter_var($data[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a whole number greater than zero.';
        }
    }
    foreach (['telephone_number', 'telephone_number_kin'] as $field) {
        if (!empty($data[$field]) && !preg_match('/^[0-9+() .-]{7,30}$/', (string) $data[$field])) {
            $errors[$field] = 'Enter a valid telephone number.';
        }
    }
    foreach (['deposit_date', 'date_of_safe_keeping', 'date_of_birth'] as $field) {
        if (!empty($data[$field])) {
            $date = DateTime::createFromFormat('Y-m-d', (string) $data[$field]);
            if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid date.';
            } elseif ($date > new DateTime('today')) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' cannot be in the future.';
            }
        }
    }

    if (!empty($data['username'])) {
        $query = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $params = [$data['username']];
        if ($editMode && $editId) {
            $query .= ' AND id <> ?';
            $params[] = $editId;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors['username'] = 'That username is already in use.';
        }
    }

    if (!empty($data['email'])) {
        $query = 'SELECT COUNT(*) FROM users WHERE LOWER(TRIM(email)) = ?';
        $params = [$data['email']];
        if ($editMode && $editId) {
            $query .= ' AND id <> ?';
            $params[] = $editId;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors['email'] = 'That email address is already in use.';
        }
    }

    return $errors;
}

function gv_save_user_form(PDO $pdo, bool $editMode, ?int $editId): int
{
    $data = $_SESSION['user_form'];
    $pdo->beginTransaction();

    try {
        if ($editMode && $editId) {
            $stmt = $pdo->prepare('UPDATE users SET username=?, first_name=?, last_name=?, email=?, telephone_number=?, role=?, status=?, session_version=session_version + IF(status <> ?, 1, 0) WHERE id=?');
            $stmt->execute([$data['username'], $data['first_name'], $data['last_name'], $data['email'], $data['telephone_number'], 'User', $data['status'], $data['status'], $editId]);
            $userId = $editId;
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, telephone_number, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['username'], $data['password_hash'], $data['first_name'], $data['last_name'], $data['email'], $data['telephone_number'], 'User', $data['status']]);
            $userId = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare('INSERT INTO userprofile (user_id, nationality, married_status, has_child, child_name, address) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nationality=VALUES(nationality), married_status=VALUES(married_status), has_child=VALUES(has_child), child_name=VALUES(child_name), address=VALUES(address)');
        $stmt->execute([$userId, $data['nationality'], $data['married_status'], $data['has_child'], $data['child_name'] ?? '', $data['address']]);

        $stmt = $pdo->prepare('INSERT INTO item_details (user_id, insurance_number, reference_code, transaction_code, box_dimension, deposited_item, package_type, package_quantity, total_weight, deposit_date, monthly_charges, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE insurance_number=VALUES(insurance_number), reference_code=VALUES(reference_code), transaction_code=VALUES(transaction_code), box_dimension=VALUES(box_dimension), deposited_item=VALUES(deposited_item), package_type=VALUES(package_type), package_quantity=VALUES(package_quantity), total_weight=VALUES(total_weight), deposit_date=VALUES(deposit_date), monthly_charges=VALUES(monthly_charges), amount_paid=VALUES(amount_paid)');
        $stmt->execute([$userId, $data['insurance_number'], $data['reference_code'], $data['transaction_code'], $data['box_dimension'], $data['deposited_item'], $data['package_type'], $data['package_quantity'], $data['total_weight'], $data['deposit_date'], $data['monthly_charges'], $data['amount_paid']]);

        $stmt = $pdo->prepare('INSERT INTO state_of_items (user_id, quantity, current_gold_worth, price_per_kilogram, cost_of_safe_keeping, date_of_safe_keeping) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), current_gold_worth=VALUES(current_gold_worth), price_per_kilogram=VALUES(price_per_kilogram), cost_of_safe_keeping=VALUES(cost_of_safe_keeping), date_of_safe_keeping=VALUES(date_of_safe_keeping)');
        $stmt->execute([$userId, $data['quantity'], $data['current_gold_worth'], $data['price_per_kilogram'], $data['cost_of_safe_keeping'], $data['date_of_safe_keeping']]);

        $stmt = $pdo->prepare('INSERT INTO next_of_kin (user_id, name_of_beneficial, relation_with_user, date_of_birth, email_address, telephone_number_kin) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name_of_beneficial=VALUES(name_of_beneficial), relation_with_user=VALUES(relation_with_user), date_of_birth=VALUES(date_of_birth), email_address=VALUES(email_address), telephone_number_kin=VALUES(telephone_number_kin)');
        $stmt->execute([$userId, $data['name_of_beneficial'], $data['relation_with_user'], $data['date_of_birth'], $data['email_address'], $data['telephone_number_kin']]);

        $snapshot = $data;
        unset($snapshot['password_hash'], $snapshot['_last_password_length']);
        record_user_revision($pdo, $userId, $editMode ? 'update' : 'create', $snapshot);
        $pdo->commit();

        log_admin_action($editMode ? 'update_user' : 'create_user', $userId, 'User account and related records saved');
        return $userId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$steps = ['account', 'item', 'kin'];
$stepLabels = ['account' => 'Account Profile', 'item' => 'Deposit Value', 'kin' => 'Beneficiary Save'];
$stepDescriptions = [
    'account' => 'Create the login identity and personal profile.',
    'item' => 'Record deposit, package, value, and safe-keeping details.',
    'kin' => 'Add beneficiary details and save the account.',
];
$stepIcons = ['account' => 'fa-id-card', 'item' => 'fa-box-open', 'kin' => 'fa-user-friends'];

$editId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : null;
$editMode = $editId !== null && $editId > 0;
$step = $_GET['step'] ?? 'account';
if (!in_array($step, $steps, true)) {
    $step = 'account';
}

$context = $editMode ? 'edit:' . $editId : 'create';
if (($_SESSION['user_form_context'] ?? null) !== $context) {
    $_SESSION['user_form_context'] = $context;
    $_SESSION['user_form'] = $editMode ? gv_form_from_database($pdo, (int) $editId) : gv_blank_user_form();
}
if (empty($_SESSION['user_form']) || !is_array($_SESSION['user_form'])) {
    $_SESSION['user_form'] = $editMode ? gv_form_from_database($pdo, (int) $editId) : gv_blank_user_form();
}
$_SESSION['user_form']['role'] = 'User';
$_SESSION['user_form']['status'] = gv_value('status') ?: 'Active';
$_SESSION['user_form']['has_child'] = gv_value('has_child') ?: 'No';
$_SESSION['user_form']['married_status'] = gv_value('married_status') ?: 'Single';

$errors = [];
$fatalError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $postedStep = $_POST['current_step'] ?? $step;
        if (!in_array($postedStep, $steps, true)) {
            $postedStep = $step;
        }

        gv_collect_posted_fields($postedStep);

        $action = $_POST['wizard_action'] ?? 'next';
        if (!in_array($action, ['back', 'next', 'save'], true)) {
            $action = 'next';
        }

        $currentIndex = array_search($postedStep, $steps, true);
        $currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;

        if ($action === 'back') {
            gv_redirect($steps[max(0, $currentIndex - 1)], $editId);
        }

        if ($action === 'next') {
            $errors = gv_validate_steps($pdo, [$postedStep], $editMode, $editId);
            if (empty($errors)) {
                gv_redirect($steps[min(count($steps) - 1, $currentIndex + 1)], $editId);
            }
            $step = $postedStep;
        }

        if ($action === 'save') {
            $errors = gv_validate_steps($pdo, $steps, $editMode, $editId);
            if (empty($errors)) {
                gv_save_user_form($pdo, $editMode, $editId);
                unset($_SESSION['user_form'], $_SESSION['user_form_context']);
                header('Location: user-list.php');
                exit;
            }

            foreach (array_keys($errors) as $field) {
                $step = gv_step_for_field($field);
                break;
            }
        }
    } catch (Throwable $exception) {
        error_log('Admin user form error: ' . $exception->getMessage());
        $fatalError = 'Unable to save the user record. Please check the details and try again.';
    }
}

$stepNumber = (int) array_search($step, $steps, true) + 1;
$editSuffix = $editMode ? '&amp;id=' . (int) $editId : '';
$formAction = 'user-form.php?step=' . rawurlencode($step) . ($editMode ? '&id=' . (int) $editId : '');
$summaryName = trim(gv_value('first_name') . ' ' . gv_value('last_name'));

include __DIR__ . '/admin_header.php';
?>
<style>
    .gv-user-page{--gv-primary:#2442d8;--gv-ink:#172033;--gv-muted:#667085;--gv-line:#e6eaf2;--gv-soft:#f8faff;color:var(--gv-ink)}
    .gv-page-shell{max-width:1180px}
    .gv-page-hero{background:linear-gradient(135deg,#172033 0%,#2442d8 100%);border:0;border-radius:22px;box-shadow:0 18px 45px rgba(23,32,51,.18);color:#fff;overflow:hidden;position:relative}
    .gv-page-hero::after{background:radial-gradient(circle,rgba(255,255,255,.22),transparent 62%);content:"";height:220px;position:absolute;right:-70px;top:-90px;width:220px}
    .gv-form-card,.gv-summary{border:1px solid var(--gv-line);border-radius:22px;box-shadow:0 14px 40px rgba(23,32,51,.08)}
    .gv-stepper{display:grid;gap:.65rem;grid-template-columns:repeat(3,minmax(0,1fr))}
    .gv-stepper .nav-link{align-items:center;background:#fff;border:1px solid var(--gv-line);border-radius:14px;color:var(--gv-muted);display:flex;gap:.65rem;min-height:58px;padding:.65rem .8rem}
    .gv-stepper .nav-link.active{background:rgba(36,66,216,.08);border-color:rgba(36,66,216,.32);color:var(--gv-primary);font-weight:700}
    .gv-step-number{align-items:center;background:#eef2ff;border-radius:999px;display:inline-flex;flex:0 0 30px;height:30px;justify-content:center;width:30px}
    .gv-stepper .active .gv-step-number{background:var(--gv-primary);color:#fff}
    .gv-user-form{display:grid;gap:.8rem;grid-template-columns:repeat(12,minmax(0,1fr))}
    .gv-field{grid-column:span 6}.gv-field-wide,.gv-section-title,.gv-user-form>.alert,.gv-actions{grid-column:1/-1}
    @media (min-width:1200px){.gv-field{grid-column:span 4}.gv-field-wide{grid-column:span 8}}
    .gv-section-title{align-items:center;border-bottom:1px solid var(--gv-line);color:var(--gv-ink);display:flex;font-size:.88rem;font-weight:800;gap:.55rem;letter-spacing:.03em;margin:.35rem 0 .1rem;padding:.25rem 0 .65rem;text-transform:uppercase}
    .gv-user-form label{color:#344054;font-size:.78rem;font-weight:700;margin-bottom:.32rem}
    .gv-user-form .form-control,.gv-user-form .form-select,.gv-user-form .input-group-text{border-color:#d9e0ec;border-radius:10px;font-size:.92rem;min-height:40px}
    .gv-user-form .input-group .form-control{border-bottom-left-radius:0;border-top-left-radius:0}.gv-user-form textarea.form-control{min-height:82px;resize:vertical}
    .gv-actions{background:rgba(255,255,255,.95);border-top:1px solid var(--gv-line);bottom:0;margin:.5rem -1rem -1rem;padding:.9rem 1rem;position:sticky;z-index:5}
    .gv-summary{background:var(--gv-soft);padding:.95rem}.gv-side-panel{position:sticky;top:1rem}.gv-summary-label{color:var(--gv-muted);font-size:.72rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase}.gv-summary-value{font-size:.92rem;font-weight:700;word-break:break-word}
    @media (max-width:767.98px){.gv-user-page.main-content{padding:1rem}.gv-stepper,.gv-user-form{grid-template-columns:1fr}.gv-field,.gv-field-wide{grid-column:1/-1}.gv-side-panel{position:static}}
</style>
<main class="col-md-10 ms-sm-auto main-content gv-user-page">
    <div class="container-fluid">
        <div class="gv-page-shell mx-auto">
            <div class="card gv-page-hero mb-3">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                        <div class="flex-grow-1">
                            <div class="small text-white-50 fw-semibold mb-1"><?= $editMode ? 'Edit client account' : 'New client account' ?></div>
                            <h3 class="mb-1"><?= gv_h($stepLabels[$step]) ?></h3>
                            <p class="mb-0 text-white-50"><?= gv_h($stepDescriptions[$step]) ?></p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill bg-light text-dark px-3 py-2">Step <?= $stepNumber ?> of 3</span>
                            <a href="user-list.php" class="btn btn-sm btn-outline-light"><i class="fa fa-times me-1"></i> Close</a>
                        </div>
                    </div>
                    <div class="progress mt-3 bg-white bg-opacity-25" style="height:6px">
                        <div class="progress-bar bg-warning" role="progressbar" style="width:<?= $stepNumber * 33.33 ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card gv-form-card">
                        <div class="card-body p-3">
                            <ul class="nav gv-stepper mb-3" id="userFormSteps">
                                <?php foreach ($steps as $index => $stepKey): ?>
                                    <?php $isEnabled = $editMode || $index <= array_search($step, $steps, true); ?>
                                    <li class="nav-item">
                                        <a class="nav-link<?= $step === $stepKey ? ' active' : '' ?><?= $isEnabled ? '' : ' disabled' ?>" href="?step=<?= gv_h($stepKey) ?><?= $editSuffix ?>">
                                            <span class="gv-step-number"><?= $index + 1 ?></span>
                                            <span><?= gv_h($stepLabels[$stepKey]) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <form method="post" action="<?= gv_h($formAction) ?>" autocomplete="off" class="gv-user-form">
                                <input type="hidden" name="current_step" value="<?= gv_h($step) ?>">
                                <input type="hidden" name="csrf_token" value="<?= gv_h(csrf_token()) ?>">

                                <?php if ($fatalError !== ''): ?>
                                    <div class="alert alert-danger mb-0"><?= gv_h($fatalError) ?></div>
                                <?php elseif (!empty($errors)): ?>
                                    <div class="alert alert-danger mb-0">
                                        <div class="fw-bold mb-1"><i class="fa fa-exclamation-triangle me-1"></i> Please check the details below.</div>
                                        <div class="small"><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> need attention.</div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($step === 'account'): ?>
                                    <h5 class="gv-section-title"><i class="fa fa-id-card text-primary"></i> Account profile</h5>
                                    <div class="gv-field"><label class="form-label">Account Number <?= gv_error($errors, 'username') ?></label><input type="text" name="username" class="form-control" value="<?= gv_h(gv_value('username')) ?>" readonly required></div>
                                    <?php if (!$editMode): ?><div class="gv-field"><label class="form-label">Password <?= gv_error($errors, 'password') ?></label><div class="input-group"><input type="password" name="password" class="form-control" id="password-field" minlength="8" autocomplete="new-password" required><button type="button" class="btn btn-outline-secondary" id="toggle-password" aria-label="Show password"><i class="fa fa-eye"></i></button></div></div><?php endif; ?>
                                    <div class="gv-field"><label class="form-label">First Name <?= gv_error($errors, 'first_name') ?></label><input type="text" name="first_name" class="form-control" value="<?= gv_h(gv_value('first_name')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Last Name <?= gv_error($errors, 'last_name') ?></label><input type="text" name="last_name" class="form-control" value="<?= gv_h(gv_value('last_name')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Email <?= gv_error($errors, 'email') ?></label><input type="email" name="email" class="form-control" value="<?= gv_h(gv_value('email')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Telephone <?= gv_error($errors, 'telephone_number') ?></label><input type="text" name="telephone_number" class="form-control" value="<?= gv_h(gv_value('telephone_number')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Status <?= gv_error($errors, 'status') ?></label><select name="status" class="form-select" required><?php foreach (['Active', 'Suspended', 'Closed'] as $status): ?><option value="<?= gv_h($status) ?>" <?= gv_value('status') === $status ? 'selected' : '' ?>><?= gv_h($status) ?></option><?php endforeach; ?></select></div>
                                    <input type="hidden" name="role" value="User">
                                    <h5 class="gv-section-title"><i class="fa fa-user-circle text-primary"></i> Personal details</h5>
                                    <div class="gv-field"><label class="form-label">Nationality <?= gv_error($errors, 'nationality') ?></label><input type="text" name="nationality" class="form-control" value="<?= gv_h(gv_value('nationality')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Marital Status <?= gv_error($errors, 'married_status') ?></label><select name="married_status" class="form-select" required><?php foreach (['Single', 'Married', 'Divorced'] as $status): ?><option value="<?= gv_h($status) ?>" <?= gv_value('married_status') === $status ? 'selected' : '' ?>><?= gv_h($status) ?></option><?php endforeach; ?></select></div>
                                    <div class="gv-field"><label class="form-label">Has Child <?= gv_error($errors, 'has_child') ?></label><select name="has_child" class="form-select" id="has-child" required><option value="No" <?= gv_value('has_child') === 'No' ? 'selected' : '' ?>>No</option><option value="Yes" <?= gv_value('has_child') === 'Yes' ? 'selected' : '' ?>>Yes</option></select></div>
                                    <div class="gv-field" id="child-name-wrap"><label class="form-label">Child Name <?= gv_error($errors, 'child_name') ?></label><input type="text" name="child_name" id="child-name" class="form-control" value="<?= gv_h(gv_value('child_name')) ?>"></div>
                                    <div class="gv-field-wide"><label class="form-label">Address <?= gv_error($errors, 'address') ?></label><textarea name="address" class="form-control" rows="2" required><?= gv_h(gv_value('address')) ?></textarea></div>
                                    <div class="d-flex justify-content-end gap-2 gv-actions"><a href="user-list.php" class="btn btn-outline-secondary">Cancel</a><button type="submit" name="wizard_action" value="next" class="btn btn-primary">Next: Deposit Value <i class="fa fa-arrow-right ms-1"></i></button></div>
                                <?php elseif ($step === 'item'): ?>
                                    <div class="alert alert-info d-flex align-items-start gap-2 mb-0"><i class="fa fa-info-circle mt-1"></i><div>Deposit details and value are combined here so you can finish the account faster.</div></div>
                                    <h5 class="gv-section-title"><i class="fa fa-box-open text-primary"></i> Deposit details</h5>
                                    <div class="gv-field"><label class="form-label">Insurance Number <?= gv_error($errors, 'insurance_number') ?></label><input type="text" name="insurance_number" class="form-control" value="<?= gv_h(gv_value('insurance_number')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Reference Code <?= gv_error($errors, 'reference_code') ?></label><input type="text" name="reference_code" class="form-control" value="<?= gv_h(gv_value('reference_code')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Transaction Code <?= gv_error($errors, 'transaction_code') ?></label><input type="text" name="transaction_code" class="form-control" value="<?= gv_h(gv_value('transaction_code')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Box Dimension <?= gv_error($errors, 'box_dimension') ?></label><input type="text" name="box_dimension" class="form-control" list="boxDimensionOptions" placeholder="e.g. 30 x 20 x 15 cm" value="<?= gv_h(gv_value('box_dimension')) ?>" required><datalist id="boxDimensionOptions"><option value="30 x 20 x 15 cm"><option value="40 x 30 x 20 cm"><option value="60 x 40 x 40 cm"></datalist></div>
                                    <div class="gv-field"><label class="form-label">Package Type <?= gv_error($errors, 'package_type') ?></label><input type="text" name="package_type" class="form-control" list="packageTypeOptions" value="<?= gv_h(gv_value('package_type')) ?>" required><datalist id="packageTypeOptions"><option value="Safe Trunk"><option value="Seal Trunks"><option value="Box"><option value="Crate"><option value="Other"></datalist></div>
                                    <div class="gv-field"><label class="form-label">Package Quantity <?= gv_error($errors, 'package_quantity') ?></label><input type="number" name="package_quantity" class="form-control" value="<?= gv_h(gv_value('package_quantity')) ?>" min="1" required></div>
                                    <div class="gv-field-wide"><label class="form-label">Deposited Item <?= gv_error($errors, 'deposited_item') ?></label><textarea name="deposited_item" class="form-control" rows="2" required><?= gv_h(gv_value('deposited_item')) ?></textarea></div>
                                    <h5 class="gv-section-title"><i class="fa fa-chart-line text-success"></i> Value & safe-keeping</h5>
                                    <div class="gv-field"><label class="form-label">Total Weight (kg) <?= gv_error($errors, 'total_weight') ?></label><input type="number" name="total_weight" class="form-control" value="<?= gv_h(gv_value('total_weight')) ?>" step="0.01" min="0" required></div>
                                    <div class="gv-field"><label class="form-label">Deposit Date <?= gv_error($errors, 'deposit_date') ?></label><input type="date" name="deposit_date" class="form-control" value="<?= gv_h(gv_value('deposit_date')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Monthly Charges <?= gv_error($errors, 'monthly_charges') ?></label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="monthly_charges" class="form-control" value="<?= gv_h(gv_value('monthly_charges')) ?>" step="0.01" min="0" required></div></div>
                                    <div class="gv-field"><label class="form-label">Amount Paid <?= gv_error($errors, 'amount_paid') ?></label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="amount_paid" class="form-control" value="<?= gv_h(gv_value('amount_paid')) ?>" step="0.01" min="0" required></div></div>
                                    <div class="gv-field"><label class="form-label">Quantity <?= gv_error($errors, 'quantity') ?></label><input type="number" name="quantity" class="form-control" value="<?= gv_h(gv_value('quantity')) ?>" min="1" required></div>
                                    <div class="gv-field"><label class="form-label">Current Gold Worth <?= gv_error($errors, 'current_gold_worth') ?></label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="current_gold_worth" class="form-control" value="<?= gv_h(gv_value('current_gold_worth')) ?>" step="0.01" min="0" required></div></div>
                                    <div class="gv-field"><label class="form-label">Price Per Kilogram <?= gv_error($errors, 'price_per_kilogram') ?></label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="price_per_kilogram" class="form-control" value="<?= gv_h(gv_value('price_per_kilogram')) ?>" step="0.01" min="0" required></div></div>
                                    <div class="gv-field"><label class="form-label">Cost of Safe Keeping <?= gv_error($errors, 'cost_of_safe_keeping') ?></label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="cost_of_safe_keeping" class="form-control" value="<?= gv_h(gv_value('cost_of_safe_keeping')) ?>" step="0.01" min="0" required></div></div>
                                    <div class="gv-field"><label class="form-label">Date of Safe Keeping <?= gv_error($errors, 'date_of_safe_keeping') ?></label><input type="date" name="date_of_safe_keeping" class="form-control" value="<?= gv_h(gv_value('date_of_safe_keeping')) ?>" required></div>
                                    <div class="d-flex justify-content-between gap-2 gv-actions"><button type="submit" name="wizard_action" value="back" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i> Back</button><button type="submit" name="wizard_action" value="next" class="btn btn-primary">Next: Beneficiary Save <i class="fa fa-arrow-right ms-1"></i></button></div>
                                <?php elseif ($step === 'kin'): ?>
                                    <h5 class="gv-section-title"><i class="fa fa-user-friends text-primary"></i> Beneficiary save</h5>
                                    <div class="gv-field"><label class="form-label">Full Name <?= gv_error($errors, 'name_of_beneficial') ?></label><input type="text" name="name_of_beneficial" class="form-control" value="<?= gv_h(gv_value('name_of_beneficial')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Relationship <?= gv_error($errors, 'relation_with_user') ?></label><input type="text" name="relation_with_user" class="form-control" value="<?= gv_h(gv_value('relation_with_user')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Date of Birth <?= gv_error($errors, 'date_of_birth') ?></label><input type="date" name="date_of_birth" class="form-control" value="<?= gv_h(gv_value('date_of_birth')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Email Address <?= gv_error($errors, 'email_address') ?></label><input type="email" name="email_address" class="form-control" value="<?= gv_h(gv_value('email_address')) ?>" required></div>
                                    <div class="gv-field"><label class="form-label">Telephone Number <?= gv_error($errors, 'telephone_number_kin') ?></label><input type="text" name="telephone_number_kin" class="form-control" value="<?= gv_h(gv_value('telephone_number_kin')) ?>" required></div>
                                    <div class="d-flex justify-content-between gap-2 gv-actions"><button type="submit" name="wizard_action" value="back" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i> Back</button><button type="submit" name="wizard_action" value="save" class="btn btn-success">Save Account <i class="fa fa-check ms-1"></i></button></div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <aside class="gv-side-panel">
                        <div class="gv-summary mb-3">
                            <div class="gv-summary-label mb-1">Current record</div>
                            <div class="gv-summary-value"><?= gv_h($summaryName !== '' ? $summaryName : 'Name not entered') ?></div>
                            <div class="small text-muted mt-1"><?= gv_h(gv_value('username') ?: 'Account number pending') ?></div>
                            <div class="small text-muted"><?= gv_h(gv_value('email') ?: 'Email not entered') ?></div>
                        </div>
                        <div class="gv-summary">
                            <div class="gv-summary-label mb-2">Wizard flow</div>
                            <?php foreach ($steps as $index => $stepKey): ?>
                                <div class="d-flex gap-2<?= $index < 2 ? ' mb-2' : '' ?>">
                                    <i class="fa <?= gv_h($stepIcons[$stepKey]) ?> <?= $step === $stepKey ? 'text-primary' : 'text-muted' ?> mt-1"></i>
                                    <div class="small"><strong><?= $index + 1 ?>. <?= gv_h($stepLabels[$stepKey]) ?></strong><br><span class="text-muted"><?= gv_h($stepDescriptions[$stepKey]) ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var passwordField = document.getElementById('password-field');
    var passwordToggle = document.getElementById('toggle-password');
    if (passwordField && passwordToggle) {
        passwordToggle.addEventListener('click', function () {
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
            passwordToggle.innerHTML = passwordField.type === 'password' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>';
        });
    }

    var hasChild = document.getElementById('has-child');
    var childName = document.getElementById('child-name');
    var childWrap = document.getElementById('child-name-wrap');
    function syncChildField() {
        if (!hasChild || !childName || !childWrap) return;
        var show = hasChild.value === 'Yes';
        childWrap.classList.toggle('d-none', !show);
        childName.required = show;
        if (!show) childName.value = '';
    }
    if (hasChild) {
        hasChild.addEventListener('change', syncChildField);
        syncChildField();
    }
});
</script>
<?php include __DIR__ . '/admin_footer.php'; ?>
