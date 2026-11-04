<?php

namespace App\Services\Employees;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeDeletionService
{
    /** @param array<int, int> $employeeIds */
    public function delete(array $employeeIds): int
    {
        return DB::transaction(function () use ($employeeIds): int {
            $employees = Employee::query()
                ->whereIn('id', $employeeIds)
                ->withCount(['mutations', 'promotions', 'demotions', 'wlaParticipations'])
                ->lockForUpdate()
                ->get();

            foreach ($employees as $employee) {
                $blockingRelations = collect([
                    'mutasi' => $employee->mutations_count,
                    'promosi' => $employee->promotions_count,
                    'demosi' => $employee->demotions_count,
                    'WLA' => $employee->wla_participations_count,
                ])->filter(fn (int $count): bool => $count > 0)->keys();

                if ($blockingRelations->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'employees' => "Pegawai {$employee->sap} tidak dapat dihapus karena masih memiliki relasi: {$blockingRelations->join(', ')}.",
                    ]);
                }
            }

            return Employee::query()->whereKey($employees->modelKeys())->delete();
        }, attempts: 3);
    }
}
