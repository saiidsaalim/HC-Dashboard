<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Position::class);

        $search = trim((string) $request->input('search', ''));
        $departmentId = trim((string) $request->input('department_id', ''));
        $unitId = trim((string) $request->input('unit_id', ''));
        $active = $request->input('active', '');
        $departments = Department::query()->orderBy('name')->get();
        $units = Unit::query()
            ->with('department')
            ->when($departmentId !== '', fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();
        $positions = Position::query()
            ->with('unit.department')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($departmentId !== '', fn ($query) => $query->whereHas('unit', fn ($query) => $query->where('department_id', $departmentId)))
            ->when($unitId !== '', fn ($query) => $query->where('unit_id', $unitId))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.positions.index', compact(
            'positions', 'departments', 'units', 'search', 'departmentId', 'unitId', 'active',
        ));
    }

    public function create(): View
    {
        Gate::authorize('create', Position::class);

        return view('organization.positions.create', [
            'departments' => Department::query()->orderBy('name')->get(),
            'units' => Unit::query()->with('department')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Position::class);

        Position::create($this->validatedData($request));

        return to_route('organization.positions.index')->with('status', 'Position berhasil ditambahkan.');
    }

    public function edit(Position $position): View
    {
        Gate::authorize('update', $position);

        return view('organization.positions.edit', [
            'position' => $position,
            'departments' => Department::query()->orderBy('name')->get(),
            'units' => Unit::query()->with('department')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        Gate::authorize('update', $position);

        $data = $this->validatedData($request, $position);

        if ((int) $data['unit_id'] !== $position->unit_id && $position->employees()->exists()) {
            return back()->withErrors(['unit_id' => 'Unit Position tidak dapat dipindahkan selama ada Employee yang terhubung.'])->withInput();
        }

        $position->update($data);

        return to_route('organization.positions.index')->with('status', 'Position berhasil diperbarui.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        Gate::authorize('delete', $position);

        $position->delete();

        return to_route('organization.positions.index')->with('status', 'Position berhasil dihapus.');
    }

    /** @return array{unit_id: int, code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('positions', 'code')->where('unit_id', $request->input('unit_id'))->ignore($position),
            ],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
