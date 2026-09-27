<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;

class AssemblyOrder extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'bill_of_materials_id',
        'warehouse_id',
        'quantity',
        'status',
        'total_cost',
        'notes',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(int $tenantId): string
    {
        $latest = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        $nextNumber = $latest ? ((int) substr($latest->order_number, 4)) + 1 : 1;

        return 'ASM-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Build/assemble: consume components, produce finished item.
     */
    public function complete(): void
    {
        if ($this->status !== self::STATUS_DRAFT && $this->status !== self::STATUS_IN_PROGRESS) {
            throw new \RuntimeException('Only draft or in-progress assembly orders can be completed.');
        }

        $bom = $this->billOfMaterial()->with('components.item')->first();

        if (!$bom) {
            throw new \RuntimeException('Bill of materials not found.');
        }

        DB::transaction(function () use ($bom) {
            $totalCost = 0;

            // 1. Consume component items
            foreach ($bom->components as $component) {
                $required = $component->effective_quantity * (float) $this->quantity;
                $item = $component->item;

                if (!$item || !$item->track_inventory) {
                    continue;
                }

                $inventoryQuery = Inventory::where('tenant_id', $this->tenant_id)
                    ->where('item_id', $component->item_id);

                if ($this->warehouse_id) {
                    $inventoryQuery->where('warehouse_id', $this->warehouse_id);
                }

                $inventory = $inventoryQuery->first();

                if ($inventory) {
                    $inventory->quantity -= $required;
                    $inventory->save();
                }

                $totalCost += $required * ($item->cost_price ?? 0);

                InventoryHistory::create([
                    'tenant_id' => $this->tenant_id,
                    'item_id' => $component->item_id,
                    'type' => 'out',
                    'quantity' => $required,
                    'reference_type' => 'assembly_order',
                    'reference_id' => $this->id,
                    'notes' => "Consumed for assembly #{$this->order_number}",
                    'created_by' => auth()->id(),
                ]);
            }

            // 2. Produce finished goods
            $outputQty = (float) $this->quantity * (float) $bom->output_quantity;
            $finishedItem = $bom->item;

            if ($finishedItem && $finishedItem->track_inventory) {
                $inventory = Inventory::firstOrCreate(
                    [
                        'tenant_id' => $this->tenant_id,
                        'item_id' => $finishedItem->id,
                        'warehouse_id' => $this->warehouse_id,
                    ],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'unit_cost' => 0]
                );

                // Update weighted average cost
                $existingValue = (float) $inventory->quantity * (float) $inventory->unit_cost;
                $newValue = $existingValue + $totalCost;
                $newTotalQty = (float) $inventory->quantity + $outputQty;
                $inventory->unit_cost = $newTotalQty > 0 ? $newValue / $newTotalQty : 0;
                $inventory->quantity += $outputQty;
                $inventory->save();

                // Create inventory layer
                InventoryLayer::create([
                    'tenant_id' => $this->tenant_id,
                    'item_id' => $finishedItem->id,
                    'warehouse_id' => $this->warehouse_id,
                    'quantity' => $outputQty,
                    'remaining_quantity' => $outputQty,
                    'unit_cost' => $outputQty > 0 ? $totalCost / $outputQty : 0,
                    'reference_type' => 'assembly_order',
                    'reference_id' => $this->id,
                    'received_date' => now()->toDateString(),
                ]);

                InventoryHistory::create([
                    'tenant_id' => $this->tenant_id,
                    'item_id' => $finishedItem->id,
                    'type' => 'in',
                    'quantity' => $outputQty,
                    'reference_type' => 'assembly_order',
                    'reference_id' => $this->id,
                    'notes' => "Assembled via #{$this->order_number}",
                    'created_by' => auth()->id(),
                ]);
            }

            $this->update([
                'status' => self::STATUS_COMPLETED,
                'total_cost' => $totalCost,
                'completed_at' => now(),
            ]);
        });
    }
}
