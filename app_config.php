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
