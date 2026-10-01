<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Unit::class);

        $search = trim((string) $request->input('search', ''));
        $departmentId = trim((string) $request->input('department_id', ''));
        $active = $request->input('active', '');
        $departments = Department::query()->orderBy('name')->get();
        $units = Unit::query()
            ->with('department')
            ->withCount('positions')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($departmentId !== '', fn ($query) => $query->where('department_id', $departmentId))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.units.index', compact('units', 'departments', 'search', 'departmentId', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', Unit::class);

        return view('organization.units.create', [
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        Unit::create($this->validatedData($request));

        return to_route('organization.units.index')->with('status', 'Unit berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        Gate::authorize('update', $unit);

        return view('organization.units.edit', [
            'unit' => $unit,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        Gate::authorize('update', $unit);

        $data = $this->validatedData($request, $unit);
        $departmentChanged = (int) $data['department_id'] !== $unit->department_id;
        $hasEmployees = $unit->employees()->exists()
            || $unit->positions()->whereHas('employees')->exists();

        if ($departmentChanged && $hasEmployees) {
            return back()->withErrors(['department_id' => 'Department Unit tidak dapat dipindahkan selama ada Employee yang terhubung.'])->withInput();
        }

        $unit->update($data);

        return to_route('organization.units.index')->with('status', 'Unit berhasil diperbarui.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);

        if ($unit->positions()->exists()) {
            return back()->withErrors(['unit' => 'Unit tidak dapat dihapus selama masih memiliki Position.']);
        }

        $unit->delete();

        return to_route('organization.units.index')->with('status', 'Unit berhasil dihapus.');
    }

    /** @return array{department_id: int, code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('units', 'code')->where('department_id', $request->input('department_id'))->ignore($unit),
            ],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
