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

            $tree = $this->buildTree($root, $department->positions);
            $maximumDepths = [];
            $this->collectMaximumDepths($tree, $maximumDepths);
            $structures[$department->name] = $this->applyLevelOffsets($tree, $maximumDepths);
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
        int $depth = 0,
    ): array {
        if (in_array($position->id, $visitedIds, true)) {
            return $this->positionData($position, [], $depth);
        }

        $visitedIds[] = $position->id;
        $children = $positions
            ->where('parent_id', $position->id)
            ->map(fn (SuccessorStructurePosition $child): array => $this->buildTree($child, $positions, $visitedIds, $depth + 1))
            ->values()
            ->all();

        return $this->positionData($position, $children, $depth);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function positionData(SuccessorStructurePosition $position, array $children, int $depth): array
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
            'depth' => $depth,
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, int>  $maximumDepths
     */
    private function collectMaximumDepths(array $position, array &$maximumDepths): void
    {
        $levelKey = $this->levelKey($position['level']);
        $maximumDepths[$levelKey] = max($maximumDepths[$levelKey] ?? 0, $position['depth']);

        foreach ($position['children'] as $child) {
            $this->collectMaximumDepths($child, $maximumDepths);
        }
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, int>  $maximumDepths
     * @return array<string, mixed>
     */
    private function applyLevelOffsets(array $position, array $maximumDepths): array
    {
        $automaticOffset = ($maximumDepths[$this->levelKey($position['level'])] ?? $position['depth']) - $position['depth'];
        $position['level_offset'] = $position['align_with_grandchildren']
            ? max(1, $automaticOffset)
            : $automaticOffset;
        $position['children'] = array_map(
            fn (array $child): array => $this->applyLevelOffsets($child, $maximumDepths),
            $position['children'],
        );

        return $position;
    }

    private function levelKey(string $level): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $level) ?? $level));
    }
}
