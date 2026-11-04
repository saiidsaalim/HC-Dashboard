<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DemotionApprovalConfig;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeePromotion;
use App\Models\Position;
use App\Models\PromotionApprovalConfig;
use App\Models\Unit;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeMovementFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demotion_updates_employee_only_on_third_distinct_approval_and_only_once(): void
    {
        [$employee, $movement] = $this->demotion();
        $untouchedEmployee = Employee::query()->create(['sap' => 'UNTOUCHED']);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        $this->configureDemotionApprovers($approvers);
        $this->submitDemotion($movement);
        $approvalService = app(ApprovalService::class);

        $approvalService->approve($movement, $approvers[0]);
        $approvalService->approve($movement, $approvers[1]);

        $this->assertSame($movement->old_position_id, $employee->fresh()->position_id);
        $this->assertSame('B1', $employee->fresh()->employee_subgroup);
        $this->assertNull($movement->fresh()->employee_applied_at);

        $approvalService->approve($movement, $approvers[2]);
        $appliedAt = $movement->fresh()->employee_applied_at;
        $employee->refresh();

        $this->assertSame('Final', $movement->fresh()->verification_status);
        $this->assertNotNull($appliedAt);
        $this->assertSame($movement->new_position_id, $employee->position_id);
        $this->assertSame('Directorate Baru', $employee->txt_dir);
        $this->assertSame('Department Baru', $employee->department);
        $this->assertSame('Unit Baru', $employee->organizational_unit);
        $this->assertSame('Position Baru', $employee->position);
        $this->assertSame('B2', $employee->employee_subgroup);
        $this->assertNull($untouchedEmployee->fresh()->position_id);

        $this->assertTrue($approvalService->approve($movement->fresh(), $approvers[2]));
        $this->assertTrue($appliedAt->equalTo($movement->fresh()->employee_applied_at));
        $this->assertDatabaseCount('demotion_approvals', 3);
        $this->assertSame('B2', $employee->fresh()->employee_subgroup);
    }

    public function test_stale_employee_level_rolls_back_last_approval_and_finalization(): void
    {
        [$employee, $movement] = $this->demotion();
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        $this->configureDemotionApprovers($approvers);
        $this->submitDemotion($movement);
        $approvalService = app(ApprovalService::class);
        $approvalService->approve($movement, $approvers[0]);
        $approvalService->approve($movement, $approvers[1]);
        $employee->update(['employee_subgroup' => 'LEVEL-TERBARU']);

        try {
            $approvalService->approve($movement, $approvers[2]);
            $this->fail('Finalisasi dengan Level stale seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Level Data Pegawai sudah berubah', $exception->getMessage());
        }

        $this->assertSame(2, $movement->approvals()->where('status', 'Approved')->count());
        $this->assertDatabaseCount('demotion_approvals', 3);
        $this->assertSame('Pending', $movement->fresh()->verification_status);
        $this->assertNull($movement->fresh()->finalized_at);
        $this->assertNull($movement->fresh()->employee_applied_at);
        $this->assertSame('LEVEL-TERBARU', $employee->fresh()->employee_subgroup);
    }

    public function test_stale_employee_rolls_back_last_approval_and_final_status(): void
    {
        [$employee, $movement, $target] = $this->demotion();
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        $this->configureDemotionApprovers($approvers);
        $this->submitDemotion($movement);
        $approvalService = app(ApprovalService::class);
        $approvalService->approve($movement, $approvers[0]);
        $approvalService->approve($movement, $approvers[1]);
        $employee->update(['position_id' => $target['position']->id]);

        try {
            $approvalService->approve($movement, $approvers[2]);
            $this->fail('Finalisasi stale seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('sudah berubah', $exception->getMessage());
        }

        $this->assertSame(2, $movement->approvals()->where('status', 'Approved')->count());
        $this->assertDatabaseCount('demotion_approvals', 3);
        $this->assertSame('Pending', $movement->fresh()->verification_status);
        $this->assertNull($movement->fresh()->finalized_at);
        $this->assertNull($movement->fresh()->employee_applied_at);
    }

    public function test_invalidated_target_rolls_back_last_approval(): void
    {
        [$employee, $movement, $target] = $this->demotion();
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        $this->configureDemotionApprovers($approvers);
        $this->submitDemotion($movement);
        $approvalService = app(ApprovalService::class);
        $approvalService->approve($movement, $approvers[0]);
        $approvalService->approve($movement, $approvers[1]);
        $target['position']->update(['active' => false]);

        $this->expectException(ValidationException::class);
        try {
            $approvalService->approve($movement, $approvers[2]);
        } finally {
            $this->assertSame(2, $movement->approvals()->where('status', 'Approved')->count());
            $this->assertDatabaseCount('demotion_approvals', 3);
            $this->assertSame($movement->old_position_id, $employee->fresh()->position_id);
            $this->assertSame('Pending', $movement->fresh()->verification_status);
        }
    }

    public function test_promotion_applies_employee_in_the_fourth_approval_transaction(): void
    {
        [$employee, $demotion] = $this->demotion();
        $promotion = EmployeePromotion::query()->create([
            ...$demotion->only([
                'sap', 'nama', 'direktorat_lama', 'direktorat_baru', 'departemen_lama', 'departemen_baru',
                'unit_lama', 'unit_baru', 'section_lama', 'section_baru', 'jabatan_lama', 'jabatan_baru',
                'old_directorate_id', 'new_directorate_id', 'old_department_id', 'new_department_id',
                'old_unit_id', 'new_unit_id', 'old_section_id', 'new_section_id', 'old_position_id', 'new_position_id',
                'tmt', 'pg', 'level_lama', 'jg_lama', 'level_baru', 'jg_baru',
            ]),
            'verification_status' => 'Draft',
        ]);
        $approvers = User::factory()->count(4)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $step => $approver) {
            PromotionApprovalConfig::query()->create(['step_order' => $step + 1, 'user_id' => $approver->id]);
        }
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $workflow = app(PromotionApprovalWorkflowService::class);
        $workflow->submit($promotion, $submitter);

        foreach ($approvers->take(3) as $approver) {
            $workflow->approve($promotion, $approver);
        }
        $this->assertSame($promotion->old_position_id, $employee->fresh()->position_id);

        $workflow->approve($promotion, $approvers[3]);
        $this->assertSame($promotion->new_position_id, $employee->fresh()->position_id);
        $this->assertSame('Final', $promotion->fresh()->verification_status);
        $this->assertNotNull($promotion->fresh()->employee_applied_at);
    }

    /**
     * @return array{Employee, EmployeeDemotion, array{directorate: Directorate, department: Department, unit: Unit, position: Position}}
     */
    private function demotion(): array
    {
        $oldDirectorate = Directorate::query()->create(['code' => 'OLD-DIR', 'name' => 'Directorate Lama']);
        $oldDepartment = Department::query()->create(['directorate_id' => $oldDirectorate->id, 'code' => 'OLD-DEP', 'name' => 'Department Lama']);
        $oldUnit = Unit::query()->create(['department_id' => $oldDepartment->id, 'code' => 'OLD-UNIT', 'name' => 'Unit Lama']);
        $oldPosition = Position::query()->create(['unit_id' => $oldUnit->id, 'code' => 'OLD-POS', 'name' => 'Position Lama']);
        $employee = Employee::query()->create([
            'sap' => 'EMPLOYEE', 'directorate_id' => $oldDirectorate->id, 'department_id' => $oldDepartment->id,
            'unit_id' => $oldUnit->id, 'position_id' => $oldPosition->id, 'employee_subgroup' => 'B1',
        ]);
        $employee->forceFill(['name' => 'Pegawai'])->save();

        $directorate = Directorate::query()->create(['code' => 'NEW-DIR', 'name' => 'Directorate Baru']);
        $department = Department::query()->create(['directorate_id' => $directorate->id, 'code' => 'NEW-DEP', 'name' => 'Department Baru']);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'NEW-UNIT', 'name' => 'Unit Baru']);
        $position = Position::query()->create(['unit_id' => $unit->id, 'code' => 'NEW-POS', 'name' => 'Position Baru']);
        $target = compact('directorate', 'department', 'unit', 'position');
        $snapshot = app(PersonnelMovementSnapshotService::class)->fromEmployee($employee, [
            'new_directorate_id' => $directorate->id,
            'new_department_id' => $department->id,
            'new_unit_id' => $unit->id,
            'new_section_id' => null,
            'new_position_id' => $position->id,
            'tmt' => '2026-11-02', 'pg' => 'PG', 'level_lama' => 'B1', 'jg_lama' => 'J1',
            'level_baru' => 'B2', 'jg_baru' => 'J2',
        ]);
        $movement = EmployeeDemotion::query()->create($snapshot);

        return [$employee, $movement, $target];
    }

    /** @param Collection<int, User> $approvers */
    private function configureDemotionApprovers(Collection $approvers): void
    {
        foreach ($approvers as $step => $approver) {
            DemotionApprovalConfig::query()->create([
                'step_order' => $step + 1,
                'user_id' => $approver->id,
            ]);
        }
    }

    private function submitDemotion(EmployeeDemotion $movement): void
    {
        app(MovementApprovalWorkflowService::class)->submit(
            $movement,
            User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]),
        );
    }
}
