<?php
declare(strict_types=1);

final class ProviderSchema
{
    public static function load(string $providerId): array
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $providerId);
        $file = __DIR__.'/schemas/'.$safe.'.php';
        if (!is_file($file)) throw new RuntimeException('Provider schema not found.');
        $schema = require $file;
        if (!is_array($schema)) throw new RuntimeException('Invalid provider schema.');
        return $schema;
    }
}
