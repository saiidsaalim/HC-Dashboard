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

            $this->ensureEditable($lockedPersonnelAction);
            $lockedPersonnelAction->fill($attributes);
            $changed = $lockedPersonnelAction->isDirty();
            $lockedPersonnelAction->save();

            return $changed;
        });
    }

    /** @param Builder<EmployeeMutation|EmployeePromotion|EmployeeDemotion> $query */
    public function deleteMany(Builder $query): int
    {
        return DB::transaction(function () use ($query): int {
            $personnelActions = (clone $query)->lockForUpdate()->get();

            foreach ($personnelActions as $personnelAction) {
                $this->ensureDeletable($personnelAction);
            }

            return $query->delete();
        });
    }

    private function ensureEditable(
        EmployeeMutation|EmployeePromotion|EmployeeDemotion $personnelAction,
    ): void {
        $editable = $personnelAction instanceof EmployeePromotion
            ? $personnelAction->isEditable()
            : $personnelAction->verification_status === 'Returned'
                || ($personnelAction->verification_status === 'Draft' && ! $personnelAction->approvals()->exists());

        if (! $editable) {
            throw ValidationException::withMessages(['status' => 'Pengajuan pada status ini tidak dapat diubah.']);
        }
    }

    private function ensureDeletable(
        EmployeeMutation|EmployeePromotion|EmployeeDemotion $personnelAction,
    ): void {
        $deletable = $personnelAction instanceof EmployeePromotion
            ? $personnelAction->isDeletable()
            : $personnelAction->verification_status === 'Draft' && ! $personnelAction->approvals()->exists();

        if (! $deletable) {
            throw ValidationException::withMessages([
                'status' => 'Hanya pengajuan Draft yang belum pernah dikirim yang dapat dihapus.',
            ]);
        }
    }
}
