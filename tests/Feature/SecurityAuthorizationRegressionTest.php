<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeImportBatch;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    #[DataProvider('canonicalResetBaseUrls')]
    public function test_password_reset_invitation_uses_the_configured_host_instead_of_the_request_host(string $baseUrl, string $expectedDirectory): void
    {
        Notification::fake();
        config(['app.url' => $baseUrl]);
        $user = User::factory()->create();

        $this->post('http://attacker.example.test/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect()->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $expectedDirectory): bool {
            $resetUrl = $notification->toMail($user)->actionUrl;
            $this->assertSame('hc-dashboard.example.test', parse_url($resetUrl, PHP_URL_HOST));
            $this->assertSame('https', parse_url($resetUrl, PHP_URL_SCHEME));
            $this->assertSame($expectedDirectory, dirname(parse_url($resetUrl, PHP_URL_PATH)));
            parse_str(parse_url($resetUrl, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);

            return true;
        });
    }

    /** @return array<string, array{string, string}> */
    public static function canonicalResetBaseUrls(): array
    {
        return [
            'root application URL' => ['https://hc-dashboard.example.test', '/reset-password'],
            'application URL with a base directory' => ['https://hc-dashboard.example.test/hr/', '/hr/reset-password'],
        ];
    }

    public function test_login_rate_limit_blocks_valid_credentials_until_the_lockout_expires(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => trans('auth.throttle', ['seconds' => 60, 'minutes' => 1]),
        ]);
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('portal'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rotates_the_session_and_logout_discards_existing_session_data(): void
    {
        $user = User::factory()->create();
        $this->withSession(['audit_session_marker' => 'before-login']);
        $previousSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('portal'));

        $this->assertNotSame($previousSessionId, session()->getId());
        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('audit_session_marker');
        $this->assertGuest();
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_delegated_role_manager_cannot_promote_another_user_to_super_admin(UserRole $role): void
    {
        $actor = $this->delegatedUser($role, 'users.manage-roles');
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $response = $this->actingAs($actor)->patch(route('konfigurasi-user.role', $target), [
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->assertSame(UserRole::STAFF->value, $target->fresh()->role);
        $this->assertFalse($target->fresh()->hasPermission('users.manage-access'));
        $response->assertForbidden();
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_delegated_role_manager_cannot_promote_self_to_super_admin(UserRole $role): void
    {
        $actor = $this->delegatedUser($role, 'users.manage-roles');

        $response = $this->actingAs($actor)->patch(route('konfigurasi-user.role', $actor), [
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->assertSame($role->value, $actor->fresh()->role);
        $response->assertForbidden();
    }

    public function test_delegated_role_manager_retains_configured_non_super_admin_role_management(): void
    {
        $actor = $this->delegatedUser(UserRole::ADMIN, 'users.manage-roles');
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($actor)->patch(route('konfigurasi-user.role', $target), [
            'role' => UserRole::MEMBER->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(UserRole::MEMBER->value, $target->fresh()->role);
    }

    public function test_super_admin_can_promote_another_user_to_super_admin(): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($actor)->patch(route('konfigurasi-user.role', $target), [
            'role' => UserRole::SUPER_ADMIN->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(UserRole::SUPER_ADMIN->value, $target->fresh()->role);
    }

    #[DataProvider('allRoles')]
    public function test_employee_identity_updates_preserve_the_linked_users_assigned_role(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::query()->create([
            'sap' => 'SYNC-'.$role->name,
            'personal_number' => 'Identity '.$role->name,
            'email' => strtolower($role->name).'@example.test',
        ]);
        $target = User::factory()->create([
            'employee_id' => $employee->id,
            'role' => $role->value,
            'name' => $employee->personal_number,
            'email' => $employee->email,
        ]);

        $this->actingAs($actor)->put(route('data-pegawai.update', $employee), [
            'sap' => $employee->sap,
            'personal_number' => 'Updated '.$role->name,
            'email' => 'updated.'.strtolower($role->name).'@example.test',
        ])->assertRedirect(route('data-pegawai'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => $role->value,
            'name' => 'Updated '.$role->name,
            'email' => 'updated.'.strtolower($role->name).'@example.test',
            'employee_id' => $employee->id,
        ]);
    }

    /** @param array<string, string> $changes */
    #[DataProvider('protectedIdentityChanges')]
    public function test_delegated_employee_editor_cannot_change_linked_super_admin_identity(array $changes): void
    {
        $actor = $this->delegatedUser(UserRole::ADMIN, 'employees.update');
        [$employee, $target] = $this->employeeLinkedToSuperAdmin();

        $response = $this->actingAs($actor)->putJson(route('data-pegawai.update', $employee), [
            'sap' => $employee->sap,
            ...$changes,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Protected identity',
            'email' => 'protected@example.test',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'personal_number' => 'Protected identity',
            'email' => 'protected@example.test',
        ]);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function protectedIdentityChanges(): array
    {
        return [
            'change recovery email' => [['email' => 'attacker@example.test']],
            'change account name' => [['personal_number' => 'Changed identity']],
        ];
    }

    public function test_delegated_employee_editor_can_update_non_identity_fields_of_linked_super_admin(): void
    {
        $actor = $this->delegatedUser(UserRole::ADMIN, 'employees.update');
        [$employee, $target] = $this->employeeLinkedToSuperAdmin();

        $this->actingAs($actor)->putJson(route('data-pegawai.update', $employee), [
            'sap' => $employee->sap,
            'cost_ctr' => 'UPDATED-COST-CENTER',
        ])->assertRedirect(route('data-pegawai'));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'cost_ctr' => 'UPDATED-COST-CENTER']);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::SUPER_ADMIN->value,
            'name' => 'Protected identity',
            'email' => 'protected@example.test',
        ]);
    }

    public function test_delegated_importer_cannot_change_linked_super_admin_identity_and_batch_rolls_back(): void
    {
        $actor = $this->delegatedUser(UserRole::ADMIN, 'employees.import');
        [$employee, $target] = $this->employeeLinkedToSuperAdmin();
        $ordinaryEmployee = Employee::query()->create(['sap' => 'ORDINARY', 'cost_ctr' => 'ORIGINAL']);
        $batch = $this->approvedImportBatch($actor, [
            ['sap' => $ordinaryEmployee->sap, 'cost_ctr' => 'UPDATED'],
            ['sap' => $employee->sap, 'personal_number' => 'Changed identity', 'email' => 'attacker@example.test'],
        ]);

        $response = $this->actingAs($actor)->postJson(route('data-pegawai.import-batches.process', $batch));

        $this->assertSame('protected@example.test', $target->fresh()->email);
        $response->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $ordinaryEmployee->id, 'cost_ctr' => 'ORIGINAL']);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'email' => 'protected@example.test']);
        $this->assertSame(0, $batch->rows()->whereNotNull('processed_at')->count());
        $this->assertSame('REVIEW_REQUIRED', $batch->fresh()->status);
    }

    public function test_super_admin_importer_can_update_linked_super_admin_identity(): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        [$employee, $target] = $this->employeeLinkedToSuperAdmin();
        $batch = $this->approvedImportBatch($actor, [
            ['sap' => $employee->sap, 'personal_number' => 'Changed identity', 'email' => 'changed@example.test'],
        ]);

        $this->actingAs($actor)->postJson(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()->assertJsonPath('processed', 1);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::SUPER_ADMIN->value,
            'name' => 'Changed identity',
            'email' => 'changed@example.test',
        ]);
    }

    /** @return array{Employee, User} */
    private function employeeLinkedToSuperAdmin(): array
    {
        $employee = Employee::query()->create([
            'sap' => 'PROTECTED',
            'personal_number' => 'Protected identity',
            'email' => 'protected@example.test',
        ]);
        $user = User::factory()->create([
            'employee_id' => $employee->id,
            'role' => UserRole::SUPER_ADMIN->value,
            'name' => $employee->personal_number,
            'email' => $employee->email,
        ]);

        return [$employee, $user];
    }

    /** @param list<array<string, mixed>> $payloads */
    private function approvedImportBatch(User $actor, array $payloads): EmployeeImportBatch
    {
        $batch = EmployeeImportBatch::query()->create([
            'source_file' => 'audit-test.csv',
            'source_type' => 'csv',
            'imported_by' => $actor->id,
            'imported_at' => now(),
            'status' => 'REVIEW_REQUIRED',
        ]);

        foreach ($payloads as $index => $payload) {
            $batch->rows()->create([
                'source_row' => $index + 2,
                'source_payload' => $payload,
                'normalized_payload' => $payload,
                'validation_status' => 'VALID',
                'mapping_status' => 'APPROVED',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        }

        return $batch;
    }

    #[DataProvider('invalidEmployeeDetailIdentifiers')]
    public function test_invalid_employee_detail_identifiers_return_not_found(string $routeName, string $identifier): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($actor)->get(route($routeName, ['id' => $identifier]))->assertNotFound();
    }

    #[DataProvider('employeeDetailRoutes')]
    public function test_numeric_employee_detail_identifiers_remain_accessible(string $routeName): void
    {
        $actor = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employee = Employee::query()->create(['sap' => 'VALID-DETAIL-ID']);

        $this->actingAs($actor)->get(route($routeName, ['id' => $employee->id]))
            ->assertOk()->assertSee('VALID-DETAIL-ID');
    }

    /** @return array<string, array{string, string}> */
    public static function invalidEmployeeDetailIdentifiers(): array
    {
        $cases = [];

        foreach (self::employeeDetailRoutes() as $context => [$routeName]) {
            foreach (['alphabetic' => 'invalid', 'scientific notation' => '12e3', 'integer overflow' => '92233720368547758080'] as $format => $identifier) {
                $cases[$context.' '.$format] = [$routeName, $identifier];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string}> */
    public static function employeeDetailRoutes(): array
    {
        return [
            'career management' => ['employees.show'],
            'performance and talent management' => ['performance-talent.data-pegawai.show'],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function nonSuperAdminRoles(): array
    {
        return array_filter(self::allRoles(), fn (array $roles): bool => $roles[0] !== UserRole::SUPER_ADMIN);
    }

    /** @return array<string, array{UserRole}> */
    public static function allRoles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            $roles[$role->value] = [$role];
        }

        return $roles;
    }

    private function delegatedUser(UserRole $role, string $permissionKey): User
    {
        $this->seed(PermissionSeeder::class);
        $permissionId = DB::table('permissions')->where('key', $permissionKey)->value('id');
        DB::table('permission_role')->insert([
            'permission_id' => $permissionId,
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(RolePermissionCache::class)->clear();

        return User::factory()->create(['role' => $role->value]);
    }
}
