<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone registered for push notifications (mobile plan A2). Scoped by
 * its user, not by tenant: a device belongs to whoever signed in on it.
 */
class UserDevice extends Model
{
    public const PLATFORMS = ['ios', 'android'];

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'token_hash',
        'token',
        'platform',
        'app_version',
        'last_sent_at',
    ];

    protected $hidden = ['token'];

    protected $casts = [
        'last_sent_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
