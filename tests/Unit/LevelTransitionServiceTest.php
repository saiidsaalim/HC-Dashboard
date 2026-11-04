<?php

namespace Tests\Unit;

use App\Services\Personnel\LevelTransitionService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LevelTransitionServiceTest extends TestCase
{
    public function test_it_exposes_the_official_levels_from_highest_to_lowest(): void
    {
        $this->assertSame([
            'General Manager',
            'Senior Manager',
            'Manager',
            'Associate',
            'Supervisor',
            'Non-Eselon',
        ], (new LevelTransitionService)->levels());
    }

    #[DataProvider('validTransitions')]
    public function test_it_accepts_transitions_using_the_official_hierarchy(
        string $movement,
        string $currentLevel,
        string $newLevel,
    ): void {
        $service = new LevelTransitionService;

        if ($movement === 'promotion') {
            $service->ensurePromotion($currentLevel, $newLevel);
        } else {
            $service->ensureDemotion($currentLevel, $newLevel);
        }

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, string, string}> */
    public static function validTransitions(): array
    {
        return [
            'promotion to general manager' => ['promotion', 'Senior Manager', 'General Manager'],
            'promotion to senior manager' => ['promotion', 'Manager', 'Senior Manager'],
            'promotion to manager' => ['promotion', 'Associate', 'Manager'],
            'promotion to associate' => ['promotion', 'Supervisor', 'Associate'],
            'promotion to supervisor' => ['promotion', 'Non-Eselon', 'Supervisor'],
            'demotion to senior manager' => ['demotion', 'General Manager', 'Senior Manager'],
            'demotion to manager' => ['demotion', 'Senior Manager', 'Manager'],
            'demotion to associate' => ['demotion', 'Manager', 'Associate'],
            'demotion to supervisor' => ['demotion', 'Associate', 'Supervisor'],
            'demotion to non eselon' => ['demotion', 'Supervisor', 'Non-Eselon'],
            'case whitespace and non eselon separator are normalized' => ['promotion', '  NON ESELON ', ' SUPERVISOR '],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_it_rejects_equal_reversed_and_unofficial_levels(
        string $movement,
        string $currentLevel,
        string $newLevel,
    ): void {
        $service = new LevelTransitionService;

        $this->expectException(ValidationException::class);

        if ($movement === 'promotion') {
            $service->ensurePromotion($currentLevel, $newLevel);
        } else {
            $service->ensureDemotion($currentLevel, $newLevel);
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidTransitions(): array
    {
        return [
            'equal promotion' => ['promotion', 'Manager', 'Manager'],
            'reversed promotion' => ['promotion', 'Manager', 'Associate'],
            'reversed demotion' => ['demotion', 'Supervisor', 'Associate'],
            'old band level' => ['promotion', 'B2', 'B1'],
            'unknown level' => ['demotion', 'Staff', 'Non-Eselon'],
        ];
    }
}
