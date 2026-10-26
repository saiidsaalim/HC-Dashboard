<?php

namespace Tests\Feature;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Successor;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuccessorManagementTest extends TestCase
{
    use RefreshDatabase;

    private const POSITION_KEY = 'market-planning-development.general-manager';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_structure_nodes_are_buttons_and_popup_renders_successor_data(): void
    {
        $successor = Successor::factory()->create([
            'structure_position_key' => self::POSITION_KEY,
            'name' => 'Andi Pratama',
            'position' => 'Senior Manager Strategy',
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 'Strategic leadership program',
            'education' => 'S2 Manajemen',
            'tenure' => '12 tahun',
            'directorate' => 'Commercial Directorate',
            'photo_path' => 'successors/andi-pratama.jpg',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['position' => self::POSITION_KEY]))
            ->assertOk()
            ->assertSee('aria-label="Lihat successor untuk Market Planning &amp; Development"', escape: false)
            ->assertSee('Successor -')
            ->assertSee('Daftar Kandidat')
            ->assertSee('Tambah Successor')
            ->assertSee('Andi Pratama')
            ->assertSee('Senior Manager Strategy')
            ->assertSee('High Potential')
            ->assertSee('Ready Now')
            ->assertSee('Strategic leadership program')
            ->assertSee('Profil Singkat')
            ->assertSee('S2 Manajemen')
            ->assertSee('12 tahun')
            ->assertSee('Commercial Directorate')
            ->assertSee('md:grid-cols-2 lg:grid-cols-3', escape: false)
            ->assertSee('enctype="multipart/form-data"', escape: false)
            ->assertSee('storage/successors/andi-pratama.jpg', escape: false)
            ->assertSee(route('performance-talent.successors.update', $successor), escape: false)
            ->assertSee(route('performance-talent.successors.destroy', $successor), escape: false);
    }

    public function test_successor_can_be_created_for_a_structure_position(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect(route('performance-talent.successors.index', ['position' => self::POSITION_KEY]))
            ->assertSessionHas('status', 'Successor berhasil ditambahkan.');

        $this->assertDatabaseHas('successors', $this->validData());
        $this->assertTrue(Schema::hasColumns('successors', [
            'structure_position_key',
            'name',
            'position',
            'talent_mapping',
            'succession_readiness',
            'idp',
            'education',
            'tenure',
            'directorate',
            'photo_path',
        ]));
    }

    public function test_successor_photo_can_be_uploaded_replaced_and_deleted(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData([
                'photo' => UploadedFile::fake()->image('candidate-one.jpg', 400, 400),
            ]))
            ->assertRedirect();

        $successor = Successor::query()->sole();
        $firstPhotoPath = $successor->photo_path;

        $this->assertNotNull($firstPhotoPath);
        Storage::disk('public')->assertExists($firstPhotoPath);

        $this->put(route('performance-talent.successors.update', $successor), $this->validData([
            'successor_id' => $successor->id,
            'photo' => UploadedFile::fake()->image('candidate-two.webp', 400, 400),
        ]))->assertRedirect();

        $successor->refresh();
        $secondPhotoPath = $successor->photo_path;

        $this->assertNotNull($secondPhotoPath);
        $this->assertNotSame($firstPhotoPath, $secondPhotoPath);
        Storage::disk('public')->assertMissing($firstPhotoPath);
        Storage::disk('public')->assertExists($secondPhotoPath);

        $this->delete(route('performance-talent.successors.destroy', $successor))->assertRedirect();

        Storage::disk('public')->assertMissing($secondPhotoPath);
    }

    public function test_successor_photo_must_be_a_supported_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.successors.index'))
            ->post(route('performance-talent.successors.store'), $this->validData([
                'photo' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('performance-talent.successors.index'))
            ->assertSessionHasErrorsIn('createSuccessor', ['photo']);

        $this->assertDatabaseCount('successors', 0);
    }

    public function test_successor_can_be_updated(): void
    {
        $successor = Successor::factory()->create();
        $data = $this->validData([
            'successor_id' => $successor->id,
            'name' => 'Updated Candidate',
            'succession_readiness' => '1-2 Tahun',
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successors.update', $successor), $data)
            ->assertRedirect(route('performance-talent.successors.index', ['position' => self::POSITION_KEY]))
            ->assertSessionHas('status', 'Successor berhasil diperbarui.');

        $this->assertDatabaseHas('successors', [
            'id' => $successor->id,
            'name' => 'Updated Candidate',
            'succession_readiness' => '1-2 Tahun',
        ]);
    }

    public function test_successor_can_be_deleted(): void
    {
        $successor = Successor::factory()->create();

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.successors.destroy', $successor))
            ->assertRedirect(route('performance-talent.successors.index', ['position' => self::POSITION_KEY]))
            ->assertSessionHas('status', 'Successor berhasil dihapus.');

        $this->assertDatabaseMissing('successors', ['id' => $successor->id]);
    }

    public function test_successor_requires_complete_data_and_a_known_structure_position(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.successors.index'))
            ->post(route('performance-talent.successors.store'), [
                'structure_position_key' => 'unknown.position',
            ])
            ->assertRedirect(route('performance-talent.successors.index'))
            ->assertSessionHasErrorsIn('createSuccessor', [
                'structure_position_key',
                'name',
                'position',
                'talent_mapping',
                'succession_readiness',
                'idp',
                'education',
                'tenure',
                'directorate',
            ]);

        $this->assertDatabaseCount('successors', 0);
    }

    public function test_user_with_view_only_permission_cannot_manage_successors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $successor = Successor::factory()->create();
        $this->grantPermissionsToRole(UserRole::ADMIN, [
            'workspace.performance-talent-management.view',
            'successors.view',
        ]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, [
            'hc-od-career-management',
            'performance-talent-management',
        ]);

        $this->actingAs($admin)
            ->get(route('performance-talent.successors.index'))
            ->assertOk()
            ->assertDontSee('Tambah Successor');

        $this->post(route('performance-talent.successors.store'), $this->validData())->assertForbidden();
        $this->put(route('performance-talent.successors.update', $successor), $this->validData([
            'successor_id' => $successor->id,
        ]))->assertForbidden();
        $this->delete(route('performance-talent.successors.destroy', $successor))->assertForbidden();

        $this->assertDatabaseHas('successors', ['id' => $successor->id]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'structure_position_key' => self::POSITION_KEY,
            'name' => 'Siti Rahma',
            'position' => 'Senior Manager Commercial',
            'talent_mapping' => 'High Potential',
            'succession_readiness' => 'Ready Now',
            'idp' => 'Executive leadership and commercial strategy program.',
            'education' => 'S2 Manajemen',
            'tenure' => '10 tahun',
            'directorate' => 'Commercial Directorate',
        ], $overrides);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissionsToRole(UserRole $role, array $permissionKeys): void
    {
        $now = now();
        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        DB::table('permission_role')->insert(array_map(
            fn (string $permissionKey): array => [
                'permission_id' => $permissionIds->get($permissionKey),
                'role' => $role->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $permissionKeys,
        ));

        app(RolePermissionCache::class)->clear();
    }
}
