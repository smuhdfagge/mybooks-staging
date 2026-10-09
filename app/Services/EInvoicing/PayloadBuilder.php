<?php

namespace App\Services\EInvoicing;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\EInvoiceSetting;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Rules\Tin;
use App\Support\Money;

/**
 * Turns an invoice or a credit note into the JSON NRS wants (session 18).
 *
 * The field names follow the shape described by NRS partners (see
 * NrsDriver: UNVERIFIED, check against the NRS Postman collection). The
 * books hold naira; every sum here is done in whole kobo and turned back
 * into naira only when the figure is written out.
 *
 * Stops with a plain-English EInvoiceException when something NRS needs is
 * missing (business TIN, a credit note's original invoice, naira).
 */
class PayloadBuilder
{
    /** B2B when the customer has a TIN, else B2C. */
    public function kind(Invoice|CreditNote $document): string
    {
        return $document->customer?->hasTin() ? 'b2b' : 'b2c';
    }

    /** A B2C document above the threshold must be reported to NRS within 24 hours. */
    public function mustReport(Invoice|CreditNote $document): bool
    {
        return $this->kind($document) === 'b2b'
            || (float) $document->total > (float) config('mybooks.einvoicing.b2c_threshold', 50000);
    }

    /** @return array<string, mixed> */
    public function build(Invoice|CreditNote $document, EInvoiceSetting $settings): array
    {
        $isInvoice = $document instanceof Invoice;
        $tenant = Tenant::query()->findOrFail($document->tenant_id);
        $document->loadMissing(['customer', 'items.item']);
        $customer = $document->customer;

        if (strtoupper((string) $tenant->currency) !== config('mybooks.einvoicing.currency', 'NGN')) {
            throw new EInvoiceException('Only naira documents can be sent to NRS. This business keeps its books in '.($tenant->currency ?: 'another currency').'.');
        }
        if (! Tin::isValid($tenant->tax_number) || trim((string) $tenant->tax_number) === '') {
            throw new EInvoiceException('Add your business TIN in Settings > Company profile before sending documents to NRS.');
        }
        if (! $customer) {
            throw new EInvoiceException('This document has no customer.');
        }
        if (! Tin::isValid($customer->tax_number)) {
            throw new EInvoiceException("The TIN on customer {$customer->name} does not look right. Correct it on the customer's page.");
        }
        if ($document->items->isEmpty()) {
            throw new EInvoiceException('This document has no lines.');
        }

        $date = $isInvoice ? $document->invoice_date : $document->credit_note_date;
        $number = $isInvoice ? $document->invoice_number : $document->credit_note_number;
        $irn = Irn::make((string) $number, (string) $settings->service_id, $date);

        $lines = $isInvoice ? $this->invoiceLines($document) : $this->creditNoteLines($document);
        $totals = $this->totals($document, $lines);

        $payload = [
            'business_id' => (string) $settings->business_id,
            'irn' => $irn,
            'invoice_kind' => strtoupper($this->kind($document)),
            'issue_date' => $date->format('Y-m-d'),
            'issue_time' => ($document->created_at ?? now())->format('H:i:s'),
            'invoice_type_code' => (string) config('mybooks.einvoicing.type_codes.'.($isInvoice ? 'invoice' : 'credit_note')),
            'document_currency_code' => 'NGN',
            'tax_currency_code' => 'NGN',
            'tax_point_date' => $date->format('Y-m-d'),
            'payment_status' => $isInvoice ? $this->paymentStatus($document) : 'PENDING',
            'reference' => (string) ($isInvoice ? ($document->reference ?: $number) : $number),
            'accounting_supplier_party' => $this->party(
                $tenant->name, $tenant->tax_number, $tenant->email, $tenant->phone,
                $tenant->address, $tenant->city, $tenant->state, $tenant->postal_code,
            ),
            'accounting_customer_party' => $this->party(
                $customer->company_name ?: $customer->name, $customer->tax_number, $customer->email, $customer->phone,
                $customer->billing_address, $customer->city, $customer->state, $customer->postal_code,
            ),
            'tax_total' => $totals['tax_total'],
            'legal_monetary_total' => $totals['monetary'],
            'invoice_line' => $lines,
        ];

        if ($isInvoice) {
            $payload['due_date'] = $document->due_date->format('Y-m-d');
        } else {
            $payload['billing_reference'] = [$this->billingReference($document)];
        }
        if (filled($document->notes)) {
            $payload['note'] = mb_substr((string) $document->notes, 0, 500);
        }

        return $payload;
    }

    /** The invoice this credit note corrects, with the IRN NRS gave it. @return array{irn: string, issue_date: string} */
    private function billingReference(CreditNote $note): array
    {
        if (! $note->invoice_id) {
            throw new EInvoiceException('A credit note can only be sent to NRS when it is against an invoice, because NRS needs the original invoice.');
        }
        $invoice = $note->invoice;
        $original = $invoice ? EInvoiceSubmission::forDocument($invoice) : null;
        if (! $original || ! $original->isAccepted() || ! filled($original->irn)) {
            throw new EInvoiceException("Send invoice {$invoice?->invoice_number} to NRS first and wait until it is accepted. A credit note refers to its IRN.");
        }

        return ['irn' => (string) $original->irn, 'issue_date' => $invoice->invoice_date->format('Y-m-d')];
    }

    private function paymentStatus(Invoice $invoice): string
    {
        return match ($invoice->status) {
            'paid' => 'PAID',
            'partial' => 'PARTIAL',
            default => 'PENDING',
        };
    }

    /** @return array<string, mixed> */
    private function party(?string $name, ?string $tin, ?string $email, ?string $phone, ?string $street, ?string $city, ?string $state, ?string $postal): array
    {
        $address = array_filter([
            'street_name' => $street,
            'city_name' => $city,
            'state' => $state,
            'postal_zone' => $postal,
        ], fn ($v) => filled($v));
        $address['country'] = config('mybooks.einvoicing.country', 'NG');

        return array_filter([
            'party_name' => $name,
            'tin' => filled($tin) ? trim((string) $tin) : null,
            'email' => $email,
            'telephone' => $phone,
            'postal_address' => $address,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * One entry per line, with the work done in kobo: net, the line's share
     * of the document discount, VAT, and the VAT category.
     *
     * @return list<array<string, mixed>>
     */
    private function invoiceLines(Invoice $invoice): array
    {
        $discount = Money::toMinor($invoice->discount_amount);
        $nets = $invoice->items->map(fn (InvoiceItem $l) => Money::toMinor($l->total) - Money::toMinor($l->tax_amount))->all();
        $shares = $discount > 0
            ? array_map(fn ($share) => Money::toMinor($share), Money::allocate(Money::fromMinor($discount), $nets))
            : array_fill(0, count($nets), 0);

        $out = [];
        foreach ($invoice->items->values() as $i => $line) {
            $out[] = $this->line($line->description, $line->item?->sku, (float) $line->quantity, Money::toMinor($line->unit_price), $nets[$i], $shares[$i] ?? 0, Money::toMinor($line->tax_amount), (float) $line->tax_rate, $line->vat_treatment, $line->item?->name);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function creditNoteLines(CreditNote $note): array
    {
        $out = [];
        foreach ($note->items->values() as $line) {
            /** @var CreditNoteItem $line */
            $out[] = $this->line($line->description, $line->item?->sku, (float) $line->quantity, Money::toMinor($line->unit_price), Money::toMinor($line->total) - Money::toMinor($line->tax_amount), 0, Money::toMinor($line->tax_amount), (float) $line->tax_rate, $line->vat_treatment, $line->item?->name);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function line(?string $description, ?string $sku, float $quantity, int $unitPriceMinor, int $netMinor, int $shareMinor, int $taxMinor, float $rate, ?string $treatment, ?string $itemName): array
    {
        $categories = config('mybooks.einvoicing.tax_categories');
        $category = $rate > 0 ? $categories['standard'] : ($treatment === 'exempt' || $treatment === 'out_of_scope' ? $categories['exempt'] : $categories['zero']);

        return [
            'invoiced_quantity' => $quantity,
            'line_extension_amount' => Money::fromMinor($netMinor),
            'item' => array_filter([
                'name' => $itemName ?: $description,
                'description' => $description,
                'sellers_item_identification' => $sku,
            ], fn ($v) => filled($v)),
            'price' => [
                'price_amount' => Money::fromMinor($unitPriceMinor),
                'base_quantity' => 1,
                'price_unit' => 'EA',
            ],
            // Not part of NRS's line; used to build the VAT summary, removed afterwards.
            '_taxable_minor' => $netMinor - $shareMinor,
            '_tax_minor' => $taxMinor,
            '_category' => $category,
            '_rate' => $rate,
        ];
    }

    /**
     * The VAT summary and monetary totals; strips the helper fields from the lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{tax_total: array<string, mixed>, monetary: array<string, mixed>}
     */
    private function totals(Invoice|CreditNote $document, array &$lines): array
    {
        $groups = [];
        $netMinor = 0;
        foreach ($lines as $i => $line) {
            $key = $line['_category'].'|'.$line['_rate'];
            $groups[$key] ??= ['category' => $line['_category'], 'rate' => $line['_rate'], 'taxable' => 0, 'tax' => 0];
            $groups[$key]['taxable'] += $line['_taxable_minor'];
            $groups[$key]['tax'] += $line['_tax_minor'];
            $netMinor += Money::toMinor($line['line_extension_amount']);
            unset($lines[$i]['_taxable_minor'], $lines[$i]['_tax_minor'], $lines[$i]['_category'], $lines[$i]['_rate']);
        }

        $taxMinor = array_sum(array_column($groups, 'tax'));
        $discountMinor = $document instanceof Invoice ? Money::toMinor($document->discount_amount) : 0;
        $exclusive = $netMinor - $discountMinor;

        $monetary = [
            'line_extension_amount' => Money::fromMinor($netMinor),
            'tax_exclusive_amount' => Money::fromMinor($exclusive),
            'tax_inclusive_amount' => Money::fromMinor($exclusive + $taxMinor),
            'payable_amount' => Money::fromMinor($exclusive + $taxMinor),
        ];
        if ($discountMinor > 0) {
            $monetary = ['allowance_total_amount' => Money::fromMinor($discountMinor)] + $monetary;
        }

        return [
            'tax_total' => [
                'tax_amount' => Money::fromMinor($taxMinor),
                'tax_subtotal' => array_values(array_map(fn ($g) => [
                    'taxable_amount' => Money::fromMinor($g['taxable']),
                    'tax_amount' => Money::fromMinor($g['tax']),
                    'tax_category' => ['id' => $g['category'], 'percent' => $g['rate']],
                ], $groups)),
            ],
            'monetary' => $monetary,
        ];
    }
}
