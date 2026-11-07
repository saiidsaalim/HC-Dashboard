<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Section::class);
        $search = trim((string) $request->input('search', ''));
        $unitId = trim((string) $request->input('unit_id', ''));
        $active = $request->input('active', '');
        $perPage = $this->perPage($request);
        $units = Unit::query()->with('department.directorate')->orderBy('name')->get();
        $sections = Section::query()
            ->with('unit.department.directorate')->withCount('positions')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($unitId !== '', fn ($query) => $query->where('unit_id', $unitId))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active))
            ->orderBy('name')->paginate($perPage)->withQueryString();

        return view('organization.sections.index', compact('sections', 'units', 'search', 'unitId', 'active', 'perPage'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Section::class);
        $units = Unit::query()->with('department.directorate')->where('active', true)
            ->whereHas('department', fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true))
            ->orderBy('name')->get();
        $requestedUnitId = $request->integer('unit_id');

        return view('organization.sections.create', [
            'units' => $units,
            'selectedUnitId' => $units->contains('id', $requestedUnitId) ? (string) $requestedUnitId : '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Section::class);
        Section::query()->create($this->validatedData($request));

        return to_route('organization.sections.index')->with('status', 'Section berhasil ditambahkan.');
    }

    public function edit(Section $section): View
    {
        Gate::authorize('update', $section);

        return view('organization.sections.edit', [
            'section' => $section,
            'units' => Unit::query()->with('department.directorate')
                ->where(fn (EloquentBuilder $query): EloquentBuilder => $query->where('active', true)->orWhere('id', $section->unit_id))
                ->orderBy('name')->get(),
            'selectedUnitId' => (string) $section->unit_id,
        ]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('update', $section);
        $data = $this->validatedData($request, $section);
        $unitChanged = (int) $data['unit_id'] !== $section->unit_id;
        if ($unitChanged && $section->positions()->exists()) {
            return back()->withErrors([
                'unit_id' => 'Section tidak dapat dipindahkan selama masih memiliki Position. Pindahkan Position melalui pengelolaan posisi terlebih dahulu.',
            ])->withInput();
        }
        $hasEmployees = $section->employees()->exists() || $section->positions()->whereHas('employees')->exists();
        if ($unitChanged && $hasEmployees) {
            return back()->withErrors(['unit_id' => 'Section tidak dapat dipindahkan selama ada Employee yang terhubung.'])->withInput();
        }
        $section->update($data);

        return to_route('organization.sections.index')->with('status', 'Section berhasil diperbarui.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('delete', $section);
        if ($section->positions()->exists()) {
            return back()->withErrors(['section' => 'Section tidak dapat dihapus selama masih memiliki Position.']);
        }
        if ($section->employees()->exists()) {
            return back()->withErrors(['section' => 'Section tidak dapat dihapus selama ada Employee yang terhubung.']);
        }

        try {
            $section->delete();
        } catch (QueryException $exception) {
            if (! in_array($exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            return back()->withErrors(['section' => 'Section tidak dapat dihapus karena masih digunakan oleh data lain.']);
        }

        return to_route('organization.sections.index')->with('status', 'Section berhasil dihapus.');
    }

    /** @return array{unit_id: int, code: string, name: string, active: bool} */
    private function validatedData(Request $request, ?Section $section = null): array
    {
        $unitRule = Rule::exists('units', 'id')->where(
            fn (QueryBuilder $query): QueryBuilder => $query->where('active', true)
                ->when($section !== null, fn (QueryBuilder $query): QueryBuilder => $query->orWhere('id', $section->unit_id)),
        );

        return $request->validate([
            'unit_id' => ['required', 'integer', $unitRule],
            'code' => ['required', 'string', 'max:50', Rule::unique('sections', 'code')->where('unit_id', $request->input('unit_id'))->ignore($section)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ]);
    }
}
