<?php

namespace App\Models;

use App\Services\Accounting\LockDates;
use App\Services\Accounting\VatDefaults;
use App\Services\ChartOfAccountService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     * Automatically create default chart of accounts when a new tenant is created.
     */
    protected static function booted(): void
    {
        static::created(function (Tenant $tenant) {
            ChartOfAccountService::createDefaultAccountsForTenant($tenant);
            WhtCategory::seedDefaults($tenant->id);
            VatDefaults::seedForTenant($tenant->id);
        });
        static::saved(fn (Tenant $tenant) => LockDates::instance()->forget($tenant->id));
    }

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'logo',
        'website',
        'tax_number',
        'currency',
        'fiscal_year_start',
        'is_active',
        'settings',
        'default_sales_tax_id',
        'default_purchase_tax_id',
        'prices_include_tax',
        'tax_per_line_item',
        'invoice_template_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'fiscal_year_start' => 'date',
        'prices_include_tax' => 'boolean',
        'tax_per_line_item' => 'boolean',
        'closure_requested_at' => 'datetime',
        'closure_purge_at' => 'datetime',
        'staff_lock_date' => 'date',
        'all_users_lock_date' => 'date',
    ];

    /** Days between closing a business and erasing its data (O7). */
    public const CLOSURE_GRACE_DAYS = 30;

    /**
     * The owner: the first active admin of the business (the person who
     * registered it, unless they have left). Only the owner can close it (O7).
     */
    public function owner(): ?User
    {
        return $this->users()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('id')
            ->first();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->tenant_id === $this->id && $this->owner()?->id === $user->id;
    }

    /** Closed by its owner and waiting to be erased (O7). */
    public function isClosing(): bool
    {
        return $this->closure_purge_at !== null;
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function defaultSalesTax(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_sales_tax_id');
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function defaultPurchaseTax(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_purchase_tax_id');
    }

    /** @return BelongsTo<InvoiceTemplate, $this> */
    public function invoiceTemplate(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class);
    }

    /** @return HasMany<InvoiceTemplate, $this> */
    public function invoiceTemplates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Vendor, $this> */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<Department, $this> */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** @return HasMany<ChartOfAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    /**
     * Get all subscriptions for this tenant
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The subscription that currently gives access: status active and the
     * paid period not yet over (finding C1). One with no end date was
     * granted open-ended by an admin.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('ends_at')
            ->latest('id');
    }

    /**
     * The most recent subscription of any status (for renewing one that has
     * run out, or paying for one started at sign-up).
     *
     * @return HasOne<Subscription, $this>
     */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Get the current plan
     */
    public function currentPlan()
    {
        return $this->activeSubscription?->plan;
    }

    /**
     * Check if tenant has an active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Check if tenant can add more users
     */
    public function canAddUsers(int $count = 1): bool
    {
        $subscription = $this->activeSubscription;

        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        $currentUsers = $this->users()->count();

        return ($currentUsers + $count) <= $subscription->plan->max_users;
    }

    /**
     * Get remaining user slots
     */
    public function remainingUserSlots(): int
    {
        $subscription = $this->activeSubscription;

        if (! $subscription || ! $subscription->plan) {
            return 0;
        }

        return max(0, $subscription->plan->max_users - $this->users()->count());
    }

    /**
     * Get subscription status label
     */
    public function getSubscriptionStatusAttribute(): string
    {
        $subscription = $this->activeSubscription;

        if (! $subscription) {
            return 'No Subscription';
        }

        return match ($subscription->status) {
            Subscription::STATUS_ACTIVE => 'Active',
            Subscription::STATUS_CANCELLED => 'Cancelled',
            Subscription::STATUS_EXPIRED => 'Expired',
            Subscription::STATUS_PAST_DUE => 'Past Due',
            default => 'Unknown',
        };
    }

    /**
     * Get currency symbol for the tenant's currency
     */
    public function getCurrencySymbolAttribute(): string
    {
        $currencies = collect(config('mybooks.currencies', []));
        $match = $currencies->firstWhere('code', $this->currency);

        return $match['symbol'] ?? $this->currency ?? '$';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
