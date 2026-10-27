<?php

namespace AmrSaleh\FasahPay\Support;

use AmrSaleh\FasahPay\Exceptions\ConfigurationException;

final class Connection
{
    private const REQUIRED = ['token_url', 'invoice_base_url', 'client_id', 'client_secret', 'username', 'password'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly string $name,
        public readonly array $config,
        public readonly int $timeout = 45,
        public readonly int $tokenTtl = 3000,
        public readonly ?string $cacheStore = null,
        public readonly string $paymentMethod = 'Sadad',
        public readonly string $providerType = 'GEN',
    ) {}

    public function isProduction(): bool
    {
        return $this->name === 'production';
    }

    public function get(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public function billerVatNumber(): ?string
    {
        return $this->get('biller_vat_number');
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->get('invoice_base_url'), '/');
    }

    public function tokenCacheKey(): string
    {
        return "fasahpay.jwt.{$this->name}.".md5((string) $this->get('client_id').'|'.(string) $this->get('username'));
    }

    /**
     * @throws ConfigurationException
     */
    public function assertComplete(): void
    {
        $missing = array_values(array_filter(self::REQUIRED, fn (string $key) => $this->get($key) === null));

        if ($missing !== []) {
            throw new ConfigurationException(
                "Missing Fasah Pay config for connection [{$this->name}]: ".implode(', ', $missing).'. Check your .env.'
            );
        }
    }
}
