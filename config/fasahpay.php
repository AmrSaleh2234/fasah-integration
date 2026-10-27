<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    | "sandbox" for testing, "production" for live. You can also define your
    | own connections below (e.g. a second company/VAT number).
    */
    'default' => env('FASAHPAY_ENV', 'sandbox'),

    'timeout' => (int) env('FASAHPAY_TIMEOUT', 45),

    /*
    | Cache store used for the JWT (null = default cache store).
    | The token is cached per connection + client id.
    */
    'cache' => [
        'store' => env('FASAHPAY_CACHE_STORE'),
        'token_ttl' => (int) env('FASAHPAY_TOKEN_CACHE_TTL', 3000),
    ],

    'connections' => [
        'sandbox' => [
            'token_url' => env('FASAHPAY_SANDBOX_TOKEN_URL', 'https://qapigw.tabadul.sa/tabadul/qa/oauth2/token/v1/jwt'),
            'invoice_base_url' => env('FASAHPAY_SANDBOX_INVOICE_BASE_URL', 'https://qapigw.tabadul.sa/tabadul/qa/api/v1.1/fasahpay/invoices'),
            'client_id' => env('FASAHPAY_SANDBOX_CLIENT_ID'),
            'client_secret' => env('FASAHPAY_SANDBOX_CLIENT_SECRET'),
            'username' => env('FASAHPAY_SANDBOX_USERNAME'),
            'password' => env('FASAHPAY_SANDBOX_PASSWORD'),
            'biller_vat_number' => env('FASAHPAY_SANDBOX_BILLER_VAT_NUMBER', env('FASAHPAY_BILLER_VAT_NUMBER')),
        ],

        'production' => [
            'token_url' => env('FASAHPAY_PRODUCTION_TOKEN_URL', 'https://apigw.tabadul.sa/tabadul/public/oauth2/token/v1/jwt'),
            'invoice_base_url' => env('FASAHPAY_PRODUCTION_INVOICE_BASE_URL', 'https://apigw.tabadul.sa/tabadul/public/api/v1.1/fasahpay/invoices'),
            'client_id' => env('FASAHPAY_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('FASAHPAY_PRODUCTION_CLIENT_SECRET'),
            'username' => env('FASAHPAY_PRODUCTION_USERNAME'),
            'password' => env('FASAHPAY_PRODUCTION_PASSWORD'),
            'biller_vat_number' => env('FASAHPAY_PRODUCTION_BILLER_VAT_NUMBER', env('FASAHPAY_BILLER_VAT_NUMBER')),
        ],
    ],

    /*
    | Sadad = real bills | View = demo only (no payment).
    */
    'payment_method' => env('FASAHPAY_PAYMENT_METHOD', 'Sadad'),

    /*
    | Provider type sent when subscribing to notifications.
    */
    'provider_type' => env('FASAHPAY_PROVIDER_TYPE', 'GEN'),

    /*
    | Callback (webhook) endpoints. When enabled the package registers:
    |   POST {prefix}/invoice-notification
    |   POST {prefix}/settlement-notification
    | and fires the InvoiceNotificationReceived / SettlementNotificationReceived events.
    | Fasah must reach these over public HTTPS (and your IP must be whitelisted).
    */
    'callbacks' => [
        'enabled' => (bool) env('FASAHPAY_CALLBACKS_ENABLED', true),
        'prefix' => env('FASAHPAY_CALLBACKS_PREFIX', 'fasahpay'),
        'middleware' => ['api'],
        // Shared secret checked via "X-Callback-Secret" header or ?secret= query. Strongly recommended in production.
        'secret' => env('FASAHPAY_CALLBACK_SECRET'),
        'invoice_url' => env('FASAHPAY_INVOICE_CALLBACK_URL'),
        'settlement_url' => env('FASAHPAY_SETTLEMENT_CALLBACK_URL'),
    ],
];
