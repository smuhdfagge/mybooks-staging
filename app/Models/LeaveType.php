<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class LeaveType extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'days_per_year',
        'is_paid',
        'is_carry_forward',
        'max_carry_forward_days',
        'description',
        'is_active',
    ];

    protected $casts = [
        'days_per_year' => 'integer',
        'is_paid' => 'boolean',
        'is_carry_forward' => 'boolean',
        'max_carry_forward_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
