<?php
declare(strict_types=1);

require_once __DIR__ . '/app_config.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ((string) guardian_config_value('GUARDIAN_DEPLOYMENT_CHECK', '0') !== '1') {
    http_response_code(404);
    exit('Deployment check is disabled.');
}

$checks = [];
$checks[] = ['PHP version', PHP_VERSION];
$checks[] = ['PDO MySQL loaded', extension_loaded('pdo_mysql') ? 'yes' : 'no'];
$checks[] = ['config.php present', is_file(__DIR__ . '/config.php') ? 'yes' : 'no'];

$dbHost = (string) guardian_config_value('GUARDIAN_DB_HOST', 'localhost');
$dbPort = (int) guardian_config_value('GUARDIAN_DB_PORT', 3306);
$dbName = (string) guardian_config_value('GUARDIAN_DB_NAME', '');
$dbUser = (string) guardian_config_value('GUARDIAN_DB_USER', '');
$dbPassword = (string) guardian_config_value('GUARDIAN_DB_PASSWORD', '');
$appKey = (string) guardian_config_value('GUARDIAN_APP_KEY', '');

$checks[] = ['database host', $dbHost];
$checks[] = ['database port', (string) $dbPort];
$checks[] = ['database name set', $dbName !== '' && $dbName !== 'cpaneluser_guardianvaultau' ? 'yes' : 'no'];
$checks[] = ['database user set', $dbUser !== '' && $dbUser !== 'cpaneluser_guardianapp' ? 'yes' : 'no'];
$checks[] = ['database password set', $dbPassword !== '' && !str_contains($dbPassword, 'replace-with-') ? 'yes' : 'no'];
$checks[] = ['app key set', $appKey !== '' && strlen($appKey) >= 32 && !str_contains($appKey, 'replace-with-') ? 'yes' : 'no'];
$sessionPath = session_save_path() ?: sys_get_temp_dir();
$checks[] = ['session path', $sessionPath];
$checks[] = ['session path writable', is_writable($sessionPath) ? 'yes' : 'no'];
$checks[] = ['HTTPS detected', (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' ? 'yes' : 'no'];

echo "Guardian Vault deployment check\n\n";
foreach ($checks as [$label, $value]) {
    echo str_pad($label . ':', 24) . $value . "\n";
}

if (!extension_loaded('pdo_mysql')) {
    echo "\nDatabase connection: skipped because pdo_mysql is not enabled.\n";
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
    echo "\nDatabase connection: ok\n";
    echo "Tables found: {$tables}\n";
    $requiredTables = ['admin_users', 'users', 'login_attempts', 'security_event_log', 'admin_activity_log', 'data_quality_issues', 'schema_migrations'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        echo "Table {$table}: " . ((int) $stmt->fetchColumn() === 1 ? 'ok' : 'missing') . "\n";
    }
    $requiredColumns = [
        'admin_users' => ['id', 'username', 'password', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status', 'session_version', 'totp_secret'],
        'login_attempts' => ['realm', 'username_hash', 'ip_address', 'successful', 'attempted_at'],
        'security_event_log' => ['realm', 'event_type', 'actor_id', 'subject_id', 'ip_address', 'user_agent', 'details'],
        'admin_activity_log' => ['admin_id', 'action', 'target_user_id', 'details', 'ip_address', 'user_agent'],
        'data_quality_issues' => ['resolved_at'],
    ];
    foreach ($requiredColumns as $table => $columns) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $actualColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($columns, $actualColumns));
        echo "Columns {$table}: " . ($missing === [] ? 'ok' : 'missing ' . implode(',', $missing)) . "\n";
    }
    if ($tables > 0) {
        echo "Admin accounts: " . (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() . "\n";
        echo "Active admins: " . (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'Active'")->fetchColumn() . "\n";
        echo "Active super admins: " . (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'Active' AND role = 'super_admin'")->fetchColumn() . "\n";
        echo "Admins with MFA: " . (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE totp_secret IS NOT NULL AND totp_secret <> ""')->fetchColumn() . "\n";
        echo "User accounts: " . (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";
        $firstAdmin = $pdo->query('SELECT username, password, role, status, session_version FROM admin_users ORDER BY id LIMIT 1')->fetch();
        if ($firstAdmin) {
            echo "First admin username: " . $firstAdmin['username'] . "\n";
            echo "First admin status: " . $firstAdmin['status'] . "\n";
            echo "First admin role: " . $firstAdmin['role'] . "\n";
            echo "First admin password hash: " . (password_get_info((string) $firstAdmin['password'])['algo'] !== 0 ? 'ok' : 'not a password_hash value') . "\n";
        }
        try {
            (int) $pdo->query('SELECT COUNT(*) FROM data_quality_issues WHERE resolved_at IS NULL')->fetchColumn();
            (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
            echo "Admin dashboard queries: ok\n";
        } catch (Throwable $dashboardException) {
            echo "Admin dashboard queries: failed\n";
            echo "Dashboard error: " . $dashboardException->getMessage() . "\n";
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO login_attempts (realm, username_hash, ip_address, successful) VALUES (?, ?, ?, ?)');
            $stmt->execute(['admin', hash('sha256', 'deployment-check'), '127.0.0.1', 0]);
            $stmt = $pdo->prepare('INSERT INTO security_event_log (realm, event_type, actor_id, subject_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(['admin', 'deployment_check', null, null, '127.0.0.1', 'deployment-check', 'write test']);
            $stmt = $pdo->prepare('INSERT INTO admin_activity_log (admin_id, action, target_user_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([1, 'deployment_check', null, 'write test', '127.0.0.1', 'deployment-check']);
            $pdo->rollBack();
            echo "Database write test: ok\n";
        } catch (Throwable $writeException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "Database write test: failed\n";
            echo "Write error: " . $writeException->getMessage() . "\n";
            echo "Likely fix: grant INSERT, UPDATE, DELETE, and SELECT privileges to the configured database user in cPanel.\n";
        }
    }
    if ($appKey === '' || strlen($appKey) < 32 || str_contains($appKey, 'replace-with-')) {
        echo "\nRequired fix: set GUARDIAN_APP_KEY in config.php to a random string of at least 32 characters. Keep the same value after launch; changing it later invalidates encrypted MFA secrets and signed statement verification.\n";
    }
} catch (Throwable $exception) {
    $sqlState = $exception instanceof PDOException && isset($exception->errorInfo[0]) ? (string) $exception->errorInfo[0] : 'n/a';
    $driverCode = $exception instanceof PDOException && isset($exception->errorInfo[1]) ? (string) $exception->errorInfo[1] : 'n/a';
    echo "\nDatabase connection: failed\n";
    echo "SQLSTATE: {$sqlState}\n";
    echo "Driver code: {$driverCode}\n";
    if ($driverCode === '1045') {
        echo "Meaning: MySQL rejected the configured database user or password, or that user has not been granted access to this database.\n";
        echo "Likely fix: in cPanel MySQL Databases, reset the database user password, assign that user to the database, grant ALL PRIVILEGES, then put the full prefixed database name and username in config.php.\n";
    } elseif ($driverCode === '1049') {
        echo "Meaning: the configured database name does not exist on this MySQL server.\n";
        echo "Likely fix: import database/schema.sql into the exact database named in config.php.\n";
    } elseif ($driverCode === '2002') {
        echo "Meaning: PHP could not reach the configured MySQL host.\n";
        echo "Likely fix: ask the host for the MySQL hostname. It may not be localhost on this cPanel account.\n";
    } else {
        echo "Likely fix: confirm the cPanel database name, database user, password, host, and user privileges.\n";
    }
}
