<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DashboardLayout;
use App\Models\FitProperActivity;
use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    private const Workspace = 'hc-od-career-management';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    protected function migrateDatabases(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate', ['--no-interaction' => true])->assertExitCode(0);
    }

    public function test_guests_cannot_save_a_dashboard_layout(): void
    {
        $this->putJson(route('dashboard.layout'), $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('dashboard_layouts', 0);
    }

    public function test_guests_cannot_read_calendar_events(): void
    {
        $this->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertUnauthorized();
    }

    public function test_default_widgets_are_returned_without_persisting_an_unsaved_layout(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertViewHas('dashboardLayout', $this->defaultSlots())
            ->assertViewHas('canViewCalendar', true);

        $widgetIds = array_column($response->viewData('dashboardWidgets'), 'id');
        sort($widgetIds);
        $this->assertSame([
            'career-movements', 'fit-proper-participants', 'fit-proper-status', 'follow-up',
            'pending-requests', 'total-employees', 'wla-draft', 'wla-summary',
        ], $widgetIds);
        $this->assertDatabaseCount('dashboard_layouts', 0);
    }

    public function test_default_widgets_are_filtered_by_permissions_without_filling_other_available_slots(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['dashboard.view', 'wla.view']);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertViewHas('dashboardLayout', [
            'compact-1' => null, 'compact-2' => null, 'main-1' => null, 'main-2' => null,
            'right-1' => null, 'right-2' => 'wla-summary',
        ]);

        $this->assertSame(['wla-draft', 'wla-summary'], array_column($response->viewData('dashboardWidgets'), 'id'));
        $this->assertDatabaseCount('dashboard_layouts', 0);
    }

    public function test_saving_and_replacing_a_layout_persists_one_record_and_restores_it_on_reload(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        $slots = [
            'compact-1' => 'wla-draft', 'compact-2' => 'pending-requests',
            'main-1' => 'wla-summary', 'main-2' => 'follow-up',
            'right-1' => 'fit-proper-status', 'right-2' => 'career-movements',
        ];

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($slots))
            ->assertOk()->assertJsonPath('workspace', self::Workspace)->assertJsonPath('slots', $slots);

        $layout = DashboardLayout::query()->sole();
        $this->assertSame($user->id, $layout->user_id);
        $this->assertSame(self::Workspace, $layout->workspace);
        $this->assertSame($slots, $layout->slots);
        $this->get(route('dashboard'))->assertViewHas('dashboardLayout', $slots);

        $replacement = [
            'compact-1' => null, 'compact-2' => 'total-employees',
            'main-1' => null, 'main-2' => 'career-movements',
            'right-1' => 'wla-summary', 'right-2' => null,
        ];
        $this->putJson(route('dashboard.layout'), $this->payload($replacement))
            ->assertOk()->assertJsonPath('slots', $replacement);

        $this->assertDatabaseCount('dashboard_layouts', 1);
        $this->assertSame($replacement, $layout->fresh()->slots);
        $this->get(route('dashboard'))->assertViewHas('dashboardLayout', $replacement);
    }

    public function test_a_deliberately_empty_layout_stays_empty_after_reload(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        $slots = $this->emptySlots();

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($slots))
            ->assertOk()->assertJsonPath('slots', $slots);

        $this->get(route('dashboard'))->assertViewHas('dashboardLayout', $slots);
        $this->assertSame($slots, DashboardLayout::query()->sole()->slots);
    }

    public function test_main_widgets_can_swap_between_center_and_right_slots(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $this->defaultSlots()]);
        $slots = [
            'compact-1' => 'total-employees', 'compact-2' => 'fit-proper-participants',
            'main-1' => 'fit-proper-status', 'main-2' => 'wla-summary',
            'right-1' => 'career-movements', 'right-2' => 'follow-up',
        ];

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($slots))
            ->assertOk()->assertJsonPath('slots', $slots);

        $this->assertSame($slots, DashboardLayout::query()->sole()->slots);
        $this->get(route('dashboard'))->assertViewHas('dashboardLayout', $slots);
    }

    public function test_legacy_four_slot_layout_keeps_its_original_values_and_leaves_new_right_slots_empty(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        $stored = [
            'compact-1' => 'wla-draft', 'compact-2' => null,
            'main-1' => 'fit-proper-status', 'main-2' => 'career-movements',
        ];
        $layout = DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $stored]);

        $this->actingAs($user)->get(route('dashboard'))->assertViewHas('dashboardLayout', [
            ...$stored, 'right-1' => null, 'right-2' => null,
        ]);

        $this->assertSame($stored, $layout->fresh()->slots);
        $this->assertDatabaseCount('dashboard_layouts', 1);
    }

    public function test_legacy_four_slot_save_is_rejected_without_discarding_existing_right_widgets(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        $layout = DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $this->defaultSlots()]);
        $legacy = [
            'compact-1' => 'wla-draft', 'compact-2' => null,
            'main-1' => 'fit-proper-status', 'main-2' => 'career-movements',
        ];

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($legacy))
            ->assertUnprocessable()->assertInvalid([
                'slots' => 'Seluruh enam slot dashboard harus disertakan. Muat ulang dashboard lalu coba lagi.',
                'slots.right-1' => 'Seluruh enam slot dashboard harus disertakan. Muat ulang dashboard lalu coba lagi.',
                'slots.right-2' => 'Seluruh enam slot dashboard harus disertakan. Muat ulang dashboard lalu coba lagi.',
            ]);

        $this->assertSame($this->defaultSlots(), $layout->fresh()->slots);
        $this->assertDatabaseCount('dashboard_layouts', 1);
    }

    public function test_saving_a_layout_cannot_target_another_user_or_overwrite_another_workspace(): void
    {
        $actor = $this->user(UserRole::SUPER_ADMIN);
        $other = $this->user(UserRole::SUPER_ADMIN);
        $otherLayout = DashboardLayout::factory()->for($other)->create(['workspace' => self::Workspace, 'slots' => $this->emptySlots()]);
        $otherWorkspace = DashboardLayout::factory()->for($actor)->create(['workspace' => 'performance-talent-management', 'slots' => $this->emptySlots()]);
        $payload = [...$this->payload(), 'user_id' => $other->id, 'id' => $otherLayout->id];

        $this->actingAs($actor)->putJson(route('dashboard.layout'), $payload)
            ->assertOk()->assertJsonPath('slots', $this->defaultSlots());

        $this->assertSame($this->defaultSlots(), DashboardLayout::query()->where('user_id', $actor->id)->where('workspace', self::Workspace)->sole()->slots);
        $this->assertSame($this->emptySlots(), $otherLayout->fresh()->slots);
        $this->assertSame($this->emptySlots(), $otherWorkspace->fresh()->slots);
        $this->actingAs($other)->get(route('dashboard'))->assertViewHas('dashboardLayout', $this->emptySlots());
        $this->assertDatabaseCount('dashboard_layouts', 3);
    }

    #[DataProvider('invalidLayouts')]
    public function test_invalid_layouts_return_422_without_overwriting_saved_slots(array $invalidPayload, string $errorKey): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        $layout = DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $this->defaultSlots()]);

        $this->actingAs($user)->putJson(route('dashboard.layout'), $invalidPayload)
            ->assertUnprocessable()->assertJsonValidationErrors($errorKey);

        $this->assertSame($this->defaultSlots(), $layout->fresh()->slots);
        $this->assertDatabaseCount('dashboard_layouts', 1);
    }

    public function test_dashboard_permission_is_required_to_save_even_when_module_access_exists(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['employees.view']);

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('dashboard_layouts', 0);
    }

    public function test_workspace_denial_blocks_layout_and_calendar_routes_without_writes(): void
    {
        $user = $this->user();
        $permission = Permission::query()->where('key', 'workspace.hc-od-career-management.view')->sole();
        $user->permissionOverrides()->attach($permission, ['granted' => false]);

        $this->actingAs($user->fresh())->putJson(route('dashboard.layout'), $this->payload())
            ->assertForbidden();
        $this->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertForbidden();

        $this->assertDatabaseCount('dashboard_layouts', 0);
    }

    public function test_view_access_is_sufficient_to_personalize_without_module_write_permissions(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['dashboard.view', 'employees.view']);
        $slots = [...$this->emptySlots(), 'compact-2' => 'total-employees'];

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($slots))
            ->assertOk()->assertJsonPath('slots', $slots);

        $this->assertSame($slots, DashboardLayout::query()->sole()->slots);
        $response = $this->get(route('dashboard'))->assertViewHas('dashboardLayout', $slots)->assertViewHas('canViewCalendar', false);
        $this->assertSame(['total-employees'], array_column($response->viewData('dashboardWidgets'), 'id'));
    }

    #[DataProvider('restrictedWidgets')]
    public function test_widgets_from_inaccessible_modules_return_422_and_preserve_the_saved_layout(string $slot, string $widget): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['dashboard.view']);
        $layout = DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $this->emptySlots()]);
        $slots = [...$this->emptySlots(), $slot => $widget];

        $this->actingAs($user)->putJson(route('dashboard.layout'), $this->payload($slots))
            ->assertUnprocessable()->assertJsonValidationErrors('slots.'.$slot);

        $this->assertSame($this->emptySlots(), $layout->fresh()->slots);
    }

    public function test_revoked_permissions_remove_only_disallowed_widgets_without_refilling_empty_slots(): void
    {
        $user = $this->user();
        $stored = [
            'compact-1' => 'total-employees', 'compact-2' => 'wla-draft',
            'main-1' => 'wla-summary', 'main-2' => 'career-movements',
            'right-1' => 'fit-proper-status', 'right-2' => 'follow-up',
        ];
        $layout = DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => $stored]);
        $this->replaceRolePermissions(['dashboard.view', 'wla.view', 'mutations.view']);

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertViewHas('dashboardLayout', [
                'compact-1' => null, 'compact-2' => 'wla-draft',
                'main-1' => 'wla-summary', 'main-2' => 'career-movements',
                'right-1' => null, 'right-2' => 'follow-up',
            ])
            ->assertViewHas('canViewCalendar', false);

        $widgetIds = array_column($response->viewData('dashboardWidgets'), 'id');
        sort($widgetIds);
        $this->assertSame(['career-movements', 'follow-up', 'pending-requests', 'wla-draft', 'wla-summary'], $widgetIds);
        $this->assertSame($stored, $layout->fresh()->slots);
    }

    public function test_legacy_unknown_wrong_sized_and_duplicate_saved_widgets_are_normalized(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);
        DashboardLayout::factory()->for($user)->create(['workspace' => self::Workspace, 'slots' => [
            'compact-1' => 'unknown-widget', 'compact-2' => 'career-movements',
            'main-1' => 'fit-proper-status', 'main-2' => 'fit-proper-status', 'extra-slot' => 'follow-up',
        ]]);

        $this->actingAs($user)->get(route('dashboard'))->assertViewHas('dashboardLayout', [
            'compact-1' => null, 'compact-2' => null, 'main-1' => 'fit-proper-status', 'main-2' => null,
            'right-1' => null, 'right-2' => null,
        ]);
    }

    public function test_calendar_lists_only_creator_pic_and_examiner_activities_within_the_exclusive_date_range(): void
    {
        $user = $this->user();
        $other = $this->user(UserRole::SUPER_ADMIN);
        $this->replaceRolePermissions(['dashboard.view', 'fit-proper.view']);
        $created = $this->activity($user, '2026-11-01', 'Owned event');
        $pic = $this->activity($other, '2026-11-12', 'PIC event');
        $pic->pics()->attach($user);
        $examiner = $this->activity($other, '2026-11-30', 'Examiner event');
        $examiner->examiners()->create(['user_id' => $user->id, 'name' => $user->name]);
        $this->activity($other, '2026-11-15', 'Inaccessible event');
        $this->activity($user, '2026-10-31', 'Before range');
        $this->activity($user, '2026-12-01', 'At exclusive end');

        $response = $this->actingAs($user)->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertOk()->assertJsonCount(3);

        $this->assertSame([
            ['id' => 'fit-proper-'.$created->id, 'title' => 'Owned event', 'start' => '2026-11-01', 'date' => '2026-11-01', 'url' => route('fit-proper.show', $created), 'allDay' => true],
            ['id' => 'fit-proper-'.$pic->id, 'title' => 'PIC event', 'start' => '2026-11-12', 'date' => '2026-11-12', 'url' => route('fit-proper.show', $pic), 'allDay' => true],
            ['id' => 'fit-proper-'.$examiner->id, 'title' => 'Examiner event', 'start' => '2026-11-30', 'date' => '2026-11-30', 'url' => route('fit-proper.show', $examiner), 'allDay' => true],
        ], $response->json());
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_calendar_view_all_permission_includes_another_creators_activity(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['dashboard.view', 'fit-proper.view', 'fit-proper.view-all']);
        $activity = $this->activity($this->user(UserRole::SUPER_ADMIN), '2026-11-07', 'Shared event');

        $this->actingAs($user)->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', 'fit-proper-'.$activity->id);
    }

    public function test_calendar_denies_module_access_even_for_an_activity_creator(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['dashboard.view']);
        $this->activity($user, '2026-11-07', 'Private activity');

        $this->actingAs($user)->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertForbidden()->assertDontSee('Private activity');
    }

    public function test_calendar_requires_dashboard_permission_even_with_fit_proper_access(): void
    {
        $user = $this->user();
        $this->replaceRolePermissions(['fit-proper.view']);

        $this->actingAs($user)->getJson(route('dashboard.calendar', ['start' => '2026-11-01', 'end' => '2026-12-01']))
            ->assertForbidden();
    }

    #[DataProvider('invalidCalendarRanges')]
    public function test_invalid_calendar_ranges_return_422(array $query, string $errorKey): void
    {
        $this->actingAs($this->user(UserRole::SUPER_ADMIN))->getJson(route('dashboard.calendar', $query))
            ->assertUnprocessable()->assertJsonValidationErrors($errorKey);
    }

    public function test_calendar_accepts_a_range_of_exactly_sixty_two_days(): void
    {
        $this->actingAs($this->user(UserRole::SUPER_ADMIN))
            ->getJson(route('dashboard.calendar', ['start' => '2026-10-01', 'end' => '2026-12-02']))
            ->assertOk()->assertExactJson([]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidLayouts(): array
    {
        $slots = [
            'compact-1' => 'total-employees', 'compact-2' => 'fit-proper-participants',
            'main-1' => 'career-movements', 'main-2' => 'follow-up',
            'right-1' => 'fit-proper-status', 'right-2' => 'wla-summary',
        ];
        $payload = ['workspace' => self::Workspace, 'slots' => $slots];

        return [
            'workspace missing' => [['slots' => $slots], 'workspace'],
            'unsupported workspace' => [[...$payload, 'workspace' => 'performance-talent-management'], 'workspace'],
            'slots missing' => [['workspace' => self::Workspace], 'slots'],
            'slots not an object' => [[...$payload, 'slots' => 'total-employees'], 'slots'],
            'main slot missing' => [[...$payload, 'slots' => array_diff_key($slots, ['main-2' => null])], 'slots.main-2'],
            'first right slot missing' => [[...$payload, 'slots' => array_diff_key($slots, ['right-1' => null])], 'slots.right-1'],
            'second right slot missing' => [[...$payload, 'slots' => array_diff_key($slots, ['right-2' => null])], 'slots.right-2'],
            'extra slot exceeds capacity' => [[...$payload, 'slots' => [...$slots, 'main-3' => 'follow-up']], 'slots'],
            'unknown widget' => [[...$payload, 'slots' => [...$slots, 'compact-1' => 'unregistered-widget']], 'slots.compact-1'],
            'main widget in compact slot' => [[...$payload, 'slots' => [...$slots, 'compact-2' => 'follow-up']], 'slots.compact-2'],
            'compact widget in main slot' => [[...$payload, 'slots' => [...$slots, 'main-2' => 'wla-draft']], 'slots.main-2'],
            'compact widget in first right slot' => [[...$payload, 'slots' => [...$slots, 'right-1' => 'wla-draft']], 'slots.right-1'],
            'compact widget in second right slot' => [[...$payload, 'slots' => [...$slots, 'right-2' => 'pending-requests']], 'slots.right-2'],
            'unknown right widget' => [[...$payload, 'slots' => [...$slots, 'right-1' => 'unregistered-widget']], 'slots.right-1'],
            'duplicate widget' => [[...$payload, 'slots' => [...$slots, 'compact-2' => 'total-employees']], 'slots.compact-2'],
            'duplicate widget across center and right' => [[...$payload, 'slots' => [...$slots, 'right-1' => 'career-movements']], 'slots.right-1'],
            'duplicate widget across right slots' => [[...$payload, 'slots' => [...$slots, 'right-2' => 'fit-proper-status']], 'slots.right-2'],
            'array widget value' => [[...$payload, 'slots' => [...$slots, 'compact-1' => ['total-employees']]], 'slots.compact-1'],
            'numeric widget value' => [[...$payload, 'slots' => [...$slots, 'compact-1' => 1]], 'slots.compact-1'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function restrictedWidgets(): array
    {
        return [
            'employee metric' => ['compact-1', 'total-employees'],
            'WLA metric' => ['compact-1', 'wla-draft'],
            'Fit Proper metric' => ['compact-1', 'fit-proper-participants'],
            'movement metric' => ['compact-1', 'pending-requests'],
            'career chart' => ['main-1', 'career-movements'],
            'Fit Proper chart' => ['main-1', 'fit-proper-status'],
            'WLA chart' => ['main-1', 'wla-summary'],
            'follow up list' => ['main-1', 'follow-up'],
            'right career chart' => ['right-1', 'career-movements'],
            'right Fit Proper chart' => ['right-1', 'fit-proper-status'],
            'right WLA chart' => ['right-2', 'wla-summary'],
            'right follow up list' => ['right-2', 'follow-up'],
        ];
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidCalendarRanges(): array
    {
        return [
            'start missing' => [['end' => '2026-12-01'], 'start'],
            'end missing' => [['start' => '2026-11-01'], 'end'],
            'invalid start' => [['start' => 'not-a-date', 'end' => '2026-12-01'], 'start'],
            'invalid calendar date' => [['start' => '2026-02-30', 'end' => '2026-03-10'], 'start'],
            'timestamp instead of date' => [['start' => '2026-11-01T00:00:00Z', 'end' => '2026-12-01'], 'start'],
            'same boundary' => [['start' => '2026-11-01', 'end' => '2026-11-01'], 'end'],
            'reversed boundary' => [['start' => '2026-12-01', 'end' => '2026-11-01'], 'end'],
            'sixty three days' => [['start' => '2026-10-01', 'end' => '2026-12-03'], 'end'],
        ];
    }

    private function user(UserRole $role = UserRole::MEMBER): User
    {
        return User::factory()->create(['role' => $role->value, 'must_change_password' => false]);
    }

    /** @return array<string, string|null> */
    private function defaultSlots(): array
    {
        return [
            'compact-1' => 'total-employees', 'compact-2' => 'fit-proper-participants',
            'main-1' => 'career-movements', 'main-2' => 'follow-up',
            'right-1' => 'fit-proper-status', 'right-2' => 'wla-summary',
        ];
    }

    /** @return array<string, null> */
    private function emptySlots(): array
    {
        return ['compact-1' => null, 'compact-2' => null, 'main-1' => null, 'main-2' => null, 'right-1' => null, 'right-2' => null];
    }

    /**
     * @param  array<string, string|null>|null  $slots
     * @return array{workspace: string, slots: array<string, string|null>}
     */
    private function payload(?array $slots = null): array
    {
        return ['workspace' => self::Workspace, 'slots' => $slots ?? $this->defaultSlots()];
    }

    private function activity(User $creator, string $date, string $name): FitProperActivity
    {
        return FitProperActivity::query()->create([
            'name' => $name, 'target_position' => 'Analyst', 'scheduled_at' => $date,
            'created_by' => $creator->id, 'standard' => [], 'status' => 'Draft',
        ]);
    }

    /** @param list<string> $keys */
    private function replaceRolePermissions(array $keys): void
    {
        DB::table('permission_role')->where('role', UserRole::MEMBER->value)->delete();

        foreach (Permission::query()->whereIn('key', $keys)->pluck('id') as $permissionId) {
            DB::table('permission_role')->insert([
                'role' => UserRole::MEMBER->value, 'permission_id' => $permissionId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        app(RolePermissionCache::class)->clear();
    }
}
