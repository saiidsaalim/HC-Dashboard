<?php

namespace App\Services\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WlaMasterService
{
    public function __construct(private WlaCalculationService $wlaCalculationService) {}

    /** @param array<string, mixed> $data */
    public function updateCalendar(WorkCalendar $calendar, array $data): WorkCalendar
    {
        return DB::transaction(function () use ($calendar, $data): WorkCalendar {
            $lockedCalendar = WorkCalendar::query()->lockForUpdate()->findOrFail($calendar->getKey());
            $relatedAssessments = WlaAssessment::query()
                ->where('work_calendar_id', $lockedCalendar->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'period']);

            if ($relatedAssessments->contains(
                fn (WlaAssessment $assessment): bool => $assessment->period !== (int) $data['year'],
            )) {
                throw ValidationException::withMessages([
                    'year' => 'Tahun kalender kerja harus sama dengan periode seluruh WLA yang menggunakannya.',
                ]);
            }

            $lockedCalendar->update($data);
            $this->recalculateDraftAssessments('work_calendar_id', $lockedCalendar->getKey());

            return $lockedCalendar->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateSchedule(WorkSchedule $schedule, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data): WorkSchedule {
            $lockedSchedule = WorkSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $this->lockRelatedAssessments('work_schedule_id', $lockedSchedule->getKey());
            $lockedSchedule->update($data);

            if ($lockedSchedule->calculationTypeForWla() === null
                && $lockedSchedule->wlaAssessments()->exists()) {
                throw ValidationException::withMessages([
                    'calculation_type' => 'Kelompok kalkulasi wajib dipilih karena jadwal masih digunakan oleh WLA.',
                ]);
            }

            $this->recalculateDraftAssessments('work_schedule_id', $lockedSchedule->getKey());

            return $lockedSchedule->refresh();
        });
    }

    public function deleteCalendar(WorkCalendar $calendar): void
    {
        $this->deleteUnusedMaster(
            $calendar,
            'work_calendar_id',
            'Kalender kerja tidak dapat dihapus karena masih digunakan oleh WLA.',
        );
    }

    public function deleteSchedule(WorkSchedule $schedule): void
    {
        $this->deleteUnusedMaster(
            $schedule,
            'work_schedule_id',
            'Jadwal kerja tidak dapat dihapus karena masih digunakan oleh WLA.',
        );
    }

    private function recalculateDraftAssessments(string $foreignKey, int $masterId): void
    {
        $assessments = WlaAssessment::query()
            ->where($foreignKey, $masterId)
            ->where('status', WlaAssessmentStatus::Draft->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($assessments as $assessment) {
            $this->wlaCalculationService->recalculate($assessment);
        }
    }

    private function lockRelatedAssessments(string $foreignKey, int $masterId): void
    {
        WlaAssessment::query()
            ->where($foreignKey, $masterId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    private function deleteUnusedMaster(
        WorkCalendar|WorkSchedule $master,
        string $foreignKey,
        string $message,
    ): void {
        try {
            DB::transaction(function () use ($master, $foreignKey, $message): void {
                $lockedMaster = $master->newQuery()->lockForUpdate()->findOrFail($master->getKey());

                if (WlaAssessment::query()->where($foreignKey, $lockedMaster->getKey())->exists()) {
                    throw ValidationException::withMessages(['master' => $message]);
                }

                $lockedMaster->delete();
            });
        } catch (QueryException $exception) {
            if ($this->isForeignKeyConstraintViolation($exception)
                && WlaAssessment::query()->where($foreignKey, $master->getKey())->exists()) {
                throw ValidationException::withMessages(['master' => $message]);
            }

            throw $exception;
        }
    }

    private function isForeignKeyConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return ($sqlState === '23000' && in_array($driverCode, [19, 1451, 1452], true))
            && str_contains($message, 'foreign key');
    }
}
