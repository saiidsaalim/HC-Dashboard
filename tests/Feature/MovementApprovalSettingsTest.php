<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DemotionApprovalConfig;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MovementApprovalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_import_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('promosi.import'));
        $this->assertFalse(Route::has('mutasi.import'));
        $this->assertFalse(Route::has('demosi.import'));
    }

    public function test_super_admin_can_configure_exactly_three_approvers_for_mutations_and_demotions(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);

        $this->actingAs($superAdmin)->put(route('mutasi.approval-settings.update'), [
            'approvers' => [
                1 => $approvers[0]->id,
                2 => $approvers[1]->id,
                3 => $approvers[2]->id,
            ],
        ])->assertRedirect(route('mutasi.approval-settings.edit'));

        $this->assertDatabaseCount('mutation_approval_configs', 3);
        $this->assertDatabaseHas('mutation_approval_configs', [
            'step_order' => 3,
            'user_id' => $approvers[2]->id,
        ]);

        $this->actingAs($superAdmin)->put(route('demosi.approval-settings.update'), [
            'approvers' => [
                1 => $approvers[2]->id,
                2 => $approvers[1]->id,
                3 => $approvers[0]->id,
            ],
        ])->assertRedirect(route('demosi.approval-settings.edit'));

        $this->assertSame(3, DemotionApprovalConfig::query()->count());
        $this->assertDatabaseHas('demotion_approval_configs', [
            'step_order' => 1,
            'user_id' => $approvers[2]->id,
        ]);
    }

    public function test_mutation_approval_is_sequential_and_table_has_only_requested_columns(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $step => $approver) {
            MutationApprovalConfig::query()->create([
                'step_order' => $step + 1,
                'user_id' => $approver->id,
            ]);
        }
        $employee = Employee::query()->create(['sap' => 'SAP-WORKFLOW']);
        $mutation = EmployeeMutation::query()->create([
            'employee_id' => $employee->id,
            'sap' => 'SAP-WORKFLOW',
            'nama' => 'Pegawai Workflow',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Posisi Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Posisi Baru',
            'tmt' => '2026-11-02',
        ]);
        $this->actingAs($superAdmin)->post(route('mutasi.submit', $mutation))->assertRedirect(route('mutasi'));

        $this->actingAs($approvers[1])->post(route('mutasi.approve', $mutation))->assertForbidden();
        $this->actingAs($approvers[0])->post(route('mutasi.approve', $mutation))->assertRedirect(route('mutasi'));
        $this->assertDatabaseHas('mutation_approvals', [
            'employee_mutation_id' => $mutation->id,
            'step_order' => 1,
            'manager_id' => $approvers[0]->id,
        ]);

        $this->actingAs($superAdmin)->get(route('mutasi'))
            ->assertOk()
            ->assertSeeInOrder(['Pilih', 'SAP', 'Pegawai', 'Posisi Saat Ini', 'Usulan', 'Workflow', 'Aksi'])
            ->assertDontSee('Import Mutasi')
            ->assertDontSee('Departemen Lama</th>', escape: false)
            ->assertDontSee('TMT</th>', escape: false);
    }
}
