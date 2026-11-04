<?php

namespace App\Services\Personnel;

use Illuminate\Validation\ValidationException;

class LevelTransitionService
{
    /** @var array<string, int> */
    public const LEVEL_PRIORITIES = [
        'General Manager' => 1,
        'Senior Manager' => 2,
        'Manager' => 3,
        'Associate' => 4,
        'Supervisor' => 5,
        'Non-Eselon' => 6,
    ];

    /** @return array<int, string> */
    public function levels(): array
    {
        return array_keys(self::LEVEL_PRIORITIES);
    }

    public function ensurePromotion(string $currentLevel, string $newLevel): void
    {
        $comparison = $this->compare($currentLevel, $newLevel);

        if ($comparison === null) {
            throw ValidationException::withMessages([
                'level_baru' => 'Level Lama dan Level Baru tidak dapat dibandingkan. Gunakan urutan level resmi: General Manager, Senior Manager, Manager, Associate, Supervisor, atau Non-Eselon.',
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
                'level_baru' => 'Level Lama dan Level Baru tidak dapat dibandingkan. Gunakan urutan level resmi: General Manager, Senior Manager, Manager, Associate, Supervisor, atau Non-Eselon.',
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
        $currentRank = $this->levelPriority($currentLevel);
        $newRank = $this->levelPriority($newLevel);

        if ($currentRank === null || $newRank === null) {
            return null;
        }

        return $currentRank <=> $newRank;
    }

    private function levelPriority(string $level): ?int
    {
        $normalizedLevel = preg_replace('/\s+/', ' ', trim($level));
        if ($normalizedLevel === null) {
            return null;
        }

        $normalizedLevel = preg_replace('/\s*-\s*/', ' ', $normalizedLevel);
        if ($normalizedLevel === null) {
            return null;
        }

        foreach (self::LEVEL_PRIORITIES as $officialLevel => $priority) {
            $normalizedOfficialLevel = str_replace('-', ' ', $officialLevel);

            if (strcasecmp($normalizedLevel, $normalizedOfficialLevel) === 0) {
                return $priority;
            }
        }

        return null;
    }
}
