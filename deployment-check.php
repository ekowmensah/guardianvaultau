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
