<?php

namespace App\Models;

use App\Enums\CriticalPositionCoverageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'unit_id',
    'section_id',
    'parent_position_id',
    'code',
    'external_position_id',
    'name',
    'job_level',
    'formation_count',
    'formation_is_confirmed',
    'sort_order',
    'active',
])]
class Position extends Model
{
    public const JOB_LEVEL_GENERAL_MANAGER = 'General Manager';

    public const JOB_LEVEL_SENIOR_MANAGER = 'Senior Manager';

    public const JOB_LEVEL_MANAGER = 'Manager';

    public const JOB_LEVEL_STAFF = 'Staff';

    public const JOB_LEVEL_PRIORITIES = [
        self::JOB_LEVEL_GENERAL_MANAGER => 1,
        self::JOB_LEVEL_SENIOR_MANAGER => 2,
        self::JOB_LEVEL_MANAGER => 3,
        self::JOB_LEVEL_STAFF => 4,
    ];

    public function structuralJobLevel(): string
    {
        return self::isValidJobLevel($this->job_level)
            ? (string) $this->job_level
            : self::JOB_LEVEL_STAFF;
    }

    public static function isValidJobLevel(?string $jobLevel): bool
    {
        return array_key_exists((string) $jobLevel, self::JOB_LEVEL_PRIORITIES);
    }

    public static function jobLevelPriority(?string $jobLevel): int
    {
        return self::JOB_LEVEL_PRIORITIES[$jobLevel] ?? self::JOB_LEVEL_PRIORITIES[self::JOB_LEVEL_STAFF];
    }

    /** @return array<int, string> */
    public static function allowedParentLevels(string $jobLevel): array
    {
        return match ($jobLevel) {
            self::JOB_LEVEL_SENIOR_MANAGER => [self::JOB_LEVEL_GENERAL_MANAGER],
            self::JOB_LEVEL_MANAGER => [self::JOB_LEVEL_SENIOR_MANAGER, self::JOB_LEVEL_GENERAL_MANAGER],
            self::JOB_LEVEL_STAFF => [self::JOB_LEVEL_MANAGER, self::JOB_LEVEL_SENIOR_MANAGER, self::JOB_LEVEL_GENERAL_MANAGER],
            default => [],
        };
    }

    public function wouldCreateCircularParent(int $parentPositionId): bool
    {
        if (! $this->exists) {
            return false;
        }

        $visitedPositionIds = [];
        $currentPositionId = $parentPositionId;

        while ($currentPositionId !== 0) {
            if ($currentPositionId === $this->getKey()) {
                return true;
            }

            if (isset($visitedPositionIds[$currentPositionId])) {
                return true;
            }

            $visitedPositionIds[$currentPositionId] = true;
            $currentPositionId = (int) (self::query()
                ->whereKey($currentPositionId)
                ->value('parent_position_id') ?? 0);
        }

        return false;
    }

    /** @return array<int, int> */
    public function descendantIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        $descendantIds = [];
        $pendingParentIds = [$this->getKey()];

        while ($pendingParentIds !== []) {
            $childIds = self::query()
                ->whereIn('parent_position_id', $pendingParentIds)
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();
            $childIds = array_values(array_diff($childIds, $descendantIds));

            if ($childIds === []) {
                break;
            }

            $descendantIds = [...$descendantIds, ...$childIds];
            $pendingParentIds = $childIds;
        }

        return $descendantIds;
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'formation_is_confirmed' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_position_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_position_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function criticalPosition(): HasOne
    {
        return $this->hasOne(CriticalPosition::class);
    }

    public function successors(): HasMany
    {
        return $this->hasMany(Successor::class, 'target_position_id');
    }

    public function wlaAssessments(): HasMany
    {
        return $this->hasMany(WlaAssessment::class);
    }

    public function incumbentCount(): int
    {
        if (array_key_exists('employees_count', $this->attributes)) {
            return (int) $this->attributes['employees_count'];
        }

        if ($this->relationLoaded('employees')) {
            return $this->employees->count();
        }

        return $this->employees()->count();
    }

    public function coverageStatus(): CriticalPositionCoverageStatus
    {
        return CriticalPositionCoverageStatus::fromCounts(
            $this->incumbentCount(),
            max(1, (int) $this->formation_count),
        );
    }

    public function successorCount(): int
    {
        if (array_key_exists('successors_count', $this->attributes)) {
            return (int) $this->attributes['successors_count'];
        }

        if ($this->relationLoaded('successors')) {
            return $this->successors->count();
        }

        return $this->successors()->count();
    }

    public function readyNowSuccessorCount(): int
    {
        if ($this->relationLoaded('successors')) {
            return $this->successors
                ->filter(fn (Successor $successor): bool => mb_strtolower(trim((string) $successor->succession_readiness)) === 'ready now')
                ->count();
        }

        return $this->successors()->whereRaw('LOWER(TRIM(succession_readiness)) = ?', ['ready now'])->count();
    }

    public function bestSuccessorReadiness(): ?string
    {
        $priorities = array_flip(Employee::SUCCESSION_READINESS_OPTIONS);
        $readinessValues = $this->relationLoaded('successors')
            ? $this->successors->pluck('succession_readiness')
            : $this->successors()->pluck('succession_readiness');

        return $readinessValues
            ->map(fn (mixed $readiness): string => mb_strtolower(trim((string) $readiness)))
            ->filter()
            ->sortBy(fn (string $readiness): int => $priorities[$readiness] ?? PHP_INT_MAX)
            ->first();
    }

    public function scopeWhereCoverageStatus(Builder $query, CriticalPositionCoverageStatus $status): Builder
    {
        $employeeCount = '(select count(*) from employees where employees.position_id = positions.id)';

        return match ($status) {
            CriticalPositionCoverageStatus::Vacant => $query->whereRaw("{$employeeCount} = 0"),
            CriticalPositionCoverageStatus::PartiallyCovered => $query
                ->whereRaw("{$employeeCount} > 0")
                ->whereRaw("{$employeeCount} < positions.formation_count"),
            CriticalPositionCoverageStatus::Covered => $query->whereRaw("{$employeeCount} = positions.formation_count"),
            CriticalPositionCoverageStatus::OverCapacity => $query->whereRaw("{$employeeCount} > positions.formation_count"),
        };
    }
}
