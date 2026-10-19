<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FitProperSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['standard' => 'array'];
    }

    public const DEFAULT_STANDARD = ['weight_kpi' => 0.1, 'weight_assessment' => 0.4, 'weight_fp' => 0.5, 'max_kpi' => 5, 'max_assessment' => 4, 'passing_grade' => 75];

    /**
     * @return array{weight_kpi: float, weight_assessment: float, weight_fp: float, max_kpi: float|int, max_assessment: float|int, passing_grade: float|int}
     */
    public static function current(): array
    {
        return static::query()->find(1)?->standard ?? self::DEFAULT_STANDARD;
    }
}
