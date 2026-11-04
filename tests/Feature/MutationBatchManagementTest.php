<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeMutationBatch;
use App\Models\MutationApprovalConfig;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutationBatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_batch_with_multiple_independent_mutations(): void
    {
        $user = User::factory()->create();
        [$employees, $target] = $this->employeesAndTarget();

        $this->actingAs($user)->get(route('mutasi.batches.create'))
            ->assertOk()
            ->assertSee('Buat Batch Mutasi')
            ->assertSee('Atur Tujuan per Pegawai')
            ->assertSee('Simpan Batch Mutasi');

        $response = $this->actingAs($user)->post(route('mutasi.batches.store'), [
            'name' => 'Mutasi Organisasi Januari 2027',
            'description' => 'Penyesuaian organisasi dua pegawai.',
            'default_tmt' => '2027-01-01',
            'items' => [
                $this->itemPayload($employees[0], $target, 'JG-OLD-1', 'JG-NEW-1'),
                $this->itemPayload($employees[1], $target, 'JG-OLD-2', 'JG-NEW-2'),
            ],
        ]);

        $batch = EmployeeMutationBatch::query()->sole();
        $response->assertRedirect(route('mutasi.batches.show', $batch));
        $this->assertStringStartsWith('MUT-', $batch->batch_number);
        $this->assertSame($user->id, $batch->created_by);
        $this->assertDatabaseCount('employee_mutations', 2);
        $this->assertDatabaseHas('employee_mutations', [
            'employee_mutation_batch_id' => $batch->id,
            'employee_id' => $employees[0]->id,
            'new_position_id' => $target['position']->id,
            'level_lama' => 'Manager',
            'level_baru' => 'Associate',
            'verification_status' => 'Draft',
        ]);

        $standaloneMutation = $batch->mutations()->firstOrFail()->replicate();
        $standaloneMutation->forceFill([
            'employee_mutation_batch_id' => null,
            'sap' => 'SAP-DI-LUAR-BATCH',
            'nama' => 'Pegawai Di Luar Batch',
        ])->save();

        $this->actingAs($user)->get(route('mutasi'))
            ->assertOk()
            ->assertViewHas('mutations', fn ($mutations): bool => $mutations->total() === 3)
            ->assertSee('Batch Mutasi Terbaru')
            ->assertSee($batch->batch_number)
            ->assertSee('2 item')
            ->assertSee('Semua pegawai yang dimutasi ditampilkan saat halaman pertama dibuka.')
            ->assertSee('href="'.route('mutasi', ['batch' => $batch->id]).'#data-mutasi"', escape: false);

        $this->actingAs($user)->get(route('mutasi', ['batch' => $batch->id]))
            ->assertOk()
            ->assertViewHas('batchId', $batch->id)
            ->assertViewHas('mutations', fn ($mutations): bool => $mutations->total() === 2)
            ->assertSee('Pegawai dalam Batch '.$batch->batch_number)
            ->assertSee('Tampilkan semua pegawai')
            ->assertDontSee('Pegawai Di Luar Batch');

        $this->actingAs($user)->get(route('mutasi.batches.show', $batch))
            ->assertOk()
            ->assertSee('Item Batch Mutasi')
            ->assertSee('Ajukan Semua (2)')
            ->assertSee($employees[0]->sap)
            ->assertSee($employees[1]->sap);
    }

    public function test_batch_requires_at_least_two_distinct_employees(): void
    {
        $user = User::factory()->create();
        [$employees, $target] = $this->employeesAndTarget();

        $this->actingAs($user)->post(route('mutasi.batches.store'), [
            'name' => 'Batch Tidak Valid',
            'default_tmt' => '2027-01-01',
            'items' => [$this->itemPayload($employees[0], $target, 'JG-1', 'JG-2')],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('employee_mutation_batches', 0);
        $this->assertDatabaseCount('employee_mutations', 0);
    }

    public function test_batch_can_submit_all_items_and_current_approver_can_approve_selected_items(): void
    {
        $submitter = User::factory()->create();
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $index => $approver) {
            MutationApprovalConfig::query()->create(['step_order' => $index + 1, 'user_id' => $approver->id]);
        }
        [$employees, $target] = $this->employeesAndTarget();
        $batch = EmployeeMutationBatch::factory()->create(['created_by' => $submitter->id]);
        foreach ($employees as $index => $employee) {
            $batch->mutations()->create([
                'employee_id' => $employee->id,
                'sap' => $employee->sap,
                'nama' => 'Pegawai '.$employee->sap,
                'departemen_lama' => 'Department Lama',
                'jabatan_lama' => 'Position Lama',
                'departemen_baru' => 'Department Baru',
                'jabatan_baru' => 'Position Baru',
                'tmt' => '2027-01-01',
                'pg' => 'PG-1',
                'level_lama' => 'Manager',
                'jg_lama' => 'JG-OLD-'.($index + 1),
                'level_baru' => 'Associate',
                'jg_baru' => 'JG-NEW-'.($index + 1),
                'verification_status' => 'Draft',
                'new_directorate_id' => $target['directorate']->id,
                'new_department_id' => $target['department']->id,
                'new_unit_id' => $target['unit']->id,
                'new_position_id' => $target['position']->id,
            ]);
        }

        $this->actingAs($submitter)->post(route('mutasi.batches.submit', $batch))
            ->assertRedirect()
            ->assertSessionHas('status', '2 item batch berhasil diajukan.');

        $mutations = $batch->mutations()->orderBy('id')->get();
        $this->assertSame(['Pending', 'Pending'], $mutations->pluck('verification_status')->all());
        $this->assertDatabaseCount('mutation_approvals', 6);

        $this->actingAs($approvers[0])->post(route('mutasi.batches.approve-selected', $batch), [
            'mutation_ids' => $mutations->pluck('id')->all(),
        ])->assertRedirect()->assertSessionHas('status', '2 item batch berhasil disetujui.');

        foreach ($mutations as $mutation) {
            $mutation->refresh();
            $this->assertSame('Pending', $mutation->verification_status);
            $this->assertSame(2, $mutation->current_approval_step);
            $this->assertDatabaseHas('mutation_approvals', [
                'employee_mutation_id' => $mutation->id,
                'step_order' => 1,
                'status' => 'Approved',
            ]);
        }
    }

    /** @return array{array<int, Employee>, array{directorate: Directorate, department: Department, unit: Unit, position: Position}} */
    private function employeesAndTarget(): array
    {
        $oldDirectorate = Directorate::query()->create(['code' => 'BATCH-OLD-DIR', 'name' => 'Directorate Lama']);
        $oldDepartment = Department::query()->create(['directorate_id' => $oldDirectorate->id, 'code' => 'BATCH-OLD-DEP', 'name' => 'Department Lama']);
        $oldUnit = Unit::query()->create(['department_id' => $oldDepartment->id, 'code' => 'BATCH-OLD-UNIT', 'name' => 'Unit Lama']);
        $oldPosition = Position::query()->create(['unit_id' => $oldUnit->id, 'code' => 'BATCH-OLD-POS', 'name' => 'Position Lama']);
        $employees = [];
        foreach (range(1, 2) as $index) {
            $employee = Employee::query()->create([
                'sap' => 'SAP-BATCH-'.$index,
                'directorate_id' => $oldDirectorate->id,
                'department_id' => $oldDepartment->id,
                'unit_id' => $oldUnit->id,
                'position_id' => $oldPosition->id,
                'employee_subgroup' => 'Manager',
            ]);
            $employee->forceFill(['name' => 'Pegawai Batch '.$index])->save();
            $employees[] = $employee;
        }

        $directorate = Directorate::query()->create(['code' => 'BATCH-NEW-DIR', 'name' => 'Directorate Baru']);
        $department = Department::query()->create(['directorate_id' => $directorate->id, 'code' => 'BATCH-NEW-DEP', 'name' => 'Department Baru']);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'BATCH-NEW-UNIT', 'name' => 'Unit Baru']);
        $position = Position::query()->create(['unit_id' => $unit->id, 'code' => 'BATCH-NEW-POS', 'name' => 'Position Baru']);

        return [$employees, compact('directorate', 'department', 'unit', 'position')];
    }

    /**
     * @param  array{directorate: Directorate, department: Department, unit: Unit, position: Position}  $target
     * @return array<string, int|string|null>
     */
    private function itemPayload(Employee $employee, array $target, string $oldJg, string $newJg): array
    {
        return [
            'employee_id' => $employee->id,
            'new_directorate_id' => $target['directorate']->id,
            'new_department_id' => $target['department']->id,
            'new_unit_id' => $target['unit']->id,
            'new_section_id' => null,
            'new_position_id' => $target['position']->id,
            'tmt' => '2027-01-01',
            'pg' => 'PG-1',
            'jg_lama' => $oldJg,
            'level_baru' => 'Associate',
            'jg_baru' => $newJg,
        ];
    }
}
