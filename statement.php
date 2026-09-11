<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
// If the user is not logged in, redirect to the login page
require_user();
$userID = $_SESSION['loggedin'];

// Retrieve latest state of items for summary
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
                                <i class="fas fa-file-invoice-dollar me-2 text-success"></i>
                                Gold Deposit Statement
                            </h2>
                            <div class="mb-2 lead text-muted">Below is your current gold deposit summary. Each downloaded PDF is issued as an immutable statement snapshot with a unique reference.</div>
                        </div>
                        <div class="text-center mt-3 mt-md-0">
                            <a href="statement-pdf.php" class="btn btn-primary me-2"><i class="fa fa-file-pdf me-1"></i> Download PDF</a>
<a href="#" class="btn btn-outline-secondary" onclick="window.print(); return false;"><i class="fa fa-print me-1"></i> Print Statement</a>
                            <a href="verify-statement.php" class="btn btn-outline-primary"><i class="fa fa-shield-alt me-1"></i> Verify PDF</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gold Deposit Summary -->
    <!--    <div class="row g-4 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm h-100 border-start-warning">
                    <div class="card-body">
                        <h5 class="fw-bold text-warning mb-3"><i class="fas fa-piggy-bank me-2"></i>Gold Deposit Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                <span><i class="fa fa-coins text-warning me-2"></i>Current Gold Worth</span>
                                <span class="fw-semibold text-primary">$<?php echo isset($stateOfItems['current_gold_worth']) ? number_format($stateOfItems['current_gold_worth'], 2) : '-'; ?></span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                <span><i class="fa fa-balance-scale text-info me-2"></i>Price per Kilogram</span>
                                <span class="fw-semibold text-primary">$<?php echo isset($stateOfItems['price_per_kilogram']) ? number_format($stateOfItems['price_per_kilogram'], 2) : '-'; ?></span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                <span><i class="fa fa-cubes text-success me-2"></i>Quantity</span>
                                <span class="fw-semibold text-primary"><?php echo htmlspecialchars($stateOfItems['quantity'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                <span><i class="fa fa-calendar-day text-secondary me-2"></i>Date of Safe Keeping</span>
                                <span class="fw-semibold text-primary"><?php echo isset($stateOfItems['date_of_safe_keeping']) ? date('jS F, Y', strtotime($stateOfItems['date_of_safe_keeping'])) : '-'; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>-->

        <!-- Deposit Details Table -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-8 mx-auto">
                <div class="card shadow-lg border-0">
                    <div class="card-body">
                        <h4 class="fw-bold text-primary mb-4"><i class="fas fa-table me-2"></i>Deposit Details</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 bg-white rounded-3 shadow-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:45%">Detail</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><i class="fa fa-coins text-warning me-2"></i>Current Gold Worth</td>
                                        <td class="fw-semibold text-primary">$<?php echo isset($stateOfItems['current_gold_worth']) ? number_format($stateOfItems['current_gold_worth'], 2) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-balance-scale text-info me-2"></i>Price per Kilogram</td>
                                        <td class="fw-semibold text-info">$<?php echo isset($stateOfItems['price_per_kilogram']) ? number_format($stateOfItems['price_per_kilogram'], 2) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-cubes text-secondary me-2"></i>Quantity</td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($stateOfItems['quantity'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-lock text-primary me-2"></i>Cost of Safe Keeping</td>
                                        <td class="fw-semibold text-primary">$<?php echo isset($stateOfItems['cost_of_safe_keeping']) ? number_format($stateOfItems['cost_of_safe_keeping'], 2) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><i class="fa fa-calendar-alt text-success me-2"></i>Date of Safe Keeping</td>
                                        <td class="fw-semibold text-success"><?php echo isset($stateOfItems['date_of_safe_keeping']) ? date('jS F, Y', strtotime($stateOfItems['date_of_safe_keeping'])) : '-'; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction History Table -->
        <!--<div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 text-center py-5">
                    <div class="card-body">
                        <h5 class="fw-bold text-success mb-3"><i class="fa fa-history me-2"></i>Transaction History</h5>
                        <div class="lead text-muted mb-0">
                            <i class="fa fa-info-circle me-2"></i>No transaction history available.
                        </div>
                    </div>
                </div>
            </div>
        </div>-->
    </div>
</main>
<?php include 'user_footer.php'; ?>
