<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Collection;

class SuccessorOrganizationStructure
{
    /** @var Collection<int, Department>|null */
    private ?Collection $organization = null;

    /** @return array<int, array{number: int, name: string, slug: string, formation_count: int}> */
    public function departments(): array
    {
        return $this->organization()
            ->values()
            ->map(fn (Department $department, int $index): array => [
                'number' => $index + 1,
                'name' => $department->name,
                'slug' => (string) $department->id,
                'formation_count' => $this->departmentFormationCount($department),
            ])
            ->all();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function structures(): array
    {
        return $this->organization()
            ->mapWithKeys(function (Department $department): array {
                $roots = $this->departmentRoots($department);

                if ($roots === []) {
                    return [];
                }

                return [$department->name => $roots];
            })
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function positions(): array
    {
        return $this->organization()
            ->flatMap(fn (Department $department) => $department->units->flatMap->positions)
            ->mapWithKeys(fn (Position $position): array => [
                (string) $position->id => $this->positionNode($position),
            ])
            ->all();
    }

    /** @return array<int, int> */
    public function positionIds(): array
    {
        return array_map('intval', array_keys($this->positions()));
    }

    /** @return Collection<int, Department> */
    private function organization(): Collection
    {
        return $this->organization ??= Department::query()
            ->where('active', true)
            ->with([
                'units' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name')
                    ->with([
                        'positions' => fn ($query) => $query
                            ->where('active', true)
                            ->with('unit.department')
                            ->withCount('employees')
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ]),
            ])
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function departmentRoots(Department $department): array
    {
        $positions = $this->sortedPositions($department->units->flatMap->positions);
        $positionsById = $positions->keyBy(fn (Position $position): int => $position->id);
        $parentIds = [];

        foreach ($positions as $position) {
            $explicitParent = $position->parent_position_id === null
                ? null
                : $positionsById->get($position->parent_position_id);
            $parent = $explicitParent instanceof Position
                && $this->isEligibleParent($explicitParent, $position)
                    ? $explicitParent
                    : $this->fallbackParent($position, $positions);
            $parentIds[$position->id] = $parent?->id;
        }

        $childrenByParent = [];
        $roots = [];

        foreach ($positions as $position) {
            $parentId = $parentIds[$position->id];

            if ($parentId === null) {
                $roots[] = $position;

                continue;
            }

            $childrenByParent[$parentId][] = $position;
        }

        $visitedPositionIds = [];

        return collect($roots)
            ->map(fn (Position $position): array => $this->buildPositionNode($position, $childrenByParent, $visitedPositionIds))
            ->all();
    }

    /** @param array<int, array<int, Position>> $childrenByParent
     * @param  array<int, bool>  $visitedPositionIds
     * @return array<string, mixed>
     */
    private function buildPositionNode(Position $position, array $childrenByParent, array &$visitedPositionIds): array
    {
        if (isset($visitedPositionIds[$position->id])) {
            return $this->positionNode($position);
        }

        $visitedPositionIds[$position->id] = true;
        $children = collect($childrenByParent[$position->id] ?? [])
            ->map(fn (Position $child): array => $this->buildPositionNode($child, $childrenByParent, $visitedPositionIds))
            ->all();

        return $this->positionNode($position, $children);
    }

    private function fallbackParent(Position $position, Collection $positions): ?Position
    {
        foreach (Position::allowedParentLevels($position->structuralJobLevel()) as $parentLevel) {
            $parent = $positions
                ->filter(fn (Position $candidate): bool => $candidate->id !== $position->id
                    && $candidate->structuralJobLevel() === $parentLevel)
                ->sort(fn (Position $left, Position $right): int => [
                    $this->scopePriority($position, $left),
                    $left->sort_order,
                    mb_strtolower($left->name),
                ] <=> [
                    $this->scopePriority($position, $right),
                    $right->sort_order,
                    mb_strtolower($right->name),
                ])
                ->first();

            if ($parent instanceof Position) {
                return $parent;
            }
        }

        return null;
    }

    private function isEligibleParent(Position $parent, Position $child): bool
    {
        return in_array(
            $parent->structuralJobLevel(),
            Position::allowedParentLevels($child->structuralJobLevel()),
            true,
        );
    }

    private function scopePriority(Position $child, Position $parent): int
    {
        if ($child->section_id !== null && $child->section_id === $parent->section_id) {
            return 1;
        }

        return $child->unit_id === $parent->unit_id ? 2 : 3;
    }

    /** @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function positionNode(Position $position, array $children = []): array
    {
        return [
            'key' => (string) $position->id,
            'level' => $position->structuralJobLevel(),
            'name' => $position->name,
            'formation_count' => $position->formation_count,
            'occupied_count' => $position->employees_count,
            'children' => $children,
            'department' => $position->unit->department->name,
        ];
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @return Collection<int, Position>
     */
    private function sortedPositions(Collection $positions): Collection
    {
        return $positions
            ->sort(fn (Position $left, Position $right): int => [
                Position::jobLevelPriority($left->structuralJobLevel()),
                $left->sort_order,
                mb_strtolower($left->name),
            ] <=> [
                Position::jobLevelPriority($right->structuralJobLevel()),
                $right->sort_order,
                mb_strtolower($right->name),
            ])
            ->values();
    }

    private function departmentFormationCount(Department $department): int
    {
        return (int) $department->units
            ->flatMap->positions
            ->sum('formation_count');
    }
}
