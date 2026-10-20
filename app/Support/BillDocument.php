<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Everything the bill document needs, in one place, so the bill page, the
 * print page and the PDF all show the same document (the invoice rebuild,
 * applied to bills).
 *
 * A bill prints as the business's own record of what a supplier charged:
 * the sheet that gets filed with the supplier's original and passed round
 * for approval before anyone pays it. It takes its colours, font and logo
 * from the business's invoice template, so everything it prints looks like
 * one business.
 */
class BillDocument
{
    /** The fonts offered before the house font existed. */
    private const OLD_DEFAULT_FONT = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';

    /**
     * The view data for bills/document.blade.php.
     *
     * @param  array<string, mixed>|null  $settings  template settings to use instead of the business's
     * @return array<string, mixed>
     */
    public static function data(Bill $bill, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $bill->tenant ?? Tenant::findOrFail($bill->tenant_id);
        $s = InvoiceDocument::clean(array_merge(
            InvoiceTemplate::getDefaultSettings(),
            $settings ?? InvoiceDocument::templateSettings($tenant)
        ));

        $font = trim((string) $s['font_family']) === self::OLD_DEFAULT_FONT
            ? '"IBM Plex Sans", Segoe UI, Arial, sans-serif'
            : (string) $s['font_family'];

        $paidByMoney = 0.0;
        $credited = 0.0;
        if ($bill->exists) {
            $bill->loadMissing(['payments', 'vendorCreditApplications']);
            $paidByMoney = (float) $bill->payments->sum(fn ($p) => (float) $p->amount + (float) $p->wht_amount);
            $credited = (float) $bill->vendorCreditApplications->sum('amount');
            // Older bills may carry a paid amount without the payment rows
            // behind it; the bill's own figure is the one that counts.
            $paidByMoney = max($paidByMoney, (float) $bill->amount_paid - $credited);
        }

        $currency = $tenant->currency ?? null;

        return [
            'bill' => $bill,
            'tenant' => $tenant,
            's' => $s,
            'font' => $forPdf ? 'DejaVu Sans, sans-serif' : $font,
            'forPdf' => $forPdf,
            'logo' => $s['show_logo'] ? self::logoFor($tenant, $forPdf) : null,
            'initials' => TenantLogo::initials((string) $tenant->name),
            'state' => self::state($bill),
            'paid' => round($paidByMoney, 2),
            'credited' => round($credited, 2),
            'inWords' => AmountInWords::format($bill->total, $currency),
            'currency' => $currency,
        ];
    }

    private static function logoFor(Tenant $tenant, bool $forPdf): ?string
    {
        $uri = $tenant->exists ? TenantLogo::dataUri($tenant) : null;

        // DomPDF cannot always draw SVG; the initials stand in on the PDF.
        return $forPdf && $uri !== null && str_starts_with($uri, 'data:image/svg') ? null : $uri;
    }

    /**
     * Where the bill stands, in words for the document and the page.
     *
     * @return array{key: string, label: string, daysLate: int}
     */
    public static function state(Bill $bill): array
    {
        return DocumentState::of(
            $bill->status,
            (float) $bill->balance_due,
            (float) $bill->total,
            (float) $bill->amount_paid,
            $bill->due_date,
        );
    }

    /** The bill as an A4 PDF, with page numbers. */
    public static function pdf(Bill $bill): string
    {
        $bill->loadMissing(['vendor', 'items.item', 'purchaseOrder']);
        $pdf = Pdf::loadView('bills.pdf', self::data($bill, forPdf: true))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]);

        return $pdf->output();
    }

    /** A file name like "Bill BILL-000123 - Dantata Supplies.pdf". */
    public static function fileName(Bill $bill): string
    {
        $who = preg_replace('/[^\pL\pN \-_.&]/u', '', (string) $bill->vendor?->name);

        return trim('Bill '.$bill->bill_number.($who ? ' - '.$who : '')).'.pdf';
    }
}
