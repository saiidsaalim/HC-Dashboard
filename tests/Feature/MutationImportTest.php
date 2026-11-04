<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_renders_accessible_organization_comparison_layout(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($superAdmin)->get(route('mutasi'));

        $response->assertOk()
            ->assertSee('Tambah Data Mutasi')
            ->assertSee('Lengkapi data utama dan bandingkan organisasi saat ini dengan organisasi tujuan sebelum menyimpan.')
            ->assertSeeInOrder(['Cari Employee', 'Organisasi Saat Ini', 'Organisasi Tujuan', 'Detail Perubahan'])
            ->assertSee('aria-describedby="manual-mutation-description"', escape: false)
            ->assertSee('name="employee_id"', escape: false)
            ->assertSee('name="new_position_id"', escape: false)
            ->assertSee('name="level_lama"', escape: false)
            ->assertSee('name="level_baru"', escape: false)
            ->assertSee('w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap', escape: false)
            ->assertSee('w-60 px-4 py-3">Workflow', escape: false)
            ->assertSee('Level Lama')
            ->assertSee('Level Baru')
            ->assertDontSee('BA'.'ND')
            ->assertSee('Minimal satu organisasi atau Position harus berubah.');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="level_baru" and @id="create-mutation-level_baru"]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length, 'Form Mutasi tidak boleh bersarang.');

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $ids[] = $element->getAttribute('id');
        }
        $this->assertSame($ids, array_values(array_unique($ids)), 'Setiap elemen harus memiliki ID unik.');
    }

    public function test_authenticated_user_can_search_mutations(): void
    {
        $user = User::factory()->create();
        EmployeeMutation::create($this->mutationAttributes('SAP006'));
        EmployeeMutation::create($this->mutationAttributes('SAP007'));

        $response = $this->actingAs($user)->get(route('mutasi', ['search' => 'SAP006']));

        $response->assertOk()
            ->assertViewHas('search', 'SAP006')
            ->assertViewHas('mutations', fn ($mutations): bool => $mutations->count() === 1
                && $mutations->first()->sap === 'SAP006')
            ->assertSee('SAP006')
            ->assertSee('Data mutasi terbaru');
    }

    public function test_detail_dialog_renders_complete_comparison_and_three_step_approval_progress(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $mutation = EmployeeMutation::query()->create($this->mutationAttributes('SAP-DETAIL'));
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);

        foreach ($approvers as $step => $approver) {
            MutationApprovalConfig::query()->create([
                'step_order' => $step + 1,
                'user_id' => $approver->id,
            ]);
        }
        app(MovementApprovalWorkflowService::class)->submit($mutation, $superAdmin);
        app(ApprovalService::class)->approve($mutation, $approvers[0]);

        $this->actingAs($superAdmin)->get(route('mutasi'))
            ->assertOk()
            ->assertSee('Detail Mutasi HC')
            ->assertSeeInOrder(['Organisasi Sebelumnya', 'Organisasi Tujuan', 'Detail Perubahan'])
            ->assertSee('Metadata Mutasi')
            ->assertSee('Persetujuan 1 dari 3')
            ->assertSee($approvers[0]->name)
            ->assertSee($approvers[1]->name)
            ->assertSee('Disetujui')
            ->assertSee('Menunggu keputusan')
            ->assertSee('Edit Data');
    }

    public function test_pending_mutations_card_counts_only_pending_records(): void
    {
        $user = User::factory()->create();
        EmployeeMutation::create([...$this->mutationAttributes('SAP013'), 'verification_status' => 'Pending']);
        EmployeeMutation::create([...$this->mutationAttributes('SAP014'), 'verification_status' => 'Pending']);
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
            ...$this->mutationAttributes('SAP003'),
            'nama' => 'Dewi Lestari',
            'departemen_lama' => 'Keuangan',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Audit',
            'jabatan_baru' => 'Supervisor',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'level_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'level_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ]);

        $response = $this->actingAs($user)->put(route('mutasi.update', $mutation), [
            ...$this->mutationPayload('SAP003'),
            'tmt' => '2027-01-01',
            'pg' => 'PG-2',
            'level_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Data mutasi berhasil diperbarui.');
        $this->assertDatabaseHas('employee_mutations', [
            'id' => $mutation->id,
            'nama' => 'Pegawai SAP003',
            'jabatan_baru' => 'Position Baru SAP003',
            'pg' => 'PG-2',
            'level_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);
    }

    public function test_mutation_becomes_final_after_three_admin_or_manager_approvals(): void
    {
        $mutation = EmployeeMutation::create([
            ...$this->mutationAttributes('SAP008'),
            'tmt' => today()->toDateString(),
        ]);
        $approvers = collect([
            User::factory()->create(['role' => UserRole::ADMIN->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
        ]);
        foreach ($approvers as $step => $approver) {
            MutationApprovalConfig::query()->create(['step_order' => $step + 1, 'user_id' => $approver->id]);
        }
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->actingAs($submitter)->post(route('mutasi.submit', $mutation))->assertRedirect(route('mutasi'));

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

    public function test_finalized_mutation_print_contains_level_labels(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $mutation = EmployeeMutation::query()->create([
            ...$this->mutationAttributes('SAP-PRINT-LEVEL'),
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->get(route('mutasi.print', $mutation));
        $response->assertOk()->assertDownload();
        $downloadPath = $response->baseResponse->getFile()->getPathname();
        $archive = new \ZipArchive;
        $this->assertSame(true, $archive->open($downloadPath));
        $documentXml = $archive->getFromName('word/document.xml');
        $archive->close();
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }

        $this->assertIsString($documentXml);
        $this->assertStringContainsString('Level Lama: B1', $documentXml);
        $this->assertStringContainsString('Level Baru: B2', $documentXml);
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
            ...$this->mutationPayload('SAP002'),
            'tmt' => '2026-11-01',
            'level_baru' => 'B3',
            'jg_baru' => 'JG-3',
        ]);

        $response->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Data mutasi berhasil disimpan sebagai Draft.');
        $this->assertDatabaseHas('employee_mutations', [
            'sap' => 'SAP002',
            'nama' => 'Pegawai SAP002',
            'departemen_baru' => 'Department Baru SAP002',
            'verification_status' => 'Draft',
        ]);

        $mutation = EmployeeMutation::query()->where('sap', 'SAP002')->firstOrFail();
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $step => $approver) {
            MutationApprovalConfig::query()->create(['step_order' => $step + 1, 'user_id' => $approver->id]);
        }

        $this->actingAs($approvers[0])->post(route('mutasi.approve', $mutation))->assertForbidden();

        $listResponse = $this->actingAs($user)->get(route('mutasi'));
        $listResponse->assertOk()->assertSee('Ajukan Mutasi');
        $document = new \DOMDocument;
        @$document->loadHTML($listResponse->getContent());
        $xpath = new \DOMXPath($document);
        $employeeCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP002")]]/td[@data-label="Pegawai"]')->item(0);
        $targetCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP002")]]/td[@data-label="Usulan"]')->item(0);
        $actionCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP002")]]/td[@data-label="Aksi"]')->item(0);
        $this->assertNotNull($employeeCell);
        $this->assertNotNull($targetCell);
        $this->assertNotNull($actionCell);
        $this->assertStringContainsString('TMT 01 Nov 2026', $employeeCell->textContent);
        $this->assertStringNotContainsString('TMT', $targetCell->textContent);
        $this->assertSame('Detail Ajukan Mutasi', preg_replace('/\s+/', ' ', trim($actionCell->textContent)));

        $this->actingAs($user)->post(route('mutasi.submit', $mutation))
            ->assertRedirect(route('mutasi'))
            ->assertSessionHas('status', 'Mutasi berhasil diajukan ke approver Tahap 1.');
        $this->assertSame('Pending', $mutation->fresh()->verification_status);
    }

    /** @return array<string, string> */
    private function mutationAttributes(string $sap): array
    {
        return [
            ...$this->mutationPayload($sap),
            'sap' => $sap,
            'nama' => 'Pegawai '.$sap,
            'departemen_lama' => 'Operasional',
            'jabatan_lama' => 'Staff',
            'departemen_baru' => 'Keuangan',
            'jabatan_baru' => 'Supervisor',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'level_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'level_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ];
    }

    /** @return array<string, int|string|null> */
    private function mutationPayload(string $sap): array
    {
        Employee::query()->updateOrCreate(
            ['sap' => 'LEVEL-OPTION-B2'],
            ['employee_subgroup' => 'B2'],
        );
        Employee::query()->updateOrCreate(
            ['sap' => 'LEVEL-OPTION-B3'],
            ['employee_subgroup' => 'B3'],
        );
        $oldDirectorate = Directorate::query()->firstOrCreate(['code' => 'OLD-DIR-'.$sap], ['name' => 'Directorate Lama '.$sap]);
        $oldDepartment = Department::query()->firstOrCreate(['code' => 'OLD-DEP-'.$sap], [
            'directorate_id' => $oldDirectorate->id, 'name' => 'Department Lama '.$sap,
        ]);
        $oldUnit = Unit::query()->firstOrCreate(['department_id' => $oldDepartment->id, 'code' => 'OLD-UNIT-'.$sap], ['name' => 'Unit Lama '.$sap]);
        $oldPosition = Position::query()->firstOrCreate(['unit_id' => $oldUnit->id, 'code' => 'OLD-POS-'.$sap], ['name' => 'Position Lama '.$sap]);
        $employee = Employee::query()->firstOrCreate(['sap' => $sap]);
        $employee->update([
            'employee_subgroup' => 'B1',
            'directorate_id' => $oldDirectorate->id, 'department_id' => $oldDepartment->id,
            'unit_id' => $oldUnit->id, 'position_id' => $oldPosition->id,
        ]);
        $employee->forceFill(['name' => 'Pegawai '.$sap])->save();

        $newDirectorate = Directorate::query()->firstOrCreate(['code' => 'NEW-DIR-'.$sap], ['name' => 'Directorate Baru '.$sap]);
        $newDepartment = Department::query()->firstOrCreate(['code' => 'NEW-DEP-'.$sap], [
            'directorate_id' => $newDirectorate->id, 'name' => 'Department Baru '.$sap,
        ]);
        $newUnit = Unit::query()->firstOrCreate(['department_id' => $newDepartment->id, 'code' => 'NEW-UNIT-'.$sap], ['name' => 'Unit Baru '.$sap]);
        $newPosition = Position::query()->firstOrCreate(['unit_id' => $newUnit->id, 'code' => 'NEW-POS-'.$sap], ['name' => 'Position Baru '.$sap]);

        return [
            'employee_id' => $employee->id,
            'old_directorate_id' => $oldDirectorate->id,
            'old_department_id' => $oldDepartment->id,
            'old_unit_id' => $oldUnit->id,
            'old_section_id' => null,
            'old_position_id' => $oldPosition->id,
            'new_directorate_id' => $newDirectorate->id,
            'new_department_id' => $newDepartment->id,
            'new_unit_id' => $newUnit->id,
            'new_section_id' => null,
            'new_position_id' => $newPosition->id,
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'level_lama' => 'B1',
            'jg_lama' => 'JG-1',
            'level_baru' => 'B2',
            'jg_baru' => 'JG-2',
        ];
    }
}
