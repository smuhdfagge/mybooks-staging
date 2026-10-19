<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Everything the payment voucher needs, in one place, so the expense page,
 * the print page and the PDF all show the same document (the invoice
 * rebuild, applied to expenses).
 *
 * An expense prints as a payment voucher: the document a business files
 * and signs for money going out, the counterpart of the invoice for money
 * coming in. It takes its colours, font and logo from the business's
 * invoice template, so everything it prints looks like one business.
 */
class ExpenseDocument
{
    /** The fonts offered before the house font existed. */
    private const OLD_DEFAULT_FONT = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';

    /**
     * The view data for expenses/document.blade.php.
     *
     * @param  array<string, mixed>|null  $settings  template settings to use instead of the business's
     * @return array<string, mixed>
     */
    public static function data(Expense $expense, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $expense->tenant ?? Tenant::findOrFail($expense->tenant_id);
        $s = InvoiceDocument::clean(array_merge(
            InvoiceTemplate::getDefaultSettings(),
            $settings ?? InvoiceDocument::templateSettings($tenant)
        ));

        $font = trim((string) $s['font_family']) === self::OLD_DEFAULT_FONT
            ? '"IBM Plex Sans", Segoe UI, Arial, sans-serif'
            : (string) $s['font_family'];

        $currency = $tenant->currency ?? null;

        return [
            'expense' => $expense,
            'tenant' => $tenant,
            's' => $s,
            'font' => $forPdf ? 'DejaVu Sans, sans-serif' : $font,
            'forPdf' => $forPdf,
            'logo' => $s['show_logo'] ? self::logoFor($tenant, $forPdf) : null,
            'initials' => TenantLogo::initials((string) $tenant->name),
            'state' => self::state($expense),
            'inWords' => AmountInWords::format($expense->total, $currency),
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
     * Where the expense stands, in words for the document and the page.
     *
     * @return array{key: string, label: string, tone: string}
     */
    public static function state(Expense $expense): array
    {
        $key = match ($expense->status) {
            Expense::STATUS_PAID => 'paid',
            Expense::STATUS_APPROVED => 'approved',
            Expense::STATUS_REJECTED => 'rejected',
            Expense::STATUS_PENDING_APPROVAL => 'pending',
            default => 'draft',
        };

        $label = [
            'paid' => 'Paid',
            'approved' => 'Approved, awaiting payment',
            'rejected' => 'Rejected',
            'pending' => 'Awaiting approval',
            'draft' => 'Draft',
        ][$key];

        // The badge colours the app already uses for these states.
        $tone = ['paid' => 'paid', 'approved' => 'partial', 'rejected' => 'cancelled', 'pending' => 'unpaid', 'draft' => 'draft'][$key];

        return ['key' => $key, 'label' => $label, 'tone' => $tone];
    }

    /** The voucher as an A4 PDF, with page numbers. */
    public static function pdf(Expense $expense): string
    {
        $expense->loadMissing(['vendor', 'expenseAccount', 'paidThroughAccount', 'createdBy', 'approvedByUser']);
        $pdf = Pdf::loadView('expenses.pdf', self::data($expense, forPdf: true))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]);

        return $pdf->output();
    }

    /** A file name like "Payment voucher EXP-000123 - Dangote Cement.pdf". */
    public static function fileName(Expense $expense): string
    {
        $who = preg_replace('/[^\pL\pN \-_.&]/u', '', (string) $expense->vendor?->name);

        return trim('Payment voucher '.$expense->expense_number.($who ? ' - '.$who : '')).'.pdf';
    }
}
