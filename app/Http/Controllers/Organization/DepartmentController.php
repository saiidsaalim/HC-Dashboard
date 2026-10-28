<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Department::class);

        $search = trim((string) $request->input('search', ''));
        $active = $request->input('active', '');
        $directorateId = trim((string) $request->input('directorate_id', ''));
        $directorates = Directorate::query()->orderBy('name')->get();

        $departments = Department::query()
            ->with('directorate')
            ->withCount('units')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->when($directorateId !== '', fn ($query) => $query->where('directorate_id', $directorateId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.departments.index', compact('departments', 'directorates', 'directorateId', 'search', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('organization.departments.create', [
            'directorates' => Directorate::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Department::class);

        Department::create($this->validatedData($request));

        return to_route('organization.departments.index')->with('status', 'Department berhasil ditambahkan.');
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('organization.departments.edit', [
            'department' => $department,
            'directorates' => Directorate::query()
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query
                    ->where('active', true)
                    ->orWhere('id', $department->directorate_id))
                ->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);

        $data = $this->validatedData($request, $department);
        $directorateChanged = (int) ($data['directorate_id'] ?? 0) !== (int) $department->directorate_id;
        if ($directorateChanged && ($department->employees()->exists() || $department->units()->whereHas('employees')->exists())) {
            return back()->withErrors(['directorate_id' => 'Department tidak dapat dipindahkan selama ada Employee yang terhubung.'])->withInput();
        }
        $department->update($data);

        return to_route('organization.departments.index')->with('status', 'Department berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        Gate::authorize('delete', $department);

        if ($department->units()->exists()) {
            return back()->withErrors(['department' => 'Department tidak dapat dihapus selama masih memiliki Unit.']);
        }

        $department->delete();

        return to_route('organization.departments.index')->with('status', 'Department berhasil dihapus.');
    }

    /** @return array{directorate_id: ?int, code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Department $department = null): array
    {
        $directorateRule = Rule::exists('directorates', 'id')->where(
            fn (QueryBuilder $query): QueryBuilder => $query->where('active', true)
                ->when($department?->directorate_id !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('id', $department->directorate_id)),
        );

        return $request->validate([
            'directorate_id' => ['nullable', 'integer', $directorateRule],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
