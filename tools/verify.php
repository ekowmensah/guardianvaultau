<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function verify_check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $exitCode);
    verify_check($exitCode === 0, 'PHP syntax error: ' . substr($path, strlen($root) + 1));
}

$dangerousPatterns = [
    '/\bmd5\s*\(/i' => 'MD5 usage',
    '/\buniqid\s*\(/i' => 'predictable uniqid usage',
    '/\bif\s*\(\s*false\s*\)/i' => 'dead if(false) branch',
    '/https?:\/\/(?:cdn\.|cdnjs\.|stackpath\.|unpkg\.)/i' => 'external CDN dependency',
];

foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'html'], true)) {
        continue;
    }
    $path = $file->getPathname();
    if ($path === __FILE__ || basename($path) === 'fpdf.php') {
        continue;
    }
    $contents = file_get_contents($path);
    foreach ($dangerousPatterns as $pattern => $label) {
        verify_check($contents !== false && !preg_match($pattern, $contents), $label . ': ' . substr($path, strlen($root) + 1));
    }
}

try {
    require_once $root . '/db_conn.php';
    $pdo = db_connection();

    $requiredMigrations = [
        '001_production_hardening',
        '003_statement_snapshots',
        '004_admin_mfa',
        '005_remove_redundant_indexes',
    ];
    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredMigrations as $migration) {
        verify_check(in_array($migration, $applied, true), 'Missing migration: ' . $migration);
    }

    $requiredColumns = [
        'users' => ['status', 'session_version'],
        'admin_users' => ['role', 'status', 'session_version', 'totp_secret'],
        'account_statements' => ['statement_number', 'subject_user_id', 'snapshot_hash', 'signature'],
        'next_of_kin' => ['address'],
    ];
    $columnQuery = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    foreach ($requiredColumns as $table => $columns) {
        $columnQuery->execute([$table]);
        $actual = $columnQuery->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $column) {
            verify_check(in_array($column, $actual, true), "Missing column: {$table}.{$column}");
        }
    }

    foreach (['userprofile', 'item_details', 'state_of_items', 'next_of_kin'] as $table) {
        $indexQuery = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND NON_UNIQUE = 0"
        );
        $indexQuery->execute([$table]);
        verify_check((int) $indexQuery->fetchColumn() === 1, "Missing unique user_id index: {$table}");
    }
} catch (Throwable $exception) {
    $failures[] = 'Database verification failed: ' . $exception->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "Verification failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Verification passed ({$checks} checks).\n";
