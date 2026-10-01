<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored API write response, replayed for a retry with the same
 * Idempotency-Key (I5). Kept for 24 hours, then pruned by model:prune.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $route
 * @property string $request_hash
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $content_type
 * @property Carbon|null $created_at
 */
class IdempotencyKey extends Model
{
    use MassPrunable;

    public const TTL_HOURS = 24;

    protected $fillable = [
        'user_id', 'key', 'route', 'request_hash', 'status_code', 'response_body', 'content_type',
    ];

    protected $casts = [
        'status_code' => 'integer',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subHours(self::TTL_HOURS));
    }

    public function isExpired(): bool
    {
        return $this->created_at === null || $this->created_at->lt(now()->subHours(self::TTL_HOURS));
    }
}
