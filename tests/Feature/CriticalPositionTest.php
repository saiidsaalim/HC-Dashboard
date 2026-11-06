<?php

namespace Tests\Feature;

use App\Actions\Users\SyncUserWorkspaceAccess;
use App\Enums\CriticalPositionCoverageStatus;
use App\Enums\CriticalPositionCriticality;
use App\Enums\UserRole;
use App\Exports\EmployeeSpreadsheet;
use App\Models\CriticalPosition;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Successor;
use App\Models\User;
use App\Services\Authorization\RolePermissionCache;
use App\Services\Employees\EmployeeOrganizationSyncService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CriticalPositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_authorized_user_can_view_relational_index_and_summary(): void
    {
        $position = $this->position('INDEX', formationCount: 2);
        $this->criticalPosition($position);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('Daftar Critical Position')
            ->assertSee('Total Critical Position')
            ->assertSee('High Criticality')
            ->assertSee('Belum Memiliki Successor')
            ->assertSee($position->name)
            ->assertDontSee('>SAP Position ID</th>', false)
            ->assertDontSee($position->code);
    }

    public function test_dashboard_starts_with_summary_and_omits_the_page_header(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('data-critical-position-summary', false)
            ->assertDontSee('Penilaian posisi kritis yang terhubung langsung dengan Master Organization')
            ->assertSee('Distribusi Criticality')
            ->assertSee('Coverage Formasi')
            ->assertSee('Kesiapan Successor')
            ->assertViewHas('summary', [
                'total' => 0,
                'high' => 0,
                'vacant' => 0,
                'without_successor' => 0,
            ])
            ->assertViewHas('chartData', function (array $chartData): bool {
                return $chartData['criticality']['series'] === [0, 0, 0]
                    && $chartData['coverage']['series'] === [0, 0, 0, 0]
                    && $chartData['successor']['series'] === [0, 0];
            });

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Distribusi Criticality'),
            strpos($content, 'data-critical-position-summary'),
        );
        $this->assertLessThan(
            strpos($content, 'Daftar Critical Position'),
            strpos($content, 'data-critical-position-summary'),
        );
        $this->assertSame(1, substr_count($content, 'openCreate($event.currentTarget)'));
        $this->assertGreaterThan(
            strpos($content, 'Daftar Critical Position'),
            strpos($content, 'openCreate($event.currentTarget)'),
        );
    }

    public function test_kpi_and_chart_data_use_all_filtered_database_records(): void
    {
        $covered = $this->position('ANALYTIC-COVERED');
        $partial = $this->position('ANALYTIC-PARTIAL', formationCount: 2);
        $vacant = $this->position('ANALYTIC-VACANT');
        $overCapacity = $this->position('ANALYTIC-OVER');

        $this->criticalPosition($covered, ['criticality' => 'high']);
        $this->criticalPosition($partial, ['criticality' => 'low']);
        $this->criticalPosition($vacant, ['criticality' => 'medium']);
        $this->criticalPosition($overCapacity, ['criticality' => 'high']);
        $this->employees($covered, 1, 'ANALYTIC-COVERED');
        $this->employees($partial, 1, 'ANALYTIC-PARTIAL');
        $this->employees($overCapacity, 2, 'ANALYTIC-OVER');
        $this->successor($covered, 'Analytic Ready Now', 'ready now');
        $this->successor($partial, 'Analytic One Year', 'ready in one year');

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertViewHas('summary', [
                'total' => 4,
                'high' => 2,
                'vacant' => 1,
                'without_successor' => 2,
            ])
            ->assertViewHas('chartData', function (array $chartData): bool {
                return $chartData['criticality']['series'] === [2, 1, 1]
                    && $chartData['coverage']['series'] === [1, 1, 1, 1]
                    && $chartData['successor']['series'] === [2, 1]
                    && $chartData['successor']['missing'] === 2;
            });

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', ['criticality' => 'high']))
            ->assertOk()
            ->assertViewHas('summary', [
                'total' => 2,
                'high' => 2,
                'vacant' => 0,
                'without_successor' => 1,
            ])
            ->assertViewHas('chartData', function (array $chartData): bool {
                return $chartData['criticality']['series'] === [2, 0, 0]
                    && $chartData['coverage']['series'] === [1, 0, 0, 1]
                    && $chartData['successor']['series'] === [1, 1];
            })
            ->assertViewHas('criticalPositions', fn ($positions): bool => $positions->total() === 2);
    }

    public function test_user_without_view_permission_is_denied(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissions($admin->roleEnum(), ['workspace.performance-talent-management.view']);
        app(SyncUserWorkspaceAccess::class)->execute($admin, ['performance-talent-management']);

        $this->actingAs($admin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertForbidden();
    }

    public function test_create_and_edit_share_one_accessible_centered_modal_form(): void
    {
        $editablePosition = $this->position('MODAL-EDIT');
        $criticalPosition = $this->criticalPosition($editablePosition);
        $this->position('MODAL-CREATE');

        $response = $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('openCreate($event.currentTarget)', false)
            ->assertSee('data-critical-position-form-modal', false)
            ->assertSee('x-teleport="body"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-labelledby="critical-position-form-title"', false)
            ->assertSee('items-center justify-center', false)
            ->assertSee('max-w-4xl', false)
            ->assertSee('sm:max-h-[90vh]', false)
            ->assertSee('trapFormFocus($event)', false)
            ->assertSee('aria-label="Tutup modal"', false)
            ->assertSee('type="button" @click="closeForm()"', false)
            ->assertSee('Simpan Critical Position')
            ->assertSee('Simpan Perubahan')
            ->assertSee(route('performance-talent.critical-positions.store'), false)
            ->assertSee(route('performance-talent.critical-positions.update', $criticalPosition), false)
            ->assertDontSee('fixed inset-y-0 left-0 z-[71]', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-critical-position-form data-create-action'));
        foreach (['position_id', 'criticality', 'assessment_reason', 'assessed_at', 'review_due_at', 'notes'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }
        foreach (['department', 'directorate', 'incumbent', 'sap', 'status'] as $manualField) {
            $response->assertDontSee('name="'.$manualField.'"', false);
        }
        foreach (['high', 'medium', 'low'] as $criticality) {
            $response->assertSee('type="radio" name="criticality" value="'.$criticality.'"', false);
        }
    }

    public function test_create_validation_reopens_modal_with_old_input(): void
    {
        $position = $this->position('CREATE-ERROR');
        $user = $this->superAdmin();
        $oldInput = $this->validData($position, [
            'criticality' => 'medium',
            'assessment_reason' => '',
            'notes' => 'Catatan create tetap tampil',
        ]);

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('performance-talent.critical-positions.index'))
            ->post(route('performance-talent.critical-positions.store'), $oldInput)
            ->assertOk()
            ->assertSee('data-initial-panel="form"', false)
            ->assertSee('data-initial-form-mode="create"', false)
            ->assertSee('Catatan create tetap tampil')
            ->assertSee('Critical Position belum dapat disimpan.')
            ->assertSee('Alasan penilaian wajib diisi.');
    }

    public function test_update_validation_reopens_shared_modal_with_update_action_and_old_input(): void
    {
        $position = $this->position('UPDATE-ERROR');
        $criticalPosition = $this->criticalPosition($position);
        $user = $this->superAdmin();
        $oldInput = $this->validData($position, [
            'critical_position_id' => $criticalPosition->id,
            'criticality' => 'low',
            'assessment_reason' => '',
            'notes' => 'Catatan update tetap tampil',
        ]);

        $this->followingRedirects()
            ->actingAs($user)
            ->from(route('performance-talent.critical-positions.index'))
            ->put(route('performance-talent.critical-positions.update', $criticalPosition), $oldInput)
            ->assertOk()
            ->assertSee('data-initial-panel="form"', false)
            ->assertSee('data-initial-form-mode="edit"', false)
            ->assertSee(route('performance-talent.critical-positions.update', $criticalPosition), false)
            ->assertSee('Catatan update tetap tampil')
            ->assertSee('Alasan penilaian wajib diisi.');
    }

    public function test_create_uses_position_id_lowercase_criticality_and_current_assessor(): void
    {
        $position = $this->position('CREATE');
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->post(route('performance-talent.critical-positions.store'), $this->validData($position, [
                'criticality' => 'HIGH',
            ]))
            ->assertRedirect(route('performance-talent.critical-positions.index'));

        $this->assertDatabaseHas('critical_positions', [
            'position_id' => $position->id,
            'criticality' => 'high',
            'assessed_by' => $user->id,
        ]);
        $this->assertDatabaseMissing('critical_positions', ['criticality' => 'HIGH']);
    }

    public function test_same_position_cannot_be_added_twice(): void
    {
        $position = $this->position('DUPLICATE');
        $this->criticalPosition($position);

        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.critical-positions.index'))
            ->post(route('performance-talent.critical-positions.store'), $this->validData($position))
            ->assertSessionHasErrorsIn('createCriticalPosition', ['position_id']);

        $this->assertDatabaseCount('critical_positions', 1);
    }

    public function test_organization_data_is_read_from_current_position_relations(): void
    {
        $position = $this->position('RELATION');
        $this->criticalPosition($position);
        $position->unit->department->update(['name' => 'Department Relasi Terbaru']);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('Department Relasi Terbaru')
            ->assertDontSee('Department RELATION');
    }

    public function test_incumbents_use_personal_number_as_name_and_show_sap_below_it(): void
    {
        $position = $this->position('INCUMBENT');
        $this->criticalPosition($position);
        Employee::forceCreate([
            'sap' => 'SAP-EMP-INCUMBENT',
            'name' => null,
            'personal_number' => 'PN-INCUMBENT',
            'position_id' => $position->id,
            'position' => 'Legacy Current Position',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSeeInOrder(['PN-INCUMBENT', 'SAP Pegawai: SAP-EMP-INCUMBENT'])
            ->assertDontSee('Personal Number:');
    }

    public function test_incumbent_name_does_not_fall_back_to_the_employee_name_column(): void
    {
        $position = $this->position('INCUMBENT-NO-PERSONAL-NUMBER');
        $this->criticalPosition($position);
        Employee::forceCreate([
            'sap' => 'SAP-EMP-NO-PERSONAL-NUMBER',
            'name' => 'Nama Dari Kolom Lama',
            'personal_number' => null,
            'position_id' => $position->id,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSeeInOrder(['Nama belum tersedia', 'SAP Pegawai: SAP-EMP-NO-PERSONAL-NUMBER'])
            ->assertDontSee('Nama Dari Kolom Lama');
    }

    public function test_position_preview_uses_personal_number_as_incumbent_name_without_position_code(): void
    {
        $position = $this->position('PREVIEW-NAME');
        $this->criticalPosition($position);
        Employee::forceCreate([
            'sap' => 'SAP-PREVIEW-NAME',
            'name' => 'Nama Lama Tidak Digunakan',
            'personal_number' => 'Nama Dari Personal Number',
            'position_id' => $position->id,
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('performance-talent.critical-positions.position-preview', $position))
            ->assertOk()
            ->assertJsonPath('incumbents.0.name', 'Nama Dari Personal Number')
            ->assertJsonPath('incumbents.0.sap', 'SAP-PREVIEW-NAME');

        $this->assertArrayNotHasKey('code', $response->json());
        $this->assertArrayNotHasKey('personal_number', $response->json('incumbents.0'));
    }

    public function test_incumbent_follows_employee_after_position_move_without_updating_assessment(): void
    {
        $oldPosition = $this->position('MOVE-OLD');
        $newPosition = $this->position('MOVE-NEW');
        $criticalPosition = $this->criticalPosition($oldPosition);
        $employee = Employee::forceCreate([
            'sap' => 'SAP-MOVED',
            'name' => 'Pegawai Mutasi',
            'position_id' => $oldPosition->id,
        ]);
        $originalAssessment = $criticalPosition->getAttributes();

        $employee->update(['position_id' => $newPosition->id]);

        $this->assertSame(0, $oldPosition->employees()->count());
        $this->assertSame(1, $newPosition->employees()->count());
        $this->assertEquals(
            collect($originalAssessment)->only(['position_id', 'criticality', 'assessment_reason', 'assessed_at', 'review_due_at', 'notes', 'updated_at'])->all(),
            collect($criticalPosition->fresh()->getAttributes())->only(['position_id', 'criticality', 'assessment_reason', 'assessed_at', 'review_due_at', 'notes', 'updated_at'])->all(),
        );
    }

    public function test_coverage_statuses_are_calculated_from_formation_and_incumbent_counts(): void
    {
        $vacant = $this->position('VACANT', formationCount: 2);
        $partial = $this->position('PARTIAL', formationCount: 2);
        $covered = $this->position('COVERED', formationCount: 2);
        $over = $this->position('OVER', formationCount: 1);

        $this->employees($partial, 1, 'PARTIAL');
        $this->employees($covered, 2, 'COVERED');
        $this->employees($over, 2, 'OVER');

        $this->assertSame(CriticalPositionCoverageStatus::Vacant, $vacant->coverageStatus());
        $this->assertSame(CriticalPositionCoverageStatus::PartiallyCovered, $partial->coverageStatus());
        $this->assertSame(CriticalPositionCoverageStatus::Covered, $covered->coverageStatus());
        $this->assertSame(CriticalPositionCoverageStatus::OverCapacity, $over->coverageStatus());
    }

    public function test_successor_summary_uses_target_position_id(): void
    {
        $position = $this->position('SUCCESSOR');
        $otherPosition = $this->position('SUCCESSOR-OTHER');
        $this->criticalPosition($position);
        $this->successor($position, 'Kandidat Ready Now', 'ready now');
        $this->successor($position, 'Kandidat Satu Tahun', 'ready in one year');
        $this->successor($otherPosition, 'Kandidat Position Lain', 'ready now');

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertSee('2 kandidat')
            ->assertSee('Ready Now: 1')
            ->assertSee('Kandidat Ready Now')
            ->assertDontSee('Kandidat Position Lain');
    }

    public function test_search_and_filters_work_against_position_and_relations(): void
    {
        $matching = $this->position('FILTER-MATCH', jobLevel: Position::JOB_LEVEL_MANAGER);
        $matching->update(['external_position_id' => 'SAP-POS-FIND-ME']);
        $other = $this->position('FILTER-OTHER', jobLevel: Position::JOB_LEVEL_STAFF);
        $this->criticalPosition($matching, ['criticality' => 'high']);
        $this->criticalPosition($other, ['criticality' => 'low']);
        $this->employees($matching, 1, 'FILTER');
        $this->successor($matching, 'Filtered Successor', 'ready now');

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', [
                'search' => 'SAP-POS-FIND-ME',
                'directorate_id' => $matching->unit->department->directorate_id,
                'department_id' => $matching->unit->department_id,
                'job_level' => Position::JOB_LEVEL_MANAGER,
                'criticality' => 'high',
                'coverage_status' => 'covered',
                'successor_status' => 'available',
            ]))
            ->assertOk()
            ->assertViewHas('criticalPositions', fn ($positions): bool => $positions->count() === 1
                && $positions->first()->position_id === $matching->id);
    }

    public function test_pagination_uses_twenty_rows_keeps_numbering_and_query_string(): void
    {
        foreach (range(1, 21) as $number) {
            $position = $this->position(sprintf('PAGE-%02d', $number));
            $this->criticalPosition($position, ['criticality' => 'high']);
        }

        $response = $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', ['criticality' => 'high', 'page' => 2]))
            ->assertOk()
            ->assertSee('Position PAGE-21')
            ->assertSee('criticality=high', false);

        $this->assertMatchesRegularExpression('/>\s*21\s*<\/td>/', $response->getContent());

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', [
                'criticality' => 'high',
                'per_page' => 40,
            ]))
            ->assertOk()
            ->assertViewHas('perPage', 40)
            ->assertViewHas('criticalPositions', fn ($positions): bool => $positions->perPage() === 40
                && $positions->count() === 21)
            ->assertSee('data-per-page-selector', false)
            ->assertSee('name="criticality" value="high"', false);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', ['per_page' => 60]))
            ->assertOk()
            ->assertViewHas('perPage', 60)
            ->assertViewHas('criticalPositions', fn ($positions): bool => $positions->perPage() === 60)
            ->assertSee('value="60" selected', false);

        $this->actingAs($this->superAdmin())
            ->get(route('performance-talent.critical-positions.index', ['per_page' => 999]))
            ->assertOk()
            ->assertViewHas('perPage', 20)
            ->assertViewHas('criticalPositions', fn ($positions): bool => $positions->perPage() === 20);
    }

    public function test_review_schedule_labels_future_today_overdue_and_unscheduled_dates(): void
    {
        $today = CarbonImmutable::parse('2026-11-06');
        $position = $this->position('REVIEW-SCHEDULE');

        $future = $this->criticalPosition($position, ['review_due_at' => '2026-11-19']);
        $this->assertSame('13 hari lagi', $future->reviewSchedule($today)['label']);

        $future->review_due_at = CarbonImmutable::parse('2026-11-06');
        $this->assertSame('Hari ini', $future->reviewSchedule($today)['label']);

        $future->review_due_at = CarbonImmutable::parse('2026-11-03');
        $this->assertSame('Terlambat 3 hari', $future->reviewSchedule($today)['label']);

        $future->review_due_at = null;
        $this->assertSame([
            'date' => 'Belum dijadwalkan',
            'label' => 'Belum dijadwalkan',
            'tone' => 'neutral',
        ], $future->reviewSchedule($today));
    }

    public function test_update_only_changes_assessment_fields(): void
    {
        $position = $this->position('UPDATE');
        $employee = Employee::forceCreate(['sap' => 'SAP-UPDATE', 'name' => 'Tetap Sama', 'position_id' => $position->id]);
        $criticalPosition = $this->criticalPosition($position);
        $positionBefore = $position->fresh()->getAttributes();
        $employeeBefore = $employee->fresh()->getAttributes();

        $this->actingAs($this->superAdmin())
            ->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData($position, [
                'critical_position_id' => $criticalPosition->id,
                'criticality' => 'medium',
                'assessment_reason' => 'Alasan penilaian diperbarui.',
            ]))
            ->assertRedirect(route('performance-talent.critical-positions.index'));

        $this->assertSame($positionBefore, $position->fresh()->getAttributes());
        $this->assertSame($employeeBefore, $employee->fresh()->getAttributes());
        $this->assertSame(CriticalPositionCriticality::Medium, $criticalPosition->fresh()->criticality);
    }

    public function test_delete_only_removes_the_assessment(): void
    {
        $position = $this->position('DELETE');
        $employee = Employee::forceCreate(['sap' => 'SAP-DELETE', 'position_id' => $position->id]);
        $successor = $this->successor($position, 'Successor Tetap', 'ready now');
        $criticalPosition = $this->criticalPosition($position);

        $this->actingAs($this->superAdmin())
            ->delete(route('performance-talent.critical-positions.destroy', $criticalPosition))
            ->assertRedirect(route('performance-talent.critical-positions.index'));

        $this->assertDatabaseMissing('critical_positions', ['id' => $criticalPosition->id]);
        $this->assertDatabaseHas('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'position_id' => $position->id]);
        $this->assertDatabaseHas('successors', ['id' => $successor->id, 'target_position_id' => $position->id]);
    }

    public function test_master_position_used_by_critical_position_cannot_be_deleted(): void
    {
        $position = $this->position('RESTRICT');
        $this->criticalPosition($position);

        $this->actingAs($this->superAdmin())
            ->from(route('organization.positions.index'))
            ->delete(route('organization.positions.destroy', $position))
            ->assertRedirect(route('organization.positions.index'))
            ->assertSessionHasErrors('position');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }

    public function test_review_date_must_not_be_before_assessment_date(): void
    {
        $position = $this->position('DATE');

        $this->actingAs($this->superAdmin())
            ->from(route('performance-talent.critical-positions.index'))
            ->post(route('performance-talent.critical-positions.store'), $this->validData($position, [
                'assessed_at' => '2026-11-05',
                'review_due_at' => '2026-11-04',
            ]))
            ->assertSessionHasErrorsIn('createCriticalPosition', ['review_due_at']);
    }

    public function test_inactive_position_cannot_be_created_but_current_inactive_position_can_be_updated(): void
    {
        $inactivePosition = $this->position('INACTIVE', active: false);

        $this->actingAs($this->superAdmin())
            ->post(route('performance-talent.critical-positions.store'), $this->validData($inactivePosition))
            ->assertSessionHasErrorsIn('createCriticalPosition', ['position_id']);

        $criticalPosition = $this->criticalPosition($inactivePosition);
        $this->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData($inactivePosition, [
            'critical_position_id' => $criticalPosition->id,
            'criticality' => 'low',
        ]))->assertRedirect(route('performance-talent.critical-positions.index'));

        $this->assertSame(CriticalPositionCriticality::Low, $criticalPosition->fresh()->criticality);
    }

    public function test_view_only_user_does_not_see_management_buttons_and_direct_actions_are_denied(): void
    {
        $position = $this->position('VIEW-ONLY');
        $criticalPosition = $this->criticalPosition($position);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->grantPermissions($admin->roleEnum(), [
            'workspace.performance-talent-management.view',
            'critical-positions.view',
        ]);
        app(SyncUserWorkspaceAccess::class)->execute($admin, ['performance-talent-management']);

        $this->actingAs($admin)
            ->get(route('performance-talent.critical-positions.index'))
            ->assertOk()
            ->assertDontSee('Tambah Critical Position')
            ->assertDontSee('>Edit</button>', false)
            ->assertDontSee('>Hapus</button>', false)
            ->assertDontSee('Lihat Successor');

        $this->post(route('performance-talent.critical-positions.store'), $this->validData($this->position('VIEW-CREATE')))->assertForbidden();
        $this->put(route('performance-talent.critical-positions.update', $criticalPosition), $this->validData($position))->assertForbidden();
        $this->delete(route('performance-talent.critical-positions.destroy', $criticalPosition))->assertForbidden();
    }

    public function test_index_eager_loads_relations_without_lazy_loading(): void
    {
        $position = $this->position('EAGER');
        $this->criticalPosition($position);
        $this->employees($position, 2, 'EAGER');
        $this->successor($position, 'Eager Successor', 'ready now');

        Position::preventLazyLoading();
        try {
            $this->actingAs($this->superAdmin())
                ->get(route('performance-talent.critical-positions.index'))
                ->assertOk();
        } finally {
            Position::preventLazyLoading(false);
        }
    }

    public function test_employee_position_fields_are_removed_from_schema_model_and_spreadsheet(): void
    {
        $this->assertFalse(Schema::hasColumn('employees', 'criticality'));
        $this->assertFalse(Schema::hasColumn('employees', 'formation_status'));
        $this->assertNotContains('criticality', (new Employee)->getFillable());
        $this->assertNotContains('formation_status', (new Employee)->getFillable());
        $fields = array_merge(...array_values(EmployeeSpreadsheet::fieldGroups(true)));
        $this->assertNotContains('criticality', $fields);
        $this->assertNotContains('formation_status', $fields);
        $this->assertContains('talent_mapping', $fields);
        $this->assertContains('succession_readiness', $fields);
        $this->assertContains('idp_percentage', $fields);
    }

    public function test_schema_refactor_migrations_can_be_rolled_back_and_applied_again(): void
    {
        $criticalPositionMigration = require database_path('migrations/2026_11_06_185957_replace_critical_positions_prototype_structure.php');
        $employeeMigration = require database_path('migrations/2026_11_06_185958_remove_position_fields_from_employees_table.php');
        $positionMigration = require database_path('migrations/2026_11_06_185959_add_external_position_id_to_positions_table.php');

        $positionMigration->down();
        $employeeMigration->down();
        $criticalPositionMigration->down();

        $this->assertFalse(Schema::hasColumn('positions', 'external_position_id'));
        $this->assertTrue(Schema::hasColumn('employees', 'criticality'));
        $this->assertTrue(Schema::hasColumn('employees', 'formation_status'));
        $this->assertTrue(Schema::hasColumn('critical_positions', 'position'));
        $this->assertFalse(Schema::hasColumn('critical_positions', 'position_id'));

        $criticalPositionMigration->up();
        $employeeMigration->up();
        $positionMigration->up();

        $this->assertTrue(Schema::hasColumn('positions', 'external_position_id'));
        $this->assertFalse(Schema::hasColumn('employees', 'criticality'));
        $this->assertFalse(Schema::hasColumn('employees', 'formation_status'));
        $this->assertFalse(Schema::hasColumn('critical_positions', 'position'));
        $this->assertTrue(Schema::hasColumn('critical_positions', 'position_id'));
    }

    public function test_conflicting_employee_position_source_ids_are_logged_without_silent_selection(): void
    {
        $position = $this->position('EXTERNAL-CONFLICT');
        $position->update(['external_position_id' => null]);
        Employee::forceCreate(['sap' => 'SAP-CONFLICT-1', 'position_id' => $position->id, 'position_id_source' => 'EXT-ONE']);
        Employee::forceCreate(['sap' => 'SAP-CONFLICT-2', 'position_id' => $position->id, 'position_id_source' => 'EXT-TWO']);
        Log::spy();

        $result = app(EmployeeOrganizationSyncService::class)->synchronizePositionExternalIds([$position->id]);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(['EXT-ONE', 'EXT-TWO'], $result['conflicts'][0]['external_position_ids']);
        $this->assertNull($position->fresh()->external_position_id);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_consistent_employee_position_source_id_is_copied_to_master_position(): void
    {
        $position = $this->position('EXTERNAL-CONSISTENT');
        $position->update(['external_position_id' => null]);
        Employee::forceCreate(['sap' => 'SAP-CONSISTENT-1', 'position_id' => $position->id, 'position_id_source' => 'EXT-SAME']);
        Employee::forceCreate(['sap' => 'SAP-CONSISTENT-2', 'position_id' => $position->id, 'position_id_source' => ' EXT-SAME ']);

        $result = app(EmployeeOrganizationSyncService::class)->synchronizePositionExternalIds([$position->id]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame([], $result['conflicts']);
        $this->assertSame('EXT-SAME', $position->fresh()->external_position_id);
    }

    /** @param array<string, mixed> $overrides */
    private function criticalPosition(Position $position, array $overrides = []): CriticalPosition
    {
        return CriticalPosition::query()->create(array_merge([
            'position_id' => $position->id,
            'criticality' => 'high',
            'assessment_reason' => 'Posisi mempunyai dampak besar terhadap keberlangsungan organisasi.',
            'assessed_at' => '2026-11-01',
            'review_due_at' => '2027-05-01',
            'notes' => 'Tinjau berkala.',
        ], $overrides));
    }

    private function position(
        string $suffix,
        int $formationCount = 1,
        bool $active = true,
        string $jobLevel = Position::JOB_LEVEL_STAFF,
    ): Position {
        $directorate = Directorate::query()->create([
            'code' => 'DIR-'.$suffix,
            'name' => 'Direktorat '.$suffix,
            'active' => true,
        ]);
        $department = Department::query()->create([
            'directorate_id' => $directorate->id,
            'code' => 'DPT-'.$suffix,
            'name' => 'Department '.$suffix,
            'active' => true,
        ]);
        $unit = $department->units()->create([
            'code' => 'UNT-'.$suffix,
            'name' => 'Unit '.$suffix,
            'active' => true,
        ]);
        $section = $unit->sections()->create([
            'code' => 'SEC-'.$suffix,
            'name' => 'Section '.$suffix,
            'active' => true,
        ]);

        return $unit->positions()->create([
            'section_id' => $section->id,
            'code' => 'POS-'.$suffix,
            'external_position_id' => 'EXT-'.$suffix,
            'name' => 'Position '.$suffix,
            'job_level' => $jobLevel,
            'formation_count' => $formationCount,
            'active' => $active,
        ])->load('unit.department.directorate', 'section');
    }

    private function employees(Position $position, int $count, string $suffix): void
    {
        foreach (range(1, $count) as $index) {
            Employee::forceCreate([
                'sap' => "SAP-{$suffix}-{$index}",
                'name' => "Pegawai {$suffix} {$index}",
                'personal_number' => "PN-{$suffix}-{$index}",
                'position_id' => $position->id,
                'position' => $position->name,
            ]);
        }
    }

    private function successor(Position $position, string $name, string $readiness): Successor
    {
        return Successor::query()->create([
            'target_position_id' => $position->id,
            'name' => $name,
            'sap' => 'SAP-'.strtoupper(str_replace(' ', '-', $name)),
            'current_position' => 'Current '.$name,
            'succession_readiness' => $readiness,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function validData(Position $position, array $overrides = []): array
    {
        return array_merge([
            'position_id' => $position->id,
            'criticality' => 'high',
            'assessment_reason' => 'Posisi mempunyai dampak besar terhadap keberlangsungan organisasi.',
            'assessed_at' => '2026-11-01',
            'review_due_at' => '2027-05-01',
            'notes' => 'Catatan penilaian.',
        ], $overrides);
    }

    /** @param array<int, string> $permissionKeys */
    private function grantPermissions(UserRole $role, array $permissionKeys): void
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

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }
}
