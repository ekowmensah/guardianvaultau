<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
// If the user is not logged in, redirect to the login page
require_user();
$userID = $_SESSION['loggedin'];
$query = "SELECT * FROM next_of_kin WHERE user_id = ? LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute([$userID]);
$nextOfKin = $stmt->fetch() ?: [];
include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content">
    <div class="container py-5">
        <!-- Professional Profile Header -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-lg bg-gradient-primary position-relative overflow-hidden" style="min-height: 170px; background: linear-gradient(90deg, #e3f0ff 0%, #f8fafd 100%);">
                    <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-between p-4">
                        <div class="d-flex align-items-center gap-4">
                            <div class="rounded-circle bg-white border border-4 border-primary d-flex align-items-center justify-content-center shadow" style="width:100px; height:100px;">
                                <i class="fas fa-user-shield fa-3x text-primary"></i>
                            </div>
                            <div>
                                <h1 class="fw-bold mb-1 text-primary" style="font-size:2rem; letter-spacing:1px;">
                                    Next of Kin Profile
                                </h1>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-gradient-success px-3 py-2 fs-6" style="font-size:1rem; background: linear-gradient(90deg, #28a745 0%, #c0f3c8 100%); color:#fff;"><?= $nextOfKin ? 'On file' : 'Not provided' ?></span>
                                    <span class="text-muted small ms-2"><i class="fas fa-user-circle me-1"></i>Guardian Vault Member</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 mt-md-0">
                            <a href="edit-next-of-kin.php" class="btn btn-primary btn-lg px-5 py-2 shadow-sm rounded-pill fw-bold"><i class="fa fa-edit me-2"></i>Edit Next of Kin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next of Kin Info Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 h-100" style="border-top: 4px solid #ffc107;">
    <div class="card-body text-center">
        <div class="mb-3"><i class="fas fa-user fa-2x text-warning"></i></div>
        <div class="fw-semibold text-uppercase text-muted small mb-2">Name of Beneficiary</div>
        <div class="fw-bold text-dark fs-5" style="text-transform:capitalize;">
            <?php echo htmlspecialchars($nextOfKin['name_of_beneficial'] ?? '-'); ?>
        </div>
    </div>
</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 h-100" style="border-top: 4px solid #17a2b8;">
    <div class="card-body text-center">
        <div class="mb-3"><i class="fas fa-user-friends fa-2x text-info"></i></div>
        <div class="fw-semibold text-uppercase text-muted small mb-2">Relationship</div>
        <div class="fw-bold text-dark fs-5" style="text-transform:capitalize;">
            <?php echo htmlspecialchars($nextOfKin['relation_with_user'] ?? '-'); ?>
        </div>
    </div>
</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 h-100" style="border-top: 4px solid #28a745;">
    <div class="card-body text-center">
        <div class="mb-3"><i class="fas fa-calendar-alt fa-2x text-success"></i></div>
        <div class="fw-semibold text-uppercase text-muted small mb-2">Date of Birth</div>
        <div class="fw-bold text-dark fs-5">
            <?php echo isset($nextOfKin['date_of_birth']) ? date('jS F, Y', strtotime($nextOfKin['date_of_birth'])) : '-'; ?>
        </div>
    </div>
</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 h-100" style="border-top: 4px solid #6c757d;">
    <div class="card-body text-center">
        <div class="mb-3"><i class="fas fa-envelope fa-2x text-secondary"></i></div>
        <div class="fw-semibold text-uppercase text-muted small mb-2">Email Address</div>
        <div class="fw-bold text-dark fs-5">
            <?php echo htmlspecialchars($nextOfKin['email_address'] ?? '-'); ?>
        </div>
    </div>
</div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-lg border-0 h-100" style="border-top: 4px solid #007bff;">
    <div class="card-body text-center">
        <div class="mb-3"><i class="fas fa-phone fa-2x text-primary"></i></div>
        <div class="fw-semibold text-uppercase text-muted small mb-2">Telephone Number</div>
        <div class="fw-bold text-dark fs-5">
            <?php echo htmlspecialchars($nextOfKin['telephone_number_kin'] ?? '-'); ?>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</main>
<?php include 'user_footer.php'; ?>
