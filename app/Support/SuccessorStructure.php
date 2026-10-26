<?php

namespace App\Support;

use App\Models\SuccessorStructureDepartment;
use App\Models\SuccessorStructurePosition;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class SuccessorStructure
{
    /** @return array<int, array{id: int, number: int, name: string, slug: string, formation_count: int}> */
    public function departments(): array
    {
        return SuccessorStructureDepartment::query()
            ->withSum('positions', 'formation_count')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SuccessorStructureDepartment $department): array => [
                'id' => $department->id,
                'number' => $department->number,
                'name' => $department->name,
                'slug' => $department->slug,
                'formation_count' => (int) ($department->positions_sum_formation_count ?? 0),
            ])
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function structures(): array
    {
        $departments = SuccessorStructureDepartment::query()
            ->with(['positions' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $structures = [];

        foreach ($departments as $department) {
            $root = $department->positions->firstWhere('parent_id', null);

            if ($root === null) {
                continue;
            }

            $structures[$department->name] = $this->buildTree($root, $department->positions);
        }

        return $structures;
    }

    /** @return array<string, array<string, mixed>> */
    public function positions(): array
    {
        return SuccessorStructurePosition::query()
            ->with('department:id,name')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (SuccessorStructurePosition $position): array => [
                $position->key => [
                    'id' => $position->id,
                    'key' => $position->key,
                    'level' => $position->level,
                    'name' => $position->name,
                    'formation_count' => $position->formation_count,
                    'parent_id' => $position->parent_id,
                    'sort_order' => $position->sort_order,
                    'align_with_grandchildren' => $position->align_with_grandchildren,
                    'department' => $position->department->name,
                ],
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function positionKeys(): array
    {
        return SuccessorStructurePosition::query()
            ->orderBy('id')
            ->pluck('key')
            ->all();
    }

    /**
     * @param  EloquentCollection<int, SuccessorStructurePosition>  $positions
     * @param  array<int, int>  $visitedIds
     * @return array<string, mixed>
     */
    private function buildTree(
        SuccessorStructurePosition $position,
        EloquentCollection $positions,
        array $visitedIds = [],
    ): array {
        if (in_array($position->id, $visitedIds, true)) {
            return $this->positionData($position, []);
        }

        $visitedIds[] = $position->id;
        $children = $positions
            ->where('parent_id', $position->id)
            ->map(fn (SuccessorStructurePosition $child): array => $this->buildTree($child, $positions, $visitedIds))
            ->values()
            ->all();

        return $this->positionData($position, $children);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function positionData(SuccessorStructurePosition $position, array $children): array
    {
        return [
            'id' => $position->id,
            'key' => $position->key,
            'level' => $position->level,
            'name' => $position->name,
            'formation_count' => $position->formation_count,
            'parent_id' => $position->parent_id,
            'sort_order' => $position->sort_order,
            'align_with_grandchildren' => $position->align_with_grandchildren,
            'children' => $children,
        ];
    }
}
