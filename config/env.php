<?php
/**
 * Minimal .env loader (no external dependency). Parses KEY=VALUE lines,
 * ignores comments/blank lines, and populates getenv()/$_ENV.
 */

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

load_env(dirname(__DIR__) . '/.env');

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    if (in_array(strtolower($value), ['true', 'false'], true)) {
        return strtolower($value) === 'true';
    }
    return $value;
}
