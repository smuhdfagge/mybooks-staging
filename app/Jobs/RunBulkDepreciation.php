<?php

namespace App\Jobs;

use App\Models\FixedAsset;
use App\Models\Tenant;
use App\Services\DepreciationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunBulkDepreciation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $tenantId,
        public ?string $depreciationDate = null
    ) {}

    public function handle(DepreciationService $service): void
    {
        $date = $this->depreciationDate
            ? Carbon::parse($this->depreciationDate)
            : now();

        Tenant::run($this->tenantId, fn () => FixedAsset::query()
            ->where('status', 'active')
            ->whereColumn('book_value', '>', 'salvage_value')
            ->each(function (FixedAsset $asset) use ($service, $date) {
                DB::transaction(function () use ($service, $asset, $date) {
                    $service->recordDepreciation($asset, $date);
                });
            }));
    }
}
