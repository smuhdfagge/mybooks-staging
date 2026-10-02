<?php

namespace App\Models;

use App\Actions\Quotations\ConvertQuotation;
use App\Enums\QuotationStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant, HasFactory, KeepsTotalsBalanced, LogsActivity, SoftDeletes;
    use GuardsStatusTransitions, HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_SENT = 'sent';

    const STATUS_ACCEPTED = 'accepted';

    const STATUS_REJECTED = 'rejected';

    const STATUS_EXPIRED = 'expired';

    const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_number',
        'reference',
        'quotation_date',
        'expiry_date',
        'status',
        'sent_at',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'notes',
        'terms',
        'converted_to_so_id',
        'converted_to_invoice_id',
        'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_so_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['quotation_number', 'QTN-', 6];
    }

    /** Past its expiry date and not yet answered (the daily command marks it expired). */
    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Expired->value
            || ($this->expiry_date && $this->expiry_date->lt(today())
                && in_array($this->status, [QuotationStatus::Draft->value, QuotationStatus::Sent->value], true));
    }

    /**
     * Convert this quotation to a sales order (see ConvertQuotation).
     */
    public function convertToSalesOrder(): SalesOrder
    {
        return app(ConvertQuotation::class)->toSalesOrder($this, auth()->id());
    }

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced, Q2).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }

    /** Allowed status moves. */
    protected static function statusEnum(): string
    {
        return QuotationStatus::class;
    }
}
