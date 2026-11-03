<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateMovementApprovalSettingsRequest;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\User;
use App\Services\Personnel\MovementApprovalWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MovementApprovalSettingsController extends Controller
{
    public function editMutation(MovementApprovalWorkflowService $workflow): View
    {
        return $this->edit(EmployeeMutation::class, 'Mutasi', 'mutasi', $workflow);
    }

    public function editDemotion(MovementApprovalWorkflowService $workflow): View
    {
        return $this->edit(EmployeeDemotion::class, 'Demosi', 'demosi', $workflow);
    }

    public function updateMutation(
        UpdateMovementApprovalSettingsRequest $request,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        return $this->update($request, EmployeeMutation::class, 'Mutasi', 'mutasi', $workflow);
    }

    public function updateDemotion(
        UpdateMovementApprovalSettingsRequest $request,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        return $this->update($request, EmployeeDemotion::class, 'Demosi', 'demosi', $workflow);
    }

    /** @param class-string<EmployeeMutation|EmployeeDemotion> $movementClass */
    private function edit(
        string $movementClass,
        string $label,
        string $routePrefix,
        MovementApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('manageApprovalSettings', $movementClass);

        return view('pages.movement-approval-settings', [
            'label' => $label,
            'routePrefix' => $routePrefix,
            'approvers' => User::query()
                ->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value])
                ->with('employee:id,sap')
                ->orderBy('name')
                ->get(),
            'configuredApprovers' => $workflow->configuration($movementClass)->pluck('user_id', 'step_order'),
            'approvalSteps' => range(1, MovementApprovalWorkflowService::REQUIRED_STEPS),
        ]);
    }

    /** @param class-string<EmployeeMutation|EmployeeDemotion> $movementClass */
    private function update(
        UpdateMovementApprovalSettingsRequest $request,
        string $movementClass,
        string $label,
        string $routePrefix,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        /** @var array<int|string, int|string> $approvers */
        $approvers = $request->validated('approvers');
        $configClass = $workflow->configClass($movementClass);

        DB::transaction(function () use ($approvers, $configClass, $request): void {
            $configClass::query()->lockForUpdate()->get();
            $configClass::query()->delete();

            foreach (range(1, MovementApprovalWorkflowService::REQUIRED_STEPS) as $step) {
                /** @var Model $config */
                $config = new $configClass;
                $config->forceFill([
                    'step_order' => $step,
                    'user_id' => (int) $approvers[$step],
                    'updated_by' => $request->user()->id,
                ])->save();
            }
        });

        return to_route("{$routePrefix}.approval-settings.edit")
            ->with('status', "Pengaturan persetujuan {$label} berhasil disimpan.");
    }
}
