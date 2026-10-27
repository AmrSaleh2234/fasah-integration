<?php

namespace AmrSaleh\FasahPay\Invoice;

use AmrSaleh\FasahPay\Enums\InvoiceType;
use AmrSaleh\FasahPay\Exceptions\FasahPayException;
use AmrSaleh\FasahPay\Exceptions\InvalidInvoiceException;
use AmrSaleh\FasahPay\FasahPayClient;
use AmrSaleh\FasahPay\Support\LookupValues;
use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

/**
 * Fluent, validated builder for Fasah Pay invoice payloads.
 *
 * Item shape: name, price, quantity?, vat_rate? (0|15, default 15), discount? (amount),
 * discount_percent?, name_ar?, code?, unit?, unit_ar?
 */
class InvoiceBuilder
{
    public const VAT_RATES = [0.0, 15.0];

    private InvoiceType $type = InvoiceType::General;

    private ?string $internalNumber = null;

    private string $description = 'Invoice';

    private ?string $issueDate = null;

    private ?string $dueDate = null;

    private ?string $customerVat = null;

    /** @var list<array<string, mixed>> */
    private array $items = [];

    /** @var array<string, mixed> */
    private array $details = [];

    public function __construct(
        private string $billerVatNumber,
        private string $paymentMethod = 'Sadad',
        private readonly ?FasahPayClient $client = null,
    ) {}

    public static function make(string $billerVatNumber, string $paymentMethod = 'Sadad', ?FasahPayClient $client = null): self
    {
        return new self($billerVatNumber, $paymentMethod, $client);
    }

    public function type(InvoiceType|string $type): self
    {
        $this->type = InvoiceType::resolve($type);

        return $this;
    }

    public function billerVat(string $vatNumber): self
    {
        $this->billerVatNumber = $vatNumber;

        return $this;
    }

    public function paymentMethod(string $method): self
    {
        $this->paymentMethod = $method;

        return $this;
    }

    public function internalNumber(string|int $number): self
    {
        $this->internalNumber = (string) $number;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function issueDate(DateTimeInterface|string $date): self
    {
        $this->issueDate = $this->formatDate($date);

        return $this;
    }

    public function dueDate(DateTimeInterface|string $date): self
    {
        $this->dueDate = $this->formatDate($date);

        return $this;
    }

    public function customerVat(?string $vatNumber): self
    {
        $this->customerVat = $vatNumber;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function addItem(array $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function items(array $items): self
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    /**
     * Type-specific fields (see InvoiceType::requiredDetails()).
     *
     * @param  array<string, mixed>  $details
     */
    public function details(array $details): self
    {
        $this->details = array_merge($this->details, array_filter($details, fn ($v) => $v !== null && $v !== ''));

        return $this;
    }

    /**
     * @return array{type: InvoiceType, payload: array<string, mixed>, internal_invoice_number: string, total_before_vat: float, total_vat: float, grand_total: float}
     *
     * @throws InvalidInvoiceException
     */
    public function build(): array
    {
        $errors = [];

        if (preg_match('/^[0-9]{15}$/D', $this->billerVatNumber) !== 1) {
            $errors[] = 'Biller VAT number must be 15 digits.';
        }
        if ($this->customerVat !== null && $this->customerVat !== '' && preg_match('/^[0-9]{15}$/D', $this->customerVat) !== 1) {
            $errors[] = 'Customer VAT number must be 15 digits when provided.';
        }
        if (! in_array($this->paymentMethod, ['Sadad', 'View'], true)) {
            $errors[] = 'Payment method must be Sadad or View.';
        }
        if ($this->internalNumber === null || $this->internalNumber === '') {
            $errors[] = 'Internal invoice number is required.';
        }
        if ($this->items === []) {
            $errors[] = 'At least one line item is required.';
        }

        $lines = $this->mapItems($errors);
        $details = $this->normalizeDetails($errors);

        $issue = $this->issueDate ?? Carbon::now('Asia/Riyadh')->toDateString();
        $due = $this->dueDate ?? $issue;
        if ($due < $issue) {
            $errors[] = 'Due date cannot be before the issue date.';
        }

        if ($errors !== []) {
            throw new InvalidInvoiceException($errors);
        }

        $before = round(array_sum(array_column($lines, 'lineItemAmountAfterDiscount')), 2);
        $vat = round(array_sum(array_column($lines, 'lineItemTotalVAT')), 2);
        $grand = round($before + $vat, 2);

        $header = [
            'billerVATNumber' => $this->billerVatNumber,
            'internalInvoiceNumber' => (string) $this->internalNumber,
            'invoiceDescription' => $this->description,
            'invoiceIssueDate' => $issue,
            'invoiceDueDate' => $due,
            'paymentMethod' => $this->paymentMethod,
            'totalBeforeVAT' => $before,
            'totalVAT' => $vat,
            'grandTotal' => $grand,
            'item' => $lines,
        ];

        if ($this->customerVat !== null && $this->customerVat !== '') {
            $header['customerVATNumber'] = $this->customerVat;
        }

        return [
            'type' => $this->type,
            'payload' => array_merge($header, $this->typeFields($details)),
            'internal_invoice_number' => (string) $this->internalNumber,
            'total_before_vat' => $before,
            'total_vat' => $vat,
            'grand_total' => $grand,
        ];
    }

    /**
     * Build and create the invoice on Fasah (requires a builder created via `$client->invoice()`).
     *
     * @return array<string, mixed>
     */
    public function send(): array
    {
        if ($this->client === null) {
            throw new FasahPayException('This builder has no client attached. Create it via FasahPay::invoice().');
        }

        $built = $this->build();

        return $this->client->createInvoice($built['type'], $built['payload']);
    }

    /**
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private function mapItems(array &$errors): array
    {
        $lines = [];

        foreach ($this->items as $index => $item) {
            $n = $index + 1;
            $quantity = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $rate = (float) ($item['vat_rate'] ?? 15);
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                $errors[] = "Item #{$n}: name is required.";
            }
            if ($quantity <= 0) {
                $errors[] = "Item #{$n}: quantity must be greater than zero.";
            }
            if ($price < 0) {
                $errors[] = "Item #{$n}: price cannot be negative.";
            }
            if (! in_array($rate, self::VAT_RATES, true)) {
                $errors[] = "Item #{$n}: VAT rate must be 0 or 15.";
            }

            $amount = round($quantity * $price, 2);
            $percent = (float) ($item['discount_percent'] ?? 0);
            $discount = $percent > 0 ? round($amount * $percent / 100, 2) : round((float) ($item['discount'] ?? 0), 2);
            if ($discount > $amount) {
                $errors[] = "Item #{$n}: discount exceeds the line amount.";
            }
            $after = round($amount - $discount, 2);
            $lineVat = round($after * $rate / 100, 2);
            $nameAr = trim((string) ($item['name_ar'] ?? '')) ?: $name;

            $lines[] = [
                'invoiceLineSerialNo' => $n,
                'itemCode' => (string) ($item['code'] ?? $n),
                'itemDescriptionArabic' => $nameAr,
                'itemDescriptionEnglish' => $name,
                'itemPrice' => $price,
                'quantity' => $quantity,
                'lineItemAmount' => $amount,
                'lineItemDiscount' => $percent > 0 ? $percent : ($amount > 0 ? round($discount * 100 / $amount, 8) : 0),
                'lineItemDiscountAmount' => $discount,
                'lineItemAmountAfterDiscount' => $after,
                'lineItemVAT' => $rate,
                'lineItemTotalVAT' => $lineVat,
                'lineItemTotal' => round($after + $lineVat, 2),
                'unitOfMeasureArabic' => (string) ($item['unit_ar'] ?? 'وحدة'),
                'unitOfMeasureEnglish' => (string) ($item['unit'] ?? 'unit'),
            ];
        }

        return $lines;
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function normalizeDetails(array &$errors): array
    {
        $details = $this->details;

        $missing = array_filter($this->type->requiredDetails(), fn (string $key) => ! isset($details[$key]) || $details[$key] === '');
        if ($missing !== []) {
            $errors[] = "Missing required details for {$this->type->value}: ".implode(', ', $missing).'.';

            return $details;
        }

        if (isset($details['port']) && ! ctype_digit((string) $details['port'])) {
            $errors[] = 'Port code must contain digits only.';
        }

        foreach (['carrier_manifest_date', 'declaration_date'] as $field) {
            if (isset($details[$field])) {
                try {
                    $details[$field] = $this->formatDate($details[$field]);
                } catch (Throwable) {
                    $errors[] = "{$field} is not a valid date.";
                }
            }
        }

        if ($this->type === InvoiceType::BillOfLading) {
            $hasRef = ! empty($details['doc_ref_no']);
            $hasManifest = ! empty($details['carrier_manifest']) && ! empty($details['carrier_manifest_date']);
            if (! $hasRef && ! $hasManifest) {
                $errors[] = 'billoflading requires doc_ref_no, or carrier_manifest + carrier_manifest_date.';
            }
            if (! ctype_digit((string) $details['shipment_type']) || ! in_array((int) $details['shipment_type'], LookupValues::CONFIRMED_TYPES, true)) {
                $errors[] = 'shipment_type is not a valid Fasah code.';
            }
        }

        if ($this->type === InvoiceType::Declaration
            && (! ctype_digit((string) $details['declaration_type'])
                || ! in_array((int) $details['declaration_type'], [...LookupValues::CONFIRMED_TYPES, ...LookupValues::DISPUTED_DECLARATION_TYPES], true))) {
            $errors[] = 'declaration_type is not a valid Fasah code.';
        }

        if ($this->type === InvoiceType::CustomBroker && ! in_array((string) $details['license_type'], ['1', '2', '5'], true)) {
            $errors[] = 'license_type must be 1, 2 or 5.';
        }

        if ($this->type === InvoiceType::General) {
            $mobile = LookupValues::normalizeMobile((string) $details['consumer_mobile']);
            if ($mobile === null) {
                $errors[] = 'consumer_mobile must be a Saudi mobile (05XXXXXXXX or +9665XXXXXXXX).';
            } else {
                $details['consumer_mobile'] = $mobile;
            }
            if (filter_var($details['consumer_email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'consumer_email is not a valid email.';
            }
        }

        return $details;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function typeFields(array $d): array
    {
        $bl = (string) ($d['bill_of_lading'] ?? '');

        return match ($this->type) {
            InvoiceType::General => array_filter([
                'companyName' => (string) $d['company_name'],
                'consumerEmail' => (string) $d['consumer_email'],
                'consumerMobile' => (string) $d['consumer_mobile'],
                'companyRegistrationNumber' => isset($d['company_registration_number']) ? (string) $d['company_registration_number'] : null,
            ], fn ($v) => $v !== null),
            InvoiceType::BillOfLading => array_filter([
                'billofLadingNo' => (string) $d['bill_of_lading'],
                'port' => (string) $d['port'],
                'shipmentType' => (int) $d['shipment_type'],
                'docRefNo' => ! empty($d['doc_ref_no']) ? (string) $d['doc_ref_no'] : null,
                'carrierManifest' => empty($d['doc_ref_no']) ? (string) $d['carrier_manifest'] : null,
                'carrierManifestDate' => empty($d['doc_ref_no']) ? $d['carrier_manifest_date'] : null,
                'consumerContactDetails' => $this->consumerContact($d),
            ], fn ($v) => $v !== null && $v !== []),
            InvoiceType::Declaration => [
                'declarationNumber' => (string) $d['declaration_number'],
                'declarationDate' => $d['declaration_date'],
                'declarationType' => (int) $d['declaration_type'],
                'port' => (string) $d['port'],
                'billofLadingNo' => $bl,
            ],
            InvoiceType::CustomBroker => [
                'customsBrokerLicenseNumber' => (string) $d['customs_broker_license'],
                'licenseType' => (string) $d['license_type'],
                'port' => (string) $d['port'],
                'billofLadingNo' => $bl,
            ],
            InvoiceType::Importer => [
                'importerNumber' => (string) $d['importer_number'],
                'port' => (string) $d['port'],
                'billofLadingNo' => $bl,
            ],
            InvoiceType::ShippingAgent => [
                'shippingAgentNumber' => (string) $d['shipping_agent_number'],
                'port' => (string) $d['port'],
                'billofLadingNo' => $bl,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function consumerContact(array $d): array
    {
        if (empty($d['company_name_en']) && empty($d['consumer_email'])) {
            return [];
        }

        return array_filter([
            'companyNameEn' => $d['company_name_en'] ?? null,
            'companyNameAr' => $d['company_name_ar'] ?? null,
            'consumerEmail' => $d['consumer_email'] ?? null,
            'consumerMobile' => $d['consumer_mobile'] ?? null,
            'consumerUserId' => $d['consumer_user_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        $value = (string) $value;

        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $value) === 1) {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        }

        return Carbon::parse($value)->format('Y-m-d');
    }
}
