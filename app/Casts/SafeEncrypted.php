<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Laravel's 'encrypted' cast, except that a value it cannot read gives
 * back null instead of throwing.
 *
 * Why this exists: a bank account number is encrypted with APP_KEY, and
 * if that key changes without the old one being kept in
 * APP_PREVIOUS_KEYS, every row written under the old key becomes
 * unreadable. With the stock cast the decryption throws while Blade is
 * rendering, which turns one unreadable field into a 500 page for the
 * whole screen. That is what happened on mybooks.cloud: a tenant could
 * not open Banks, and could not record a payment either, because the
 * payment form lists bank accounts with their numbers.
 *
 * Losing one field should cost that field, not the page. The business
 * can still see its bank names, still record a payment, and still type
 * the number in again. Each failure is logged with the row it came from,
 * so this is visible rather than silent.
 *
 * It does not make unreadable data readable. Only the original key can,
 * by going back into APP_PREVIOUS_KEYS.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SafeEncrypted implements CastsAttributes
{
    /** Rows already reported, so one bad row cannot fill the log. */
    private static array $reported = [];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            $this->reportOnce($model, $key);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }

    private function reportOnce(Model $model, string $key): void
    {
        $id = $model::class.'#'.($model->getKey() ?? 'new').'.'.$key;

        if (isset(self::$reported[$id])) {
            return;
        }

        self::$reported[$id] = true;

        // Named plainly, because whoever reads this will be looking at a
        // blank field and wondering whether the data was ever there.
        Log::warning('Could not decrypt a stored value; showing it as empty.', [
            'model' => $model::class,
            'id' => $model->getKey(),
            'field' => $key,
            'tenant_id' => $model->getAttribute('tenant_id'),
            'hint' => 'Encrypted under a different APP_KEY. Put the old key in APP_PREVIOUS_KEYS to read it again.',
        ]);
    }

    /** Test seam: the per-request guard would otherwise span test cases. */
    public static function forgetReported(): void
    {
        self::$reported = [];
    }
}
