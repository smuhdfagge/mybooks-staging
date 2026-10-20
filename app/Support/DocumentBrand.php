<?php

namespace App\Support;

use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The look every printed document shares: the business's template
 * settings, its lettering, and its logo. Worked out once here so the
 * invoice, the payment voucher, the bill, the quotation, the credit note
 * and the delivery note all come out of the same business.
 */
final class DocumentBrand
{
    /** The fonts offered for documents before the house font existed. */
    private const OLD_DEFAULT_FONT = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';

    /**
     * @param  array<string, mixed>|null  $settings  settings to use instead of the business's (the template editor preview)
     * @return array{tenant: Tenant, s: array<string, mixed>, font: string, forPdf: bool, logo: string|null, initials: string, currency: string|null}
     */
    public static function of(Tenant $tenant, bool $forPdf = false, ?array $settings = null): array
    {
        $s = InvoiceDocument::clean(array_merge(
            InvoiceTemplate::getDefaultSettings(),
            $settings ?? InvoiceDocument::templateSettings($tenant)
        ));

        // The old default font (Segoe UI) is missing on most phones and on
        // Linux; the app's own lettering stands in for it on screen.
        $font = trim((string) $s['font_family']) === self::OLD_DEFAULT_FONT
            ? '"IBM Plex Sans", Segoe UI, Arial, sans-serif'
            : (string) $s['font_family'];

        return [
            'tenant' => $tenant,
            's' => $s,
            'font' => $forPdf ? 'DejaVu Sans, sans-serif' : $font,
            'forPdf' => $forPdf,
            'logo' => $s['show_logo'] ? self::logo($tenant, $forPdf) : null,
            'initials' => TenantLogo::initials((string) $tenant->name),
            'currency' => $tenant->currency ?? null,
        ];
    }

    private static function logo(Tenant $tenant, bool $forPdf): ?string
    {
        $uri = $tenant->exists ? TenantLogo::dataUri($tenant) : null;

        // DomPDF cannot always draw SVG; the initials stand in on the PDF.
        return $forPdf && $uri !== null && str_starts_with($uri, 'data:image/svg') ? null : $uri;
    }

    /**
     * The document as an A4 PDF, with page numbers in the bottom margin.
     *
     * @param  array<string, mixed>  $data
     */
    public static function pdf(string $view, array $data): string
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]);

        return $pdf->output();
    }

    /** A file name like "Quotation QUO-000123 - Danjuma Foods.pdf". */
    public static function fileName(string $kind, ?string $number, ?string $who): string
    {
        $name = preg_replace('/[^\pL\pN \-_.&]/u', '', (string) $who);

        return trim($kind.' '.$number.($name ? ' - '.$name : '')).'.pdf';
    }
}
