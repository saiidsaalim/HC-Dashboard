<?php

namespace App\Http\Controllers;

use App\Enums\PromotionStatus;
use App\Enums\WlaAssessmentStatus;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\FitProperActivity;
use App\Models\FitProperParticipant;
use App\Models\User;
use App\Models\WlaAssessment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $today = CarbonImmutable::today();
        $firstMonth = $today->startOfMonth()->subMonths(5);
        $movementMonths = [];

        for ($monthIndex = 0; $monthIndex < 6; $monthIndex++) {
            $month = $firstMonth->addMonths($monthIndex)->locale('id');
            $movementMonths[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M'),
                'fullLabel' => $month->translatedFormat('F Y'),
            ];
        }

        $movements = [];

        foreach ([
            ['key' => 'mutations', 'label' => 'Mutasi', 'model' => EmployeeMutation::class, 'pendingStatus' => 'Pending', 'icon' => 'arrows', 'tone' => 'blue', 'route' => 'mutasi'],
            ['key' => 'promotions', 'label' => 'Promosi', 'model' => EmployeePromotion::class, 'pendingStatus' => PromotionStatus::WaitingApproval->value, 'icon' => 'chart', 'tone' => 'teal', 'route' => 'promosi'],
            ['key' => 'demotions', 'label' => 'Demosi', 'model' => EmployeeDemotion::class, 'pendingStatus' => 'Pending', 'icon' => 'demosi', 'tone' => 'amber', 'route' => 'demosi'],
        ] as $module) {
            if (! $user->hasPermission($module['key'].'.view')) {
                continue;
            }

            $counts = $this->movementCounts($module['model'], $module['pendingStatus'], $firstMonth, $today);
            unset($module['model'], $module['pendingStatus']);
            $movements[] = [...$module, ...$counts];
        }

        return view('dashboard', [
            'totalEmployees' => $user->hasPermission('employees.view') ? Employee::query()->count() : null,
            'wlaSummary' => $user->hasPermission('wla.view') ? $this->wlaSummary() : null,
            'fitProperSummary' => $user->hasPermission('fit-proper.view') ? $this->fitProperSummary($user) : null,
            'movements' => $movements,
            'pendingTotal' => $movements === [] ? null : array_sum(array_column($movements, 'pending')),
            'movementMonths' => $movementMonths,
            'movementPeriod' => $firstMonth->locale('id')->translatedFormat('M Y').' – '.$today->locale('id')->translatedFormat('M Y'),
        ]);
    }

    /**
     * @return array{running: int, total: int, statuses: list<array{status: string, label: string, count: int, tone: string}>}
     */
    private function wlaSummary(): array
    {
        $counts = WlaAssessment::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->whereIn('status', array_column(WlaAssessmentStatus::cases(), 'value'))
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status');
        $draftCount = (int) $counts->get(WlaAssessmentStatus::Draft->value, 0);
        $finalCount = (int) $counts->get(WlaAssessmentStatus::Final->value, 0);

        return [
            'running' => $draftCount,
            'total' => $draftCount + $finalCount,
            'statuses' => [
                ['status' => WlaAssessmentStatus::Draft->value, 'label' => 'Draft', 'count' => $draftCount, 'tone' => 'blue'],
                ['status' => WlaAssessmentStatus::Final->value, 'label' => 'Final', 'count' => $finalCount, 'tone' => 'amber'],
            ],
        ];
    }

    /**
     * @return array{activeParticipants: int, total: int, statuses: list<array{status: string, label: string, count: int, tone: string}>}
     */
    private function fitProperSummary(User $user): array
    {
        $counts = FitProperActivity::query()
            ->accessibleTo($user)
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->whereIn('status', ['Draft', 'Berjalan', 'Selesai'])
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status');
        $statuses = [];

        foreach (['Draft' => 'blue', 'Berjalan' => 'teal', 'Selesai' => 'amber'] as $status => $tone) {
            $statuses[] = ['status' => $status, 'label' => $status, 'count' => (int) $counts->get($status, 0), 'tone' => $tone];
        }

        return [
            'activeParticipants' => FitProperParticipant::query()
                ->whereHas('activity', fn (Builder $query): Builder => $query->accessibleTo($user)->where('status', 'Berjalan'))
                ->count(),
            'total' => array_sum(array_column($statuses, 'count')),
            'statuses' => $statuses,
        ];
    }

    /**
     * @param  class-string<EmployeeMutation|EmployeePromotion|EmployeeDemotion>  $modelClass
     * @return array{pending: int, values: list<int>}
     */
    private function movementCounts(string $modelClass, string $pendingStatus, CarbonImmutable $firstMonth, CarbonImmutable $today): array
    {
        $query = $modelClass::query()
            ->selectRaw('COALESCE(SUM(CASE WHEN verification_status = ? THEN 1 ELSE 0 END), 0) AS pending', [$pendingStatus])
            ->where(function (Builder $query) use ($pendingStatus, $firstMonth, $today): void {
                $query->where('verification_status', $pendingStatus)
                    ->orWhere(function (Builder $query) use ($firstMonth, $today): void {
                        $query->where('verification_status', PromotionStatus::Final->value)
                            ->where('tmt', '>=', $firstMonth->toDateString())
                            ->where('tmt', '<', $today->addDay()->toDateString());
                    });
            });

        for ($monthIndex = 0; $monthIndex < 6; $monthIndex++) {
            $month = $firstMonth->addMonths($monthIndex);
            $query->selectRaw(
                'COALESCE(SUM(CASE WHEN verification_status = ? AND tmt >= ? AND tmt < ? THEN 1 ELSE 0 END), 0) AS month_'.$monthIndex,
                [PromotionStatus::Final->value, $month->toDateString(), $month->addMonth()->toDateString()],
            );
        }

        $counts = $query->toBase()->first();
        $values = [];

        for ($monthIndex = 0; $monthIndex < 6; $monthIndex++) {
            $values[] = (int) $counts->{'month_'.$monthIndex};
        }

        return ['pending' => (int) $counts->pending, 'values' => $values];
    }
}
