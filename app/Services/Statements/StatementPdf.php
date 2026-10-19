<?php

namespace App\Services\Statements;

use App\Models\Tenant;
use App\Support\TenantLogo;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $uri = TenantLogo::dataUri($tenant);

        return $uri !== null && str_starts_with($uri, 'data:image/svg') ? null : $uri;
    }
}
