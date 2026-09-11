<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
// If the user is not logged in, redirect to the login page
require_user();
$userID = $_SESSION['loggedin'];

// Fetch current next-of-kin data
$query = "SELECT * FROM next_of_kin WHERE user_id = ? LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute([$userID]);
$nextOfKin = $stmt->fetch() ?: [];

// Initialize variables
$name = $nextOfKin['name_of_beneficial'] ?? '';
$relation = $nextOfKin['relation_with_user'] ?? '';
$dateOfBirth = $nextOfKin['date_of_birth'] ?? '';
$phone = $nextOfKin['telephone_number_kin'] ?? '';
$email = $nextOfKin['email_address'] ?? '';
$address = $nextOfKin['address'] ?? '';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name_of_beneficial'] ?? '');
    $relation = trim($_POST['relation_with_user'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $phone = trim($_POST['telephone_number_kin'] ?? '');
    $email = trim($_POST['email_address'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validation
    if ($name === '') {
        $errors['name_of_beneficial'] = 'Name is required.';
    }
    if ($relation === '') {
        $errors['relation_with_user'] = 'Relationship is required.';
    }
    $birthDate = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    if (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth || $birthDate > new DateTime('today')) {
        $errors['date_of_birth'] = 'Enter a valid date of birth that is not in the future.';
    }
    if ($phone === '') {
        $errors['telephone_number_kin'] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9\-\+ ]{7,20}$/', $phone)) {
        $errors['telephone_number_kin'] = 'Invalid phone number format.';
    }
    if ($email === '') {
        $errors['email_address'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email_address'] = 'Invalid email format.';
    }
    if ($address === '') {
        $errors['address'] = 'Address is required.';
    }

    if (empty($errors)) {
        // Update the record
        if ($nextOfKin) {
            $updateQuery = "UPDATE next_of_kin SET name_of_beneficial = ?, relation_with_user = ?, date_of_birth = ?, telephone_number_kin = ?, email_address = ?, address = ? WHERE user_id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            if ($updateStmt->execute([$name, $relation, $dateOfBirth, $phone, $email, $address, $userID])) {
                $success = true;
            } else {
                $errors['general'] = 'Failed to update next of kin details.';
            }
        } else {
            // Insert if not exists (optional, for robustness)
            $insertQuery = "INSERT INTO next_of_kin (user_id, name_of_beneficial, relation_with_user, date_of_birth, telephone_number_kin, email_address, address) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertQuery);
            if ($insertStmt->execute([$userID, $name, $relation, $dateOfBirth, $phone, $email, $address])) {
                $success = true;
            } else {
                $errors['general'] = 'Failed to add next of kin details.';
            }
        }
        // Refresh data
        if ($success) {
            log_security_event('user', 'beneficiary_updated', (int) $userID, (int) $userID);
            $nextOfKin = [
                'name_of_beneficial' => $name,
                'relation_with_user' => $relation,
                'date_of_birth' => $dateOfBirth,
                'telephone_number_kin' => $phone,
                'email_address' => $email,
                'address' => $address
            ];
        }
    }
}

include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content">
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-0 bg-primary bg-opacity-10 position-relative overflow-hidden" style="min-height: 160px;">
                    <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-between p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-white border border-3 border-primary d-flex align-items-center justify-content-center shadow-sm" style="width:90px; height:90px;">
                                <i class="fas fa-users fa-3x text-primary"></i>
                            </div>
                            <div>
                                <h2 class="fw-bold mb-1 text-primary" style="font-size:1.7rem;">
                                    Edit Next of Kin Details
                                </h2>
                                <span class="badge bg-warning px-3 py-2 fs-6">Editing</span>
                            </div>
                        </div>
                        <div class="mt-4 mt-md-0">
                            <a href="next-of-kin.php" class="btn btn-outline-secondary btn-pill px-4 py-2 me-2"><i class="fa fa-arrow-left me-1"></i>Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">Next of kin details updated successfully.</div>
                        <?php elseif (!empty($errors['general'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="name_of_beneficial" class="form-label">Name of Beneficiary</label>
                                <input type="text" class="form-control <?php echo isset($errors['name_of_beneficial']) ? 'is-invalid' : ''; ?>" id="name_of_beneficial" name="name_of_beneficial" value="<?php echo htmlspecialchars($name); ?>">
                                <?php if (isset($errors['name_of_beneficial'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name_of_beneficial']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="relation_with_user" class="form-label">Relationship</label>
                                <input type="text" class="form-control <?php echo isset($errors['relation_with_user']) ? 'is-invalid' : ''; ?>" id="relation_with_user" name="relation_with_user" value="<?php echo htmlspecialchars($relation); ?>">
                                <?php if (isset($errors['relation_with_user'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['relation_with_user']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control <?php echo isset($errors['date_of_birth']) ? 'is-invalid' : ''; ?>" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($dateOfBirth); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                <?php if (isset($errors['date_of_birth'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($errors['date_of_birth']); ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="telephone_number_kin" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control <?php echo isset($errors['telephone_number_kin']) ? 'is-invalid' : ''; ?>" id="telephone_number_kin" name="telephone_number_kin" value="<?php echo htmlspecialchars($phone); ?>" required>
                                <?php if (isset($errors['telephone_number_kin'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['telephone_number_kin']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="email_address" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email_address']) ? 'is-invalid' : ''; ?>" id="email_address" name="email_address" value="<?php echo htmlspecialchars($email); ?>" required>
                                <?php if (isset($errors['email_address'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email_address']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" id="address" name="address" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
                                <?php if (isset($errors['address'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['address']); ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'user_footer.php'; ?>
