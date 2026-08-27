<?php
/**
 * Authentication / authorization middleware.
 *
 * Every protected page and every API endpoint must call require_auth()
 * (and require_role() where relevant) before doing anything else. Each
 * call independently re-checks the session AND re-queries the user's
 * current status in the database — this is what makes admin-side
 * suspension take effect on the very next request from the affected
 * user, without any separate token-revocation table.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('verapay_session');
    session_start();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME_MINUTES * 60) {
        destroy_session();
        return;
    }
    $_SESSION['last_activity'] = time();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_json_request(): bool
{
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
}

function deny_unauthenticated(string $message = 'Please sign in to continue.'): never
{
    destroy_session();
    if (is_json_request()) {
        json_response(false, null, $message, 401);
    }
    header('Location: /login');
    exit;
}

function deny_suspended(): never
{
    destroy_session();
    $message = 'Your account has been suspended. Contact support for assistance.';
    if (is_json_request()) {
        json_response(false, null, $message, 403);
    }
    header('Location: /suspended');
    exit;
}

/**
 * Validates the session and re-checks live account status. Returns the
 * authenticated user row on success; never returns on failure.
 */
function require_auth(): array
{
    bootstrap_session();

    if (empty($_SESSION['user_id'])) {
        deny_unauthenticated();
    }

   $stmt = db()->prepare('SELECT id, name, email, role, status, avatar_initials, gender, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        deny_unauthenticated();
    }

    if ($user['status'] !== 'active') {
        deny_suspended();
    }

    return $user;
}

function require_role(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        if (is_json_request()) {
            json_response(false, null, 'You do not have permission to perform this action.', 403);
        }
        http_response_code(403);
        echo 'You do not have permission to view this page.';
        exit;
    }
}

function csrf_token(): string
{
    bootstrap_session();
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';

    if ($sent === '' || !hash_equals($expected, $sent)) {
        if (is_json_request()) {
            json_response(false, null, 'Your session has expired. Please refresh and try again.', 419);
        }
        http_response_code(419);
        echo 'Your session has expired. Please refresh and try again.';
        exit;
    }
}

/** Call at the top of every API endpoint. Combines auth + CSRF + optional role check. */
function api_guard(array $roles = []): array
{
    $user = require_auth();
    verify_csrf();
    if (!empty($roles)) {
        require_role($user, ...$roles);
    }
    return $user;
}

// ---- Login rate limiting ----

function login_is_locked_out(string $email, string $ip): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?) AND succeeded = 0
           AND created_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, $ip, LOGIN_LOCKOUT_MINUTES]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function record_login_attempt(string $email, string $ip, bool $succeeded): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)');
    $stmt->execute([$email, $ip, $succeeded ? 1 : 0]);
}

function write_audit_log(?int $actorId, string $action, string $targetType, ?int $targetId, array $metadata = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (actor_id, action, target_type, target_id, metadata, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$actorId, $action, $targetType, $targetId, json_encode($metadata), $_SERVER['REMOTE_ADDR'] ?? null]);
}
