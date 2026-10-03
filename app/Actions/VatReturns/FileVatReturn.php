<?php

namespace App\Actions\VatReturns;

use App\Models\VatReturnFiling;
use App\Services\Accounting\VatReturn;
use App\Services\Accounting\VatReturnForm;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Marking a month's VAT return as filed, in one transaction:
 * - the return is built with the hand-entered lines (65 imports, 85 VAT
 *   deducted at source, 90 automatic VAT payments) and line 100 from the
 *   last filed return;
 * - the month's output and input VAT are settled into VAT Payable
 *   (VatReturn::settle, dated the last day of the month), unless that was
 *   already done from the VAT/GST report, or there is no VAT;
 * - the lines as filed are kept, so later months can carry the credit
 *   forward and the filed figures can be compared with the ledger.
 *
 * A filed month can't be filed again.
 */
class FileVatReturn
{
    public function __construct(protected VatReturnForm $form, protected VatReturn $ledger) {}

    /**
     * @param  array{imports?: float|int|string|null, import_vat?: float|int|string|null, vat_withheld?: float|int|string|null, auto_vat_paid?: float|int|string|null}  $manual
     */
    public function handle(int $tenantId, string $month, array $manual, ?int $userId = null, ?string $reference = null): VatReturnFiling
    {
        if (VatReturnFiling::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('month', $month)->exists()) {
            throw ValidationException::withMessages(['month' => 'The VAT return for this month has already been filed.']);
        }
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        if ($start->copy()->endOfMonth()->isFuture()) {
            throw ValidationException::withMessages(['month' => 'A month can only be filed once it has ended.']);
        }

        $manual = array_map(fn ($v) => Money::round(max(0, (float) $v)), [
            'imports' => $manual['imports'] ?? 0,
            'import_vat' => $manual['import_vat'] ?? 0,
            'vat_withheld' => $manual['vat_withheld'] ?? 0,
            'auto_vat_paid' => $manual['auto_vat_paid'] ?? 0,
        ]);

        return DB::transaction(function () use ($tenantId, $month, $manual, $start, $userId, $reference) {
            $from = $start->toDateString();
            $to = $start->copy()->endOfMonth()->toDateString();

            $journal = $this->ledger->settlementFor($tenantId, $from, $to);
            if (! $journal) {
                try {
                    $journal = $this->ledger->settle($tenantId, $from, $to);
                } catch (RuntimeException) {
                    $journal = null; // no VAT in the month: a nil return
                }
            }

            $broughtForward = VatReturnFiling::creditBroughtForward($tenantId, $month);
            $return = $this->form->build($tenantId, $month, $manual, $broughtForward);
            $L = $return['lines'];

            $filing = new VatReturnFiling([
                'month' => $month,
                'credit_brought_forward' => $broughtForward,
                'output_vat' => $L[45],
                'input_vat' => $L[75],
                'vat_payable' => $L[120],
                'credit_carried_forward' => $L[115],
                'lines' => array_map(fn ($v) => (float) $v, $L),
                'settlement_journal_id' => $journal?->id,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
                'filed_at' => now(),
                'filed_by' => $userId,
            ] + $manual);
            $filing->tenant_id = $tenantId;
            $filing->save();

            return $filing;
        });
    }
}
