<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

/**
 * Recording an expense as a draft, from the expense form and from a bank
 * feed line (session 17). Nothing is posted: bank balance and journal only
 * change when the expense is approved and marked as paid.
 */
class CreateExpense
{
    /**
     * $data keys: name, expense_date, expense_account_id, amount, tax_amount,
     * vendor_id, paid_through_id, bank_id, reference, description,
     * is_billable, customer_id, payment_method, notes.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(int $tenantId, array $data, ?int $userId = null): Expense
    {
        $amount = $data['amount'];
        $taxAmount = $data['tax_amount'] ?? 0;

        return Expense::create([
            'tenant_id' => $tenantId,
            'expense_number' => Expense::generateNumber($tenantId),
            'name' => $data['name'],
            'expense_date' => $data['expense_date'],
            'expense_account_id' => $data['expense_account_id'],
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total' => $amount + $taxAmount,
            'vendor_id' => $data['vendor_id'] ?? null,
            'paid_through_id' => $data['paid_through_id'] ?? null,
            'bank_id' => $data['bank_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'is_billable' => $data['is_billable'] ?? false,
            'customer_id' => $data['customer_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'status' => Expense::STATUS_DRAFT, // always starts as a draft
        ]);
    }
}
