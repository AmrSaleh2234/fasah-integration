<?php

namespace AmrSaleh\FasahPay\Facades;

use AmrSaleh\FasahPay\Enums\InvoiceType;
use AmrSaleh\FasahPay\FasahPayClient;
use AmrSaleh\FasahPay\FasahPayManager;
use AmrSaleh\FasahPay\Invoice\InvoiceBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FasahPayClient connection(?string $name = null)
 * @method static FasahPayClient sandbox()
 * @method static FasahPayClient production()
 * @method static InvoiceBuilder invoice(InvoiceType|string $type = InvoiceType::General)
 * @method static array createInvoice(InvoiceType|string $type, array $payload)
 * @method static array updateInvoice(InvoiceType|string $type, array $payload)
 * @method static array subscribeInvoiceNotifications(string $url)
 * @method static array subscribeSettlementNotifications(string $url)
 *
 * @see FasahPayManager
 */
class FasahPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FasahPayManager::class;
    }
}
