<?php

namespace AmrSaleh\FasahPay\Console;

use AmrSaleh\FasahPay\FasahPayManager;
use Illuminate\Console\Command;
use Throwable;

class SubscribeCallbacksCommand extends Command
{
    protected $signature = 'fasah:subscribe-callbacks
        {--connection= : sandbox | production (defaults to config)}
        {--invoice-url= : Override invoice callback URL}
        {--settlement-url= : Override settlement callback URL}';

    protected $description = 'Register invoice + settlement callback URLs with Fasah Pay';

    public function handle(FasahPayManager $fasah): int
    {
        $invoiceUrl = $this->option('invoice-url') ?: config('fasahpay.callbacks.invoice_url');
        $settlementUrl = $this->option('settlement-url') ?: config('fasahpay.callbacks.settlement_url');

        if (! $invoiceUrl || ! $settlementUrl) {
            $this->error('Set FASAHPAY_INVOICE_CALLBACK_URL and FASAHPAY_SETTLEMENT_CALLBACK_URL, or pass the options.');

            return self::FAILURE;
        }

        try {
            $client = $fasah->connection($this->option('connection') ?: null);

            $this->info('Invoice callback: '.json_encode($client->subscribeInvoiceNotifications($invoiceUrl)));
            $this->info('Settlement callback: '.json_encode($client->subscribeSettlementNotifications($settlementUrl)));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
