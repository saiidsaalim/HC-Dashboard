<?php

namespace Tests\Unit;

use App\Models\Position;
use App\Services\Employees\EmployeeOrganizationSyncService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PositionJobLevelTest extends TestCase
{
    #[DataProvider('employeeLevels')]
    public function test_it_normalizes_employee_level_for_master_organization(string $employeeLevel, string $expectedLevel): void
    {
        $this->assertSame(
            $expectedLevel,
            (new EmployeeOrganizationSyncService)->normalizeEmployeeLevel($employeeLevel),
        );
    }

    /** @return array<string, array{string, string}> */
    public static function employeeLevels(): array
    {
        return [
            'general manager' => ['General Manager', Position::JOB_LEVEL_GENERAL_MANAGER],
            'uppercase general manager' => ['GENERAL MANAGER', Position::JOB_LEVEL_GENERAL_MANAGER],
            'senior manager' => ['Senior Manager', Position::JOB_LEVEL_SENIOR_MANAGER],
            'uppercase senior manager' => ['SENIOR MANAGER', Position::JOB_LEVEL_SENIOR_MANAGER],
            'manager' => ['Manager', Position::JOB_LEVEL_MANAGER],
            'uppercase manager' => ['MANAGER', Position::JOB_LEVEL_MANAGER],
            'supervisor' => ['Supervisor', Position::JOB_LEVEL_STAFF],
            'senior officer' => ['Senior Officer', Position::JOB_LEVEL_STAFF],
            'officer' => ['Officer', Position::JOB_LEVEL_STAFF],
            'junior officer' => ['Junior Officer', Position::JOB_LEVEL_STAFF],
            'analyst' => ['Analyst', Position::JOB_LEVEL_STAFF],
            'engineer' => ['Engineer', Position::JOB_LEVEL_STAFF],
            'staff' => ['Staff', Position::JOB_LEVEL_STAFF],
            'multiple whitespace' => ['  Senior   Manager  ', Position::JOB_LEVEL_SENIOR_MANAGER],
            'managerial is not manager' => ['Managerial Capability Specialist', Position::JOB_LEVEL_STAFF],
            'unverified abbreviation is staff' => ['GM', Position::JOB_LEVEL_STAFF],
        ];
    }
}
