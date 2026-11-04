<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinalPersonnelActionProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('personnelModules')]
    public function test_authorized_user_can_update_pending_record(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $personnelAction = $this->createPersonnelAction($modelClass, '100001', 'Pending');

        $this->actingAs($superAdmin)
            ->put(route($updateRoute, $personnelAction), $this->validPayload('100001', 'Nama Diperbarui', $modelClass))
            ->assertRedirectToRoute($indexRoute);

        $this->assertDatabaseHas($personnelAction->getTable(), [
            'id' => $personnelAction->getKey(),
            'nama' => 'Nama Awal',
        ]);
    }

    #[DataProvider('personnelModules')]
    public function test_authorized_user_can_delete_pending_record(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $personnelAction = $this->createPersonnelAction($modelClass, '100002', 'Pending');

        $this->actingAs($superAdmin)
            ->delete(route($destroyRoute), [$idsField => [$personnelAction->getKey()]])
            ->assertRedirectToRoute($indexRoute);

        $this->assertDatabaseMissing($personnelAction->getTable(), [
            'id' => $personnelAction->getKey(),
        ]);
    }

    #[DataProvider('personnelModules')]
    public function test_final_record_cannot_be_updated(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $personnelAction = $this->createPersonnelAction($modelClass, '100003', 'Final');

        $response = $this->actingAs($superAdmin)
            ->from(route($indexRoute))
            ->put(route($updateRoute, $personnelAction), $this->validPayload('100003', 'Nama Diperbarui', $modelClass));

        $response->assertForbidden();

        $this->assertDatabaseHas($personnelAction->getTable(), [
            'id' => $personnelAction->getKey(),
            'nama' => 'Nama Awal',
            'verification_status' => 'Final',
        ]);
    }

    #[DataProvider('personnelModules')]
    public function test_final_record_cannot_be_deleted(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $personnelAction = $this->createPersonnelAction($modelClass, '100004', 'Final');

        $response = $this->actingAs($superAdmin)
            ->from(route($indexRoute))
            ->delete(route($destroyRoute), [$idsField => [$personnelAction->getKey()]]);

        if ($modelClass === EmployeePromotion::class) {
            $response->assertRedirect(route($indexRoute))->assertSessionHasErrors('status');
        } else {
            $response->assertForbidden();
        }

        $this->assertDatabaseHas($personnelAction->getTable(), [
            'id' => $personnelAction->getKey(),
            'verification_status' => 'Final',
        ]);
    }

    #[DataProvider('personnelModules')]
    public function test_bulk_delete_is_cancelled_when_selection_contains_final_record(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $pendingAction = $this->createPersonnelAction($modelClass, '100005', 'Pending');
        $finalAction = $this->createPersonnelAction($modelClass, '100006', 'Final');

        $response = $this->actingAs($superAdmin)
            ->from(route($indexRoute))
            ->delete(route($destroyRoute), [
                $idsField => [$pendingAction->getKey(), $finalAction->getKey()],
            ]);

        if ($modelClass === EmployeePromotion::class) {
            $response->assertRedirect(route($indexRoute))->assertSessionHasErrors('status');
        } else {
            $response->assertForbidden();
        }

        $this->assertDatabaseHas($pendingAction->getTable(), ['id' => $pendingAction->getKey()]);
        $this->assertDatabaseHas($finalAction->getTable(), ['id' => $finalAction->getKey()]);
    }

    #[DataProvider('personnelModules')]
    public function test_final_record_does_not_show_edit_or_delete_controls(
        string $modelClass,
        string $indexRoute,
        string $updateRoute,
        string $destroyRoute,
        string $idsField,
    ): void {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $pendingAction = $this->createPersonnelAction($modelClass, '100007', 'Pending');
        $finalAction = $this->createPersonnelAction($modelClass, '100008', 'Final');

        $this->actingAs($superAdmin)
            ->get(route($indexRoute))
            ->assertOk()
            ->assertSee('action="'.route($updateRoute, $pendingAction).'"', escape: false)
            ->assertDontSee('action="'.route($updateRoute, $finalAction).'"', escape: false)
            ->assertSee('name="'.$idsField.'[]" value="'.$pendingAction->getKey().'"', escape: false)
            ->assertDontSee('name="'.$idsField.'[]" value="'.$finalAction->getKey().'"', escape: false);
    }

    /**
     * @return array<string, array{class-string<Model>, string, string, string, string}>
     */
    public static function personnelModules(): array
    {
        return [
            'mutation' => [
                EmployeeMutation::class,
                'mutasi',
                'mutasi.update',
                'mutasi.destroy-many',
                'mutation_ids',
            ],
            'promotion' => [
                EmployeePromotion::class,
                'promosi',
                'promosi.update',
                'promosi.destroy-many',
                'promotion_ids',
            ],
            'demotion' => [
                EmployeeDemotion::class,
                'demosi',
                'demosi.update',
                'demosi.destroy-many',
                'demotion_ids',
            ],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function createPersonnelAction(string $modelClass, string $sap, string $status): Model
    {
        $employee = Employee::create(['sap' => $sap, 'employee_subgroup' => 'Level 1']);
        $employee->forceFill(['name' => 'Nama Awal'])->save();

        return $modelClass::query()->create([
            ...$this->validPayload($sap, 'Nama Awal', $modelClass),
            'verification_status' => $status,
            'finalized_at' => $status === 'Final' ? now() : null,
        ]);
    }

    /** @return array<string, int|string|null> */
    /** @param class-string<Model> $modelClass */
    private function validPayload(string $sap, string $name, string $modelClass): array
    {
        $currentLevel = $modelClass === EmployeeDemotion::class ? 'Level 2' : 'Level 1';
        $newLevel = $modelClass === EmployeeDemotion::class ? 'Level 1' : 'Level 2';
        Employee::query()->updateOrCreate(
            ['sap' => 'LEVEL-OPTION-'.$newLevel],
            ['employee_subgroup' => $newLevel],
        );
        $employee = Employee::query()->where('sap', $sap)->firstOrFail();
        $oldDirectorate = Directorate::query()->firstOrCreate(['code' => 'OLD-DIR-'.$sap], ['name' => 'Directorate Lama']);
        $oldDepartment = Department::query()->firstOrCreate(['code' => 'OLD-DEP-'.$sap], [
            'directorate_id' => $oldDirectorate->id,
            'name' => 'Department Lama',
        ]);
        $oldUnit = Unit::query()->firstOrCreate(['department_id' => $oldDepartment->id, 'code' => 'OLD-U-'.$sap], ['name' => 'Unit Lama']);
        $oldPosition = Position::query()->firstOrCreate(['unit_id' => $oldUnit->id, 'code' => 'OLD-P-'.$sap], ['name' => 'Position Lama']);
        $newDirectorate = Directorate::query()->firstOrCreate(['code' => 'NEW-DIR-'.$sap], ['name' => 'Directorate Baru']);
        $newDepartment = Department::query()->firstOrCreate(['code' => 'NEW-DEP-'.$sap], [
            'directorate_id' => $newDirectorate->id,
            'name' => 'Department Baru',
        ]);
        $newUnit = Unit::query()->firstOrCreate(['department_id' => $newDepartment->id, 'code' => 'NEW-U-'.$sap], ['name' => 'Unit Baru']);
        $newPosition = Position::query()->firstOrCreate(['unit_id' => $newUnit->id, 'code' => 'NEW-P-'.$sap], ['name' => 'Position Baru']);
        $employee->update([
            'employee_subgroup' => $currentLevel,
            'directorate_id' => $oldDirectorate->id,
            'department_id' => $oldDepartment->id,
            'unit_id' => $oldUnit->id,
            'position_id' => $oldPosition->id,
        ]);

        return [
            'employee_id' => $employee->id,
            'old_directorate_id' => $oldDirectorate->id,
            'old_department_id' => $oldDepartment->id,
            'old_unit_id' => $oldUnit->id,
            'old_section_id' => null,
            'old_position_id' => $oldPosition->id,
            'new_directorate_id' => $newDirectorate->id,
            'new_department_id' => $newDepartment->id,
            'new_unit_id' => $newUnit->id,
            'new_section_id' => null,
            'new_position_id' => $newPosition->id,
            'sap' => $sap,
            'nama' => $name,
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Jabatan Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Jabatan Baru',
            'tmt' => '2026-10-01',
            'pg' => 'PG 1',
            'level_lama' => $currentLevel,
            'jg_lama' => 'JG 1',
            'level_baru' => $newLevel,
            'jg_baru' => 'JG 2',
        ];
    }

    /**
     * Prepare the in-memory test database without invoking migrate:fresh.
     */
    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }
}
