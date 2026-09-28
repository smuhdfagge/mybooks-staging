<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use App\Models\ActivityLog;

class Journal extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, LogsActivity, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'journal_number',
        'journal_date',
        'reference',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'is_posted',
        'posted_at',
        'reference_type',
        'reference_id',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posted_at' => 'datetime',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    public function entries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public static function generateNumber($tenantId)
    {
        $lastJournal = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
        
        $number = $lastJournal ? intval(substr($lastJournal->journal_number, 3)) + 1 : 1;
        return 'JE-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function isBalanced(): bool
    {
        // Check the lines themselves. total_debit/total_credit are only
        // filled in by updateTotals(), so a draft journal read 0 = 0 and
        // counted as balanced whatever its lines said.
        $debit = round((float) $this->entries()->sum('debit'), 2);
        $credit = round((float) $this->entries()->sum('credit'), 2);

        return abs($debit - $credit) < 0.005;
    }

    public function updateTotals()
    {
        $this->total_debit = $this->entries()->sum('debit');
        $this->total_credit = $this->entries()->sum('credit');
        $this->withoutPeriodValidation()->save();
    }

    public function post()
    {
        if (! $this->entries()->exists()) {
            throw new \Exception('Journal has no lines to post.');
        }

        if (!$this->isBalanced()) {
            throw new \Exception('Journal entries must be balanced before posting.');
        }

        foreach ($this->entries as $entry) {
            $account = $entry->account;
            if ($account->isDebitBalance()) {
                $delta = (float) ($entry->debit - $entry->credit);
            } else {
                $delta = (float) ($entry->credit - $entry->debit);
            }
            ChartOfAccount::where('id', $account->id)
                ->update(['current_balance' => \DB::raw('current_balance + (' . (float) $delta . ')')]);
        }

        $this->is_posted = true;
        $this->posted_at = now();
        $this->status = 'posted';
        $this->withoutPeriodValidation()->save();

        // Log the posting activity
        $this->logCustomActivity(ActivityLog::ACTION_POSTED, "Journal '{$this->journal_number}' was posted");
    }
}
