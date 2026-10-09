<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A business's e-invoicing choices and NRS keys (session 18). The keys are
 * encrypted at rest, hidden from toArray()/JSON, and only ever shown
 * masked (see mask()).
 */
class EInvoiceSetting extends Model
{
    use BelongsToTenant;

    public const ENVIRONMENTS = ['sandbox', 'live'];

    public const MODES = ['manual', 'auto'];

    /** The fields that hold a secret. */
    public const SECRETS = ['api_key', 'api_secret', 'service_id', 'business_id', 'public_key', 'certificate'];

    protected $table = 'e_invoice_settings';

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = self::SECRETS;

    protected $casts = [
        'enabled' => 'boolean',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'service_id' => 'encrypted',
        'business_id' => 'encrypted',
        'public_key' => 'encrypted',
        'certificate' => 'encrypted',
        'last_tested_at' => 'datetime',
        'last_test_ok' => 'boolean',
    ];

    public static function forTenant(int $tenantId): self
    {
        $settings = self::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        if ($settings) {
            return $settings;
        }

        $settings = new self;
        $settings->skipTenantGuard = true; // tenant set here, also from the scheduler
        $settings->tenant_id = $tenantId;
        $settings->save();

        return $settings->refresh();
    }

    /** Every key NRS needs for a call has been entered. */
    public function hasKeys(): bool
    {
        return filled($this->api_key) && filled($this->api_secret) && filled($this->service_id) && filled($this->business_id);
    }

    public function isAuto(): bool
    {
        return $this->submit_mode === 'auto';
    }

    public function isLiveEnvironment(): bool
    {
        return $this->environment === 'live';
    }

    /** "••••1234": enough to recognise a key, never enough to use it. */
    public static function mask(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        return '••••••••'.(strlen($value) > 8 ? substr($value, -4) : '');
    }
}
