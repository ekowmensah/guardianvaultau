<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('view_users');
require_once __DIR__ . '/../db_conn.php';

try {
  if (!isset($_GET['id'])) {
    throw new Exception('No user ID specified.');
  }
    $user_id = intval($_GET['id']);
    $user = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status, created_at, updated_at FROM users WHERE id = ?');
    $user->execute([$user_id]);
    $user = $user->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception('User not found.');
    $profile = $pdo->prepare('SELECT * FROM userprofile WHERE user_id = ?');
    $profile->execute([$user_id]);
    $profile = $profile->fetch(PDO::FETCH_ASSOC);
    $item = $pdo->prepare('SELECT * FROM item_details WHERE user_id = ?');
    $item->execute([$user_id]);
    $item = $item->fetch(PDO::FETCH_ASSOC);
    $state = $pdo->prepare('SELECT * FROM state_of_items WHERE user_id = ?');
    $state->execute([$user_id]);
    $state = $state->fetch(PDO::FETCH_ASSOC);
    $kin = $pdo->prepare('SELECT * FROM next_of_kin WHERE user_id = ?');
    $kin->execute([$user_id]);
    $kin = $kin->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('Admin user view error: ' . $e->getMessage());
  echo "<div class='alert alert-danger'>Unable to load the user record.</div>";
    exit;
}
?>
<?php include 'admin_header.php'; ?>
<main class="col-md-10 ms-sm-auto main-content">
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card shadow border-0 statement-card mb-4">
          <div class="card-body p-4 premium-bg">
  <div class="statement-header mb-4 print-hide">
    <div class="d-flex align-items-center">
      <img src="../assets/img/thelogseclogo.png" alt="Guardian Vault logo" class="me-3" style="height:48px;">
      <div>
        <span class="fs-3 fw-bold text-primary">Vault Bank</span>
        <div class="text-muted small">Digital Gold Vault - Secure. Trusted. Verified.</div>
      </div>
      <span class="badge bg-secondary ms-4 fs-6 verified-badge"><i class="fa fa-database me-1"></i>Current Account Record</span>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary me-2" id="printBtn"><i class="fa fa-print me-1"></i>Print</button>
    </div>
  </div>
  <div class="statement-summary-box mb-4">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <div class="fs-5 fw-semibold text-primary mb-1"><i class="fa fa-user-circle me-2 text-gold"></i><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="mb-1"><strong>Username:</strong> <span class="text-dark"><?= htmlspecialchars($user['username']) ?></span></div>
        <div class="mb-1"><strong>Email:</strong> <span class="text-dark"><?= htmlspecialchars($user['email']) ?></span></div>
        <div><strong>Role:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></div>
      </div>
      <div class="col-md-4 text-md-end">
        <div class="mb-1"><strong>Viewed:</strong> <span class="text-dark"><?= date('F j, Y') ?></span></div>
        <div class="mb-1"><strong>Account ID:</strong> <span class="text-dark"><?= 'USR-' . str_pad($user['id'], 6, '0', STR_PAD_LEFT) ?></span></div>
      </div>
    </div>
  </div>
  <div class="statement-divider mb-3"></div>
  <div id="statement-content">

            <div class="statement-section mb-4">
  <div class="statement-section-header"><i class="fa fa-user text-gold me-2"></i>Account Holder Information</div>
  <table class="table table-bordered align-middle mb-0 bg-white">
    <tbody>
      <tr><th scope="row">Full Name</th><td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td></tr>
      <tr><th scope="row">Username</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
      <tr><th scope="row">Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
      <tr><th scope="row">Role</th><td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td></tr>
    </tbody>
  </table>
</div>
<div class="statement-section mb-4">
  <div class="statement-section-header"><i class="fa fa-address-card text-gold me-2"></i>Profile Details</div>
  <?php if ($profile): ?>
    <table class="table table-bordered align-middle mb-0 bg-white">
      <tbody>
        <?php foreach ($profile as $key => $value): ?>
  <?php if ($key !== 'user_id' && $key !== 'id'): ?>
    <tr>
      <th scope="row" class="text-capitalize"><?= ucwords(str_replace('_', ' ', $key)) ?></th>
      <td>
        <?php if ($key === 'married_status'): ?>
          <?php if ($value === 'Single'): ?>
            <span class="badge bg-primary">Single</span>
          <?php elseif ($value === 'Married'): ?>
            <span class="badge bg-success">Married</span>
          <?php elseif ($value === 'Divorced'): ?>
            <span class="badge bg-warning text-dark">Divorced</span>
          <?php else: ?>
            <?= $value === null || $value === '' ? '<span class=\'badge bg-secondary bg-opacity-25 text-muted\'>N/A</span>' : htmlspecialchars($value) ?>
          <?php endif; ?>
        <?php else: ?>
          <?= $value === null || $value === '' ? '<span class=\'badge bg-secondary bg-opacity-25 text-muted\'>N/A</span>' : htmlspecialchars($value) ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endif; ?>
<?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="text-muted">No profile info.</div>
  <?php endif; ?>
</div>

            <div class="statement-section mb-4">
  <div class="statement-section-header"><i class="fa fa-box-open text-gold me-2"></i>Item Details</div>
  <?php if ($item): ?>
    <table class="table table-bordered align-middle mb-0 bg-white">
      <tbody>
        <?php foreach ($item as $key => $value): ?>
          <?php if ($key !== 'user_id' && $key !== 'id'): ?>
            <tr>
              <th scope="row" class="text-capitalize"><?= ucwords(str_replace('_', ' ', $key)) ?></th>
              <td><?= $value === null || $value === '' ? '<span class=\'badge bg-secondary bg-opacity-25 text-muted\'>N/A</span>' : htmlspecialchars($value) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="text-muted">No item details.</div>
  <?php endif; ?>
</div>
<div class="statement-section mb-4">
  <div class="statement-section-header"><i class="fa fa-warehouse text-gold me-2"></i>State of Items</div>
  <?php if ($state): ?>
    <table class="table table-bordered align-middle mb-0 bg-white">
      <tbody>
        <?php foreach ($state as $key => $value): ?>
          <?php if ($key !== 'user_id' && $key !== 'id'): ?>
            <tr>
              <th scope="row" class="text-capitalize"><?= ucwords(str_replace('_', ' ', $key)) ?></th>
              <td><?= $value === null || $value === '' ? '<span class=\'badge bg-secondary bg-opacity-25 text-muted\'>N/A</span>' : htmlspecialchars($value) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="text-muted">No state info.</div>
  <?php endif; ?>
</div>

            <div class="statement-section mb-4">
  <div class="statement-section-header"><i class="fa fa-users text-gold me-2"></i>Next of Kin</div>
  <?php if ($kin): ?>
    <table class="table table-bordered align-middle mb-0 bg-white">
      <tbody>
        <?php foreach ($kin as $key => $value): ?>
          <?php if ($key !== 'user_id' && $key !== 'id'): ?>
            <tr>
              <th scope="row" class="text-capitalize"><?= ucwords(str_replace('_', ' ', $key)) ?></th>
              <td><?= $value === null || $value === '' ? '<span class=\'badge bg-secondary bg-opacity-25 text-muted\'>N/A</span>' : htmlspecialchars($value) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="text-muted">No next of kin info.</div>
  <?php endif; ?>
</div>

            <!-- Watermark/accent -->
            <div class="statement-watermark">GUARDIAN VAULT</div>
            </div> <!-- #statement-content -->
            <div class="statement-divider mb-4"></div>
            <footer class="statement-footer text-center py-3">
              <div class="mb-2 small text-muted">This screen shows the current database record and is not an immutable account statement.</div>
              <div class="mt-2 small text-muted">For inquiries: <a href="mailto:support@guardian-vault.com" class="text-primary text-decoration-none">support@guardian-vault.com/</a> | +1 (800) 555-VAULT</div>
              <div class="mt-2 small text-muted">&copy; <?= date('Y') ?> Guardian Vault. All rights reserved.</div>
            </footer>
            <div class="d-flex justify-content-end mt-4 print-hide">
              <a href="user-list.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i>Back</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<style>
  .premium-bg { background: #f8fafc; }
  .statement-card { position: relative; background: #fff; border-radius: 1rem; border: 1.5px solid #ffc107; box-shadow: 0 2px 16px 0 #e3e7f1; }
  .statement-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ffc107; padding-bottom: 0.75rem; }
  .verified-badge { border-radius: 0.5rem; font-weight: 600; letter-spacing: 1px; box-shadow: 0 2px 4px 0 #f4e7c1; }
  .statement-summary-box { background: #fffbe6; border: 1.5px solid #ffc107; border-radius: 0.75rem; padding: 1.25rem 1.5rem; box-shadow: 0 2px 8px 0 #f4e7c1; }
  .statement-divider { border-bottom: 2px dashed #ffc107; margin: 2rem 0 1.5rem 0; }
  .statement-section-header {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1a237e;
    background: #f6f8fc;
    padding: 0.5rem 1rem;
    border-left: 5px solid #ffc107;
    margin-bottom: 0.5rem;
    border-radius: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 1px 4px 0 #e3e7f1;
  }
  .statement-section { margin-bottom: 2rem; }
  .statement-section table {
    border-color: #ffc107 !important;
  }
  .statement-section table th, .statement-section table td {
    background: #fff !important;
    border-color: #ffc107 !important;
    padding: 0.75rem 1rem;
    vertical-align: middle;
  }
  .statement-section table tbody tr:nth-child(odd) td {
    background: #fffbe6 !important;
  }
  .statement-section table tbody tr:hover td {
    background: #fff3cd !important;
    transition: background 0.2s;
  }
  .text-gold { color: #ffc107 !important; }
  .statement-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    font-size: 6rem;
    color: #ffc107;
    opacity: 0.08;
    font-weight: 900;
    font-family: 'Segoe UI', 'Arial', sans-serif;
    pointer-events: none;
    transform: translate(-50%, -50%) rotate(-18deg);
    user-select: none;
    z-index: 0;
    letter-spacing: 0.3em;
    text-shadow: 0 2px 16px #fffbe6;
  }
  .statement-footer {
    border-top: 2px solid #ffc107;
    background: #f6f8fc;
    border-radius: 0 0 1rem 1rem;
    margin-top: 2rem;
    font-size: 0.95rem;
  }
  .btn-outline-secondary, .btn-outline-success {
    border-width: 2px;
    font-weight: 500;
    letter-spacing: 0.5px;
  }
  .btn-outline-secondary:hover, .btn-outline-success:hover {
    background: #ffc107 !important;
    color: #1a237e !important;
    border-color: #ffc107 !important;
    box-shadow: 0 1px 8px #ffe082;
  }
  @media print {
    .print-hide, .print-hide * { display: none !important; }
    .statement-card { box-shadow: none !important; border: 1px solid #bbb !important; }
    body { background: #fff !important; }
    .statement-footer { color: #444 !important; background: #fff !important; border: none !important; }
    .statement-watermark { opacity: 0.13 !important; }
  }
</style>
<script>
  document.getElementById('printBtn').onclick = function() {
    window.print();
  };
</script>
<?php include 'admin_footer.php'; ?>
