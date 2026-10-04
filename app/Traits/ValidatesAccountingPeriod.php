<?php

namespace App\Traits;

use App\Models\AccountingPeriod;
use App\Services\Accounting\LockDates;
use Illuminate\Validation\ValidationException;

trait ValidatesAccountingPeriod
{
    /**
     * Whether period validation is temporarily disabled.
     * Protected to prevent unauthorized bypass — use withoutPeriodValidation() method.
     */
    protected bool $skipPeriodValidation = false;

    /**
     * Boot the trait
     */
    public static function bootValidatesAccountingPeriod()
    {
        // Validate on creating
        static::creating(function ($model) {
            $model->validateAccountingPeriod();
        });

        // Validate on updating
        static::updating(function ($model) {
            $model->validateAccountingPeriodOnUpdate();
        });

        // Validate on deleting
        static::deleting(function ($model) {
            $model->validateAccountingPeriodOnDelete();
        });
    }

    /**
     * Get the date field to check for period validation
     */
    protected function getPeriodDateField(): string
    {
        // Override this in models if the date field is different
        $possibleFields = ['invoice_date', 'bill_date', 'expense_date', 'payment_date', 'journal_date', 'transaction_date', 'date'];

        foreach ($possibleFields as $field) {
            if (isset($this->attributes[$field])) {
                return $field;
            }
        }

        return 'created_at';
    }

    /**
     * Get the transaction date for period validation
     */
    protected function getTransactionDate()
    {
        $field = $this->getPeriodDateField();

        return $this->attributes[$field] ?? $this->{$field} ?? now();
    }

    /**
     * Validate that the transaction date is not in a closed period
     */
    protected function validateAccountingPeriod(): void
    {
        if (! $this->shouldValidatePeriod()) {
            return;
        }

        $date = $this->getTransactionDate();
        $tenantId = $this->tenant_id ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            return;
        }

        if (AccountingPeriod::isDateInClosedPeriod($date, $tenantId)) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => [AccountingPeriod::getClosedPeriodMessage($date, $tenantId)],
            ]);
        }

        $this->validateLockDate($date, $tenantId, $this->getPeriodDateField());
    }

    /**
     * Validate on update - check both original and new dates
     */
    protected function validateAccountingPeriodOnUpdate(): void
    {
        if (! $this->shouldValidatePeriod()) {
            return;
        }

        $tenantId = $this->tenant_id ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            return;
        }

        // Check original date (in case record was in a now-closed period)
        $originalDate = $this->getOriginal($this->getPeriodDateField());
        if ($originalDate && AccountingPeriod::isDateInClosedPeriod($originalDate, $tenantId)) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => ['This record belongs to a closed accounting period and cannot be modified.'],
            ]);
        }
        $this->validateLockDate($originalDate, $tenantId, $this->getPeriodDateField());

        // Check new date
        $newDate = $this->getTransactionDate();
        if (AccountingPeriod::isDateInClosedPeriod($newDate, $tenantId)) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => [AccountingPeriod::getClosedPeriodMessage($newDate, $tenantId)],
            ]);
        }
        $this->validateLockDate($newDate, $tenantId, $this->getPeriodDateField());
    }

    /**
     * Validate on delete - ensure record is not in a closed period
     */
    protected function validateAccountingPeriodOnDelete(): void
    {
        if (! $this->shouldValidatePeriod()) {
            return;
        }

        $date = $this->getTransactionDate();
        $tenantId = $this->tenant_id ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            return;
        }

        if (AccountingPeriod::isDateInClosedPeriod($date, $tenantId)) {
            throw ValidationException::withMessages([
                'period' => ['This record belongs to a closed accounting period and cannot be deleted.'],
            ]);
        }

        $this->validateLockDate($date, $tenantId, 'period');
    }

    /**
     * Lock dates (session 11): the staff lock unless the signed-in user may
     * override it, and the all-users lock for everyone. Not for records with
     * no date of their own (the created_at fallback, e.g. purchase orders).
     */
    protected function validateLockDate(mixed $date, int|string $tenantId, string $key): void
    {
        if (! $date || $this->getPeriodDateField() === 'created_at') {
            return;
        }

        if ($reason = LockDates::instance()->lockReason($date, (int) $tenantId, auth()->user())) {
            throw ValidationException::withMessages([$key => [$reason]]);
        }
    }

    /**
     * The same check as saving with events on, for actions that save with
     * events off (SaveInvoice, SaveBill, SaveSalesReceipt): call it after
     * fill(), before the save. Those saves skipped the check entirely, so a
     * document in a closed period could be moved out of it or changed.
     */
    public function assertPeriodAllowsSave(): void
    {
        $this->exists ? $this->validateAccountingPeriodOnUpdate() : $this->validateAccountingPeriod();
    }

    /**
     * Determine if period validation should be performed
     * Override this method to conditionally skip validation
     */
    protected function shouldValidatePeriod(): bool
    {
        if ($this->skipPeriodValidation) {
            return false;
        }

        return true;
    }

    /**
     * Temporarily disable period validation
     */
    public function withoutPeriodValidation(): self
    {
        $this->skipPeriodValidation = true;

        return $this;
    }

    /**
     * Re-enable period validation
     */
    public function withPeriodValidation(): self
    {
        $this->skipPeriodValidation = false;

        return $this;
    }
}
