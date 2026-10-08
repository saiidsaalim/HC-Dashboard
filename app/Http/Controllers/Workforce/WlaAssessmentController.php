<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaPeriodUnit;
use App\Exports\WlaFinalSpreadsheet;
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
use App\Services\Workforce\WlaFinalizationService;
use App\Services\Workforce\WlaFinalSnapshotService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WlaAssessmentController extends Controller
{
    public function __construct(
        private WlaCalculationService $wlaCalculationService,
        private WlaFinalizationService $wlaFinalizationService,
        private WlaFinalSnapshotService $wlaFinalSnapshotService,
        private WlaFinalSpreadsheet $wlaFinalSpreadsheet,
    ) {}

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

        if ($wla->status === WlaAssessmentStatus::Final) {
            $wla->load('finalizer');

            return view('wla.show', [
                'wla' => $wla,
                'finalSnapshot' => $wla->final_snapshot,
            ]);
        }

        $wla->load(['department', 'unit', 'position', 'workSchedule', 'workCalendar', 'creator', 'activities']);
        $calculationState = $this->wlaCalculationService->calculationState($wla);

        return view('wla.show', [
            'wla' => $wla,
            'calculationState' => $calculationState,
            'finalizationReasons' => $this->finalizationReasons($wla, $calculationState),
        ]);
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

    public function finalize(Request $request, WlaAssessment $wla): RedirectResponse
    {
        $this->wlaFinalizationService->finalize($wla, $request->user());

        return to_route('wla.show', $wla)->with('status', 'WLA berhasil difinalisasi.');
    }

    public function print(WlaAssessment $wla): View
    {
        Gate::authorize('view', $wla);

        return view('wla.print', [
            'snapshot' => $this->wlaFinalSnapshotService->validatedSnapshot($wla),
        ]);
    }

    public function exportExcel(WlaAssessment $wla): BinaryFileResponse
    {
        Gate::authorize('view', $wla);

        return $this->wlaFinalSpreadsheet->download($wla);
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

    /**
     * @param  array<string, mixed>  $calculationState
     * @return array<int, string>
     */
    private function finalizationReasons(WlaAssessment $assessment, array $calculationState): array
    {
        $reasons = [];

        if ($assessment->activities->isEmpty()) {
            $reasons[] = 'Tambahkan minimal satu aktivitas.';
        }

        if (! $calculationState['calculation_available']) {
            $reasons[] = $calculationState['unavailable_reason'] ?? 'Kalkulasi WLA belum tersedia.';
        } elseif (! $calculationState['calculation_complete']) {
            $reasons[] = 'Selesaikan review seluruh aktivitas.';
        }

        foreach ([
            [$assessment->department, 'Department'],
            [$assessment->unit, 'Unit'],
            [$assessment->position, 'Position'],
            [$assessment->workSchedule, 'Jadwal kerja'],
            [$assessment->workCalendar, 'Kalender kerja'],
        ] as [$master, $label]) {
            if ($master === null || ! $master->active) {
                $reasons[] = "{$label} harus tersedia dan aktif.";
            }
        }

        return array_values(array_unique($reasons));
    }
}
