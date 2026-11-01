<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_id',
    'section_id',
    'parent_position_id',
    'code',
    'name',
    'job_level',
    'formation_count',
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

    public function successors(): HasMany
    {
        return $this->hasMany(Successor::class, 'target_position_id');
    }

    public function wlaAssessments(): HasMany
    {
        return $this->hasMany(WlaAssessment::class);
    }
}
