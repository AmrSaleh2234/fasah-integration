<?php

namespace AmrSaleh\FasahPay\Enums;

use AmrSaleh\FasahPay\Exceptions\FasahPayException;

enum InvoiceType: string
{
    case General = 'general';
    case BillOfLading = 'billoflading';
    case Declaration = 'declaration';
    case CustomBroker = 'custombroker';
    case Importer = 'importer';
    case ShippingAgent = 'shippingagent';

    /**
     * Accepts the enum, its value, or a common alias (bl, broker, shipping_agent...).
     */
    public static function resolve(self|string $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'general' => self::General,
            'billoflading', 'bill_of_lading', 'bl' => self::BillOfLading,
            'declaration' => self::Declaration,
            'custombroker', 'customs_broker', 'broker' => self::CustomBroker,
            'importer' => self::Importer,
            'shippingagent', 'shipping_agent' => self::ShippingAgent,
            default => throw new FasahPayException("Unsupported Fasah invoice type: {$type}"),
        };
    }

    /**
     * Endpoint path appended to the invoice base URL.
     */
    public function path(): string
    {
        return match ($this) {
            self::General => '',
            default => '/'.$this->value,
        };
    }

    /**
     * Required `details()` keys (snake_case) for this invoice type.
     *
     * @return list<string>
     */
    public function requiredDetails(): array
    {
        return match ($this) {
            self::General => ['company_name', 'consumer_email', 'consumer_mobile'],
            self::BillOfLading => ['bill_of_lading', 'shipment_type', 'port'],
            self::Declaration => ['declaration_number', 'declaration_date', 'declaration_type', 'port'],
            self::CustomBroker => ['customs_broker_license', 'license_type', 'port'],
            self::Importer => ['importer_number', 'port'],
            self::ShippingAgent => ['shipping_agent_number', 'port'],
        };
    }
}
