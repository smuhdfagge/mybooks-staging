<?php

namespace App\Models;

use App\Services\ChartOfAccountService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        });
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
    ];

    public function defaultSalesTax()
    {
        return $this->belongsTo(TaxRate::class, 'default_sales_tax_id');
    }

    public function defaultPurchaseTax()
    {
        return $this->belongsTo(TaxRate::class, 'default_purchase_tax_id');
    }

    public function invoiceTemplate()
    {
        return $this->belongsTo(InvoiceTemplate::class);
    }

    public function invoiceTemplates()
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function vendors()
    {
        return $this->hasMany(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function accounts()
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    /**
     * Get all subscriptions for this tenant
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The subscription that currently gives access: status active and the
     * paid period not yet over (finding C1). One with no end date was
     * granted open-ended by an admin.
     */
    public function activeSubscription()
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
     */
    public function latestSubscription()
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
        
        if (!$subscription || !$subscription->plan) {
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
        
        if (!$subscription || !$subscription->plan) {
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
        
        if (!$subscription) {
            return 'No Subscription';
        }

        return match($subscription->status) {
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
