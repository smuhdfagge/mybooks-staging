<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDepreciation;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use App\Services\DepreciationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class FixedAssetController extends Controller
{
    protected DepreciationService $depreciationService;

    public function __construct(DepreciationService $depreciationService)
    {
        $this->depreciationService = $depreciationService;
    }

    public function index()
    {
        return view('fixed-assets.index');
    }

    public function create()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $categories = FixedAssetCategory::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $assetAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->get();

        $expenseAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->orderBy('account_code')
            ->get();

        $depreciationMethods = FixedAsset::getDepreciationMethods();

        return view('fixed-assets.create', compact(
            'categories', 'vendors', 'assetAccounts', 'expenseAccounts', 'depreciationMethods'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'category_id' => ['nullable', Rule::exists('fixed_asset_categories', 'id')->where('tenant_id', $tenantId)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'serial_number' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'purchase_invoice' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'in_service_date' => 'required|date|after_or_equal:purchase_date',
            'purchase_cost' => 'required|numeric|min:0',
            'salvage_value' => 'required|numeric|min:0|lt:purchase_cost',
            'useful_life' => 'required|numeric|min:0.5|max:50',
            'depreciation_method' => 'required|in:straight_line,declining_balance,double_declining,sum_of_years',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        // If category is selected, use its default values if not specified
        if ($request->category_id) {
            $category = FixedAssetCategory::find($request->category_id);
            if ($category) {
                if (!isset($validated['depreciation_method']) && $category->default_depreciation_method) {
                    $validated['depreciation_method'] = $category->default_depreciation_method;
                }
                if (!isset($validated['useful_life']) && $category->default_useful_life) {
                    $validated['useful_life'] = $category->default_useful_life;
                }
            }
        }

        $asset = FixedAsset::create([
            'tenant_id' => $tenantId,
            'asset_number' => FixedAsset::generateNumber($tenantId),
            'created_by' => auth()->id(),
            ...$validated,
        ]);

        // Create initial journal entry for asset acquisition
        $this->createAcquisitionJournal($asset);

        return redirect()->route('fixed-assets.show', $asset)
            ->with('success', 'Fixed asset created successfully.');
    }

    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load(['category', 'vendor', 'assignedUser', 'depreciations' => function ($query) {
            $query->orderBy('period_number', 'desc');
        }]);

        $schedule = $fixedAsset->generateDepreciationSchedule();

        return view('fixed-assets.show', compact('fixedAsset', 'schedule'));
    }

    public function edit(FixedAsset $fixedAsset)
    {
        $categories = FixedAssetCategory::all();
        
        return view('fixed-assets.edit', [
            'asset' => $fixedAsset,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, FixedAsset $fixedAsset)
    {
        // Don't allow editing certain fields if depreciation has started
        $hasDepreciation = $fixedAsset->depreciations()->exists();

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'serial_number' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,under_maintenance,idle,disposed',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', auth()->user()->tenant_id)],
        ];

        if (!$hasDepreciation) {
            $rules = array_merge($rules, [
                'category_id' => ['nullable', Rule::exists('fixed_asset_categories', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            ]);
        }

        $validated = $request->validate($rules);

        $fixedAsset->update($validated);

        return redirect()->route('fixed-assets.show', $fixedAsset)
            ->with('success', 'Fixed asset updated successfully.');
    }

    public function destroy(FixedAsset $fixedAsset)
    {
        if ($fixedAsset->depreciations()->where('status', 'posted')->exists()) {
            return back()->with('error', 'Cannot delete asset with posted depreciation. Please dispose the asset instead.');
        }

        $fixedAsset->delete();

        return redirect()->route('fixed-assets.index')
            ->with('success', 'Fixed asset deleted successfully.');
    }

    /**
     * Record depreciation for an asset
     */
    public function depreciate(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'depreciation_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $depreciation = $this->depreciationService->recordDepreciation(
                $fixedAsset,
                Carbon::parse($validated['depreciation_date']),
                $validated['notes'] ?? null
            );

            return back()->with('success', 'Depreciation recorded successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Run depreciation for all eligible assets
     */
    public function runDepreciation(Request $request)
    {
        $validated = $request->validate([
            'depreciation_date' => 'required|date',
        ]);

        $results = $this->depreciationService->runMonthlyDepreciation(
            auth()->user()->tenant_id,
            Carbon::parse($validated['depreciation_date'])
        );

        $message = "Depreciation run completed. Processed: {$results['processed']}, Skipped: {$results['skipped']}";
        
        if (!empty($results['errors'])) {
            $message .= ". Errors: " . count($results['errors']);
        }

        return back()->with('success', $message);
    }

    /**
     * Dispose an asset
     */
    public function dispose(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'disposal_method' => 'required|in:sale,scrapped,donated,lost,other',
            'disposal_amount' => 'nullable|numeric|min:0',
            'disposal_date' => 'required|date',
            'disposal_notes' => 'nullable|string',
        ]);

        try {
            $this->depreciationService->disposeAsset(
                $fixedAsset,
                $validated['disposal_method'],
                $validated['disposal_amount'] ?? 0,
                Carbon::parse($validated['disposal_date']),
                $validated['disposal_notes'] ?? null
            );

            return redirect()->route('fixed-assets.show', $fixedAsset)
                ->with('success', 'Asset disposed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse depreciation
     */
    public function reverseDepreciation(FixedAssetDepreciation $depreciation)
    {
        try {
            $this->depreciationService->reverseDepreciation($depreciation);

            return back()->with('success', 'Depreciation reversed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * View depreciation schedule for a single asset
     */
    public function schedule(FixedAsset $fixedAsset)
    {
        $schedule = $fixedAsset->generateDepreciationSchedule();
        $depreciations = $fixedAsset->depreciations()->orderBy('period_number')->get();

        return view('fixed-assets.asset-schedule', compact('fixedAsset', 'schedule', 'depreciations'));
    }

    /**
     * View depreciation schedule for all assets
     */
    public function depreciationSchedule(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $categoryId = $request->get('category_id');
        $status = $request->get('status');
        $year = $request->get('year', date('Y'));

        $query = FixedAsset::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['disposed', 'sold'])
            ->with(['category', 'depreciations']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_number')->get();

        // Generate schedule for each asset
        $scheduleData = [];
        foreach ($assets as $asset) {
            $assetSchedule = $asset->generateDepreciationSchedule();
            // Filter by year
            $yearSchedule = collect($assetSchedule)->filter(function ($item) use ($year) {
                return date('Y', strtotime($item['date'])) == $year;
            })->values();
            
            if ($yearSchedule->count() > 0) {
                $scheduleData[] = [
                    'asset' => $asset,
                    'schedule' => $yearSchedule,
                    'year_depreciation' => $yearSchedule->sum('depreciation'),
                ];
            }
        }

        $categories = FixedAssetCategory::where('tenant_id', $tenantId)->orderBy('name')->get();

        $totalYearDepreciation = collect($scheduleData)->sum('year_depreciation');

        return view('fixed-assets.schedule', compact('scheduleData', 'categories', 'year', 'totalYearDepreciation'));
    }

    /**
     * Asset register report
     */
    public function register(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $categoryId = $request->get('category_id');
        $status = $request->get('status');

        $query = FixedAsset::where('tenant_id', $tenantId)
            ->where('purchase_date', '<=', $asOf)
            ->with(['category', 'vendor']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_number')->get();

        $categories = FixedAssetCategory::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $totalAssets = $assets->count();
        $totalCost = $assets->sum('purchase_cost');
        $totalDepreciation = $assets->sum('accumulated_depreciation');
        $totalBookValue = $assets->sum('book_value');

        return view('fixed-assets.register', compact(
            'assets', 
            'categories', 
            'asOf', 
            'categoryId', 
            'status',
            'totalAssets',
            'totalCost',
            'totalDepreciation',
            'totalBookValue'
        ));
    }

    /**
     * Create journal entry for asset acquisition
     */
    protected function createAcquisitionJournal(FixedAsset $asset): void
    {
        $assetAccount = $asset->asset_account_id
            ? ChartOfAccount::find($asset->asset_account_id)
            : ChartOfAccount::where('tenant_id', $asset->tenant_id)
                ->where('account_code', '1500')
                ->first();

        $cashAccount = ChartOfAccount::where('tenant_id', $asset->tenant_id)
            ->where('account_code', '1000')
            ->first();

        if (!$assetAccount || !$cashAccount) {
            return;
        }

        $journal = \App\Models\Journal::create([
            'tenant_id' => $asset->tenant_id,
            'journal_number' => \App\Models\Journal::generateNumber($asset->tenant_id),
            'journal_date' => $asset->purchase_date,
            'reference' => $asset->asset_number,
            'description' => "Asset Acquisition - {$asset->name}",
            'reference_type' => FixedAsset::class,
            'reference_id' => $asset->id,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
            'created_by' => auth()->id(),
        ]);

        // Debit: Asset Account
        \App\Models\JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $assetAccount->id,
            'description' => "Asset Acquisition - {$asset->name}",
            'debit' => $asset->purchase_cost,
            'credit' => 0,
        ]);

        // Credit: Cash
        \App\Models\JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cashAccount->id,
            'description' => "Payment for {$asset->name}",
            'debit' => 0,
            'credit' => $asset->purchase_cost,
        ]);

        $journal->updateTotals();

        // Apply to the account balances with the normal debit/credit rules.
        app(\App\Services\JournalService::class)->updateAccountBalances($journal);
    }
}
