<?php

namespace App\Models;

use App\Enums\CriticalPositionCriticality;
use App\Enums\CriticalPositionStatus;
use Database\Factories\CriticalPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'position',
    'level',
    'department',
    'directorate',
    'criticality',
    'incumbent',
    'sap',
    'status',
])]
class CriticalPosition extends Model
{
    /** @use HasFactory<CriticalPositionFactory> */
    use HasFactory;

    protected $casts = [
        'criticality' => CriticalPositionCriticality::class,
        'status' => CriticalPositionStatus::class,
    ];
}
