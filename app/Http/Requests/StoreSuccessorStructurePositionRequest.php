<?php

namespace App\Http\Requests;

use App\Models\Successor;
use App\Models\SuccessorStructurePosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSuccessorStructurePositionRequest extends FormRequest
{
    protected $errorBag = 'createStructurePosition';

    public function authorize(): bool
    {
        return $this->user()?->can('updateStructure', Successor::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $departmentId = $this->input('department_id');

        return [
            'department_id' => ['required', 'integer', 'exists:successor_structure_departments,id'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('successor_structure_positions', 'id')
                    ->where('department_id', $departmentId),
            ],
            'level' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'formation_count' => ['required', 'integer', 'min:1', 'max:999'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'align_with_grandchildren' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $position = $this->structurePosition();
                $parentId = $this->integer('parent_id') ?: null;
                $departmentId = $this->integer('department_id');

                if ($position !== null && $parentId === $position->id) {
                    $validator->errors()->add('parent_id', 'Posisi tidak dapat menjadi atasan bagi dirinya sendiri.');

                    return;
                }

                if ($parentId === null) {
                    $rootQuery = SuccessorStructurePosition::query()
                        ->where('department_id', $departmentId)
                        ->whereNull('parent_id');

                    if ($position !== null) {
                        $rootQuery->whereKeyNot($position->id);
                    }

                    if ($rootQuery->exists()) {
                        $validator->errors()->add('parent_id', 'Department hanya dapat memiliki satu posisi paling atas.');
                    }

                    return;
                }

                if ($position !== null && in_array($parentId, $this->descendantIds($position), true)) {
                    $validator->errors()->add('parent_id', 'Posisi turunan tidak dapat dipilih sebagai atasan.');
                }
            },
        ];
    }

    protected function structurePosition(): ?SuccessorStructurePosition
    {
        $position = $this->route('structurePosition');

        return $position instanceof SuccessorStructurePosition ? $position : null;
    }

    /** @return array<int, int> */
    private function descendantIds(SuccessorStructurePosition $position): array
    {
        $descendantIds = [];
        $parentIds = [$position->id];

        while ($parentIds !== []) {
            $childIds = SuccessorStructurePosition::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $childIds = array_values(array_diff($childIds, $descendantIds));
            $descendantIds = [...$descendantIds, ...$childIds];
            $parentIds = $childIds;
        }

        return $descendantIds;
    }
}
