<?php

namespace AmrSaleh\FasahPay\Tests;

use AmrSaleh\FasahPay\Enums\InvoiceType;
use AmrSaleh\FasahPay\Exceptions\InvalidInvoiceException;
use AmrSaleh\FasahPay\Invoice\InvoiceBuilder;

class InvoiceBuilderTest extends TestCase
{
    private function builder(): InvoiceBuilder
    {
        return InvoiceBuilder::make('300000000000003')
            ->internalNumber('INV-1')
            ->issueDate('2026-01-10')
            ->addItem(['name' => 'Freight', 'price' => 100, 'quantity' => 2, 'discount' => 20]);
    }

    public function test_builds_bill_of_lading_payload_with_totals(): void
    {
        $built = $this->builder()
            ->type('bl')
            ->details(['bill_of_lading' => 'BL123', 'shipment_type' => 1, 'port' => '10', 'doc_ref_no' => '12345678901234'])
            ->build();

        $this->assertSame(InvoiceType::BillOfLading, $built['type']);
        $this->assertSame(180.0, $built['total_before_vat']);
        $this->assertSame(27.0, $built['total_vat']);
        $this->assertSame(207.0, $built['grand_total']);
        $this->assertSame('BL123', $built['payload']['billofLadingNo']);
        $this->assertSame('12345678901234', $built['payload']['docRefNo']);
        $this->assertArrayNotHasKey('carrierManifest', $built['payload']);
    }

    public function test_general_invoice_normalizes_saudi_mobile(): void
    {
        $built = $this->builder()->type(InvoiceType::General)->details([
            'company_name' => 'Acme', 'consumer_email' => 'a@b.sa', 'consumer_mobile' => '+966 50 123 4567',
        ])->build();

        $this->assertSame('0501234567', $built['payload']['consumerMobile']);
    }

    public function test_collects_all_validation_errors(): void
    {
        try {
            InvoiceBuilder::make('123')->type(InvoiceType::Declaration)->addItem(['name' => '', 'price' => 1, 'vat_rate' => 5])->build();
            $this->fail('Expected InvalidInvoiceException');
        } catch (InvalidInvoiceException $e) {
            $this->assertGreaterThanOrEqual(4, count($e->errors));
        }
    }

    public function test_zero_rated_items_have_no_vat(): void
    {
        $built = InvoiceBuilder::make('300000000000003')->internalNumber('X')
            ->type(InvoiceType::Importer)->details(['importer_number' => '7001', 'port' => '10'])
            ->addItem(['name' => 'Export svc', 'price' => 50, 'vat_rate' => 0])->build();

        $this->assertSame(0.0, $built['total_vat']);
        $this->assertSame(50.0, $built['grand_total']);
    }
}
