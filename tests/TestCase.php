<?php

namespace AmrSaleh\FasahPay\Tests;

use AmrSaleh\FasahPay\FasahPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FasahPayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('fasahpay.default', 'sandbox');
        $app['config']->set('fasahpay.callbacks.secret', 'shh');

        foreach (['sandbox' => 'qa', 'production' => 'live'] as $name => $tag) {
            $app['config']->set("fasahpay.connections.{$name}", [
                'token_url' => "https://{$tag}.example.test/token",
                'invoice_base_url' => "https://{$tag}.example.test/invoices",
                'client_id' => "{$tag}-id",
                'client_secret' => "{$tag}-secret",
                'username' => 'user',
                'password' => 'pass',
                'biller_vat_number' => '300000000000003',
            ]);
        }
    }
}
