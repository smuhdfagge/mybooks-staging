<?php

namespace App\Support;

use App\Models\CreditNote;
use App\Models\Tenant;

/**
 * Everything the credit note document needs, in one place, so the credit
 * note page, the print page and the PDF all show the same document.
 *
 * A credit note goes to the customer: it says what is being taken back off
 * an invoice and how much of that credit is still theirs to use.
 */
class CreditNoteDocument
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function data(CreditNote $note, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $note->tenant ?? Tenant::findOrFail($note->tenant_id);
        $brand = DocumentBrand::of($tenant, $forPdf, $settings);

        return $brand + [
            'note' => $note,
            'state' => self::state($note),
            'used' => round((float) $note->total - (float) $note->balance, 2),
            'inWords' => AmountInWords::format($note->total, $brand['currency']),
        ];
    }

    /**
     * Where the credit note stands, in words.
     *
     * @return array{key: string, label: string}
     */
    public static function state(CreditNote $note): array
    {
        $key = match ($note->status) {
            CreditNote::STATUS_VOID => 'void',
            CreditNote::STATUS_CLOSED => 'closed',
            CreditNote::STATUS_OPEN => 'open',
            default => 'draft',
        };

        $label = [
            'void' => 'Void',
            'closed' => 'Used up',
            'open' => 'Credit available',
            'draft' => 'Draft',
        ][$key];

        return ['key' => $key, 'label' => $label];
    }

    public static function pdf(CreditNote $note): string
    {
        $note->loadMissing(['customer', 'items.item', 'invoice']);

        return DocumentBrand::pdf('credit-notes.pdf', self::data($note, forPdf: true));
    }

    public static function fileName(CreditNote $note): string
    {
        return DocumentBrand::fileName('Credit note', $note->credit_note_number, $note->customer?->name);
    }
}
