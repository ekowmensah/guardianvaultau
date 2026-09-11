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
        $currentStep = $_POST['current_step'] ?? 'account';
        $requestedStep = $_POST['next_step'] ?? 'account';
        $allowedStepTargets = [
            'account' => ['item'],
            'item' => ['account', 'kin'],
            'kin' => ['item', 'finish'],
        ];
        if (!in_array($currentStep, ['account', 'item', 'kin'], true)) {
            $currentStep = 'account';
        }
        if (!in_array($requestedStep, $allowedStepTargets[$currentStep] ?? [], true)) {
            $requestedStep = $currentStep;
        }
        $step = $requestedStep;
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
        $validate_step = $currentStep;
        $is_going_back = false;
        $step_keys = array_keys($step_fields);
        if (isset($_POST['next_step'])) {
            $currentStepIndex = array_search($validate_step, $step_keys, true);
            $nextStepIndex = array_search($requestedStep, $step_keys, true);
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // If errors, do not redirect; show errors on the relevant grouped step.
            if (!empty($errors) && !$is_finishing) {
                // Stay on the current step and show errors
                $step = $currentStep;
            } elseif (!$is_finishing) {
                if ($is_going_back) {
                    // When going back, set $step to the previous step and do NOT redirect
                    $step = $requestedStep;
                    // Just fall through and re-render that step with session data
                } else {
                    // When going forward and there are no errors, redirect to new step
                    $step = $requestedStep;
                    $editQuery = !empty($_POST['edit_id']) ? '&id=' . (int) $_POST['edit_id'] : '';
                    header("Location: user-form.php?step=$step$editQuery");
                    exit;
                }
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
<?php if (!in_array($step, ['account', 'item', 'kin'], true)) { $step = 'account'; } ?>
<?php include 'admin_header.php'; ?>
<style>
    .gv-user-page {
        --gv-primary: #2442d8;
        --gv-ink: #172033;
        --gv-muted: #667085;
        --gv-line: #e6eaf2;
        --gv-soft: #f8faff;
        color: var(--gv-ink);
    }

    .gv-page-shell {
        max-width: 1180px;
    }

    .gv-page-hero {
        background: linear-gradient(135deg, #172033 0%, #2442d8 100%);
        border: 0;
        border-radius: 22px;
        box-shadow: 0 18px 45px rgba(23, 32, 51, .18);
        color: #fff;
        overflow: hidden;
        position: relative;
    }

    .gv-page-hero::after {
        background: radial-gradient(circle, rgba(255,255,255,.22), transparent 62%);
        content: "";
        height: 220px;
        position: absolute;
        right: -70px;
        top: -90px;
        width: 220px;
    }

    .gv-page-hero .btn {
        position: relative;
        z-index: 1;
    }

    .gv-stepper {
        display: grid;
        gap: .65rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .gv-stepper .nav-link {
        align-items: center;
        background: #fff;
        border: 1px solid var(--gv-line);
        border-radius: 14px;
        color: var(--gv-muted);
        display: flex;
        gap: .65rem;
        min-height: 58px;
        padding: .65rem .8rem;
    }

    .gv-stepper .nav-link.active {
        background: rgba(36, 66, 216, .08);
        border-color: rgba(36, 66, 216, .32);
        color: var(--gv-primary);
        font-weight: 700;
    }

    .gv-step-number {
        align-items: center;
        background: #eef2ff;
        border-radius: 999px;
        display: inline-flex;
        flex: 0 0 30px;
        height: 30px;
        justify-content: center;
        width: 30px;
    }

    .gv-stepper .active .gv-step-number {
        background: var(--gv-primary);
        color: #fff;
    }

    .gv-form-card {
        border: 1px solid var(--gv-line);
        border-radius: 22px;
        box-shadow: 0 14px 40px rgba(23, 32, 51, .08);
    }

    .gv-form-card .card-body {
        padding: 1rem;
    }

    .gv-section-title {
        align-items: center;
        border-bottom: 1px solid var(--gv-line);
        color: var(--gv-ink);
        display: flex;
        font-size: .88rem;
        font-weight: 800;
        gap: .55rem;
        grid-column: 1 / -1;
        letter-spacing: .03em;
        margin: .35rem 0 .1rem;
        padding: .25rem 0 .65rem;
        text-transform: uppercase;
    }

    .gv-user-form {
        display: grid;
        gap: .8rem;
        grid-template-columns: repeat(12, minmax(0, 1fr));
    }

    .gv-user-form > .mb-3 {
        grid-column: span 6;
        margin-bottom: 0 !important;
    }

    .gv-user-form > .alert,
    .gv-user-form > .d-flex,
    .gv-user-form > hr,
    .gv-user-form > h5,
    .gv-user-form > .gv-form-alert {
        grid-column: 1 / -1;
    }

    .gv-user-form > .mb-3:has(textarea),
    .gv-user-form > .mb-3:has(input[list]) {
        grid-column: span 6;
    }

    .gv-user-form label {
        color: #344054;
        font-size: .78rem;
        font-weight: 700;
        margin-bottom: .32rem;
    }

    .gv-user-form .form-control,
    .gv-user-form .form-select,
    .gv-user-form .input-group-text {
        border-color: #d9e0ec;
        border-radius: 10px;
        font-size: .92rem;
        min-height: 40px;
    }

    .gv-user-form .input-group .form-control {
        border-bottom-left-radius: 0;
        border-top-left-radius: 0;
    }

    .gv-user-form textarea.form-control {
        min-height: 82px;
        resize: vertical;
    }

    .gv-user-form .form-text {
        color: var(--gv-muted);
        font-size: .75rem;
    }

    .gv-actions {
        background: rgba(255,255,255,.92);
        border-top: 1px solid var(--gv-line);
        bottom: 0;
        margin: .5rem -1rem -1rem;
        padding: .9rem 1rem;
        position: sticky;
        z-index: 5;
    }

    .gv-summary {
        background: var(--gv-soft);
        border: 1px solid var(--gv-line);
        border-radius: 18px;
        padding: .95rem;
    }

    .gv-side-panel {
        position: sticky;
        top: 1rem;
    }

    .gv-summary-label {
        color: var(--gv-muted);
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .gv-summary-value {
        font-size: .92rem;
        font-weight: 700;
        word-break: break-word;
    }

    @media (min-width: 1200px) {
        .gv-user-form > .mb-3 {
            grid-column: span 4;
        }
    }

    @media (max-width: 767.98px) {
        .gv-user-page.main-content {
            padding: 1rem;
        }

        .gv-stepper,
        .gv-user-form {
            grid-template-columns: 1fr;
        }

        .gv-user-form > .mb-3 {
            grid-column: 1 / -1;
        }

        .gv-side-panel {
            position: static;
        }
    }
</style>
<main class="col-md-10 ms-sm-auto main-content gv-user-page">
<div class="container-fluid">
    <div class="gv-page-shell mx-auto">
        <?php
        $stepNumber = ['account' => 1, 'item' => 2, 'kin' => 3][$step] ?? 1;
        $stepTitles = [
            'account' => 'Account & Profile',
            'item' => 'Deposit & Value',
            'kin' => 'Beneficiary & Save',
        ];
        $stepDescriptions = [
            'account' => 'Create the login identity and personal profile details.',
            'item' => 'Capture the vault item, package, value, and safe-keeping data.',
            'kin' => 'Add beneficiary contact details and complete the account.',
        ];
        $editSuffix = $edit_mode ? '&amp;id=' . (int) $user_id : '';
        $summaryName = trim((string) (($_SESSION['user_form']['first_name'] ?? ($edit_mode ? ($edit_data['users']['first_name'] ?? '') : '')) . ' ' . ($_SESSION['user_form']['last_name'] ?? ($edit_mode ? ($edit_data['users']['last_name'] ?? '') : ''))));
        $summaryUsername = $_SESSION['user_form']['username'] ?? ($edit_mode ? ($edit_data['users']['username'] ?? '') : ($generated['username'] ?? 'Pending'));
        $summaryEmail = $_SESSION['user_form']['email'] ?? ($edit_mode ? ($edit_data['users']['email'] ?? '') : 'Not entered');
        ?>
        <div class="card gv-page-hero mb-3">
            <div class="card-body p-3 p-lg-4">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                    <div class="flex-grow-1">
                        <div class="small text-white-50 fw-semibold mb-1"><?= $edit_mode ? 'Edit client account' : 'New client account' ?></div>
                        <h3 class="mb-1"><?= htmlspecialchars($stepTitles[$step], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mb-0 text-white-50"><?= htmlspecialchars($stepDescriptions[$step], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-light text-dark px-3 py-2">Step <?= $stepNumber ?> of 3</span>
                        <a href="user-list.php" class="btn btn-sm btn-outline-light"><i class="fa fa-times me-1"></i> Close</a>
                    </div>
                </div>
                <div class="progress mt-3 bg-white bg-opacity-25" style="height: 6px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $stepNumber * 33.33 ?>%"></div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card gv-form-card">
                    <div class="card-body">
<ul class="nav gv-stepper mb-3" id="userFormSteps">
    <li class="nav-item"><a class="nav-link<?= $step==='account'?' active':'' ?>" href="?step=account<?= $editSuffix ?>"><span class="gv-step-number">1</span><span>Account<br><small>Profile</small></span></a></li>
        <li class="nav-item"><a class="nav-link<?= $step==='item'?' active':'' ?><?= ($edit_mode||$step==='item'||$step==='kin')?'':' disabled' ?>" href="?step=item<?= $editSuffix ?>"><span class="gv-step-number">2</span><span>Deposit<br><small>Value</small></span></a></li>
        <li class="nav-item"><a class="nav-link<?= $step==='kin'?' active':'' ?><?= ($edit_mode||$step==='kin')?'':' disabled' ?>" href="?step=kin<?= $editSuffix ?>"><span class="gv-step-number">3</span><span>Beneficiary<br><small>Save</small></span></a></li>
</ul>
<form method="post" autocomplete="off" class="gv-user-form">
    <input type="hidden" name="current_step" value="<?= htmlspecialchars($step) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <?php if ($edit_mode): ?>
        <input type="hidden" name="edit_id" value="<?= htmlspecialchars($user_id) ?>">
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger gv-form-alert mb-0">
            <div class="fw-bold mb-1"><i class="fa fa-exclamation-triangle me-1"></i> Please check the details below.</div>
            <div class="small"><?= count($errors) ?> field<?= count($errors) === 1 ? '' : 's' ?> need attention before you continue.</div>
        </div>
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
        <h5 class="gv-section-title"><i class="fa fa-id-card text-primary"></i> Login & contact</h5>
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
        <h5 class="gv-section-title"><i class="fa fa-user-circle text-primary"></i> Profile details</h5>
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
            <?php foreach (['Single', 'Married', 'Divorced'] as $status): ?><option value="<?= $status ?>" <?= (($_SESSION['user_form']['married_status'] ?? ($edit_mode ? ($edit_data['userprofile']['married_status'] ?? '') : '')) === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
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
            <input type="text" name="child_name" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['child_name'] ?? ($edit_mode ? ($edit_data['userprofile']['child_name'] ?? '') : '')) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Address
                <?php if (!empty($errors['address'])): ?><span class="text-danger small ms-2"><?= $errors['address'] ?></span><?php endif; ?>
            </label>
            <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($_SESSION['user_form']['address'] ?? ($edit_mode ? ($edit_data['userprofile']['address'] ?? '') : '')) ?></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2 gv-actions">
            <button type="submit" name="next_step" value="item" class="btn btn-primary">
                Next <i class="fa fa-arrow-right ms-1"></i>
            </button>
            <a href="user-list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    <?php elseif ($step === 'item'): ?>
        <div class="alert alert-info d-flex align-items-start gap-2 mb-0">
            <i class="fa fa-info-circle mt-1"></i>
            <div>Enter the physical deposit details first, then record its financial value and safe-keeping state below.</div>
        </div>
        <h5 class="gv-section-title"><i class="fa fa-box-open text-primary"></i> Deposit details</h5>
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
        <h5 class="gv-section-title"><i class="fa fa-chart-line text-success"></i> Value &amp; safe-keeping</h5>
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
            <div class="input-group"><span class="input-group-text">$</span><input type="number" name="current_gold_worth" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['current_gold_worth'] ?? ($edit_mode ? ($edit_data['state_of_items']['current_gold_worth'] ?? '') : '')) ?>" step="0.01" min="0" required></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Price Per Kilogram
                <?php if (!empty($errors['price_per_kilogram'])): ?><span class="text-danger small ms-2"><?= $errors['price_per_kilogram'] ?></span><?php endif; ?>
            </label>
            <div class="input-group"><span class="input-group-text">$</span><input type="number" name="price_per_kilogram" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['price_per_kilogram'] ?? ($edit_mode ? ($edit_data['state_of_items']['price_per_kilogram'] ?? '') : '')) ?>" step="0.01" min="0" required></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Cost of Safe Keeping
                <?php if (!empty($errors['cost_of_safe_keeping'])): ?><span class="text-danger small ms-2"><?= $errors['cost_of_safe_keeping'] ?></span><?php endif; ?>
            </label>
            <div class="input-group"><span class="input-group-text">$</span><input type="number" name="cost_of_safe_keeping" class="form-control" inputmode="decimal" value="<?= htmlspecialchars($_SESSION['user_form']['cost_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['cost_of_safe_keeping'] ?? '') : '')) ?>" step="0.01" min="0" required></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Date of Safe Keeping
                <?php if (!empty($errors['date_of_safe_keeping'])): ?><span class="text-danger small ms-2"><?= $errors['date_of_safe_keeping'] ?></span><?php endif; ?>
            </label>
            <input type="date" name="date_of_safe_keeping" class="form-control" value="<?= htmlspecialchars($_SESSION['user_form']['date_of_safe_keeping'] ?? ($edit_mode ? ($edit_data['state_of_items']['date_of_safe_keeping'] ?? '') : '')) ?>" required>
        </div>
        <div class="d-flex justify-content-between gap-2 gv-actions">
            <button type="submit" name="next_step" value="account" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="kin" class="btn btn-primary">Next <i class="fa fa-arrow-right ms-1"></i></button>
        </div>
    <?php elseif ($step === 'kin'): ?>
        <h5 class="gv-section-title"><i class="fa fa-user-friends text-primary"></i> Beneficiary contact</h5>
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
        <div class="d-flex justify-content-between gap-2 gv-actions">
            <button type="submit" name="next_step" value="item" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i>Back</button>
            <button type="submit" name="next_step" value="finish" class="btn btn-success">Save Account <i class="fa fa-check ms-1"></i></button>
        </div>
<?php endif; ?>
</form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="gv-side-panel">
                    <div class="gv-summary mb-3">
                        <div class="gv-summary-label mb-1">Current record</div>
                        <div class="gv-summary-value"><?= htmlspecialchars($summaryName !== '' ? $summaryName : 'Name not entered', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-muted mt-1"><?= htmlspecialchars((string) $summaryUsername, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string) $summaryEmail, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="gv-summary">
                        <div class="gv-summary-label mb-2">Quick guide</div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fa fa-check-circle text-success mt-1"></i>
                            <div class="small">Use the step cards to review completed sections.</div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <i class="fa fa-lock text-primary mt-1"></i>
                            <div class="small">Generated account codes are kept visible for easy copying.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <i class="fa fa-save text-warning mt-1"></i>
                            <div class="small">Nothing is saved permanently until the final Save Account button.</div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var hasChild = document.querySelector('select[name="has_child"]');
    var childName = document.querySelector('input[name="child_name"]');
    var childGroup = childName ? childName.closest('.mb-3') : null;

    function syncChildField() {
        if (!hasChild || !childName || !childGroup) return;
        var show = hasChild.value === 'Yes';
        childGroup.classList.toggle('d-none', !show);
        childName.required = show;
        if (!show) childName.value = '';
    }

    if (hasChild) {
        hasChild.addEventListener('change', syncChildField);
        syncChildField();
    }
});
</script>
</main>
<?php include 'admin_footer.php'; ?>
