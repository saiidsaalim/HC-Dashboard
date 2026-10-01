<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\WorkCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkCalendarController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkCalendar::class);

        $search = trim((string) $request->input('search', ''));
        $active = $request->input('active', '');

        $workCalendars = WorkCalendar::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('year', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderByDesc('year')
            ->paginate(15)
            ->withQueryString();

        return view('workforce.work-calendars.index', compact('workCalendars', 'search', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkCalendar::class);

        return view('workforce.work-calendars.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', WorkCalendar::class);

        WorkCalendar::create($this->validatedData($request));

        return to_route('work-calendars.index')->with('status', 'Work calendar berhasil ditambahkan.');
    }

    public function edit(WorkCalendar $workCalendar): View
    {
        Gate::authorize('update', $workCalendar);

        return view('workforce.work-calendars.edit', compact('workCalendar'));
    }

    public function update(Request $request, WorkCalendar $workCalendar): RedirectResponse
    {
        Gate::authorize('update', $workCalendar);

        $workCalendar->update($this->validatedData($request, $workCalendar));

        return to_route('work-calendars.index')->with('status', 'Work calendar berhasil diperbarui.');
    }

    public function destroy(WorkCalendar $workCalendar): RedirectResponse
    {
        Gate::authorize('delete', $workCalendar);

        $workCalendar->delete();

        return to_route('work-calendars.index')->with('status', 'Work calendar berhasil dihapus.');
    }

    /** @return array{year: int, total_days: int, total_weeks: int, annual_leave: int, national_holiday: int, common_leave: int, saturday_days: int, sunday_days: int, notes: string|null, active: bool} */
    private function validatedData(Request $request, ?WorkCalendar $workCalendar = null): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:2100', Rule::unique('work_calendars', 'year')->ignore($workCalendar)],
            'total_days' => ['required', 'integer', 'min:1', 'max:366'],
            'total_weeks' => ['required', 'integer', 'min:1', 'max:53'],
            'annual_leave' => ['required', 'integer', 'min:0', 'max:366'],
            'national_holiday' => ['required', 'integer', 'min:0', 'max:366'],
            'common_leave' => ['required', 'integer', 'min:0', 'max:366'],
            'saturday_days' => ['required', 'integer', 'min:0', 'max:366'],
            'sunday_days' => ['required', 'integer', 'min:0', 'max:366'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
