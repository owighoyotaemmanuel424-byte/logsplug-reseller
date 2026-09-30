<?php
declare(strict_types=1);

interface ProviderInterface
{
    public function id(): string;
    public function label(): string;
    public function schema(): array;
    public function health(): array;
    public function walletBalance(): ?string;
    public function catalog(): array;
    public function createOrder(array $payload, string $idempotencyKey): array;
    public function getOrder(string $providerRef): array;
    public function createPayment(array $payload, string $idempotencyKey): array;
    public function verifyPayment(array $payload): array;
}
