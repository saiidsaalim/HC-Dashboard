<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeImportBatch;
use App\Models\EmployeeImportRow;
use App\Models\EmployeePromotion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeImportStagingTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'SAP', 'ID Number', 'AGKN', 'Position id', 'Personal Number', 'Position',
        'Employee Subgroup', 'Cost Ctr', 'TXT_DIR', 'TXT_DEPT', 'TXT_BIRO', 'TXT_SECT',
        'Birth date', 'Gender Key', 'Personnel Area', 'abrevation position',
        'abrevation organization', 'Organizational Unit', 'Cost Center', 'Masa Kontrak',
        'E-mail', 'Religious', 'Usia', 'Tempat Lahir', 'Pendidikan', 'Hiring',
        'Organilk', 'Alamat',
    ];

    /**
     * @return array<string, string>
     */
    public function test_employee_schema_has_snake_case_target_fields_and_keeps_legacy_name_nullable(): void
    {
        $columns = Schema::getColumns('employees');
        $columnNames = array_column($columns, 'name');

        foreach ([
            'sap', 'id_number', 'agkn', 'position_id_source', 'personal_number', 'position',
            'employee_subgroup', 'cost_ctr', 'txt_dir', 'txt_dept', 'txt_biro', 'txt_sect',
            'birth_date', 'gender_key', 'personnel_area', 'abrevation_position',
            'abrevation_organization', 'organizational_unit', 'cost_center', 'masa_kontrak',
            'email', 'religious', 'usia', 'tempat_lahir', 'pendidikan', 'hiring', 'organilk', 'alamat',
        ] as $canonicalColumn) {
            $this->assertContains($canonicalColumn, $columnNames);
        }

        $this->assertContains('id', $columnNames);
        $sapColumn = collect($columns)->firstWhere('name', 'sap');
        $this->assertFalse($sapColumn['nullable']);
        $employeeTableDefinition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'employees'")->sql;
        $this->assertStringContainsString('"sap" varchar not null', strtolower($employeeTableDefinition));
        $this->assertTrue(collect(Schema::getIndexes('employees'))->contains(
            fn (array $index): bool => $index['primary'] && $index['columns'] === ['id'],
        ));
        $this->assertTrue(collect(Schema::getIndexes('employees'))->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['sap'],
        ));
        $this->assertStringContainsString('"masa_kontrak" date', strtolower($employeeTableDefinition));
        $this->assertStringContainsString('"organilk" date', strtolower($employeeTableDefinition));
        $this->assertTrue(collect($columns)->firstWhere('name', 'name')['nullable']);
        $this->assertNotContains('name', (new Employee)->getFillable());
        $this->assertFalse(collect(Schema::getIndexes('employees'))->contains(fn (array $index): bool => in_array('personal_number', $index['columns'], true) && $index['unique']));
        $this->assertFalse(collect(Schema::getForeignKeys('employees'))->contains(fn (array $foreignKey): bool => array_intersect(
            ['position_id_source', 'txt_dir', 'txt_dept', 'txt_biro', 'txt_sect', 'organizational_unit'],
            $foreignKey['columns'],
        ) !== []));
    }

    public function test_source_upload_is_staged_privately_without_writing_employees_and_accepts_position_id_alias(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $this->createEmployee('SAP-UPDATE-01');
        $headers = self::HEADERS;
        $headers[3] = 'Position ID';
        $file = $this->csvFile($headers, [[
            'SAP' => ' SAP-UPDATE-01 ',
            'Position ID' => 'SAP-POS-001',
            'Masa Kontrak' => '50041',
            'Organilk' => '39934',
            'Birth date' => '1990-05-21',
            'Hiring' => '2018-04-01',
            'E-mail' => 'employee@example.test',
        ]]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'));

        $batch = EmployeeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();
        $this->assertSame('VALID', $row->validation_status);
        $this->assertSame('AUTO_CANDIDATE', $row->mapping_status);
        $this->assertSame('SAP-POS-001', $row->normalized_payload['position_id_source']);
        $this->assertSame('UPDATE', $row->normalized_payload['_operation']);
        $this->assertArrayNotHasKey('position_id', $row->normalized_payload);
        $this->assertSame('SAP-UPDATE-01', $row->normalized_payload['sap']);
        $this->assertSame('2037-01-01', $row->normalized_payload['masa_kontrak']);
        $this->assertSame('2009-05-01', $row->normalized_payload['organilk']);
        $this->assertSame('Position ID', array_keys($row->source_payload)[3]);
        $this->assertSame(1, $batch->total_rows);
        $this->assertSame(1, Employee::query()->count());
        $this->assertTrue(Storage::disk('local')->exists($batch->source_file));

        $this->actingAs($actor)->get(route('data-pegawai.import-batches.show', $batch))
            ->assertOk()
            ->assertJsonMissingPath('rows.0.source_payload');

        $this->actingAs($actor)->get(route('data-pegawai.import-rows.show', $row))
            ->assertOk()
            ->assertJsonPath('source_payload.Position ID', 'SAP-POS-001');
    }

    public function test_employee_sap_database_constraint_rejects_null(): void
    {
        $this->expectException(QueryException::class);

        Employee::query()->create(['sap' => null]);
    }

    public function test_import_uses_sap_not_other_identifiers_to_select_create_or_update(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $existingEmployee = $this->createEmployee('SAP-IDENTITY-OLD', [
            'id_number' => 'ID-SHARED',
            'personal_number' => 'PN-SHARED',
        ]);
        $file = $this->csvFile(self::HEADERS, [[
            'SAP' => 'SAP-IDENTITY-NEW',
            'ID Number' => 'ID-SHARED',
            'Personal Number' => 'PN-SHARED',
        ]]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $row = EmployeeImportRow::query()->firstOrFail();

        $this->assertSame('CREATE', $row->normalized_payload['_operation']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-IDENTITY-NEW']);

        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $row))->assertOk();
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $row->batch))
            ->assertOk()->assertJsonPath('processed', 1);

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', [
            'sap' => 'SAP-IDENTITY-NEW',
            'id_number' => 'ID-SHARED',
            'personal_number' => 'PN-SHARED',
        ]);
        $this->assertSame('SAP-IDENTITY-OLD', $existingEmployee->fresh()->sap);
    }

    public function test_new_employee_create_waits_for_approval_then_processes_by_sap_without_synthetic_name(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $file = $this->csvFile(self::HEADERS, [[
            'SAP' => 'SAP-CREATE-01',
            'ID Number' => 'ID-001',
            'AGKN' => 'AGKN-001',
            'Personal Number' => 'PN-001',
            'E-mail' => 'new.employee@example.test',
            'Birth date' => '21-05-1990',
            'Hiring' => '01-04-2018',
            'Usia' => '34',
            'Cost Ctr' => 'CTR-RAW',
            'Cost Center' => 'Cost Center Source',
            'Masa Kontrak' => '2025-12-01',
        ]]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $batch = EmployeeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame('VALID', $row->validation_status);
        $this->assertSame('AUTO_CANDIDATE', $row->mapping_status);
        $this->assertSame('CREATE', $row->normalized_payload['_operation']);
        $this->assertArrayNotHasKey('name', $row->normalized_payload);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-CREATE-01']);
        $row->forceFill([
            'validation_status' => 'REVIEW_REQUIRED',
            'mapping_status' => 'REVIEW_REQUIRED',
        ])->save();
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()
            ->assertJsonPath('processed', 0)
            ->assertJsonPath('review_required', 1);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-CREATE-01']);

        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $row))
            ->assertOk()->assertJsonPath('mapping_status', 'APPROVED');

        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-CREATE-01']);
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()
            ->assertJsonPath('processed', 1);
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()
            ->assertJsonPath('processed', 0);

        $employee = Employee::query()->where('sap', 'SAP-CREATE-01')->firstOrFail();
        $this->assertDatabaseCount('employees', 1);
        $this->assertNull($employee->getAttribute('name'));
        $this->assertSame('ID-001', $employee->id_number);
        $this->assertSame('AGKN-001', $employee->agkn);
        $this->assertSame('2025-12-01', $employee->masa_kontrak->toDateString());
        $this->assertSame('CTR-RAW', $employee->cost_ctr);
        $this->assertSame('Cost Center Source', $employee->cost_center);
        $this->assertSame(34, $employee->usia);
        $this->assertSame('2018-04-01', $employee->hiring->toDateString());
        $this->assertSame('1990-05-21', $employee->birth_date->toDateString());
    }

    public function test_sample_batch_validates_mixed_rows_and_writes_only_approved_create_and_update(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $existingEmployee = $this->createEmployee('SAP-E2E UPDATE', ['txt_dept' => 'Old Department']);
        $createSource = $this->canonicalSourceRow('SAP-E2E-CREATE');
        $duplicateSource = $this->canonicalSourceRow('SAP-E2E-DUPLICATE');
        $duplicateWithWhitespace = $duplicateSource;
        $duplicateWithWhitespace['SAP'] = ' SAP-E2E-DUPLICATE ';
        $invalidDateSource = $this->canonicalSourceRow('SAP-E2E-INVALID-DATE');
        $invalidDateSource['Birth date'] = '31-02-2024';
        $invalidDateSource['Hiring'] = 'not-a-date';
        $updateSource = $this->canonicalSourceRow(' SAP-E2E   UPDATE ');
        $updateSource['TXT_DEPT'] = 'Updated Department';
        $emptyOptionalSource = ['SAP' => 'SAP-E2E-EMPTY'];
        $file = $this->csvFile(self::HEADERS, [
            $createSource,
            $duplicateSource,
            $duplicateWithWhitespace,
            ['SAP' => '   ', 'ID Number' => 'ID-WITHOUT-SAP'],
            $invalidDateSource,
            $emptyOptionalSource,
            $updateSource,
        ]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $batch = EmployeeImportBatch::query()->firstOrFail();
        $rows = $batch->rows()->orderBy('source_row')->get();

        $this->assertSame(7, $batch->total_rows);
        $this->assertSame(3, $batch->valid_rows);
        $this->assertSame(4, $batch->invalid_rows);
        $this->assertSame(0, $batch->approved_rows);
        $this->assertSame(0, $batch->processed_rows);
        $this->assertSame('VALID', $rows[0]->validation_status);
        $this->assertSame('INVALID', $rows[1]->validation_status);
        $this->assertSame('INVALID', $rows[2]->validation_status);
        $this->assertSame('INVALID', $rows[3]->validation_status);
        $this->assertSame('INVALID', $rows[4]->validation_status);
        $this->assertSame('VALID', $rows[5]->validation_status);
        $this->assertSame('VALID', $rows[6]->validation_status);
        $this->assertSame('CREATE', $rows[0]->normalized_payload['_operation']);
        $this->assertSame('UPDATE', $rows[6]->normalized_payload['_operation']);
        $this->assertSame('SAP-E2E UPDATE', $rows[6]->normalized_payload['sap']);
        $this->assertSame('EXT-POS-SAP-E2E-CREATE', $rows[0]->normalized_payload['position_id_source']);
        $this->assertSame('2025-12-01', $rows[0]->normalized_payload['masa_kontrak']);
        $this->assertSame('31-02-2024', $rows[4]->source_payload['Birth date']);
        $this->assertSame('31-02-2024', $rows[4]->normalized_payload['birth_date']);
        $this->assertSame('not-a-date', $rows[4]->source_payload['Hiring']);
        $this->assertSame('not-a-date', $rows[4]->normalized_payload['hiring']);
        $this->assertSame('SAP-E2E-CREATE', $rows[0]->normalized_payload['sap']);
        $this->assertNull($rows[5]->normalized_payload['id_number']);

        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()->assertJsonPath('processed', 0);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-E2E-CREATE']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-E2E-EMPTY']);

        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $rows[0]))->assertOk();
        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $rows[6]))->assertOk();
        $this->assertSame(2, $batch->fresh()->approved_rows);

        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $batch))
            ->assertOk()->assertJsonPath('processed', 2);
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', [
            'sap' => 'SAP-E2E-CREATE',
            'id_number' => 'ID-SAP-E2E-CREATE',
            'agkn' => 'AGKN-SOURCE',
            'position_id_source' => 'EXT-POS-SAP-E2E-CREATE',
            'personal_number' => 'PN-SAP-E2E-CREATE',
            'position' => 'Source Position',
            'employee_subgroup' => 'Source Subgroup',
            'cost_ctr' => 'CCTR-SOURCE',
            'txt_dir' => 'Directorate Source',
            'txt_dept' => 'Department Source',
            'txt_biro' => 'Bureau Source',
            'txt_sect' => 'Section Source',
            'gender_key' => 'F',
            'personnel_area' => 'Area Source',
            'abrevation_position' => 'POS-ABBR',
            'abrevation_organization' => 'ORG-ABBR',
            'organizational_unit' => 'Unit Source',
            'cost_center' => 'Cost Center Source',
            'email' => 'source@example.test',
            'religious' => 'Religion Source',
            'usia' => 36,
            'tempat_lahir' => 'Bandung',
            'pendidikan' => 'S1',
            'alamat' => 'Alamat Source',
        ]);
        $createdEmployee = Employee::query()->where('sap', 'SAP-E2E-CREATE')->firstOrFail();
        $this->assertSame('1990-05-21', $createdEmployee->birth_date->toDateString());
        $this->assertSame('2018-04-01', $createdEmployee->hiring->toDateString());
        $this->assertSame('2025-12-01', $createdEmployee->masa_kontrak->toDateString());
        $this->assertSame('2025-01-15', $createdEmployee->organilk->toDateString());
        $this->assertDatabaseHas('employees', [
            'id' => $existingEmployee->id,
            'sap' => 'SAP-E2E UPDATE',
            'txt_dept' => 'Updated Department',
        ]);
        $this->assertSame('CREATE', $rows[0]->fresh()->processing_result['operation']);
        $this->assertSame('UPDATE', $rows[6]->fresh()->processing_result['operation']);
        $this->assertNull(Employee::query()->where('sap', 'SAP-E2E-EMPTY')->first());
    }

    public function test_reimporting_an_approved_sap_updates_instead_of_creating_a_duplicate(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $file = $this->csvFile(self::HEADERS, [$this->canonicalSourceRow('SAP-E2E-IDEMPOTENT')]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $firstRow = EmployeeImportRow::query()->firstOrFail();
        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $firstRow))->assertOk();
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $firstRow->batch))
            ->assertOk()->assertJsonPath('processed', 1);

        $file = $this->csvFile(self::HEADERS, [$this->canonicalSourceRow('SAP-E2E-IDEMPOTENT')]);
        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $secondRow = EmployeeImportRow::query()->where('id', '!=', $firstRow->id)->firstOrFail();
        $this->assertSame('UPDATE', $secondRow->normalized_payload['_operation']);
        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $secondRow))->assertOk();
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $secondRow->batch))
            ->assertOk()->assertJsonPath('processed', 1);
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $secondRow->batch))
            ->assertOk()->assertJsonPath('processed', 0);

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseHas('employees', ['sap' => 'SAP-E2E-IDEMPOTENT']);
        $this->assertSame('UPDATE', $secondRow->fresh()->processing_result['operation']);
    }

    public function test_validated_existing_employee_update_does_not_overwrite_legacy_name_or_write_before_approval(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $employee = $this->createEmployee('SAP-UPDATE-02', ['name' => 'Existing Legacy Name']);
        $employee->forceFill(['name' => 'Existing Legacy Name'])->save();
        $file = $this->csvFile(self::HEADERS, [[
            'SAP' => 'SAP-UPDATE-02',
            'Position id' => 'EXT-POS-77',
            'Position' => 'Analyst',
            'TXT_DEPT' => 'Research',
            'E-mail' => 'updated@example.test',
            'Birth date' => '1992-02-03',
            'Hiring' => '2017-08-09',
        ]]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('VALID', $row->validation_status);
        $this->assertSame('AUTO_CANDIDATE', $row->mapping_status);
        $this->assertNull($employee->fresh()->position_id_source);

        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $row))->assertOk();
        $this->assertNull($employee->fresh()->position_id_source);

        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $row->batch))->assertOk()
            ->assertJsonPath('processed', 1);
        $employee->refresh();
        $this->assertSame('Existing Legacy Name', $employee->name);
        $this->assertSame('EXT-POS-77', $employee->position_id_source);
        $this->assertSame('Research', $employee->txt_dept);
        $this->assertSame('Analyst', $employee->position);
        $this->assertSame('updated@example.test', $employee->email);
    }

    public function test_duplicate_sap_invalid_dates_and_invalid_email_are_reported_without_losing_source_values(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $file = $this->csvFile(self::HEADERS, [
            [
                'SAP' => 'SAP-DUP-01',
                'E-mail' => 'invalid-email',
                'Birth date' => '31-02-2024',
                'Hiring' => 'not-a-date',
                'Masa Kontrak' => 'bukan-tanggal',
                'Organilk' => 'bukan-tanggal',
            ],
            ['SAP' => 'SAP-DUP-01'],
        ]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $rows = EmployeeImportRow::query()->orderBy('source_row')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('INVALID', $row->validation_status);
            $this->assertContains('SAP appears more than once in this source file.', $row->validation_errors);
        }
        $this->assertSame('31-02-2024', $rows[0]->source_payload['Birth date']);
        $this->assertSame('not-a-date', $rows[0]->source_payload['Hiring']);
        $this->assertSame('31-02-2024', $rows[0]->normalized_payload['birth_date']);
        $this->assertSame('not-a-date', $rows[0]->normalized_payload['hiring']);
        $this->assertContains('E-mail is not a valid email address.', $rows[0]->validation_errors);
        $this->assertContains('birth_date is not a valid date.', $rows[0]->validation_errors);
        $this->assertContains('hiring is not a valid date.', $rows[0]->validation_errors);
        $this->assertSame('bukan-tanggal', $rows[0]->source_payload['Masa Kontrak']);
        $this->assertSame('bukan-tanggal', $rows[0]->normalized_payload['masa_kontrak']);
        $this->assertSame('bukan-tanggal', $rows[0]->source_payload['Organilk']);
        $this->assertSame('bukan-tanggal', $rows[0]->normalized_payload['organilk']);
        $this->assertContains('masa_kontrak is not a valid date.', $rows[0]->validation_errors);
        $this->assertContains('organilk is not a valid date.', $rows[0]->validation_errors);
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $rows[0]->batch))
            ->assertOk()->assertJsonPath('processed', 0);
        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $rows[0]))
            ->assertSessionHasErrors('row');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_only_super_admin_can_upload_review_and_process_employee_imports(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->post(route('data-pegawai.import'), ['file' => $this->csvFile(self::HEADERS, [])])
            ->assertForbidden();
        $this->assertDatabaseCount('employee_import_batches', 0);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_batch_and_source_payload_review_endpoints_are_super_admin_only(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin)->post(route('data-pegawai.import'), [
            'file' => $this->csvFile(self::HEADERS, [['SAP' => 'SAP-PRIVATE-01']]),
        ])->assertRedirect();
        $batch = EmployeeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get(route('data-pegawai.import-batches.show', $batch))->assertForbidden();
        $this->actingAs($admin)->get(route('data-pegawai.import-rows.show', $row))->assertForbidden();
        $this->actingAs($admin)->post(route('data-pegawai.import-rows.approve', $row))->assertForbidden();
        $this->actingAs($admin)->post(route('data-pegawai.import-batches.process', $batch))->assertForbidden();
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_super_admin_review_pages_show_batch_payload_and_upload_link(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $file = $this->csvFile(self::HEADERS, [
            $this->canonicalSourceRow('SAP-REVIEW-UI'),
            ['SAP' => '', 'ID Number' => 'ID-WITHOUT-SAP'],
        ]);

        $this->actingAs($superAdmin)->followingRedirects()->post(route('data-pegawai.import'), ['file' => $file])
            ->assertOk()
            ->assertSee('Buka review batch');

        $batch = EmployeeImportBatch::query()->firstOrFail();
        $row = $batch->rows()->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get(route('data-pegawai.import.review.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('data-pegawai.import.review.show', $batch))->assertForbidden();
        $this->actingAs($admin)->post(route('data-pegawai.import-batches.approve-candidates', $batch))->assertForbidden();

        $this->actingAs($superAdmin)->get(route('data-pegawai.import.review.index'))
            ->assertOk()
            ->assertSee('#'.$batch->id)
            ->assertSee('Review');
        $this->actingAs($superAdmin)->get(route('data-pegawai.import.review.show', $batch))
            ->assertOk()
            ->assertSee('Row '.$row->source_row)
            ->assertSee('SAP-REVIEW-UI')
            ->assertSee('Source payload')
            ->assertSee('Normalized payload')
            ->assertSee('Position id')
            ->assertSee('EXT-POS-SAP-REVIEW-UI')
            ->assertSee('Setujui semua (1)')
            ->assertSee('Invalid (1)');

        $this->actingAs($superAdmin)->get(route('data-pegawai.import.review.show', [
            'batch' => $batch,
            'filter' => 'invalid',
        ]))->assertOk()
            ->assertSee('Invalid (1)')
            ->assertSee('ID-WITHOUT-SAP')
            ->assertDontSee('SAP-REVIEW-UI');
    }

    public function test_bulk_approval_only_approves_valid_auto_candidates(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $file = $this->csvFile(self::HEADERS, [
            $this->canonicalSourceRow('SAP-BULK-ELIGIBLE'),
            $this->canonicalSourceRow('SAP-BULK-REVIEW'),
            ['SAP' => '', 'ID Number' => 'ID-WITHOUT-SAP'],
            $this->canonicalSourceRow('SAP-BULK-UNMATCHED'),
        ]);

        $this->actingAs($actor)->post(route('data-pegawai.import'), ['file' => $file])->assertRedirect();
        $batch = EmployeeImportBatch::query()->firstOrFail();
        $rows = $batch->rows()->orderBy('source_row')->get();
        $rows[1]->forceFill([
            'validation_status' => 'REVIEW_REQUIRED',
            'mapping_status' => 'REVIEW_REQUIRED',
        ])->save();
        $rows[3]->forceFill(['mapping_status' => 'UNMATCHED'])->save();

        $this->actingAs($actor)->post(route('data-pegawai.import-batches.approve-candidates', $batch))
            ->assertOk()->assertJsonPath('approved', 1);

        $this->assertSame('APPROVED', $rows[0]->fresh()->mapping_status);
        $this->assertSame('REVIEW_REQUIRED', $rows[1]->fresh()->mapping_status);
        $this->assertSame('INVALID', $rows[2]->fresh()->validation_status);
        $this->assertSame('UNMATCHED', $rows[3]->fresh()->mapping_status);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_unmatched_import_row_cannot_be_approved_or_processed(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $this->actingAs($actor)->post(route('data-pegawai.import'), [
            'file' => $this->csvFile(self::HEADERS, [['SAP' => 'SAP-UNMATCHED-01']]),
        ])->assertRedirect();
        $row = EmployeeImportRow::query()->firstOrFail();
        $row->forceFill([
            'validation_status' => 'VALID',
            'mapping_status' => 'UNMATCHED',
        ])->save();

        $this->actingAs($actor)->post(route('data-pegawai.import-rows.approve', $row))
            ->assertSessionHasErrors('row');
        $this->actingAs($actor)->post(route('data-pegawai.import-batches.process', $row->batch))
            ->assertOk()->assertJsonPath('processed', 0);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP-UNMATCHED-01']);
    }

    public function test_source_row_is_unique_within_an_import_batch(): void
    {
        Storage::fake('local');
        $actor = $this->superAdmin();
        $this->actingAs($actor)->post(route('data-pegawai.import'), [
            'file' => $this->csvFile(self::HEADERS, [['SAP' => 'SAP-UNIQUE-ROW']]),
        ])->assertRedirect();

        $row = EmployeeImportRow::query()->firstOrFail();
        $this->expectException(QueryException::class);
        EmployeeImportRow::query()->create([
            'employee_import_batch_id' => $row->employee_import_batch_id,
            'source_row' => $row->source_row,
            'source_payload' => [],
            'validation_status' => 'PENDING',
            'mapping_status' => 'REVIEW_REQUIRED',
        ]);
    }

    public function test_promotion_sap_foreign_key_rejects_an_orphan_employee_identifier(): void
    {
        $this->expectException(QueryException::class);
        EmployeePromotion::query()->create([
            'sap' => 'SAP-NOT-FOUND',
            'nama' => 'Snapshot Name',
            'departemen_lama' => 'Old Department',
            'departemen_baru' => 'New Department',
            'jabatan_lama' => 'Old Position',
            'jabatan_baru' => 'New Position',
            'tmt' => '2026-01-01',
            'pg' => 'PG',
            'band_lama' => 'B1',
            'jg_lama' => 'J1',
            'band_baru' => 'B2',
            'jg_baru' => 'J2',
            'verification_status' => 'Pending',
        ]);
    }

    public function test_employee_mutation_relationship_resolves_using_sap_without_fk_while_orphans_exist(): void
    {
        $employee = $this->createEmployee('SAP-MUTATION-REL');
        $mutation = $employee->mutations()->create([
            'nama' => 'Historical Snapshot Name',
            'departemen_lama' => 'Old Department',
            'jabatan_lama' => 'Old Position',
            'departemen_baru' => 'New Department',
            'jabatan_baru' => 'New Position',
            'tmt' => '2026-01-01',
        ]);

        $this->assertSame($employee->sap, $mutation->sap);
        $this->assertSame($employee->id, $mutation->employee->id);
        $this->assertFalse(collect(Schema::getForeignKeys('employee_mutations'))->contains(
            fn (array $foreignKey): bool => in_array('sap', $foreignKey['columns'], true),
        ));
    }

    public function test_sap_foreign_keys_cascade_on_update_restrict_delete_while_mutation_remains_snapshot(): void
    {
        $employee = $this->createEmployee('SAP-REL-01');
        $promotion = EmployeePromotion::query()->create([
            'sap' => $employee->sap,
            'nama' => 'Historical Snapshot Name',
            'departemen_lama' => 'Old Department',
            'departemen_baru' => 'New Department',
            'jabatan_lama' => 'Old Position',
            'jabatan_baru' => 'New Position',
            'tmt' => '2026-01-01',
            'pg' => 'PG',
            'band_lama' => 'B1',
            'jg_lama' => 'J1',
            'band_baru' => 'B2',
            'jg_baru' => 'J2',
            'verification_status' => 'Pending',
        ]);
        $this->assertSame($employee->id, $promotion->employee->id);
        $this->assertTrue($employee->promotions()->whereKey($promotion->id)->exists());

        $employee->sap = 'SAP-REL-02';
        $employee->save();
        $this->assertDatabaseHas('employee_promotions', ['sap' => 'SAP-REL-02', 'nama' => 'Historical Snapshot Name']);
        $this->assertSame('SAP-REL-02', $promotion->fresh()->employee->sap);
        $this->assertFalse(collect(Schema::getForeignKeys('employee_mutations'))->contains(fn (array $foreignKey): bool => in_array('sap', $foreignKey['columns'], true)));

        $this->expectException(QueryException::class);
        $employee->delete();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
    }

    /** @return array<string, string> */
    private function canonicalSourceRow(string $sap): array
    {
        return [
            'SAP' => $sap,
            'ID Number' => 'ID-'.$sap,
            'AGKN' => 'AGKN-SOURCE',
            'Position id' => 'EXT-POS-'.$sap,
            'Personal Number' => 'PN-'.$sap,
            'Position' => 'Source Position',
            'Employee Subgroup' => 'Source Subgroup',
            'Cost Ctr' => 'CCTR-SOURCE',
            'TXT_DIR' => 'Directorate Source',
            'TXT_DEPT' => 'Department Source',
            'TXT_BIRO' => 'Bureau Source',
            'TXT_SECT' => 'Section Source',
            'Birth date' => '1990-05-21',
            'Gender Key' => 'F',
            'Personnel Area' => 'Area Source',
            'abrevation position' => 'POS-ABBR',
            'abrevation organization' => 'ORG-ABBR',
            'Organizational Unit' => 'Unit Source',
            'Cost Center' => 'Cost Center Source',
            'Masa Kontrak' => '2025-12-01',
            'E-mail' => 'source@example.test',
            'Religious' => 'Religion Source',
            'Usia' => '36',
            'Tempat Lahir' => 'Bandung',
            'Pendidikan' => 'S1',
            'Hiring' => '2018-04-01',
            'Organilk' => '2025-01-15',
            'Alamat' => 'Alamat Source',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function createEmployee(string $sap, array $attributes = []): Employee
    {
        return Employee::query()->create([
            'sap' => $sap,
            ...$attributes,
        ]);
    }

    /** @param array<int, string> $headers @param array<int, array<string, string>> $rows */
    private function csvFile(array $headers, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): string => $row[$header] ?? '', $headers));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('employee-source.csv', $contents ?: '');
    }
}
