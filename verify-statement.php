<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/db_conn.php';

$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$result = null;
if ($reference !== '') {
    $stmt = $pdo->prepare('SELECT statement_number, subject_user_id, snapshot, snapshot_hash, signature, issued_at FROM account_statements WHERE statement_number = ? LIMIT 1');
    $stmt->execute([$reference]);
    $statement = $stmt->fetch();
    if ($statement) {
        $actualHash = hash('sha256', $statement['snapshot']);
        $actualSignature = hash_hmac('sha256', $statement['statement_number'] . '|' . (int) $statement['subject_user_id'] . '|' . $actualHash, app_key());
        $result = hash_equals($statement['snapshot_hash'], $actualHash) && hash_equals($statement['signature'], $actualSignature)
            ? ['valid' => true, 'issued_at' => $statement['issued_at']]
            : ['valid' => false, 'issued_at' => $statement['issued_at']];
    } else {
        $result = ['valid' => false, 'issued_at' => null];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify statement | Guardian Vault</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h3">Verify a statement</h1>
        <p class="text-muted">Enter the statement reference shown on the PDF. No customer information is disclosed.</p>
        <form method="get" class="row g-2">
            <div class="col-sm-9"><label for="reference" class="visually-hidden">Statement reference</label><input id="reference" name="reference" class="form-control" maxlength="40" required value="<?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?>" placeholder="GV-YYYYMM-…"></div>
            <div class="col-sm-3"><button class="btn btn-primary w-100">Verify</button></div>
        </form>
        <?php if ($result): ?>
            <div class="alert <?= $result['valid'] ? 'alert-success' : 'alert-danger' ?> mt-4 mb-0">
                <?php if ($result['valid']): ?>This statement exists and its stored snapshot is intact. Issued <?= htmlspecialchars($result['issued_at'], ENT_QUOTES, 'UTF-8') ?> UTC.<?php else: ?>This statement could not be verified.<?php endif; ?>
            </div>
        <?php endif; ?>
    </div></div>
</main>
</body>
</html>
