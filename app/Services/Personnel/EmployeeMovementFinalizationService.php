<?php

namespace App\Services\Personnel;

use App\Enums\PromotionStatus;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Services\Employees\EmployeeOrganizationSyncService;
use Illuminate\Validation\ValidationException;

class EmployeeMovementFinalizationService
{
    public function __construct(
        private readonly OrganizationHierarchyService $organizationHierarchy,
        private readonly EmployeeOrganizationSyncService $organizationSyncService,
    ) {}

    public function apply(
        EmployeePromotion|EmployeeDemotion|EmployeeMutation $movement,
    ): void {
        if ($movement->verification_status !== PromotionStatus::Approved->value) {
            throw ValidationException::withMessages([
                'status' => 'Hanya perubahan berstatus Approved yang dapat diterapkan.',
            ]);
        }

        if ($movement->employee_applied_at !== null) {
            return;
        }

        $employee = Employee::query()->whereKey($movement->employee_id)->lockForUpdate()->first();
        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => 'Data Pegawai untuk usulan ini tidak lagi tersedia.',
            ]);
        }

        $oldOrganization = [
            'directorate_id' => $movement->old_directorate_id,
            'department_id' => $movement->old_department_id,
            'unit_id' => $movement->old_unit_id,
            'section_id' => $movement->old_section_id,
            'position_id' => $movement->old_position_id,
        ];

        foreach ($oldOrganization as $field => $expectedId) {
            if ($this->normalizedId($employee->{$field}) !== $this->normalizedId($expectedId)) {
                throw ValidationException::withMessages([
                    'employee' => 'Data Pegawai sudah berubah sejak usulan dibuat. Perbarui atau buat ulang usulan berdasarkan kondisi terbaru.',
                ]);
            }
        }

        if ($employee->employee_subgroup !== $movement->level_lama) {
            throw ValidationException::withMessages([
                'employee' => 'Level Data Pegawai sudah berubah sejak usulan dibuat. Perbarui atau buat ulang usulan berdasarkan kondisi terbaru.',
            ]);
        }

        $target = $this->organizationHierarchy->validatedTarget($movement->only([
            'new_directorate_id', 'new_department_id', 'new_unit_id', 'new_section_id', 'new_position_id',
        ]));
        $oldPositionId = $employee->position_id;

        $employee->update([
            'directorate_id' => $target['directorate']->id,
            'department_id' => $target['department']->id,
            'unit_id' => $target['unit']->id,
            'section_id' => $target['section']?->id,
            'position_id' => $target['position']->id,
            'txt_dir' => $target['directorate']->name,
            'directorate' => $target['directorate']->name,
            'txt_dept' => $target['department']->name,
            'department' => $target['department']->name,
            'txt_biro' => $target['unit']->name,
            'bureau' => $target['unit']->name,
            'organizational_unit' => $target['unit']->name,
            'txt_sect' => $target['section']?->name,
            'section' => $target['section']?->name,
            'position' => $target['position']->name,
            'employee_subgroup' => $movement->level_baru,
        ]);

        $this->organizationSyncService->synchronizePositionMetadata([$oldPositionId, $employee->position_id]);
        $movement->update([
            'employee_applied_at' => now(),
            'verification_status' => 'Final',
        ]);
    }

    public function applyIfDue(
        EmployeePromotion|EmployeeDemotion|EmployeeMutation $movement,
    ): bool {
        if ($movement->employee_applied_at !== null) {
            return false;
        }

        if ($movement->verification_status !== PromotionStatus::Approved->value
            || $movement->finalized_at === null
            || $movement->tmt->isFuture()) {
            return false;
        }

        $this->apply($movement);

        return true;
    }

    private function normalizedId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
