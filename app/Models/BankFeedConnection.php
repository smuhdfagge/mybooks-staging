<?php

namespace App\Models;

use App\Enums\BankFeedConnectionStatus;
use App\Support\Money;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bank account linked through a provider (Mono), feeding one MyBooks
 * bank account (session 17). Holds no login details and no full account
 * number: only the last 4 digits.
 */
class BankFeedConnection extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'provider_balance' => 'integer',
        'balance_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_sync_requested_at' => 'datetime',
        'linked_at' => 'datetime',
        'unlinked_at' => 'datetime',
    ];

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class)->withTrashed();
    }

    /** @return HasMany<BankFeedLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BankFeedLine::class, 'connection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function statusEnum(): BankFeedConnectionStatus
    {
        return BankFeedConnectionStatus::tryFrom((string) $this->status) ?? BankFeedConnectionStatus::Error;
    }

    public function isActive(): bool
    {
        return in_array($this->status, BankFeedConnectionStatus::counted(), true);
    }

    /** Linked accounts that count against the plan (not pending or disconnected). */
    public function scopeCounted($query)
    {
        return $query->whereIn('status', BankFeedConnectionStatus::counted());
    }

    /** "GTBank ••••1234" */
    public function title(): string
    {
        $name = $this->institution_name ?: 'Bank account';

        return $this->account_mask ? "{$name} ••••{$this->account_mask}" : $name;
    }

    /** The bank's own balance in naira, if known. */
    public function providerBalance(): ?float
    {
        return $this->provider_balance === null ? null : Money::fromMinor($this->provider_balance);
    }
}
