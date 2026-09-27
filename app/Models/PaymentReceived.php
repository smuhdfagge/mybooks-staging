<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Services\JournalService;

class PaymentReceived extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    protected $table = 'payments_received';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'invoice_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'bank_id',
        'reference',
        'notes',
        'is_deposit',
        'unused_amount',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'unused_amount' => 'decimal:2',
        'is_deposit' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber($tenantId)
    {
        $lastPayment = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastPayment ? intval(substr($lastPayment->payment_number, 4)) + 1 : 1;
        return 'PAY-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * Get all applications of this deposit to invoices
     */
    public function depositApplications()
    {
        return $this->hasMany(CustomerDepositApplication::class, 'deposit_payment_id');
    }

    /**
     * Check if this payment is a deposit
     */
    public function isDeposit(): bool
    {
        return (bool) $this->is_deposit;
    }

    /**
     * Check if this deposit has any unused balance
     */
    public function hasUnusedBalance(): bool
    {
        return $this->is_deposit && $this->unused_amount > 0;
    }

    /**
     * Get the amount that has been applied from this deposit
     */
    public function getAppliedAmountAttribute()
    {
        return $this->amount - $this->unused_amount;
    }

    /**
     * Apply a portion of this deposit to an invoice
     */
    public function applyToInvoice(Invoice $invoice, float $amount, ?string $notes = null): ?CustomerDepositApplication
    {
        if (!$this->is_deposit) {
            throw new \InvalidArgumentException('This payment is not a deposit');
        }

        if ($amount > $this->unused_amount) {
            throw new \InvalidArgumentException('Amount exceeds available deposit balance');
        }

        if ($amount > $invoice->balance_due) {
            throw new \InvalidArgumentException('Amount exceeds invoice balance due');
        }

        return \DB::transaction(function () use ($invoice, $amount, $notes) {
            // Create deposit application record
            $application = CustomerDepositApplication::create([
                'tenant_id' => $this->tenant_id,
                'customer_id' => $this->customer_id,
                'deposit_payment_id' => $this->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'application_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create a payment record for the invoice
            $payment = PaymentReceived::create([
                'tenant_id' => $this->tenant_id,
                'customer_id' => $this->customer_id,
                'invoice_id' => $invoice->id,
                'payment_number' => PaymentReceived::generateNumber($this->tenant_id),
                'payment_date' => now(),
                'amount' => $amount,
                'payment_method' => 'deposit',
                'reference' => "Applied from deposit {$this->payment_number}",
                'notes' => $notes,
                'is_deposit' => false,
                'unused_amount' => 0,
                'created_by' => auth()->id(),
            ]);

            // Update the application with the payment ID
            $application->update(['applied_payment_id' => $payment->id]);

            // Update this deposit's unused amount
            $this->unused_amount -= $amount;
            $this->withoutPeriodValidation()->save();

            // Update customer's deposit balance
            $this->customer->updateDepositBalance();

            return $application;
        });
    }

    /**
     * Create or update journal entry for this payment
     */
    public function createJournalEntry(): ?Journal
    {
        $journalService = app(JournalService::class);
        return $journalService->createPaymentReceivedJournal($this);
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->updateBalances();
            }
            
            // Update customer deposit balance if this is a deposit
            if ($payment->is_deposit) {
                $payment->customer->updateDepositBalance();
            }
            
            // Create journal entry for the payment
            if ($payment->amount > 0) {
                $payment->createJournalEntry();
            }
        });

        static::updated(function ($payment) {
            // Update customer deposit balance if this is a deposit
            if ($payment->is_deposit) {
                $payment->customer->updateDepositBalance();
            }
            
            // Update journal entry when payment is modified
            if ($payment->amount > 0) {
                $payment->createJournalEntry();
            }
        });

        // Use deleting event to ensure journal cleanup happens before payment deletion
        static::deleting(function ($payment) {
            // Delete journal entry and reverse chart of account balances
            $journalService = app(JournalService::class);
            $journalService->deleteJournalForTransaction(PaymentReceived::class, $payment->id, $payment->tenant_id);
        });

        static::deleted(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->updateBalances();
            }
            
            // Update customer deposit balance if this was a deposit
            if ($payment->is_deposit && $payment->customer) {
                $payment->customer->updateDepositBalance();
            }
        });
    }
}
