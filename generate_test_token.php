<?php
$token = 'vpk_live_' . bin2hex(random_bytes(20));

echo "TOKEN (use this in the Authorization header):\n{$token}\n\n";
echo "TOKEN_HASH (use this in the SQL insert):\n" . hash('sha256', $token) . "\n\n";
echo "TOKEN_LAST4 (use this in the SQL insert):\n" . substr($token, -4) . "\n";
