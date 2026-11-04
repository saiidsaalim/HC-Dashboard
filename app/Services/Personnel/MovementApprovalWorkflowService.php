<?php

namespace App\Services\Personnel;

use App\Enums\UserRole;
use App\Models\DemotionApprovalConfig;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementApprovalWorkflowService
{
    public const REQUIRED_STEPS = 3;

    /**
     * @param  class-string<EmployeeMutation|EmployeeDemotion>  $movementClass
     * @return Collection<int, MutationApprovalConfig|DemotionApprovalConfig>
     */
    public function configuration(string $movementClass): Collection
    {
        $configClass = $this->configClass($movementClass);

        return $configClass::query()
            ->with('user.employee')
            ->orderBy('step_order')
            ->get()
            ->keyBy('step_order');
    }

    /** @param class-string<EmployeeMutation|EmployeeDemotion> $movementClass */
    public function isConfigurationComplete(string $movementClass): bool
    {
        try {
            $this->validConfiguration($movementClass);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function activeStep(EmployeeMutation|EmployeeDemotion $movement): int
    {
        return (int) ($movement->current_approval_step
            ?? $movement->approvals()->where('status', 'Pending')->min('step_order')
            ?? self::REQUIRED_STEPS);
    }

    public function currentApprover(EmployeeMutation|EmployeeDemotion $movement): ?User
    {
        if ($movement->verification_status !== 'Pending') {
            return null;
        }

        return $movement->approvals()
            ->where('approval_round', $movement->approval_round)
            ->where('step_order', $this->activeStep($movement))
            ->where('status', 'Pending')
            ->first()?->manager;
    }

    public function submit(EmployeeMutation|EmployeeDemotion $movement, User $submitter): void
    {
        DB::transaction(function () use ($movement, $submitter): void {
            $lockedMovement = $movement::query()->lockForUpdate()->findOrFail($movement->getKey());

            if ($lockedMovement->verification_status !== 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan hanya dapat dikirim dari status Draft.',
                ]);
            }

            $configuration = $this->validConfiguration($lockedMovement::class, lockForUpdate: true);
            $approvalRound = ((int) $lockedMovement->approval_round) + 1;

            foreach ($configuration as $step => $config) {
                $approver = $config->user;
                $lockedMovement->approvals()->create([
                    'approval_round' => $approvalRound,
                    'step_order' => $step,
                    'manager_id' => $approver->id,
                    'approver_name_snapshot' => $approver->name,
                    'approver_role_snapshot' => $approver->role,
                    'approver_number_snapshot' => $approver->employee?->sap,
                    'status' => 'Pending',
                ]);
            }

            $lockedMovement->update([
                'verification_status' => 'Pending',
                'approval_round' => $approvalRound,
                'current_approval_step' => 1,
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
                'finalized_at' => null,
                'employee_applied_at' => null,
            ]);
        }, attempts: 3);
    }

    /**
     * @param  class-string<EmployeeMutation|EmployeeDemotion>  $movementClass
     * @return Collection<int, MutationApprovalConfig|DemotionApprovalConfig>
     */
    public function validConfiguration(string $movementClass, bool $lockForUpdate = false): Collection
    {
        $configClass = $this->configClass($movementClass);
        $query = $configClass::query()->with('user.employee')->orderBy('step_order');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $configuration = $query->get()->keyBy('step_order');
        $expectedSteps = range(1, self::REQUIRED_STEPS);

        if ($configuration->count() !== self::REQUIRED_STEPS
            || $configuration->keys()->values()->all() !== $expectedSteps
            || $configuration->pluck('user_id')->unique()->count() !== self::REQUIRED_STEPS
            || $configuration->contains(fn (Model $config): bool => ! $this->isEligible($config->user))) {
            throw ValidationException::withMessages([
                'approval_config' => 'Konfigurasi tiga approver belum lengkap. Hubungi Super Admin.',
            ]);
        }

        return $configuration;
    }

    private function isEligible(?User $user): bool
    {
        return $user !== null
            && in_array($user->roleEnum(), [UserRole::ADMIN, UserRole::MANAGER], true);
    }

    /**
     * @param  class-string<EmployeeMutation|EmployeeDemotion>  $movementClass
     * @return class-string<MutationApprovalConfig|DemotionApprovalConfig>
     */
    public function configClass(string $movementClass): string
    {
        return match ($movementClass) {
            EmployeeMutation::class => MutationApprovalConfig::class,
            EmployeeDemotion::class => DemotionApprovalConfig::class,
        };
    }
}
