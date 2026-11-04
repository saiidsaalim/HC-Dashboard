<?php

namespace App\Services\Personnel;

use App\Models\Position;
use Illuminate\Validation\ValidationException;

class LevelTransitionService
{
    public function ensurePromotion(string $currentLevel, string $newLevel): void
    {
        if ($this->compare($currentLevel, $newLevel) !== 1) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Baru promosi harus lebih tinggi daripada Level Lama.',
            ]);
        }
    }

    public function ensureDemotion(string $currentLevel, string $newLevel): void
    {
        if ($this->compare($currentLevel, $newLevel) !== -1) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Baru demosi harus lebih rendah daripada Level Lama.',
            ]);
        }
    }

    /**
     * Returns 1 when the new level is higher, -1 when lower, and 0 when equal or incomparable.
     */
    public function compare(string $currentLevel, string $newLevel): int
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

        if ($currentRank === null || $newRank === null || $currentRank['prefix'] !== $newRank['prefix']) {
            return 0;
        }

        return $newRank['rank'] <=> $currentRank['rank'];
    }

    /** @return array{prefix: string, rank: int}|null */
    private function codedLevelRank(string $level): ?array
    {
        if (preg_match('/^([A-Z]+)[\s-]*(\d+)$/i', $level, $matches) !== 1) {
            return null;
        }

        return [
            'prefix' => strtoupper($matches[1]),
            'rank' => (int) $matches[2],
        ];
    }
}
