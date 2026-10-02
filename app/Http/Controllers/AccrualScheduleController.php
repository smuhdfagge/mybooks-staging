<?php

namespace App\Http\Controllers;

use App\Actions\Accruals\CancelAccrualSchedule;
use App\Actions\Accruals\CreateAccrualSchedule;
use App\Actions\Accruals\ReleaseAccrualSchedule;
use App\Models\AccrualSchedule;
use App\Models\ChartOfAccount;
use App\Services\AccountCodeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Prepaid expenses and income received in advance, released one month at
 * a time (App\Actions\Accruals, command accruals:release).
 */
class AccrualScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = AccrualSchedule::with(['plAccount', 'balanceAccount'])
            ->when($request->get('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('start_date')->latest('id')
            ->paginate(25)->withQueryString();

        return view('accrual-schedules.index', compact('schedules'));
    }

    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'name', 'type', 'sub_type']);
        $type = $request->get('type') === AccrualSchedule::TYPE_DEFERRED ? AccrualSchedule::TYPE_DEFERRED : AccrualSchedule::TYPE_PREPAID;
        $code = fn (string $key) => $accounts->firstWhere('account_code', AccountCodeService::resolve($tenantId, $key))?->id;
        $defaults = [
            'prepaid_balance' => $code('prepaid_expenses'),
            'deferred_balance' => $code('deferred_revenue'),
            'cash' => $code('checking'),
        ];

        return view('accrual-schedules.create', compact('accounts', 'type', 'defaults'));
    }

    public function store(Request $request, CreateAccrualSchedule $create)
    {
        $tenantId = auth()->user()->tenant_id;
        $account = Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(AccrualSchedule::TYPES))],
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'recorded_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'balance_account_id' => ['required', $account],
            'pl_account_id' => ['required', $account],
            'funding' => ['required', Rule::in(array_keys(AccrualSchedule::FUNDING))],
            'funding_account_id' => ['nullable', 'required_if:funding,bank', $account],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'funding_account_id.required_if' => 'Choose the bank or cash account the money went out of or came into.',
        ]);

        $schedule = $create->handle($tenantId, $validated, auth()->id());

        return redirect()->route('accrual-schedules.show', $schedule)->with('success', 'Schedule saved. Each month is released automatically at the end of the month.');
    }

    public function show(AccrualSchedule $accrualSchedule)
    {
        $accrualSchedule->load(['plAccount', 'balanceAccount', 'fundingAccount', 'releases.journal', 'createdBy']);

        return view('accrual-schedules.show', ['schedule' => $accrualSchedule]);
    }

    public function release(AccrualSchedule $accrualSchedule, ReleaseAccrualSchedule $release)
    {
        $count = $release->handle($accrualSchedule);

        return back()->with('success', $count ? "{$count} month(s) released." : 'Nothing is due yet.');
    }

    public function cancel(AccrualSchedule $accrualSchedule, CancelAccrualSchedule $cancel)
    {
        $cancel->handle($accrualSchedule);

        return back()->with('success', 'Schedule stopped. What is left stays in '.$accrualSchedule->balanceAccount->name.'.');
    }
}
