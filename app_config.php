<?php
declare(strict_types=1);

function guardian_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $configFile = __DIR__ . '/config.php';
    if (is_file($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded)) {
            $config = $loaded;
            return $config;
        }
    }

    $config = [];
    return $config;
}

function guardian_config_value(string $key, mixed $default = null): mixed
{
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    $config = guardian_config();
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

function guardian_deployment_check_enabled(): bool
{
    return (string) guardian_config_value('GUARDIAN_DEPLOYMENT_CHECK', '0') === '1';
}

function guardian_install_runtime_debug_handlers(): void
{
    if (!guardian_deployment_check_enabled() || PHP_SAPI === 'cli') {
        return;
    }

    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    set_exception_handler(static function (Throwable $exception): void {
        error_log('Guardian Vault runtime error: ' . $exception->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo "Guardian Vault runtime error\n\n";
        echo 'Type: ' . get_class($exception) . "\n";
        echo 'Message: ' . $exception->getMessage() . "\n";
        echo 'File: ' . $exception->getFile() . "\n";
        echo 'Line: ' . $exception->getLine() . "\n";
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        error_log('Guardian Vault fatal error: ' . $error['message']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo "Guardian Vault fatal error\n\n";
        echo 'Type: ' . $error['type'] . "\n";
        echo 'Message: ' . $error['message'] . "\n";
        echo 'File: ' . $error['file'] . "\n";
        echo 'Line: ' . $error['line'] . "\n";
    });
}
