<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DemotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_demotions_and_see_live_totals(): void
    {
        $user = User::factory()->create();
        EmployeeDemotion::create($this->demotionAttributes('SAP201'));
        EmployeeDemotion::create($this->demotionAttributes('SAP202'));

        $this->actingAs($user)->get(route('demosi', ['search' => 'SAP201']))
            ->assertOk()
            ->assertViewHas('search', 'SAP201')
            ->assertSee('SAP201')
            ->assertDontSee('SAP202')
            ->assertSee('Total usulan');
    }

    public function test_authenticated_user_can_create_and_super_admin_can_update_a_demotion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('demosi.store'), $this->demotionAttributes('SAP203'))
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Data demosi berhasil ditambahkan.');

        $demotion = EmployeeDemotion::query()->where('sap', 'SAP203')->firstOrFail();
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $updated = array_merge($this->demotionAttributes('SAP203'), [
            'nama' => 'Nama Diperbarui',
        ]);

        $this->actingAs($superAdmin)->put(route('demosi.update', $demotion), $updated)
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Data demosi berhasil diperbarui.');

        $this->assertDatabaseHas('employee_demotions', [
            'id' => $demotion->id,
            'nama' => 'Nama Diperbarui',
        ]);
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

    public function test_authenticated_user_can_import_demotions_from_csv(): void
    {
        $user = User::factory()->create();
        Employee::query()->create(['sap' => 'SAP206']);
        $file = UploadedFile::fake()->createWithContent(
            'demosi.csv',
            "SAP,Nama,Departemen Lama,Jabatan Lama,Departemen Baru,Jabatan Baru,TMT,PG,Band Lama,JG Lama,Band Baru,JG Baru\n".
            "SAP206,Budi Santoso,Operasional,Supervisor,Operasional,Staff,2026-10-01,PG-1,B2,JG-2,B1,JG-1\n",
        );

        $this->actingAs($user)->post(route('demosi.import'), ['file' => $file])
            ->assertRedirect(route('demosi'))
            ->assertSessionHas('status', 'Import berhasil: 1 data demosi disimpan.');

        $this->assertDatabaseHas('employee_demotions', [
            'sap' => 'SAP206',
            'jabatan_baru' => 'Staff',
            'verification_status' => 'Pending',
        ]);
    }

    public function test_demotion_becomes_final_after_three_approvals_and_can_be_printed(): void
    {
        $demotion = EmployeeDemotion::create($this->demotionAttributes('SAP207'));
        $approvers = collect([
            User::factory()->create(['role' => UserRole::ADMIN->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

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

    /** @return array<string, string> */
    private function demotionAttributes(string $sap): array
    {
        Employee::query()->firstOrCreate(['sap' => $sap]);

        return [
            'sap' => $sap,
            'nama' => 'Pegawai '.$sap,
            'departemen_lama' => 'Operasional',
            'jabatan_lama' => 'Supervisor',
            'departemen_baru' => 'Operasional',
            'jabatan_baru' => 'Staff',
            'tmt' => '2026-12-01',
            'pg' => 'PG-1',
            'band_lama' => 'B2',
            'jg_lama' => 'JG-2',
            'band_baru' => 'B1',
            'jg_baru' => 'JG-1',
        ];
    }
}
