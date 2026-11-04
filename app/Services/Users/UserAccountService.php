<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\DemotionApproval;
use App\Models\DemotionApprovalConfig;
use App\Models\MutationApproval;
use App\Models\MutationApprovalConfig;
use App\Models\PromotionApproval;
use App\Models\PromotionApprovalConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->lockAndProtectLastSuperAdmin($lockedUser, null);
            $this->ensureNotRequiredByWorkflow($lockedUser);
            $lockedUser->delete();
        }, attempts: 3);
    }

    public function changeRole(User $user, UserRole $newRole): void
    {
        DB::transaction(function () use ($user, $newRole): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->lockAndProtectLastSuperAdmin($lockedUser, $newRole);

            if (! in_array($newRole, [UserRole::ADMIN, UserRole::MANAGER], true)) {
                $this->ensureNotRequiredByWorkflow($lockedUser);
            }

            $lockedUser->update(['role' => $newRole->value]);
        }, attempts: 3);
    }

    private function lockAndProtectLastSuperAdmin(User $user, ?UserRole $newRole): void
    {
        if ($user->roleEnum() !== UserRole::SUPER_ADMIN
            || $newRole === UserRole::SUPER_ADMIN) {
            return;
        }

        $superAdminCount = User::query()
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        if ($superAdminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Super Admin aktif terakhir tidak dapat dihapus atau diturunkan rolenya.',
            ]);
        }
    }

    private function ensureNotRequiredByWorkflow(User $user): void
    {
        $configurations = [
            ['model' => MutationApprovalConfig::class, 'module' => 'Mutasi'],
            ['model' => PromotionApprovalConfig::class, 'module' => 'Promosi'],
            ['model' => DemotionApprovalConfig::class, 'module' => 'Demosi'],
        ];

        foreach ($configurations as $configuration) {
            $configurationClass = $configuration['model'];
            $configuredStep = $configurationClass::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->value('step_order');

            if ($configuredStep !== null) {
                throw ValidationException::withMessages([
                    'user' => "Pengguna masih menjadi approver konfigurasi {$configuration['module']} pada langkah {$configuredStep}. Pindahkan konfigurasi terlebih dahulu.",
                ]);
            }
        }

        $activeApprovals = [
            [
                'model' => MutationApproval::class,
                'movement_table' => 'employee_mutations',
                'foreign_key' => 'employee_mutation_id',
                'module' => 'Mutasi',
                'status' => 'Pending',
            ],
            [
                'model' => PromotionApproval::class,
                'movement_table' => 'employee_promotions',
                'foreign_key' => 'employee_promotion_id',
                'module' => 'Promosi',
                'status' => 'Waiting Approval',
            ],
            [
                'model' => DemotionApproval::class,
                'movement_table' => 'employee_demotions',
                'foreign_key' => 'employee_demotion_id',
                'module' => 'Demosi',
                'status' => 'Pending',
            ],
        ];

        foreach ($activeApprovals as $approvalDefinition) {
            $approvalClass = $approvalDefinition['model'];
            $approvalTable = (new $approvalClass)->getTable();
            $approval = $approvalClass::query()
                ->join(
                    $approvalDefinition['movement_table'],
                    $approvalDefinition['movement_table'].'.id',
                    '=',
                    $approvalTable.'.'.$approvalDefinition['foreign_key'],
                )
                ->where($approvalTable.'.manager_id', $user->id)
                ->where($approvalTable.'.status', 'Pending')
                ->where($approvalDefinition['movement_table'].'.verification_status', $approvalDefinition['status'])
                ->whereColumn(
                    $approvalTable.'.approval_round',
                    $approvalDefinition['movement_table'].'.approval_round',
                )
                ->whereColumn(
                    $approvalTable.'.step_order',
                    $approvalDefinition['movement_table'].'.current_approval_step',
                )
                ->lockForUpdate()
                ->first([$approvalTable.'.step_order']);

            if ($approval !== null) {
                throw ValidationException::withMessages([
                    'user' => "Pengguna masih menjadi approver aktif {$approvalDefinition['module']} pada langkah {$approval->step_order}. Selesaikan atau pindahkan workflow terlebih dahulu.",
                ]);
            }
        }
    }
}
