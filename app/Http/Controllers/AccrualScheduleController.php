<?php

namespace App\Http\Controllers;

use App\Actions\AccrualSchedules\CancelAccrualSchedule;
use App\Actions\AccrualSchedules\DeleteAccrualSchedule;
use App\Actions\AccrualSchedules\ReleaseAccrualSchedule;
use App\Actions\AccrualSchedules\SaveAccrualSchedule;
use App\Http\Requests\SaveAccrualScheduleRequest;
use App\Models\AccrualSchedule;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\AccountCodeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Prepaid expenses and deferred revenue, released one month at a time
 * (S9). The rules live in App\Actions\AccrualSchedules; the daily command
 * is accruals:release.
 */
class AccrualScheduleController extends Controller
{
    public function index()
    {
        return view('accrual-schedules.index');
    }

    /** New schedule. ?source=bill:12 (or expense:/invoice:) fills it in from that document. */
    public function create(Request $request)
    {
        $schedule = new AccrualSchedule([
            'type' => $request->get('type') === AccrualSchedule::TYPE_DEFERRED ? AccrualSchedule::TYPE_DEFERRED : AccrualSchedule::TYPE_PREPAID,
            'start_date' => today()->startOfMonth(),
            'months' => 12,
        ]);
        if ($source = $this->sourceFromQuery((string) $request->get('source'))) {
            $schedule->fill([
                'type' => $source[0] === 'invoice' ? AccrualSchedule::TYPE_DEFERRED : AccrualSchedule::TYPE_PREPAID,
                'source_type' => $source[0],
                'source_id' => $source[1]->id,
                'total_amount' => $source[1]->total,
                'reference' => $source[1]->{AccrualSchedule::SOURCES[$source[0]][1]},
            ]);
        }

        return view('accrual-schedules.form', $this->formData($schedule));
    }

    public function store(SaveAccrualScheduleRequest $request, SaveAccrualSchedule $save)
    {
        $schedule = $save->handle(auth()->user()->tenant_id, $request->validated(), null, auth()->id());

        return redirect()->route('accrual-schedules.show', $schedule)->with('success', $this->savedMessage($schedule));
    }

    public function show(AccrualSchedule $accrualSchedule)
    {
        $accrualSchedule->load(['plAccount', 'balanceAccount', 'releases.journal', 'createdBy']);

        return view('accrual-schedules.show', [
            'schedule' => $accrualSchedule,
            'plan' => $accrualSchedule->plan(),
            'source' => $accrualSchedule->sourceDocument(),
        ]);
    }

    public function edit(AccrualSchedule $accrualSchedule)
    {
        if (! $accrualSchedule->isActive() || $accrualSchedule->hasReleases()) {
            return redirect()->route('accrual-schedules.show', $accrualSchedule)
                ->with('error', 'Months have already been released from this schedule, so it can\'t be changed. Cancel it and set up a new one.');
        }

        return view('accrual-schedules.form', $this->formData($accrualSchedule));
    }

    public function update(SaveAccrualScheduleRequest $request, AccrualSchedule $accrualSchedule, SaveAccrualSchedule $save)
    {
        try {
            $schedule = $save->handle(auth()->user()->tenant_id, $request->validated(), $accrualSchedule, auth()->id());
        } catch (ValidationException $e) {
            if (isset($e->errors()['schedule'])) {
                return redirect()->route('accrual-schedules.show', $accrualSchedule)->with('error', $e->errors()['schedule'][0]);
            }
            throw $e;
        }

        return redirect()->route('accrual-schedules.show', $schedule)->with('success', $this->savedMessage($schedule));
    }

    /** "Release due months now": the same as the daily run, for this schedule. */
    public function release(AccrualSchedule $accrualSchedule, ReleaseAccrualSchedule $release)
    {
        try {
            $count = $release->handle($accrualSchedule);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $count ? "{$count} month(s) released." : 'Nothing is due yet: each month is released on its last day.');
    }

    public function cancel(AccrualSchedule $accrualSchedule, CancelAccrualSchedule $cancel)
    {
        try {
            $schedule = $cancel->handle($accrualSchedule);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        $schedule->load('balanceAccount');

        return back()->with('success', 'Schedule cancelled. No more months will be released. '
            .Money::format($schedule->remaining())." stays in {$schedule->balanceAccount->name}; move it with a manual journal if needed.");
    }

    public function destroy(AccrualSchedule $accrualSchedule, DeleteAccrualSchedule $delete)
    {
        try {
            $delete->handle($accrualSchedule);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('accrual-schedules.index')->with('success', "Schedule {$accrualSchedule->schedule_number} deleted.");
    }

    protected function savedMessage(AccrualSchedule $schedule): string
    {
        $released = $schedule->releases()->count();
        $message = "Schedule {$schedule->schedule_number} saved. Each month is released on its last day.";
        if ($released) {
            $message .= " {$released} month(s) already past were released straight away.";
        }

        return $message;
    }

    /** @return array{0: string, 1: Bill|Expense|Invoice}|null */
    protected function sourceFromQuery(string $value): ?array
    {
        [$type, $id] = str_contains($value, ':') ? explode(':', $value, 2) : [null, null];
        $model = AccrualSchedule::SOURCES[$type][0] ?? null;
        $doc = $model ? $model::find((int) $id) : null;

        return $doc ? [$type, $doc] : null;
    }

    /** @return array<string, mixed> */
    protected function formData(AccrualSchedule $schedule): array
    {
        $tenantId = auth()->user()->tenant_id;
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'expense', 'income'])
            ->whereNotIn('sub_type', ['cash', 'bank'])
            ->orderBy('account_code')->get(['id', 'account_code', 'name', 'type']);
        $label = fn ($a) => "{$a->account_code} - {$a->name}";
        $options = fn (string $type) => $accounts->where('type', $type)->mapWithKeys(fn ($a) => [(string) $a->id => $label($a)])->all();
        $default = fn (string $key) => $accounts->firstWhere('account_code', AccountCodeService::resolve($tenantId, $key))?->id;

        // Recent documents to link to (the 200 latest of each).
        $doc = fn (string $type, $rows) => $rows->mapWithKeys(fn ($d) => [
            "{$type}:{$d->id}" => $d->{AccrualSchedule::SOURCES[$type][1]}.' · '.($d->vendor->name ?? $d->customer->name ?? '').' · '.Money::format($d->total),
        ])->all();
        $sources = [
            AccrualSchedule::TYPE_PREPAID => $doc('bill', Bill::with('vendor')->latest('id')->limit(200)->get())
                + $doc('expense', Expense::with('vendor')->latest('id')->limit(200)->get()),
            AccrualSchedule::TYPE_DEFERRED => $doc('invoice', Invoice::with('customer')->latest('id')->limit(200)->get()),
        ];

        return [
            'schedule' => $schedule,
            'accountOptions' => [
                AccrualSchedule::TYPE_PREPAID => ['balance' => $options('asset'), 'pl' => $options('expense')],
                AccrualSchedule::TYPE_DEFERRED => ['balance' => $options('liability'), 'pl' => $options('income')],
            ],
            'defaults' => [
                AccrualSchedule::TYPE_PREPAID => $default('prepaid_expenses'),
                AccrualSchedule::TYPE_DEFERRED => $default('deferred_revenue'),
            ],
            'sources' => $sources,
        ];
    }
}
