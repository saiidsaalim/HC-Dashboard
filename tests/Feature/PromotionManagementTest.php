<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_promotions_and_see_live_totals(): void
    {
        $user = User::factory()->create();
        EmployeePromotion::create($this->promotionAttributes('SAP100'));
        EmployeePromotion::create($this->promotionAttributes('SAP101'));

        $response = $this->actingAs($user)->get(route('promosi', ['search' => 'SAP100']));

        $response->assertOk()
            ->assertViewHas('search', 'SAP100')
            ->assertSee('SAP100')
            ->assertDontSee('SAP101')
            ->assertSee('Total usulan');
    }

    public function test_authenticated_user_can_create_and_update_a_promotion(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $response = $this->actingAs($user)->post(route('promosi.store'), $this->promotionAttributes('SAP102'));
        $response->assertRedirect(route('promosi'))
            ->assertSessionHas('status', 'Data promosi berhasil ditambahkan.');

        $promotion = EmployeePromotion::query()->where('sap', 'SAP102')->firstOrFail();
        $updated = array_merge($this->promotionAttributes('SAP102'), [
            'nama' => 'Nama Pegawai Diperbarui',
            'jabatan_baru' => 'Manager',
        ]);

        $this->actingAs($user)->put(route('promosi.update', $promotion), $updated)
            ->assertRedirect(route('promosi'))
            ->assertSessionHas('status', 'Data promosi berhasil diperbarui.');

        $this->assertDatabaseHas('employee_promotions', [
            'id' => $promotion->id,
            'nama' => 'Nama Pegawai Diperbarui',
            'jabatan_baru' => 'Manager',
        ]);
    }

    public function test_authenticated_user_can_delete_selected_promotions(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $selected = EmployeePromotion::create($this->promotionAttributes('SAP103'));
        $remaining = EmployeePromotion::create($this->promotionAttributes('SAP104'));

        $this->actingAs($user)->delete(route('promosi.destroy-many'), [
            'promotion_ids' => [$selected->id],
        ])->assertRedirect(route('promosi'))
            ->assertSessionHas('status', 'Berhasil menghapus 1 data promosi.');

        $this->assertDatabaseMissing('employee_promotions', ['id' => $selected->id]);
        $this->assertDatabaseHas('employee_promotions', ['id' => $remaining->id]);
    }

    public function test_authenticated_user_can_import_promotions_from_csv(): void
    {
        $user = User::factory()->create();
        Employee::query()->create(['sap' => 'SAP105']);
        $file = UploadedFile::fake()->createWithContent(
            'promosi.csv',
            "SAP,Nama,Departemen Lama,Jabatan Lama,Departemen Baru,Jabatan Baru,TMT,PG,Band Lama,JG Lama,Band Baru,JG Baru\n".
            "SAP105,Budi Santoso,Operasional,Staff,Keuangan,Supervisor,2026-10-01,PG-1,B1,JG-1,B2,JG-2\n",
        );

        $this->actingAs($user)->post(route('promosi.import'), ['file' => $file])
            ->assertRedirect(route('promosi'))
            ->assertSessionHas('status', 'Import berhasil: 1 data promosi disimpan.');

        $this->assertDatabaseHas('employee_promotions', [
            'sap' => 'SAP105',
            'departemen_lama' => 'Operasional',
            'departemen_baru' => 'Keuangan',
            'jabatan_baru' => 'Supervisor',
            'verification_status' => 'Pending',
        ]);
    }

    public function test_promotion_becomes_final_after_three_admin_or_manager_approvals_and_can_be_printed(): void
    {
        $promotion = EmployeePromotion::create($this->promotionAttributes('SAP106'));
        $approvers = collect([
            User::factory()->create(['role' => UserRole::ADMIN->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
            User::factory()->create(['role' => UserRole::MANAGER->value]),
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        foreach ($approvers as $approver) {
            $this->actingAs($approver)->post(route('promosi.approve', $promotion))
                ->assertRedirect(route('promosi'));
        }

        $this->assertSame('Final', $promotion->fresh()->verification_status);
        $this->assertDatabaseCount('promotion_approvals', 3);

        $response = $this->actingAs($superAdmin)->get(route('promosi.print', $promotion));
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

        $this->assertStringContainsString('Pegawai SAP106', $documentText);
        $this->assertStringContainsString('SAP106', $documentText);
        $this->assertStringContainsString('Supervisor', $documentText);
        $this->assertStringNotContainsString('[PEGAWAI]', $documentText);
        $this->assertStringNotContainsString('[SAP]', $documentText);
        $this->assertStringNotContainsString('[JABATAN BARU]', $documentText);
    }

    public function test_staff_cannot_approve_a_promotion(): void
    {
        $promotion = EmployeePromotion::create($this->promotionAttributes('SAP107'));
        $staff = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($staff)->post(route('promosi.approve', $promotion))->assertForbidden();
        $this->assertDatabaseCount('promotion_approvals', 0);
    }

    public function test_admin_cannot_edit_delete_or_print_promotions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $promotion = EmployeePromotion::create(array_merge($this->promotionAttributes('SAP108'), [
            'verification_status' => 'Final',
            'finalized_at' => now(),
        ]));

        $this->actingAs($admin)->put(route('promosi.update', $promotion), $this->promotionAttributes('SAP108'))
            ->assertForbidden();
        $this->actingAs($admin)->delete(route('promosi.destroy-many'), ['promotion_ids' => [$promotion->id]])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('promosi.print', $promotion))->assertForbidden();

        $this->assertDatabaseHas('employee_promotions', ['id' => $promotion->id, 'sap' => 'SAP108']);
    }

    /** @return array<string, string> */
    private function promotionAttributes(string $sap): array
    {
        Employee::query()->firstOrCreate(['sap' => $sap]);

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
