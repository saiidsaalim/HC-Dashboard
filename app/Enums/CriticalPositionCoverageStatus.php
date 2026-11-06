<?php

namespace App\Enums;

enum CriticalPositionCoverageStatus: string
{
    case Vacant = 'vacant';
    case PartiallyCovered = 'partially_covered';
    case Covered = 'covered';
    case OverCapacity = 'over_capacity';

    public static function fromCounts(int $incumbentCount, int $formationCount): self
    {
        return match (true) {
            $incumbentCount === 0 => self::Vacant,
            $incumbentCount < $formationCount => self::PartiallyCovered,
            $incumbentCount === $formationCount => self::Covered,
            default => self::OverCapacity,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'Vacant',
            self::PartiallyCovered => 'Partially Covered',
            self::Covered => 'Covered',
            self::OverCapacity => 'Over Capacity',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Vacant => 'danger',
            self::PartiallyCovered => 'warning',
            self::Covered => 'success',
            self::OverCapacity => 'info',
        };
    }
}
