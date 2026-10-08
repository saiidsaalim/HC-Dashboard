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
    ): void {
        DB::transaction(function () use ($personnelAction, $attributes): void {
            $lockedPersonnelAction = $personnelAction->newQuery()
                ->lockForUpdate()
                ->findOrFail($personnelAction->getKey());

            $this->ensureNotFinal($lockedPersonnelAction);
            $lockedPersonnelAction->update($attributes);
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
