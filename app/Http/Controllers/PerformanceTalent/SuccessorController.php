<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuccessorRequest;
use App\Http\Requests\UpdateSuccessorRequest;
use App\Models\Successor;
use App\Support\SuccessorOrganizationStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SuccessorController extends Controller
{
    public function index(Request $request, SuccessorOrganizationStructure $structure): View|Response
    {
        Gate::authorize('viewAny', Successor::class);

        if ($request->query('fragment') !== null) {
            $validated = $request->validate([
                'fragment' => ['required', Rule::in(['structure', 'candidates'])],
                'department' => ['required_if:fragment,structure', 'nullable', 'integer'],
                'position' => ['required_if:fragment,candidates', 'nullable', 'integer'],
            ]);

            if ($validated['fragment'] === 'structure') {
                $department = collect($structure->departments())->firstWhere('slug', (string) $validated['department']);
                abort_if($department === null, 404);

                return response()->view('performance-talent.successors._structure', [
                    'departmentName' => $department['name'],
                    'structureRoots' => $structure->structures()[$department['name']] ?? [],
                ])->header('Cache-Control', 'private, no-store')->header('X-Successor-Fragment', 'structure');
            }

            $positionId = (string) $validated['position'];
            abort_unless(isset($structure->positions()[$positionId]), 404);
            $successors = Successor::query()->where('target_position_id', $positionId)->orderBy('name')->orderBy('id')->get();

            return response()->view('performance-talent.successors._candidates', [
                'successors' => $successors,
                ...$this->profileColors(),
            ])->header('Cache-Control', 'private, no-store')
                ->header('X-Successor-Fragment', 'candidates')
                ->header('X-Successor-Count', (string) $successors->count());
        }

        $successors = Successor::query()
            ->with('targetPosition')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $successorCounts = $successors->countBy('target_position_id');
        $positions = $structure->positions();
        $departments = $structure->departments();
        $selectedPositionKey = trim((string) $request->query('position', ''));

        if (! isset($positions[$selectedPositionKey])) {
            $selectedPositionKey = null;
        }

        $requestedDepartment = collect($departments)
            ->firstWhere('slug', trim((string) $request->query('department', '')));
        $selectedDepartmentName = $requestedDepartment['name'] ?? null;

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
            ...$this->profileColors(),
        ]);
    }

    /** @return array{talentBoxColorClasses: array<int, string>, successionReadinessColorClasses: array<string, string>} */
    private function profileColors(): array
    {
        return [
            'talentBoxColorClasses' => [
                1 => 'bg-yellow-600',
                2 => 'bg-yellow-600',
                3 => 'bg-yellow-600',
                4 => 'bg-yellow-600',
                5 => 'bg-blue-600',
                6 => 'bg-blue-600',
                7 => 'bg-yellow-600',
                8 => 'bg-blue-600',
                9 => 'bg-emerald-600',
            ],
            'successionReadinessColorClasses' => [
                'ready now' => 'bg-emerald-600',
                'ready in one year' => 'bg-blue-600',
                'ready in two - three years' => 'bg-amber-600',
                'ready in three - five years' => 'bg-rose-600',
            ],
        ];
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

        return to_route('performance-talent.successors.index', ['position' => $successor->target_position_id])
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

        return to_route('performance-talent.successors.index', ['position' => $successor->target_position_id])
            ->with('status', 'Successor berhasil diperbarui.');
    }

    public function destroy(Successor $successor): RedirectResponse
    {
        Gate::authorize('delete', $successor);
        $positionId = $successor->target_position_id;
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

        return to_route('performance-talent.successors.index', ['position' => $positionId])
            ->with('status', 'Successor berhasil dihapus.');
    }
}
