<?php
declare(strict_types=1);

function nairaDecimal(string|int $amount): string
{
    $value = is_int($amount) ? number_format($amount / 100, 2, '.', '') : trim($amount);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) throw new InvalidArgumentException('Invalid NGN amount.');
    [$whole,$frac] = array_pad(explode('.', $value, 2), 2, '0');
    return $whole.'.'.str_pad($frac, 2, '0');
}

function nairaKobo(string $amount): int
{
    $amount = nairaDecimal($amount);
    [$whole,$frac] = explode('.', $amount);
    $kobo = ((int)$whole * 100) + (int)$frac;
    if ($kobo < 0) throw new InvalidArgumentException('Amount cannot be negative.');
    return $kobo;
}

function nairaFormat(string|int $amount): string
{
    return '₦'.number_format(nairaKobo((string)$amount) / 100, 2, '.', ',');
}

function nairaAdd(string $a, string $b): string
{
    return nairaDecimal((string)(nairaKobo($a) + nairaKobo($b)));
}

function nairaMultiply(string $a, int $qty): string
{
    if ($qty < 0) throw new InvalidArgumentException('Quantity cannot be negative.');
    return nairaDecimal((string)(nairaKobo($a) * $qty));
}
