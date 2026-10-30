<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
        $sectionId = trim((string) $request->input('section_id', ''));
        $active = $request->input('active', '');
        $departments = Department::query()->orderBy('name')->get();
        $units = Unit::query()
            ->with('department')
            ->when($departmentId !== '', fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();
        $sections = Section::query()
            ->with('unit')
            ->when($unitId !== '', fn ($query) => $query->where('unit_id', $unitId))
            ->orderBy('name')->get();
        $positions = Position::query()
            ->with(['unit.department', 'section'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($departmentId !== '', fn ($query) => $query->whereHas('unit', fn ($query) => $query->where('department_id', $departmentId)))
            ->when($unitId !== '', fn ($query) => $query->where('unit_id', $unitId))
            ->when($sectionId !== '', fn ($query) => $query->where('section_id', $sectionId))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.positions.index', compact(
            'positions', 'departments', 'units', 'sections', 'search', 'departmentId', 'unitId', 'sectionId', 'active',
        ));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Position::class);

        $directorates = Directorate::query()->where('active', true)->orderBy('name')->get();
        $departments = Department::query()
            ->where('active', true)
            ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                ->whereNull('directorate_id')
                ->orWhereHas('directorate', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true)))
            ->orderBy('name')
            ->get();
        $units = Unit::query()
            ->with('department')
            ->where('active', true)
            ->whereHas('department', fn (EloquentBuilder $query): EloquentBuilder => $query
                ->where('active', true)
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->whereNull('directorate_id')
                    ->orWhereHas('directorate', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true))))
            ->orderBy('name')
            ->get();
        $sections = Section::query()->with('unit')->where('active', true)
            ->whereHas('unit', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true))
            ->orderBy('name')->get();
        $requestedDirectorateId = $request->integer('directorate_id');
        $selectedDirectorateId = $directorates->contains('id', $requestedDirectorateId)
            ? (string) $requestedDirectorateId
            : '';
        $requestedDepartmentId = $request->integer('department_id');
        $requestedDepartment = $departments->firstWhere('id', $requestedDepartmentId);
        if ($selectedDirectorateId === '' && $requestedDepartment?->directorate_id !== null) {
            $selectedDirectorateId = (string) $requestedDepartment->directorate_id;
        }
        $selectedDepartmentId = $requestedDepartment !== null
            && (string) ($requestedDepartment->directorate_id ?? '') === $selectedDirectorateId
                ? (string) $requestedDepartmentId
                : '';
        $requestedUnitId = $request->integer('unit_id');
        $selectedUnitId = $units->contains(
            fn (Unit $unit): bool => $unit->id === $requestedUnitId
                && (string) $unit->department_id === $selectedDepartmentId,
        ) ? (string) $requestedUnitId : '';
        $requestedSectionId = $request->integer('section_id');
        $selectedSectionId = $sections->contains(
            fn (Section $section): bool => $section->id === $requestedSectionId
                && (string) $section->unit_id === $selectedUnitId,
        ) ? (string) $requestedSectionId : '';

        return view('organization.positions.create', [
            'directorates' => $directorates,
            'departments' => $departments,
            'units' => $units,
            'sections' => $sections,
            'selectedDirectorateId' => $selectedDirectorateId,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedUnitId' => $selectedUnitId,
            'selectedSectionId' => $selectedSectionId,
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

        $position->loadMissing(['unit.department.directorate', 'section']);

        $currentDepartment = $position->unit->department;
        $currentDirectorateId = $currentDepartment->directorate_id;

        return view('organization.positions.edit', [
            'position' => $position,
            'directorates' => Directorate::query()
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->where('active', true)
                    ->when($currentDirectorateId !== null, fn (EloquentBuilder $query): EloquentBuilder => $query->orWhere('id', $currentDirectorateId)))
                ->orderBy('name')
                ->get(),
            'departments' => Department::query()
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                        ->where('active', true)
                        ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                            ->whereNull('directorate_id')
                            ->orWhereHas('directorate', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true))))
                    ->orWhere('id', $currentDepartment->id))
                ->orderBy('name')
                ->get(),
            'units' => Unit::query()
                ->with('department')
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                        ->where('active', true)
                        ->whereHas('department', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true)))
                    ->orWhere('id', $position->unit_id))
                ->orderBy('name')
                ->get(),
            'sections' => Section::query()->with('unit')
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->where('active', true)
                    ->when($position->section_id !== null, fn (EloquentBuilder $query): EloquentBuilder => $query->orWhere('id', $position->section_id)))
                ->orderBy('name')->get(),
            'selectedDirectorateId' => (string) ($currentDirectorateId ?? ''),
            'selectedDepartmentId' => (string) $position->unit->department_id,
            'selectedUnitId' => (string) $position->unit_id,
            'selectedSectionId' => (string) ($position->section_id ?? ''),
        ]);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        Gate::authorize('update', $position);

        $data = $this->validatedData($request, $position);

        $parentChanged = (int) $data['unit_id'] !== $position->unit_id
            || (int) ($data['section_id'] ?? 0) !== (int) $position->section_id;
        if ($parentChanged && $position->employees()->exists()) {
            return back()->withErrors(['unit_id' => 'Induk Position tidak dapat dipindahkan selama ada Employee yang terhubung.'])->withInput();
        }

        $position->update($data);

        return to_route('organization.positions.index')->with('status', 'Position berhasil diperbarui.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        Gate::authorize('delete', $position);

        if ($position->successors()->exists()) {
            return back()->withErrors([
                'position' => 'Position tidak dapat dihapus selama masih digunakan sebagai target successor.',
            ]);
        }

        $position->delete();

        return to_route('organization.positions.index')->with('status', 'Position berhasil dihapus.');
    }

    /** @return array{unit_id: int, section_id: ?int, code: string, name: string, job_level: ?string, formation_count: int, sort_order: int, active: bool} */
    private function validatedData(Request $request, ?Position $position = null): array
    {
        $position?->loadMissing('unit.department');
        $currentDepartmentId = $position?->unit->department_id;
        $currentDirectorateId = $position?->unit->department->directorate_id;
        $directorateRule = Rule::exists('directorates', 'id')->where(
            fn (QueryBuilder $query): QueryBuilder => $query
                ->where('active', true)
                ->when($currentDirectorateId !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('id', $currentDirectorateId)),
        );
        $departmentRule = Rule::exists('departments', 'id')->where(
            fn (QueryBuilder $query): QueryBuilder => $query
                ->where('directorate_id', $request->input('directorate_id'))
                ->where(fn (QueryBuilder $query): QueryBuilder => $query
                    ->where('active', true)
                    ->when($currentDepartmentId !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('id', $currentDepartmentId))),
        );
        $unitRule = Rule::exists('units', 'id')
            ->where('department_id', $request->input('department_id'))
            ->where(fn (QueryBuilder $query): QueryBuilder => $query
                ->where(fn (QueryBuilder $query): QueryBuilder => $query
                    ->where('units.active', true)
                    ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query
                        ->selectRaw('1')
                        ->from('departments')
                        ->whereColumn('departments.id', 'units.department_id')
                        ->where('departments.active', true)))
                ->when($position !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('units.id', $position->unit_id)));
        $sectionRule = Rule::exists('sections', 'id')
            ->where('unit_id', $request->input('unit_id'))
            ->where(fn (QueryBuilder $query): QueryBuilder => $query->where('active', true)
                ->when($position?->section_id !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('id', $position->section_id)));

        $validated = $request->validate([
            'directorate_id' => ['nullable', 'integer', $directorateRule],
            'department_id' => ['required', 'integer', $departmentRule],
            'unit_id' => ['required', 'integer', $unitRule],
            'section_id' => ['nullable', 'integer', $sectionRule],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('positions', 'code')->where('unit_id', $request->input('unit_id'))->ignore($position),
            ],
            'name' => ['required', 'string', 'max:255'],
            'job_level' => ['nullable', 'string', 'max:100'],
            'formation_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:999'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
            'active' => ['required', 'boolean'],
        ], [
            'directorate_id.integer' => 'Directorate yang dipilih tidak valid.',
            'directorate_id.exists' => 'Directorate yang dipilih tidak tersedia atau tidak aktif.',
            'department_id.required' => 'Pilih Department untuk Position ini.',
            'department_id.integer' => 'Department yang dipilih tidak valid.',
            'department_id.exists' => 'Department yang dipilih tidak aktif atau tidak berada pada Directorate terpilih.',
            'unit_id.required' => 'Pilih Unit untuk Position ini.',
            'unit_id.integer' => 'Unit yang dipilih tidak valid.',
            'unit_id.exists' => 'Unit yang dipilih tidak aktif atau tidak berada pada Department terpilih.',
            'code.required' => 'Kode Position wajib diisi.',
            'code.max' => 'Kode Position maksimal 50 karakter.',
            'code.unique' => 'Kode Position sudah digunakan pada Unit tersebut.',
            'name.required' => 'Nama Position wajib diisi.',
            'name.max' => 'Nama Position maksimal 255 karakter.',
            'job_level.max' => 'Level jabatan maksimal 100 karakter.',
            'formation_count.required' => 'Jumlah formasi wajib diisi.',
            'formation_count.integer' => 'Jumlah formasi harus berupa angka.',
            'formation_count.min' => 'Jumlah formasi minimal 1.',
            'formation_count.max' => 'Jumlah formasi maksimal 999.',
            'sort_order.required' => 'Urutan tampil wajib diisi.',
            'sort_order.integer' => 'Urutan tampil harus berupa angka.',
            'active.required' => 'Status Position wajib dipilih.',
            'active.boolean' => 'Status Position tidak valid.',
        ]);

        unset($validated['directorate_id'], $validated['department_id']);
        $validated['formation_count'] ??= $position?->formation_count ?? 1;
        $validated['sort_order'] ??= $position?->sort_order ?? 0;

        return $validated;
    }
}
