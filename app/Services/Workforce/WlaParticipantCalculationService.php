<?php

namespace App\Services\Workforce;

use App\Enums\WlaPeriodUnit;
use App\Models\WlaAssessment;

class WlaParticipantCalculationService
{
    public function __construct(
        private WlaCalculationService $wlaCalculationService,
        private WorkCalendarService $workCalendarService,
    ) {}

    public function annualWorkload(
        WlaAssessment $assessment,
        string|int|float $frequency,
        string|int|float $timeAllocatedHours,
        WlaPeriodUnit $periodUnit,
    ): string {
        $assessment->loadMissing(['workCalendar', 'workSchedule']);
        $workingDays = $this->workCalendarService->calculateWorkingDays(
            $assessment->period,
            $assessment->workCalendar,
            $assessment->workSchedule,
        );

        return $this->wlaCalculationService->calculateAnnualWorkload(
            $frequency,
            $timeAllocatedHours,
            $periodUnit,
            $workingDays,
        );
    }

    /** @param iterable<int, string|int|float> $annualWorkloads */
    public function subtotal(iterable $annualWorkloads): string
    {
        return $this->wlaCalculationService->sumAnnualWorkloads($annualWorkloads);
    }
}
