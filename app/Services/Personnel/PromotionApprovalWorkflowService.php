<?php

namespace App\Services\Personnel;

use App\Enums\PromotionApprovalStatus;
use App\Enums\PromotionStatus;
use App\Enums\UserRole;
use App\Models\EmployeePromotion;
use App\Models\PromotionApproval;
use App\Models\PromotionApprovalConfig;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionApprovalWorkflowService
{
    public const REQUIRED_STEPS = 4;

    /** @return Collection<int, PromotionApprovalConfig> */
    public function configuration(): Collection
    {
        return $this->loadConfiguration();
    }

    public function isConfigurationComplete(): bool
    {
        try {
            $this->validConfiguration();

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function currentApprover(EmployeePromotion $promotion): ?User
    {
        if ($promotion->statusEnum() !== PromotionStatus::WaitingApproval
            || $promotion->current_approval_step === null) {
            return null;
        }

        $config = $this->configuration()->get($promotion->current_approval_step);

        return $config !== null && $this->isEligible($config->user) ? $config->user : null;
    }

    public function submit(EmployeePromotion $promotion, User $submitter): EmployeePromotion
    {
        return DB::transaction(function () use ($promotion, $submitter): EmployeePromotion {
            $lockedPromotion = EmployeePromotion::query()->lockForUpdate()->findOrFail($promotion->id);

            if (! $lockedPromotion->statusEnum()->canBeSubmitted()) {
                throw ValidationException::withMessages([
                    'status' => 'Promosi hanya dapat diajukan dari status Draft atau Dikembalikan.',
                ]);
            }

            $this->validConfiguration(lockForUpdate: true);

            $lockedPromotion->update([
                'approval_round' => ((int) $lockedPromotion->approval_round) + 1,
                'current_approval_step' => 1,
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
                'verification_status' => PromotionStatus::WaitingApproval->value,
                'finalized_at' => null,
            ]);

            return $lockedPromotion->fresh();
        }, attempts: 3);
    }

    public function approve(EmployeePromotion $promotion, User $approver): bool
    {
        return DB::transaction(function () use ($promotion, $approver): bool {
            $lockedPromotion = EmployeePromotion::query()->lockForUpdate()->findOrFail($promotion->id);
            $existingDecision = $this->existingDecision($lockedPromotion, $approver);

            if ($existingDecision !== null) {
                return true;
            }

            $step = $this->authorizeActiveApprover($lockedPromotion, $approver);
            $this->createDecision($lockedPromotion, $approver, $step, PromotionApprovalStatus::Approved);

            if ($step === self::REQUIRED_STEPS) {
                $lockedPromotion->update([
                    'verification_status' => PromotionStatus::Final->value,
                    'current_approval_step' => null,
                    'finalized_at' => now(),
                ]);
            } else {
                $lockedPromotion->update(['current_approval_step' => $step + 1]);
            }

            return false;
        }, attempts: 3);
    }

    public function sendBack(EmployeePromotion $promotion, User $approver, string $note): bool
    {
        return DB::transaction(function () use ($promotion, $approver, $note): bool {
            $lockedPromotion = EmployeePromotion::query()->lockForUpdate()->findOrFail($promotion->id);
            $existingDecision = $this->existingDecision($lockedPromotion, $approver);

            if ($existingDecision?->status === PromotionApprovalStatus::Returned) {
                return true;
            }

            $step = $this->authorizeActiveApprover($lockedPromotion, $approver);
            $this->createDecision(
                $lockedPromotion,
                $approver,
                $step,
                PromotionApprovalStatus::Returned,
                $note,
            );
            $lockedPromotion->update([
                'verification_status' => PromotionStatus::Returned->value,
                'current_approval_step' => null,
            ]);

            return false;
        }, attempts: 3);
    }

    /** @return Collection<int, PromotionApprovalConfig> */
    private function validConfiguration(bool $lockForUpdate = false): Collection
    {
        $configuration = $this->loadConfiguration($lockForUpdate);
        $expectedSteps = collect(range(1, self::REQUIRED_STEPS));
        $configuredUsers = $configuration->pluck('user_id');

        if ($configuration->count() !== self::REQUIRED_STEPS
            || $configuration->keys()->values()->all() !== $expectedSteps->all()
            || $configuredUsers->unique()->count() !== self::REQUIRED_STEPS
            || $configuration->contains(fn (PromotionApprovalConfig $config): bool => ! $this->isEligible($config->user))) {
            throw ValidationException::withMessages([
                'approval_config' => 'Konfigurasi empat approver Promosi belum lengkap. Hubungi Super Admin.',
            ]);
        }

        return $configuration;
    }

    private function isEligible(?User $user): bool
    {
        return $user !== null
            && in_array($user->roleEnum(), [UserRole::ADMIN, UserRole::MANAGER], true);
    }

    private function authorizeActiveApprover(EmployeePromotion $promotion, User $approver): int
    {
        if ($promotion->statusEnum() !== PromotionStatus::WaitingApproval
            || $promotion->current_approval_step === null) {
            throw new AuthorizationException('Promosi tidak sedang menunggu keputusan Anda.');
        }

        $step = $promotion->current_approval_step;
        $configuration = $this->validConfiguration(lockForUpdate: true);
        $configuredApprover = $configuration->get($step)?->user;

        if ($configuredApprover?->isNot($approver) ?? true) {
            throw new AuthorizationException('Anda bukan approver aktif untuk tahap ini.');
        }

        return $step;
    }

    private function existingDecision(EmployeePromotion $promotion, User $approver): ?PromotionApproval
    {
        return $promotion->approvals()
            ->where('approval_round', $promotion->approval_round)
            ->where('manager_id', $approver->id)
            ->first();
    }

    private function createDecision(
        EmployeePromotion $promotion,
        User $approver,
        int $step,
        PromotionApprovalStatus $status,
        ?string $note = null,
    ): void {
        $approver->loadMissing('employee');
        $decisionAt = now();

        $promotion->approvals()->create([
            'approval_round' => $promotion->approval_round,
            'step_order' => $step,
            'manager_id' => $approver->id,
            'status' => $status->value,
            'note' => $note,
            'approved_at' => $status === PromotionApprovalStatus::Approved ? $decisionAt : null,
            'returned_at' => $status === PromotionApprovalStatus::Returned ? $decisionAt : null,
            'approver_name_snapshot' => $approver->name,
            'approver_role_snapshot' => $approver->role,
            'approver_number_snapshot' => $approver->employee?->sap,
        ]);
    }

    /** @return Collection<int, PromotionApprovalConfig> */
    private function loadConfiguration(bool $lockForUpdate = false): Collection
    {
        $query = PromotionApprovalConfig::query()
            ->with('user.employee')
            ->orderBy('step_order');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy('step_order');
    }
}
