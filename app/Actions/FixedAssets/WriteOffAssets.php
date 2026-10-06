<?php

namespace App\Actions\FixedAssets;

use App\Models\FixedAsset;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Bulk dispose from the fixed-assets list (F2): each asset goes through the
 * same disposal as the asset's own page (DepreciationService::disposeAsset),
 * as a write-off with no money received, so each one posts its disposal
 * journal (Dr accumulated depreciation, Dr loss, Cr asset cost). The old
 * bulk action only changed the status, leaving the asset in the books.
 *
 * Assets already disposed or sold are skipped. One that can't be disposed
 * (wrong status, date locked or in a closed period) is listed with the
 * reason; the others still go through.
 */
class WriteOffAssets
{
    public const METHODS = ['scrapped', 'donated', 'lost', 'other'];

    public function __construct(private DepreciationService $depreciation) {}

    /**
     * @param  array<int, int|string>  $assetIds
     * @return array{disposed: int, skipped: int, failed: array<string, string>}
     */
    public function handle(array $assetIds, mixed $date, string $method = 'scrapped', ?string $reason = null): array
    {
        if (! in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['disposalMethod' => 'Choose how the assets were disposed of.']);
        }
        $date = Carbon::parse($date)->startOfDay();
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['disposalDate' => 'The disposal date can\'t be in the future.']);
        }

        $result = ['disposed' => 0, 'skipped' => 0, 'failed' => []];
        foreach (FixedAsset::whereIn('id', $assetIds)->orderBy('id')->get() as $asset) {
            if (in_array($asset->status, [FixedAsset::STATUS_DISPOSED, FixedAsset::STATUS_SOLD], true)) {
                $result['skipped']++;

                continue;
            }
            if (! $asset->canDispose()) {
                $result['failed'][$asset->name] = 'only active or fully depreciated assets can be disposed of';

                continue;
            }
            try {
                $this->depreciation->disposeAsset($asset, $method, 0, $date->copy(), $reason ?: null);
                $result['disposed']++;
            } catch (ValidationException $e) {
                $result['failed'][$asset->name] = (string) collect($e->errors())->flatten()->first();
            }
        }

        return $result;
    }
}
