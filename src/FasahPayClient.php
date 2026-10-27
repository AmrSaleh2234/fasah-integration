<?php

namespace AmrSaleh\FasahPay;

use AmrSaleh\FasahPay\Auth\TokenManager;
use AmrSaleh\FasahPay\Enums\InvoiceType;
use AmrSaleh\FasahPay\Exceptions\RequestException;
use AmrSaleh\FasahPay\Invoice\InvoiceBuilder;
use AmrSaleh\FasahPay\Support\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FasahPayClient
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TokenManager $tokens,
    ) {}

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function tokens(): TokenManager
    {
        return $this->tokens;
    }

    /**
     * Start a fluent invoice pre-filled with this connection's biller VAT and payment method.
     */
    public function invoice(InvoiceType|string $type = InvoiceType::General): InvoiceBuilder
    {
        return InvoiceBuilder::make(
            (string) $this->connection->billerVatNumber(),
            $this->connection->paymentMethod,
            $this,
        )->type($type);
    }

    public function http(): PendingRequest
    {
        $this->connection->assertComplete();

        return Http::baseUrl($this->connection->baseUrl())
            ->timeout($this->connection->timeout)
            ->acceptJson()
            ->withHeaders([
                'X-Tabadul-Client-Id' => $this->connection->get('client_id'),
                'X-Tabadul-Client-Secret' => $this->connection->get('client_secret'),
                'Authorization' => 'Bearer '.$this->tokens->token(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createInvoice(InvoiceType|string $type, array $payload): array
    {
        $type = InvoiceType::resolve($type);

        return $this->handle(
            $this->send(fn () => $this->http()->post($type->path(), $payload)),
            "createInvoice({$type->value})",
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateInvoice(InvoiceType|string $type, array $payload): array
    {
        $type = InvoiceType::resolve($type);

        return $this->handle(
            $this->send(fn () => $this->http()->put($type->path(), $payload)),
            "updateInvoice({$type->value})",
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function subscribeInvoiceNotifications(string $url): array
    {
        return $this->handle($this->send(fn () => $this->http()->post('/subscribenotifications', [
            'notificationURL' => $url,
            'providerType' => $this->connection->providerType,
        ])), 'subscribeInvoiceNotifications');
    }

    /**
     * @return array<string, mixed>
     */
    public function subscribeSettlementNotifications(string $url): array
    {
        return $this->handle($this->send(fn () => $this->http()->post('/subscribe-settlement', [
            'providerType' => $this->connection->providerType,
            'settlementUrl' => $url,
        ])), 'subscribeSettlementNotifications');
    }

    /**
     * Retry once with a fresh token on 401.
     *
     * @param  callable(): Response  $callback
     */
    private function send(callable $callback): Response
    {
        $response = $callback();

        if ($response->status() === 401) {
            $this->tokens->forget();
            $response = $callback();
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(Response $response, string $operation): array
    {
        if ($response->failed()) {
            $data = $response->json();

            throw new RequestException(
                $response->status(),
                "Fasah Pay {$operation} failed: ".$response->status().' '.$response->body(),
                is_array($data) ? $data : null,
            );
        }

        return $response->json() ?? [];
    }
}
