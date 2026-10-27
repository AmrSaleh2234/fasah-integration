<?php

namespace AmrSaleh\FasahPay\Events;

class SettlementNotificationReceived
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function fasahInvoiceNumber(): ?string
    {
        $number = $this->payload['invoiceNumber'] ?? $this->payload['fasahpayInvoiceNumber'] ?? null;

        return $number !== null ? (string) $number : null;
    }

    public function internalInvoiceNumber(): ?string
    {
        $number = $this->payload['internalInvoiceNumber'] ?? null;

        return $number !== null ? (string) $number : null;
    }

    public function sadadNumber(): ?string
    {
        $number = $this->payload['sadadNumber'] ?? null;

        return $number !== null ? (string) $number : null;
    }
}
