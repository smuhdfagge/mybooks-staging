<?php

namespace App\Http\Controllers;

use App\Models\EInvoiceSetting;
use App\Services\EInvoicing\EInvoiceDrivers;
use App\Services\EInvoicing\NotSetUp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings > E-invoicing (session 18): switch it on, sandbox or live, the
 * business's NRS keys, manual or automatic submission, and a "Test
 * connection" button. Keys are kept encrypted and only shown masked; a blank
 * key field on save keeps the saved one.
 */
class EInvoicingSettingsController extends Controller
{
    public function __construct(private EInvoiceDrivers $drivers) {}

    public function show(Request $request)
    {
        $settings = EInvoiceSetting::forTenant($request->user()->tenant_id);
        $state = $this->drivers->state($settings);

        return view('settings.e-invoicing.show', [
            'settings' => $settings,
            'state' => $state,
            'canManage' => (bool) $request->user()->can('manage e-invoicing'),
            'tin' => $request->user()->tenant->tax_number,
            'addressSet' => filled(config('mybooks.einvoicing.base_urls.'.($settings->isLiveEnvironment() ? 'live' : 'sandbox'))),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'environment' => ['required', Rule::in(EInvoiceSetting::ENVIRONMENTS)],
            'submit_mode' => ['required', Rule::in(EInvoiceSetting::MODES)],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'service_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'business_id' => ['nullable', 'string', 'max:100'],
            'public_key' => ['nullable', 'string', 'max:10000'],
            'certificate' => ['nullable', 'string', 'max:20000'],
            'clear_keys' => ['boolean'],
        ], [
            'service_id.regex' => 'The Service ID is 8 letters or digits, as shown on your NRS dashboard.',
        ]);

        $settings = EInvoiceSetting::forTenant($request->user()->tenant_id);
        $data = [
            'enabled' => $request->boolean('enabled'),
            'environment' => $validated['environment'],
            'submit_mode' => $validated['submit_mode'],
        ];

        if ($request->boolean('clear_keys')) {
            $data += array_fill_keys(EInvoiceSetting::SECRETS, null);
        } else {
            foreach (EInvoiceSetting::SECRETS as $field) {
                $value = trim((string) ($validated[$field] ?? ''));
                if ($value !== '') {
                    $data[$field] = $value;
                }
            }
        }

        // Different keys or another environment: the last test no longer applies.
        $keysChanged = collect(EInvoiceSetting::SECRETS)->contains(fn ($f) => array_key_exists($f, $data)) || $data['environment'] !== $settings->environment;
        if ($keysChanged) {
            $data += ['last_tested_at' => null, 'last_test_ok' => null, 'last_test_message' => null];
        }

        $settings->update($data);

        return redirect()->route('settings.e-invoicing')->with('success', 'E-invoicing settings saved.');
    }

    public function test(Request $request)
    {
        $settings = EInvoiceSetting::forTenant($request->user()->tenant_id);

        try {
            $result = $this->drivers->for($settings)->test($settings);
        } catch (NotSetUp $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $settings->update([
            'last_tested_at' => now(),
            'last_test_ok' => $result['ok'],
            'last_test_message' => mb_substr($result['message'], 0, 250),
        ]);

        return redirect()->route('settings.e-invoicing')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
