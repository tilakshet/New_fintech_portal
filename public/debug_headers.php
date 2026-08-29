<?php
$keys = array_filter(array_keys($_SERVER), fn($k) => str_contains(strtoupper($k), 'AUTH'));
foreach ($keys as $k) { echo "$k = {$_SERVER[$k]}\n"; }
if (empty($keys)) { echo "no AUTH-related SERVER keys found\n"; }
echo "getallheaders: "; var_export(function_exists('getallheaders') ? getallheaders() : 'not available'); echo "\n";
