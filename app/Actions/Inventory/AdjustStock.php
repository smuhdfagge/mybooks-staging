<?php

namespace App\Actions\Inventory;

use App\Models\ChartOfAccount;
use App\Models\InventoryHistory;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A stock adjustment, a stock count or opening stock (F1), shared by the
 * inventory page, the API, the stock list's bulk reset and the item import.
 *
 * Stock out (or counted lower) leaves through StockValuationService::issue(),
 * so it is costed at its FIFO layers or the warehouse's average, and posts
 * Dr Stock Losses, Cr Inventory at that cost.
 *
 * Stock in (or counted higher) goes in as a cost layer at the cost given,
 * else the warehouse's current average cost, else the item's cost price,
 * and posts Dr Inventory, Cr Stock Losses (found stock reduces the losses)
 * or the account the user chose. Opening stock credits Opening Balance
 * Equity instead.
 *
 * Before this, adjustments changed stock but never touched the ledger, so
 * the Inventory account drifted away from the stock value.
 */
class AdjustStock
{
    public const IN = 'in';

    public const OUT = 'out';

    public const SET = 'set';

    public function __construct(private StockValuationService $valuation, private JournalService $journals) {}

    /**
     * @param  string  $mode  in, out or set (count: $quantity is what is there now)
     * @param  ?int  $accountId  the other side of the journal, instead of Stock Losses
     * @param  string  $label  starts the journal description ("Stock count", "Opening stock")
     * @param  string  $offsetKey  AccountCodeService key used when no account is chosen
     * @param  ?string  $historyType  'adjustment' keeps a signed change on the history row (the API's rows)
     */
    public function handle(
        Item $item,
        string $mode,
        float $quantity,
        ?int $warehouseId = null,
        ?float $unitCost = null,
        ?string $reason = null,
        ?int $accountId = null,
        mixed $date = null,
        ?string $batchNumber = null,
        string $label = 'Stock adjustment',
        string $referenceType = 'adjustment',
        string $offsetKey = 'stock_losses',
        ?int $userId = null,
        ?string $historyType = null,
    ): InventoryHistory {
        $tenantId = (int) $item->tenant_id;
        $userId ??= auth()->id();
        $date = Carbon::parse($date ?? now())->toDateString();
        if (! in_array($mode, [self::IN, self::OUT, self::SET], true)) {
            throw ValidationException::withMessages(['type' => 'Choose stock in, stock out or a count.']);
        }
        if ($quantity < 0 || ($mode !== self::SET && $quantity <= 0)) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity above zero.']);
        }
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages(['date' => 'A stock adjustment can\'t be dated in the future.']);
        }
        if ($blocked = LockDates::instance()->blockReason($date, $tenantId, auth()->user())) {
            throw ValidationException::withMessages(['date' => $blocked]);
        }
        $offset = $this->offsetAccount($tenantId, $accountId, $offsetKey);
        $warehouseId ??= Warehouse::defaultIdFor($tenantId);

        return DB::transaction(function () use ($item, $mode, $quantity, $warehouseId, $unitCost, $reason, $date, $batchNumber, $label, $referenceType, $offset, $tenantId, $userId, $historyType) {
            $inventory = $this->valuation->stockRow($tenantId, $item->id, $warehouseId);
            if ($inventory->wasRecentlyCreated) {
                $inventory->unit_cost = $item->cost_price ?? 0;
            }

            $change = match ($mode) {
                self::IN => $quantity,
                self::OUT => -$quantity,
                default => round($quantity - (float) $inventory->quantity, 4),
            };

            // Stock can't go below what is reserved for invoices, or below zero.
            $free = (float) $inventory->quantity - (float) $inventory->reserved_quantity;
            if ($change < 0 && -$change - $free > 0.00001) {
                throw ValidationException::withMessages(['quantity' => "Only {$this->qty(max(0, $free))} of {$item->name} is free in this warehouse, so you can't take out {$this->qty(-$change)}."]);
            }

            $note = $reason !== null && trim($reason) !== '' ? trim($reason) : null;
            $history = new InventoryHistory([
                'tenant_id' => $tenantId,
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'type' => $type = $historyType ?? match ($mode) {
                    self::IN => 'in',
                    self::OUT => 'out',
                    default => 'adjustment',
                },
                'quantity' => $type === 'adjustment' ? $change : $quantity,
                'reference_type' => $referenceType,
                'notes' => $mode === self::SET ? trim("Counted: {$this->qty($quantity)}. ".($note ?? '')) : $note,
                'created_by' => $userId,
            ]);
            $history->skipTenantGuard = true;
            $history->save();

            $value = 0.0;
            if ($change > 0) {
                $cost = round($unitCost ?? $this->valuation->getWeightedAverageCost($item, $warehouseId), 4);
                $this->valuation->updateWeightedAverageCost($inventory, $change, $cost);
                $this->valuation->addLayer($tenantId, $item->id, $change, $cost, $warehouseId, 'adjustment', $history->id, $batchNumber)
                    ->forceFill(['received_date' => $date])->save();
                $value = round($change * $cost, 2);
            } elseif ($change < 0) {
                // Out of the cost layers at FIFO / average, recorded against this row.
                $value = $this->valuation->issue($item, -$change, InventoryHistory::class, $history->id, false, $warehouseId);
            }

            $inventory->quantity = round((float) $inventory->quantity + $change, 4);
            $inventory->save();

            if ($value >= 0.01) {
                $this->post($history, $item, $warehouseId, $change, $value, $offset, $label, $note, $date, $userId);
            }

            return $history;
        });
    }

    /** The account on the other side: the one chosen, else $offsetKey's. */
    private function offsetAccount(int $tenantId, ?int $accountId, string $offsetKey): string
    {
        $inventoryCode = AccountCodeService::resolve($tenantId, 'inventory');
        if (! $accountId) {
            return AccountCodeService::resolve($tenantId, $offsetKey);
        }

        $code = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)
            ->whereKey($accountId)->value('account_code');
        if (! $code) {
            throw ValidationException::withMessages(['account_id' => 'Choose one of your own accounts.']);
        }
        if ($code === $inventoryCode) {
            throw ValidationException::withMessages(['account_id' => 'Choose where the stock came from or went to, not Inventory itself.']);
        }

        return $code;
    }

    private function post(InventoryHistory $history, Item $item, int $warehouseId, float $change, float $value, string $offset, string $label, ?string $note, string $date, ?int $userId): void
    {
        $t = (int) $history->tenant_id;
        $warehouse = Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name') ?? 'Main warehouse';
        $what = "{$item->name} ".($change < 0 ? '−' : '+').$this->qty(abs($change)).($item->unit ? " {$item->unit}" : '');
        $description = "{$label}, {$warehouse}: {$what}".($note ? " ({$note})" : '');
        $inventory = AccountCodeService::resolve($t, 'inventory');

        $text = mb_substr("{$label}: {$what}", 0, 190);
        $lines = $change < 0
            ? [[$offset, $value, 0.0, $text], [$inventory, 0.0, $value, $text]]
            : [[$inventory, $value, 0.0, $text], [$offset, 0.0, $value, $text]];

        $this->journals->postLines($history, 'STK-'.$history->id, $date, mb_substr($description, 0, 250), $lines, $userId, 'stock_adjustment');
    }

    private function qty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
