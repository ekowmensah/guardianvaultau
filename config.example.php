<?php
declare(strict_types=1);

return [
    'GUARDIAN_APP_ENV' => 'production',
    'GUARDIAN_DB_HOST' => 'localhost',
    'GUARDIAN_DB_PORT' => 3306,
    'GUARDIAN_DB_NAME' => 'cpaneluser_guardianvaultau',
    'GUARDIAN_DB_USER' => 'cpaneluser_guardianapp',
    'GUARDIAN_DB_PASSWORD' => 'replace-with-the-cpanel-database-password',
    'GUARDIAN_APP_KEY' => 'replace-with-at-least-32-random-characters',
    'GUARDIAN_SESSION_IDLE_MINUTES' => 30,
    'GUARDIAN_SESSION_ABSOLUTE_HOURS' => 12,
    'GUARDIAN_TIMEZONE' => 'Australia/Sydney',
    'GUARDIAN_ADMIN_MFA_REQUIRED' => '0',
    'GUARDIAN_TRUST_PROXY' => '0',
    'GUARDIAN_COOKIE_PATH' => '',
    'GUARDIAN_SESSION_NAME' => '',
    // Temporarily set to '1' only while checking a live deployment, then set back to '0'.
    'GUARDIAN_DEPLOYMENT_CHECK' => '0',
];
