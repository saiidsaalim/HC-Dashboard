<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeOrganizationSyncWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_preview_or_apply_employee_organization_sync(): void
    {
        $this->get(route('data-pegawai.organization-sync.preview'))
            ->assertRedirect(route('login'));

        $this->post(route('data-pegawai.organization-sync.apply'))
            ->assertRedirect(route('login'));
    }

    #[DataProvider('rolesWithoutEmployeeManagement')]
    public function test_non_super_admin_cannot_preview_or_apply_employee_organization_sync(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)->get(route('data-pegawai.organization-sync.preview'))
            ->assertForbidden();
        $this->actingAs($user)->post(route('data-pegawai.organization-sync.apply'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_sync_button_while_other_roles_do_not(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($superAdmin)->get(route('data-pegawai'))
            ->assertOk()
            ->assertSee('Sinkronkan Organisasi')
            ->assertSee(route('data-pegawai.organization-sync.preview'), escape: false);

        $this->actingAs($admin)->get(route('data-pegawai'))
            ->assertOk()
            ->assertDontSee('Sinkronkan Organisasi');
    }

    public function test_preview_is_read_only_and_shows_safe_mapping_summary(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::create($this->employeeAttributes('WEB-SYNC-001', 'Operations', 'Control Room', 'Operator'));

        $this->actingAs($superAdmin)->get(route('data-pegawai.organization-sync.preview'))
            ->assertOk()
            ->assertSee('Preview ini tidak mengubah database.')
            ->assertSee('TXT_DEPT')
            ->assertSee('TXT_BIRO')
            ->assertSee('Organizational Unit tidak digunakan')
            ->assertSee('ready_create_master')
            ->assertSee('Terapkan Sinkronisasi');

        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertNull($employee->fresh()->department_id);
        $this->assertNull($employee->fresh()->unit_id);
        $this->assertNull($employee->fresh()->position_id);
    }

    public function test_super_admin_can_apply_safe_employee_organization_sync(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::create($this->employeeAttributes('WEB-SYNC-002', '  Operations ', ' Control   Room ', ' Operator '));

        $this->actingAs($superAdmin)->post(route('data-pegawai.organization-sync.apply'))
            ->assertRedirect(route('data-pegawai.organization-sync.preview'))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 pegawai'));

        $employee->refresh();
        $department = Department::query()->sole();
        $unit = $department->units()->sole();
        $position = $unit->positions()->sole();
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($unit->id, $employee->unit_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame('  Operations ', $employee->txt_dept);
        $this->assertSame(' Control   Room ', $employee->txt_biro);
        $this->assertSame(' Operator ', $employee->position);
        $this->assertSame('IGNORED ORGANIZATIONAL UNIT', $employee->organizational_unit);
    }

    public function test_web_apply_does_not_overwrite_conflicting_foreign_key(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $sales = Department::create(['code' => 'DPT-WEB-SALES', 'name' => 'Sales', 'active' => true]);
        $unit = $sales->units()->create(['code' => 'UNT-WEB-SALES', 'name' => 'Field Team', 'active' => true]);
        $unit->positions()->create(['code' => 'POS-WEB-SALES', 'name' => 'Analyst', 'active' => true]);
        $engineering = Department::create(['code' => 'DPT-WEB-ENG', 'name' => 'Engineering', 'active' => true]);
        $employee = Employee::create([
            ...$this->employeeAttributes('WEB-SYNC-003', 'Sales', 'Field Team', 'Analyst'),
            'department_id' => $engineering->id,
        ]);

        $this->actingAs($superAdmin)->get(route('data-pegawai.organization-sync.preview'))
            ->assertOk()
            ->assertSee('conflict')
            ->assertSee((string) $employee->id);

        $this->actingAs($superAdmin)->post(route('data-pegawai.organization-sync.apply'))
            ->assertRedirect(route('data-pegawai.organization-sync.preview'));

        $employee->refresh();
        $this->assertSame($engineering->id, $employee->department_id);
        $this->assertNull($employee->unit_id);
        $this->assertNull($employee->position_id);
    }

    /** @return array<string, array{UserRole}> */
    public static function rolesWithoutEmployeeManagement(): array
    {
        return [
            'admin' => [UserRole::ADMIN],
            'manager' => [UserRole::MANAGER],
            'staff' => [UserRole::STAFF],
            'member' => [UserRole::MEMBER],
        ];
    }

    /** @return array<string, mixed> */
    private function employeeAttributes(string $sap, string $department, string $unit, string $position): array
    {
        return [
            'sap' => $sap,
            'txt_dept' => $department,
            'txt_biro' => $unit,
            'position' => $position,
            'organizational_unit' => 'IGNORED ORGANIZATIONAL UNIT',
        ];
    }
}
