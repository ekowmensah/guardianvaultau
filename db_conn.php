<?php
declare(strict_types=1);

function db_connection(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $dbHost = (string) (getenv('GUARDIAN_DB_HOST') ?: 'localhost');
    $dbPort = (int) (getenv('GUARDIAN_DB_PORT') ?: 3306);
    $dbName = (string) (getenv('GUARDIAN_DB_NAME') ?: 'guardianvaultau');
    $configuredUser = getenv('GUARDIAN_DB_USER');
    $configuredPassword = getenv('GUARDIAN_DB_PASSWORD');
    $environment = strtolower((string) (getenv('GUARDIAN_APP_ENV') ?: 'local'));
    $isLocalRequest = PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

    if ($environment === 'production' && (!$configuredUser || $configuredPassword === false || $configuredPassword === '')) {
        throw new RuntimeException('Production database credentials are not configured.');
    }
    if (!$isLocalRequest && (!$configuredUser || $configuredPassword === false || $configuredPassword === '')) {
        throw new RuntimeException('Database credentials are required for non-local requests.');
    }

    $dbUser = (string) ($configuredUser ?: 'root');
    $dbPassword = (string) ($configuredPassword !== false ? $configuredPassword : '');

    try {
        $connection = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );
        return $connection;
    } catch (PDOException $exception) {
        error_log('Guardian Vault database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('The application is temporarily unavailable.');
    }
}

$pdo = db_connection();
