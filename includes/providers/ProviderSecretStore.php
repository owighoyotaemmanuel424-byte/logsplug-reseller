<?php
declare(strict_types=1);
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../crypto.php';

final class ProviderSecretStore
{
    public static function get(string $providerId, string $secretName): ?string
    {
        $st=db()->prepare('SELECT secret_ciphertext FROM provider_secrets WHERE provider_id=? AND secret_name=? LIMIT 1');
        $st->execute([$providerId,$secretName]);
        $cipher=$st->fetchColumn();
        if (!is_string($cipher) || $cipher==='') return null;
        return decryptSecret($cipher);
    }

    public static function put(string $providerId, string $secretName, string $secret): void
    {
        $cipher=encryptSecret($secret);
        db()->prepare('INSERT INTO provider_secrets(provider_id,secret_name,secret_ciphertext,updated_at)
            VALUES(?,?,?,CURRENT_TIMESTAMP)
            ON CONFLICT(provider_id,secret_name) DO UPDATE SET secret_ciphertext=EXCLUDED.secret_ciphertext,updated_at=CURRENT_TIMESTAMP')
            ->execute([$providerId,$secretName,$cipher]);
    }

    public static function delete(string $providerId, string $secretName): void
    {
        db()->prepare('DELETE FROM provider_secrets WHERE provider_id=? AND secret_name=?')->execute([$providerId,$secretName]);
    }
}
