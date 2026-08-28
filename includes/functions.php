<?php
/**
 * Small shared helpers: output escaping, formatting, pagination.
 */

/** HTML-escape for safe output. Use on every dynamic value printed into markup. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Realistic per-user profile photo, sourced live from randomuser.me's
 * stable portrait endpoints (/portraits/{men|women}/0-99.jpg). Every user
 * gets one: gender-matched when it's on file, otherwise deterministically
 * assigned from the user id so it's still stable across visits. The index
 * is likewise derived from the id so the same user always gets the same photo.
 */
function user_avatar_photo_url(array $user): string
{
    $gender = $user['gender'] ?? null;
    if ($gender !== 'male' && $gender !== 'female') {
        $gender = ((int) $user['id']) % 2 === 0 ? 'female' : 'male';
    }
    $folder = $gender === 'male' ? 'men' : 'women';
    $index = ((int) $user['id']) % 100;
    return "https://randomuser.me/api/portraits/{$folder}/{$index}.jpg";
}

/** Always sized via the caller's wrapping element (w-full h-full), so callers keep controlling avatar size there. */
function user_avatar_markup(array $user): string
{
    $photoUrl = user_avatar_photo_url($user);
    $initials = e($user['avatar_initials'] ?? substr($user['name'], 0, 2));

    // onerror swaps to the initials fallback in place if the external photo fails to load.
    return '<span class="relative block w-full h-full">'
        . '<img src="' . e($photoUrl) . '" alt="" class="absolute inset-0 w-full h-full rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer" '
        . 'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
        . '<span class="hidden absolute inset-0 rounded-full items-center justify-center bg-brand-muted text-brand-emphasis font-semibold">' . $initials . '</span>'
        . '</span>';
}

function money_format(string $amount, string $currency = 'INR'): string
{
    $symbols = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format((float) $amount, 2);
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'success', 'active', 'open', 'approved' => 'badge-success',
        'pending', 'processing' => 'badge-warning',
        'failed', 'suspended', 'rejected' => 'badge-danger',
        'refunded', 'info' => 'badge-info',
        default => 'badge-neutral',
    };
}

function status_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

/**
 * Reads and validates a page/per_page pair from query params for
 * server-side pagination. Always returns safe, bounded integers.
 */
function paginate_params(int $defaultPerPage = 20, int $maxPerPage = 100): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? $defaultPerPage);
    $perPage = max(1, min($maxPerPage, $perPage));
    return [$page, $perPage, ($page - 1) * $perPage];
}

function json_response(bool $success, $data = null, string $message = '', int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

/**
 * Canonical list of KYC document slots — shared by the KYC Verification
 * page and its API endpoints so the UI and the DB enum never drift apart.
 */
function kyc_document_types(): array
{
    return [
        'aadhar_card' => 'Aadhar Card',
        'pan_card' => 'PAN Card',
        'gst_certificate' => 'GST Certificate',
        'board_resolution' => 'Board Resolution',
        'certificate_of_incorporation' => 'Certificate of Incorporation',
        'passport_photo' => 'Passport Size Photo',
        'service_agreement' => 'Service Agreement',
    ];
}

/**
 * Minimal HS256 JWT encoder/decoder for platform API bearer tokens
 * (Payment gateways → API Credentials → Generate Bearer Token). No external
 * library needed — HS256 is just base64url(header).base64url(payload)
 * signed with HMAC-SHA256, which PHP's hash_hmac() does natively.
 */
function jwt_encode(array $payload, string $secret): string
{
    $b64url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    $header = $b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body = $b64url(json_encode($payload));
    $signature = $b64url(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

    return "{$header}.{$body}.{$signature}";
}

/**
 * Verifies signature + expiry and returns the decoded payload, or null if
 * the token is malformed, mis-signed, or expired. Available for any
 * endpoint that wants to accept the platform bearer token as an
 * authentication method going forward.
 */
function jwt_decode_verify(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header, $body, $signature] = $parts;

    $b64url = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    $expected = $b64url(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
        return null;
    }

    return $payload;
}

function generate_reference(string $type): string
{
    $prefix = $type === 'deposit' ? 'DX' : 'WX';
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function current_route(): string
{
    return $_GET['route'] ?? 'dashboard';
}
