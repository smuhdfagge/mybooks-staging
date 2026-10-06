<?php

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\Tenant;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The fixed-assets list's bulk "Dispose" used to mark assets disposed
 * without a disposal journal (F2), so they stayed in the books. Run once
 * after deploying: for each asset marked disposed (or sold) with no
 * disposal journal, post the journal the asset's own page would have,
 * dated its disposal date, as a write-off unless a disposal amount was
 * recorded. Dates behind a lock date or in a closed period are listed and
 * skipped. --dry-run shows everything without posting.
 */
class PostMissingDisposalJournals extends Command
{
    protected $signature = 'assets:post-missing-disposal-journals
        {--dry-run : Show what would be posted without changing anything}
        {--tenant= : Only this business (id)}';

    protected $description = 'Post the missing disposal journals for fixed assets marked disposed';

    public function handle(JournalService $journals): int
    {
        $dry = (bool) $this->option('dry-run');
        $posted = 0;
        $skipped = 0;

        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))->orderBy('id')->get();
        foreach ($tenants as $tenant) {
            $t = (int) $tenant->id;
            $assets = FixedAsset::withoutGlobalScopes()->where('tenant_id', $t)
                ->whereIn('status', [FixedAsset::STATUS_DISPOSED, FixedAsset::STATUS_SOLD])
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journals')
                    ->whereColumn('journals.reference_id', 'fixed_assets.id')
                    ->where('journals.reference_type', FixedAsset::class)
                    ->where('journals.journal_type', JournalService::ASSET_DISPOSAL)
                    ->whereNull('journals.deleted_at'))
                ->orderBy('id')->get();
            if ($assets->isEmpty()) {
                continue;
            }

            $this->newLine();
            $this->line("<options=bold>Business {$t}: {$tenant->name}</>");
            $rows = [];
            foreach ($assets as $asset) {
                $date = Carbon::parse($asset->disposal_date ?? $asset->updated_at)->startOfDay();
                $proceeds = round((float) ($asset->disposal_amount ?? 0), 2);
                $gainLoss = round($proceeds - (float) $asset->book_value, 2);
                $blocked = LockDates::instance()->blockReason($date, $t);
                $status = match (true) {
                    (float) $asset->purchase_cost <= 0 => 'nothing to post (no cost)',
                    $blocked !== null => 'SKIPPED: date is locked or closed',
                    $dry => 'would post',
                    default => 'posted',
                };
                $rows[] = [$date->toDateString(), $asset->asset_number, $asset->name, number_format((float) $asset->purchase_cost, 2),
                    number_format((float) $asset->accumulated_depreciation, 2), number_format($proceeds, 2), number_format($gainLoss, 2), $status];

                if ((float) $asset->purchase_cost <= 0) {
                    continue;
                }
                if ($blocked !== null) {
                    $skipped++;

                    continue;
                }
                $posted++;
                if ($dry) {
                    continue;
                }

                DB::transaction(function () use ($asset, $date, $proceeds, $gainLoss, $journals) {
                    $journal = $journals->createDisposalJournal($asset, $date, $proceeds, $gainLoss);
                    $journal?->forceFill(['description' => $journal->description.' (posted late)'])->withoutPeriodValidation()->save();
                    $asset->forceFill([
                        'disposal_date' => $asset->disposal_date ?? $date,
                        'disposal_amount' => $proceeds,
                        'disposal_method' => $asset->disposal_method ?: 'other',
                        'gain_loss_on_disposal' => $gainLoss,
                    ])->withoutPeriodValidation()->save();
                });
            }
            $this->table(['Date', 'Asset', 'Name', 'Cost', 'Depreciation', 'Received', 'Gain / loss', 'Result'], $rows);
        }

        $this->newLine();
        $this->info(($dry ? 'Would post' : 'Posted')." {$posted} disposal journal(s); {$skipped} skipped (locked or closed dates).");

        return self::SUCCESS;
    }
}
