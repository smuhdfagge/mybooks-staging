<?php

namespace App\Traits;

use App\Services\Accounting\PostingLock;
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

        if ($reason = PostingLock::reasonFor($date, (int) $tenantId)) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => [$reason],
            ]);
        }
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
        if ($originalDate && ($reason = PostingLock::reasonFor($originalDate, (int) $tenantId))) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => ['This record is dated in a closed or locked period and cannot be changed. '.$reason],
            ]);
        }

        // Check new date
        $newDate = $this->getTransactionDate();
        if ($reason = PostingLock::reasonFor($newDate, (int) $tenantId)) {
            throw ValidationException::withMessages([
                $this->getPeriodDateField() => [$reason],
            ]);
        }
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

        if ($reason = PostingLock::reasonFor($date, (int) $tenantId)) {
            throw ValidationException::withMessages([
                'period' => ['This record is dated in a closed or locked period and cannot be deleted. '.$reason],
            ]);
        }
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
