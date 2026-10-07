<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WorkScheduleCalculationType;
use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use App\Services\Workforce\WlaMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkScheduleController extends Controller
{
    public function __construct(private WlaMasterService $wlaMasterService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkSchedule::class);

        $search = trim((string) $request->input('search', ''));
        $active = $request->input('active', '');

        $workSchedules = WorkSchedule::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('schedule_type', 'like', "%{$search}%")))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('workforce.work-schedules.index', compact('workSchedules', 'search', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkSchedule::class);

        return view('workforce.work-schedules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WorkSchedule::class);

        WorkSchedule::create($this->validatedData($request));

        return to_route('work-schedules.index')->with('status', 'Work schedule berhasil ditambahkan.');
    }

    public function edit(WorkSchedule $workSchedule): View
    {
        Gate::authorize('update', $workSchedule);

        return view('workforce.work-schedules.edit', compact('workSchedule'));
    }

    public function update(Request $request, WorkSchedule $workSchedule): RedirectResponse
    {
        Gate::authorize('update', $workSchedule);

        $this->wlaMasterService->updateSchedule(
            $workSchedule,
            $this->validatedData($request, $workSchedule),
        );

        return to_route('work-schedules.index')->with('status', 'Work schedule berhasil diperbarui.');
    }

    public function destroy(WorkSchedule $workSchedule): RedirectResponse
    {
        Gate::authorize('delete', $workSchedule);

        $this->wlaMasterService->deleteSchedule($workSchedule);

        return to_route('work-schedules.index')->with('status', 'Work schedule berhasil dihapus.');
    }

    /** @return array{code: string, name: string, schedule_type: string, calculation_type: string, working_hours_per_day: numeric-string, working_days_per_week: int, active: bool, description: string|null} */
    private function validatedData(Request $request, ?WorkSchedule $workSchedule = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('work_schedules', 'code')->ignore($workSchedule)],
            'name' => ['required', 'string', 'max:255'],
            'schedule_type' => ['required', 'string', 'max:50'],
            'calculation_type' => ['required', Rule::enum(WorkScheduleCalculationType::class)],
            'working_hours_per_day' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:24'],
            'working_days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
