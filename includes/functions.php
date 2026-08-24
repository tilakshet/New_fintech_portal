<?php
/**
 * Small shared helpers: output escaping, formatting, pagination.
 */

/** HTML-escape for safe output. Use on every dynamic value printed into markup. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function user_avatar_markup(array $user, string $iconClass = 'w-4 h-4'): string
{
    $gender = $user['gender'] ?? null;
    if ($gender === 'male') {
        return icon('avatar-male', $iconClass);
    }
    if ($gender === 'female') {
        return icon('avatar-female', $iconClass);
    }
    return e($user['avatar_initials'] ?? substr($user['name'], 0, 2));
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

function generate_reference(string $type): string
{
    $prefix = $type === 'deposit' ? 'DX' : 'WX';
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function current_route(): string
{
    return $_GET['route'] ?? 'dashboard';
}
