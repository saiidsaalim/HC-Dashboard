<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
            ->expectsOutput('Berhasil diterapkan: 1')
            ->expectsOutput('Dilewati: 2')
            ->expectsOutput('Gagal: 0')
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
            ->expectsOutput('Berhasil diterapkan: 0')
            ->expectsOutput('Dilewati: 3')
            ->expectsOutput('Gagal: 0')
            ->assertSuccessful();

        $this->assertTrue($appliedAt->equalTo($dueMovement->fresh()->employee_applied_at));
    }

    public function test_failed_stale_movement_does_not_block_the_next_valid_movement(): void
    {
        [$staleEmployee, $staleMovement] = $this->movement('STALE', today(), 'Approved');
        [$validEmployee, $validMovement] = $this->movement('VALID', today(), 'Approved');
        $staleEmployee->update(['employee_subgroup' => 'B4']);
        Log::spy();

        $this->artisan('personnel:apply-due-movements')
            ->expectsOutput('Berhasil diterapkan: 1')
            ->expectsOutput('Dilewati: 0')
            ->expectsOutput('Gagal: 1')
            ->assertFailed();

        $this->assertSame('Approved', $staleMovement->fresh()->verification_status);
        $this->assertNull($staleMovement->fresh()->employee_applied_at);
        $this->assertSame('Final', $validMovement->fresh()->verification_status);
        $this->assertSame($validMovement->new_position_id, $validEmployee->fresh()->position_id);
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($staleMovement): bool {
            return $message === 'Gagal menerapkan personnel movement.'
                && $context['movement_id'] === $staleMovement->id
                && $context['movement_type'] === 'EmployeeMutation'
                && $context['employee_id'] === $staleMovement->employee_id
                && ! array_key_exists('sap', $context);
        });
    }

    public function test_scheduler_prevents_overlapping_runs(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($scheduledEvent): bool => str_contains($scheduledEvent->command ?? '', 'personnel:apply-due-movements'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
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
