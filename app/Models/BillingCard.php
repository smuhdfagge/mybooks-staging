<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The card a business saved with Paystack for renewing its MyBooks
 * subscription (session 15). One per business; paying by card again
 * replaces it. The authorization code is what lets us charge the card, so
 * it is encrypted at rest and hidden from arrays, JSON and logs.
 */
class BillingCard extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'authorization_code', 'signature', 'card_type', 'bank',
        'last4', 'exp_month', 'exp_year', 'email', 'customer_code', 'auto_renew',
    ];

    protected $hidden = ['authorization_code', 'customer_code', 'signature'];

    protected $casts = [
        'authorization_code' => 'encrypted',
        'customer_code' => 'encrypted',
        'auto_renew' => 'boolean',
    ];

    /** "Visa •••• 4081" */
    public function label(): string
    {
        $type = trim(ucfirst(strtolower((string) $this->card_type)));

        return ($type !== '' ? $type : 'Card').' •••• '.$this->last4;
    }

    /** "08/27" */
    public function expiryLabel(): string
    {
        return str_pad((string) $this->exp_month, 2, '0', STR_PAD_LEFT).'/'.substr((string) $this->exp_year, -2);
    }

    /** The last moment the card can be used: the end of its expiry month. */
    public function expiresAt(): ?Carbon
    {
        $month = (int) $this->exp_month;
        $year = (int) $this->exp_year;

        if ($month < 1 || $month > 12 || $year < 2000) {
            return null;
        }

        return Carbon::create($year, $month, 1)->endOfMonth();
    }

    public function hasExpiredBy(CarbonInterface $date): bool
    {
        $expires = $this->expiresAt();

        return $expires !== null && $date->greaterThan($expires);
    }

    /** For log lines: never the code itself (session 15). */
    public static function mask(?string $code): string
    {
        $code = (string) $code;

        return $code === '' ? '' : substr($code, 0, 5).'…'.substr($code, -2);
    }
}
