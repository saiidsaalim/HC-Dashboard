<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year', 'total_days', 'total_weeks', 'annual_leave', 'national_holiday', 'common_leave', 'saturday_days', 'sunday_days', 'notes', 'active'])]
class WorkCalendar extends Model
{
    protected $casts = [
        'active' => 'boolean',
        'year' => 'integer',
        'total_days' => 'integer',
        'total_weeks' => 'integer',
        'annual_leave' => 'integer',
        'national_holiday' => 'integer',
        'common_leave' => 'integer',
        'saturday_days' => 'integer',
        'sunday_days' => 'integer',
    ];
}
