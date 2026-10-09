<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a customer, from the web form or the API (finding Q5).
 * The rules already matched; the API also took is_active, so both do now.
 */
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create customers');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            // Nigerian mobiles are saved as +234... (session 16); other
            // numbers (landlines, other countries with +) as typed.
            'phone' => ['nullable', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! PhoneNumber::looksValid((string) $value)) {
                    $fail('Enter a phone number like 0803 123 4567, or start with + and the country code for other countries.');
                }
            }],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            // Withholding tax: payee type sets the rate and the authority (NRS or state IRS).
            'payee_type' => ['sometimes', Rule::in(['company', 'individual'])],
            'wht_category_id' => ['nullable', Rule::exists('wht_categories', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'wht_exempt' => ['boolean'],
            // Customer asked not to get SMS / WhatsApp messages (session 16).
            'sms_opt_out' => ['boolean'],
            'whatsapp_opt_out' => ['boolean'],
        ];
    }

    /** validated() with the phone number as saved. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        if (! empty($data['phone']) && ($phone = PhoneNumber::normalise((string) $data['phone']))) {
            $data['phone'] = $phone;
        }

        return data_get($data, $key, $default);
    }
}
