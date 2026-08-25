<?php require_once __DIR__ . '/../../includes/banner.php';
/** Admin only. Static reference page — collapsible endpoints, copyable code blocks. */
$extraScripts = ['/assets/js/pages/gateway-docs.js'];

function method_badge(string $method): string
{
    $class = $method === 'GET' ? 'badge-info' : 'badge-success';
    return "<span class=\"{$class} font-mono\">{$method}</span>";
}

function code_block(string $json): string
{
    return '<div class="code-block group">'
        . '<pre><code>' . e($json) . '</code></pre>'
        . '<button type="button" class="code-copy-btn" data-copy-text="' . e($json) . '" aria-label="Copy code to clipboard">'
        . icon('copy', 'w-4 h-4 copy-icon-default')
        . icon('check', 'w-4 h-4 copy-icon-copied hidden text-success')
        . '</button>'
        . '</div>';
}

$endpoints = [
    [
        'slug' => 'list',
        'method' => 'GET',
        'path' => '/api/admin/gateways/list.php',
        'summary' => 'List every configured gateway, most-recently-default first.',
        'request' => null,
        'response' => <<<JSON
{
  "success": true,
  "data": {
    "gateways": [
      {
        "id": 1,
        "display_name": "Primary Processor",
        "provider": "razorpay",
        "api_key_last4": "a1B9",
        "status": "active",
        "is_default": 1,
        "created_at": "2026-07-11 07:37:24",
        "updated_at": "2026-08-20 07:37:24"
      }
    ]
  },
  "message": "ok"
}
JSON,
        'notes' => 'The full API key is never returned — only api_key_last4. There is no endpoint that returns a stored key in full.',
    ],
    [
        'slug' => 'create',
        'method' => 'POST',
        'path' => '/api/admin/gateways/create.php',
        'summary' => 'Add a new gateway configuration. Starts inactive — activate it explicitly once you\'re ready to route traffic through it.',
        'request' => <<<JSON
{
  "display_name": "Backup Processor",
  "provider": "payu",
  "api_key": "sk_live_your_real_key_here"
}
JSON,
        'response' => <<<JSON
{
  "success": true,
  "data": { "id": 4 },
  "message": "Gateway added. Activate it when you are ready to accept traffic through it."
}
JSON,
        'notes' => "provider must be one of: razorpay, payu, cashfree, stripe, paypal, other. api_key must be at least 8 characters; only its hash and last 4 characters are stored.",
    ],
    [
        'slug' => 'update-status',
        'method' => 'POST',
        'path' => '/api/admin/gateways/update-status.php',
        'summary' => 'Activate or deactivate a gateway.',
        'request' => <<<JSON
{ "id": 4, "status": "active" }
JSON,
        'response' => <<<JSON
{ "success": true, "data": null, "message": "Backup Processor is now active." }
JSON,
        'notes' => 'status must be "active" or "inactive". Deactivating the current default is rejected — set another gateway as default first.',
    ],
    [
        'slug' => 'set-default',
        'method' => 'POST',
        'path' => '/api/admin/gateways/set-default.php',
        'summary' => 'Mark a gateway as the default processor. Unsets is_default on every other gateway in the same request.',
        'request' => <<<JSON
{ "id": 4 }
JSON,
        'response' => <<<JSON
{ "success": true, "data": null, "message": "Backup Processor is now the default gateway." }
JSON,
        'notes' => 'The target gateway must already be active — activate it first via update-status.php.',
    ],
    [
        'slug' => 'rotate-key',
        'method' => 'POST',
        'path' => '/api/admin/gateways/rotate-key.php',
        'summary' => "Replace a gateway's stored API key. The previous key is invalidated immediately.",
        'request' => <<<JSON
{ "id": 4, "api_key": "sk_live_the_new_key" }
JSON,
        'response' => <<<JSON
{
  "success": true,
  "data": { "api_key_last4": "3feb" },
  "message": "Backup Processor's key has been rotated."
}
JSON,
        'notes' => 'Same 8-character minimum as create.php. There is no "view current key" endpoint by design.',
    ],
    [
        'slug' => 'delete',
        'method' => 'POST',
        'path' => '/api/admin/gateways/delete.php',
        'summary' => 'Permanently remove a gateway configuration.',
        'request' => <<<JSON
{ "id" : 4 }
JSON,
        'response' => <<<JSON
{ "success": true, "data": null, "message": "Backup Processor has been removed." }
JSON,
        'notes' => 'Rejected if the gateway is the current default — reassign the default first. This cannot be undone.',
    ],
];

render_hero_banner(
    $user,
    'Gateway documentation',
    'Reference for the payment gateway management API. All endpoints live under /api/admin/gateways/.'
); ?>
<div class="mb-6 flex justify-end">
    <a href="/admin/gateways" class="btn-secondary shrink-0"><?= icon('gateway', 'w-4 h-4') ?>Manage gateways</a>
</div>

<nav class="flex flex-wrap items-center gap-2 mb-6" aria-label="Jump to endpoint">
    <?php foreach ($endpoints as $ep): ?>
        <a href="#ep-<?= e($ep['slug']) ?>" class="doc-jump-link">
            <span class="font-mono text-xs font-bold <?= $ep['method'] === 'GET' ? 'text-info' : 'text-success' ?>"><?= e($ep['method']) ?></span>
            <?= e($ep['slug']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="card mb-5">
    <h2 class="card-title mb-3">Before you start</h2>
    <ul class="space-y-2 text-md text-text-secondary list-disc pl-5">
        <li><strong class="text-text-primary">Authentication:</strong> every endpoint requires an authenticated session with the <code class="font-mono text-sm">admin</code> role — operators and customers receive <code class="font-mono text-sm">403</code>.</li>
        <li><strong class="text-text-primary">CSRF:</strong> every <code class="font-mono text-sm">POST</code> requires an <code class="font-mono text-sm">X-CSRF-Token</code> header matching the current session's token (read from the <code class="font-mono text-sm">&lt;meta name="csrf-token"&gt;</code> tag if you're calling this from the browser console).</li>
        <li><strong class="text-text-primary">Response envelope:</strong> every response is <code class="font-mono text-sm">{ "success": bool, "data": ..., "message": "..." }</code>. Check <code class="font-mono text-sm">success</code>, not the HTTP status alone, before trusting <code class="font-mono text-sm">data</code>.</li>
        <li><strong class="text-text-primary">Exactly one default:</strong> the platform always routes new transactions through whichever gateway has <code class="font-mono text-sm">is_default: true</code>. <code class="font-mono text-sm">set-default.php</code> is the only way to change it, and it can't point at an inactive gateway.</li>
    </ul>
</div>

<div class="space-y-4">
    <?php foreach ($endpoints as $i => $ep): ?>
        <details class="doc-endpoint" id="ep-<?= e($ep['slug']) ?>" <?= $i === 0 ? 'open' : '' ?>>
            <summary>
                <?= method_badge($ep['method']) ?>
                <code class="font-mono text-md text-text-primary font-semibold flex-1"><?= e($ep['path']) ?></code>
                <?= icon('chevron-down', 'w-4 h-4 text-text-secondary shrink-0 doc-chevron') ?>
            </summary>
            <div class="doc-endpoint-body">
                <p class="text-md text-text-secondary mb-4"><?= e($ep['summary']) ?></p>

                <?php if ($ep['request']): ?>
                    <p class="text-sm font-semibold text-text-primary mb-1.5">Request body</p>
                    <?= code_block($ep['request']) ?>
                    <div class="mt-3"></div>
                <?php endif; ?>

                <p class="text-sm font-semibold text-text-primary mb-1.5">Response</p>
                <?= code_block($ep['response']) ?>

                <p class="text-sm text-text-secondary mt-3"><?= icon('alert-circle', 'w-3.5 h-3.5 inline -mt-0.5 mr-1') ?><?= e($ep['notes']) ?></p>
            </div>
        </details>
    <?php endforeach; ?>
</div>

<div class="card mt-5">
    <h2 class="card-title mb-3">Error responses</h2>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Meaning</th>
                </tr>
            </thead>
            <tbody>
                <tr><td class="font-mono text-sm">422</td><td>Validation failed — check <code class="font-mono text-sm">message</code> for which field</td></tr>
                <tr><td class="font-mono text-sm">403</td><td>Not signed in as an admin, or CSRF token missing/invalid</td></tr>
                <tr><td class="font-mono text-sm">404</td><td>No gateway exists with the given <code class="font-mono text-sm">id</code></td></tr>
                <tr><td class="font-mono text-sm">500</td><td>Unexpected server error — the real cause is written to the server log, never returned in the response</td></tr>
            </tbody>
        </table>
    </div>
</div>
