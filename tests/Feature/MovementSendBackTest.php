<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DemotionApprovalConfig;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementSendBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutation_can_be_sent_back_corrected_and_resubmitted(): void
    {
        $this->assertMovementCanBeSentBackAndResubmitted(
            EmployeeMutation::class,
            MutationApprovalConfig::class,
            'mutasi',
            'mutation_approvals',
            'employee_mutation_id',
        );
    }

    public function test_demotion_can_be_sent_back_corrected_and_resubmitted(): void
    {
        $this->assertMovementCanBeSentBackAndResubmitted(
            EmployeeDemotion::class,
            DemotionApprovalConfig::class,
            'demosi',
            'demotion_approvals',
            'employee_demotion_id',
        );
    }

    /**
     * @param  class-string<EmployeeMutation|EmployeeDemotion>  $movementClass
     * @param  class-string<MutationApprovalConfig|DemotionApprovalConfig>  $configClass
     */
    private function assertMovementCanBeSentBackAndResubmitted(
        string $movementClass,
        string $configClass,
        string $routePrefix,
        string $approvalTable,
        string $movementForeignKey,
    ): void {
        $formPrefix = $routePrefix === 'mutasi' ? 'mutation' : 'demotion';
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);

        foreach ($approvers as $index => $approver) {
            $configClass::query()->create([
                'step_order' => $index + 1,
                'user_id' => $approver->id,
            ]);
        }

        $employee = Employee::query()->create(['sap' => strtoupper($routePrefix).'-SEND-BACK']);
        /** @var EmployeeMutation|EmployeeDemotion $movement */
        $movement = $movementClass::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => 'Pegawai Send Back',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Posisi Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Posisi Baru',
            'tmt' => today()->addMonth(),
            'pg' => 'PG-1',
            'level_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'level_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ]);

        $this->actingAs($submitter)
            ->post(route("{$routePrefix}.submit", $movement))
            ->assertRedirect(route($routePrefix));

        $this->actingAs($approvers[2])
            ->post(route("{$routePrefix}.send-back", $movement), ['return_note' => 'Tidak berwenang.'])
            ->assertForbidden();

        $this->actingAs($approvers[0])
            ->post(route("{$routePrefix}.approve", $movement))
            ->assertRedirect(route($routePrefix));

        $this->actingAs($approvers[1])
            ->post(route("{$routePrefix}.send-back", $movement), [
                '_form' => "return-{$formPrefix}-{$movement->id}",
            ])
            ->assertSessionHasErrors('return_note');

        $this->actingAs($approvers[1])
            ->get(route($routePrefix))
            ->assertOk()
            ->assertSee(route("{$routePrefix}.send-back", $movement), escape: false)
            ->assertSee('Catatan Send Back');

        $this->actingAs($approvers[1])
            ->post(route("{$routePrefix}.send-back", $movement), [
                '_form' => "return-{$formPrefix}-{$movement->id}",
                'return_note' => 'Perbaiki organisasi tujuan.',
            ])
            ->assertRedirect(route($routePrefix));

        $movement->refresh();
        $this->assertSame('Returned', $movement->verification_status);
        $this->assertNull($movement->current_approval_step);
        $this->assertTrue($submitter->can('update', $movement));
        $this->assertTrue($submitter->can('submit', $movement));
        $this->assertDatabaseHas($approvalTable, [
            $movementForeignKey => $movement->id,
            'approval_round' => 1,
            'step_order' => 2,
            'status' => 'Returned',
            'note' => 'Perbaiki organisasi tujuan.',
        ]);
        $this->assertNotNull($movement->approvals()->where('step_order', 2)->value('returned_at'));

        $this->actingAs($submitter)
            ->post(route("{$routePrefix}.submit", $movement))
            ->assertRedirect(route($routePrefix));

        $movement->refresh();
        $this->assertSame('Pending', $movement->verification_status);
        $this->assertSame(2, $movement->approval_round);
        $this->assertSame(1, $movement->current_approval_step);
        $this->assertSame(6, $movement->approvals()->count());
        $this->assertSame(1, $movement->approvals()->where('approval_round', 1)->where('status', 'Approved')->count());
        $this->assertSame(1, $movement->approvals()->where('approval_round', 1)->where('status', 'Returned')->count());
        $this->assertSame(1, $movement->approvals()->where('approval_round', 1)->where('status', 'Superseded')->count());
        $this->assertSame(3, $movement->approvals()->where('approval_round', 2)->where('status', 'Pending')->count());
    }
}
