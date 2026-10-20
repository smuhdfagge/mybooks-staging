<?php

namespace App\Support;

use App\Models\Quotation;
use App\Models\Tenant;

/**
 * Everything the quotation document needs, in one place, so the quotation
 * page, the print page and the PDF all show the same document.
 *
 * A quotation is the first paper a customer sees of a business, and it
 * becomes the invoice when they say yes, so it carries the same logo,
 * colours and font as that invoice will.
 */
class QuotationDocument
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function data(Quotation $quotation, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $quotation->tenant ?? Tenant::findOrFail($quotation->tenant_id);

        return DocumentBrand::of($tenant, $forPdf, $settings) + [
            'quotation' => $quotation,
            'state' => self::state($quotation),
        ];
    }

    /**
     * Where the quotation stands, in words. A quotation is not owed, so it
     * has its own states: it is live until it expires, or answered.
     *
     * @return array{key: string, label: string, expired: bool, daysLeft: int|null}
     */
    public static function state(Quotation $quotation): array
    {
        $expiry = $quotation->expiry_date;
        $answered = in_array($quotation->status, ['accepted', 'rejected', 'converted'], true);
        $expired = ! $answered
            && $quotation->status !== 'draft'
            && ($quotation->status === 'expired' || ($expiry && $expiry->lt(now()->startOfDay())));

        $key = match (true) {
            $quotation->status === 'draft' => 'draft',
            $quotation->status === 'converted' => 'converted',
            $quotation->status === 'accepted' => 'accepted',
            $quotation->status === 'rejected' => 'rejected',
            $expired => 'expired',
            default => 'sent',
        };

        $label = [
            'draft' => 'Draft',
            'converted' => 'Invoiced',
            'accepted' => 'Accepted',
            'rejected' => 'Declined',
            'expired' => 'Expired',
            'sent' => 'Awaiting your answer',
        ][$key];

        $daysLeft = $key === 'sent' && $expiry && $expiry->gte(now()->startOfDay())
            ? (int) now()->startOfDay()->diffInDays($expiry)
            : null;

        return ['key' => $key, 'label' => $label, 'expired' => $expired, 'daysLeft' => $daysLeft];
    }

    public static function pdf(Quotation $quotation): string
    {
        $quotation->loadMissing(['customer', 'items.item']);

        return DocumentBrand::pdf('quotations.pdf', self::data($quotation, forPdf: true));
    }

    public static function fileName(Quotation $quotation): string
    {
        return DocumentBrand::fileName('Quotation', $quotation->quotation_number, $quotation->customer?->name);
    }
}
