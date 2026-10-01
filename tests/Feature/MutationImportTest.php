<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmployeeMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MutationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_mutations(): void
    {
        $user = User::factory()->create();
        EmployeeMutation::create($this->mutationAttributes('SAP006'));
        EmployeeMutation::create($this->mutationAttributes('SAP007'));

        $response = $this->actingAs($user)->get(route('mutasi', ['search' => 'SAP006']));

        $response->assertOk()
            ->assertViewHas('search', 'SAP006')
            ->assertSee('SAP006')
            ->assertDontSee('SAP007');
    }

    public function test_pending_mutations_card_counts_only_pending_records(): void
    {
        $user = User::factory()->create();
        EmployeeMutation::create($this->mutationAttributes('SAP013'));
        EmployeeMutation::create($this->mutationAttributes('SAP014'));
        EmployeeMutation::create(array_merge($this->mutationAttributes('SAP015'), [
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]));
        EmployeeMutation::create(array_merge($this->mutationAttributes('SAP016'), [
            'verification_status' => 'Final',
            'finalized_at' => now()->subYear(),
        ]));

        $this->actingAs($user)->get(route('mutasi'))
            ->assertOk()
            ->assertViewHas('pendingMutations', 2)
            ->assertViewHas('approvedMutationsThisYear', 1)
            ->assertSee('Menunggu persetujuan')
            ->assertSee('Disetujui tahun ini');
    }

    public function test_super_admin_can_delete_selected_mutations(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $selectedMutation = EmployeeMutation::create($this->mutationAttributes('SAP004'));
        $remainingMutation = EmployeeMutation::create($this->mutationAttributes('SAP005'));

        $response = $this->actingAs($user)->delete(route('mutasi.destroy-many'), [
            'mutation_ids' => [$selectedMutation->id],
        ]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Berhasil menghapus 1 data mutasi.');
        $this->assertDatabaseMissing('employee_mutations', ['id' => $selectedMutation->id]);
        $this->assertDatabaseHas('employee_mutations', ['id' => $remainingMutation->id]);
    }

    public function test_super_admin_can_update_a_mutation(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $mutation = EmployeeMutation::create([
            'sap' => 'SAP003',
            'nama' => 'Dewi Lestari',
            'departemen_lama' => 'Keuangan',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Audit',
            'jabatan_baru' => 'Supervisor',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'band_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'band_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ]);

        $response = $this->actingAs($user)->put(route('mutasi.update', $mutation), [
            'sap' => 'SAP003',
            'nama' => 'Dewi Lestari Updated',
            'departemen_lama' => 'Keuangan',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Audit',
            'jabatan_baru' => 'Manager',
            'tmt' => '2027-01-01',
            'pg' => 'PG-2',
            'band_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'band_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Data mutasi berhasil diperbarui.');
        $this->assertDatabaseHas('employee_mutations', [
            'id' => $mutation->id,
            'nama' => 'Dewi Lestari Updated',
            'jabatan_baru' => 'Manager',
            'pg' => 'PG-2',
            'band_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);
    }

    public function test_mutation_becomes_final_after_three_admin_or_manager_approvals(): void
    {
        $mutation = EmployeeMutation::create($this->mutationAttributes('SAP008'));
        $approvers = collect([
            User::factory()->create(['role' => UserRole::ADMIN->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
        ]);

        foreach ($approvers as $approver) {
            $response = $this->actingAs($approver)->post(route('mutasi.approve', $mutation));
            $response->assertRedirect(route('mutasi'));
        }

        $this->assertSame('Final', $mutation->fresh()->verification_status);
        $this->assertDatabaseCount('mutation_approvals', 3);
    }

    public function test_finalized_mutation_shows_next_steps_and_print_action(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $mutation = EmployeeMutation::create(array_merge($this->mutationAttributes('SAP009'), [
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]));

        $response = $this->actingAs($user)->get(route('mutasi'));

        $response->assertOk()
            ->assertSee('Final')
            ->assertSee('Print')
            ->assertSee('Detail');
        $this->assertDatabaseHas('employee_mutations', ['id' => $mutation->id, 'verification_status' => 'Final']);
    }

    public function test_admin_cannot_edit_or_delete_mutations(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $mutation = EmployeeMutation::create($this->mutationAttributes('SAP011'));

        $this->actingAs($admin)->put(route('mutasi.update', $mutation), $this->mutationAttributes('SAP011'))
            ->assertForbidden();
        $this->actingAs($admin)->delete(route('mutasi.destroy-many'), ['mutation_ids' => [$mutation->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('employee_mutations', ['id' => $mutation->id, 'sap' => 'SAP011']);
    }

    public function test_manager_cannot_print_a_final_mutation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $mutation = EmployeeMutation::create(array_merge($this->mutationAttributes('SAP012'), [
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]));

        $this->actingAs($manager)->get(route('mutasi.print', $mutation))->assertForbidden();
    }

    public function test_authenticated_user_can_create_a_manual_mutation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('mutasi.store'), [
            'sap' => 'SAP002',
            'nama' => 'Sari Wulandari',
            'departemen_lama' => 'Keuangan',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Audit',
            'jabatan_baru' => 'Supervisor',
            'tmt' => '2026-11-01',
            'pg' => 'PG-1',
            'band_lama' => 'B2',
            'jg_lama' => 'JG-2',
            'band_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Data mutasi berhasil ditambahkan.');
        $this->assertDatabaseHas('employee_mutations', [
            'sap' => 'SAP002',
            'nama' => 'Sari Wulandari',
            'departemen_baru' => 'Audit',
        ]);
    }

    public function test_authenticated_user_can_import_mutation_csv(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'mutasi.csv',
            "SAP,Nama,Departemen Lama,Jabatan Lama,Departemen Baru,Jabatan Baru,TMT,PG,Band Lama,JG Lama,Band Baru,JG Baru\n".
            "SAP001,Budi Santoso,Operasional,Staff,Keuangan,Supervisor,2026-10-01,PG-1,B1,JG-1,B2,JG-2\n",
        );

        $response = $this->actingAs($user)->post(route('mutasi.import'), ['file' => $file]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Import berhasil: 1 data mutasi disimpan.');
        $this->assertDatabaseHas('employee_mutations', [
            'sap' => 'SAP001',
            'nama' => 'Budi Santoso',
            'departemen_lama' => 'Operasional',
            'jabatan_baru' => 'Supervisor',
            'pg' => 'PG-1',
            'band_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'band_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ]);
        $this->assertSame('2026-10-01', EmployeeMutation::query()->firstOrFail()->tmt->toDateString());
    }

    public function test_mutation_import_rejects_missing_headers(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('mutasi.csv', "SAP,Nama\nSAP001,Budi Santoso\n");

        $response = $this->actingAs($user)
            ->from(route('mutasi'))
            ->post(route('mutasi.import'), ['file' => $file]);

        $response->assertRedirect(route('mutasi'))->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('employee_mutations', ['sap' => 'SAP001']);
    }

    /** @return array<string, string> */
    private function mutationAttributes(string $sap): array
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
