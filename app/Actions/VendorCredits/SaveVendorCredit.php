<?php

namespace App\Actions\VendorCredits;

use App\Enums\VendorCreditStatus;
use App\Models\Bill;
use App\Models\VendorCredit;
use App\Models\VendorCreditItem;
use App\Models\Warehouse;
use App\Services\Sales\DocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording a supplier credit (purchase return or price reduction).
 * Lines and VAT are worked out like a bill's. Saved as a draft (posts
 * nothing) or opened straight away (OpenVendorCredit posts it and takes
 * returned goods out of stock).
 *
 * $data keys: vendor_id, bill_id, credit_date, vendor_reference, reason,
 * notes, status (draft|open; default open), items[] (item_id, account_id,
 * description, quantity, unit_price, tax_rate, optional vat_treatment for a
 * line without VAT: zero, exempt or out_of_scope).
 */
class SaveVendorCredit
{
    public function __construct(protected OpenVendorCredit $open) {}

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): VendorCredit
    {
        $status = $data['status'] ?? VendorCreditStatus::Open->value;
        if (! in_array($status, VendorCreditStatus::startValues(), true)) {
            throw ValidationException::withMessages(['status' => 'A new supplier credit can only be a draft or open.']);
        }

        $bill = ! empty($data['bill_id']) ? Bill::find($data['bill_id']) : null;
        if (! empty($data['bill_id']) && (! $bill || (int) $bill->vendor_id !== (int) $data['vendor_id'])) {
            throw ValidationException::withMessages(['bill_id' => "That bill isn't from this supplier."]);
        }
        if ($bill && in_array($bill->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages(['bill_id' => "Bill {$bill->bill_number} is {$bill->status}."]);
        }

        $lines = array_values(array_filter($data['items'] ?? [], fn ($l) => (float) ($l['quantity'] ?? 0) > 0));
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line with a quantity.']);
        }
        $totals = DocumentTotals::calculate($lines, null, 0);
        if ($totals['total'] <= 0) {
            throw ValidationException::withMessages(['items' => 'The credit must be for more than zero.']);
        }

        // Goods go back from the chosen warehouse; empty = the bill's (session 12).
        $warehouseId = empty($data['warehouse_id']) ? null : Warehouse::resolveIdFor($tenantId, $data['warehouse_id'], true);

        return DB::transaction(function () use ($tenantId, $data, $userId, $status, $bill, $totals, $warehouseId) {
            $credit = VendorCredit::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'vendor_id' => $data['vendor_id'],
                'bill_id' => $bill?->id,
                'vendor_credit_number' => VendorCredit::generateNumber($tenantId),
                'vendor_reference' => $data['vendor_reference'] ?? null,
                'credit_date' => $data['credit_date'],
                'status' => VendorCreditStatus::Draft->value,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'balance' => 0,
                'created_by' => $userId,
            ]);

            foreach ($totals['lines'] as $line) {
                VendorCreditItem::create([
                    'vendor_credit_id' => $credit->id,
                    'item_id' => $line['item_id'] ?? null,
                    'account_id' => $line['account_id'] ?? null,
                    'description' => $line['description'] ?? 'Returned goods',
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'vat_treatment' => $line['vat_treatment'] ?? null,
                    'total' => $line['total'],
                ]);
            }

            if ($status === VendorCreditStatus::Open->value) {
                $this->open->handle($credit);
            }

            return $credit->fresh(['items']);
        });
    }
}
