<?php

namespace AmrSaleh\FasahPay\Tests;

use AmrSaleh\FasahPay\Events\InvoiceNotificationReceived;
use AmrSaleh\FasahPay\Exceptions\ConfigurationException;
use AmrSaleh\FasahPay\Exceptions\RequestException;
use AmrSaleh\FasahPay\Facades\FasahPay;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class ClientTest extends TestCase
{
    public function test_sandbox_and_production_use_their_own_urls_and_credentials(): void
    {
        Http::fake([
            'qa.example.test/token' => Http::response(['token' => 'Bearer qa-jwt']),
            'live.example.test/token' => Http::response(['token' => 'live-jwt']),
            '*/invoices/billoflading' => Http::response(['statusCode' => '00000', 'fasahpayInvoiceNumber' => '99']),
        ]);

        FasahPay::sandbox()->createInvoice('billoflading', ['a' => 1]);
        FasahPay::production()->createInvoice('bl', ['a' => 1]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://qa.example.test/invoices/billoflading'
            && $r->header('Authorization')[0] === 'Bearer qa-jwt'
            && $r->header('X-Tabadul-Client-Id')[0] === 'qa-id');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://live.example.test/invoices/billoflading'
            && $r->header('Authorization')[0] === 'Bearer live-jwt'
            && $r->header('X-Tabadul-Client-Id')[0] === 'live-id');
    }

    public function test_token_is_cached_between_calls(): void
    {
        Http::fake([
            '*/token' => Http::response(['token' => 'jwt']),
            '*/invoices*' => Http::response(['statusCode' => '00000']),
        ]);

        FasahPay::createInvoice('importer', []);
        FasahPay::createInvoice('importer', []);

        Http::assertSentCount(3);
    }

    public function test_retries_once_with_fresh_token_on_401(): void
    {
        Http::fake([
            '*/token' => Http::response(['token' => 'jwt']),
            '*/invoices*' => Http::sequence()->push([], 401)->push(['statusCode' => '00000']),
        ]);

        $this->assertSame('00000', FasahPay::createInvoice('importer', [])['statusCode']);
    }

    public function test_failed_request_throws_with_uncertainty_flag(): void
    {
        Http::fake([
            '*/token' => Http::response(['token' => 'jwt']),
            '*/invoices*' => Http::response(['correlationId' => 'abc'], 503),
        ]);

        try {
            FasahPay::createInvoice('importer', []);
            $this->fail('Expected RequestException');
        } catch (RequestException $e) {
            $this->assertTrue($e->isUncertain());
            $this->assertSame('abc', $e->correlationId());
        }
    }

    public function test_missing_credentials_throw_configuration_exception(): void
    {
        config(['fasahpay.connections.sandbox.client_id' => null]);

        $this->expectException(ConfigurationException::class);
        FasahPay::sandbox()->createInvoice('importer', []);
    }

    public function test_callback_requires_secret_and_fires_event(): void
    {
        Event::fake([InvoiceNotificationReceived::class]);

        $this->postJson('/fasahpay/invoice-notification', ['invoiceStatus' => 'paid'])->assertStatus(401);

        $this->postJson('/fasahpay/invoice-notification', ['invoiceStatus' => 'paid'], ['X-Callback-Secret' => 'shh'])
            ->assertOk()->assertJson(['statusCode' => '00000']);

        Event::assertDispatched(InvoiceNotificationReceived::class, fn ($e) => $e->isPaid());
    }
}
