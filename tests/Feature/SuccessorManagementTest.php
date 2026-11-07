<?php

namespace Tests\Feature;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Successor;
use App\Models\Unit;
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

    private Position $targetPosition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $department = Department::query()->create([
            'code' => 'COMMERCIAL',
            'name' => 'Commercial Department',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'STRATEGY',
            'name' => 'Strategy Unit',
            'active' => true,
        ]);
        $this->targetPosition = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'TARGET',
            'name' => 'Commercial General Manager',
            'formation_count' => 1,
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    public function test_successor_popup_renders_manual_profile_for_selected_target_position(): void
    {
        $successor = $this->createSuccessor(['photo_path' => 'successors/andi-pratama.jpg']);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertOk()
            ->assertSee('aria-label="Lihat successor untuk Commercial General Manager"', escape: false)
            ->assertSee('Andi Pratama')
            ->assertSee('Manager Human Capital Planning')
            ->assertSee('S2 Manajemen')
            ->assertSee('SAP-1001')
            ->assertSee('Band 4')
            ->assertSeeInOrder(['TALENT', 'BOX', '9'])
            ->assertSee('data-successor-candidate-preview', escape: false)
            ->assertSee('overflow-x-auto', escape: false)
            ->assertSee('xl:w-[27.5%]', escape: false)
            ->assertSee('data-talent-box-color="bg-emerald-600"', escape: false)
            ->assertSee('data-succession-readiness-color="bg-emerald-600"', escape: false)
            ->assertSee('Direkomendasikan untuk posisi target.')
            ->assertSee('Sangat Baik')
            ->assertSee('Pemimpin strategis dan tegas.')
            ->assertSee('Human Capital')
            ->assertSee('Business Strategy')
            ->assertSee('storage/successors/andi-pratama.jpg', escape: false)
            ->assertSee('data-successor-form', escape: false)
            ->assertSee('Preview Kandidat')
            ->assertSee('Riwayat Jabatan')
            ->assertSee(route('performance-talent.successors.update', $successor), escape: false);
    }

    public function test_successor_preview_uses_grouped_talent_box_colors(): void
    {
        $this->createSuccessor([
            'talent_box' => 8,
            'succession_readiness' => 'ready in one year',
        ]);
        $this->createSuccessor([
            'name' => 'Kandidat Talent Box Tujuh',
            'sap' => 'SAP-1007',
            'talent_box' => 7,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertOk()
            ->assertSee('data-talent-box-color="bg-blue-600"', escape: false)
            ->assertSee('data-talent-box-color="bg-yellow-600"', escape: false)
            ->assertSee('data-succession-readiness-color="bg-blue-600"', escape: false);
    }

    public function test_successor_can_be_created_without_employee_using_manual_profile_fields(): void
    {
        $this->assertDatabaseCount('employees', 0);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil ditambahkan.');

        $successor = Successor::query()->sole();
        $this->assertSame($this->validData()['position_history'], $successor->position_history);
        $this->assertSame('1985-05-15', $successor->birth_date?->format('Y-m-d'));
        $this->assertSame('SAP-1001', $successor->sap);
        $this->assertSame(9, $successor->talent_box);
        $this->assertSame('4.50', $successor->performance_score);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_successor_schema_has_no_employee_or_legacy_structure_dependency(): void
    {
        $this->assertTrue(Schema::hasColumn('successors', 'target_position_id'));
        $this->assertTrue(Schema::hasColumns('successors', [
            'sap',
            'current_position',
            'position_history',
            'birth_date',
            'last_education',
            'talent_box',
            'recommendation',
            'targeted_band',
            'performance_year',
            'performance_score',
            'performance_category',
            'mbti_type',
            'mbti_description',
            'top_job_family_1',
            'top_job_family_2',
        ]));
        $this->assertFalse(Schema::hasColumn('successors', 'employee_id'));
        $this->assertFalse(Schema::hasTable('successor_structure_departments'));
        $this->assertFalse(Schema::hasTable('successor_structure_positions'));
    }

    public function test_invalid_target_position_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData([
                'target_position_id' => 999999,
            ]))
            ->assertSessionHasErrorsIn('createSuccessor', ['target_position_id']);

        $this->assertDatabaseCount('successors', 0);
    }

    public function test_executive_profile_fields_are_required_and_validated(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), [
                'target_position_id' => $this->targetPosition->id,
                'talent_box' => 10,
            ])
            ->assertSessionHasErrorsIn('createSuccessor', [
                'name',
                'sap',
                'current_position',
                'position_history',
                'birth_date',
                'last_education',
                'talent_box',
                'succession_readiness',
                'recommendation',
                'targeted_band',
                'performance_year',
                'performance_score',
                'performance_category',
                'mbti_type',
                'mbti_description',
                'top_job_family_1',
                'top_job_family_2',
            ]);
    }

    public function test_validation_errors_reopen_form_and_keep_preview_values(): void
    {
        $indexUrl = route('performance-talent.successors.index', ['position' => $this->targetPosition->id]);

        $response = $this->actingAs($this->superAdmin())
            ->from($indexUrl)
            ->followingRedirects()
            ->post(route('performance-talent.successors.store'), $this->validData([
                'name' => 'Kandidat Preview Lama',
                'sap' => '',
            ]));

        $response
            ->assertOk()
            ->assertSee('Kandidat Preview Lama')
            ->assertSee('Periksa kembali data Successor yang dimasukkan.')
            ->assertSee('Preview Kandidat');
    }

    public function test_same_manual_candidate_name_can_target_different_positions(): void
    {
        $secondTargetPosition = $this->createTargetPosition('TARGET-2', 'Senior Manager Commercial', 2);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect();

        $this->post(route('performance-talent.successors.store'), $this->validData([
            'target_position_id' => $secondTargetPosition->id,
        ]))->assertRedirect();

        $this->assertDatabaseCount('successors', 2);
        $this->assertSame(
            [$this->targetPosition->id, $secondTargetPosition->id],
            Successor::query()->orderBy('target_position_id')->pluck('target_position_id')->all(),
        );
    }

    public function test_successor_can_be_updated_with_manual_data_and_target_position(): void
    {
        $successor = $this->createSuccessor();
        $secondTargetPosition = $this->createTargetPosition('TARGET-2', 'Senior Manager Commercial', 2);

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successors.update', $successor), $this->validData([
                'successor_id' => $successor->id,
                'target_position_id' => $secondTargetPosition->id,
                'name' => 'Budi Santoso',
                'sap' => 'SAP-2002',
                'current_position' => 'Manager Strategy',
                'talent_box' => 8,
                'succession_readiness' => 'ready in one year',
                'last_education' => 'S1 Teknik Industri',
                'targeted_band' => 'Band 5',
            ]))
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $secondTargetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil diperbarui.');

        $this->assertDatabaseHas('successors', [
            'id' => $successor->id,
            'target_position_id' => $secondTargetPosition->id,
            'name' => 'Budi Santoso',
            'sap' => 'SAP-2002',
            'current_position' => 'Manager Strategy',
            'talent_box' => 8,
            'succession_readiness' => 'ready in one year',
            'last_education' => 'S1 Teknik Industri',
            'targeted_band' => 'Band 5',
        ]);
    }

    public function test_creating_successor_does_not_mutate_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $employeeAttributes = $employee->refresh()->getAttributes();
        $positionAttributes = $this->targetPosition->refresh()->getAttributes();

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData())
            ->assertRedirect();

        $this->assertSame($employeeAttributes, $employee->refresh()->getAttributes());
        $this->assertSame($positionAttributes, $this->targetPosition->refresh()->getAttributes());
    }

    public function test_editing_successor_does_not_mutate_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $successor = $this->createSuccessor();
        $employeeAttributes = $employee->refresh()->getAttributes();
        $positionAttributes = $this->targetPosition->refresh()->getAttributes();

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.successors.update', $successor), $this->validData([
                'successor_id' => $successor->id,
                'name' => 'Updated Manual Name',
            ]))
            ->assertRedirect();

        $this->assertSame($employeeAttributes, $employee->refresh()->getAttributes());
        $this->assertSame($positionAttributes, $this->targetPosition->refresh()->getAttributes());
    }

    public function test_deleting_successor_does_not_delete_employee_or_target_position(): void
    {
        $employee = $this->createUnrelatedEmployee();
        $successor = $this->createSuccessor();

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.successors.destroy', $successor))
            ->assertRedirect(route('performance-talent.successors.index', ['position' => $this->targetPosition->id]))
            ->assertSessionHas('status', 'Successor berhasil dihapus.');

        $this->assertDatabaseMissing('successors', ['id' => $successor->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('positions', ['id' => $this->targetPosition->id]);
    }

    public function test_successor_photo_can_be_uploaded_replaced_and_deleted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.successors.store'), $this->validData([
                'photo' => UploadedFile::fake()->image('candidate-one.jpg'),
            ]))
            ->assertRedirect();

        $successor = Successor::query()->sole();
        $firstPhotoPath = $successor->photo_path;
        Storage::disk('public')->assertExists($firstPhotoPath);

        $this->put(route('performance-talent.successors.update', $successor), $this->validData([
            'successor_id' => $successor->id,
            'photo' => UploadedFile::fake()->image('candidate-two.webp'),
        ]))->assertRedirect();

        $successor->refresh();
        Storage::disk('public')->assertMissing($firstPhotoPath);
        Storage::disk('public')->assertExists($successor->photo_path);

        $this->delete(route('performance-talent.successors.destroy', $successor))->assertRedirect();
        Storage::disk('public')->assertMissing($successor->photo_path);
    }

    public function test_user_with_view_only_permission_cannot_manage_successors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $successor = $this->createSuccessor();
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
    }

    public function test_initial_successor_page_defers_other_department_trees_and_closed_candidate_profiles(): void
    {
        $successor = $this->createSuccessor(['name' => 'Deferred Manual Candidate']);
        $department = Department::query()->create(['code' => 'OTHER', 'name' => 'Other Department', 'active' => true]);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'OTHER', 'name' => 'Other Unit', 'active' => true]);
        $position = Position::query()->create(['unit_id' => $unit->id, 'code' => 'OTHER', 'name' => 'Deferred Other Position', 'active' => true]);

        $this->actingAs($this->superAdmin())->get(route('performance-talent.successors.index'))
            ->assertSee($department->name)
            ->assertSee($this->targetPosition->name)
            ->assertDontSee($position->name)
            ->assertDontSee($successor->name)
            ->assertViewHas('successors', fn ($successors): bool => $successors->contains($successor));

        $this->get(route('performance-talent.successors.index', ['fragment' => 'structure', 'department' => $department->id]))
            ->assertSee($position->name)
            ->assertDontSee($this->targetPosition->name)
            ->assertHeader('X-Successor-Fragment', 'structure')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_candidate_fragment_keeps_all_candidates_for_the_selected_position_and_escapes_their_names(): void
    {
        $first = $this->createSuccessor(['name' => 'Candidate <script>alert(1)</script>']);
        $second = $this->createSuccessor(['name' => 'Second Selected Candidate', 'sap' => 'SAP-SECOND']);
        $otherPosition = $this->createTargetPosition('OTHER', 'Other Position', 1);
        $other = $this->createSuccessor(['target_position_id' => $otherPosition->id, 'name' => 'Other Position Candidate', 'sap' => 'SAP-OTHER']);

        $this->actingAs($this->superAdmin())->get(route('performance-talent.successors.index', ['fragment' => 'candidates', 'position' => $this->targetPosition->id]))
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertDontSee($other->name)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertHeader('X-Successor-Fragment', 'candidates')
            ->assertHeader('X-Successor-Count', '2')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_successor_fragments_require_feature_access_and_an_active_master_target(): void
    {
        $route = route('performance-talent.successors.index', ['fragment' => 'candidates', 'position' => $this->targetPosition->id]);
        $this->get($route)->assertRedirect(route('login'));
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get($route)->assertForbidden();
        $this->targetPosition->update(['active' => false]);

        $this->actingAs($this->superAdmin())->get($route)->assertNotFound();
        $this->getJson(route('performance-talent.successors.index', ['fragment' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('fragment');
        $this->getJson(route('performance-talent.successors.index', ['fragment' => 'structure', 'department' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('department');
        $this->get(route('performance-talent.successors.index', ['fragment' => 'structure', 'department' => 999999]))->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function createSuccessor(array $overrides = []): Successor
    {
        return Successor::query()->create(array_merge($this->validData(), $overrides));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'target_position_id' => $this->targetPosition->id,
            'name' => 'Andi Pratama',
            'sap' => 'SAP-1001',
            'current_position' => 'Manager Human Capital Planning',
            'position_history' => [[
                'period' => '2023 - 2025',
                'position' => 'Manager Human Capital Planning',
            ]],
            'birth_date' => '1985-05-15',
            'last_education' => 'S2 Manajemen',
            'talent_box' => 9,
            'succession_readiness' => 'ready now',
            'recommendation' => 'Direkomendasikan untuk posisi target.',
            'targeted_band' => 'Band 4',
            'performance_year' => 2025,
            'performance_score' => 4.5,
            'performance_category' => 'Sangat Baik',
            'mbti_type' => 'ENTJ',
            'mbti_description' => 'Pemimpin strategis dan tegas.',
            'top_job_family_1' => 'Human Capital',
            'top_job_family_2' => 'Business Strategy',
        ], $overrides);
    }

    private function createTargetPosition(string $code, string $name, int $sortOrder): Position
    {
        return Position::query()->create([
            'unit_id' => $this->targetPosition->unit_id,
            'code' => $code,
            'name' => $name,
            'formation_count' => 1,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function createUnrelatedEmployee(): Employee
    {
        return Employee::query()->forceCreate([
            'sap' => 'SAP-UNRELATED',
            'name' => 'Unrelated Employee',
            'position_id' => $this->targetPosition->id,
            'unit_id' => $this->targetPosition->unit_id,
            'department_id' => $this->targetPosition->unit->department_id,
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissionsToRole(UserRole $role, array $permissionKeys): void
    {
        $now = now();
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id', 'key');

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
