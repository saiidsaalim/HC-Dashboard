<?php

namespace App\Services\Personnel;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonnelActionGuard
{
    /** @param array<string, mixed> $attributes */
    public function update(
        EmployeeMutation|EmployeePromotion|EmployeeDemotion $personnelAction,
        array $attributes,
    ): bool {
        return DB::transaction(function () use ($personnelAction, $attributes): bool {
            $lockedPersonnelAction = $personnelAction->newQuery()
                ->lockForUpdate()
                ->findOrFail($personnelAction->getKey());

            $this->ensureNotFinal($lockedPersonnelAction);
            $lockedPersonnelAction->fill($attributes);
            $materialChanged = $lockedPersonnelAction->isDirty([
                'sap',
                'old_directorate_id', 'old_department_id', 'old_unit_id', 'old_section_id', 'old_position_id',
                'new_directorate_id', 'new_department_id', 'new_unit_id', 'new_section_id', 'new_position_id',
                'tmt', 'pg', 'level_lama', 'jg_lama', 'level_baru', 'jg_baru',
            ]);
            $approvalsReset = $materialChanged && $lockedPersonnelAction->approvals()->exists();

            if ($approvalsReset) {
                $lockedPersonnelAction->approvals()->delete();
                $lockedPersonnelAction->forceFill([
                    'verification_status' => 'Pending',
                    'finalized_at' => null,
                    'employee_applied_at' => null,
                ]);
            }

            $lockedPersonnelAction->save();

            return $approvalsReset;
        });
    }

    /** @param Builder<EmployeeMutation|EmployeePromotion|EmployeeDemotion> $query */
    public function deleteMany(Builder $query): int
    {
        return DB::transaction(function () use ($query): int {
            $personnelActions = (clone $query)->lockForUpdate()->get();

            foreach ($personnelActions as $personnelAction) {
                $this->ensureNotFinal($personnelAction);
            }

            return $query->delete();
        });
    }

    private function ensureNotFinal(
        EmployeeMutation|EmployeePromotion|EmployeeDemotion $personnelAction,
    ): void {
        if ($personnelAction->verification_status === 'Final') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan yang sudah Final tidak dapat diubah atau dihapus.',
            ]);
        }
    }
}
