<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Directorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DirectorateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Directorate::class);
        $search = trim((string) $request->input('search', ''));
        $active = $request->input('active', '');
        $directorates = Directorate::query()
            ->withCount('departments')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('organization.directorates.index', compact('directorates', 'search', 'active'));
    }

    public function create(): View
    {
        Gate::authorize('create', Directorate::class);

        return view('organization.directorates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Directorate::class);
        Directorate::query()->create($this->validatedData($request));

        return to_route('organization.directorates.index')->with('status', 'Directorate berhasil ditambahkan.');
    }

    public function edit(Directorate $directorate): View
    {
        Gate::authorize('update', $directorate);

        return view('organization.directorates.edit', compact('directorate'));
    }

    public function update(Request $request, Directorate $directorate): RedirectResponse
    {
        Gate::authorize('update', $directorate);
        $directorate->update($this->validatedData($request, $directorate));

        return to_route('organization.directorates.index')->with('status', 'Directorate berhasil diperbarui.');
    }

    public function destroy(Directorate $directorate): RedirectResponse
    {
        Gate::authorize('delete', $directorate);
        if ($directorate->departments()->exists()) {
            return back()->withErrors(['directorate' => 'Directorate tidak dapat dihapus selama masih memiliki Department.']);
        }
        $directorate->delete();

        return to_route('organization.directorates.index')->with('status', 'Directorate berhasil dihapus.');
    }

    /** @return array{code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Directorate $directorate = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('directorates', 'code')->ignore($directorate)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
