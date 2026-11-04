<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DemotionApprovalConfig;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DemotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_uses_level_terms_and_no_legacy_term(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('demosi'));

        $response
            ->assertOk()
            ->assertSee('Tambah Data Demosi')
            ->assertSee('Lengkapi data utama dan bandingkan organisasi saat ini dengan organisasi tujuan sebelum menyimpan.')
            ->assertSeeInOrder(['Cari Employee', 'Organisasi Saat Ini', 'Organisasi Tujuan', 'Detail Perubahan'])
            ->assertSee('aria-describedby="manual-demotion-description"', escape: false)
            ->assertSee('Level Lama')
            ->assertSee('Level Baru')
            ->assertSee('name="level_lama"', escape: false)
            ->assertSee('name="level_baru"', escape: false)
            ->assertSee('<select id="create-demotion-level_baru"', escape: false)
            ->assertSee('w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap', escape: false)
            ->assertSee('w-60 px-4 py-3">Workflow', escape: false)
            ->assertDontSee('BA'.'ND');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form//form')->length, 'Form Demosi tidak boleh bersarang.');
    }

    public function test_authenticated_user_can_search_demotions_and_see_live_totals(): void
    {
        $user = User::factory()->create();
        EmployeeDemotion::create($this->demotionAttributes('SAP201'));
        EmployeeDemotion::create($this->demotionAttributes('SAP202'));

        $this->actingAs($user)->get(route('demosi', ['search' => 'SAP201']))
            ->assertOk()
            ->assertViewHas('search', 'SAP201')
            ->assertViewHas('demotions', fn ($demotions): bool => $demotions->count() === 1
                && $demotions->first()->sap === 'SAP201')
            ->assertSee('SAP201')
            ->assertSeeInOrder([
                'Detail Demosi HC',
                'Organisasi Sebelumnya',
                'Organisasi Tujuan',
                'Detail Perubahan',
                'Metadata Demosi',
                'Persetujuan 0 dari 3',
            ])
            ->assertSee('Total usulan');
    }

    public function test_authenticated_user_can_create_a_draft_and_submit_it_after_super_admin_updates_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('demosi.store'), $this->demotionAttributes('SAP203'))
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Data demosi berhasil disimpan sebagai Draft.');

        $demotion = EmployeeDemotion::query()->where('sap', 'SAP203')->firstOrFail();
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $updated = array_merge($this->demotionAttributes('SAP203'), [
            'nama' => 'Pegawai SAP203',
        ]);

        $this->actingAs($superAdmin)->put(route('demosi.update', $demotion), $updated)
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Data demosi berhasil diperbarui.');

        $this->assertDatabaseHas('employee_demotions', [
            'id' => $demotion->id,
            'nama' => 'Pegawai SAP203',
            'verification_status' => 'Draft',
        ]);

        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);
        foreach ($approvers as $step => $approver) {
            DemotionApprovalConfig::query()->create(['step_order' => $step + 1, 'user_id' => $approver->id]);
        }

        $this->actingAs($approvers[0])->post(route('demosi.approve', $demotion))->assertForbidden();

        $listResponse = $this->actingAs($user)->get(route('demosi'));
        $listResponse->assertOk()->assertSee('Ajukan Demosi');
        $document = new \DOMDocument;
        @$document->loadHTML($listResponse->getContent());
        $xpath = new \DOMXPath($document);
        $employeeCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP203")]]/td[@data-label="Pegawai"]')->item(0);
        $targetCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP203")]]/td[@data-label="Usulan"]')->item(0);
        $actionCell = $xpath->query('//table/tbody/tr[td[contains(., "SAP203")]]/td[@data-label="Aksi"]')->item(0);
        $this->assertNotNull($employeeCell);
        $this->assertNotNull($targetCell);
        $this->assertNotNull($actionCell);
        $this->assertStringContainsString('TMT 01 Dec 2026', $employeeCell->textContent);
        $this->assertStringNotContainsString('TMT', $targetCell->textContent);
        $this->assertSame('Detail Ajukan Demosi', preg_replace('/\s+/', ' ', trim($actionCell->textContent)));

        $this->actingAs($user)->post(route('demosi.submit', $demotion))
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Demosi berhasil diajukan ke approver Tahap 1.');
        $this->assertSame('Pending', $demotion->fresh()->verification_status);
    }

    public function test_super_admin_can_delete_selected_demotions(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $selected = EmployeeDemotion::create($this->demotionAttributes('SAP204'));
        $remaining = EmployeeDemotion::create($this->demotionAttributes('SAP205'));

        $this->actingAs($superAdmin)->delete(route('demosi.destroy-many'), [
            'demotion_ids' => [$selected->id],
        ])->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Berhasil menghapus 1 data demosi.');

        $this->assertDatabaseMissing('employee_demotions', ['id' => $selected->id]);
        $this->assertDatabaseHas('employee_demotions', ['id' => $remaining->id]);
    }

    public function test_demotion_becomes_final_after_three_approvals_and_can_be_printed(): void
    {
        $demotion = EmployeeDemotion::create([
            ...$this->demotionAttributes('SAP207'),
            'tmt' => today()->toDateString(),
        ]);
        $approvers = collect([
            User::factory()->create(['role' => UserRole::ADMIN->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
        ]);
        foreach ($approvers as $step => $approver) {
            DemotionApprovalConfig::query()->create(['step_order' => $step + 1, 'user_id' => $approver->id]);
        }
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $this->actingAs($superAdmin)->post(route('demosi.submit', $demotion))->assertRedirect(route('demosi'));

        foreach ($approvers as $approver) {
            $this->actingAs($approver)->post(route('demosi.approve', $demotion))
                ->assertRedirect(route('demosi'));
        }

        $this->assertSame('Final', $demotion->fresh()->verification_status);
        $this->assertDatabaseCount('demotion_approvals', 3);

        $response = $this->actingAs($superAdmin)->get(route('demosi.print', $demotion));
        $response->assertOk()->assertDownload();

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);
        $downloadPath = $baseResponse->getFile()->getPathname();
        $archive = new \ZipArchive;
        $this->assertSame(true, $archive->open($downloadPath));
        $documentXml = $archive->getFromName('word/document.xml');
        $archive->close();
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }

        $this->assertNotFalse($documentXml);
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($documentXml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $documentText = '';
        foreach ($xpath->query('//w:t') as $textNode) {
            $documentText .= $textNode->textContent;
        }

        $this->assertStringContainsString('Demosi', $documentText);
        $this->assertStringContainsString('SAP207', $documentText);
        $this->assertStringContainsString('Level Lama: B2', $documentText);
        $this->assertStringContainsString('Level Baru: B1', $documentText);
        $this->assertStringNotContainsString('[PEGAWAI]', $documentText);
    }

    public function test_admin_cannot_edit_delete_or_print_demotions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $demotion = EmployeeDemotion::create(array_merge($this->demotionAttributes('SAP208'), [
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]));

        $this->actingAs($admin)->put(route('demosi.update', $demotion), $this->demotionAttributes('SAP208'))
            ->assertForbidden();
        $this->actingAs($admin)->delete(route('demosi.destroy-many'), ['demotion_ids' => [$demotion->id]])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('demosi.print', $demotion))->assertForbidden();

        $this->assertDatabaseHas('employee_demotions', ['id' => $demotion->id, 'sap' => 'SAP208']);
    }

    /** @return array<string, int|string|null> */
    private function demotionAttributes(string $sap): array
    {
        Employee::query()->updateOrCreate(
            ['sap' => 'LEVEL-OPTION-B1'],
            ['employee_subgroup' => 'B1'],
        );
        $employee = Employee::query()->firstOrCreate(['sap' => $sap]);
        $directorate = Directorate::query()->firstOrCreate(['code' => 'DIR-'.$sap], ['name' => 'Directorate '.$sap]);
        $department = Department::query()->firstOrCreate(['code' => 'DEP-'.$sap], [
            'directorate_id' => $directorate->id,
            'name' => 'Department '.$sap,
        ]);
        $unit = Unit::query()->firstOrCreate([
            'department_id' => $department->id,
            'code' => 'UNIT-'.$sap,
        ], ['name' => 'Unit '.$sap]);
        $position = Position::query()->firstOrCreate([
            'unit_id' => $unit->id,
            'code' => 'POS-'.$sap,
        ], ['name' => 'Position '.$sap]);
        $employee->update([
            'employee_subgroup' => 'B2',
            'directorate_id' => $directorate->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);
        $employee->forceFill(['name' => 'Pegawai '.$sap])->save();

        return [
            'employee_id' => $employee->id,
            'old_directorate_id' => $directorate->id,
            'old_department_id' => $department->id,
            'old_unit_id' => $unit->id,
            'old_section_id' => null,
            'old_position_id' => $position->id,
            'new_directorate_id' => $directorate->id,
            'new_department_id' => $department->id,
            'new_unit_id' => $unit->id,
            'new_section_id' => null,
            'new_position_id' => $position->id,
            'sap' => $sap,
            'nama' => 'Pegawai '.$sap,
            'departemen_lama' => 'Operasional',
            'jabatan_lama' => 'Supervisor',
            'departemen_baru' => 'Operasional',
            'jabatan_baru' => 'Staff',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'level_lama' => 'B2',
            'jg_lama' => 'JG-2',
            'level_baru' => 'B1',
            'jg_baru' => 'JG-1',
        ];
    }
}
