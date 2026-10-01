<?php

namespace App\Services\Payroll;

use App\Models\StatutoryContribution;
use App\Models\Tenant;

/**
 * Business-wide statutory payroll switches, kept in the tenant's settings
 * under 'payroll_statutory':
 * - auto: payroll works out pension, NHF, NSITF and ITF from the rates on
 *   Payroll > Statutory settings (off by default, so existing payslips
 *   don't change until the business turns it on);
 * - pensionable_components: allowances counted with basic salary for
 *   pension (matched by name, e.g. "Housing" matches "Housing Allowance").
 */
class StatutorySettings
{
    /** @return array{auto: bool, pensionable_components: list<string>} */
    public static function get(int $tenantId): array
    {
        $settings = Tenant::whereKey($tenantId)->first()?->settings['payroll_statutory'] ?? [];
        $components = $settings['pensionable_components'] ?? StatutoryContribution::DEFAULT_PENSIONABLE_COMPONENTS;

        return [
            'auto' => (bool) ($settings['auto'] ?? false),
            'pensionable_components' => array_values(array_filter(array_map('trim', (array) $components), fn (string $c) => $c !== '')),
        ];
    }

    /** @param  list<string>  $components */
    public static function put(int $tenantId, bool $auto, array $components): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $settings = $tenant->settings ?? [];
        $settings['payroll_statutory'] = [
            'auto' => $auto,
            'pensionable_components' => array_values(array_unique(array_filter(array_map('trim', $components), fn (string $c) => $c !== ''))),
        ];
        $tenant->settings = $settings;
        $tenant->save();
    }
}
