<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';

function encryptionKey(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $raw = base64_decode(APP_ENCRYPTION_KEY, true);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException('APP_ENCRYPTION_KEY must be base64-encoded 32 bytes.');
    }
    return $key = $raw;
}

function encryptSecret(string $plaintext): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) throw new RuntimeException('Secret encryption failed.');
    return base64_encode($iv.$tag.$cipher);
}

function decryptSecret(string $encoded): string
{
    $blob = base64_decode($encoded, true);
    if ($blob === false || strlen($blob) < 29) throw new RuntimeException('Invalid encrypted secret.');
    $iv = substr($blob, 0, 12);
    $tag = substr($blob, 12, 16);
    $cipher = substr($blob, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) throw new RuntimeException('Secret decryption failed.');
    return $plain;
}
