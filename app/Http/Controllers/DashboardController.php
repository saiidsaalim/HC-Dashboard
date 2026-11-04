<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\FitProperParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $fitProperCount = 0;

        if ($user->hasPermission('fit-proper.view') && Schema::hasTable('fit_proper_participants')) {
            $fitProperCount = FitProperParticipant::query()
                ->whereHas('activity', function ($query) use ($user): void {
                    $query->where('status', 'Berjalan');

                    if (! $user->isSuperAdmin()) {
                        $query->where(function ($query) use ($user): void {
                            $query->whereHas('pics', fn ($query) => $query->where('users.id', $user->id))
                                ->orWhereHas('examiners', fn ($query) => $query->where('user_id', $user->id));
                        });
                    }
                })
                ->count();
        }

        $mutationCount = $user->hasPermission('mutations.view') && Schema::hasTable('employee_mutations')
            ? EmployeeMutation::query()->whereYear('created_at', now()->year)->count()
            : 0;
        $promotionCount = $user->hasPermission('promotions.view') && Schema::hasTable('employee_promotions')
            ? EmployeePromotion::query()->whereIn('verification_status', ['Waiting Approval', 'Approved'])->count()
            : 0;
        $demotionCount = $user->hasPermission('demotions.view') && Schema::hasTable('employee_demotions')
            ? EmployeeDemotion::query()->whereIn('verification_status', ['Pending', 'Approved'])->count()
            : 0;

        $stats = collect([
            ['permission' => 'fit-proper.view', 'label' => 'Fit & Proper', 'value' => $fitProperCount, 'description' => 'Kandidat pada kegiatan aktif', 'icon' => 'clipboard', 'tone' => 'amber'],
            ['permission' => 'mutations.view', 'label' => 'Mutasi', 'value' => $mutationCount, 'description' => 'Pengajuan mutasi tahun ini', 'icon' => 'arrows', 'tone' => 'sky'],
            ['permission' => 'promotions.view', 'label' => 'Promosi', 'value' => $promotionCount, 'description' => 'Promosi menunggu atau sudah disetujui', 'icon' => 'chart', 'tone' => 'emerald'],
            ['permission' => 'demotions.view', 'label' => 'Demosi', 'value' => $demotionCount, 'description' => 'Demosi menunggu atau sudah disetujui', 'icon' => 'document', 'tone' => 'violet'],
        ])->filter(fn (array $stat): bool => $user->hasPermission($stat['permission']))
            ->map(function (array $stat): array {
                unset($stat['permission']);

                return $stat;
            })
            ->values()
            ->all();

        return view('dashboard', [
            'stats' => $stats,
        ]);
    }
}
