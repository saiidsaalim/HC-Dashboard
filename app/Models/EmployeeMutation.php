<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sap',
    'nama',
    'departemen_lama',
    'jabatan_lama',
    'departemen_baru',
    'jabatan_baru',
    'tmt',
    'pg',
    'band_lama',
    'jg_lama',
    'band_baru',
    'jg_baru',
    'verification_status',
    'finalized_at',
])]
class EmployeeMutation extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sap', 'sap');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(MutationApproval::class);
    }

    protected function casts(): array
    {
        return [
            'tmt' => 'date',
            'finalized_at' => 'datetime',
        ];
    }
}
