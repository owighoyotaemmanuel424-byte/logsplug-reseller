<?php
declare(strict_types=1);
require_once __DIR__.'/../crypto.php';
final class ProviderSecretStore {
 public static function encrypt(string $secret):string{return encryptSecret($secret);}
 public static function decrypt(string $ciphertext):string{return decryptSecret($ciphertext);}
}
