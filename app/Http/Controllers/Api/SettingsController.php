<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\Accounting\LockDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends BaseApiController
{
    /**
     * Get tenant settings
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return $this->error('No organization found.', 404);
        }

        $tenant->load(['defaultSalesTax', 'defaultPurchaseTax']);
        $lockDates = LockDates::instance()->dates($tenant->id);

        return $this->success([
            'organization' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'country' => $tenant->country,
                'postal_code' => $tenant->postal_code,
                'website' => $tenant->website,
                'tax_number' => $tenant->tax_number,
                'logo' => $tenant->logo,
            ],
            'accounting' => [
                'currency' => $tenant->currency ?? 'NGN',
                'fiscal_year_start' => $tenant->fiscal_year_start?->format('Y-m-d'),
                // Read only (session 11); changed on the accounting periods page.
                'lock_dates' => [
                    'staff_lock_date' => $lockDates['staff']?->toDateString(),
                    'all_users_lock_date' => $lockDates['all_users']?->toDateString(),
                    'locked_for_you_up_to' => LockDates::instance()->lockedUpTo($tenant->id, $user)?->toDateString(),
                ],
            ],
            'tax' => [
                'default_sales_tax_id' => $tenant->default_sales_tax_id,
                'default_sales_tax' => $tenant->defaultSalesTax ? new TaxRateResource($tenant->defaultSalesTax) : null,
                'default_purchase_tax_id' => $tenant->default_purchase_tax_id,
                'default_purchase_tax' => $tenant->defaultPurchaseTax ? new TaxRateResource($tenant->defaultPurchaseTax) : null,
                'prices_include_tax' => (bool) $tenant->prices_include_tax,
                'tax_per_line_item' => (bool) $tenant->tax_per_line_item,
            ],
            'custom_settings' => $tenant->settings ?? [],
        ]);
    }

    /**
     * Update organization settings
     */
    public function updateOrganization(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            return $this->error('No organization found.', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|string|max:50',
        ]);

        $tenant->update($validated);

        return $this->success([
            'organization' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'country' => $tenant->country,
                'postal_code' => $tenant->postal_code,
                'website' => $tenant->website,
                'tax_number' => $tenant->tax_number,
            ],
        ], 'Organization settings updated successfully');
    }

    /**
     * Update accounting settings
     */
    public function updateAccounting(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            return $this->error('No organization found.', 404);
        }

        $validated = $request->validate([
            'currency' => 'sometimes|string|size:3',
            'fiscal_year_start' => 'sometimes|date',
        ]);

        $tenant->update($validated);

        return $this->success([
            'accounting' => [
                'currency' => $tenant->currency,
                'fiscal_year_start' => $tenant->fiscal_year_start?->format('Y-m-d'),
            ],
        ], 'Accounting settings updated successfully');
    }

    /**
     * Update tax settings
     */
    public function updateTax(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            return $this->error('No organization found.', 404);
        }

        $validated = $request->validate([
            'default_sales_tax_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('tenant_id', $tenant->id)],
            'default_purchase_tax_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('tenant_id', $tenant->id)],
            'prices_include_tax' => 'sometimes|boolean',
            'tax_per_line_item' => 'sometimes|boolean',
        ]);

        // Validate tax rates belong to tenant
        if (isset($validated['default_sales_tax_id'])) {
            $taxRate = TaxRate::where('id', $validated['default_sales_tax_id'])
                ->where('tenant_id', $tenant->id)
                ->first();
            if (! $taxRate) {
                return $this->validationError(['default_sales_tax_id' => ['Invalid tax rate selected.']]);
            }
        }

        if (isset($validated['default_purchase_tax_id'])) {
            $taxRate = TaxRate::where('id', $validated['default_purchase_tax_id'])
                ->where('tenant_id', $tenant->id)
                ->first();
            if (! $taxRate) {
                return $this->validationError(['default_purchase_tax_id' => ['Invalid tax rate selected.']]);
            }
        }

        $tenant->update($validated);
        $tenant->load(['defaultSalesTax', 'defaultPurchaseTax']);

        return $this->success([
            'tax' => [
                'default_sales_tax_id' => $tenant->default_sales_tax_id,
                'default_sales_tax' => $tenant->defaultSalesTax ? new TaxRateResource($tenant->defaultSalesTax) : null,
                'default_purchase_tax_id' => $tenant->default_purchase_tax_id,
                'default_purchase_tax' => $tenant->defaultPurchaseTax ? new TaxRateResource($tenant->defaultPurchaseTax) : null,
                'prices_include_tax' => (bool) $tenant->prices_include_tax,
                'tax_per_line_item' => (bool) $tenant->tax_per_line_item,
            ],
        ], 'Tax settings updated successfully');
    }

    /**
     * Update custom settings
     */
    public function updateCustomSettings(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $tenant) {
            return $this->error('No organization found.', 404);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        // Merge with existing settings
        $currentSettings = $tenant->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated['settings']);

        $tenant->update(['settings' => $newSettings]);

        return $this->success([
            'custom_settings' => $tenant->settings,
        ], 'Custom settings updated successfully');
    }

    /**
     * Get available currencies
     */
    public function currencies(): JsonResponse
    {
        return $this->success(config('mybooks.currencies', []));
    }

    /**
     * Get payment methods
     */
    public function paymentMethods(): JsonResponse
    {
        $methods = [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'check', 'label' => 'Check'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'credit_card', 'label' => 'Credit Card'],
            ['value' => 'debit_card', 'label' => 'Debit Card'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'online', 'label' => 'Online Payment'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return $this->success($methods);
    }
}
