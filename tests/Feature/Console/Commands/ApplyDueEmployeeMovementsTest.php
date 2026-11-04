<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyDueEmployeeMovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_applies_only_due_approved_movements_and_is_idempotent(): void
    {
        [$dueEmployee, $dueMovement] = $this->movement('DUE', today(), 'Approved');
        [$futureEmployee, $futureMovement] = $this->movement('FUTURE', today()->addDay(), 'Approved');
        [$pendingEmployee, $pendingMovement] = $this->movement('PENDING', today(), 'Pending');

        $this->artisan('personnel:apply-due-movements')
            ->expectsOutput('1 perubahan pegawai diterapkan.')
            ->assertSuccessful();

        $dueMovement->refresh();
        $this->assertSame('Final', $dueMovement->verification_status);
        $this->assertNotNull($dueMovement->employee_applied_at);
        $this->assertSame($dueMovement->new_position_id, $dueEmployee->fresh()->position_id);
        $this->assertSame('B2', $dueEmployee->fresh()->employee_subgroup);
        $this->assertSame($futureMovement->old_position_id, $futureEmployee->fresh()->position_id);
        $this->assertNull($futureMovement->fresh()->employee_applied_at);
        $this->assertSame($pendingMovement->old_position_id, $pendingEmployee->fresh()->position_id);
        $this->assertNull($pendingMovement->fresh()->employee_applied_at);

        $appliedAt = $dueMovement->employee_applied_at;
        $this->artisan('personnel:apply-due-movements')
            ->expectsOutput('0 perubahan pegawai diterapkan.')
            ->assertSuccessful();

        $this->assertTrue($appliedAt->equalTo($dueMovement->fresh()->employee_applied_at));
    }

    /** @return array{Employee, EmployeeMutation} */
    private function movement(string $suffix, mixed $tmt, string $status): array
    {
        $oldDirectorate = Directorate::query()->create(['code' => "OLD-DIR-{$suffix}", 'name' => "Old Directorate {$suffix}"]);
        $oldDepartment = Department::query()->create(['directorate_id' => $oldDirectorate->id, 'code' => "OLD-DEP-{$suffix}", 'name' => "Old Department {$suffix}"]);
        $oldUnit = Unit::query()->create(['department_id' => $oldDepartment->id, 'code' => "OLD-UNIT-{$suffix}", 'name' => "Old Unit {$suffix}"]);
        $oldPosition = Position::query()->create(['unit_id' => $oldUnit->id, 'code' => "OLD-POS-{$suffix}", 'name' => "Old Position {$suffix}"]);

        $newDirectorate = Directorate::query()->create(['code' => "NEW-DIR-{$suffix}", 'name' => "New Directorate {$suffix}"]);
        $newDepartment = Department::query()->create(['directorate_id' => $newDirectorate->id, 'code' => "NEW-DEP-{$suffix}", 'name' => "New Department {$suffix}"]);
        $newUnit = Unit::query()->create(['department_id' => $newDepartment->id, 'code' => "NEW-UNIT-{$suffix}", 'name' => "New Unit {$suffix}"]);
        $newPosition = Position::query()->create(['unit_id' => $newUnit->id, 'code' => "NEW-POS-{$suffix}", 'name' => "New Position {$suffix}"]);

        $employee = Employee::query()->create([
            'sap' => "SAP-{$suffix}",
            'directorate_id' => $oldDirectorate->id,
            'department_id' => $oldDepartment->id,
            'unit_id' => $oldUnit->id,
            'position_id' => $oldPosition->id,
            'employee_subgroup' => 'B1',
        ]);
        $employee->forceFill(['name' => "Employee {$suffix}"])->save();

        $snapshot = app(PersonnelMovementSnapshotService::class)->fromEmployee($employee, [
            'new_directorate_id' => $newDirectorate->id,
            'new_department_id' => $newDepartment->id,
            'new_unit_id' => $newUnit->id,
            'new_section_id' => null,
            'new_position_id' => $newPosition->id,
            'tmt' => $tmt,
            'pg' => 'PG-1',
            'jg_lama' => 'JG-1',
            'level_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ]);
        $movement = EmployeeMutation::query()->create([
            ...$snapshot,
            'verification_status' => $status,
            'finalized_at' => $status === 'Approved' ? now() : null,
        ]);

        return [$employee, $movement];
    }
}
