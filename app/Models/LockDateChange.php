<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the lock date history (session 11): a lock date set, moved or
 * cleared, an accounting period closed or reopened, or a VAT return reopened.
 */
class LockDateChange extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'kind', 'old_date', 'new_date', 'reason', 'description', 'user_id'];

    protected $casts = [
        'old_date' => 'date',
        'new_date' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Write a history line for the business. */
    public static function record(int $tenantId, string $lock, mixed $oldDate, mixed $newDate, ?string $reason, string $description, ?int $userId = null): self
    {
        $change = new self([
            'kind' => $lock,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'description' => $description,
            'user_id' => $userId ?? auth()->id(),
        ]);
        $change->tenant_id = $tenantId;
        $change->skipTenantGuard = true;
        $change->save();

        return $change;
    }
}
