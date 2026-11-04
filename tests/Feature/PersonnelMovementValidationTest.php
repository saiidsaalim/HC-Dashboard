<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelMovementValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_options_use_the_official_hierarchy_from_highest_to_lowest(): void
    {
        foreach (['B2', ' B1 ', 'B1', '', null] as $index => $level) {
            Employee::query()->create([
                'sap' => 'LEVEL-'.$index,
                'employee_subgroup' => $level,
            ]);
        }

        $this->actingAs($this->superAdmin())->get(route('mutasi'))
            ->assertOk()
            ->assertViewHas('movementLevels', fn ($levels): bool => $levels->all() === [
                'General Manager',
                'Senior Manager',
                'Manager',
                'Associate',
                'Supervisor',
                'Non-Eselon',
            ]);
    }

    public function test_server_ignores_spoofed_employee_and_organization_snapshots(): void
    {
        [$employee, $target] = $this->employeeAndTarget();
        $employee->update(['employee_subgroup' => 'Manager']);

        $this->actingAs($this->superAdmin())->post(route('demosi.store'), [
            ...$this->payload($employee, $target),
            'level_baru' => 'Associate',
            'sap' => 'SAP-PALSU',
            'nama' => 'Nama Palsu',
            'departemen_lama' => 'Department Palsu',
            'departemen_baru' => 'Department Palsu',
            'jabatan_lama' => 'Position Palsu',
            'jabatan_baru' => 'Position Palsu',
            'level_lama' => 'LEVEL-PALSU',
        ])->assertRedirect(route('demosi'));

        $this->assertDatabaseHas('employee_demotions', [
            'sap' => $employee->sap,
            'nama' => 'Pegawai Resmi',
            'old_department_id' => $employee->department_id,
            'new_department_id' => $target['department']->id,
            'departemen_lama' => 'Department Lama',
            'departemen_baru' => 'Department Baru',
            'jabatan_lama' => 'Position Lama',
            'jabatan_baru' => 'Position Baru',
            'level_lama' => 'Manager',
            'level_baru' => 'Associate',
        ]);
    }

    public function test_mismatched_target_hierarchy_is_rejected(): void
    {
        [$employee, $target] = $this->employeeAndTarget();

        $this->actingAs($this->superAdmin())->post(route('promosi.store'), [
            ...$this->payload($employee, $target),
            'new_department_id' => $employee->department_id,
        ])->assertSessionHasErrors('new_department_id');

        $this->assertDatabaseCount('employee_promotions', 0);
    }

    public function test_level_baru_outside_the_official_hierarchy_is_rejected(): void
    {
        [$employee, $target] = $this->employeeAndTarget();

        $this->actingAs($this->superAdmin())->post(route('demosi.store'), [
            ...$this->payload($employee, $target),
            'level_baru' => 'LEVEL-TIDAK-TERDAFTAR',
        ])->assertSessionHasErrors([
            'level_baru' => 'Level Baru harus menggunakan urutan level resmi yang tersedia.',
        ]);

        $this->assertDatabaseCount('employee_demotions', 0);
    }

    public function test_mutation_without_an_organization_or_position_change_is_rejected(): void
    {
        [$employee] = $this->employeeAndTarget();

        $this->actingAs($this->superAdmin())->post(route('mutasi.store'), [
            ...$this->baseFields(),
            'employee_id' => $employee->id,
            'new_directorate_id' => $employee->directorate_id,
            'new_department_id' => $employee->department_id,
            'new_unit_id' => $employee->unit_id,
            'new_section_id' => $employee->section_id,
            'new_position_id' => $employee->position_id,
        ])->assertSessionHasErrors('new_position_id');

        $this->assertDatabaseCount('employee_mutations', 0);
    }

    /** @return array{Employee, array{directorate: Directorate, department: Department, unit: Unit, section: Section, position: Position}} */
    private function employeeAndTarget(): array
    {
        Employee::query()->updateOrCreate(
            ['sap' => 'LEVEL-OPTION-ASSOCIATE'],
            ['employee_subgroup' => 'Associate'],
        );
        $oldDirectorate = Directorate::query()->create(['code' => 'OLD-DIR', 'name' => 'Directorate Lama']);
        $oldDepartment = Department::query()->create([
            'directorate_id' => $oldDirectorate->id,
            'code' => 'OLD-DEP',
            'name' => 'Department Lama',
        ]);
        $oldUnit = Unit::query()->create(['department_id' => $oldDepartment->id, 'code' => 'OLD-U', 'name' => 'Unit Lama']);
        $oldSection = Section::query()->create(['unit_id' => $oldUnit->id, 'code' => 'OLD-S', 'name' => 'Section Lama']);
        $oldPosition = Position::query()->create([
            'unit_id' => $oldUnit->id,
            'section_id' => $oldSection->id,
            'code' => 'OLD-P',
            'name' => 'Position Lama',
        ]);
        $employee = Employee::query()->create([
            'sap' => 'SAP-RESMI',
            'directorate_id' => $oldDirectorate->id,
            'department_id' => $oldDepartment->id,
            'unit_id' => $oldUnit->id,
            'section_id' => $oldSection->id,
            'position_id' => $oldPosition->id,
            'employee_subgroup' => 'Manager',
        ]);
        $employee->forceFill(['name' => 'Pegawai Resmi'])->save();

        $directorate = Directorate::query()->create(['code' => 'NEW-DIR', 'name' => 'Directorate Baru']);
        $department = Department::query()->create([
            'directorate_id' => $directorate->id,
            'code' => 'NEW-DEP',
            'name' => 'Department Baru',
        ]);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'NEW-U', 'name' => 'Unit Baru']);
        $section = Section::query()->create(['unit_id' => $unit->id, 'code' => 'NEW-S', 'name' => 'Section Baru']);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'NEW-P',
            'name' => 'Position Baru',
        ]);

        return [$employee, compact('directorate', 'department', 'unit', 'section', 'position')];
    }

    /** @param array{directorate: Directorate, department: Department, unit: Unit, section: Section, position: Position} $target */
    private function payload(Employee $employee, array $target): array
    {
        return [
            ...$this->baseFields(),
            'employee_id' => $employee->id,
            'new_directorate_id' => $target['directorate']->id,
            'new_department_id' => $target['department']->id,
            'new_unit_id' => $target['unit']->id,
            'new_section_id' => $target['section']->id,
            'new_position_id' => $target['position']->id,
        ];
    }

    /** @return array<string, string> */
    private function baseFields(): array
    {
        return [
            'tmt' => '2026-11-02',
            'pg' => 'PG-1',
            'level_lama' => 'Manager',
            'jg_lama' => 'JG-1',
            'level_baru' => 'Associate',
            'jg_baru' => 'JG-2',
        ];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
