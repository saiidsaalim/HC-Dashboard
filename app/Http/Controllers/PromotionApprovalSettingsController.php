<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdatePromotionApprovalSettingsRequest;
use App\Models\EmployeePromotion;
use App\Models\PromotionApprovalConfig;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromotionApprovalSettingsController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('manageApprovalSettings', EmployeePromotion::class);

        return view('pages.promotion-approval-settings', [
            'approvers' => User::query()
                ->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value])
                ->with('employee:id,sap')
                ->orderBy('name')
                ->get(),
            'configuredApprovers' => PromotionApprovalConfig::query()
                ->orderBy('step_order')
                ->pluck('user_id', 'step_order'),
        ]);
    }

    public function update(UpdatePromotionApprovalSettingsRequest $request): RedirectResponse
    {
        /** @var array<int|string, int|string> $approvers */
        $approvers = $request->validated('approvers');

        DB::transaction(function () use ($approvers, $request): void {
            PromotionApprovalConfig::query()->lockForUpdate()->get();
            PromotionApprovalConfig::query()->delete();

            foreach ([1, 2, 3] as $step) {
                PromotionApprovalConfig::query()->create([
                    'step_order' => $step,
                    'user_id' => (int) $approvers[$step],
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return to_route('promosi.approval-settings.edit')
            ->with('status', 'Pengaturan persetujuan Promosi berhasil disimpan.');
    }
}
