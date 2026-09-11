<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
// If the user is not logged in, redirect to the login page
require_user();
// Retrieve the user's information from the session
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if (!$user) {
    header('Location: login.php');
    exit;
}

// Fetch gold deposit state for the current user
$userID = $_SESSION['loggedin'];
$stmtState = $pdo->prepare("SELECT * FROM state_of_items WHERE user_id = ?");
$stmtState->execute([$userID]);
$stateOfItems = $stmtState->fetch();

include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content">
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-0 bg-light">
                    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div>
                            <h2 class="fw-bold mb-1 text-primary">
                                <i class="fas fa-hand-paper me-2 text-warning"></i>
                                Welcome, <?php echo htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'User'); ?>!
                            </h2>
                            <div class="mb-2 lead text-muted">This is your secure Guardian Vault dashboard. Here you can view your gold deposit summary, manage your account, and access quick actions.</div>
                        </div>
                        <div class="text-center mt-3 mt-md-0">
                            <img src="assets/img/avatars/user.png" alt="Default account avatar" style="max-width:110px;" class="img-fluid rounded-circle border border-3 border-primary bg-white shadow-sm d-none d-md-inline-block">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Gold Deposit Summary Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-warning">
                    <div class="card-body text-center">
                        <div class="mb-2 fs-2 text-warning"><i class="fas fa-piggy-bank"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Gold Deposit</div>
                        <div class="fw-bold text-uppercase text-primary fs-5">
                            <span class="me-1">Worth:</span>
                            $<?php echo isset($stateOfItems['current_gold_worth']) ? number_format($stateOfItems['current_gold_worth'], 2) : '-'; ?>
                        </div>
                        <div class="text-muted small mt-2">Quantity: <span class="fw-semibold text-dark"><?php echo htmlspecialchars($stateOfItems['quantity'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <a href="gold-deposit.php" class="btn btn-outline-warning btn-sm mt-3"><i class="fa fa-arrow-right me-1"></i>View Details</a>
                    </div>
                </div>
            </div>
            <!-- Profile Quick Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-primary">
                    <div class="card-body text-center">
                        <div class="mb-2 fs-2 text-primary"><i class="fas fa-user"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Account Profile</div>
                        <div class="fw-bold text-uppercase text-primary fs-5">
                            <?php echo htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'User'); ?>
                        </div>
                        <div class="text-muted small mt-2">Status: <span class="badge bg-success"><?php echo htmlspecialchars($user['status'] ?? 'Active'); ?></span></div>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm mt-3"><i class="fa fa-user me-1"></i>View Profile</a>
                    </div>
                </div>
            </div>
            <!-- Statement Quick Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card shadow-sm h-100 border-start-success">
                    <div class="card-body text-center">
                        <div class="mb-2 fs-2 text-success"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="fw-bold text-uppercase text-muted small mb-1">Statement</div>
                        <div class="fw-bold text-uppercase text-primary fs-5">
                            <span class="me-1">Last Deposit:</span>
                            <?php echo isset($stateOfItems['date_of_safe_keeping']) ? date('jS F, Y', strtotime($stateOfItems['date_of_safe_keeping'])) : '-'; ?>
                        </div>
                        <a href="statement.php" class="btn btn-outline-success btn-sm mt-3"><i class="fa fa-file-invoice-dollar me-1"></i>View Statement</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Quick Actions -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h5 class="fw-bold text-primary mb-3"><i class="fa fa-bolt me-2 text-warning"></i>Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="gold-deposit.php" class="btn btn-outline-warning"><i class="fa fa-piggy-bank me-1"></i>Gold Deposit</a>
                            <a href="next-of-kin.php" class="btn btn-outline-info"><i class="fa fa-users me-1"></i>Beneficiary</a>
                            <a href="profile.php" class="btn btn-outline-primary"><i class="fa fa-user me-1"></i>Account Profile</a>
                            <a href="statement.php" class="btn btn-outline-success"><i class="fa fa-file-invoice-dollar me-1"></i>Statement</a>
                            <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-outline-danger"><i class="fa fa-sign-out-alt me-1"></i>Logout</button></form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Announcements or Tips -->
            <div class="col-12 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h5 class="fw-bold text-primary mb-3"><i class="fa fa-bullhorn me-2 text-info"></i>Announcements</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="fa fa-check-circle text-success me-2"></i>Review your deposit record and report any discrepancy promptly.</li>
                            <li class="mb-2"><i class="fa fa-shield-alt text-warning me-2"></i>Remember to update your beneficiary details regularly.</li>
                            <li><i class="fa fa-lightbulb text-primary me-2"></i>For support, contact our help desk anytime.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'user_footer.php'; ?>
