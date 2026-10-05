<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Journal;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    /**
     * Calculate monthly depreciation amount based on method
     */
    public function calculateMonthlyDepreciation(FixedAsset $asset, int $periodNumber = 1): float
    {
        if (! $asset->canDepreciate()) {
            return 0;
        }

        return match ($asset->depreciation_method) {
            FixedAsset::METHOD_STRAIGHT_LINE => $this->calculateStraightLine($asset),
            FixedAsset::METHOD_DECLINING_BALANCE => $this->calculateDecliningBalance($asset, $periodNumber),
            FixedAsset::METHOD_DOUBLE_DECLINING => $this->calculateDoubleDecliningBalance($asset, $periodNumber),
            FixedAsset::METHOD_SUM_OF_YEARS => $this->calculateSumOfYears($asset, $periodNumber),
            default => $this->calculateStraightLine($asset),
        };
    }

    /**
     * Straight Line Depreciation
     * (Cost - Salvage Value) / Useful Life in Months
     */
    protected function calculateStraightLine(FixedAsset $asset): float
    {
        $usefulLifeMonths = $asset->useful_life * 12;
        if ($usefulLifeMonths <= 0) {
            return 0;
        }

        $monthlyDepreciation = $asset->depreciable_amount / $usefulLifeMonths;

        // Don't depreciate below salvage value
        $remainingDepreciable = $asset->book_value - $asset->salvage_value;

        return min($monthlyDepreciation, max(0, $remainingDepreciable));
    }

    /**
     * Declining Balance Depreciation
     * Book Value * (Depreciation Rate / 12)
     */
    protected function calculateDecliningBalance(FixedAsset $asset, int $periodNumber): float
    {
        $rate = $asset->depreciation_rate ?? (1 / $asset->useful_life) * 100;
        $monthlyRate = $rate / 100 / 12;

        $depreciation = $asset->book_value * $monthlyRate;

        // Don't depreciate below salvage value
        $remainingDepreciable = $asset->book_value - $asset->salvage_value;

        return min($depreciation, max(0, $remainingDepreciable));
    }

    /**
     * Double Declining Balance Depreciation
     * Book Value * (2 / Useful Life Years / 12)
     */
    protected function calculateDoubleDecliningBalance(FixedAsset $asset, int $periodNumber): float
    {
        $yearsLife = $asset->useful_life;
        $monthlyRate = (2 / $yearsLife) / 12;

        $depreciation = $asset->book_value * $monthlyRate;

        // Don't depreciate below salvage value
        $remainingDepreciable = $asset->book_value - $asset->salvage_value;

        return min($depreciation, max(0, $remainingDepreciable));
    }

    /**
     * Sum of Years Digits Depreciation
     * Depreciable Amount * (Remaining Life / Sum of Years Digits) / 12
     */
    protected function calculateSumOfYears(FixedAsset $asset, int $periodNumber): float
    {
        $yearsLife = $asset->useful_life;
        $sumOfYears = ($yearsLife * ($yearsLife + 1)) / 2;

        // Calculate which year we're in
        $currentYear = ceil($periodNumber / 12);
        $remainingYears = max(1, $yearsLife - $currentYear + 1);

        // Annual depreciation for this year
        $annualDepreciation = $asset->depreciable_amount * ($remainingYears / $sumOfYears);
        $monthlyDepreciation = $annualDepreciation / 12;

        // Don't depreciate below salvage value
        $remainingDepreciable = $asset->book_value - $asset->salvage_value;

        return min($monthlyDepreciation, max(0, $remainingDepreciable));
    }

    /**
     * Generate complete depreciation schedule for an asset
     */
    public function generateSchedule(FixedAsset $asset): array
    {
        $schedule = [];
        $bookValue = $asset->purchase_cost;
        $accumulatedDepreciation = 0;
        $date = $asset->in_service_date->copy()->endOfMonth();

        $usefulLifeMonths = (int) ($asset->useful_life * 12);
        for ($period = 1; $period <= $usefulLifeMonths; $period++) {
            // Create temporary asset state for calculation
            $tempAsset = clone $asset;
            $tempAsset->book_value = $bookValue;
            $tempAsset->accumulated_depreciation = $accumulatedDepreciation;

            $depreciation = $this->calculateMonthlyDepreciation($tempAsset, $period);

            if ($depreciation <= 0) {
                break; // Stop if no more depreciation
            }

            $accumulatedDepreciation += $depreciation;
            $bookValue -= $depreciation;

            // Ensure book value doesn't go below salvage value
            if ($bookValue < $asset->salvage_value) {
                $depreciation -= ($asset->salvage_value - $bookValue);
                $bookValue = $asset->salvage_value;
                $accumulatedDepreciation = $asset->depreciable_amount;
            }

            $schedule[] = [
                'period' => $period,
                'date' => $date->copy(),
                'depreciation_amount' => round($depreciation, 2),
                'accumulated_depreciation' => round($accumulatedDepreciation, 2),
                'book_value' => round($bookValue, 2),
            ];

            if ($bookValue <= $asset->salvage_value) {
                break;
            }

            $date->addMonth()->endOfMonth();
        }

        return $schedule;
    }

    /**
     * Record depreciation for an asset
     */
    public function recordDepreciation(FixedAsset $asset, Carbon $depreciationDate, ?string $notes = null): ?FixedAssetDepreciation
    {
        if (! $asset->canDepreciate()) {
            throw new Exception('Asset cannot be depreciated.');
        }

        // One depreciation per asset per month (A11): the manual button and
        // the monthly run could both post the same month.
        $alreadyDone = $asset->depreciations()
            ->where('status', FixedAssetDepreciation::STATUS_POSTED)
            ->whereYear('depreciation_date', $depreciationDate->year)
            ->whereMonth('depreciation_date', $depreciationDate->month)
            ->exists();
        if ($alreadyDone) {
            throw new Exception("{$asset->name} has already been depreciated for {$depreciationDate->format('F Y')}.");
        }

        return DB::transaction(function () use ($asset, $depreciationDate, $notes) {
            // Calculate period number
            $periodNumber = $asset->depreciations()->count() + 1;

            // Calculate depreciation amount
            $depreciationAmount = $this->calculateMonthlyDepreciation($asset, $periodNumber);

            if ($depreciationAmount <= 0) {
                throw new Exception('No depreciation amount to record.');
            }

            $newAccumulated = $asset->accumulated_depreciation + $depreciationAmount;
            $newBookValue = $asset->book_value - $depreciationAmount;

            // Ensure we don't go below salvage value
            if ($newBookValue < $asset->salvage_value) {
                $depreciationAmount = $asset->book_value - $asset->salvage_value;
                $newBookValue = $asset->salvage_value;
                $newAccumulated = $asset->depreciable_amount;
            }

            // A month that is closed or locked is posted on the first open
            // date after it, and says so (session 11); it used to fail.
            $postOn = Carbon::instance(app(JournalService::class)->firstOpenDate($asset->tenant_id, $depreciationDate));
            $journal = $this->createDepreciationJournal($asset, $postOn, $depreciationAmount, $depreciationDate);

            // Create depreciation record
            $depreciation = FixedAssetDepreciation::create([
                'tenant_id' => $asset->tenant_id,
                'fixed_asset_id' => $asset->id,
                'journal_id' => $journal?->id,
                'depreciation_date' => $depreciationDate,
                'period_number' => $periodNumber,
                'depreciation_amount' => $depreciationAmount,
                'accumulated_depreciation' => $newAccumulated,
                'book_value' => $newBookValue,
                'status' => FixedAssetDepreciation::STATUS_POSTED,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Update asset
            $asset->withoutPeriodValidation()->update([
                'accumulated_depreciation' => $newAccumulated,
                'book_value' => $newBookValue,
                'status' => $newBookValue <= $asset->salvage_value
                    ? FixedAsset::STATUS_FULLY_DEPRECIATED
                    : FixedAsset::STATUS_ACTIVE,
            ]);

            return $depreciation;
        });
    }

    /**
     * Dr depreciation expense, Cr accumulated depreciation, with mapped or
     * category accounts (A11).
     */
    protected function createDepreciationJournal(FixedAsset $asset, Carbon $date, float $amount, ?Carbon $month = null): ?Journal
    {
        return app(JournalService::class)->createDepreciationJournal($asset, $date, $amount, $month);
    }

    /**
     * Run depreciation for all eligible assets for a given month
     */
    public function runMonthlyDepreciation(int $tenantId, Carbon $depreciationDate): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $assets = FixedAsset::where('tenant_id', $tenantId)
            ->where('status', FixedAsset::STATUS_ACTIVE)
            ->where('in_service_date', '<=', $depreciationDate)
            ->whereColumn('book_value', '>', 'salvage_value')
            ->get();

        foreach ($assets as $asset) {
            try {
                // Check if depreciation already exists for this period
                $existingDepreciation = $asset->depreciations()
                    ->whereYear('depreciation_date', $depreciationDate->year)
                    ->whereMonth('depreciation_date', $depreciationDate->month)
                    ->exists();

                if ($existingDepreciation) {
                    $results['skipped']++;

                    continue;
                }

                $this->recordDepreciation($asset, $depreciationDate);
                $results['processed']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'asset' => $asset->asset_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Reverse a depreciation entry
     */
    public function reverseDepreciation(FixedAssetDepreciation $depreciation): bool
    {
        if (! $depreciation->canReverse()) {
            throw new Exception('This depreciation cannot be reversed.');
        }

        return DB::transaction(function () use ($depreciation) {
            $asset = $depreciation->fixedAsset;

            // Reverse the journal entry if exists
            if ($depreciation->journal) {
                $this->reverseJournal($depreciation->journal);
            }

            // Update asset balances
            $asset->withoutPeriodValidation()->update([
                'accumulated_depreciation' => $asset->accumulated_depreciation - $depreciation->depreciation_amount,
                'book_value' => $asset->book_value + $depreciation->depreciation_amount,
                'status' => FixedAsset::STATUS_ACTIVE,
            ]);

            // Update depreciation status
            $depreciation->update(['status' => FixedAssetDepreciation::STATUS_REVERSED]);

            return true;
        });
    }

    /**
     * Reverse a journal entry
     */
    protected function reverseJournal(Journal $journal): void
    {
        // Post a reversing journal (M6). Only undoing the balances left the
        // original lines counting in the ledger, so balances and ledger disagreed.
        app(JournalService::class)->reverseJournal($journal, 'Depreciation reversed');
    }

    /**
     * Dispose an asset
     */
    public function disposeAsset(
        FixedAsset $asset,
        string $method,
        ?float $amount = null,
        ?Carbon $date = null,
        ?string $notes = null
    ): bool {
        if (! $asset->canDispose()) {
            throw new Exception('Asset cannot be disposed.');
        }

        return DB::transaction(function () use ($asset, $method, $amount, $date, $notes) {
            $disposalDate = $date ?? now();
            $disposalAmount = $amount ?? 0;

            // Calculate gain/loss
            $gainLoss = $disposalAmount - $asset->book_value;

            // Create disposal journal entry
            $this->createDisposalJournal($asset, $disposalDate, $disposalAmount, $gainLoss);

            // Update asset status
            $status = $method === FixedAsset::DISPOSAL_SALE
                ? FixedAsset::STATUS_SOLD
                : FixedAsset::STATUS_DISPOSED;

            $asset->withoutPeriodValidation()->update([
                'status' => $status,
                'disposal_date' => $disposalDate,
                'disposal_amount' => $disposalAmount,
                'disposal_method' => $method,
                'disposal_notes' => $notes,
                'gain_loss_on_disposal' => $gainLoss,
            ]);

            return true;
        });
    }

    /**
     * Remove the asset and its depreciation, record proceeds and the gain or
     * loss, with mapped or category accounts (A11).
     */
    protected function createDisposalJournal(FixedAsset $asset, Carbon $date, float $amount, float $gainLoss): ?Journal
    {
        return app(JournalService::class)->createDisposalJournal($asset, $date, $amount, $gainLoss);
    }

    /**
     * Get depreciation summary for reporting
     */
    public function getDepreciationSummary(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        return FixedAssetDepreciation::where('tenant_id', $tenantId)
            ->whereBetween('depreciation_date', [$startDate, $endDate])
            ->where('status', FixedAssetDepreciation::STATUS_POSTED)
            ->with('fixedAsset')
            ->get()
            ->groupBy('fixed_asset_id')
            ->map(function ($depreciations) {
                $asset = $depreciations->first()->fixedAsset;

                return [
                    'asset' => $asset,
                    'total_depreciation' => $depreciations->sum('depreciation_amount'),
                    'periods' => $depreciations->count(),
                ];
            })
            ->values()
            ->toArray();
    }
}
