<?php

namespace App\Services\Personnel;

use App\Models\Position;
use Illuminate\Validation\ValidationException;

class LevelTransitionService
{
    public function ensurePromotion(string $currentLevel, string $newLevel): void
    {
        $comparison = $this->compare($currentLevel, $newLevel);

        if ($comparison === null) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Lama dan Level Baru tidak dapat dibandingkan. Gunakan level Band B1-B4 atau level struktural resmi.',
            ]);
        }

        if ($comparison !== 1) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Baru promosi harus lebih tinggi daripada Level Lama.',
            ]);
        }
    }

    public function ensureDemotion(string $currentLevel, string $newLevel): void
    {
        $comparison = $this->compare($currentLevel, $newLevel);

        if ($comparison === null) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Lama dan Level Baru tidak dapat dibandingkan. Gunakan level Band B1-B4 atau level struktural resmi.',
            ]);
        }

        if ($comparison !== -1) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Baru demosi harus lebih rendah daripada Level Lama.',
            ]);
        }
    }

    /**
     * Returns 1 when the new level is higher, -1 when lower, 0 when equal,
     * and null when the levels cannot be compared.
     */
    public function compare(string $currentLevel, string $newLevel): ?int
    {
        $currentLevel = trim($currentLevel);
        $newLevel = trim($newLevel);

        if (strcasecmp($currentLevel, $newLevel) === 0) {
            return 0;
        }

        if (Position::isValidJobLevel($currentLevel) && Position::isValidJobLevel($newLevel)) {
            return Position::jobLevelPriority($currentLevel) <=> Position::jobLevelPriority($newLevel);
        }

        $currentRank = $this->codedLevelRank($currentLevel);
        $newRank = $this->codedLevelRank($newLevel);

        if ($currentRank === null || $newRank === null) {
            return null;
        }

        return $currentRank <=> $newRank;
    }

    private function codedLevelRank(string $level): ?int
    {
        if (preg_match('/^(?:BAND\s*|B)[\s-]*([1-4])$/i', $level, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
