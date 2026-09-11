<?php
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");

// If the user is not logged in, redirect to the login page
require_user();

$userID = $_SESSION['loggedin'];

// Retrieve the item details associated with the user
$stmtItem = $pdo->prepare("SELECT * FROM item_details WHERE user_id = ?");
$stmtItem->execute([$userID]);
$itemDetails = $stmtItem->fetch();

// Retrieve the state of items associated with the user
$stmtStateOfItems = $pdo->prepare("SELECT * FROM state_of_items WHERE user_id = ?");
$stmtStateOfItems->execute([$userID]);
$stateOfItems = $stmtStateOfItems->fetch();

include 'user_header.php';
?>
<main class="col-lg-10 ms-sm-auto main-content">
    <div class="container py-2" style="margin-top:0;">
        <!-- Gold Deposit Overview -->
        <div class="card shadow mb-4 border-0 bg-light">
            <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                <div>
                    <h4 class="fw-bold mb-1 text-primary">Gold Deposit Overview</h4>
                    <div class="mb-2">
                        <span class="me-3"><i class="fas fa-shield-alt text-gold me-1"></i> <strong>Insurance #:</strong> <?php echo htmlspecialchars($itemDetails['insurance_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="me-3"><i class="fas fa-barcode text-primary me-1"></i> <strong>Reference:</strong> <?php echo htmlspecialchars($itemDetails['reference_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="me-3"><i class="fas fa-random text-primary me-1"></i> <strong>Transaction:</strong> <?php echo htmlspecialchars($itemDetails['transaction_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
</tr>
                    <span class="badge bg-secondary me-2"><?= $itemDetails ? 'Record on file' : 'No deposit record' ?></span>
                </div>
               <!-- <div class="mt-3 mt-md-0">
                    <button class="btn btn-outline-primary me-2" data-bs-toggle="tooltip" title="Download your deposit statement"><i class="fas fa-file-download"></i> Download Statement</button>
                    <button class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Print this page"><i class="fas fa-print"></i> Print</button>
                </div> -->
            </div>
        </div>

        <!-- Modern Responsive Details Cards -->
        <div class="row g-2">
    <!-- Item Details Card -->
    <div class="col-12 col-lg-7">
        <div class="card shadow h-100 border-start-primary">
            <div class="card-header bg-white border-0 pb-0 d-flex align-items-center">
                <span class="fw-bold text-primary" style="font-size:1.3rem; letter-spacing:1px; text-transform:uppercase;">
                    <i class="fas fa-cube me-2"></i> ITEM DETAILS
                </span>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <?php
                    $items = [
                        ['icon' => 'fa-cube', 'label' => 'Box Dimension', 'field' => 'box_dimension', 'color' => 'text-secondary'],
                        ['icon' => 'fa-archive', 'label' => 'Deposited Item', 'field' => 'deposited_item', 'color' => 'text-warning'],
                        ['icon' => 'fa-box', 'label' => 'Package Type', 'field' => 'package_type', 'color' => 'text-success'],
                        ['icon' => 'fa-boxes', 'label' => 'Package Quantity', 'field' => 'package_quantity', 'color' => 'text-success'],
                        ['icon' => 'fa-weight-hanging', 'label' => 'Total Weight', 'field' => 'total_weight', 'suffix' => ' kg', 'color' => 'text-info'],
                        ['icon' => 'fa-calendar-alt', 'label' => 'Deposit Date', 'field' => 'deposit_date', 'format_date' => true, 'color' => 'text-primary'],
                        ['icon' => 'fa-dollar-sign', 'label' => 'Monthly Charges', 'field' => 'monthly_charges', 'prefix' => '$', 'format_number' => true, 'color' => 'text-success'],
                        ['icon' => 'fa-money-bill-wave', 'label' => 'Amount Paid', 'field' => 'amount_paid', 'prefix' => '$', 'format_number' => true, 'color' => 'text-success'],
                    ];

                    foreach ($items as $item) {
                        $value = $itemDetails[$item['field']] ?? '-';
                        if (!empty($item['format_number']) && $value !== '-') {
                            $value = number_format($value, 2);
                        }
                        if (!empty($item['format_date']) && $value !== '-') {
                            $value = date('jS F, Y', strtotime($value));
                        }
                        $value = (!empty($item['prefix']) ? $item['prefix'] : '') . $value . (!empty($item['suffix']) ? $item['suffix'] : '');
                        $safeValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        echo <<<HTML
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body text-center">
                                    <div class="mb-2 fs-2 {$item['color']}"><i class="fas {$item['icon']}"></i></div>
                                    <div class="fw-bold text-uppercase text-muted small mb-1">{$item['label']}</div>
                                    <div class="fw-bold text-uppercase text-primary fs-6">{$safeValue}</div>
                                </div>
                            </div>
                        </div>
                        HTML;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- State Of Items Card -->
    <div class="col-12 col-lg-5">
        <div class="card shadow h-100 border-start-success">
            <div class="card-header bg-white border-0 pb-0 d-flex align-items-center">
                <span class="fw-bold text-success" style="font-size:1.1rem; letter-spacing:1px; text-transform:uppercase;">
                    <i class="fas fa-clipboard-check me-2"></i> STATE OF ITEMS
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="fw-bold text-uppercase text-muted text-end pe-3" style="width:45%; font-size:1rem;">
                                    <i class="fas fa-coins me-1 text-warning"></i> Current Gold Worth:
                                </td>
                                <td class="fw-bold text-uppercase text-primary ps-2" style="font-size:1.1rem;">
                                    $<?php echo isset($stateOfItems['current_gold_worth']) ? number_format($stateOfItems['current_gold_worth'], 2) : '-'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-uppercase text-muted text-end pe-3" style="font-size:1rem;">
                                    <i class="fas fa-balance-scale me-1 text-info"></i> Price per Kilogram:
                                </td>
                                <td class="fw-bold text-uppercase text-primary ps-2" style="font-size:1.1rem;">
                                    $<?php echo isset($stateOfItems['price_per_kilogram']) ? number_format($stateOfItems['price_per_kilogram'], 2) : '-'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-uppercase text-muted text-end pe-3" style="font-size:1rem;">
                                    <i class="fas fa-lock me-1 text-primary"></i> Cost of Safe Keeping:
                                </td>
                                <td class="fw-bold text-uppercase text-primary ps-2" style="font-size:1.1rem;">
                                    $<?php echo isset($stateOfItems['cost_of_safe_keeping']) ? number_format($stateOfItems['cost_of_safe_keeping'], 2) : '-'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-uppercase text-muted text-end pe-3" style="font-size:1rem;">
                                    <i class="fas fa-calendar-day me-1 text-secondary"></i> Date of Safe Keeping:
                                </td>
                                <td class="fw-bold text-uppercase text-primary ps-2" style="font-size:1.1rem;">
                                    <?php echo isset($stateOfItems['date_of_safe_keeping']) ? date('jS F, Y', strtotime($stateOfItems['date_of_safe_keeping'])) : '-'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-uppercase text-muted text-end pe-3" style="font-size:1rem;">
                                    <i class="fas fa-cubes me-1 text-success"></i> Quantity:
                                </td>
                                <td class="fw-bold text-uppercase text-primary ps-2" style="font-size:1.1rem;">
                                    <?php echo htmlspecialchars($stateOfItems['quantity'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</main>
<?php include 'user_footer.php'; ?>
