<?php
declare(strict_types=1);

date_default_timezone_set((string) (getenv('GUARDIAN_TIMEZONE') ?: 'Australia/Sydney'));

function app_environment(): string
{
    return strtolower((string) (getenv('GUARDIAN_APP_ENV') ?: 'local'));
}

function app_key(): string
{
    $key = (string) (getenv('GUARDIAN_APP_KEY') ?: '');
    if ($key !== '') {
        return $key;
    }
    if (app_environment() === 'production') {
        throw new RuntimeException('GUARDIAN_APP_KEY is required in production.');
    }
    return hash('sha256', __DIR__ . '|guardian-vault-local-development-key');
}

function encrypt_secret(string $plainText): string
{
    $key = hash('sha256', app_key(), true);
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipherText === false) {
        throw new RuntimeException('Unable to encrypt secret.');
    }
    return base64_encode($iv . $tag . $cipherText);
}

function decrypt_secret(string $encoded): string
{
    $payload = base64_decode($encoded, true);
    if ($payload === false || strlen($payload) < 29) {
        throw new RuntimeException('Invalid encrypted secret.');
    }
    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipherText = substr($payload, 28);
    $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', hash('sha256', app_key(), true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plainText === false) {
        throw new RuntimeException('Unable to decrypt secret.');
    }
    return $plainText;
}

function base32_encode_bytes(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        $output .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $output;
}

function base32_decode_bytes(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value) ?? '')) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) {
            throw new InvalidArgumentException('Invalid Base32 value.');
        }
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $output .= chr(bindec($chunk));
    }
    return $output;
}

function verify_totp(string $secret, string $code, ?int $timestamp = null): bool
{
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $key = base32_decode_bytes($secret);
    $counter = intdiv($timestamp ?? time(), 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $movingFactor = pack('N2', 0, $counter + $offset);
        $hash = hash_hmac('sha1', $movingFactor, $key, true);
        $index = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$index]) & 0x7f) << 24) | ((ord($hash[$index + 1]) & 0xff) << 16) | ((ord($hash[$index + 2]) & 0xff) << 8) | (ord($hash[$index + 3]) & 0xff);
        if (hash_equals(str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT), $code)) return true;
    }
    return false;
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return getenv('GUARDIAN_TRUST_PROXY') === '1'
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    if (app_environment() === 'production' && PHP_SAPI !== 'cli' && !request_is_https()) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('HTTPS is required.');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('GUARDIANSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'httponly' => true,
        'secure' => request_is_https(),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    start_secure_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function configured_positive_int(string $name, int $default): int
{
    $value = filter_var(getenv($name), FILTER_VALIDATE_INT);
    return $value && $value > 0 ? $value : $default;
}

function enforce_session_lifetime(string $loginLocation): void
{
    $now = time();
    $idleLimit = configured_positive_int('GUARDIAN_SESSION_IDLE_MINUTES', 30) * 60;
    $absoluteLimit = configured_positive_int('GUARDIAN_SESSION_ABSOLUTE_HOURS', 12) * 3600;
    $started = (int) ($_SESSION['auth_started_at'] ?? $now);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
    if (($now - $lastActivity) > $idleLimit || ($now - $started) > $absoluteLimit) {
        destroy_session();
        header('Location: ' . $loginLocation . '?error=session-expired');
        exit;
    }
    $_SESSION['auth_started_at'] = $started;
    $_SESSION['last_activity'] = $now;
}

function establish_user_session(array $user): void
{
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['auth_type'] = 'user';
    $_SESSION['loggedin'] = (int) $user['id'];
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);
    $_SESSION['auth_started_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user'] = array_intersect_key($user, array_flip(['id', 'username', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status']));
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function establish_admin_session(array $admin, bool $mfaVerified = false): void
{
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['auth_type'] = 'admin';
    $_SESSION['is_admin'] = true;
    $_SESSION['loggedin'] = (int) $admin['id'];
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_role'] = (string) ($admin['role'] ?? 'operator');
    $_SESSION['session_version'] = (int) ($admin['session_version'] ?? 1);
    $_SESSION['auth_started_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['user'] = array_intersect_key($admin, array_flip(['id', 'username', 'first_name', 'last_name', 'email', 'telephone_number', 'role', 'status']));
    if ($mfaVerified) $_SESSION['mfa_verified_at'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_user(): void
{
    start_secure_session();
    if (($_SESSION['auth_type'] ?? '') !== 'user' || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    enforce_session_lifetime('login.php');
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status, session_version FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'Active' || (int) $user['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)) {
        destroy_session();
        header('Location: login.php?error=account-unavailable');
        exit;
    }
    $_SESSION['loggedin'] = (int) $user['id'];
    $_SESSION['user'] = $user;
}

function require_admin(): void
{
    start_secure_session();
    if (($_SESSION['auth_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
        header('Location: admin_login.php');
        exit;
    }
    enforce_session_lifetime('admin_login.php');
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, telephone_number, role, status, session_version, totp_secret FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin || $admin['status'] !== 'Active' || (int) $admin['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)) {
        destroy_session();
        header('Location: admin_login.php?error=account-unavailable');
        exit;
    }
    $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!empty($admin['totp_secret']) && empty($_SESSION['mfa_verified_at'])) {
        destroy_session();
        header('Location: admin_login.php?error=mfa-required');
        exit;
    }
    if (getenv('GUARDIAN_ADMIN_MFA_REQUIRED') === '1' && empty($admin['totp_secret']) && !in_array($currentPage, ['mfa-setup.php', 'logout.php'], true)) {
        header('Location: mfa-setup.php?required=1');
        exit;
    }
    $_SESSION['loggedin'] = (int) $admin['id'];
    $_SESSION['admin_role'] = (string) $admin['role'];
    $_SESSION['user'] = $admin;
}

function admin_can(string $capability): bool
{
    $role = (string) ($_SESSION['admin_role'] ?? '');
    $permissions = [
        'super_admin' => ['view_users', 'manage_users', 'manage_admins', 'view_audit'],
        'operator' => ['view_users', 'manage_users'],
        'auditor' => ['view_users', 'view_audit'],
    ];
    return in_array($capability, $permissions[$role] ?? [], true);
}

function require_admin_capability(string $capability): void
{
    require_admin();
    if (!admin_can($capability)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

function log_security_event(string $realm, string $eventType, ?int $actorId = null, ?int $subjectId = null, ?string $details = null): void
{
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    try {
        $stmt = $pdo->prepare('INSERT INTO security_event_log (realm, event_type, actor_id, subject_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$realm, $eventType, $actorId, $subjectId, client_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $details]);
    } catch (Throwable $exception) {
        error_log('Security event log error: ' . $exception->getMessage());
    }
}

function login_identity_hash(string $username): string
{
    return hash('sha256', strtolower(trim($username)));
}

function login_is_rate_limited(string $realm, string $username): bool
{
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    $stmt = $pdo->prepare('SELECT SUM(username_hash = ? AND successful = 0) AS account_failures, SUM(ip_address = ? AND successful = 0) AS ip_failures FROM login_attempts WHERE realm = ? AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)');
    $stmt->execute([login_identity_hash($username), client_ip(), $realm]);
    $counts = $stmt->fetch() ?: [];
    return (int) ($counts['account_failures'] ?? 0) >= 5 || (int) ($counts['ip_failures'] ?? 0) >= 20;
}

function record_login_attempt(string $realm, string $username, bool $successful): void
{
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    $stmt = $pdo->prepare('INSERT INTO login_attempts (realm, username_hash, ip_address, successful) VALUES (?, ?, ?, ?)');
    $stmt->execute([$realm, login_identity_hash($username), client_ip(), $successful ? 1 : 0]);
    if (random_int(1, 100) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (CURRENT_TIMESTAMP - INTERVAL 30 DAY)");
    }
}

function log_admin_action(string $action, ?int $targetUserId = null, ?string $details = null): void
{
    if (($_SESSION['auth_type'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
        return;
    }
    require_once __DIR__ . '/db_conn.php';
    $pdo = db_connection();
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_activity_log (admin_id, action, target_user_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int) $_SESSION['admin_id'], $action, $targetUserId, $details, client_ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    } catch (Throwable $exception) {
        error_log('Admin activity log error: ' . $exception->getMessage());
    }
}

function record_user_revision(PDO $pdo, int $userId, string $action, ?array $snapshot): void
{
    $stmt = $pdo->prepare('INSERT INTO user_record_revisions (user_id, admin_id, action, snapshot) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, (int) ($_SESSION['admin_id'] ?? 0), $action, $snapshot === null ? null : json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

function destroy_session(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
