<?php

namespace AmrSaleh\FasahPay\Events;

class InvoiceNotificationReceived
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function fasahInvoiceNumber(): ?string
    {
        $number = $this->payload['fasahpayInvoiceNumber'] ?? $this->payload['FasahPayInvoiceNumber'] ?? null;

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

    /**
     * UNPAID | PAID | WAITING | CAN ...
     */
    public function status(): string
    {
        return strtoupper((string) ($this->payload['invoiceStatus'] ?? ''));
    }

    public function isPaid(): bool
    {
        return $this->status() === 'PAID';
    }

    public function isCancelled(): bool
    {
        return $this->status() === 'CAN';
    }
}
