# Fasah Integration for Laravel

[![tests](https://github.com/AmrSaleh2234/fasah-integration/actions/workflows/tests.yml/badge.svg)](https://github.com/AmrSaleh2234/fasah-integration/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A clean, flexible Laravel package for **Fasah Pay (Tabadul)** invoicing. Switch between **sandbox** and **production** with one env variable, build validated invoices fluently, and react to payment/settlement webhooks through Laravel events.

- Sandbox + production connections (plus any custom ones, e.g. multi-company)
- JWT auth with automatic caching and one-shot refresh on `401`
- Fluent, validated invoice builder (totals, VAT, Saudi mobile normalization)
- All invoice types: `general`, `billoflading`, `declaration`, `custombroker`, `importer`, `shippingagent`
- Built-in webhook endpoints that fire `InvoiceNotificationReceived` / `SettlementNotificationReceived`
- Typed exceptions, including an "uncertain outcome" flag to prevent duplicate invoices
- Artisan tools: `fasah:test-auth`, `fasah:subscribe-callbacks`

## Requirements

PHP 8.1+ and Laravel 10, 11 or 12.

## Installation

```bash
composer require amrsaleh2234/fasah-integration
php artisan vendor:publish --tag=fasahpay-config
```

The service provider and `FasahPay` facade are auto-discovered.

## Getting credentials from Fasah

Before the package can reach Fasah you need your IP whitelisted and sandbox credentials, then production credentials after sandbox sign-off. The full process with ready-to-send email templates is in [docs/ONBOARDING.md](docs/ONBOARDING.md).

## Configuration

Add to `.env`:

```dotenv
# sandbox | production
FASAHPAY_ENV=sandbox

FASAHPAY_BILLER_VAT_NUMBER=300000000000003   # your 15-digit VAT
FASAHPAY_PAYMENT_METHOD=Sadad                # Sadad = real bills, View = demo only

# Sandbox credentials
FASAHPAY_SANDBOX_CLIENT_ID=
FASAHPAY_SANDBOX_CLIENT_SECRET=
FASAHPAY_SANDBOX_USERNAME=
FASAHPAY_SANDBOX_PASSWORD=

# Production credentials
FASAHPAY_PRODUCTION_CLIENT_ID=
FASAHPAY_PRODUCTION_CLIENT_SECRET=
FASAHPAY_PRODUCTION_USERNAME=
FASAHPAY_PRODUCTION_PASSWORD=

# Webhooks
FASAHPAY_CALLBACK_SECRET=change-me
FASAHPAY_INVOICE_CALLBACK_URL=https://your-app.com/fasahpay/invoice-notification
FASAHPAY_SETTLEMENT_CALLBACK_URL=https://your-app.com/fasahpay/settlement-notification
```

Gateway URLs default to Tabadul's QA and public gateways and can be overridden per connection (`FASAHPAY_SANDBOX_TOKEN_URL`, `FASAHPAY_PRODUCTION_INVOICE_BASE_URL`, ...). All options are documented in `config/fasahpay.php`.

Verify credentials:

```bash
php artisan fasah:test-auth                       # uses FASAHPAY_ENV
php artisan fasah:test-auth --connection=production
```

## Usage

### Create an invoice

```php
use AmrSaleh\FasahPay\Facades\FasahPay;
use AmrSaleh\FasahPay\Enums\InvoiceType;

$response = FasahPay::invoice(InvoiceType::BillOfLading)
    ->internalNumber('INV-1001')
    ->description('Freight charges')
    ->issueDate('2026-01-10')
    ->dueDate('2026-01-25')
    ->customerVat('300111222333003')          // optional
    ->details([
        'bill_of_lading' => 'MAEU123456',
        'shipment_type'  => 1,                  // 1 = import
        'port'           => '10',               // Jeddah Islamic Port
        'doc_ref_no'     => '12345678901234',   // or carrier_manifest + carrier_manifest_date
    ])
    ->addItem(['name' => 'Ocean freight', 'price' => 1500, 'quantity' => 1])
    ->addItem(['name' => 'Handling', 'price' => 200, 'discount_percent' => 10, 'vat_rate' => 15])
    ->send();

$response['fasahpayInvoiceNumber'];
```

Totals, per-line VAT and discounts are calculated for you. Invalid input throws `InvalidInvoiceException` listing **every** problem at once (`$e->errors`).

Item fields: `name`, `price`, `quantity` (default 1), `vat_rate` (`0` or `15`, default 15), `discount` (amount), `discount_percent`, `name_ar`, `code`, `unit`, `unit_ar`.

### Invoice types and required `details()`

| Type | Required details |
|---|---|
| `general` | `company_name`, `consumer_email`, `consumer_mobile` (optional `company_registration_number`) |
| `billoflading` | `bill_of_lading`, `shipment_type`, `port`, and `doc_ref_no` **or** `carrier_manifest` + `carrier_manifest_date` |
| `declaration` | `declaration_number`, `declaration_date`, `declaration_type`, `port` |
| `custombroker` | `customs_broker_license`, `license_type` (1, 2, 5), `port` |
| `importer` | `importer_number`, `port` |
| `shippingagent` | `shipping_agent_number`, `port` |

Aliases such as `'bl'`, `'broker'` and `'shipping_agent'` are accepted wherever a type is expected. Lookup tables (ports, shipment and declaration types) live in `AmrSaleh\FasahPay\Support\LookupValues`.

### Sandbox vs production

```php
FasahPay::sandbox()->invoice('general')...;      // always sandbox
FasahPay::production()->invoice('general')...;   // always production
FasahPay::connection('acme-co')->invoice(...);   // your own connection
FasahPay::invoice(...);                          // FASAHPAY_ENV default
```

Add a custom connection by adding another entry to `connections` in `config/fasahpay.php`. Tokens are cached per connection and client id.

### Using a prebuilt payload

```php
$built = FasahPay::invoice('importer')->internalNumber('1')->details([...])->addItem([...])->build();
// ['type' => InvoiceType, 'payload' => [...], 'grand_total' => 115.0, ...]

FasahPay::createInvoice($built['type'], $built['payload']);
FasahPay::updateInvoice($built['type'], $built['payload']);
```

### Error handling

```php
use AmrSaleh\FasahPay\Exceptions\{RequestException, InvalidInvoiceException, ConfigurationException};

try {
    FasahPay::invoice(...)->send();
} catch (InvalidInvoiceException $e) {
    $e->errors;                 // fix your data
} catch (RequestException $e) {
    $e->httpStatus;
    $e->responseData;
    $e->correlationId();        // quote this to Fasah support
    if ($e->isUncertain()) {
        // 5xx / timeout: Fasah may have created the invoice.
        // Check its status before retrying to avoid duplicates.
    }
} catch (ConfigurationException $e) {
    // missing credentials for the selected connection
}
```

## Webhooks

The package registers (configurable via `callbacks.*`):

```
POST /fasahpay/invoice-notification
POST /fasahpay/settlement-notification
```

Set `FASAHPAY_CALLBACK_SECRET` and Fasah requests must send it as an `X-Callback-Secret` header (or `?secret=`). Register your URLs with Fasah:

```bash
php artisan fasah:subscribe-callbacks --connection=production
```

Listen for events:

```php
use AmrSaleh\FasahPay\Events\InvoiceNotificationReceived;
use AmrSaleh\FasahPay\Events\SettlementNotificationReceived;

Event::listen(InvoiceNotificationReceived::class, function ($event) {
    if ($event->isPaid()) {
        Order::where('invoice_no', $event->internalInvoiceNumber())->update(['paid_at' => now()]);
    }
    // also: status(), isCancelled(), fasahInvoiceNumber(), sadadNumber(), $event->payload
});

Event::listen(SettlementNotificationReceived::class, fn ($e) => /* ... */);
```

Listeners should be idempotent: Fasah may deliver the same notification more than once. Settlement bodies may contain a list; one event is fired per entry. To disable the built-in routes set `FASAHPAY_CALLBACKS_ENABLED=false` and write your own controller. Callback URLs must be public HTTPS and your server IP whitelisted by Fasah.

## Postman collection

Import these from [`docs/postman`](docs/postman):

- `Fasah-Pay.postman_collection.json`: token, all invoice types (create/update), notification subscriptions, and local webhook simulators.
- `Fasah-Sandbox.postman_environment.json` and `Fasah-Production.postman_environment.json`: fill in `client_id`, `client_secret`, `username`, `password`, `biller_vat`.

Select an environment, run **Auth > Get JWT Token** (it stores `{{token}}`), then any invoice request. Successful invoice calls store the FP number in `{{fp_number}}`.

## Going to production checklist

1. Obtain production credentials from Tabadul and set the `FASAHPAY_PRODUCTION_*` variables.
2. Run `php artisan fasah:test-auth --connection=production`.
3. Set a strong `FASAHPAY_CALLBACK_SECRET`.
4. Run `php artisan fasah:subscribe-callbacks --connection=production`.
5. Set `FASAHPAY_ENV=production`.

## Testing your own code

Because the package uses Laravel's HTTP client, you can fake it:

```php
Http::fake([
    '*/token*' => Http::response(['token' => 'jwt']),
    '*/invoices*' => Http::response(['statusCode' => '00000', 'fasahpayInvoiceNumber' => '123']),
]);
```

Run the package tests with `composer test`.

## Disclaimer

Community package, not affiliated with or endorsed by Tabadul or Fasah. Always validate against the official Fasah Pay integration guidelines for your account.

## License

MIT
