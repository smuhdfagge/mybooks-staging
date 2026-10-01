<?php

namespace App\Http\Controllers;

use App\Services\Payroll\StatutoryScheduleService;
use App\Services\Payroll\StatutorySettings;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Payroll > Statutory remittances: for a pay month, what is owed to each
 * state IRS (PAYE), each PFA (pension), FMBN (NHF), NSITF and ITF, checked
 * against the ledger, with CSV and PDF schedules.
 */
class StatutoryRemittanceController extends Controller
{
    public function __construct(
        protected StatutoryScheduleService $schedules,
        protected ReportExportService $exports,
    ) {}

    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $this->month($request);

        $summaries = collect(StatutoryScheduleService::SCHEDULES)
            ->map(fn ($label, $schedule) => $this->schedules->build($tenantId, $schedule, $month));

        return view('payroll.statutory.index', [
            'month' => $month,
            'summaries' => $summaries,
            'auto' => StatutorySettings::get($tenantId)['auto'],
        ]);
    }

    public function show(Request $request, string $schedule)
    {
        $month = $this->month($request);

        return view('payroll.statutory.show', [
            'month' => $month,
            'data' => $this->build($schedule, $month),
        ]);
    }

    public function export(Request $request, string $schedule)
    {
        $month = $this->month($request);
        $data = $this->build($schedule, $month);
        $title = $data['title'].' '.$month->format('F Y');

        $this->exports->setTitle($title)->setFilters(['Month' => $month->format('F Y')]);

        if ($request->get('format') === 'csv') {
            $headers = array_merge([$data['schedule'] === 'paye' ? 'State' : ($data['schedule'] === 'pension' ? 'PFA' : 'Paid to')], array_values($data['columns']));
            $rows = [];
            foreach ($data['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    $rows[] = array_merge([$group['label']], array_map(fn ($key) => $row[$key] ?? '', array_keys($data['columns'])));
                }
                $rows[] = array_merge([$group['label'].' total'], array_fill(0, count($data['columns']) - 1, ''), [number_format($group['amount'], 2, '.', '')]);
            }
            $blank = array_fill(0, count($data['columns']) - 1, '');
            $rows[] = array_merge(['Schedule total'], $blank, [number_format($data['total'], 2, '.', '')]);
            $rows[] = array_merge(['Ledger: '.$data['ledger']['account_code'].' '.$data['ledger']['account_name'].' posted in month'], $blank, [number_format($data['ledger']['posted'], 2, '.', '')]);
            $rows[] = array_merge(['Difference'], $blank, [number_format($data['ledger']['difference'], 2, '.', '')]);

            return $this->exports->exportToCsv($rows, $headers);
        }

        return $this->exports->setOrientation('landscape')->exportToPdf('payroll.statutory.pdf', ['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function build(string $schedule, Carbon $month): array
    {
        $user = auth()->user();
        // Full TIN, RSA PIN and NHF numbers only for people who run payroll (I7).
        $full = $user->can('record statutory-remittances') || $user->can('edit payroll');

        return $this->schedules->build($user->tenant_id, $schedule, $month, $full);
    }

    /** Pay month from ?month=YYYY-MM; last month by default. */
    private function month(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->input('month').'-01')->startOfDay()
            : now()->subMonthNoOverflow()->startOfMonth();
    }
}
