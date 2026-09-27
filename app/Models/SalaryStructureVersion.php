<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructureVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'salary_structure_id',
        'version',
        'name',
        'basic_salary',
        'effective_from',
        'effective_to',
        'items',
        'change_reason',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'items' => 'array',
        'created_at' => 'datetime',
    ];

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
