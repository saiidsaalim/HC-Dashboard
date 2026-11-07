<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_public_registration_is_unavailable_and_internal_routes_require_authentication(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('mutasi.store'))->assertRedirect(route('login'));
    }

    public function test_direct_url_access_requires_workspace_and_feature_permissions(): void
    {
        $withoutWorkspace = User::factory()->create(['role' => UserRole::STAFF->value]);
        $workspacePermission = Permission::query()
            ->where('key', 'workspace.hc-od-career-management.view')
            ->sole();
        DB::table('permission_user')->insert([
            'permission_id' => $workspacePermission->id,
            'user_id' => $withoutWorkspace->id,
            'granted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($withoutWorkspace)->get(route('dashboard'))->assertForbidden();

        $staff = User::factory()->create(['role' => UserRole::STAFF->value]);
        $this->actingAs($staff)->get(route('promosi'))->assertForbidden();
        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('movements', fn (array $movements): bool => collect($movements)->doesntContain('label', 'Promosi'));

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get(route('promosi'))->assertOk();
    }

    public function test_last_super_admin_cannot_delete_their_account_from_profile(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($superAdmin)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertAuthenticatedAs($superAdmin);
    }
}
