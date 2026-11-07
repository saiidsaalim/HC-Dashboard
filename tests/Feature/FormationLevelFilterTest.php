<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormationLevelFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_options_include_known_levels_and_nonempty_stored_values_without_inference(): void
    {
        $unit = $this->unit('OPTIONS');
        $this->position($unit, 'KNOWN', Position::JOB_LEVEL_MANAGER);
        $legacy = $this->position($unit, 'LEGACY', 'Supervisor', ['active' => false]);
        $this->position($unit, 'EMPTY', '');
        $this->position($unit, 'WHITESPACE', '   ');

        $this->actingAs($this->superAdmin())->get(route('formasi'))
            ->assertOk()
            ->assertViewHas('jobLevels', [...array_keys(Position::JOB_LEVEL_PRIORITIES), 'Supervisor']);

        $this->assertSame('Supervisor', $legacy->fresh()->job_level);
    }

    #[DataProvider('knownLevels')]
    public function test_level_filter_keeps_same_named_positions_separate_across_units(string $level): void
    {
        $unit = $this->unit('FIRST');
        $otherUnit = $this->unit('SECOND');
        $first = $this->position($unit, 'FIRST', $level, ['name' => 'Analyst']);
        $second = $this->position($otherUnit, 'SECOND', $level, ['name' => 'Analyst']);
        $this->position($unit, 'OTHER', $level === Position::JOB_LEVEL_STAFF ? Position::JOB_LEVEL_MANAGER : Position::JOB_LEVEL_STAFF);
        $first->employees()->create(['sap' => 'FIRST-LINKED']);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi', ['job_level' => $level]))->assertOk();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $response->viewData('positions')->pluck('id')->all());
        $this->assertSame(1, $response->viewData('positions')->firstWhere('id', $first->id)->employees_count);
        $this->assertSame(0, $response->viewData('positions')->firstWhere('id', $second->id)->employees_count);
        $this->assertSame($level, $response->viewData('filters')['job_level']);
    }

    public function test_legacy_level_filter_uses_the_stored_value_instead_of_staff_fallback(): void
    {
        $unit = $this->unit('LEGACY');
        $legacy = $this->position($unit, 'LEGACY', 'Supervisor');
        $this->position($unit, 'STAFF', Position::JOB_LEVEL_STAFF);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi', ['job_level' => 'Supervisor']))->assertOk();

        $this->assertSame([$legacy->id], $response->viewData('positions')->pluck('id')->all());
        $this->assertSame('Supervisor', $response->viewData('positions')->sole()->job_level);
    }

    public function test_default_and_empty_level_filter_show_all_active_levels(): void
    {
        $unit = $this->unit('ALL');
        $staff = $this->position($unit, 'STAFF', Position::JOB_LEVEL_STAFF);
        $manager = $this->position($unit, 'MANAGER', Position::JOB_LEVEL_MANAGER);
        $this->position($unit, 'INACTIVE', Position::JOB_LEVEL_SENIOR_MANAGER, ['active' => false]);
        $this->actingAs($this->superAdmin());

        foreach ([[], ['job_level' => '']] as $parameters) {
            $response = $this->get(route('formasi', $parameters))->assertOk();
            $this->assertEqualsCanonicalizing([$staff->id, $manager->id], $response->viewData('positions')->pluck('id')->all());
        }
    }

    #[DataProvider('invalidLevels')]
    public function test_invalid_level_filters_recover_with_an_error(mixed $level): void
    {
        $this->actingAs($this->superAdmin())->get(route('formasi', ['job_level' => $level]))
            ->assertRedirect(route('formasi'))->assertSessionHasErrors('job_level');

        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('formasi'))->assertOk()->assertSee('Level yang dipilih');
    }

    public function test_combined_level_filters_do_not_change_requirements_actuals_or_gap_summary(): void
    {
        $unit = $this->unit('SUMMARY');
        $otherUnit = $this->unit('OTHER');
        $shortage = $this->position($unit, 'SHORTAGE', Position::JOB_LEVEL_MANAGER, [
            'name' => 'Analyst', 'formation_count' => 3, 'formation_is_confirmed' => true,
        ]);
        $excess = $this->position($otherUnit, 'EXCESS', Position::JOB_LEVEL_MANAGER, [
            'name' => 'Analyst', 'formation_count' => 1, 'formation_is_confirmed' => true,
        ]);
        $this->position($unit, 'UNCONFIRMED', Position::JOB_LEVEL_STAFF);
        $shortage->employees()->create(['sap' => 'SHORTAGE-ONE']);
        $excess->employees()->createMany([['sap' => 'EXCESS-ONE'], ['sap' => 'EXCESS-TWO']]);
        $original = $shortage->refresh()->getAttributes();

        $response = $this->actingAs($this->superAdmin())->get(route('formasi', [
            'search' => 'Analyst', 'job_level' => Position::JOB_LEVEL_MANAGER,
            'department_id' => $unit->department_id, 'unit_id' => $unit->id,
            'confirmation' => 'confirmed', 'gap_status' => 'shortage',
        ]))->assertOk()->assertViewHas('summary', [
            'total' => 3, 'confirmed' => 2, 'unconfirmed' => 1, 'shortage' => 2, 'excess' => 1,
        ]);

        $this->assertSame([$shortage->id], $response->viewData('positions')->pluck('id')->all());
        $selected = $response->viewData('positions')->sole();
        $this->assertSame(1, $selected->employees_count);
        $this->assertSame(2, $selected->formation_count - $selected->employees_count);
        $this->assertSame($original, $shortage->refresh()->getAttributes());
    }

    public function test_level_filter_is_retained_in_pagination_urls(): void
    {
        $unit = $this->unit('PAGING');
        for ($index = 1; $index <= 21; $index++) {
            $this->position($unit, sprintf('STAFF-%02d', $index), Position::JOB_LEVEL_STAFF);
        }
        $this->position($unit, 'MANAGER', Position::JOB_LEVEL_MANAGER);

        $response = $this->actingAs($this->superAdmin())->get(route('formasi', [
            'job_level' => Position::JOB_LEVEL_STAFF, 'per_page' => 20, 'page' => 2,
        ]))->assertOk();

        $positions = $response->viewData('positions');
        $this->assertSame(21, $positions->total());
        $this->assertSame(1, $positions->count());
        $this->assertSame(2, $positions->currentPage());
        $this->assertStringContainsString('job_level=Staff', $positions->url(1));
        $this->assertStringContainsString('per_page=20', $positions->url(1));
    }

    public function test_level_context_is_retained_when_cancelling_or_saving_requirement(): void
    {
        $position = $this->position($this->unit('RETURN'), 'MANAGER', Position::JOB_LEVEL_MANAGER);
        $context = ['job_level' => Position::JOB_LEVEL_MANAGER, 'per_page' => '40', 'page' => '2'];

        $this->actingAs($this->superAdmin())->get(route('formasi.requirement.edit', ['position' => $position, ...$context]))
            ->assertOk()->assertViewHas('returnParameters', $context)
            ->assertViewHas('returnUrl', route('formasi', $context))
            ->assertSee('name="job_level" value="Manager"', escape: false)
            ->assertSee('href="'.e(route('formasi', $context)).'"', escape: false);

        $this->patch(route('formasi.requirement.update', $position), [
            ...$context, 'formation_count' => 1, 'confirm_formation' => '1',
        ])->assertRedirect(route('formasi', $context))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'job_level' => Position::JOB_LEVEL_MANAGER, 'formation_count' => 1, 'formation_is_confirmed' => true]);
    }

    public function test_invalid_return_level_context_does_not_block_explicit_requirement_save(): void
    {
        $position = $this->position($this->unit('INVALID-RETURN'), 'STAFF', Position::JOB_LEVEL_STAFF);

        $this->actingAs($this->superAdmin())->get(route('formasi.requirement.edit', [
            'position' => $position, 'job_level' => ['Manager'],
        ]))->assertOk()->assertViewHas('returnParameters', [])->assertViewHas('returnUrl', route('formasi'));

        $this->patch(route('formasi.requirement.update', $position), [
            'job_level' => 'Unknown', 'formation_count' => 2, 'confirm_formation' => '1',
        ])->assertRedirect(route('formasi'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'job_level' => Position::JOB_LEVEL_STAFF, 'formation_count' => 2, 'formation_is_confirmed' => true]);
    }

    /** @return array<string, array{string}> */
    public static function knownLevels(): array
    {
        return array_combine(array_keys(Position::JOB_LEVEL_PRIORITIES), array_map(fn (string $level): array => [$level], array_keys(Position::JOB_LEVEL_PRIORITIES)));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidLevels(): array
    {
        return ['unknown' => ['Executive'], 'array' => [[Position::JOB_LEVEL_MANAGER]]];
    }

    private function unit(string $suffix): Unit
    {
        $department = Department::query()->create(['code' => 'DEPARTMENT-'.$suffix, 'name' => 'Department '.$suffix, 'active' => true]);

        return $department->units()->create(['code' => 'UNIT-'.$suffix, 'name' => 'Unit '.$suffix, 'active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function position(Unit $unit, string $code, string $level, array $attributes = []): Position
    {
        return $unit->positions()->create(['code' => $code, 'name' => $code, 'job_level' => $level, 'active' => true, ...$attributes]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
