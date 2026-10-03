<?php

namespace App\Services\Statements;

use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Statement as an A4 PDF, with DomPDF like quotations and payslips
 * (session 10). Page numbers go on every page.
 */
class StatementPdf
{
    public function render(Statement $statement): string
    {
        $tenant = Tenant::findOrFail($statement->party->tenant_id);
        $pdf = Pdf::loadView('statements.document', [
            'statement' => $statement, 'tenant' => $tenant, 'forPdf' => true, 'logo' => self::logo($tenant),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true); // only the letters used: a small file to email
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.45, 0.45, 0.45]);

        return $pdf->output();
    }

    /** The business logo as a data URI (works on screen and in DomPDF), or null. */
    public static function logo(Tenant $tenant): ?string
    {
        $path = (string) $tenant->logo;
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
