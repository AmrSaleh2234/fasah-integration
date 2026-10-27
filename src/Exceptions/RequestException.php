<?php

namespace AmrSaleh\FasahPay\Exceptions;

class RequestException extends FasahPayException
{
    /**
     * @param  array<string, mixed>|null  $responseData
     */
    public function __construct(
        public readonly int $httpStatus,
        string $message,
        public readonly ?array $responseData = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * True when Fasah may have processed the request (timeouts / 5xx).
     * Check the invoice status on Fasah before retrying to avoid duplicates.
     */
    public function isUncertain(): bool
    {
        return $this->httpStatus >= 500 || $this->httpStatus === 408;
    }

    public function correlationId(): ?string
    {
        $id = $this->responseData['correlationId'] ?? null;

        return is_scalar($id) ? (string) $id : null;
    }
}
