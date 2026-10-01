<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOrganizationMappingAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'old_department_id',
        'new_department_id',
        'old_unit_id',
        'new_unit_id',
        'old_position_id',
        'new_position_id',
        'executed_by',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
