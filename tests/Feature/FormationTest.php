<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('formationRoutes')]
    public function test_guests_are_redirected_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    public function test_default_and_legacy_counts_are_not_confirmed_or_included_in_gap_totals(): void
    {
        $unit = $this->unit();
        $default = $this->position($unit, 'DEFAULT');
        $legacy = $this->position($unit, 'LEGACY', ['formation_count' => 7]);
        $this->employees($legacy, 2);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi'));

        $response->assertViewHas('summary', [
            'total' => 2,
            'confirmed' => 0,
            'unconfirmed' => 2,
            'shortage' => 0,
            'excess' => 0,
        ])->assertSee('Pegawai Terhubung');
        $this->assertDatabaseHas('positions', [
            'id' => $default->id,
            'formation_count' => 1,
            'formation_is_confirmed' => false,
        ]);

        foreach ([$default, $legacy] as $position) {
            $cells = $this->formationRow($response, $position);
            $this->assertSame('Belum ditetapkan', $cells[5]);
            $this->assertSame('—', $cells[7]);
            $this->assertSame('Belum ditetapkan', $cells[8]);
        }
    }

    public function test_new_positions_keep_default_one_without_an_explicit_confirmation(): void
    {
        $unit = $this->unit();

        $this->actingAs($this->superAdmin())->post(route('organization.positions.store'), [
            'department_id' => $unit->department_id,
            'unit_id' => $unit->id,
            'code' => 'NEW',
            'name' => 'New Position',
            'formation_count' => 9,
            'formation_is_confirmed' => true,
            'active' => '1',
        ])->assertRedirect(route('organization.positions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'code' => 'NEW',
            'formation_count' => 1,
            'formation_is_confirmed' => false,
        ]);
    }

    public function test_manual_confirmation_of_one_is_used_by_the_analysis(): void
    {
        $position = $this->position($this->unit(), 'MANUAL');

        $this->actingAs($this->superAdmin())
            ->put(route('organization.positions.update', $position), $this->positionPayload($position, [
                'confirm_formation' => '1',
                'formation_count' => 1,
            ]))->assertRedirect(route('organization.positions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'formation_count' => 1,
            'formation_is_confirmed' => true,
        ]);
        $response = $this->get(route('formasi'));
        $cells = $this->formationRow($response, $position);
        $this->assertSame('1', $cells[5]);
        $this->assertSame('0', $cells[6]);
        $this->assertSame('+1', $cells[7]);
        $this->assertSame('Kosong', $cells[8]);
    }

    public function test_other_position_changes_do_not_confirm_or_replace_unconfirmed_requirements(): void
    {
        $position = $this->position($this->unit(), 'UNCONFIRMED', ['formation_count' => 4]);

        $this->actingAs($this->superAdmin())
            ->put(route('organization.positions.update', $position), $this->positionPayload($position, [
                'name' => 'Renamed Position',
                'formation_count' => 8,
                'formation_is_confirmed' => true,
            ]))->assertRedirect(route('organization.positions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Renamed Position',
            'formation_count' => 4,
            'formation_is_confirmed' => false,
        ]);
    }

    public function test_other_position_changes_preserve_an_existing_confirmation_and_requirement(): void
    {
        $position = $this->position($this->unit(), 'CONFIRMED', [
            'formation_count' => 3,
            'formation_is_confirmed' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('organization.positions.update', $position), $this->positionPayload($position, [
                'name' => 'Changed Attribute',
                'formation_count' => 8,
                'formation_is_confirmed' => false,
            ]))->assertRedirect(route('organization.positions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Changed Attribute',
            'formation_count' => 3,
            'formation_is_confirmed' => true,
        ]);
    }

    #[DataProvider('confirmedGapCases')]
    public function test_confirmed_requirements_render_the_correct_gap_and_status(int $required, int $actual, string $gap, string $status): void
    {
        $position = $this->position($this->unit(), 'GAP', [
            'formation_count' => $required,
            'formation_is_confirmed' => true,
        ]);
        $this->employees($position, $actual);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi'));

        $cells = $this->formationRow($response, $position);
        $this->assertSame((string) $required, $cells[5]);
        $this->assertSame((string) $actual, $cells[6]);
        $this->assertSame($gap, $cells[7]);
        $this->assertSame($status, $cells[8]);
    }

    public function test_summary_keeps_shortage_and_excess_separate_and_excludes_inactive_positions(): void
    {
        $unit = $this->unit();
        $shortage = $this->position($unit, 'SHORT', ['formation_count' => 4, 'formation_is_confirmed' => true]);
        $excess = $this->position($unit, 'EXCESS', ['formation_count' => 1, 'formation_is_confirmed' => true]);
        $this->position($unit, 'VACANT', ['formation_count' => 2, 'formation_is_confirmed' => true]);
        $this->position($unit, 'UNKNOWN', ['formation_count' => 80]);
        $inactive = $this->position($unit, 'INACTIVE', ['formation_count' => 90, 'formation_is_confirmed' => true, 'active' => false]);
        $this->employees($shortage, 1);
        $this->employees($excess, 4);

        $this->actingAs($this->superAdmin())->get(route('formasi'))
            ->assertViewHas('summary', [
                'total' => 4,
                'confirmed' => 3,
                'unconfirmed' => 1,
                'shortage' => 5,
                'excess' => 3,
            ])
            ->assertViewHas('positions', fn ($positions): bool => ! $positions->contains('id', $inactive->id));
    }

    public function test_same_named_positions_remain_separate_and_unmapped_employees_are_not_guessed(): void
    {
        $firstUnit = $this->unit();
        $secondUnit = $firstUnit->department->units()->create(['code' => 'OTHER', 'name' => 'Other Unit', 'active' => true]);
        $first = $this->position($firstUnit, 'FIRST', ['name' => 'Analyst', 'formation_is_confirmed' => true]);
        $second = $this->position($secondUnit, 'SECOND', ['name' => 'Analyst', 'formation_is_confirmed' => true]);
        $this->employees($first, 2);
        Employee::query()->create(['sap' => 'UNMAPPED', 'position' => 'Analyst', 'position_id' => null]);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi'));

        $response->assertViewHas('unmappedEmployeeCount', 1)
            ->assertViewHas('positions', fn ($positions): bool => $positions->total() === 2);
        $firstCells = $this->formationRow($response, $first);
        $secondCells = $this->formationRow($response, $second);
        $this->assertSame('2', $firstCells[6]);
        $this->assertSame('Kelebihan', $firstCells[8]);
        $this->assertSame('0', $secondCells[6]);
        $this->assertSame('Kosong', $secondCells[8]);
        $this->assertStringContainsString('Other Unit', $secondCells[3]);
    }

    #[DataProvider('gapFilters')]
    public function test_gap_filters_select_only_confirmed_positions_with_the_requested_status(string $filter, string $expectedCode): void
    {
        $unit = $this->unit();
        $shortage = $this->position($unit, 'SHORT', ['formation_count' => 3, 'formation_is_confirmed' => true]);
        $fulfilled = $this->position($unit, 'FULL', ['formation_is_confirmed' => true]);
        $excess = $this->position($unit, 'EXCESS', ['formation_is_confirmed' => true]);
        $this->position($unit, 'VACANT', ['formation_is_confirmed' => true]);
        $this->position($unit, 'UNKNOWN');
        $this->employees($shortage, 1);
        $this->employees($fulfilled, 1);
        $this->employees($excess, 2);

        $this->actingAs($this->superAdmin())->get(route('formasi', ['gap_status' => $filter]))
            ->assertViewHas('positions', fn ($positions): bool => $positions->pluck('code')->all() === [$expectedCode]);
    }

    public function test_organization_search_and_confirmation_filters_use_ids_and_keep_global_summary(): void
    {
        $directorate = Directorate::query()->create(['code' => 'DIR', 'name' => 'Directorate', 'active' => true]);
        $unit = $this->unit(['directorate_id' => $directorate->id]);
        $otherUnit = $unit->department->units()->create(['code' => 'OTHER', 'name' => 'Other Unit', 'active' => true]);
        $section = $unit->sections()->create(['code' => 'SEC', 'name' => 'Section', 'active' => true]);
        $target = $this->position($unit, 'MATCH', ['name' => 'Filtered Analyst', 'section_id' => $section->id, 'formation_is_confirmed' => true]);
        $this->position($otherUnit, 'OTHER', ['name' => 'Filtered Analyst']);

        $this->actingAs($this->superAdmin())->get(route('formasi', [
            'search' => 'Filtered',
            'directorate_id' => $directorate->id,
            'department_id' => $unit->department_id,
            'unit_id' => $unit->id,
            'section_id' => $section->id,
            'confirmation' => 'confirmed',
        ]))
            ->assertViewHas('positions', fn ($positions): bool => $positions->pluck('id')->all() === [$target->id])
            ->assertViewHas('summary', [
                'total' => 2,
                'confirmed' => 1,
                'unconfirmed' => 1,
                'shortage' => 1,
                'excess' => 0,
            ]);

        $this->get(route('formasi', ['confirmation' => 'unconfirmed']))
            ->assertViewHas('positions', fn ($positions): bool => $positions->pluck('code')->all() === ['OTHER']);
    }

    public function test_analysis_is_paginated_without_losing_empty_positions(): void
    {
        $unit = $this->unit();
        foreach (range(1, 21) as $index) {
            $this->position($unit, sprintf('PAGE-%02d', $index));
        }
        $this->actingAs($this->superAdmin());

        $this->get(route('formasi'))
            ->assertViewHas('positions', fn ($positions): bool => $positions->count() === 20 && $positions->total() === 21);
        $this->get(route('formasi', ['page' => 2]))
            ->assertViewHas('positions', fn ($positions): bool => $positions->count() === 1 && $positions->total() === 21);
        $this->get(route('formasi', ['per_page' => 40]))
            ->assertViewHas('positions', fn ($positions): bool => $positions->count() === 21 && $positions->perPage() === 40);
    }

    #[DataProvider('rolesWithoutOrganizationAccess')]
    public function test_formation_access_does_not_allow_master_or_requirement_changes(UserRole $role): void
    {
        $position = $this->position($this->unit(), 'PROTECTED');
        $actor = User::factory()->create(['role' => $role->value]);

        $this->actingAs($actor)->get(route('formasi'))
            ->assertSee('Analisis Formasi')
            ->assertSee('Struktur Organisasi')
            ->assertDontSee(route('organization.positions.edit', $position))
            ->assertDontSee('href="'.route('formasi.master').'"', false);
        $this->get(route('formasi.master'))->assertForbidden();
        $this->get(route('organization.positions.edit', $position))->assertForbidden();
        $this->put(route('organization.positions.update', $position), $this->positionPayload($position, [
            'confirm_formation' => '1',
            'formation_count' => 4,
        ]))->assertForbidden();
        $this->post(route('organization.positions.store'), $this->positionPayload($position, ['code' => 'REJECTED']))->assertForbidden();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
        $this->assertDatabaseMissing('positions', ['code' => 'REJECTED']);
    }

    public function test_master_read_permission_does_not_grant_update_permission(): void
    {
        $directorate = Directorate::query()->create(['code' => 'DIR', 'name' => 'Directorate', 'active' => true]);
        $unit = $this->unit(['directorate_id' => $directorate->id]);
        $section = $unit->sections()->create(['code' => 'SEC', 'name' => 'Section', 'active' => true]);
        $position = $this->position($unit, 'READ-ONLY', ['section_id' => $section->id]);
        $actor = $this->userWithPermissions(['organization.view']);

        $this->actingAs($actor)->get(route('formasi.master'))
            ->assertSee('Kelola Position')
            ->assertSee(route('organization.positions.index'));
        $this->get(route('formasi'))->assertDontSee(route('organization.positions.edit', $position));
        foreach ([
            'directorates' => $directorate,
            'departments' => $unit->department,
            'units' => $unit,
            'sections' => $section,
            'positions' => $position,
        ] as $resource => $model) {
            $this->get(route('organization.'.$resource.'.index'))
                ->assertDontSee(route('organization.'.$resource.'.create'))
                ->assertDontSee(route('organization.'.$resource.'.edit', $model))
                ->assertDontSee('action="'.route('organization.'.$resource.'.destroy', $model).'"', false);
        }
        $this->put(route('organization.positions.update', $position), $this->positionPayload($position, [
            'confirm_formation' => '1',
            'formation_count' => 4,
        ]))->assertForbidden();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
    }

    public function test_formation_routes_enforce_the_formation_permission_separately(): void
    {
        $actor = $this->userWithPermissions(['organization.view']);
        DB::table('permission_role')
            ->where('role', UserRole::STAFF->value)
            ->where('permission_id', Permission::query()->where('key', 'formation.view')->value('id'))
            ->delete();
        app(RolePermissionCache::class)->clear();

        $this->actingAs($actor)->get(route('formasi'))->assertForbidden();
        $this->get(route('formasi.structure'))->assertForbidden();
        $this->get(route('formasi.master'))->assertForbidden();
        $this->get(route('organization.index'))->assertOk();
    }

    public function test_analysis_escapes_position_names(): void
    {
        $this->position($this->unit(), 'ESCAPED', ['name' => '<script>alert("position")</script>']);

        $this->actingAs($this->superAdmin())->get(route('formasi'))
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("position")</script>', false);
    }

    public function test_structure_uses_only_explicit_parent_relations_and_keeps_missing_relations_visible(): void
    {
        $directorate = Directorate::query()->create(['code' => 'DIR', 'name' => 'Real Directorate', 'active' => true]);
        $unit = $this->unit(['directorate_id' => $directorate->id]);
        $section = $unit->sections()->create(['code' => 'SEC', 'name' => 'Real Section', 'active' => true]);
        $parent = $this->position($unit, 'PARENT', ['job_level' => Position::JOB_LEVEL_MANAGER]);
        $child = $this->position($unit, 'CHILD', ['parent_position_id' => $parent->id, 'section_id' => $section->id]);
        $unknown = $this->position($unit, 'UNKNOWN', ['name' => 'Staff without parent']);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi.structure'));

        $response->assertSee('Real Directorate')->assertSee('Real Section')->assertSee('Atasan belum ditetapkan');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//li[@data-structure-position-id="'.$parent->id.'"]/ul/li[@data-structure-position-id="'.$child->id.'"]')->length);
        $this->assertSame(0, $xpath->query('//li[@data-structure-position-id="'.$unknown->id.'"]/ancestor::li[@data-structure-position-id]')->length);
        $this->assertSame('', $xpath->query('//li[@data-structure-position-id="'.$unknown->id.'"]')->item(0)->getAttribute('data-parent-position-id'));
        $this->assertNull($unknown->fresh()->parent_position_id);
    }

    public function test_structure_keeps_positions_with_circular_legacy_relations_visible(): void
    {
        $unit = $this->unit();
        $first = $this->position($unit, 'FIRST');
        $second = $this->position($unit, 'SECOND', ['parent_position_id' => $first->id]);
        $first->update(['parent_position_id' => $second->id]);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi.structure'));

        $response->assertSee('hubungan berulang');
        $this->assertSame(1, substr_count($response->getContent(), 'data-structure-position-id="'.$first->id.'"'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-structure-position-id="'.$second->id.'"'));
    }

    public function test_explicit_confirmation_allows_revising_an_existing_requirement(): void
    {
        $position = $this->position($this->unit(), 'REVISE', ['formation_count' => 2, 'formation_is_confirmed' => true]);

        $this->actingAs($this->superAdmin())
            ->put(route('organization.positions.update', $position), $this->positionPayload($position, [
                'confirm_formation' => '1',
                'formation_count' => 5,
            ]))->assertRedirect(route('organization.positions.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 5, 'formation_is_confirmed' => true]);
    }

    #[DataProvider('invalidRequirements')]
    public function test_explicit_confirmation_validates_the_requirement(mixed $value, string $message): void
    {
        $position = $this->position($this->unit(), 'INVALID');

        $this->actingAs($this->superAdmin())
            ->put(route('organization.positions.update', $position), $this->positionPayload($position, [
                'confirm_formation' => '1',
                'formation_count' => $value,
            ]))->assertSessionHasErrors(['formation_count' => $message]);

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'formation_count' => 1, 'formation_is_confirmed' => false]);
    }

    /** @return array<string, array{string}> */
    public static function formationRoutes(): array
    {
        return [
            'analysis' => ['formasi'],
            'structure' => ['formasi.structure'],
            'master' => ['formasi.master'],
        ];
    }

    /** @return array<string, array{int, int, string, string}> */
    public static function confirmedGapCases(): array
    {
        return [
            'shortage' => [3, 1, '+2', 'Kekurangan'],
            'fulfilled' => [1, 1, '0', 'Terpenuhi'],
            'excess' => [1, 3, '-2', 'Kelebihan'],
            'vacant' => [2, 0, '+2', 'Kosong'],
        ];
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidRequirements(): array
    {
        return [
            'missing' => [null, 'Jumlah formasi wajib diisi.'],
            'noninteger' => ['invalid', 'Jumlah formasi harus berupa angka.'],
            'below minimum' => [0, 'Jumlah formasi minimal 1.'],
            'above maximum' => [1000, 'Jumlah formasi maksimal 999.'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function gapFilters(): array
    {
        return [
            'shortage' => ['shortage', 'SHORT'],
            'fulfilled' => ['fulfilled', 'FULL'],
            'excess' => ['excess', 'EXCESS'],
            'vacant' => ['vacant', 'VACANT'],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function rolesWithoutOrganizationAccess(): array
    {
        return [
            'admin' => [UserRole::ADMIN],
            'manager' => [UserRole::MANAGER],
            'staff' => [UserRole::STAFF],
            'member' => [UserRole::MEMBER],
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function unit(array $attributes = []): Unit
    {
        $department = Department::query()->create([
            'code' => 'DEPARTMENT',
            'name' => 'Department',
            'active' => true,
            ...$attributes,
        ]);

        return $department->units()->create(['code' => 'UNIT', 'name' => 'Unit', 'active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function position(Unit $unit, string $code, array $attributes = []): Position
    {
        return $unit->positions()->create(['code' => $code, 'name' => $code, 'active' => true, ...$attributes]);
    }

    private function employees(Position $position, int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $position->employees()->create(['sap' => 'FORMATION-'.$position->id.'-'.$index]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function positionPayload(Position $position, array $overrides = []): array
    {
        return [
            'directorate_id' => $position->unit->department->directorate_id,
            'department_id' => $position->unit->department_id,
            'unit_id' => $position->unit_id,
            'code' => $position->code,
            'name' => $position->name,
            'active' => '1',
            ...$overrides,
        ];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
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

    /** @return list<string> */
    private function formationRow(TestResponse $response, Position $position): array
    {
        $found = preg_match('/<tr\b[^>]*data-position-id="'.$position->id.'"[^>]*>(.*?)<\/tr>/s', $response->getContent(), $row);
        $this->assertSame(1, $found, 'Missing analysis row for Position ID '.$position->id);
        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $row[1], $cells);

        return array_map(fn (string $cell): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), $cells[1]);
    }
}
