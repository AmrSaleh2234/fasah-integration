<?php

namespace AmrSaleh\FasahPay;

use AmrSaleh\FasahPay\Auth\TokenManager;
use AmrSaleh\FasahPay\Exceptions\ConfigurationException;
use AmrSaleh\FasahPay\Support\Connection;
use Illuminate\Contracts\Config\Repository;

/**
 * @mixin FasahPayClient
 */
class FasahPayManager
{
    /** @var array<string, FasahPayClient> */
    private array $clients = [];

    public function __construct(private readonly Repository $config) {}

    public function getDefaultConnection(): string
    {
        return (string) $this->config->get('fasahpay.default', 'sandbox');
    }

    public function connection(?string $name = null): FasahPayClient
    {
        $name ??= $this->getDefaultConnection();

        return $this->clients[$name] ??= $this->make($name);
    }

    public function sandbox(): FasahPayClient
    {
        return $this->connection('sandbox');
    }

    public function production(): FasahPayClient
    {
        return $this->connection('production');
    }

    /**
     * Drop cached clients (useful after changing config at runtime).
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }

    private function make(string $name): FasahPayClient
    {
        $settings = $this->config->get("fasahpay.connections.{$name}");

        if (! is_array($settings)) {
            throw new ConfigurationException("Fasah Pay connection [{$name}] is not defined in config/fasahpay.php.");
        }

        $connection = new Connection(
            name: $name,
            config: $settings,
            timeout: (int) $this->config->get('fasahpay.timeout', 45),
            tokenTtl: (int) $this->config->get('fasahpay.cache.token_ttl', 3000),
            cacheStore: $this->config->get('fasahpay.cache.store') ?: null,
            paymentMethod: (string) $this->config->get('fasahpay.payment_method', 'Sadad'),
            providerType: (string) $this->config->get('fasahpay.provider_type', 'GEN'),
        );

        return new FasahPayClient($connection, new TokenManager($connection));
    }
}
