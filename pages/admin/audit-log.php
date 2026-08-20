<?php
/** Admin only. Server-rendered (low traffic, internal page — no client fetch needed). */
$page = max(1, (int) ($_GET['ap'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT a.id, a.action, a.target_type, a.target_id, a.metadata, a.ip_address, a.created_at, u.name AS actor_name
     FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
     ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$total = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$actionLabels = [
    'login' => 'Signed in',
    'logout' => 'Signed out',
    'deposit_created' => 'Created a deposit',
    'withdrawal_created' => 'Created a withdrawal',
    'user_suspended' => 'Suspended a user',
    'user_reactivated' => 'Reactivated a user',
    'gateway_created' => 'Added a payment gateway',
    'gateway_status_changed' => 'Changed a gateway status',
    'gateway_set_default' => 'Set default gateway',
    'gateway_key_rotated' => 'Rotated a gateway key',
    'gateway_deleted' => 'Removed a payment gateway',
];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">A record of sensitive operator and account actions across Verapay.</p>
</div>

<div class="card !p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Actor</th>
                    <th scope="col">Action</th>
                    <th scope="col">Target</th>
                    <th scope="col">IP address</th>
                    <th scope="col">When</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="5" class="text-center py-10 text-text-secondary">No audit events recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['actor_name'] ?? 'System') ?></td>
                        <td><?= e($actionLabels[$log['action']] ?? $log['action']) ?></td>
                        <td class="text-text-secondary"><?= e($log['target_type']) ?> #<?= e((string) $log['target_id']) ?></td>
                        <td class="font-mono text-sm text-text-secondary"><?= e($log['ip_address'] ?? '—') ?></td>
                        <td class="text-text-secondary whitespace-nowrap"><?= e(date('M j, Y g:ia', strtotime($log['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-4 border-t border-border">
        <span class="text-sm text-text-secondary">Showing page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?> (<?= e((string) $total) ?> total)</span>
        <div class="flex items-center gap-2">
            <a href="/admin/audit-log?ap=<?= max(1, $page - 1) ?>" class="btn-secondary !px-4 !py-2 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
            <a href="/admin/audit-log?ap=<?= min($totalPages, $page + 1) ?>" class="btn-secondary !px-4 !py-2 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
        </div>
    </div>
</div>
