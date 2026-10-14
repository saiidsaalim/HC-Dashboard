<?php

namespace Tests\Feature;

use App\Enums\PromotionApprovalStatus;
use App\Enums\PromotionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\Position;
use App\Models\PromotionApproval;
use App\Models\PromotionApprovalConfig;
use App\Models\Unit;
use App\Models\User;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class PromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_only_management_roles_can_view_promotions(): void
    {
        $this->get(route('promosi'))->assertRedirect(route('login'));

        foreach ([UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::MANAGER] as $role) {
            $this->actingAs($this->user($role))->get(route('promosi'))->assertOk();
        }

        foreach ([UserRole::STAFF, UserRole::MEMBER] as $role) {
            $this->actingAs($this->user($role))->get(route('promosi'))->assertForbidden();
        }
    }

    public function test_only_super_admin_can_manage_records_and_super_admin_cannot_approve(): void
    {
        $employee = $this->employee('SAP-MANAGE');
        $promotion = $this->promotion($employee);
        $payload = $this->payload($employee);

        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->post(route('promosi.store'), $payload)->assertForbidden();
            $this->actingAs($user)->put(route('promosi.update', $promotion), $payload)->assertForbidden();
            $this->actingAs($user)->delete(route('promosi.destroy-many'), ['promotion_ids' => [$promotion->id]])->assertForbidden();
        }

        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $this->actingAs($superAdmin)->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->assertDatabaseCount('promotion_approvals', 0);
    }

    public function test_super_admin_settings_require_exactly_four_distinct_admin_or_manager_users(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $admin = $this->user(UserRole::ADMIN, 'Admin Satu');
        $managerOne = $this->user(UserRole::MANAGER, 'Manager Satu');
        $managerTwo = $this->user(UserRole::MANAGER, 'Manager Dua');
        $managerThree = $this->user(UserRole::MANAGER, 'Manager Tiga');
        $managerFour = $this->user(UserRole::MANAGER, 'Manager Empat');
        $staff = $this->user(UserRole::STAFF);
        $member = $this->user(UserRole::MEMBER);

        $this->actingAs($superAdmin)->get(route('promosi.approval-settings.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Persetujuan Promosi')
            ->assertSee('Approver Tahap 4')
            ->assertSee($admin->name)
            ->assertDontSee($staff->name)
            ->assertDontSee($member->name);

        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $unauthorizedUser = $this->user($role);
            $this->actingAs($unauthorizedUser)->get(route('promosi.approval-settings.edit'))->assertForbidden();
            $this->actingAs($unauthorizedUser)->put(route('promosi.approval-settings.update'), [
                'approvers' => [
                    1 => $admin->id,
                    2 => $managerOne->id,
                    3 => $managerTwo->id,
                    4 => $managerThree->id,
                ],
            ])->assertForbidden();
        }

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [1 => $admin->id, 2 => $admin->id, 3 => $managerOne->id],
        ])->assertSessionHasErrors('approvers');

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [
                1 => $admin->id,
                2 => $managerOne->id,
                3 => $managerTwo->id,
                4 => $managerThree->id,
                5 => $managerFour->id,
            ],
        ])->assertSessionHasErrors('approvers');

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [1 => $admin->id, 2 => $admin->id, 3 => $managerTwo->id, 4 => $managerThree->id],
        ])->assertSessionHasErrors('approvers.2');

        foreach ([$staff, $member, $superAdmin] as $ineligibleApprover) {
            $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
                'approvers' => [
                    1 => $admin->id,
                    2 => $managerOne->id,
                    3 => $managerTwo->id,
                    4 => $ineligibleApprover->id,
                ],
            ])->assertSessionHasErrors('approvers.4');
        }

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [
                1 => $admin->id,
                2 => $managerOne->id,
                3 => $managerTwo->id,
                4 => $managerThree->id,
            ],
        ])->assertRedirect(route('promosi.approval-settings.edit'));

        $this->assertDatabaseHas('promotion_approval_configs', ['step_order' => 1, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('promotion_approval_configs', ['step_order' => 4, 'user_id' => $managerThree->id]);
        $this->assertDatabaseCount('promotion_approval_configs', PromotionApprovalWorkflowService::REQUIRED_STEPS);
    }

    public function test_linked_staff_promoted_to_manager_or_admin_are_selectable_and_snapshot_employee_sap(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $managerEmployee = $this->employee('SAP-LINKED-MANAGER', [
            'personal_number' => 'PN-LINKED-MANAGER',
            'email' => 'linked-manager@example.com',
        ]);
        $adminEmployee = $this->employee('SAP-LINKED-ADMIN', [
            'personal_number' => 'PN-LINKED-ADMIN',
            'email' => 'linked-admin@example.com',
        ]);
        $promotedManager = $this->user(UserRole::STAFF, 'Manager Terhubung', $managerEmployee);
        $promotedAdmin = $this->user(UserRole::STAFF, 'Admin Terhubung', $adminEmployee);
        $thirdApprover = $this->user(UserRole::MANAGER, 'Approver Ketiga');
        $fourthApprover = $this->user(UserRole::ADMIN, 'Approver Keempat');

        $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $promotedManager), [
            'role' => UserRole::MANAGER->value,
        ])->assertRedirect();
        $this->actingAs($superAdmin)->patch(route('konfigurasi-user.role', $promotedAdmin), [
            'role' => UserRole::ADMIN->value,
        ])->assertRedirect();

        $this->actingAs($superAdmin)->get(route('promosi.approval-settings.edit'))
            ->assertOk()
            ->assertSee('SAP-LINKED-MANAGER')
            ->assertSee('Manager Terhubung')
            ->assertSee('SAP-LINKED-ADMIN')
            ->assertSee('Admin Terhubung');

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [
                1 => $promotedManager->id,
                2 => $promotedAdmin->id,
                3 => $thirdApprover->id,
                4 => $fourthApprover->id,
            ],
        ])->assertRedirect(route('promosi.approval-settings.edit'));

        $promotion = $this->promotion($this->employee('SAP-PROMOTION-LINKED-APPROVER'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))->assertRedirect(route('promosi'));
        $this->actingAs($promotedManager->fresh())->post(route('promosi.approve', $promotion))
            ->assertRedirect(route('promosi'));
        $this->actingAs($promotedAdmin->fresh())->post(route('promosi.approve', $promotion))
            ->assertRedirect(route('promosi'));

        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'manager_id' => $promotedManager->id,
            'approval_round' => 1,
            'step_order' => 1,
            'approver_number_snapshot' => 'SAP-LINKED-MANAGER',
            'status' => PromotionApprovalStatus::Approved->value,
        ]);
        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'manager_id' => $promotedAdmin->id,
            'approval_round' => 1,
            'step_order' => 2,
            'approver_number_snapshot' => 'SAP-LINKED-ADMIN',
            'status' => PromotionApprovalStatus::Approved->value,
        ]);
    }

    public function test_employee_selection_uses_server_side_sap_and_saves_correctable_snapshot_only(): void
    {
        $department = Department::query()->create(['code' => 'CANON-DEPT', 'name' => 'Canonical Department', 'active' => true]);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'CANON-UNIT', 'name' => 'Canonical Unit']);
        $position = Position::query()->create(['unit_id' => $unit->id, 'code' => 'CANON-POS', 'name' => 'Canonical Position', 'active' => true]);
        $employee = $this->employee('SAP-CANON', [
            'personal_number' => 'PN-CANON',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Legacy Department',
            'position' => 'Legacy Position',
        ]);
        $employeeUser = $this->user(UserRole::STAFF, 'Nama Employee', $employee);
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        $this->actingAs($superAdmin)->get(route('promosi'))
            ->assertOk()
            ->assertSee('SAP-CANON — PN-CANON — Nama Employee — Canonical Position')
            ->assertSee('Canonical Department', escape: false);

        $payload = $this->payload($employee, [
            'sap' => 'SAP-DIMANIPULASI',
            'nama' => 'Nama Snapshot Dikoreksi',
            'departemen_lama' => 'Department Snapshot Dikoreksi',
            'jabatan_lama' => 'Jabatan Snapshot Dikoreksi',
            'verification_status' => PromotionStatus::Final->value,
            'finalized_at' => now()->toDateTimeString(),
        ]);

        $this->actingAs($superAdmin)->post(route('promosi.store'), $payload)
            ->assertRedirect(route('promosi'));

        $this->assertDatabaseHas('employee_promotions', [
            'sap' => 'SAP-CANON',
            'nama' => 'Nama Snapshot Dikoreksi',
            'departemen_lama' => 'Department Snapshot Dikoreksi',
            'verification_status' => PromotionStatus::Draft->value,
            'finalized_at' => null,
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'txt_dept' => 'Legacy Department',
            'position' => 'Legacy Position',
        ]);
        $this->assertSame($employee->id, $employeeUser->employee_id);
    }

    public function test_employee_picker_has_searchable_safe_labels_and_preserves_edit_selection(): void
    {
        $department = Department::query()->create([
            'code' => 'SEARCH-DEPT',
            'name' => 'Department Pencarian',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'department_id' => $department->id,
            'code' => 'SEARCH-UNIT',
            'name' => 'Unit Pencarian',
        ]);
        $position = Position::query()->create([
            'unit_id' => $unit->id,
            'code' => 'SEARCH-POS',
            'name' => 'Manager HC',
            'active' => true,
        ]);
        $canonicalEmployee = $this->employee('4809', [
            'personal_number' => '10002345',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Department Legacy Diabaikan',
            'position' => 'Posisi Legacy Diabaikan',
        ]);
        $this->user(UserRole::STAFF, 'FATMA HAERANI, S.AP.', $canonicalEmployee);
        $withoutPersonalNumber = $this->employee('SAP-TANPA-PN', [
            'txt_dept' => 'Department Legacy',
            'organizational_unit' => 'Unit Legacy',
            'position' => 'Legacy Analyst',
        ]);
        $duplicateNameEmployee = $this->employee('SAP-DUPLIKAT', [
            'personal_number' => 'NAMA-SAMA',
            'position' => 'Legacy Supervisor',
        ]);
        $this->user(UserRole::STAFF, 'NAMA-SAMA', $duplicateNameEmployee);
        $promotion = $this->promotion($canonicalEmployee);
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        $response = $this->actingAs($superAdmin)->get(route('promosi'));

        $response->assertOk()
            ->assertViewHas('employeeOptions', function ($employeeOptions) use (
                $canonicalEmployee,
                $withoutPersonalNumber,
                $duplicateNameEmployee,
            ): bool {
                $canonicalOption = $employeeOptions->firstWhere('id', $canonicalEmployee->id);
                $withoutPersonalNumberOption = $employeeOptions->firstWhere('id', $withoutPersonalNumber->id);
                $duplicateNameOption = $employeeOptions->firstWhere('id', $duplicateNameEmployee->id);

                return $employeeOptions->count() === 3
                    && $canonicalOption['sap'] === '4809'
                    && $canonicalOption['personal_number'] === '10002345'
                    && $canonicalOption['name'] === 'FATMA HAERANI, S.AP.'
                    && $canonicalOption['department'] === 'Department Pencarian'
                    && $canonicalOption['unit'] === 'Unit Pencarian'
                    && $canonicalOption['position'] === 'Manager HC'
                    && $canonicalOption['label'] === '4809 — 10002345 — FATMA HAERANI, S.AP. — Manager HC'
                    && $withoutPersonalNumberOption['label'] === 'SAP-TANPA-PN — Personal Number belum tersedia — Legacy Analyst'
                    && $duplicateNameOption['label'] === 'SAP-DUPLIKAT — NAMA-SAMA — Legacy Supervisor'
                    && $employeeOptions->every(
                        fn (array $option): bool => ! str_contains($option['label'], 'Employee #'),
                    );
            })
            ->assertSee('name="employee_id"', escape: false)
            ->assertSee('type="search"', escape: false)
            ->assertSee('Cari SAP, Personal Number, nama, department, unit, atau jabatan...')
            ->assertSee('x-model="employeeSearch"', escape: false)
            ->assertSee('x-for="employee in employeeSearchResults().options"', escape: false)
            ->assertSee('employee.sap', escape: false)
            ->assertSee('employee.personal_number', escape: false)
            ->assertSee('employee.name', escape: false)
            ->assertSee('employee.department', escape: false)
            ->assertSee('employee.unit', escape: false)
            ->assertSee('employee.position', escape: false)
            ->assertSee('return { options: this.employees, matchCount: this.employees.length };', escape: false)
            ->assertSee('options: [selectedEmployee, ...matches]', escape: false)
            ->assertSee('this.sap = employee.sap;', escape: false)
            ->assertSee('this.nama = employee.name;', escape: false)
            ->assertSee('this.departemen_lama = employee.department;', escape: false)
            ->assertSee('this.jabatan_lama = employee.position;', escape: false)
            ->assertSee('Employee tidak ditemukan')
            ->assertSee('Bersihkan')
            ->assertSee('@click="employeeSearch = \'\'"', escape: false)
            ->assertDontSee('this.employeeId = employee.id', escape: false)
            ->assertSee("employeeId: {$canonicalEmployee->id}", escape: false)
            ->assertDontSee("Employee #{$canonicalEmployee->id}");

        $this->actingAs($superAdmin)->from(route('promosi'))->post(route('promosi.store'), [
            ...$this->payload($canonicalEmployee),
            '_form' => 'create',
            'nama' => '',
        ])->assertRedirect(route('promosi'))
            ->assertSessionHasErrors('nama')
            ->assertSessionHasInput('employee_id', $canonicalEmployee->id);
        $this->get(route('promosi'))
            ->assertOk()
            ->assertSee("employeeId: {$canonicalEmployee->id}", escape: false);

        $this->actingAs($this->user(UserRole::ADMIN))->get(route('promosi'))
            ->assertOk()
            ->assertDontSee('create-employee-search');

        $this->assertSame($canonicalEmployee->sap, $promotion->sap);
    }

    public function test_employee_legacy_values_are_frontend_fallback_and_invalid_employee_is_rejected(): void
    {
        $employee = $this->employee('SAP-LEGACY', [
            'txt_dept' => 'Legacy Department',
            'position' => 'Legacy Position',
            'employee_subgroup' => 'JANGAN-JADI-PG',
        ]);
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);

        $this->actingAs($superAdmin)->get(route('promosi'))
            ->assertOk()
            ->assertSee('Legacy Department', escape: false)
            ->assertSee('Legacy Position')
            ->assertDontSee('JANGAN-JADI-PG');

        $this->actingAs($superAdmin)->post(route('promosi.store'), [
            ...$this->payload($employee),
            'employee_id' => 999999,
        ])->assertSessionHasErrors('employee_id')
            ->assertSessionHasInput('employee_id', 999999)
            ->assertSessionHasInput('nama');

        $this->assertDatabaseCount('employee_promotions', 0);
    }

    public function test_submit_requires_complete_configuration_and_starts_at_first_step(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $promotion = $this->promotion($this->employee('SAP-SUBMIT'));

        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))
            ->assertSessionHasErrors('approval_config');

        $approvers = $this->configureApprovers();
        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))
            ->assertRedirect(route('promosi'));

        $promotion->refresh();
        $this->assertSame(PromotionStatus::WaitingApproval->value, $promotion->verification_status);
        $this->assertSame(1, $promotion->approval_round);
        $this->assertSame(1, $promotion->current_approval_step);
        $this->assertSame($superAdmin->id, $promotion->submitted_by);
        $this->assertNotNull($promotion->submitted_at);
        $this->assertSame($approvers[0]->id, PromotionApprovalConfig::query()->where('step_order', 1)->value('user_id'));
    }

    public function test_legacy_three_approver_configuration_remains_visible_but_blocks_submission(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $this->configureApprovers();
        PromotionApprovalConfig::query()->where('step_order', PromotionApprovalWorkflowService::REQUIRED_STEPS)->delete();
        $promotion = $this->promotion($this->employee('SAP-LEGACY-CONFIG'));

        $this->actingAs($superAdmin)->get(route('promosi.approval-settings.edit'))
            ->assertOk()
            ->assertSee('Approver Tahap 4')
            ->assertViewHas(
                'configuredApprovers',
                fn ($configuredApprovers): bool => $configuredApprovers->count() === 3
                    && ! $configuredApprovers->has(PromotionApprovalWorkflowService::REQUIRED_STEPS),
            );

        $this->actingAs($superAdmin)->get(route('promosi'))
            ->assertOk()
            ->assertSee('Konfigurasi empat approver Promosi belum lengkap.');

        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))
            ->assertSessionHasErrors('approval_config');
        $this->assertSame(PromotionStatus::Draft->value, $promotion->fresh()->verification_status);
        $this->assertDatabaseCount('promotion_approval_configs', 3);
    }

    public function test_active_three_step_workflow_waits_for_complete_configuration_then_continues_to_step_four(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $promotion = $this->promotion($this->employee('SAP-ACTIVE-LEGACY'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));
        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion));
        $this->actingAs($approvers[1])->post(route('promosi.approve', $promotion));
        PromotionApprovalConfig::query()->where('step_order', PromotionApprovalWorkflowService::REQUIRED_STEPS)->delete();

        $this->actingAs($approvers[2])->post(route('promosi.approve', $promotion))
            ->assertSessionHasErrors('approval_config');
        $this->assertSame(3, $promotion->fresh()->current_approval_step);
        $this->assertDatabaseCount('promotion_approvals', 2);

        PromotionApprovalConfig::query()->create([
            'step_order' => PromotionApprovalWorkflowService::REQUIRED_STEPS,
            'user_id' => $approvers[3]->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($approvers[2])->post(route('promosi.approve', $promotion))->assertRedirect(route('promosi'));
        $this->assertSame(4, $promotion->fresh()->current_approval_step);
        $this->assertSame(PromotionStatus::WaitingApproval->value, $promotion->fresh()->verification_status);
        $this->actingAs($approvers[3])->post(route('promosi.approve', $promotion))->assertRedirect(route('promosi'));
        $this->assertSame(PromotionStatus::Final->value, $promotion->fresh()->verification_status);
    }

    public function test_approvals_are_sequential_idempotent_and_store_identity_snapshots(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers(withEmployees: true);
        $promotion = $this->promotion($this->employee('SAP-WORKFLOW'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));

        $this->actingAs($approvers[1])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($approvers[2])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($approvers[3])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($this->user(UserRole::MANAGER, 'Manager Bukan Approver'))
            ->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($this->user(UserRole::STAFF))
            ->post(route('promosi.approve', $promotion))->assertForbidden();

        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion))->assertRedirect(route('promosi'));
        $this->assertSame(2, $promotion->fresh()->current_approval_step);
        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion))->assertRedirect(route('promosi'));
        $this->assertDatabaseCount('promotion_approvals', 1);

        $this->actingAs($approvers[1])->post(route('promosi.approve', $promotion));
        $this->assertSame(3, $promotion->fresh()->current_approval_step);
        $this->actingAs($approvers[2])->post(route('promosi.approve', $promotion));
        $promotion->refresh();
        $this->assertSame(PromotionStatus::WaitingApproval->value, $promotion->verification_status);
        $this->assertSame(4, $promotion->current_approval_step);
        $this->assertNull($promotion->finalized_at);
        $this->assertDatabaseCount('promotion_approvals', 3);

        $this->actingAs($approvers[3])->post(route('promosi.approve', $promotion));

        $promotion->refresh();
        $this->assertSame(PromotionStatus::Final->value, $promotion->verification_status);
        $this->assertNull($promotion->current_approval_step);
        $this->assertNotNull($promotion->finalized_at);
        $this->assertDatabaseCount('promotion_approvals', PromotionApprovalWorkflowService::REQUIRED_STEPS);

        foreach ($approvers as $index => $approver) {
            $this->assertDatabaseHas('promotion_approvals', [
                'employee_promotion_id' => $promotion->id,
                'approval_round' => 1,
                'step_order' => $index + 1,
                'manager_id' => $approver->id,
                'approver_name_snapshot' => $approver->name,
                'approver_role_snapshot' => $approver->role,
                'approver_number_snapshot' => $approver->employee->sap,
                'status' => PromotionApprovalStatus::Approved->value,
            ]);
        }

        $timelineResponse = $this->actingAs($superAdmin)->get(route('promosi'));
        $timelineResponse->assertOk()->assertSee('Tahap 4');
        foreach ($approvers as $approver) {
            $timelineResponse->assertSee($approver->name);
        }

        $originalFirstApproverName = $approvers[0]->name;
        $approvers[0]->update(['name' => 'Nama Baru Setelah Final']);
        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'step_order' => 1,
            'approver_name_snapshot' => $originalFirstApproverName,
        ]);
        $approvers[0]->delete();
        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'step_order' => 1,
            'manager_id' => null,
            'approver_name_snapshot' => $originalFirstApproverName,
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_decision_for_same_round_and_step(): void
    {
        $promotion = $this->promotion($this->employee('SAP-UNIQUE-DECISION'));
        $approver = $this->user(UserRole::MANAGER);
        $decision = [
            'employee_promotion_id' => $promotion->id,
            'approval_round' => 1,
            'step_order' => 1,
            'manager_id' => $approver->id,
            'status' => PromotionApprovalStatus::Approved->value,
            'approved_at' => now(),
        ];
        PromotionApproval::query()->create($decision);

        $this->expectException(QueryException::class);
        PromotionApproval::query()->create($decision);
    }

    #[DataProvider('workflowSteps')]
    public function test_active_approver_at_each_step_can_send_back_with_required_note(int $step): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $promotion = $this->promotion($this->employee("SAP-RETURN-{$step}"));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));

        for ($approvedStep = 1; $approvedStep < $step; $approvedStep++) {
            $this->actingAs($approvers[$approvedStep - 1])->post(route('promosi.approve', $promotion));
        }

        $inactive = $approvers[$step % count($approvers)];
        $this->actingAs($inactive)->post(route('promosi.send-back', $promotion), ['return_note' => 'Tidak boleh'])
            ->assertForbidden();
        $this->actingAs($approvers[$step - 1])->post(route('promosi.send-back', $promotion), [])
            ->assertSessionHasErrors('return_note');
        $this->actingAs($approvers[$step - 1])->post(route('promosi.send-back', $promotion), [
            'return_note' => "Perbaiki data pada tahap {$step}.",
        ])->assertRedirect(route('promosi'));
        $this->actingAs($approvers[$step - 1])->post(route('promosi.send-back', $promotion), [
            'return_note' => "Perbaiki data pada tahap {$step}.",
        ])->assertRedirect(route('promosi'));

        $promotion->refresh();
        $this->assertSame(PromotionStatus::Returned->value, $promotion->verification_status);
        $this->assertNull($promotion->current_approval_step);
        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'approval_round' => 1,
            'step_order' => $step,
            'status' => PromotionApprovalStatus::Returned->value,
            'note' => "Perbaiki data pada tahap {$step}.",
        ]);
        $this->assertSame(1, $promotion->approvals()->where('status', PromotionApprovalStatus::Returned->value)->count());
    }

    /** @return array<string, array{int}> */
    public static function workflowSteps(): array
    {
        $steps = [];

        foreach (range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS) as $step) {
            $steps["step {$step}"] = [$step];
        }

        return $steps;
    }

    public function test_returned_promotion_can_be_edited_and_resubmitted_without_losing_old_round(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $employee = $this->employee('SAP-RESUBMIT');
        $promotion = $this->promotion($employee);
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));
        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion));
        $this->actingAs($approvers[1])->post(route('promosi.send-back', $promotion), ['return_note' => 'Perbaiki jabatan.']);

        $this->actingAs($superAdmin)->put(route('promosi.update', $promotion), $this->payload($employee, [
            'jabatan_baru' => 'General Manager',
        ]))->assertRedirect(route('promosi'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))->assertRedirect(route('promosi'));

        $promotion->refresh();
        $this->assertSame(2, $promotion->approval_round);
        $this->assertSame(1, $promotion->current_approval_step);
        $this->assertSame('General Manager', $promotion->jabatan_baru);
        $this->assertSame(2, $promotion->approvals()->where('approval_round', 1)->count());
        $this->assertSame(0, $promotion->approvals()->where('approval_round', 2)->count());
    }

    public function test_waiting_and_final_promotions_are_immutable_and_final_cannot_be_sent_back(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $employee = $this->employee('SAP-IMMUTABLE');
        $promotion = $this->promotion($employee);
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));

        $this->actingAs($superAdmin)->put(route('promosi.update', $promotion), $this->payload($employee))->assertForbidden();
        $this->actingAs($superAdmin)->delete(route('promosi.destroy-many'), ['promotion_ids' => [$promotion->id]])
            ->assertSessionHasErrors('status');

        foreach ($approvers as $approver) {
            $this->actingAs($approver)->post(route('promosi.approve', $promotion));
        }

        $this->actingAs($superAdmin)->put(route('promosi.update', $promotion), $this->payload($employee))->assertForbidden();
        $this->actingAs($approvers[3])->post(route('promosi.send-back', $promotion), ['return_note' => 'Terlambat'])
            ->assertForbidden();
    }

    public function test_changing_active_approver_applies_immediately_without_changing_completed_approval(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $replacement = $this->user(UserRole::MANAGER, 'Pengganti Tahap Dua');
        $promotion = $this->promotion($this->employee('SAP-CONFIG-CHANGE'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));
        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion));

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [
                1 => $approvers[0]->id,
                2 => $replacement->id,
                3 => $approvers[2]->id,
                4 => $approvers[3]->id,
            ],
        ]);

        $this->actingAs($approvers[1])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($replacement)->post(route('promosi.approve', $promotion))->assertRedirect(route('promosi'));
        $this->assertDatabaseHas('promotion_approvals', [
            'employee_promotion_id' => $promotion->id,
            'step_order' => 1,
            'manager_id' => $approvers[0]->id,
            'approver_name_snapshot' => $approvers[0]->name,
        ]);
        $this->assertSame(3, $promotion->fresh()->current_approval_step);
    }

    public function test_invalid_active_configuration_blocks_workflow_safely(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $promotion = $this->promotion($this->employee('SAP-INVALID-CONFIG'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));
        $approvers[0]->update(['role' => UserRole::STAFF->value]);

        $this->actingAs($approvers[0])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->assertSame(1, $promotion->fresh()->current_approval_step);
        $this->assertDatabaseCount('promotion_approvals', 0);
    }

    public function test_deleted_configured_user_removes_only_configuration_and_blocks_submission(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers();
        $approvers[1]->delete();
        $promotion = $this->promotion($this->employee('SAP-DELETED-CONFIG'));

        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))
            ->assertSessionHasErrors('approval_config');
        $this->assertDatabaseCount('promotion_approval_configs', 3);
        $this->assertSame(PromotionStatus::Draft->value, $promotion->fresh()->verification_status);
    }

    public function test_import_is_super_admin_only_atomic_requires_existing_employee_and_always_creates_draft(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $admin = $this->user(UserRole::ADMIN);
        $this->employee('SAP-IMPORT');
        $header = 'SAP,Nama,Departemen Lama,Jabatan Lama,Departemen Baru,Jabatan Baru,TMT,PG,Band Lama,JG Lama,Band Baru,JG Baru,Status';
        $validRow = 'SAP-IMPORT,Budi,Operasional,Staff,Keuangan,Supervisor,2026-10-01,PG-1,B1,JG-1,B2,JG-2,Final';
        $invalidRow = 'SAP-TIDAK-ADA,Siti,Operasional,Staff,Keuangan,Supervisor,2026-10-01,PG-1,B1,JG-1,B2,JG-2,Final';

        $adminFile = UploadedFile::fake()->createWithContent('promosi.csv', "{$header}\n{$validRow}\n");
        $this->actingAs($admin)->post(route('promosi.import'), ['file' => $adminFile])->assertForbidden();

        $invalidFile = UploadedFile::fake()->createWithContent('promosi.csv', "{$header}\n{$validRow}\n{$invalidRow}\n");
        $this->actingAs($superAdmin)->post(route('promosi.import'), ['file' => $invalidFile])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employee_promotions', 0);

        $validFile = UploadedFile::fake()->createWithContent('promosi.csv', "{$header}\n{$validRow}\n");
        $this->actingAs($superAdmin)->post(route('promosi.import'), ['file' => $validFile])
            ->assertRedirect(route('promosi'));
        $this->assertDatabaseHas('employee_promotions', [
            'sap' => 'SAP-IMPORT',
            'verification_status' => PromotionStatus::Draft->value,
            'approval_round' => 0,
        ]);
    }

    public function test_list_is_paginated_search_and_filters_persist_and_detail_shows_approver_timeline(): void
    {
        $admin = $this->user(UserRole::ADMIN);
        foreach (range(1, 16) as $number) {
            $this->promotion($this->employee('SAP-PAGE-'.$number));
        }

        $response = $this->actingAs($admin)->get(route('promosi', ['search' => 'SAP-PAGE', 'status' => 'draft']));
        $response->assertOk()
            ->assertViewHas('promotions', fn ($promotions): bool => $promotions->perPage() === 15 && $promotions->total() === 16)
            ->assertSee('page=2', escape: false)
            ->assertSee('search=SAP-PAGE', escape: false)
            ->assertSee('status=draft', escape: false)
            ->assertSee('Data Pegawai')
            ->assertSee('Posisi Saat Ini')
            ->assertSee('Usulan Promosi')
            ->assertSee('Timeline Persetujuan')
            ->assertDontSee('Import Promosi')
            ->assertDontSee('Buat Promosi');
    }

    public function test_legacy_pending_can_start_new_round_without_counting_legacy_approvals(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $legacyApprover = $this->user(UserRole::MANAGER, 'Approver Lama');
        $this->configureApprovers();
        $promotion = $this->promotion($this->employee('SAP-LEGACY-WORKFLOW'), PromotionStatus::LegacyPending);
        PromotionApproval::query()->create([
            'employee_promotion_id' => $promotion->id,
            'manager_id' => $legacyApprover->id,
            'status' => PromotionApprovalStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion))->assertRedirect(route('promosi'));
        $promotion->refresh();
        $this->assertSame(1, $promotion->approval_round);
        $this->assertSame(1, $promotion->current_approval_step);
        $this->assertSame(0, $promotion->approvals()->where('approval_round', 1)->count());
        $this->assertSame(1, $promotion->approvals()->whereNull('approval_round')->count());
    }

    public function test_only_super_admin_can_print_new_final_with_four_approvers_and_safe_document(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $employee = $this->employee('SAP/PRINT');
        $pending = $this->promotion($employee);
        $this->actingAs($superAdmin)->get(route('promosi.print', $pending))->assertForbidden();

        $final = $this->promotion($employee, PromotionStatus::Final, [
            'nama' => 'Nama / Tidak Aman',
            'approval_round' => 1,
            'finalized_at' => now(),
        ]);
        foreach (range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS) as $step) {
            PromotionApproval::query()->create([
                'employee_promotion_id' => $final->id,
                'approval_round' => 1,
                'step_order' => $step,
                'manager_id' => $this->user(UserRole::MANAGER, "Approver Cetak {$step}")->id,
                'status' => PromotionApprovalStatus::Approved->value,
                'approved_at' => now(),
                'approver_name_snapshot' => "Approver Cetak {$step}",
                'approver_role_snapshot' => UserRole::MANAGER->value,
                'approver_number_snapshot' => "NOMOR-{$step}",
            ]);
        }
        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $this->actingAs($this->user($role))->get(route('promosi.print', $final))->assertForbidden();
        }

        $response = $this->actingAs($superAdmin)->get(route('promosi.print', $final));
        $response->assertOk()->assertDownload('promosi-sapprint-nama-tidak-aman-2026-12-01.docx');
        $xml = $this->documentXml($response);
        $this->assertStringContainsString('Nama / Tidak Aman', $xml);
        $this->assertStringContainsString('SAP/PRINT', $xml);
        $this->assertStringContainsString('Operasional', $xml);
        $this->assertStringContainsString('Keuangan', $xml);
        $this->assertStringContainsString('Supervisor', $xml);
        $this->assertStringContainsString('PG-1', $xml);
        $this->assertStringContainsString('BAND Saat Ini: B1', $xml);
        $this->assertStringContainsString('JG Usulan: JG-2', $xml);
        $this->assertStringContainsString('Approver Cetak 1', $xml);
        $this->assertStringContainsString('Approver Cetak 4', $xml);
        $this->assertStringContainsString('NOMOR-4', $xml);
        $this->assertStringContainsString('Status: Final', $xml);
        $this->assertDoesNotMatchRegularExpression('/\[[A-Z][A-Z0-9 _-]*\]/', $xml);
    }

    public function test_legacy_final_with_three_approvals_remains_final_and_prints_only_stored_snapshots(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $final = $this->promotion($this->employee('SAP-LEGACY-FINAL'), PromotionStatus::Final, [
            'approval_round' => 1,
            'finalized_at' => now(),
        ]);

        foreach (range(1, 3) as $step) {
            PromotionApproval::query()->create([
                'employee_promotion_id' => $final->id,
                'approval_round' => 1,
                'step_order' => $step,
                'manager_id' => $this->user(UserRole::MANAGER, "Approver Lama {$step}")->id,
                'status' => PromotionApprovalStatus::Approved->value,
                'approved_at' => now(),
                'approver_name_snapshot' => "Approver Lama {$step}",
                'approver_role_snapshot' => UserRole::MANAGER->value,
                'approver_number_snapshot' => "NOMOR-LAMA-{$step}",
            ]);
        }

        $response = $this->actingAs($superAdmin)->get(route('promosi.print', $final));
        $response->assertOk();
        $xml = $this->documentXml($response);

        $this->assertSame(PromotionStatus::Final->value, $final->fresh()->verification_status);
        $this->assertStringContainsString('Approver Lama 1', $xml);
        $this->assertStringContainsString('Approver Lama 3', $xml);
        $this->assertStringNotContainsString('Tahap 4:', $xml);
        $this->assertDoesNotMatchRegularExpression('/\[[A-Z][A-Z0-9 _-]*\]/', $xml);

        $this->actingAs($superAdmin)->get(route('promosi'))
            ->assertOk()
            ->assertSee('3/3 disetujui')
            ->assertDontSee('Tahap 4');
    }

    /** @return array<int, User> */
    private function configureApprovers(bool $withEmployees = false): array
    {
        $approvers = [];

        foreach (range(1, PromotionApprovalWorkflowService::REQUIRED_STEPS) as $step) {
            $role = $step % 2 === 0 ? UserRole::ADMIN : UserRole::MANAGER;
            $employee = $withEmployees ? $this->employee('APPROVER-'.$step) : null;
            $approver = $this->user($role, 'Approver '.$step, $employee);
            PromotionApprovalConfig::query()->create([
                'step_order' => $step,
                'user_id' => $approver->id,
            ]);
            $approvers[] = $approver;
        }

        return $approvers;
    }

    private function documentXml(TestResponse $response): string
    {
        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);
        $path = $baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        if (file_exists($path)) {
            unlink($path);
        }

        $this->assertIsString($xml);

        return $xml;
    }

    private function user(UserRole $role, ?string $name = null, ?Employee $employee = null): User
    {
        return User::factory()->create([
            'role' => $role->value,
            'name' => $name ?? fake()->name(),
            'employee_id' => $employee?->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function employee(string $sap, array $attributes = []): Employee
    {
        return Employee::query()->create(['sap' => $sap, ...$attributes]);
    }

    /** @param array<string, mixed> $overrides */
    private function promotion(
        Employee $employee,
        PromotionStatus $status = PromotionStatus::Draft,
        array $overrides = [],
    ): EmployeePromotion {
        return EmployeePromotion::query()->create([
            ...$this->snapshot($employee->sap),
            'verification_status' => $status->value,
            'approval_round' => 0,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(Employee $employee, array $overrides = []): array
    {
        return ['employee_id' => $employee->id, ...$this->snapshot($employee->sap), ...$overrides];
    }

    /** @return array<string, string> */
    private function snapshot(string $sap): array
    {
        return [
            'sap' => $sap,
            'nama' => 'Pegawai '.$sap,
            'departemen_lama' => 'Operasional',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Keuangan',
            'jabatan_baru' => 'Supervisor',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'band_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'band_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ];
    }
}
