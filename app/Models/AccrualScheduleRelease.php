<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One month released from an accrual schedule, with its journal. */
class AccrualScheduleRelease extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'accrual_schedule_id', 'sequence', 'due_date', 'posted_date', 'amount', 'journal_id', 'note',
    ];

    protected $casts = [
        'due_date' => 'date',
        'posted_date' => 'date',
        'amount' => 'decimal:2',
        'sequence' => 'integer',
    ];

    /** @return BelongsTo<AccrualSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AccrualSchedule::class, 'accrual_schedule_id');
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
