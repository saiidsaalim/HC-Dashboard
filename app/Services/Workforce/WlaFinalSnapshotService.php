<?php

namespace App\Services\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Models\WlaAssessment;
use Illuminate\Validation\ValidationException;

class WlaFinalSnapshotService
{
    /** @return array<string, mixed> */
    public function validatedSnapshot(WlaAssessment $assessment): array
    {
        if ($assessment->status !== WlaAssessmentStatus::Final) {
            throw ValidationException::withMessages([
                'wla_export' => 'Hanya WLA Final yang dapat dicetak atau diekspor.',
            ]);
        }

        $snapshot = $assessment->final_snapshot;

        if (! is_array($snapshot)) {
            throw ValidationException::withMessages([
                'wla_export' => 'Snapshot WLA Final tidak tersedia atau rusak.',
            ]);
        }

        $requiredPaths = [
            'assessment_code',
            'period',
            'department.id',
            'department.code',
            'department.name',
            'unit.id',
            'unit.code',
            'unit.name',
            'position.id',
            'position.code',
            'position.name',
            'schedule.id',
            'schedule.code',
            'schedule.name',
            'schedule.calculation_type',
            'schedule.calculation_type_label',
            'schedule.wla_hours_per_day',
            'calendar.id',
            'calendar.year',
            'calendar.total_days',
            'calendar.annual_leave',
            'calendar.national_holiday',
            'calendar.common_leave',
            'calendar.saturday_days',
            'calendar.sunday_days',
            'efficiency_factor',
            'working_days',
            'annual_working_hours',
            'effective_annual_working_hours',
            'activities',
            'total_annual_workload',
            'fte',
            'recommended_employees',
            'finalizer.id',
            'finalizer.name',
            'finalized_at',
        ];

        if (collect($requiredPaths)->contains(
            fn (string $path): bool => data_get($snapshot, $path) === null,
        )) {
            throw ValidationException::withMessages([
                'wla_export' => 'Struktur snapshot WLA Final tidak lengkap.',
            ]);
        }

        if (! is_array($snapshot['activities']) || $snapshot['activities'] === []) {
            throw ValidationException::withMessages([
                'wla_export' => 'Snapshot WLA Final tidak mempunyai aktivitas yang valid.',
            ]);
        }

        foreach ($snapshot['activities'] as $activity) {
            if (! is_array($activity)
                || collect([
                    'activity_name',
                    'frequency',
                    'period_unit',
                    'time_allocated_hours',
                    'annual_workload_hours',
                    'sort_order',
                ])->contains(fn (string $key): bool => ! array_key_exists($key, $activity)
                    || $activity[$key] === null)) {
                throw ValidationException::withMessages([
                    'wla_export' => 'Struktur aktivitas pada snapshot WLA Final tidak lengkap.',
                ]);
            }
        }

        return $snapshot;
    }
}
