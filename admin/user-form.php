<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_users');
include_once("../db_conn.php");

try {
    $edit_mode = false;
    $edit_data = [];
    if (isset($_GET['id'])) {
        $edit_mode = true;
        $user_id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, telephone_number, role, status, session_version, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $edit_data['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_data['users']) {
            http_response_code(404);
            exit('User not found.');
        }
        $tables = ['userprofile', 'item_details', 'state_of_items', 'next_of_kin'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $edit_data[$table] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        // Only reset session form data to current DB values on first GET to edit mode (no step param)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['step'])) {
            $_SESSION['user_form'] = array_merge(
                $edit_data['users'] ?: [],
                $edit_data['userprofile'] ?: [],
                $edit_data['item_details'] ?: [],
                $edit_data['state_of_items'] ?: [],
                $edit_data['next_of_kin'] ?: []
            );
        }
    }
    // Generate auto fields for new user
    if (!$edit_mode) {
        // On GET (not POST), clear any previous session form data for a fresh form
        // Only clear session on first GET (no step param)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['step'])) {
            $_SESSION['user_form'] = [];
        }
        $generated = [
            'username' => 'ACC' . strtoupper(bin2hex(random_bytes(6))),
            'insurance_number' => 'INS' . strtoupper(bin2hex(random_bytes(8))),
            'reference_code' => 'REF' . strtoupper(bin2hex(random_bytes(8))),
            'transaction_code' => 'TRX' . strtoupper(bin2hex(random_bytes(8))),
        ];
    }
    if (!isset($_SESSION['user_form'])) $_SESSION['user_form'] = [];
    $errors = [];
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($edit_mode && (int) ($_POST['edit_id'] ?? 0) !== $user_id) {
            throw new RuntimeException('Edit target mismatch.');
        }
        $step = ['profile' => 'account', 'state' => 'item', 'review' => 'kin'][$_POST['next_step'] ?? 'account'] ?? ($_POST['next_step'] ?? 'account');
        $is_finishing = ($step === 'finish');
        foreach ($_POST as $key => $value) {
            if ($key === 'next_step' || $key === 'edit_id' || $key === 'csrf_token') continue;
            if ($key === 'password') {
                if ($value !== '') $_SESSION['user_form']['password_hash'] = password_hash($value, PASSWORD_DEFAULT);
                continue;
            }
            $_SESSION['user_form'][$key] = $value;
        }
        $step_fields = [
            'account' => array_merge(['username', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status', 'nationality', 'married_status', 'has_child', 'child_name', 'address'], $edit_mode ? [] : ['password']),
            'item' => ['insurance_number', 'reference_code', 'transaction_code', 'box_dimension', 'deposited_item', 'package_type', 'package_quantity', 'total_weight', 'deposit_date', 'monthly_charges', 'amount_paid', 'quantity', 'current_gold_worth', 'price_per_kilogram', 'cost_of_safe_keeping', 'date_of_safe_keeping'],
            'kin' => ['name_of_beneficial', 'relation_with_user', 'date_of_birth', 'email_address', 'telephone_number_kin']
        ];
        $validate_step = ['profile' => 'account', 'state' => 'item', 'review' => 'kin', 'finish' => 'kin'][$_POST['current_step'] ?? $step] ?? ($_POST['current_step'] ?? $step);
        $is_going_back = false;
        $step_keys = array_keys($step_fields);
        if (isset($_POST['next_step'])) {
            $currentStepIndex = array_search($validate_step, $step_keys, true);
            $nextStep = ['profile' => 'account', 'state' => 'item', 'review' => 'kin'][$_POST['next_step']] ?? $_POST['next_step'];
            $nextStepIndex = array_search($nextStep, $step_keys, true);
            $is_going_back = $nextStepIndex !== false && $currentStepIndex !== false && $nextStepIndex < $currentStepIndex;
        }
        // Always validate all fields for the current step unless going back
        if (!$is_going_back && isset($step_fields[$validate_step])) {
            foreach ($step_fields[$validate_step] as $field) {
                $storedField = $field === 'password' ? 'password_hash' : $field;
                if (!isset($_SESSION['user_form'][$storedField]) || $_SESSION['user_form'][$storedField] === '') {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                }
            }
        }
        // Validate all fields before any transaction is opened.
        if ($is_finishing) {
            foreach (array_merge(...array_values($step_fields)) as $field) {
                $storedField = $field === 'password' ? 'password_hash' : $field;
                if (!isset($_SESSION['user_form'][$storedField]) || $_SESSION['user_form'][$storedField] === '') {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                }
            }
        }
        $formData = $_SESSION['user_form'];
        if (isset($formData['email'])) {
            $formData['email'] = strtolower(trim((string) $formData['email']));
            $_SESSION['user_form']['email'] = $formData['email'];
        }
        if (isset($formData['email_address'])) {
            $formData['email_address'] = strtolower(trim((string) $formData['email_address']));
            $_SESSION['user_form']['email_address'] = $formData['email_address'];
        }
        $lengthLimits = [
            'username' => 255, 'first_name' => 255, 'last_name' => 255, 'email' => 255,
            'telephone_number' => 30, 'nationality' => 100, 'child_name' => 255,
            'insurance_number' => 255, 'reference_code' => 255, 'transaction_code' => 255,
            'box_dimension' => 100, 'package_type' => 100, 'name_of_beneficial' => 255,
            'relation_with_user' => 100, 'email_address' => 255, 'telephone_number_kin' => 30,
            'address' => 5000, 'deposited_item' => 5000,
        ];
        foreach ($lengthLimits as $field => $limit) {
            if (isset($formData[$field]) && mb_strlen((string) $formData[$field], 'UTF-8') > $limit) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be {$limit} characters or fewer.";
            }
        }
        if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid account email address.';
        }
        if (!empty($formData['email_address']) && !filter_var($formData['email_address'], FILTER_VALIDATE_EMAIL)) {
            $errors['email_address'] = 'Enter a valid beneficiary email address.';
        }
        if (($formData['role'] ?? '') !== 'User') {
            $errors['role'] = 'Choose a valid account role.';
        }
        if (!in_array($formData['status'] ?? '', ['Active', 'Suspended', 'Closed'], true)) {
            $errors['status'] = 'Choose a valid account status.';
        }
        if (!in_array($formData['married_status'] ?? '', ['Single', 'Married', 'Divorced'], true)) {
            $errors['married_status'] = 'Choose a valid marital status.';
        }
        if (!in_array($formData['has_child'] ?? '', ['Yes', 'No'], true)) {
            $errors['has_child'] = 'Choose Yes or No for child status.';
        }
        if (($formData['has_child'] ?? '') === 'No') {
            $_SESSION['user_form']['child_name'] = '';
            unset($errors['child_name']);
        } elseif (($formData['has_child'] ?? '') === 'Yes' && trim($formData['child_name'] ?? '') === '') {
            $errors['child_name'] = 'Child name is required when the user has a child.';
        }
        foreach (['package_quantity', 'total_weight', 'monthly_charges', 'amount_paid', 'quantity', 'current_gold_worth', 'price_per_kilogram', 'cost_of_safe_keeping'] as $numericField) {
            if (isset($formData[$numericField]) && ($formData[$numericField] === '' || !is_numeric($formData[$numericField]) || (float) $formData[$numericField] < 0)) {
                $errors[$numericField] = ucfirst(str_replace('_', ' ', $numericField)) . ' must be a non-negative number.';
            }
        }
        foreach (['package_quantity', 'quantity'] as $positiveIntegerField) {
            if (isset($formData[$positiveIntegerField]) && filter_var($formData[$positiveIntegerField], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors[$positiveIntegerField] = ucfirst(str_replace('_', ' ', $positiveIntegerField)) . ' must be a whole number greater than zero.';
            }
        }
        if (!$edit_mode && isset($formData['password_hash']) && strlen((string) ($_POST['password'] ?? '')) < 12 && ($_POST['current_step'] ?? '') === 'account') {
            $errors['password'] = 'Password must be at least 12 characters.';
            unset($_SESSION['user_form']['password_hash']);
        }
        foreach (['telephone_number', 'telephone_number_kin'] as $phoneField) {
            if (!empty($formData[$phoneField]) && !preg_match('/^[0-9+() .-]{7,30}$/', (string) $formData[$phoneField])) {
                $errors[$phoneField] = 'Enter a valid telephone number.';
            }
        }
        foreach (['deposit_date', 'date_of_safe_keeping', 'date_of_birth'] as $dateField) {
            if (!empty($formData[$dateField])) {
                $date = DateTime::createFromFormat('Y-m-d', $formData[$dateField]);
                if (!$date || $date->format('Y-m-d') !== $formData[$dateField]) {
                    $errors[$dateField] = ucfirst(str_replace('_', ' ', $dateField)) . ' must be a valid date.';
                } elseif ($date > new DateTime('today')) {
                    $errors[$dateField] = ucfirst(str_replace('_', ' ', $dateField)) . ' cannot be in the future.';
                }
            }
        }
        if (!empty($formData['username'])) {
            $duplicateQuery = 'SELECT COUNT(*) FROM users WHERE username = ?' . (!empty($_POST['edit_id']) ? ' AND id <> ?' : '');
            $duplicateParams = [$formData['username']];
            if (!empty($_POST['edit_id'])) {
                $duplicateParams[] = (int) $_POST['edit_id'];
            }
            $duplicateStmt = $pdo->prepare($duplicateQuery);
            $duplicateStmt->execute($duplicateParams);
            if ((int) $duplicateStmt->fetchColumn() > 0) {
                $errors['username'] = 'That username is already in use.';
            }
        }
        if (!empty($formData['email'])) {
            $duplicateQuery = 'SELECT COUNT(*) FROM users WHERE LOWER(TRIM(email)) = ?' . (!empty($_POST['edit_id']) ? ' AND id <> ?' : '');
            $duplicateParams = [$formData['email']];
            if (!empty($_POST['edit_id'])) {
                $duplicateParams[] = (int) $_POST['edit_id'];
            }
            $duplicateStmt = $pdo->prepare($duplicateQuery);
            $duplicateStmt->execute($duplicateParams);
            if ((int) $duplicateStmt->fetchColumn() > 0) {
                $errors['email'] = 'That email address is already in use.';
            }
        }
        if ($is_finishing && empty($errors)) {
                $pdo->beginTransaction();
                if (!empty($_POST['edit_id'])) {
                    $user_id = intval($_POST['edit_id']);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, first_name=?, last_name=?, email=?, telephone_number=?, role=?, status=?, session_version=session_version + IF(status <> ?, 1, 0) WHERE id=?");
                    $stmt->execute([
                        $_SESSION['user_form']['username'] ?? '',
                        $_SESSION['user_form']['first_name'] ?? '',
                        $_SESSION['user_form']['last_name'] ?? '',
                        $_SESSION['user_form']['email'] ?? '',
                        $_SESSION['user_form']['telephone_number'] ?? '',
                        $_SESSION['user_form']['role'] ?? '',
                        $_SESSION['user_form']['status'] ?? 'Active',
                        $_SESSION['user_form']['status'] ?? 'Active',
                        $user_id
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, email, telephone_number, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_SESSION['user_form']['username'] ?? '',
                        $_SESSION['user_form']['password_hash'] ?? '',
                        $_SESSION['user_form']['first_name'] ?? '',
                        $_SESSION['user_form']['last_name'] ?? '',
                        $_SESSION['user_form']['email'] ?? '',
                        $_SESSION['user_form']['telephone_number'] ?? '',
                        $_SESSION['user_form']['role'] ?? 'User',
                        $_SESSION['user_form']['status'] ?? 'Active'
                    ]);
                    $user_id = $pdo->lastInsertId();
                }
                // Save other table data here
                // 1. User Profile
                if (!empty($_POST['edit_id'])) {
                    // Update userprofile
                    $stmt = $pdo->prepare("INSERT INTO userprofile (user_id, nationality, married_status, has_child, child_name, address) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nationality=VALUES(nationality), married_status=VALUES(married_status), has_child=VALUES(has_child), child_name=VALUES(child_name), address=VALUES(address)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['nationality'] ?? '',
                        $_SESSION['user_form']['married_status'] ?? '',
                        $_SESSION['user_form']['has_child'] ?? '',
                        $_SESSION['user_form']['child_name'] ?? '',
                        $_SESSION['user_form']['address'] ?? ''
                    ]);
                } else {
                    // Insert userprofile
                    $stmt = $pdo->prepare("INSERT INTO userprofile (user_id, nationality, married_status, has_child, child_name, address) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['nationality'] ?? '',
                        $_SESSION['user_form']['married_status'] ?? '',
                        $_SESSION['user_form']['has_child'] ?? '',
                        $_SESSION['user_form']['child_name'] ?? '',
                        $_SESSION['user_form']['address'] ?? ''
                    ]);
                }
                // 2. Item Details
                if (!empty($_POST['edit_id'])) {
                    $stmt = $pdo->prepare("INSERT INTO item_details (user_id, insurance_number, reference_code, transaction_code, box_dimension, deposited_item, package_type, package_quantity, total_weight, deposit_date, monthly_charges, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE insurance_number=VALUES(insurance_number), reference_code=VALUES(reference_code), transaction_code=VALUES(transaction_code), box_dimension=VALUES(box_dimension), deposited_item=VALUES(deposited_item), package_type=VALUES(package_type), package_quantity=VALUES(package_quantity), total_weight=VALUES(total_weight), deposit_date=VALUES(deposit_date), monthly_charges=VALUES(monthly_charges), amount_paid=VALUES(amount_paid)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['insurance_number'] ?? '',
                        $_SESSION['user_form']['reference_code'] ?? '',
                        $_SESSION['user_form']['transaction_code'] ?? '',
                        $_SESSION['user_form']['box_dimension'] ?? '',
                        $_SESSION['user_form']['deposited_item'] ?? '',
                        $_SESSION['user_form']['package_type'] ?? '',
                        $_SESSION['user_form']['package_quantity'] ?? '',
                        $_SESSION['user_form']['total_weight'] ?? '',
                        $_SESSION['user_form']['deposit_date'] ?? '',
                        $_SESSION['user_form']['monthly_charges'] ?? '',
                        $_SESSION['user_form']['amount_paid'] ?? ''
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO item_details (user_id, insurance_number, reference_code, transaction_code, box_dimension, deposited_item, package_type, package_quantity, total_weight, deposit_date, monthly_charges, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['insurance_number'] ?? '',
                        $_SESSION['user_form']['reference_code'] ?? '',
                        $_SESSION['user_form']['transaction_code'] ?? '',
                        $_SESSION['user_form']['box_dimension'] ?? '',
                        $_SESSION['user_form']['deposited_item'] ?? '',
                        $_SESSION['user_form']['package_type'] ?? '',
                        $_SESSION['user_form']['package_quantity'] ?? '',
                        $_SESSION['user_form']['total_weight'] ?? '',
                        $_SESSION['user_form']['deposit_date'] ?? '',
                        $_SESSION['user_form']['monthly_charges'] ?? '',
                        $_SESSION['user_form']['amount_paid'] ?? ''
                    ]);
                }
                // 3. State of Items
                if (!empty($_POST['edit_id'])) {
                    $stmt = $pdo->prepare("INSERT INTO state_of_items (user_id, quantity, current_gold_worth, price_per_kilogram, cost_of_safe_keeping, date_of_safe_keeping) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), current_gold_worth=VALUES(current_gold_worth), price_per_kilogram=VALUES(price_per_kilogram), cost_of_safe_keeping=VALUES(cost_of_safe_keeping), date_of_safe_keeping=VALUES(date_of_safe_keeping)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['quantity'] ?? '',
                        $_SESSION['user_form']['current_gold_worth'] ?? '',
                        $_SESSION['user_form']['price_per_kilogram'] ?? '',
                        $_SESSION['user_form']['cost_of_safe_keeping'] ?? '',
                        $_SESSION['user_form']['date_of_safe_keeping'] ?? ''
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO state_of_items (user_id, quantity, current_gold_worth, price_per_kilogram, cost_of_safe_keeping, date_of_safe_keeping) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['quantity'] ?? '',
                        $_SESSION['user_form']['current_gold_worth'] ?? '',
                        $_SESSION['user_form']['price_per_kilogram'] ?? '',
                        $_SESSION['user_form']['cost_of_safe_keeping'] ?? '',
                        $_SESSION['user_form']['date_of_safe_keeping'] ?? ''
                    ]);
                }
                // 4. Next of Kin
                if (!empty($_POST['edit_id'])) {
                    $stmt = $pdo->prepare("INSERT INTO next_of_kin (user_id, name_of_beneficial, relation_with_user, date_of_birth, email_address, telephone_number_kin) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name_of_beneficial=VALUES(name_of_beneficial), relation_with_user=VALUES(relation_with_user), date_of_birth=VALUES(date_of_birth), email_address=VALUES(email_address), telephone_number_kin=VALUES(telephone_number_kin)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['name_of_beneficial'] ?? '',
                        $_SESSION['user_form']['relation_with_user'] ?? '',
                        $_SESSION['user_form']['date_of_birth'] ?? '',
                        $_SESSION['user_form']['email_address'] ?? '',
                        $_SESSION['user_form']['telephone_number_kin'] ?? ''
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO next_of_kin (user_id, name_of_beneficial, relation_with_user, date_of_birth, email_address, telephone_number_kin) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id,
                        $_SESSION['user_form']['name_of_beneficial'] ?? '',
                        $_SESSION['user_form']['relation_with_user'] ?? '',
                        $_SESSION['user_form']['date_of_birth'] ?? '',
                        $_SESSION['user_form']['email_address'] ?? '',
                        $_SESSION['user_form']['telephone_number_kin'] ?? ''
                    ]);
                }
                $revisionSnapshot = $_SESSION['user_form'];
                unset($revisionSnapshot['password_hash']);
                record_user_revision($pdo, (int) $user_id, !empty($_POST['edit_id']) ? 'update' : 'create', $revisionSnapshot);
                $pdo->commit();
                log_admin_action(!empty($_POST['edit_id']) ? 'update_user' : 'create_user', $user_id, 'User account and related records saved');
                $_SESSION['user_form'] = [];
                header('Location: user-list.php');
                exit;
            } else {
                // Stay on last step and show errors and finish button
                $step = 'kin';
                $is_finishing = true;
            }
        }
        // If errors, do not redirect; show errors on the relevant grouped step.
        if (!empty($errors) && !$is_finishing) {
            // Stay on the current step and show errors
            $step = $_POST['current_step'] ?? $step;
        } elseif (!$is_finishing) {
            if ($is_going_back) {
                // When going back, set $step to the previous step and do NOT redirect
                $step = $_POST['next_step'];
                // Just fall through and re-render that step with session data
            } else {
                // When going forward and there are no errors, redirect to new step
                $step = $_POST['next_step'] ?? $step;
                $editQuery = !empty($_POST['edit_id']) ? '&id=' . (int) $_POST['edit_id'] : '';
                header("Location: user-form.php?step=$step$editQuery");
                exit;
            }
        }
    } catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log('Admin user form error: ' . $e->getMessage());
    echo "<div class='alert alert-danger'>Unable to save the user record.</div>";
}
?>
<?php if (!isset($step)) { $step = $_GET['step'] ?? 'account'; } ?>
<?php $step = ['profile' => 'account', 'state' => 'item', 'review' => 'kin', 'finish' => 'kin'][$step] ?? $step; ?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow mt-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <?php $stepNumber = ['account' => 1, 'item' => 2, 'kin' => 3][$step] ?? 1; ?>
                            <h4 class="mb-0">User Registration &mdash; Step <?= $stepNumber ?> of 3: <?= $stepNumber === 1 ? 'Account &amp; Profile' : ($stepNumber === 2 ? 'Deposit &amp; State' : 'Beneficiary &amp; Save') ?></h4>
                        </div>
                        <div style="width:200px">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $stepNumber * 33.33 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
<ul class="nav nav-pills mb-4" id="userFormSteps">
    <?php $editSuffix = $edit_mode ? '&amp;id=' . (int) $user_id : ''; ?>
    <li class="nav-item"><a class="nav-link<?= $step==='account'?' active':'' ?>" href="?step=account<?= $editSuffix ?>">1. Account &amp; Profile</a></li>
        <li class="nav-item"><a class="nav-link<?= $step==='item'?' active':'' ?><?= ($step==='item'||$step==='kin')?'':' disabled' ?>" href="?step=item<?= $editSuffix ?>">2. Deposit &amp; State</a></li>
        <li class="nav-item"><a class="nav-link<?= $step==='kin'?' active':'' ?><?= ($step==='kin')?'':' disabled' ?>" href="?step=kin<?= $editSuffix ?>">3. Beneficiary &amp; Save</a></li>
</ul>
<form method="post" autocomplete="off">
    <input type="hidden" name="current_step" value="<?= htmlspecialchars($step) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <?php if ($edit_mode): ?>
        <input type="hidden" name="edit_id" value="<?= htmlspecialchars($user_id) ?>">
    <?php endif; ?>
    <?php if (!empty(
        array_filter(array_intersect_key(
            (array)
                (isset(
                    $errors
                ) ? $errors : []),
            array_flip([
                'username','first_name','last_name','email','telephone_number','role','status','password'
            ])
        ))
    ) && $step === 'account'): ?>

    <?php endif; ?>
    <?php if ($step === 'account'): ?>
        <div class="mb-3">
            <label class="form-label">Username (auto-generated)
    <?php if (!empty($errors['username'])): ?><span class="text-danger small ms-2"><?= $errors['username'] ?></span><?php endif; ?>
</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['username'] ?? ($edit_mode ? $edit_data['users']['username'] : ($generated['username'] ?? ''))) ?>" readonly required>
        </div>
        <?php if (!$edit_mode): ?>
        <div class="mb-3">
            <label class="form-label">Password
    <?php if (!empty($errors['password'])): ?><span class="text-danger small ms-2"><?= $errors['password'] ?></span><?php endif; ?>
</label>
            <div class="input-group">
                <input type="password" name="password" class="form-control" id="password-field" value="" minlength="12" autocomplete="new-password" required>
                <button type="button" class="btn btn-outline-secondary" tabindex="-1" id="toggle-password" style="border: 1px solid #ced4da; border-left: 0;">
                    <span id="toggle-password-icon"><i class="fa fa-eye"></i></span>
                </button>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var pwField = document.getElementById('password-field');
                var toggleBtn = document.getElementById('toggle-password');
                var toggleIcon = document.getElementById('toggle-password-icon');
                if (pwField && toggleBtn) {
                    toggleBtn.addEventListener('click', function() {
                        if (pwField.type === 'password') {
                            pwField.type = 'text';
                            toggleIcon.innerHTML = '<i class="fa fa-eye-slash"></i>';
                        } else {
                            pwField.type = 'password';
                            toggleIcon.innerHTML = '<i class="fa fa-eye"></i>';
                        }
                    });
                }
            });
            </script>
        </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label">First Name
    <?php if (!empty($errors['first_name'])): ?><span class="text-danger small ms-2"><?= $errors['first_name'] ?></span><?php endif; ?>
</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['first_name'] ?? ($edit_mode ? $edit_data['users']['first_name'] : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Last Name
    <?php if (!empty($errors['last_name'])): ?><span class="text-danger small ms-2"><?= $errors['last_name'] ?></span><?php endif; ?>
</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['last_name'] ?? ($edit_mode ? $edit_data['users']['last_name'] : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email
    <?php if (!empty($errors['email'])): ?><span class="text-danger small ms-2"><?= $errors['email'] ?></span><?php endif; ?>
</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['email'] ?? ($edit_mode ? $edit_data['users']['email'] : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Telephone
    <?php if (!empty($errors['telephone_number'])): ?><span class="text-danger small ms-2"><?= $errors['telephone_number'] ?></span><?php endif; ?>
</label>
            <input type="text" name="telephone_number" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['telephone_number'] ?? ($edit_mode ? $edit_data['users']['telephone_number'] : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Role
    <?php if (!empty($errors['role'])): ?><span class="text-danger small ms-2"><?= $errors['role'] ?></span><?php endif; ?>
</label>
            <select name="role" class="form-select" required>
                <option value="User" <?= (($_SESSION['user_form']['role'] ?? ($edit_mode ? ($edit_data['users']['role'] ?? '') : '')) === 'User') ? 'selected' : '' ?>>User</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Account Status
                <?php if (!empty($errors['status'])): ?><span class="text-danger small ms-2"><?= htmlspecialchars($errors['status']) ?></span><?php endif; ?>
            </label>
            <select name="status" class="form-select" required>
                <?php foreach (['Active', 'Suspended', 'Closed'] as $status): ?>
                    <option value="<?= $status ?>" <?= (($_SESSION['user_form']['status'] ?? ($edit_mode ? ($edit_data['users']['status'] ?? 'Active') : 'Active')) === $status) ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <hr><h5 class="mt-4">Profile Details</h5>
        <div class="mb-3"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['nationality'] ?? ($edit_mode ? ($edit_data['userprofile']['nationality'] ?? '') : '')) ?>" required></div>
        <div class="mb-3"><label class="form-label">Married Status</label><select name="married_status" class="form-select" required>
            <?php foreach (['Single', 'Married', 'Divorced'] as $status): ?><option value="<?= $status ?>" <?= (($_SESSION['user_form']['married_status'] ?? ($edit_mode ? ($edit_data['userprofile']['married_status'] ?? '') : '')) === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label">Has Child</label><select name="has_child" class="form-select" required>
            <option value="No" <?= (($_SESSION['user_form']['has_child'] ?? ($edit_mode ? ($edit_data['userprofile']['has_child'] ?? '') : '')) === 'No') ? 'selected' : '' ?>>No</option>
            <option value="Yes" <?= (($_SESSION['user_form']['has_child'] ?? ($edit_mode ? ($edit_data['userprofile']['has_child'] ?? '') : '')) === 'Yes') ? 'selected' : '' ?>>Yes</option>
        </select></div>
        <div class="mb-3"><label class="form-label">Child Name</label><input type="text" name="child_name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['child_name'] ?? ($edit_mode ? ($edit_data['userprofile']['child_name'] ?? '') : '')) ?>"></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($_SESSION['user_form']['address'] ?? ($edit_mode ? ($edit_data['userprofile']['address'] ?? '') : '')) ?></textarea></div>
        <div class="d-flex justify-content-end">
            <button type="submit" name="next_step" value="item" class="btn btn-primary">
                Next <i class="fa fa-arrow-right ms-1"></i>
            </button>
            <a href="user-list.php" class="btn btn-secondary ms-2">Cancel</a>
        </div>
    <?php elseif ($step === 'profile'): ?>

        <div class="mb-3">
            <label class="form-label">Nationality
    <?php if (!empty($errors['nationality'])): ?><span class="text-danger small ms-2"><?= $errors['nationality'] ?></span><?php endif; ?>
</label>
            <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['nationality'] ?? ($edit_mode ? ($edit_data['userprofile']['nationality'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Married Status
    <?php if (!empty($errors['married_status'])): ?><span class="text-danger small ms-2"><?= $errors['married_status'] ?></span><?php endif; ?>
</label>
            <select name="married_status" class="form-select" required>
                <option value="Single" <?= (($_SESSION['user_form']['married_status'] ?? ($edit_mode ? ($edit_data['userprofile']['married_status'] ?? '') : '')) === 'Single') ? 'selected' : '' ?>>Single</option>
                <option value="Married" <?= (($_SESSION['user_form']['married_status'] ?? ($edit_mode ? ($edit_data['userprofile']['married_status'] ?? '') : '')) === 'Married') ? 'selected' : '' ?>>Married</option>


                <option value="Divorced" <?= (($_SESSION['user_form']['married_status'] ?? ($edit_mode ? ($edit_data['userprofile']['married_status'] ?? '') : '')) === 'Divorced') ? 'selected' : '' ?>>Divorced</option>


            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Has Child
    <?php if (!empty($errors['has_child'])): ?><span class="text-danger small ms-2"><?= $errors['has_child'] ?></span><?php endif; ?>
</label>
            <select name="has_child" class="form-select" required>
                <option value="No" <?= (($_SESSION['user_form']['has_child'] ?? ($edit_mode ? ($edit_data['userprofile']['has_child'] ?? '') : '')) === 'No') ? 'selected' : '' ?>>No</option>
                <option value="Yes" <?= (($_SESSION['user_form']['has_child'] ?? ($edit_mode ? ($edit_data['userprofile']['has_child'] ?? '') : '')) === 'Yes') ? 'selected' : '' ?>>Yes</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Child Name
    <?php if (!empty($errors['child_name'])): ?><span class="text-danger small ms-2"><?= $errors['child_name'] ?></span><?php endif; ?>
</label>
            <input type="text" name="child_name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['child_name'] ?? ($edit_mode ? ($edit_data['userprofile']['child_name'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Address
    <?php if (!empty($errors['address'])): ?><span class="text-danger small ms-2"><?= $errors['address'] ?></span><?php endif; ?>
</label>
            <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($_SESSION['user_form']['address'] ?? ($edit_mode ? ($edit_data['userprofile']['address'] ?? '') : '')) ?></textarea>
        </div>
        <div class="d-flex justify-content-between">
            <button type="submit" name="next_step" value="account" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="item" class="btn btn-primary">Next <i class="fa fa-arrow-right ms-1"></i></button>
    <?php elseif ($step === 'item'): ?>
        <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="fa fa-info-circle mt-1"></i>
            <div>Enter the physical deposit details first, then record its financial value and safe-keeping state below.</div>
        </div>
        <h5 class="border-bottom pb-2 mb-3"><i class="fa fa-box-open me-2 text-primary"></i>Deposit Details</h5>
        <div class="mb-3">
            <label class="form-label">Insurance Number
                <?php if (!empty($errors['insurance_number'])): ?><span class="text-danger small ms-2"><?= $errors['insurance_number'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="insurance_number" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['insurance_number'] ?? ($edit_mode ? ($edit_data['item_details']['insurance_number'] ?? ($generated['insurance_number'] ?? '')) : ($generated['insurance_number'] ?? ''))) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Reference Code
                <?php if (!empty($errors['reference_code'])): ?><span class="text-danger small ms-2"><?= $errors['reference_code'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="reference_code" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['reference_code'] ?? ($edit_mode ? ($edit_data['item_details']['reference_code'] ?? ($generated['reference_code'] ?? '')) : ($generated['reference_code'] ?? ''))) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Transaction Code
                <?php if (!empty($errors['transaction_code'])): ?><span class="text-danger small ms-2"><?= $errors['transaction_code'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="transaction_code" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['transaction_code'] ?? ($edit_mode ? ($edit_data['item_details']['transaction_code'] ?? ($generated['transaction_code'] ?? '')) : ($generated['transaction_code'] ?? ''))) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Box Dimension
                <?php if (!empty($errors['box_dimension'])): ?><span class="text-danger small ms-2"><?= $errors['box_dimension'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="box_dimension" class="form-control" list="boxDimensionOptions" placeholder="e.g. 30 x 20 x 15 cm" value="<?= htmlspecialchars($_SESSION['user_form']['box_dimension'] ?? ($edit_mode ? ($edit_data['item_details']['box_dimension'] ?? '') : '')) ?>" required>
            <div class="form-text">Use length x width x height and include the unit.</div>
            <datalist id="boxDimensionOptions"><option value="30 x 20 x 15 cm"><option value="40 x 30 x 20 cm"><option value="60 x 40 x 40 cm"></datalist>
        </div>
        <div class="mb-3">
            <label class="form-label">Deposited Item
                <?php if (!empty($errors['deposited_item'])): ?><span class="text-danger small ms-2"><?= $errors['deposited_item'] ?></span><?php endif; ?>
            </label>
            <textarea name="deposited_item" class="form-control" rows="2" placeholder="Describe the item, material, markings, and condition" required><?= htmlspecialchars($_SESSION['user_form']['deposited_item'] ?? ($edit_mode ? ($edit_data['item_details']['deposited_item'] ?? '') : '')) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Package Type
                <?php if (!empty($errors['package_type'])): ?><span class="text-danger small ms-2"><?= $errors['package_type'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="package_type" class="form-control" list="packageTypeOptions" placeholder="Select or enter a package type" value="<?= htmlspecialchars($_SESSION['user_form']['package_type'] ?? ($edit_mode ? ($edit_data['item_details']['package_type'] ?? '') : '')) ?>" required>
            <datalist id="packageTypeOptions"><option value="Box"><option value="Envelope"><option value="Pouch"><option value="Crate"><option value="Other"></datalist>
        </div>
        <div class="mb-3">
            <label class="form-label">Package Quantity
                <?php if (!empty($errors['package_quantity'])): ?><span class="text-danger small ms-2"><?= $errors['package_quantity'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="package_quantity" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['package_quantity'] ?? ($edit_mode ? ($edit_data['item_details']['package_quantity'] ?? '') : '')) ?>" min="1" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Total Weight (kg)
                <?php if (!empty($errors['total_weight'])): ?><span class="text-danger small ms-2"><?= $errors['total_weight'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="total_weight" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['total_weight'] ?? ($edit_mode ? ($edit_data['item_details']['total_weight'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Deposit Date
                <?php if (!empty($errors['deposit_date'])): ?><span class="text-danger small ms-2"><?= $errors['deposit_date'] ?></span><?php endif; ?>
            </label>
            <input type="date" name="deposit_date" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['deposit_date'] ?? ($edit_mode ? ($edit_data['item_details']['deposit_date'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Monthly Charges
                <?php if (!empty($errors['monthly_charges'])): ?><span class="text-danger small ms-2"><?= $errors['monthly_charges'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="monthly_charges" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['monthly_charges'] ?? ($edit_mode ? ($edit_data['item_details']['monthly_charges'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Amount Paid
                <?php if (!empty($errors['amount_paid'])): ?><span class="text-danger small ms-2"><?= $errors['amount_paid'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="amount_paid" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['amount_paid'] ?? ($edit_mode ? ($edit_data['item_details']['amount_paid'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <hr><h5 class="mt-4 border-bottom pb-2"><i class="fa fa-chart-line me-2 text-success"></i>Value &amp; Safe-Keeping State</h5>
        <div class="mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['quantity'] ?? ($edit_mode ? ($edit_data['state_of_items']['quantity'] ?? '') : '')) ?>" min="1" required></div>
        <div class="mb-3"><label class="form-label">Current Gold Worth</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="current_gold_worth" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['current_gold_worth'] ?? ($edit_mode ? ($edit_data['state_of_items']['current_gold_worth'] ?? '') : '')) ?>" step="0.01" min="0" required></div></div>
        <div class="mb-3"><label class="form-label">Price Per Kilogram</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="price_per_kilogram" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['price_per_kilogram'] ?? ($edit_mode ? ($edit_data['state_of_items']['price_per_kilogram'] ?? '') : '')) ?>" step="0.01" min="0" required></div></div>
        <div class="mb-3"><label class="form-label">Cost of Safe Keeping</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="cost_of_safe_keeping" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['cost_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['cost_of_safe_keeping'] ?? '') : '')) ?>" step="0.01" min="0" required></div></div>
        <div class="mb-3"><label class="form-label">Date of Safe Keeping</label><input type="date" name="date_of_safe_keeping" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['date_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['date_of_safe_keeping'] ?? '') : '')) ?>" required></div>
        <div class="d-flex justify-content-between">
            <button type="submit" name="next_step" value="account" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="kin" class="btn btn-primary">Next <i class="fa fa-arrow-right ms-1"></i></button>
        </div>
    <?php elseif ($step === 'state'): ?>
        <div class="mb-3">
            <label class="form-label">Quantity
                <?php if (!empty($errors['quantity'])): ?><span class="text-danger small ms-2"><?= $errors['quantity'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['quantity'] ?? ($edit_mode ? ($edit_data['state_of_items']['quantity'] ?? '') : '')) ?>" min="1" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Current Gold Worth
                <?php if (!empty($errors['current_gold_worth'])): ?><span class="text-danger small ms-2"><?= $errors['current_gold_worth'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="current_gold_worth" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['current_gold_worth'] ?? ($edit_mode ? ($edit_data['state_of_items']['current_gold_worth'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Price Per Kilogram
                <?php if (!empty($errors['price_per_kilogram'])): ?><span class="text-danger small ms-2"><?= $errors['price_per_kilogram'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="price_per_kilogram" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['price_per_kilogram'] ?? ($edit_mode ? ($edit_data['state_of_items']['price_per_kilogram'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Cost of Safe Keeping
                <?php if (!empty($errors['cost_of_safe_keeping'])): ?><span class="text-danger small ms-2"><?= $errors['cost_of_safe_keeping'] ?></span><?php endif; ?>
            </label>
            <input type="number" name="cost_of_safe_keeping" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['cost_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['cost_of_safe_keeping'] ?? '') : '')) ?>" step="0.01" min="0" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date of Safe Keeping
                <?php if (!empty($errors['date_of_safe_keeping'])): ?><span class="text-danger small ms-2"><?= $errors['date_of_safe_keeping'] ?></span><?php endif; ?>
            </label>
            <input type="date" name="date_of_safe_keeping" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['date_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['date_of_safe_keeping'] ?? '') : '')) ?>" required>
        </div>
        <div class="d-flex justify-content-between">
            <button type="submit" name="next_step" value="item" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="kin" class="btn btn-primary">Next <i class="fa fa-arrow-right ms-1"></i></button>
        </div>
    <?php elseif ($step === 'kin'): ?>
        <div class="mb-3">
            <label class="form-label">Full Name
                <?php if (!empty($errors['name_of_beneficial'])): ?><span class="text-danger small ms-2"><?= $errors['name_of_beneficial'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="name_of_beneficial" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['name_of_beneficial'] ?? ($edit_mode ? ($edit_data['next_of_kin']['name_of_beneficial'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Relationship
                <?php if (!empty($errors['relation_with_user'])): ?><span class="text-danger small ms-2"><?= $errors['relation_with_user'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="relation_with_user" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['relation_with_user'] ?? ($edit_mode ? ($edit_data['next_of_kin']['relation_with_user'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Date of Birth
                <?php if (!empty($errors['date_of_birth'])): ?><span class="text-danger small ms-2"><?= $errors['date_of_birth'] ?></span><?php endif; ?>
            </label>
            <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['date_of_birth'] ?? ($edit_mode ? ($edit_data['next_of_kin']['date_of_birth'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address
                <?php if (!empty($errors['email_address'])): ?><span class="text-danger small ms-2"><?= $errors['email_address'] ?></span><?php endif; ?>
            </label>
            <input type="email" name="email_address" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['email_address'] ?? ($edit_mode ? ($edit_data['next_of_kin']['email_address'] ?? '') : '')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Telephone Number
                <?php if (!empty($errors['telephone_number_kin'])): ?><span class="text-danger small ms-2"><?= $errors['telephone_number_kin'] ?></span><?php endif; ?>
            </label>
            <input type="text" name="telephone_number_kin" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['telephone_number_kin'] ?? ($edit_mode ? ($edit_data['next_of_kin']['telephone_number_kin'] ?? '') : '')) ?>" required>
        </div>
        <div class="d-flex justify-content-between">
            <button type="submit" name="next_step" value="item" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="finish" class="btn btn-success">Save Account <i class="fa fa-check ms-1"></i></button>
        </div>
    <?php elseif ($step === 'review'): ?>
        <h5 class="mb-3">Review Account Details</h5>
        <p class="text-muted">Confirm the information below before saving this account.</p>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <tbody>
                <?php foreach ([
                    'username' => 'Username', 'first_name' => 'First Name', 'last_name' => 'Last Name', 'email' => 'Email',
                    'telephone_number' => 'Telephone', 'role' => 'Role', 'status' => 'Account Status', 'nationality' => 'Nationality',
                    'married_status' => 'Married Status', 'has_child' => 'Has Child', 'child_name' => 'Child Name',
                    'address' => 'Address', 'insurance_number' => 'Insurance Number', 'reference_code' => 'Reference Code',
                    'transaction_code' => 'Transaction Code', 'deposited_item' => 'Deposited Item', 'package_quantity' => 'Package Quantity',
                    'total_weight' => 'Total Weight', 'deposit_date' => 'Deposit Date', 'monthly_charges' => 'Monthly Charges',
                    'amount_paid' => 'Amount Paid', 'quantity' => 'Quantity', 'current_gold_worth' => 'Current Gold Worth',
                    'price_per_kilogram' => 'Price Per Kilogram', 'cost_of_safe_keeping' => 'Cost of Safe Keeping',
                    'date_of_safe_keeping' => 'Date of Safe Keeping', 'name_of_beneficial' => 'Beneficiary',
                    'relation_with_user' => 'Relationship', 'date_of_birth' => 'Beneficiary Date of Birth',
                    'email_address' => 'Beneficiary Email', 'telephone_number_kin' => 'Beneficiary Telephone'
                ] as $field => $label): ?>
                    <tr><th><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></th><td><?= htmlspecialchars((string) ($_SESSION['user_form'][$field] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <button type="submit" name="next_step" value="kin" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="finish" class="btn btn-success">Save Account <i class="fa fa-check ms-1"></i></button>
        </div>
    <?php endif; ?>
</form>
</div>
            </div>
        </div>
    </div>
</div>
</main>
<?php include 'admin_footer.php'; ?>
