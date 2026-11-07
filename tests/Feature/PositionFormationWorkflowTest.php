<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Permission;
use App\Models\Position;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PositionFormationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_form_shows_the_selected_position_without_editable_master_fields(): void
    {
        $position = $this->position();
        $position->employees()->create(['sap' => 'LINKED-1']);

        $this->actingAs($this->superAdmin())->get(route('formasi.requirement.edit', $position))
            ->assertOk()
            ->assertSee($position->name)
            ->assertSee('Position ID '.$position->id)
            ->assertSee('Pegawai Terhubung')
            ->assertSee('Belum ditetapkan')
            ->assertSee('Nilai awal belum dianggap sebagai kebutuhan sampai ditetapkan.')
            ->assertViewHas('position', fn (Position $selected): bool => $selected->id === $position->id && $selected->employees_count === 1)
            ->assertDontSee('name="name"', escape: false)
            ->assertDontSee('name="parent_position_id"', escape: false)
            ->assertDontSee('name="unit_id"', escape: false);
    }

    public function test_legacy_invalid_parent_does_not_block_need_only_confirmation_or_change_master_attributes(): void
    {
        $position = $this->position();
        $parent = $position->unit->positions()->create(['code' => 'LEGACY-PARENT', 'name' => 'Legacy parent', 'active' => true]);
        $position->update(['parent_position_id' => $parent->id]);
        $original = Arr::except($position->refresh()->getAttributes(), ['formation_count', 'formation_is_confirmed', 'updated_at']);

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 1,
            'confirm_formation' => '1',
            'name' => 'Unwanted rename',
            'code' => 'OTHER',
            'parent_position_id' => null,
            'unit_id' => 999,
            'active' => false,
        ])->assertRedirect(route('formasi'))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => true]);
        $this->assertSame($original, Arr::except($position->refresh()->getAttributes(), ['formation_count', 'formation_is_confirmed', 'updated_at']));
    }

    public function test_confirmed_need_can_be_changed_back_to_one_without_master_inputs(): void
    {
        $position = $this->position();
        $position->update(['formation_count' => 4, 'formation_is_confirmed' => true]);

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 1,
            'confirm_formation' => '1',
        ])->assertRedirect(route('formasi'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => true]);
    }

    public function test_failed_input_for_one_position_is_not_applied_to_another_position(): void
    {
        $position = $this->position();
        $otherPosition = $position->unit->positions()->create([
            'code' => 'OTHER', 'name' => 'Other Position', 'formation_count' => 7, 'active' => true,
        ]);

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 0, 'confirm_formation' => '1',
        ])->assertSessionHasErrors('formation_count');

        $response = $this->get(route('formasi.requirement.edit', $otherPosition))->assertOk();
        $this->assertFormationInput($response, '7', false);
    }

    public function test_invalid_array_count_returns_a_renderable_requirement_form(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => ['invalid'], 'confirm_formation' => '1',
        ])->assertRedirect(route('formasi.requirement.edit', $position))->assertSessionHasErrors('formation_count');

        $response = $this->get(route('formasi.requirement.edit', $position))->assertOk();
        $this->assertFormationInput($response, '', true);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
    }

    public function test_failed_master_edit_does_not_preconfirm_another_position(): void
    {
        $position = $this->position();
        $otherPosition = $position->unit->positions()->create([
            'code' => 'OTHER', 'name' => 'Other Position', 'formation_count' => 7, 'active' => true,
        ]);

        $this->actingAs($this->superAdmin())->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                ...$this->masterPayload($position),
                'formation_position_id' => (string) $position->id,
                'name' => '', 'formation_count' => 4, 'confirm_formation' => '1',
            ])->assertSessionHasErrors('name');

        $response = $this->get(route('organization.positions.edit', $otherPosition))->assertOk();
        $this->assertFormationInput($response, '7', false);
        $this->put(route('organization.positions.update', $otherPosition), $this->masterPayload($otherPosition))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('positions', ['id' => $otherPosition->id, 'formation_count' => 7, 'formation_is_confirmed' => false]);
    }

    public function test_failed_master_creation_does_not_preconfirm_an_existing_position(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->from(route('organization.positions.create'))
            ->post(route('organization.positions.store'), [
                ...$this->masterPayload($position),
                'code' => 'NEW', 'name' => '', 'formation_position_id' => 'new',
                'formation_count' => 6, 'confirm_formation' => '1',
            ])->assertSessionHasErrors('name');

        $response = $this->get(route('organization.positions.edit', $position))->assertOk();
        $this->assertFormationInput($response, '1', false);
    }

    public function test_failed_master_edit_does_not_preconfirm_a_new_position(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                ...$this->masterPayload($position),
                'formation_position_id' => (string) $position->id,
                'name' => '', 'formation_count' => 4, 'confirm_formation' => '1',
            ])->assertSessionHasErrors('name');

        $response = $this->get(route('organization.positions.create'))->assertOk();
        $this->assertFormationInput($response, '1', false);
    }

    public function test_failed_master_edit_keeps_explicit_confirmation_only_for_its_position(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                ...$this->masterPayload($position),
                'formation_position_id' => (string) $position->id,
                'name' => '', 'formation_count' => 4, 'confirm_formation' => '1',
            ])->assertSessionHasErrors('name');

        $response = $this->get(route('organization.positions.edit', $position))->assertOk();
        $this->assertFormationInput($response, '4', true);
    }

    public function test_master_validation_renders_array_count_safely(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->from(route('organization.positions.edit', $position))
            ->put(route('organization.positions.update', $position), [
                ...$this->masterPayload($position),
                'formation_position_id' => (string) $position->id,
                'formation_count' => ['invalid'], 'confirm_formation' => '1',
            ])->assertSessionHasErrors('formation_count');

        $response = $this->get(route('organization.positions.edit', $position))->assertOk();
        $this->assertFormationInput($response, '', true);
    }

    public function test_direct_requirement_routes_select_the_hc_od_workspace(): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->withSession(['active_workspace' => 'performance-talent-management'])
            ->get(route('formasi.requirement.edit', $position))->assertOk()
            ->assertSessionHas('active_workspace', 'hc-od-career-management');

        $this->withSession(['active_workspace' => 'performance-talent-management'])
            ->patch(route('formasi.requirement.update', $position), ['formation_count' => 1, 'confirm_formation' => '1'])
            ->assertRedirect(route('formasi'))->assertSessionHas('active_workspace', 'hc-od-career-management');
    }

    /** @param array<string, mixed> $confirmation */
    #[DataProvider('missingConfirmationCases')]
    public function test_count_cannot_be_saved_without_explicit_confirmation(array $confirmation): void
    {
        $position = $this->position();

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 5,
            'formation_is_confirmed' => true,
            ...$confirmation,
        ])->assertRedirect(route('formasi.requirement.edit', $position))->assertSessionHasErrors('confirm_formation');

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
    }

    #[DataProvider('invalidCounts')]
    public function test_invalid_count_does_not_replace_a_confirmed_requirement(mixed $count): void
    {
        $position = $this->position();
        $position->update(['formation_count' => 3, 'formation_is_confirmed' => true]);

        $this->actingAs($this->superAdmin())->patch(route('formasi.requirement.update', $position), [
            'formation_count' => $count,
            'confirm_formation' => '1',
        ])->assertRedirect(route('formasi.requirement.edit', $position))->assertSessionHasErrors('formation_count');

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 3, 'formation_is_confirmed' => true]);
    }

    public function test_filter_and_pagination_context_survives_edit_save_and_cancel(): void
    {
        $position = $this->position();
        $section = $position->unit->sections()->create(['code' => 'SECTION', 'name' => 'Section', 'active' => true]);
        $context = [
            'search' => 'Position',
            'directorate_id' => (string) $position->unit->department->directorate_id,
            'department_id' => (string) $position->unit->department_id,
            'unit_id' => (string) $position->unit_id,
            'section_id' => (string) $section->id,
            'confirmation' => 'unconfirmed',
            'gap_status' => 'vacant',
            'per_page' => '40',
            'page' => '2',
        ];

        $this->actingAs($this->superAdmin())->get(route('formasi.requirement.edit', ['position' => $position, ...$context]))
            ->assertOk()
            ->assertViewHas('returnParameters', $context)
            ->assertViewHas('returnUrl', route('formasi', $context))
            ->assertSee('href="'.e(route('formasi', $context)).'"', escape: false)
            ->assertSee('name="page" value="2"', escape: false);

        $this->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 2,
            'confirm_formation' => '1',
            ...$context,
        ])->assertRedirect(route('formasi', $context))->assertSessionHasNoErrors();
    }

    public function test_untrusted_return_urls_and_invalid_context_are_discarded_on_success_and_validation_failure(): void
    {
        $position = $this->position();
        $untrusted = [
            'return_url' => 'https://attacker.example.test/landing',
            'redirect_to' => '//attacker.example.test/landing',
            'search' => ['invalid'],
            'department_id' => 999999,
            'per_page' => 1000,
            'confirmation' => 'wrong',
            'page' => '2',
        ];

        $this->actingAs($this->superAdmin())->get(route('formasi.requirement.edit', ['position' => $position, ...$untrusted]))
            ->assertOk()->assertViewHas('returnParameters', ['page' => '2'])
            ->assertViewHas('returnUrl', route('formasi', ['page' => 2]));

        $this->from('https://attacker.example.test/landing')
            ->patch(route('formasi.requirement.update', $position), ['formation_count' => 2, ...$untrusted])
            ->assertRedirect(route('formasi.requirement.edit', ['position' => $position, 'page' => 2]))
            ->assertSessionHasErrors('confirm_formation');

        $this->patch(route('formasi.requirement.update', $position), [
            'formation_count' => 2,
            'confirm_formation' => '1',
            ...$untrusted,
        ])->assertRedirect(route('formasi', ['page' => 2]))->assertSessionHasNoErrors();
    }

    #[DataProvider('deniedRoles')]
    public function test_roles_without_organization_update_cannot_open_or_submit_need_form(UserRole $role): void
    {
        $position = $this->position();
        $user = User::factory()->create(['role' => $role->value]);

        $this->actingAs($user)->get(route('formasi.requirement.edit', $position))->assertForbidden();
        $this->patch(route('formasi.requirement.update', $position), ['formation_count' => 8, 'confirm_formation' => '1'])->assertForbidden();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
    }

    public function test_delegated_formation_and_organization_view_do_not_allow_need_updates(): void
    {
        $position = $this->position();
        $user = $this->userWithPermissions(['formation.view', 'organization.view']);

        $this->actingAs($user)->get(route('formasi.requirement.edit', $position))->assertForbidden();
        $this->patch(route('formasi.requirement.update', $position), ['formation_count' => 5, 'confirm_formation' => '1'])->assertForbidden();
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_is_confirmed' => false]);
    }

    public function test_delegated_organization_updater_can_confirm_the_need(): void
    {
        $position = $this->position();
        $user = $this->userWithPermissions(['formation.view', 'organization.view', 'organization.update']);

        $this->actingAs($user)->get(route('formasi.requirement.edit', $position))->assertOk();
        $this->patch(route('formasi.requirement.update', $position), ['formation_count' => 5, 'confirm_formation' => '1'])
            ->assertRedirect(route('formasi'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 5, 'formation_is_confirmed' => true]);
    }

    public function test_guests_cannot_open_or_submit_need_form(): void
    {
        $position = $this->position();

        $this->get(route('formasi.requirement.edit', $position))->assertRedirect(route('login'));
        $this->patch(route('formasi.requirement.update', $position), ['formation_count' => 5, 'confirm_formation' => '1'])->assertRedirect(route('login'));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function missingConfirmationCases(): array
    {
        return [
            'missing' => [[]],
            'unchecked' => [['confirm_formation' => '0']],
            'invalid' => [['confirm_formation' => 'invalid']],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function invalidCounts(): array
    {
        return ['missing' => [null], 'zero' => [0], 'negative' => [-1], 'above maximum' => [1000], 'text' => ['invalid']];
    }

    /** @return array<string, array{UserRole}> */
    public static function deniedRoles(): array
    {
        return ['admin' => [UserRole::ADMIN], 'manager' => [UserRole::MANAGER], 'staff' => [UserRole::STAFF], 'member' => [UserRole::MEMBER]];
    }

    private function position(): Position
    {
        $directorate = Directorate::query()->create(['code' => 'DIRECTORATE', 'name' => 'Directorate', 'active' => true]);
        $department = Department::query()->create(['directorate_id' => $directorate->id, 'code' => 'DEPARTMENT', 'name' => 'Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNIT', 'name' => 'Unit', 'active' => true]);

        return $unit->positions()->create(['code' => 'POSITION', 'name' => 'Position', 'active' => true]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    /** @return array<string, int|string|null> */
    private function masterPayload(Position $position): array
    {
        return [
            'directorate_id' => $position->unit->department->directorate_id,
            'department_id' => $position->unit->department_id,
            'unit_id' => $position->unit_id,
            'code' => $position->code,
            'name' => $position->name,
            'active' => '1',
        ];
    }

    private function assertFormationInput(TestResponse $response, string $value, bool $confirmed): void
    {
        $this->assertMatchesRegularExpression('/name="formation_count"[^>]*value="'.preg_quote($value, '/').'"/s', $response->getContent());
        $this->assertSame(1, preg_match('/<input\b[^>]*name="confirm_formation"[^>]*>/s', $response->getContent(), $confirmationInput));

        if ($confirmed) {
            $this->assertStringContainsString('checked', $confirmationInput[0]);
        } else {
            $this->assertStringNotContainsString('checked', $confirmationInput[0]);
        }
    }

    /** @param list<string> $permissionKeys */
    private function userWithPermissions(array $permissionKeys): User
    {
        $this->seed(PermissionSeeder::class);
        foreach ($permissionKeys as $key) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => Permission::query()->where('key', $key)->value('id'),
                'role' => UserRole::STAFF->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        app(RolePermissionCache::class)->clear();

        return User::factory()->create(['role' => UserRole::STAFF->value]);
    }
}
