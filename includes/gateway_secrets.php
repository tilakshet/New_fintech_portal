<?php
/**
 * Reversible storage for real payment gateway API secrets.
 *
 * payment_gateways.api_key_hash (password_hash/bcrypt) is one-way and can
 * never be turned back into the plaintext key an outbound API call needs
 * — it only ever proved useful for "does this match" checks, which nothing
 * in this codebase actually does. api_key_encrypted holds the same secret
 * encrypted with AES-256-GCM under GATEWAY_ENCRYPTION_KEY (env-only, never
 * stored in the database), so it can be decrypted back for real outbound
 * calls while still never appearing in the DB as plaintext.
 */

function gateway_encrypt_secret(string $plaintext): string
{
    $key = gateway_encryption_key();
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Failed to encrypt gateway secret.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function gateway_decrypt_secret(string $encoded): string
{
    $key = gateway_encryption_key();
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RuntimeException('Malformed encrypted gateway secret.');
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Failed to decrypt gateway secret — key mismatch or corrupted data.');
    }
    return $plaintext;
}

function gateway_encryption_key(): string
{
    $configured = GATEWAY_ENCRYPTION_KEY;
    if (!$configured) {
        throw new RuntimeException('GATEWAY_ENCRYPTION_KEY is not configured. Set it in .env before creating or rotating gateway secrets.');
    }

    $key = base64_decode((string) $configured, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('GATEWAY_ENCRYPTION_KEY must decode to exactly 32 bytes (base64 of a 256-bit key).');
    }
    return $key;
}
