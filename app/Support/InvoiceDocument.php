<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Everything the invoice document needs, in one place, so the invoice page,
 * the print page, the PDF and the template preview all show the same
 * document (invoice rebuild). The look comes from the business's invoice
 * template (Settings > Invoice templates).
 */
class InvoiceDocument
{
    /** The fonts offered for invoices before the house font existed. */
    private const OLD_DEFAULT_FONT = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';

    /**
     * The view data for invoices/document.blade.php.
     *
     * @param  array<string, mixed>|null  $settings  template settings to use instead of the business's (the template editor preview)
     * @return array<string, mixed>
     */
    public static function data(Invoice $invoice, ?Tenant $tenant = null, bool $forPdf = false, ?array $settings = null): array
    {
        $tenant ??= $invoice->tenant ?? Tenant::findOrFail($invoice->tenant_id);
        $s = self::clean(array_merge(InvoiceTemplate::getDefaultSettings(), $settings ?? self::templateSettings($tenant)));

        // The old default font (Segoe UI) is missing on most phones and on
        // Linux; the app's own lettering stands in for it on screen.
        $font = trim((string) $s['font_family']) === self::OLD_DEFAULT_FONT
            ? '"IBM Plex Sans", Segoe UI, Arial, sans-serif'
            : (string) $s['font_family'];

        $paidByMoney = 0.0;
        $credited = 0.0;
        if ($invoice->exists) {
            $invoice->loadMissing(['payments', 'creditNoteApplications']);
            $paidByMoney = (float) $invoice->payments->sum(fn ($p) => (float) $p->amount + (float) $p->wht_amount);
            $credited = (float) $invoice->creditNoteApplications->sum('amount');
            // Older invoices may carry a paid amount without the payment rows
            // behind it; the invoice's own figure is the one that counts.
            $paidByMoney = max($paidByMoney, (float) $invoice->amount_paid - $credited);
        }

        return [
            'invoice' => $invoice,
            'tenant' => $tenant,
            's' => $s,
            'font' => $forPdf ? 'DejaVu Sans, sans-serif' : $font,
            'forPdf' => $forPdf,
            // The business's own logo (Settings > Company); its initials when it has none.
            'logo' => $s['show_logo'] ? self::logoFor($tenant, $forPdf) : null,
            'initials' => TenantLogo::initials((string) $tenant->name),
            'state' => self::state($invoice),
            'paid' => round($paidByMoney, 2),
            'credited' => round($credited, 2),
            'currency' => $tenant->currency ?? null,
        ];
    }

    /**
     * Template settings go into the document's styles, so only safe values
     * pass: colours as hex, a font list of plain names, known layouts.
     *
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    public static function clean(array $s): array
    {
        $defaults = InvoiceTemplate::getDefaultSettings();
        foreach ($defaults as $key => $default) {
            if (is_string($default) && str_starts_with($default, '#')) { // every colour setting
                $s[$key] = is_string($s[$key] ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $s[$key]) ? $s[$key] : $default;
            }
        }
        $s['font_family'] = is_string($s['font_family'] ?? null) && preg_match('/^[A-Za-z0-9 ,"\'\-]{1,120}$/', $s['font_family']) ? $s['font_family'] : $defaults['font_family'];
        $s['font_size'] = (string) max(10, min(16, (int) ($s['font_size'] ?? 13)));
        $s['border_width'] = (string) max(0, min(8, (int) ($s['border_width'] ?? 3)));
        $s['border_style'] = in_array($s['border_style'] ?? null, ['solid', 'dashed', 'dotted', 'double'], true) ? $s['border_style'] : 'solid';
        $s['layout'] = array_key_exists($s['layout'] ?? '', InvoiceTemplate::getLayoutOptions()) ? $s['layout'] : 'classic';
        foreach (['show_logo', 'show_status_badge', 'show_tax_column', 'show_payment_info', 'show_notes', 'show_terms', 'show_footer'] as $flag) {
            $s[$flag] = filter_var($s[$flag] ?? $defaults[$flag], FILTER_VALIDATE_BOOLEAN);
        }
        $s['footer_text'] = (string) ($s['footer_text'] ?? '');

        return $s;
    }

    private static function logoFor(Tenant $tenant, bool $forPdf): ?string
    {
        $uri = $tenant->exists ? TenantLogo::dataUri($tenant) : null;

        // DomPDF cannot always draw SVG; the initials stand in on the PDF.
        return $forPdf && $uri !== null && str_starts_with($uri, 'data:image/svg') ? null : $uri;
    }

    /** The business's chosen template settings (its own, else its default one, else the built-in look). */
    public static function templateSettings(Tenant $tenant): array
    {
        $template = $tenant->invoiceTemplate;
        if (! $template instanceof InvoiceTemplate) {
            $template = InvoiceTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_default', true)->first();
        }

        return $template instanceof InvoiceTemplate ? ($template->settings ?? []) : [];
    }

    /**
     * Where the invoice stands, in words for the document and the page.
     *
     * @return array{key: string, label: string, daysLate: int}
     */
    public static function state(Invoice $invoice): array
    {
        $balance = round((float) $invoice->balance_due, 2);
        $open = ! in_array($invoice->status, ['draft', 'cancelled', 'paid'], true) && $balance > 0;
        $daysLate = $open && $invoice->due_date->lt(now()->startOfDay())
            ? (int) $invoice->due_date->diffInDays(now()->startOfDay())
            : 0;

        $key = match (true) {
            $invoice->status === 'draft' => 'draft',
            $invoice->status === 'cancelled' => 'cancelled',
            $balance <= 0 && (float) $invoice->total > 0 => 'paid',
            $daysLate > 0 => 'overdue',
            (float) $invoice->amount_paid > 0 => 'partial',
            default => 'open',
        };

        $label = ['draft' => 'Draft', 'cancelled' => 'Cancelled', 'paid' => 'Paid', 'overdue' => 'Overdue', 'partial' => 'Part paid', 'open' => 'Awaiting payment'][$key];

        return ['key' => $key, 'label' => $label, 'daysLate' => $daysLate];
    }

    /** The invoice as an A4 PDF, with page numbers. */
    public static function pdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'items.item']);
        $pdf = Pdf::loadView('invoices.pdf', self::data($invoice, forPdf: true))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]);

        return $pdf->output();
    }

    /** A file name like "Invoice INV-000123 - Danjuma Foods.pdf". */
    public static function fileName(Invoice $invoice): string
    {
        $who = preg_replace('/[^\pL\pN \-_.&]/u', '', (string) $invoice->customer?->name);

        return trim('Invoice '.$invoice->invoice_number.($who ? ' - '.$who : '')).'.pdf';
    }
}
