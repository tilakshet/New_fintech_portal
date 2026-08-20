<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = require_auth();
require_role($user, 'admin');

$stmt = db()->query(
    'SELECT id, display_name, provider, api_key_last4, status, is_default, created_at, updated_at
     FROM payment_gateways ORDER BY is_default DESC, created_at ASC'
);

json_response(true, ['gateways' => $stmt->fetchAll()], 'ok');
