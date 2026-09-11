<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
// If the user is not logged in, redirect to the login page
require_user();
// Retrieve the user's information from the database
$userID = $_SESSION['loggedin'];
$stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, telephone_number, role, status, created_at, updated_at FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();
// Retrieve the user's profile information from the database
$stmtProfile = $pdo->prepare("SELECT * FROM userprofile WHERE user_id = ?");
$stmtProfile->execute([$userID]);
$profile = $stmtProfile->fetch();
include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content">
    <div class="container py-4">
        <!-- Profile Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-0 bg-primary bg-opacity-10 position-relative overflow-hidden" style="min-height: 180px;">
                    <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-between p-4">
                        <div class="d-flex align-items-center gap-3">
                            <img src="assets/img/avatars/user.png" alt="Default account avatar" style="width:110px; height:110px; object-fit:cover;" class="img-fluid rounded-circle border border-3 border-primary bg-white shadow-sm">
                            <div>
                                <h2 class="fw-bold mb-1 text-primary" style="font-size:2rem;">
                                    <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                                </h2>
                                <span class="badge bg-success px-3 py-2 fs-6"><?php echo htmlspecialchars($user['status'] ?? 'Active'); ?></span>
                            </div>
                        </div>
                        <div class="mt-4 mt-md-0">
                            <a href="change-password.php" class="btn btn-outline-warning btn-pill px-4 py-2 me-2"><i class="fa fa-key me-1"></i>Change Password</a>
                            <form method="POST" action="logout.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-outline-danger btn-pill px-4 py-2"><i class="fa fa-sign-out-alt me-1"></i>Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Details Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-primary">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-primary"><i class="fas fa-user"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Username</div>
                        <div class="fw-bold text-primary fs-5"><?php echo htmlspecialchars($user['username'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-info">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-info"><i class="fas fa-envelope"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Email</div>
                        <div class="fw-bold text-info fs-5"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-warning">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-warning"><i class="fas fa-phone"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Phone</div>
                        <div class="fw-bold text-warning fs-5"><?php echo htmlspecialchars($user['telephone_number'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-success">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-success"><i class="fas fa-calendar-alt"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Marital Status</div>
                        <div class="fw-bold text-success fs-5"><?php echo htmlspecialchars($profile['married_status'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-secondary">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-secondary"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Address</div>
                        <div class="fw-bold text-secondary fs-5"><?php echo htmlspecialchars($profile['address'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-dark">
                    <div class="card-body">
                        <div class="mb-2 fs-2 text-dark"><i class="fas fa-flag"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Nationality</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($profile['nationality'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'user_footer.php'; ?>
