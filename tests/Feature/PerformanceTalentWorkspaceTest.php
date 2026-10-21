<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\UserManagementController;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PerformanceTalentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_selecting_performance_talent_workspace_persists_its_authorized_sidebar(): void
    {
        $user = $this->userWithPermissions([
            'workspace.view',
            'workspace.performance-talent-management.view',
            'dashboard.view',
            'employees.view',
            'users.view',
        ]);

        $this->actingAs($user)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'performance-talent-management');

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('title="Dashboard Performance &amp; Talent Management"', escape: false)
            ->assertSee('title="Data Pegawai"', escape: false)
            ->assertSee('title="Konfigurasi User"', escape: false)
            ->assertSee('href="'.route('data-pegawai').'"', escape: false)
            ->assertSee('href="'.route('konfigurasi-user').'"', escape: false)
            ->assertDontSee('title="WLA"', escape: false)
            ->assertDontSee('title="Mutasi"', escape: false);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('active_workspace', 'performance-talent-management')
            ->assertSee('title="Dashboard Performance &amp; Talent Management"', escape: false);
    }

    public function test_workspace_sidebar_and_direct_urls_respect_existing_view_permissions(): void
    {
        $user = $this->userWithPermissions([
            'workspace.view',
            'workspace.performance-talent-management.view',
            'dashboard.view',
        ]);

        $this->actingAs($user)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('dashboard');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('title="Data Pegawai"', escape: false)
            ->assertDontSee('title="Konfigurasi User"', escape: false);

        $this->get(route('data-pegawai'))->assertForbidden();
        $this->get(route('konfigurasi-user'))->assertForbidden();
    }

    public function test_shared_pages_keep_workspace_sidebar_data_source_and_active_state(): void
    {
        $employee = Employee::query()->create(['sap' => 'SAP-PTM-SHARED']);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($user)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertRedirectToRoute('dashboard');

        $dashboardResponse = $this->get(route('dashboard'))->assertOk();
        $employeeResponse = $this->get(route('data-pegawai'))->assertOk();
        $employeeDetailResponse = $this->get(route('employees.show', $employee))->assertOk();
        $userConfigurationResponse = $this->get(route('konfigurasi-user'))->assertOk();

        $this->assertActiveNavigation($dashboardResponse->getContent(), 'Dashboard Performance & Talent Management');
        $this->assertActiveNavigation($employeeResponse->getContent(), 'Data Pegawai');
        $this->assertActiveNavigation($employeeDetailResponse->getContent(), 'Data Pegawai');
        $this->assertActiveNavigation($userConfigurationResponse->getContent(), 'Konfigurasi User');
        $employeeResponse->assertSee($employee->sap);

        $this->assertSame(EmployeeController::class.'@index', Route::getRoutes()->getByName('data-pegawai')?->getActionName());
        $this->assertSame(UserManagementController::class.'@index', Route::getRoutes()->getByName('konfigurasi-user')?->getActionName());
    }

    public function test_switching_workspace_restores_the_existing_global_sidebar(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($user)
            ->get(route('portal.workspace', 'performance-talent-management'))
            ->assertSessionHas('active_workspace', 'performance-talent-management');

        $this->get(route('portal.workspace', 'hc-od-career-management'))
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHas('active_workspace', 'hc-od-career-management');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="Dashboard"', escape: false)
            ->assertSee('title="WLA"', escape: false)
            ->assertDontSee('title="Dashboard Performance &amp; Talent Management"', escape: false);
    }

    /** @param array<int, string> $permissionKeys */
    private function userWithPermissions(array $permissionKeys): User
    {
        $role = UserRole::STAFF;
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

        return User::factory()->create(['role' => $role->value]);
    }

    private function assertActiveNavigation(string $html, string $title): void
    {
        $encodedTitle = htmlspecialchars($title, ENT_QUOTES);

        $this->assertMatchesRegularExpression(
            '/<a\s+href="[^"]+"\s+title="'.preg_quote($encodedTitle, '/').'"\s+aria-current="page"/',
            $html,
        );
    }
}
