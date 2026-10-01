<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaFrequencyUnit;
use App\Enums\WlaTimeUnit;
use App\Enums\WlaVolumeUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWlaAssessmentRequest;
use App\Http\Requests\UpdateWlaAssessmentRequest;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WlaAssessmentController extends Controller
{
    public function __construct(private WorkCalendarService $workCalendarService) {}

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

            return $assessment;
        });

        return to_route('wla.show', $assessment)->with('status', 'Draft WLA berhasil dibuat.');
    }

    public function show(WlaAssessment $wla): View
    {
        Gate::authorize('view', $wla);

        $wla->load(['department', 'unit', 'position', 'workSchedule', 'workCalendar', 'creator', 'activities']);
        $foundationPreview = $this->foundationPreview($wla);

        return view('wla.show', compact('wla', 'foundationPreview'));
    }

    public function edit(WlaAssessment $wla): View
    {
        Gate::authorize('update', $wla);

        $wla->load(['activities', 'workCalendar', 'workSchedule']);

        return view('wla.edit', [
            ...$this->formData($wla),
            'foundationPreview' => $this->foundationPreview($wla),
        ]);
    }

    public function update(UpdateWlaAssessmentRequest $request, WlaAssessment $wla): RedirectResponse
    {
        $wla->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return to_route('wla.show', $wla)->with('status', 'Draft WLA berhasil diperbarui.');
    }

    public function destroy(WlaAssessment $wla): RedirectResponse
    {
        Gate::authorize('delete', $wla);

        DB::transaction(function () use ($wla): void {
            $wla->activities()->delete();
            $wla->delete();
        });

        return to_route('wla')->with('status', 'Draft WLA berhasil dihapus.');
    }

    /** @return array{departments: Collection, units: Collection, positions: Collection, workSchedules: Collection, workCalendars: Collection, frequencyUnits: array, volumeUnits: array, timeUnits: array, assessment: WlaAssessment|null} */
    private function formData(?WlaAssessment $assessment = null): array
    {
        return [
            'assessment' => $assessment,
            'departments' => Department::query()->orderBy('name')->get(),
            'units' => Unit::query()->with('department')->orderBy('name')->get(),
            'positions' => Position::query()->with('unit')->orderBy('name')->get(),
            'workSchedules' => WorkSchedule::query()->orderBy('name')->get(),
            'workCalendars' => WorkCalendar::query()->orderByDesc('year')->get(),
            'frequencyUnits' => WlaFrequencyUnit::cases(),
            'volumeUnits' => WlaVolumeUnit::cases(),
            'timeUnits' => WlaTimeUnit::cases(),
        ];
    }

    /** @return array{working_days: int|string, working_hours_year: string, effective_working_hours: string, preview: bool} */
    private function foundationPreview(WlaAssessment $assessment): array
    {
        if ($assessment->working_days !== null
            && $assessment->working_hours_year !== null
            && $assessment->effective_working_hours !== null) {
            return [
                'working_days' => $assessment->working_days,
                'working_hours_year' => $assessment->working_hours_year,
                'effective_working_hours' => $assessment->effective_working_hours,
                'preview' => false,
            ];
        }

        try {
            $calendar = $assessment->workCalendar;
            $schedule = $assessment->workSchedule;
            $workingDays = $this->workCalendarService->calculateWorkingDays($calendar, $schedule);
            $workingHoursYear = $this->workCalendarService->calculateWorkingHoursPerYear($calendar, $schedule);

            return [
                'working_days' => $workingDays,
                'working_hours_year' => $workingHoursYear,
                'effective_working_hours' => $this->workCalendarService->calculateEffectiveWorkingHours(
                    $workingHoursYear,
                    $assessment->efficiency_factor,
                ),
                'preview' => true,
            ];
        } catch (ValidationException) {
            return [
                'working_days' => 'Pending Calculation',
                'working_hours_year' => 'Pending Calculation',
                'effective_working_hours' => 'Pending Calculation',
                'preview' => true,
            ];
        }
    }
}
