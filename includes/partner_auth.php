<?php
/**
 * Partner (server-to-server) API authentication.
 *
 * A partner request carries its own opaque bearer token (issued per
 * business customer via client_api_credentials), not a PHP session — so
 * this is deliberately separate from api_guard() in auth.php. It
 * resolves to the SAME $user array shape api_guard() returns, so any
 * endpoint or shared service that already accepts a $user from
 * api_guard() works unchanged when called from a partner_guard() caller.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php'; // write_audit_log()

/**
 * Validates the Authorization header against client_api_credentials,
 * enforces that client's IP whitelist (if configured), and returns the
 * owning user row. Never returns on failure — sends a JSON error and
 * exits, same contract as api_guard().
 */
function partner_guard(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        json_response(false, null, 'Missing or malformed Authorization header. Expected: Bearer <token>.', 401);
    }
    $token = $matches[1];
    $tokenHash = hash('sha256', $token);

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT c.id AS credential_id, c.user_id, c.status AS credential_status,
                u.id, u.name, u.email, u.role, u.status
         FROM client_api_credentials c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.token_hash = ?'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row || $row['credential_status'] !== 'active') {
        json_response(false, null, 'Invalid or revoked API token.', 401);
    }

    if ($row['status'] !== 'active') {
        json_response(false, null, 'This account is suspended.', 403);
    }

    partner_enforce_ip_whitelist($pdo, (int) $row['credential_id']);

    // Same shape as api_guard()'s return value: id, name, email, role, status.
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'role' => $row['role'],
        'status' => $row['status'],
    ];
}

/**
 * If this client has configured one or more whitelisted IPs, the
 * caller's IP must be one of them. No entries configured -> skipped
 * rather than locking the client out by default.
 */
function partner_enforce_ip_whitelist(PDO $pdo, int $credentialId): void
{
    $stmt = $pdo->prepare('SELECT ip_address FROM client_whitelisted_ips WHERE credential_id = ?');
    $stmt->execute([$credentialId]);
    $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allowed)) {
        return;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($clientIp, $allowed, true)) {
        json_response(false, null, 'Request origin is not authorized for this API token.', 403);
    }
}

/**
 * Generates a new opaque bearer token for a client: vpk_live_<40 hex
 * chars>. Returns the plaintext token (show it to the admin/customer
 * exactly once) plus what to store: its SHA-256 hash and last 4 chars.
 * Nothing beyond this function ever sees or stores the plaintext token.
 */
function generate_client_api_token(): array
{
    $token = 'vpk_live_' . bin2hex(random_bytes(20));

    return [
        'token' => $token,
        'token_hash' => hash('sha256', $token),
        'token_last4' => substr($token, -4),
    ];
}