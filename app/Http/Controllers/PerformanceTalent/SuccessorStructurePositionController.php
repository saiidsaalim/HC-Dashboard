<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuccessorStructurePositionRequest;
use App\Http\Requests\UpdateSuccessorStructurePositionRequest;
use App\Models\Successor;
use App\Models\SuccessorStructureDepartment;
use App\Models\SuccessorStructurePosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class SuccessorStructurePositionController extends Controller
{
    public function store(StoreSuccessorStructurePositionRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('align_with_grandchildren');
        $department = SuccessorStructureDepartment::query()->findOrFail($data['department_id']);
        $data['key'] = $this->uniquePositionKey($department, $data['name']);
        $data['align_with_grandchildren'] = $request->boolean('align_with_grandchildren');

        SuccessorStructurePosition::query()->create($data);

        return $this->editorRedirect($department)
            ->with('status', 'Posisi struktur berhasil ditambahkan.');
    }

    public function update(
        UpdateSuccessorStructurePositionRequest $request,
        SuccessorStructurePosition $structurePosition,
    ): RedirectResponse {
        $data = $request->safe()->except(
            'department_id',
            'structure_position_id',
            'align_with_grandchildren',
        );
        $data['align_with_grandchildren'] = $request->boolean('align_with_grandchildren');
        $structurePosition->update($data);

        return $this->editorRedirect($structurePosition->department)
            ->with('status', 'Posisi struktur berhasil diperbarui.');
    }

    public function destroy(SuccessorStructurePosition $structurePosition): RedirectResponse
    {
        Gate::authorize('updateStructure', Successor::class);
        $structurePosition->loadMissing('department');
        $redirect = $this->editorRedirect($structurePosition->department);

        if ($structurePosition->children()->exists()) {
            return $redirect->withErrors([
                'structure_position' => 'Posisi yang masih memiliki posisi bawahan tidak dapat dihapus.',
            ], 'structurePosition');
        }

        if (Successor::query()->where('structure_position_key', $structurePosition->key)->exists()) {
            return $redirect->withErrors([
                'structure_position' => 'Posisi yang masih memiliki successor tidak dapat dihapus.',
            ], 'structurePosition');
        }

        try {
            $structurePosition->delete();
        } catch (Throwable $exception) {
            report($exception);

            return $redirect->withErrors([
                'structure_position' => 'Posisi struktur tidak dapat dihapus. Silakan coba kembali.',
            ], 'structurePosition');
        }

        return $redirect->with('status', 'Posisi struktur berhasil dihapus.');
    }

    private function uniquePositionKey(SuccessorStructureDepartment $department, string $name): string
    {
        $positionSlug = Str::slug($name) ?: 'position';
        $baseKey = Str::substr($department->slug.'.'.$positionSlug, 0, 150);
        $key = $baseKey;
        $suffix = 2;

        while (SuccessorStructurePosition::query()->where('key', $key)->exists()) {
            $key = $baseKey.'-'.$suffix++;
        }

        return $key;
    }

    private function editorRedirect(SuccessorStructureDepartment $department): RedirectResponse
    {
        return to_route('performance-talent.successors.index', [
            'department' => $department->slug,
            'structure_editor' => 1,
        ]);
    }
}
