<?php
declare(strict_types=1);

function providerSchema(string $id): array
{
    require_once __DIR__.'/../ProviderSchema.php';
    return ProviderSchema::load($id);
}
