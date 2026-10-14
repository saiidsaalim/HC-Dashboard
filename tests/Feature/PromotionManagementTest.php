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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_super_admin_settings_require_three_distinct_admin_or_manager_users(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $admin = $this->user(UserRole::ADMIN, 'Admin Satu');
        $managerOne = $this->user(UserRole::MANAGER, 'Manager Satu');
        $managerTwo = $this->user(UserRole::MANAGER, 'Manager Dua');
        $staff = $this->user(UserRole::STAFF);

        $this->actingAs($superAdmin)->get(route('promosi.approval-settings.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Persetujuan Promosi')
            ->assertSee($admin->name)
            ->assertDontSee($staff->name);

        foreach ([UserRole::ADMIN, UserRole::MANAGER, UserRole::STAFF, UserRole::MEMBER] as $role) {
            $unauthorizedUser = $this->user($role);
            $this->actingAs($unauthorizedUser)->get(route('promosi.approval-settings.edit'))->assertForbidden();
            $this->actingAs($unauthorizedUser)->put(route('promosi.approval-settings.update'), [
                'approvers' => [1 => $admin->id, 2 => $managerOne->id, 3 => $managerTwo->id],
            ])->assertForbidden();
        }

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [1 => $admin->id, 2 => $admin->id, 3 => $managerOne->id],
        ])->assertSessionHasErrors('approvers.2');

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [1 => $admin->id, 2 => $managerOne->id, 3 => $staff->id],
        ])->assertSessionHasErrors('approvers.3');

        $this->actingAs($superAdmin)->put(route('promosi.approval-settings.update'), [
            'approvers' => [1 => $admin->id, 2 => $managerOne->id, 3 => $managerTwo->id],
        ])->assertRedirect(route('promosi.approval-settings.edit'));

        $this->assertDatabaseHas('promotion_approval_configs', ['step_order' => 1, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('promotion_approval_configs', ['step_order' => 3, 'user_id' => $managerTwo->id]);
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
            ->assertSee('SAP-CANON — Nama Employee — Canonical Position')
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
        ])->assertSessionHasErrors('employee_id')->assertSessionHasInput('nama');

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

    public function test_approvals_are_sequential_idempotent_and_store_identity_snapshots(): void
    {
        $superAdmin = $this->user(UserRole::SUPER_ADMIN);
        $approvers = $this->configureApprovers(withEmployees: true);
        $promotion = $this->promotion($this->employee('SAP-WORKFLOW'));
        $this->actingAs($superAdmin)->post(route('promosi.submit', $promotion));

        $this->actingAs($approvers[1])->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->actingAs($approvers[2])->post(route('promosi.approve', $promotion))->assertForbidden();
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
        $this->assertSame(PromotionStatus::Final->value, $promotion->verification_status);
        $this->assertNull($promotion->current_approval_step);
        $this->assertNotNull($promotion->finalized_at);
        $this->assertDatabaseCount('promotion_approvals', 3);

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

        $inactive = $approvers[$step % 3];
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
        return ['step one' => [1], 'step two' => [2], 'step three' => [3]];
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
        $this->actingAs($approvers[2])->post(route('promosi.send-back', $promotion), ['return_note' => 'Terlambat'])
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
            'approvers' => [1 => $approvers[0]->id, 2 => $replacement->id, 3 => $approvers[2]->id],
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
        $this->assertDatabaseCount('promotion_approval_configs', 2);
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

    public function test_only_super_admin_can_print_final_and_generated_docx_has_safe_name_and_no_supported_placeholders(): void
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
        foreach ([1, 2, 3] as $step) {
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
        $this->assertStringContainsString('Nama / Tidak Aman', $xml);
        $this->assertStringContainsString('SAP/PRINT', $xml);
        $this->assertStringContainsString('Operasional', $xml);
        $this->assertStringContainsString('Keuangan', $xml);
        $this->assertStringContainsString('Supervisor', $xml);
        $this->assertStringContainsString('PG-1', $xml);
        $this->assertStringContainsString('BAND Saat Ini: B1', $xml);
        $this->assertStringContainsString('JG Usulan: JG-2', $xml);
        $this->assertStringContainsString('Approver Cetak 1', $xml);
        $this->assertStringContainsString('NOMOR-3', $xml);
        $this->assertStringContainsString('Status: Final', $xml);
        $this->assertDoesNotMatchRegularExpression('/\[[A-Z][A-Z0-9 _-]*\]/', $xml);
    }

    /** @return array<int, User> */
    private function configureApprovers(bool $withEmployees = false): array
    {
        $roles = [UserRole::MANAGER, UserRole::ADMIN, UserRole::MANAGER];
        $approvers = [];

        foreach ($roles as $index => $role) {
            $employee = $withEmployees ? $this->employee('APPROVER-'.($index + 1)) : null;
            $approver = $this->user($role, 'Approver '.($index + 1), $employee);
            PromotionApprovalConfig::query()->create([
                'step_order' => $index + 1,
                'user_id' => $approver->id,
            ]);
            $approvers[] = $approver;
        }

        return $approvers;
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
