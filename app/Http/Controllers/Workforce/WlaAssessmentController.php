<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaPeriodUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWlaAssessmentRequest;
use App\Http\Requests\UpdateWlaAssessmentRequest;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WlaAssessmentController extends Controller
{
    public function __construct(private WlaCalculationService $wlaCalculationService) {}

    public function index(): View
    {
        Gate::authorize('viewAny', WlaAssessment::class);

        $assessments = WlaAssessment::query()
            ->with(['department', 'unit', 'position', 'workSchedule', 'workCalendar', 'creator'])
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate(15);

        return view('wla.index', compact('assessments'));
    }

    public function create(): View
    {
        Gate::authorize('create', WlaAssessment::class);

        return view('wla.create', $this->formData());
    }

    public function store(StoreWlaAssessmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $assessment = DB::transaction(function () use ($data, $request): WlaAssessment {
            $assessment = WlaAssessment::query()->create([
                ...$data,
                'assessment_code' => 'TMP-'.Str::uuid(),
                'status' => WlaAssessmentStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            $assessment->update([
                'assessment_code' => sprintf('WLA-%04d-%06d', $assessment->period, $assessment->id),
            ]);

            return $this->wlaCalculationService->recalculate($assessment);
        });

        return to_route('wla.show', $assessment)->with('status', 'Draft WLA berhasil dibuat.');
    }

    public function show(WlaAssessment $wla): View
    {
        Gate::authorize('view', $wla);

        $wla->load(['department', 'unit', 'position', 'workSchedule', 'workCalendar', 'creator', 'activities']);
        $calculationState = $this->wlaCalculationService->calculationState($wla);

        return view('wla.show', compact('wla', 'calculationState'));
    }

    public function edit(WlaAssessment $wla): View
    {
        Gate::authorize('update', $wla);

        $wla->load(['activities', 'workCalendar', 'workSchedule']);

        return view('wla.edit', [
            ...$this->formData($wla),
            'calculationState' => $this->wlaCalculationService->calculationState($wla),
        ]);
    }

    public function update(UpdateWlaAssessmentRequest $request, WlaAssessment $wla): RedirectResponse
    {
        DB::transaction(function () use ($request, $wla): void {
            $lockedAssessment = $this->lockAuthorizedAssessment($wla, 'update');
            $lockedAssessment->update([
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]);

            $this->wlaCalculationService->recalculate($lockedAssessment);
        });

        return to_route('wla.show', $wla)->with('status', 'Draft WLA berhasil diperbarui.');
    }

    public function destroy(WlaAssessment $wla): RedirectResponse
    {
        DB::transaction(function () use ($wla): void {
            $lockedAssessment = $this->lockAuthorizedAssessment($wla, 'delete');
            $lockedAssessment->activities()->lockForUpdate()->get(['id']);
            $lockedAssessment->activities()->delete();
            $lockedAssessment->delete();
        });

        return to_route('wla')->with('status', 'Draft WLA berhasil dihapus.');
    }

    /** @return array{departments: Collection, units: Collection, positions: Collection, workSchedules: Collection, workCalendars: Collection, periodUnits: array, assessment: WlaAssessment|null} */
    private function formData(?WlaAssessment $assessment = null): array
    {
        return [
            'assessment' => $assessment,
            'departments' => $this->activeOrCurrent(
                Department::query(),
                $assessment?->department_id,
            )->orderBy('name')->get(),
            'units' => $this->activeOrCurrent(
                Unit::query(),
                $assessment?->unit_id,
            )->with('department')->orderBy('name')->get(),
            'positions' => $this->activeOrCurrent(
                Position::query(),
                $assessment?->position_id,
            )->with('unit')->orderBy('name')->get(),
            'workSchedules' => $this->activeOrCurrent(
                WorkSchedule::query(),
                $assessment?->work_schedule_id,
            )->orderBy('name')->get(),
            'workCalendars' => $this->activeOrCurrent(
                WorkCalendar::query(),
                $assessment?->work_calendar_id,
            )->orderByDesc('year')->get(),
            'periodUnits' => WlaPeriodUnit::cases(),
        ];
    }

    private function activeOrCurrent(Builder $query, ?int $currentId): Builder
    {
        return $query->where(function (Builder $query) use ($currentId): void {
            $query->where('active', true);

            if ($currentId !== null) {
                $query->orWhere($query->getModel()->getKeyName(), $currentId);
            }
        });
    }

    private function lockAuthorizedAssessment(WlaAssessment $assessment, string $ability): WlaAssessment
    {
        $lockedAssessment = WlaAssessment::query()
            ->lockForUpdate()
            ->findOrFail($assessment->getKey());
        Gate::authorize($ability, $lockedAssessment);

        return $lockedAssessment;
    }
}
