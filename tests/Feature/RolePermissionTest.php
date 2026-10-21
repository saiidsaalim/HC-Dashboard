<?php

namespace Tests\Feature;

use App\Enums\PromotionStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\PermissionRegistry;
use Database\Seeders\PermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_super_admin_sees_one_global_role_access_button_and_matrix_modal(): void
    {
        $response = $this->actingAs($this->user(UserRole::SUPER_ADMIN))
            ->get(route('konfigurasi-user'));

        $response->assertOk()
            ->assertSeeText('Pengaturan Hak Akses Role')
            ->assertSeeText('Tentukan halaman dan tindakan yang dapat diakses oleh setiap role.')
            ->assertSee('id="role-permission-modal"', escape: false)
            ->assertSee('data-role-permission-table', escape: false)
            ->assertSee('Atur Hak Akses', escape: false);

        $this->assertSame(1, substr_count($response->getContent(), 'Atur Hak Akses'));
        $this->assertStringContainsString('table-fixed', $response->getContent());
        $this->assertStringNotContainsString('min-w-[1050px]', $response->getContent());
        $this->assertStringNotContainsString('user-permissions-', $response->getContent());
    }

    public function test_non_super_admin_cannot_open_or_update_role_access_settings(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $this->actingAs($admin)->get(route('konfigurasi-user'))
            ->assertOk()
            ->assertDontSee('Atur Hak Akses');

        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $this->actingAs($this->user($role))->put(route('konfigurasi-user.access.update'), [
                'permissions' => ['ADMIN' => ['dashboard.view']],
            ])->assertForbidden();
        }
    }

    public function test_matrix_lists_real_roles_and_grouped_permissions(): void
    {
        $response = $this->actingAs($this->user(UserRole::SUPER_ADMIN))
            ->get(route('konfigurasi-user'));

        foreach (UserRole::cases() as $role) {
            $response->assertSeeText($role->value);
        }

        $response->assertSeeText('Data Pegawai')
            ->assertSeeText('Lihat data pegawai')
            ->assertSee('employees.view')
            ->assertSeeText('WLA / FTE')
            ->assertSee('wla.finalize');
    }

    public function test_every_super_admin_checkbox_is_checked_and_disabled(): void
    {
        $content = $this->actingAs($this->user(UserRole::SUPER_ADMIN))
            ->get(route('konfigurasi-user'))
            ->assertOk()
            ->getContent();
        $document = new DOMDocument;
        @$document->loadHTML($content);
        $xpath = new DOMXPath($document);
        $checkboxes = $xpath->query('//input[@data-role-permission and @data-role="SUPER_ADMIN"]');

        $this->assertSame(count(PermissionRegistry::permissions()), $checkboxes->length);

        foreach ($checkboxes as $checkbox) {
            $this->assertTrue($checkbox->hasAttribute('checked'));
            $this->assertTrue($checkbox->hasAttribute('disabled'));
        }
    }

    public function test_super_admin_always_has_every_permission_even_without_pivot_rows(): void
    {
        DB::table('permission_role')->where('role', UserRole::SUPER_ADMIN->value)->delete();
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        foreach (array_keys(PermissionRegistry::permissions()) as $permission) {
            $this->assertTrue($superAdmin->hasPermission($permission));
        }
    }

    public function test_role_matrix_can_be_saved_without_changing_user_roles_or_creating_duplicates(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $admin = $this->user(UserRole::ADMIN);
        $payload = $this->matrixPayload([
            UserRole::ADMIN->name => ['dashboard.view', 'employees.view'],
        ]);

        $this->actingAs($superAdmin)->put(route('konfigurasi-user.access.update'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Hak akses role berhasil diperbarui.');
        $this->actingAs($superAdmin)->put(route('konfigurasi-user.access.update'), $payload)
            ->assertRedirect();

        $this->assertSame(UserRole::ADMIN->value, $admin->fresh()->role);
        $this->assertSame(2, DB::table('permission_role')->where('role', UserRole::ADMIN->value)->count());
        $pivotRows = DB::table('permission_role')->get(['role', 'permission_id']);
        $this->assertCount(
            $pivotRows->count(),
            $pivotRows->unique(fn (object $row): string => $row->role.':'.$row->permission_id),
        );
    }

    public function test_unchecked_permissions_are_removed_for_the_role(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        $this->actingAs($superAdmin)->put(route('konfigurasi-user.access.update'), $this->matrixPayload([
            UserRole::ADMIN->name => ['dashboard.view'],
        ]))->assertRedirect();

        $this->assertTrue($this->user(UserRole::ADMIN)->hasPermission('dashboard.view'));
        $this->assertFalse($this->user(UserRole::ADMIN)->hasPermission('employees.view'));
    }

    public function test_role_view_permission_controls_page_access(): void
    {
        $this->replaceRolePermissions(UserRole::ADMIN, ['employees.view']);
        $allowed = $this->user(UserRole::ADMIN);
        $this->actingAs($allowed)->get(route('data-pegawai'))->assertOk();

        $this->replaceRolePermissions(UserRole::ADMIN, ['dashboard.view']);
        $denied = $this->user(UserRole::ADMIN);
        $this->actingAs($denied)->get(route('data-pegawai'))->assertForbidden();
    }

    public function test_create_permission_does_not_grant_update_or_delete(): void
    {
        $this->replaceRolePermissions(UserRole::STAFF, ['mutations.create']);
        $staff = $this->user(UserRole::STAFF);

        $this->assertTrue($staff->hasPermission('mutations.create'));
        $this->assertFalse($staff->hasPermission('mutations.update'));
        $this->assertFalse($staff->hasPermission('mutations.delete'));
    }

    public function test_edit_button_and_direct_update_are_denied_without_update_permission(): void
    {
        $this->replaceRolePermissions(UserRole::STAFF, ['employees.view']);
        $staff = $this->user(UserRole::STAFF);
        $employee = Employee::query()->create(['sap' => 'SAP-ROLE-PERMISSION']);

        $this->actingAs($staff)->get(route('data-pegawai'))
            ->assertOk()
            ->assertDontSee('edit-employee-'.$employee->id, escape: false);
        $this->actingAs($staff)->put(route('data-pegawai.update', $employee), [
            'sap' => 'SAP-CHANGED',
        ])->assertForbidden();

        $this->assertSame('SAP-ROLE-PERMISSION', $employee->fresh()->sap);
    }

    public function test_sidebar_parent_is_visible_when_one_child_permission_is_granted(): void
    {
        $this->replaceRolePermissions(UserRole::STAFF, ['dashboard.view', 'wla.staff.view']);
        $staff = $this->user(UserRole::STAFF);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="WLA Saya"', escape: false);
    }

    public function test_final_business_rule_still_denies_update_and_delete_with_permissions(): void
    {
        $this->replaceRolePermissions(UserRole::ADMIN, [
            'promotions.update',
            'promotions.delete',
        ]);
        $admin = $this->user(UserRole::ADMIN);
        $promotion = new EmployeePromotion([
            'verification_status' => PromotionStatus::Final->value,
        ]);

        $this->assertFalse(Gate::forUser($admin)->allows('update', $promotion));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $promotion));
    }

    public function test_unknown_role_and_permission_are_rejected_without_changing_matrix(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $before = DB::table('permission_role')
            ->orderBy('role')
            ->orderBy('permission_id')
            ->get()
            ->toArray();

        $this->actingAs($superAdmin)
            ->from(route('konfigurasi-user'))
            ->put(route('konfigurasi-user.access.update'), [
                '_form' => 'role-access',
                'permissions' => [
                    'INVENTED_ROLE' => ['dashboard.view'],
                    UserRole::ADMIN->name => ['invented.escalation'],
                ],
            ])
            ->assertRedirect(route('konfigurasi-user'))
            ->assertSessionHasErrors([
                'permissions.INVENTED_ROLE',
                'permissions.ADMIN.0',
            ]);

        $this->assertEquals($before, DB::table('permission_role')
            ->orderBy('role')
            ->orderBy('permission_id')
            ->get()
            ->toArray());
    }

    public function test_matrix_update_is_atomic_when_a_database_write_fails(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $before = DB::table('permission_role')
            ->orderBy('role')
            ->orderBy('permission_id')
            ->get()
            ->toArray();
        $deleteCount = 0;

        DB::listen(function ($query) use (&$deleteCount): void {
            if (str_starts_with(strtolower($query->sql), 'delete from "permission_role"')) {
                $deleteCount++;

                if ($deleteCount === 3) {
                    throw new RuntimeException('Simulated matrix write failure.');
                }
            }
        });

        $this->actingAs($superAdmin)->put(route('konfigurasi-user.access.update'), $this->matrixPayload([
            UserRole::ADMIN->name => ['dashboard.view'],
        ]))->assertServerError();

        $this->assertEquals($before, DB::table('permission_role')
            ->orderBy('role')
            ->orderBy('permission_id')
            ->get()
            ->toArray());
    }

    public function test_permission_seeder_is_idempotent_and_preserves_saved_configuration(): void
    {
        $dashboardPermissionId = Permission::query()->where('key', 'dashboard.view')->value('id');
        DB::table('permission_role')
            ->where('role', UserRole::ADMIN->value)
            ->where('permission_id', $dashboardPermissionId)
            ->delete();
        $countBefore = DB::table('permission_role')->count();

        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseMissing('permission_role', [
            'role' => UserRole::ADMIN->value,
            'permission_id' => $dashboardPermissionId,
        ]);
        $this->assertSame($countBefore, DB::table('permission_role')->count());
        $this->assertSame(count(PermissionRegistry::permissions()), Permission::query()->count());
    }

    public function test_user_import_is_removed_while_other_excel_imports_remain(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        $this->assertFalse(Route::has('konfigurasi-user.import'));
        $this->assertTrue(Route::has('data-pegawai.import'));
        $this->assertTrue(Route::has('wla.participants.import-approved'));
        $this->actingAs($superAdmin)->post('/konfigurasi-user/import')->assertMethodNotAllowed();
        $this->actingAs($superAdmin)->post(route('data-pegawai.import'))->assertSessionHasErrors('file');
    }

    public function test_permission_registry_has_no_duplicate_keys(): void
    {
        $groupedKeys = collect(PermissionRegistry::groups())
            ->flatMap(fn (array $group): array => array_keys($group['permissions']));

        $this->assertCount($groupedKeys->count(), $groupedKeys->unique());
        $this->assertCount($groupedKeys->count(), PermissionRegistry::permissions());
    }

    /** @param array<string, array<int, string>> $overrides */
    private function matrixPayload(array $overrides = []): array
    {
        $matrix = [];

        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::SUPER_ADMIN) {
                continue;
            }

            $matrix[$role->name] = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role', $role->value)
                ->pluck('permissions.key')
                ->all();
        }

        return [
            '_form' => 'role-access',
            'permissions' => [...$matrix, ...$overrides],
        ];
    }

    /** @param array<int, string> $permissionKeys */
    private function replaceRolePermissions(UserRole $role, array $permissionKeys): void
    {
        DB::table('permission_role')->where('role', $role->value)->delete();
        $now = now();
        $rows = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->map(fn (int $permissionId): array => [
                'permission_id' => $permissionId,
                'role' => $role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('permission_role')->insert($rows);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role->value]);
    }
}
