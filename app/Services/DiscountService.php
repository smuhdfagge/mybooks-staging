<?php

namespace App\Services;

use App\Models\DiscountRule;

class DiscountService
{
    /**
     * Calculate applicable discounts for a set of line items.
     *
     * @param int        $tenantId
     * @param int|null   $customerId
     * @param array      $lineItems  Each item: ['item_id' => ?, 'quantity' => ?, 'amount' => ?]
     * @param float      $orderTotal
     * @return array     ['line_discounts' => [...], 'order_discount' => float, 'total_discount' => float]
     */
    public function calculateDiscounts(int $tenantId, ?int $customerId, array $lineItems, float $orderTotal): array
    {
        $lineDiscounts = [];
        $lineDiscountTotal = 0;

        // Line-item scoped discounts
        foreach ($lineItems as $index => $line) {
            $rule = DiscountRule::findBestDiscount(
                $tenantId,
                $customerId,
                $line['item_id'] ?? null,
                $line['amount'] ?? 0,
                $line['quantity'] ?? 1
            );

            if ($rule && in_array($rule->scope, [DiscountRule::SCOPE_LINE_ITEM, DiscountRule::SCOPE_ITEM, DiscountRule::SCOPE_CUSTOMER])) {
                $discountAmount = $rule->calculate($line['amount'] ?? 0, $line['quantity'] ?? 1);
                $lineDiscounts[$index] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'discount_amount' => $discountAmount,
                    'type' => $rule->type,
                    'value' => $rule->value,
                ];
                $lineDiscountTotal += $discountAmount;
            } else {
                $lineDiscounts[$index] = null;
            }
        }

        // Order-level discounts
        $orderDiscount = 0;
        $orderRules = DiscountRule::where('tenant_id', $tenantId)
            ->where('scope', DiscountRule::SCOPE_ORDER)
            ->applicable()
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($orderRules as $rule) {
            // Check customer-specific order discounts
            if ($rule->customer_id && $rule->customer_id !== $customerId) {
                continue;
            }

            $amount = $rule->calculate($orderTotal, 1);
            if ($amount > $orderDiscount) {
                $orderDiscount = $amount;
            }
        }

        return [
            'line_discounts' => $lineDiscounts,
            'order_discount' => $orderDiscount,
            'total_discount' => $lineDiscountTotal + $orderDiscount,
        ];
    }

    /**
     * Get all active discount rules for a tenant, optionally filtered.
     */
    public function getActiveRules(int $tenantId, ?string $scope = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = DiscountRule::where('tenant_id', $tenantId)->applicable();

        if ($scope) {
            $query->where('scope', $scope);
        }

        return $query->orderBy('priority', 'desc')->get();
    }
}
