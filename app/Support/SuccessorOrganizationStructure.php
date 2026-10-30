<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

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
        $generalManagers = $department->units
            ->flatMap->positions
            ->filter(fn (Position $position): bool => $position->job_level === 'General Manager')
            ->values()
            ->map(fn (Position $position): array => $this->positionNode($position));
        $unitRoots = $department->units
            ->flatMap(fn (Unit $unit): array => $this->unitRoots($unit))
            ->values()
            ->all();

        if ($generalManagers->isEmpty()) {
            return $unitRoots;
        }

        return $generalManagers
            ->map(function (array $generalManager, int $index) use ($unitRoots): array {
                $generalManager['children'] = $index === 0 ? $unitRoots : [];

                return $generalManager;
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function unitRoots(Unit $unit): array
    {
        $positions = $unit->positions
            ->reject(fn (Position $position): bool => $position->job_level === 'General Manager')
            ->values();
        $seniorManagers = $positions
            ->filter(fn (Position $position): bool => $position->job_level === 'Senior Manager')
            ->values();
        $positionsBelowSeniorManager = $this->managerRoots($positions
            ->reject(fn (Position $position): bool => $position->job_level === 'Senior Manager')
            ->values());

        if ($seniorManagers->isEmpty()) {
            return $positionsBelowSeniorManager;
        }

        return $seniorManagers
            ->map(function (Position $seniorManager, int $index) use ($positionsBelowSeniorManager): array {
                return $this->positionNode(
                    $seniorManager,
                    $index === 0 ? $positionsBelowSeniorManager : [],
                );
            })
            ->all();
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @return array<int, array<string, mixed>>
     */
    private function managerRoots(Collection $positions): array
    {
        return $positions
            ->groupBy(fn (Position $position): string => (string) ($position->section_id ?? ''))
            ->flatMap(function (Collection $sectionPositions): array {
                $managers = $sectionPositions
                    ->filter(fn (Position $position): bool => $position->job_level === 'Manager')
                    ->values();
                $positionsBelowManager = $sectionPositions
                    ->reject(fn (Position $position): bool => $position->job_level === 'Manager')
                    ->map(fn (Position $position): array => $this->positionNode($position))
                    ->values()
                    ->all();

                if ($managers->isEmpty()) {
                    return $positionsBelowManager;
                }

                return $managers
                    ->map(function (Position $manager, int $index) use ($positionsBelowManager): array {
                        return $this->positionNode(
                            $manager,
                            $index === 0 ? $positionsBelowManager : [],
                        );
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function positionNode(Position $position, array $children = []): array
    {
        return [
            'key' => (string) $position->id,
            'level' => $position->job_level ?? 'Staff',
            'name' => $position->name,
            'formation_count' => $position->formation_count,
            'occupied_count' => $position->employees_count,
            'children' => $children,
            'department' => $position->unit->department->name,
        ];
    }

    private function departmentFormationCount(Department $department): int
    {
        return (int) $department->units
            ->flatMap->positions
            ->sum('formation_count');
    }
}
