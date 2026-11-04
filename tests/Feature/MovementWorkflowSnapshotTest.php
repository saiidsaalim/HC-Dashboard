<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\LevelTransitionService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MovementWorkflowSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_workflow_keeps_approver_snapshot_when_configuration_and_user_change(): void
    {
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $index => $approver) {
            MutationApprovalConfig::query()->create([
                'step_order' => $index + 1,
                'user_id' => $approver->id,
            ]);
        }

        $employee = Employee::query()->create(['sap' => 'SAP-SNAPSHOT']);
        $movement = EmployeeMutation::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => 'Pegawai Snapshot',
            'departemen_lama' => 'Lama',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Baru',
            'jabatan_baru' => 'Supervisor',
            'tmt' => today()->addMonth(),
        ]);
        $workflow = app(MovementApprovalWorkflowService::class);
        $workflow->submit($movement, $submitter);

        $replacement = User::factory()->create(['role' => UserRole::MANAGER->value]);
        MutationApprovalConfig::query()->where('step_order', 1)->update(['user_id' => $replacement->id]);

        $movement->refresh();
        $this->assertTrue($workflow->currentApprover($movement)?->is($approvers[0]));
        $this->assertSame($approvers[0]->name, $movement->approvals()->where('step_order', 1)->value('approver_name_snapshot'));

        $this->expectException(AuthorizationException::class);
        try {
            app(ApprovalService::class)->approve($movement, $replacement);
        } finally {
            app(ApprovalService::class)->approve($movement, $approvers[0]);
            $snapshotName = $approvers[0]->name;
            $approvers[0]->delete();

            $approval = $movement->approvals()->where('step_order', 1)->sole();
            $this->assertNull($approval->manager_id);
            $this->assertSame($snapshotName, $approval->approver_name_snapshot);
            $this->assertSame('Approved', $approval->status);
        }
    }

    public function test_employee_sap_change_does_not_break_the_movement_relationship(): void
    {
        $employee = Employee::query()->create(['sap' => 'SAP-BEFORE']);
        $movement = EmployeeMutation::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => 'Pegawai Stabil',
            'departemen_lama' => 'Lama',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Baru',
            'jabatan_baru' => 'Supervisor',
            'tmt' => today(),
        ]);

        $employee->update(['sap' => 'SAP-AFTER']);

        $this->assertTrue($movement->fresh()->employee->is($employee));
        $this->assertSame('SAP-BEFORE', $movement->fresh()->sap);
    }

    public function test_level_transition_resolver_rejects_wrong_direction_equal_and_incomparable_levels(): void
    {
        $service = app(LevelTransitionService::class);
        $service->ensurePromotion('Supervisor', 'Associate');
        $service->ensureDemotion('Associate', 'Supervisor');
        $service->ensureDemotion('General Manager', 'Senior Manager');
        $service->ensurePromotion('Manager', 'Senior Manager');

        foreach ([
            fn () => $service->ensurePromotion('Manager', 'Associate'),
            fn () => $service->ensureDemotion('Supervisor', 'Associate'),
            fn () => $service->ensurePromotion('Manager', 'Manager'),
            fn () => $service->ensurePromotion('B1', 'Manager'),
            fn () => $service->ensurePromotion('Staff', 'Supervisor'),
        ] as $invalidTransition) {
            try {
                $invalidTransition();
                $this->fail('Transisi level yang tidak valid seharusnya ditolak.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('level_baru', $exception->errors());
            }
        }
    }
}
