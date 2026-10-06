<?php

namespace App\Models;

use App\Enums\AssemblyOrderStatus;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An assembly order (session 14): making a quantity of a finished item from
 * its bill of materials ("build"), or taking finished items apart again
 * ("break down", for kits and hampers).
 *
 * The quantity is always in units of the finished item (bags, hampers,
 * blocks), not batches: 80 bags from a bill that makes 40 is 2 batches.
 * warehouse_id is where the components come from (a build) or the
 * finished items (a break-down); to_warehouse_id is where the result goes.
 *
 * The work is done by the actions in App\Actions\Assembly. The old
 * complete() method here costed components at their cost price, took
 * nothing out of the cost layers, let stock go negative and never found its
 * bill of materials (wrong column), so it is gone.
 */
class AssemblyOrder extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;
    use GuardsStatusTransitions, HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const KIND_BUILD = 'build';

    const KIND_BREAKDOWN = 'breakdown';

    protected $fillable = [
        'tenant_id',
        'order_number',
        'kind',
        'assembly_date',
        'bill_of_materials_id',
        'warehouse_id',
        'to_warehouse_id',
        'quantity',
        'planned_quantity',
        'quantity_made',
        'status',
        'components_cost',
        'extra_cost',
        'total_cost',
        'unit_cost',
        'notes',
        'completed_at',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'assembly_date' => 'date',
        'quantity' => 'decimal:4',
        'planned_quantity' => 'decimal:4',
        'quantity_made' => 'decimal:4',
        'components_cost' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The old relation looked for bill_of_material_id, so it never found
     * the bill (session 14).
     *
     * @return BelongsTo<BillOfMaterial, $this>
     */
    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_materials_id');
    }

    /**
     * Where the components (a build) or finished items (a break-down) come from.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Where the result goes.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Components used (a build) or got back (a break-down).
     *
     * @return HasMany<AssemblyOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AssemblyOrderItem::class);
    }

    /**
     * Extra costs (builds only).
     *
     * @return HasMany<AssemblyOrderCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(AssemblyOrderCost::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isBreakdown(): bool
    {
        return $this->kind === self::KIND_BREAKDOWN;
    }

    /** Finished units planned (old orders kept batches in quantity). */
    public function plannedQuantity(): float
    {
        if ($this->planned_quantity !== null) {
            return (float) $this->planned_quantity;
        }
        $output = (float) ($this->billOfMaterial()->withoutGlobalScopes()->value('output_quantity') ?: 1);

        return round((float) $this->quantity * $output, 4);
    }

    /** The date stock moves on (old orders had none: the day they were made). */
    public function movementDate(): string
    {
        return ($this->assembly_date ?? $this->created_at ?? now())->toDateString();
    }

    /** "Build" or "Break-down", for headings. */
    public function kindLabel(): string
    {
        return $this->isBreakdown() ? 'Break-down' : 'Build';
    }

    public static function moduleOn(): bool
    {
        return EnsureFeatureEnabled::enabled('assembly');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['order_number', 'ASM-', 6];
    }

    /** Allowed status moves. */
    protected static function statusEnum(): string
    {
        return AssemblyOrderStatus::class;
    }
}
