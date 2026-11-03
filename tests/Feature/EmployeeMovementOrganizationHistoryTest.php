<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmployeeMovementOrganizationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_movement_preserves_organization_snapshots_and_exposes_relations(): void
    {
        $directorate = Directorate::query()->create(['code' => 'DIR', 'name' => 'Directorate Lama']);
        $department = Department::query()->create([
            'directorate_id' => $directorate->id,
            'code' => 'DEP',
            'name' => 'Department Lama',
        ]);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'UNIT', 'name' => 'Unit Lama']);
        $section = Section::query()->create(['unit_id' => $unit->id, 'code' => 'SEC', 'name' => 'Section Lama']);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'code' => 'POS',
            'name' => 'Position Lama',
        ]);
        Employee::query()->create(['sap' => '10001']);

        foreach ([EmployeePromotion::class, EmployeeDemotion::class, EmployeeMutation::class] as $modelClass) {
            $movement = $modelClass::query()->create([
                'sap' => '10001',
                'nama' => 'Pegawai',
                'departemen_lama' => 'Snapshot Department',
                'departemen_baru' => 'Snapshot Department',
                'jabatan_lama' => 'Snapshot Position',
                'jabatan_baru' => 'Snapshot Position',
                'tmt' => '2026-11-02',
                'pg' => 'PG',
                'level_lama' => 'A',
                'jg_lama' => '1',
                'level_baru' => 'B',
                'jg_baru' => '2',
                'old_directorate_id' => $directorate->id,
                'new_directorate_id' => $directorate->id,
                'old_department_id' => $department->id,
                'new_department_id' => $department->id,
                'old_unit_id' => $unit->id,
                'new_unit_id' => $unit->id,
                'old_section_id' => $section->id,
                'new_section_id' => $section->id,
                'old_position_id' => $position->id,
                'new_position_id' => $position->id,
                'direktorat_lama' => 'Snapshot Directorate',
                'direktorat_baru' => 'Snapshot Directorate',
                'unit_lama' => 'Snapshot Unit',
                'unit_baru' => 'Snapshot Unit',
                'section_lama' => 'Snapshot Section',
                'section_baru' => 'Snapshot Section',
                'employee_applied_at' => '2026-11-02 12:00:00',
            ]);

            $movement->load([
                'oldDirectorate', 'newDirectorate', 'oldDepartment', 'newDepartment',
                'oldUnit', 'newUnit', 'oldSection', 'newSection', 'oldPosition', 'newPosition',
            ]);

            $this->assertTrue($movement->oldDirectorate->is($directorate));
            $this->assertTrue($movement->newDepartment->is($department));
            $this->assertTrue($movement->oldUnit->is($unit));
            $this->assertTrue($movement->newSection->is($section));
            $this->assertTrue($movement->oldPosition->is($position));
            $this->assertSame('Snapshot Directorate', $movement->direktorat_lama);
            $this->assertInstanceOf(Carbon::class, $movement->employee_applied_at);
        }
    }

    public function test_nullable_columns_keep_legacy_movement_rows_valid(): void
    {
        Employee::query()->create(['sap' => '10002']);

        $promotion = EmployeePromotion::query()->create([
            'sap' => '10002',
            'nama' => 'Pegawai Lama',
            'departemen_lama' => 'Department Lama',
            'departemen_baru' => 'Department Baru',
            'jabatan_lama' => 'Position Lama',
            'jabatan_baru' => 'Position Baru',
            'tmt' => '2026-11-02',
            'pg' => 'PG',
            'level_lama' => 'A',
            'jg_lama' => '1',
            'level_baru' => 'B',
            'jg_baru' => '2',
        ]);

        $this->assertNull($promotion->old_directorate_id);
        $this->assertNull($promotion->new_position_id);
        $this->assertDatabaseHas('employee_promotions', [
            'id' => $promotion->id,
            'departemen_lama' => 'Department Lama',
        ]);
    }
}
