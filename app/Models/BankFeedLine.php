<?php

namespace App\Models;

use App\Enums\BankFeedLineStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One transaction pulled from the bank (session 17). Amount is positive;
 * direction says whether money went out (debit) or in (credit). It is
 * matched to, or used to create, one MyBooks record.
 */
class BankFeedLine extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'matched_at' => 'datetime',
        'rejected_matches' => 'array',
    ];

    /** @return BelongsTo<BankFeedConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankFeedConnection::class, 'connection_id');
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function matched(): MorphTo
    {
        return $this->morphTo('matched', 'matched_type', 'matched_id');
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    public function statusEnum(): BankFeedLineStatus
    {
        return BankFeedLineStatus::tryFrom((string) $this->status) ?? BankFeedLineStatus::New;
    }

    public function isNew(): bool
    {
        return $this->status === BankFeedLineStatus::New->value;
    }

    /** @return list<string> */
    public function rejectedKeys(): array
    {
        return array_values((array) ($this->rejected_matches ?? []));
    }

    public static function keyFor(Model $record): string
    {
        return $record::class.':'.$record->getKey();
    }

    public function scopeToReview($query)
    {
        return $query->where('status', BankFeedLineStatus::New->value);
    }
}
