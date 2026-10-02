<?php

namespace App\Actions\Payments;

use App\Models\WhtRate;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * The withholding tax fields of a payment (tax pack 2), checked the same
 * way for payments made and received, from the web and the API. No WHT
 * given means none: the payment works exactly as before.
 */
class WithholdingTaxOnPayment
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{wht_rate_id: ?int, wht_rate: float, wht_amount: float}
     */
    public static function from(array $data): array
    {
        $amount = Money::round($data['wht_amount'] ?? 0);
        $rate = isset($data['wht_rate_id']) && $data['wht_rate_id'] !== '' ? WhtRate::find($data['wht_rate_id']) : null;

        if ($amount < 0) {
            throw ValidationException::withMessages(['wht_amount' => 'Withholding tax cannot be negative.']);
        }
        if ($amount > 0 && $amount >= Money::round($data['amount'] ?? 0)) {
            throw ValidationException::withMessages(['wht_amount' => 'Withholding tax must be less than the payment amount.']);
        }

        return [
            'wht_rate_id' => $amount > 0 ? $rate?->id : null,
            'wht_rate' => $amount > 0 ? (float) ($data['wht_rate'] ?? 0) : 0.0,
            'wht_amount' => $amount,
        ];
    }
}
