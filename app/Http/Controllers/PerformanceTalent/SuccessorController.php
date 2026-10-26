<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuccessorRequest;
use App\Http\Requests\UpdateSuccessorRequest;
use App\Models\Successor;
use App\Models\SuccessorStructurePosition;
use App\Support\SuccessorStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SuccessorController extends Controller
{
    public function index(Request $request, SuccessorStructure $structure): View
    {
        Gate::authorize('viewAny', Successor::class);

        $successors = Successor::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $successorCounts = $successors->countBy('structure_position_key');
        $positions = $structure->positions();
        $departments = $structure->departments();
        $selectedPositionKey = trim((string) $request->query('position', ''));

        if (! isset($positions[$selectedPositionKey])) {
            $selectedPositionKey = null;
        }

        $requestedDepartment = collect($departments)
            ->firstWhere('slug', trim((string) $request->query('department', '')));
        $selectedDepartmentName = $requestedDepartment['name'] ?? null;
        $structurePositionRecords = SuccessorStructurePosition::query()
            ->with(['department:id,name,slug', 'parent:id,name'])
            ->withCount('children')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SuccessorStructurePosition $position): array => [
                'id' => $position->id,
                'department_id' => $position->department_id,
                'department_name' => $position->department->name,
                'department_slug' => $position->department->slug,
                'parent_id' => $position->parent_id,
                'parent_name' => $position->parent?->name,
                'key' => $position->key,
                'level' => $position->level,
                'name' => $position->name,
                'formation_count' => $position->formation_count,
                'sort_order' => $position->sort_order,
                'align_with_grandchildren' => $position->align_with_grandchildren,
                'children_count' => $position->children_count,
                'successor_count' => (int) ($successorCounts[$position->key] ?? 0),
                'update_url' => route('performance-talent.successor-structure.positions.update', $position),
                'destroy_url' => route('performance-talent.successor-structure.positions.destroy', $position),
            ])
            ->values();

        return view('performance-talent.show', [
            'title' => 'Successor View',
            'description' => 'Pilih department untuk melihat struktur organisasi dan data formasinya.',
            'featureView' => 'performance-talent.successors.index',
            'departments' => $departments,
            'successorStructures' => $structure->structures(),
            'structurePositions' => $positions,
            'successors' => $successors,
            'successorCounts' => $successorCounts->all(),
            'selectedPositionKey' => $selectedPositionKey,
            'selectedDepartmentName' => $selectedDepartmentName,
            'structurePositionRecords' => $structurePositionRecords,
            'structureEditorOpen' => $request->boolean('structure_editor'),
        ]);
    }

    public function store(StoreSuccessorRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('photo');
        $photoPath = $request->file('photo')?->store('successors', 'public');

        if (is_string($photoPath)) {
            $data['photo_path'] = $photoPath;
        }

        try {
            $successor = Successor::query()->create($data);
        } catch (Throwable $exception) {
            if (is_string($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        return to_route('performance-talent.successors.index', ['position' => $successor->structure_position_key])
            ->with('status', 'Successor berhasil ditambahkan.');
    }

    public function update(UpdateSuccessorRequest $request, Successor $successor): RedirectResponse
    {
        $data = $request->safe()->except('successor_id', 'photo');
        $oldPhotoPath = $successor->photo_path;
        $newPhotoPath = $request->file('photo')?->store('successors', 'public');

        if (is_string($newPhotoPath)) {
            $data['photo_path'] = $newPhotoPath;
        }

        try {
            $successor->update($data);
        } catch (Throwable $exception) {
            if (is_string($newPhotoPath)) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (is_string($newPhotoPath) && is_string($oldPhotoPath)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return to_route('performance-talent.successors.index', ['position' => $successor->structure_position_key])
            ->with('status', 'Successor berhasil diperbarui.');
    }

    public function destroy(Successor $successor): RedirectResponse
    {
        Gate::authorize('delete', $successor);
        $positionKey = $successor->structure_position_key;
        $photoPath = $successor->photo_path;

        try {
            $successor->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'successor' => 'Successor tidak dapat dihapus. Silakan coba kembali.',
            ]);
        }

        if (is_string($photoPath)) {
            Storage::disk('public')->delete($photoPath);
        }

        return to_route('performance-talent.successors.index', ['position' => $positionKey])
            ->with('status', 'Successor berhasil dihapus.');
    }
}
