<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
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

        $departments = Department::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.departments.index', compact('departments', 'search', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('organization.departments.create');
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

        return view('organization.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('update', $department);

        $department->update($this->validatedData($request, $department));

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

    /** @return array{code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
