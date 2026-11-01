<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuccessorRequest;
use App\Http\Requests\UpdateSuccessorRequest;
use App\Models\Successor;
use App\Support\SuccessorOrganizationStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class SuccessorController extends Controller
{
    public function index(Request $request, SuccessorOrganizationStructure $structure): View
    {
        Gate::authorize('viewAny', Successor::class);

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
