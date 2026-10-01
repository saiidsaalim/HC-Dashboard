<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeImportRow;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private const EMPLOYEE_IMPORT_HEADERS = [
        'SAP', 'ID Number', 'AGKN', 'Position id', 'Personal Number', 'Position',
        'Employee Subgroup', 'Cost Ctr', 'TXT_DIR', 'TXT_DEPT', 'TXT_BIRO', 'TXT_SECT',
        'Birth date', 'Gender Key', 'Personnel Area', 'abrevation position',
        'abrevation organization', 'Organizational Unit', 'Cost Center', 'Masa Kontrak',
        'E-mail', 'Religious', 'Usia', 'Tempat Lahir', 'Pendidikan', 'Hiring',
        'Organilk', 'Alamat',
    ];

    public function test_employee_identifier_uses_unique_sap_column_without_personnel_no_column(): void
    {
        $this->assertTrue(Schema::hasColumn('employees', 'sap'));
        $this->assertFalse(Schema::hasColumn('employees', 'personnel_no'));

        Employee::create($this->employeeAttributes('SAP-UNIQUE-1'));

        $this->expectException(QueryException::class);
        Employee::create($this->employeeAttributes('SAP-UNIQUE-1'));
    }

    public function test_authenticated_user_can_search_and_filter_employees(): void
    {
        $user = User::factory()->create();
        Employee::create($this->employeeAttributes('SAP201', [
            'personal_number' => 'PN201',
            'txt_dept' => 'Operasional',
            'position' => 'Staff',
            'organilk' => '2025-01-01',
        ]));
        Employee::create($this->employeeAttributes('SAP202', [
            'personal_number' => 'PN202',
            'txt_dept' => 'Keuangan',
            'position' => 'Manager',
            'organilk' => '2025-02-01',
        ]));

        $this->actingAs($user)->get(route('data-pegawai', [
            'search' => 'PN201',
            'department' => 'Operasional',
            'position' => 'Staff',
            'organic_status' => '2025-01-01',
        ]))->assertOk()
            ->assertViewHas('search', 'PN201')
            ->assertSee('SAP201')
            ->assertDontSee('SAP202');
    }

    public function test_employee_list_uses_workbook_columns_and_keeps_all_workbook_fields_in_detail(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['code' => 'DPT-DETAIL', 'name' => 'Department Detail', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-DETAIL', 'name' => 'Unit Detail', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-DETAIL', 'name' => 'Position Detail', 'active' => true]);
        $employee = Employee::create($this->employeeAttributes('SAPDETAIL', [
            'personal_number' => 'PN-DETAIL',
            'id_number' => 'ID-DETAIL',
            'agkn' => 'AGKN-DETAIL',
            'position_id_source' => 'SAP-POS-DETAIL',
            'position' => 'Imported Position',
            'employee_subgroup' => 'Subgroup Detail',
            'cost_ctr' => 'CCTR-DETAIL',
            'txt_dir' => 'Directorate Detail',
            'txt_dept' => 'Department Detail',
            'txt_biro' => 'Bureau Detail',
            'txt_sect' => 'Section Detail',
            'birth_date' => '1990-01-02',
            'gender_key' => 'F',
            'personnel_area' => 'Area Detail',
            'abrevation_position' => 'POS-ABBR',
            'abrevation_organization' => 'ORG-ABBR',
            'organizational_unit' => 'Unit Detail',
            'cost_center' => 'Cost Center Detail',
            'masa_kontrak' => '2025-12-31',
            'email' => 'detail@example.test',
            'religious' => 'Religion Detail',
            'usia' => 34,
            'tempat_lahir' => 'Birth Place Detail',
            'pendidikan' => 'Education Detail',
            'hiring' => '2018-04-01',
            'organilk' => '2025-01-15',
            'alamat' => 'Address Detail',
        ]));

        $listResponse = $this->actingAs($user)->get(route('data-pegawai', ['search' => 'PN-DETAIL']))
            ->assertOk()
            ->assertSee('SAPDETAIL')
            ->assertSee('PN-DETAIL')
            ->assertSee('Department Detail')
            ->assertSee('Imported Position')
            ->assertSee(route('employees.show', $employee->id));

        preg_match('/<table\\b.*?<\\/table>/s', $listResponse->getContent(), $tableMatch);
        $this->assertNotEmpty($tableMatch);
        preg_match_all('/<th\\b/', $tableMatch[0], $headers);
        $this->assertCount(5, $headers[0]);
        $this->assertStringContainsString('Personal Number</th>', $tableMatch[0]);
        $this->assertStringContainsString('Department</th>', $tableMatch[0]);
        $this->assertStringNotContainsString('TXT_DEPT</th>', $tableMatch[0]);
        $this->assertStringContainsString('PN-DETAIL', $tableMatch[0]);
        $this->assertStringContainsString('Department Detail', $tableMatch[0]);
        $this->assertStringNotContainsString('Nama</th>', $tableMatch[0]);

        $profileResponse = $this->actingAs($user)->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('PN-DETAIL')
            ->assertSee('ID-DETAIL')
            ->assertSee('AGKN-DETAIL')
            ->assertSee('Position ID')
            ->assertSee('SAP-POS-DETAIL')
            ->assertSee('Imported Position')
            ->assertSee('Subgroup Detail')
            ->assertSee('Directorate Detail')
            ->assertSee('Bureau Detail')
            ->assertSee('Section Detail')
            ->assertSee('Birth Place Detail')
            ->assertSee('Area Detail')
            ->assertSee('Unit Detail')
            ->assertSee('Cost Center Detail')
            ->assertSee('detail@example.test')
            ->assertSee('Religion Detail')
            ->assertSee('Education Detail')
            ->assertSee('01 Apr 2018')
            ->assertSee('Address Detail');

        preg_match('/<h3[^>]*>Informasi Organisasi<\/h3>.*?<dl[^>]*>(.*?)<\/dl>/s', $profileResponse->getContent(), $organizationSection);
        $this->assertNotEmpty($organizationSection);
        $this->assertStringContainsString('15 Jan 2025', $organizationSection[1]);
        $this->assertStringContainsString('31 Dec 2025', $organizationSection[1]);
        $this->assertStringContainsString('01 Apr 2018', $organizationSection[1]);
        preg_match('/<h3[^>]*>Kontak dan Alamat<\/h3>.*?<dl[^>]*>(.*?)<\/dl>/s', $profileResponse->getContent(), $contactSection);
        $this->assertNotEmpty($contactSection);
        $this->assertStringNotContainsString('Hiring', $contactSection[1]);
    }

    public function test_authenticated_user_can_print_all_employees_across_pages(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 11) as $number) {
            Employee::create($this->employeeAttributes(sprintf('SAP%03d', $number), $number === 11 ? [
                'id_number' => 'ID-011',
                'email' => 'pegawai011@example.test',
                'alamat' => 'Alamat lengkap pegawai 011',
            ] : []));
        }

        $this->actingAs($user)->get(route('data-pegawai.print'))
            ->assertOk()
            ->assertViewHas('employees', fn ($employees): bool => $employees->count() === 11)
            ->assertSee('SAP011')
            ->assertSee('ID Number')
            ->assertSee('ID-011')
            ->assertSee('Alamat lengkap pegawai 011');
    }

    public function test_authenticated_user_can_export_all_employee_details_to_excel(): void
    {
        $user = User::factory()->create();
        Employee::create($this->employeeAttributes('SAPXLS', [
            'id_number' => 'ID-XLS',
            'alamat' => 'Alamat Ekspor Excel',
        ]));

        $response = $this->actingAs($user)->get(route('data-pegawai.export'));
        $response->assertOk()->assertDownload();

        $archive = new ZipArchive;
        $download = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $download);
        $this->assertTrue($archive->open($download->getFile()->getPathname()) === true);
        $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('SAP', $worksheet);
        $this->assertStringContainsString('ID-XLS', $worksheet);
        $this->assertStringContainsString('Alamat Ekspor Excel', $worksheet);
    }

    public function test_super_admin_can_import_and_update_employees_from_csv(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $existingEmployee = Employee::create($this->employeeAttributes('SAP203'));
        $existingEmployee->forceFill(['name' => 'Nama Lama'])->save();
        $file = $this->employeeImportCsv([
            [
                'sap' => 'SAP203', 'Position ID' => 'EXT-POS-203',
                'Personal Number' => 'PN203', 'TXT_DEPT' => 'Operasional',
                'Organizational Unit' => 'Operations Unit', 'Position' => 'Supervisor',
                'Usia' => '35', 'Birth date' => '21-05-1990', 'Hiring' => '10-01-2020',
                'E-mail' => 'existing@example.test',
            ],
            [
                'sap' => 'SAP204', 'Position ID' => 'EXT-POS-204',
                'Personal Number' => 'PN204', 'TXT_DEPT' => 'Keuangan',
                'Organizational Unit' => 'Finance Unit', 'Position' => 'Staff',
                'Usia' => '29', 'Birth date' => '03.11.1995', 'Hiring' => '20/05/2022',
                'E-mail' => 'new@example.test',
            ],
        ]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 2 baris, 0 invalid.');

        $this->assertDatabaseCount('employees', 1);
        $rows = EmployeeImportRow::query()->orderBy('source_row')->get();
        $this->assertSame('VALID', $rows[0]->validation_status);
        $this->assertSame('VALID', $rows[1]->validation_status);
        $this->assertSame('EXT-POS-204', $rows[1]->normalized_payload['position_id_source']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP204']);

        $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $rows[0]))->assertOk();
        $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $rows[1]))->assertOk();
        $this->actingAs($user)->post(route('data-pegawai.import-batches.process', $rows[0]->batch))
            ->assertOk()->assertJsonPath('processed', 2);

        $updatedEmployee = Employee::query()->where('sap', 'SAP203')->firstOrFail();
        $this->assertSame('Nama Lama', $updatedEmployee->name);
        $this->assertSame('PN203', $updatedEmployee->personal_number);
        $this->assertSame('EXT-POS-203', $updatedEmployee->position_id_source);
        $this->assertSame('Operasional', $updatedEmployee->txt_dept);
        $this->assertSame('1990-05-21', $updatedEmployee->birth_date->toDateString());
        $this->assertSame('2020-01-10', $updatedEmployee->hiring->toDateString());
        $this->assertDatabaseHas('employees', [
            'sap' => 'SAP204',
            'personal_number' => 'PN204',
            'txt_dept' => 'Keuangan',
            'position' => 'Staff',
        ]);
        $createdEmployee = Employee::query()->where('sap', 'SAP204')->firstOrFail();
        $this->assertSame('1995-11-03', $createdEmployee->birth_date->toDateString());
        $this->assertSame('2022-05-20', $createdEmployee->hiring->toDateString());
        $this->assertDatabaseCount('employees', 2);
    }

    public function test_employee_staging_keeps_optional_identifiers_blank_and_does_not_synthesize_names(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = $this->employeeImportCsv([['sap' => 'SAP209']]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 0 invalid.');

        $this->assertDatabaseMissing('employees', ['sap' => 'SAP209']);
        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('VALID', $row->validation_status);
        $this->assertNull($row->normalized_payload['personal_number']);
        $this->assertNull($row->normalized_payload['email']);
        $this->assertArrayNotHasKey('name', $row->normalized_payload);
    }

    public function test_employee_import_rejects_the_old_header_structure(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = UploadedFile::fake()->createWithContent('legacy-header.csv', "SAP,Name\nSAP301,Employee Name\n");

        $this->actingAs($user)->from(route('data-pegawai'))
            ->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 1 invalid.');
        $this->assertDatabaseHas('employee_import_rows', ['source_row' => 0, 'validation_status' => 'INVALID']);

        $incorrectPositionHeader = self::EMPLOYEE_IMPORT_HEADERS;
        $incorrectPositionHeader[3] = 'Position';
        $file = $this->employeeImportCsv([['sap' => 'SAP302']], $incorrectPositionHeader);

        $this->actingAs($user)->from(route('data-pegawai'))
            ->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #2: 1 baris, 1 invalid.');

        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employee_import_batches', 2);
    }

    public function test_import_reads_target_xlsx_headers_and_keeps_position_id_separate_from_position(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'DPT-XLS', 'name' => 'Excel Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-XLS', 'name' => 'Excel Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-XLS', 'name' => 'Master Position', 'active' => true]);
        $file = $this->employeeImportXlsx([[
            'sap' => 'SAPXLS001',
            'ID Number' => 'ID-001',
            'AGKN' => 'AGKN-001',
            'Position ID' => 'SAP-POS-DETAIL',
            'Personal Number' => 'PN-001',
            'Position' => 'Separate Position Text',
            'Employee Subgroup' => 'Subgroup',
            'Cost Ctr' => 'CCTR-001',
            'TXT_DIR' => 'Directorate',
            'TXT_DEPT' => 'Excel Department',
            'TXT_BIRO' => 'Bureau',
            'TXT_SECT' => 'Section',
            'Birth date' => '01-02-1990',
            'Gender Key' => 'F',
            'Personnel Area' => 'Area',
            'abrevation position' => 'POS-ABBR',
            'abrevation organization' => 'ORG-ABBR',
            'Organizational Unit' => 'Excel Unit',
            'Cost Center' => 'Cost Center Name',
            'Masa Kontrak' => '2025-12-31',
            'E-mail' => 'employee@example.test',
            'Religious' => 'Religion',
            'Usia' => '34',
            'Tempat Lahir' => 'Bandung',
            'Pendidikan' => 'S1',
            'Hiring' => '01-04-2018',
            'Organilk' => '2025-01-15',
            'Alamat' => 'Jalan Excel',
        ]]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 0 invalid.');

        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('SAP-POS-DETAIL', $row->normalized_payload['position_id_source']);
        $this->assertSame('2025-12-31', $row->normalized_payload['masa_kontrak']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAPXLS001']);

        $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $row))->assertOk();
        $this->actingAs($user)->post(route('data-pegawai.import-batches.process', $row->batch))
            ->assertOk()->assertJsonPath('processed', 1);

        $this->assertDatabaseHas('employees', [
            'sap' => 'SAPXLS001',
            'id_number' => 'ID-001',
            'agkn' => 'AGKN-001',
            'position_id_source' => 'SAP-POS-DETAIL',
            'personal_number' => 'PN-001',
            'position' => 'Separate Position Text',
            'employee_subgroup' => 'Subgroup',
            'cost_ctr' => 'CCTR-001',
            'txt_dir' => 'Directorate',
            'txt_dept' => 'Excel Department',
            'txt_biro' => 'Bureau',
            'txt_sect' => 'Section',
            'gender_key' => 'F',
            'personnel_area' => 'Area',
            'abrevation_position' => 'POS-ABBR',
            'abrevation_organization' => 'ORG-ABBR',
            'organizational_unit' => 'Excel Unit',
            'cost_center' => 'Cost Center Name',
            'email' => 'employee@example.test',
            'religious' => 'Religion',
            'usia' => 34,
            'tempat_lahir' => 'Bandung',
            'pendidikan' => 'S1',
            'alamat' => 'Jalan Excel',
        ]);

        $employee = Employee::query()->where('sap', 'SAPXLS001')->firstOrFail();
        $this->assertSame('1990-02-01', $employee->birth_date->toDateString());
        $this->assertSame('2025-12-31', $employee->masa_kontrak->toDateString());
        $this->assertSame('2025-01-15', $employee->organilk->toDateString());
        $this->assertNull($employee->Date);
        $this->assertSame('2018-04-01', $employee->hiring->toDateString());
    }

    public function test_staging_requires_sap_and_reports_invalid_source_values_without_discarding_them(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = $this->employeeImportXlsx([[
            'sap' => '5254',
            'Position ID' => '50007184',
            'Personal Number' => 'DEDDY EDRIANSAH, ST., M.S.M',
            'Position' => 'GM of Internal Audit',
            'TXT_DEPT' => 'Department of Internal Audit',
            'TXT_BIRO' => '#REF!',
            'TXT_SECT' => '#REF!',
            'Usia' => '#VALUE!',
            'E-mail' => 'DEDDY.EDRIANSAH@SIG.ID',
        ]]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 1 invalid.');
        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('INVALID', $row->validation_status);
        $this->assertContains('usia must be a non-negative integer.', $row->validation_errors);
        $this->assertSame('#VALUE!', $row->source_payload['Usia']);
        $this->assertSame('50007184', $row->normalized_payload['position_id_source']);
        $this->assertDatabaseCount('employees', 0);

        $minimalFile = $this->employeeImportCsv([[
            'sap' => '5255',
            'Personal Number' => 'PN-5255',
            'E-mail' => 'pegawai5255@example.test',
        ]], ['sap', 'Personal Number', 'E-mail']);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $minimalFile])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #2: 1 baris, 1 invalid.');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_staging_marks_missing_sap_invalid_but_keeps_optional_identifiers_optional(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $file = $this->employeeImportCsv([
            ['sap' => '', 'Personal Number' => 'PN-NO-SAP'],
            ['sap' => 'SAP-OPTIONAL-1'],
            ['sap' => 'SAP-OPTIONAL-2', 'E-mail' => 'optional@example.test'],
        ]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 3 baris, 1 invalid.');

        $rows = EmployeeImportRow::query()->orderBy('source_row')->get();
        $this->assertSame('INVALID', $rows[0]->validation_status);
        $this->assertContains('SAP is required.', $rows[0]->validation_errors);
        $this->assertSame('VALID', $rows[1]->validation_status);
        $this->assertNull($rows[1]->normalized_payload['personal_number']);
        $this->assertNull($rows[1]->normalized_payload['email']);
        $this->assertSame('VALID', $rows[2]->validation_status);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_import_preserves_excel_organization_columns_without_mapping_to_other_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'FIN', 'name' => 'Finance', 'active' => true]);
        $unit = $department->units()->create(['code' => 'FIN-OPS', 'name' => 'Finance Operations', 'active' => true]);
        $unit->positions()->create(['code' => 'FIN-ACC', 'name' => 'Accountant', 'active' => true]);
        $file = $this->employeeImportCsv([[
            'sap' => 'SAPMAP001', 'TXT_DEPT' => 'fINANCE',
            'Organizational Unit' => ' finance   operations ', 'Position' => 'ACCOUNTANT',
        ]]);

        $this->actingAs($user)->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 0 invalid.');

        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('fINANCE', $row->normalized_payload['txt_dept']);
        $this->assertSame('finance   operations', $row->normalized_payload['organizational_unit']);
        $this->assertSame('ACCOUNTANT', $row->normalized_payload['position']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAPMAP001']);

        $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $row), [
            'official_name' => 'Employee 001', 'name_source' => 'HR master record',
        ])->assertOk();
        $this->actingAs($user)->post(route('data-pegawai.import-batches.process', $row->batch))->assertOk();

        $employee = Employee::query()->where('sap', 'SAPMAP001')->firstOrFail();
        $this->assertNull($employee->department_id);
        $this->assertNull($employee->unit_id);
        $this->assertNull($employee->position_id);
        $this->assertSame('fINANCE', $employee->txt_dept);
        $this->assertSame('finance   operations', $employee->organizational_unit);
        $this->assertSame('ACCOUNTANT', $employee->position);
        $this->assertDatabaseCount('departments', 1);
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_import_keeps_unmapped_optional_organization_as_legacy_text_without_creating_master_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'OPS', 'name' => 'Operations', 'active' => true]);
        $unit = $department->units()->create(['code' => 'OPS-A', 'name' => 'Operations A', 'active' => true]);
        $unit->positions()->create(['code' => 'OPS-LEAD', 'name' => 'Team Lead', 'active' => true]);
        $file = $this->employeeImportCsv([
            ['sap' => 'SAPBAD001', 'TXT_DEPT' => 'Unknown Department'],
            ['sap' => 'SAPBAD002', 'TXT_DEPT' => 'Operations', 'Organizational Unit' => 'Operations A', 'Position' => 'Unknown Position'],
        ]);

        $this->actingAs($user)->from(route('data-pegawai'))->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 2 baris, 0 invalid.');

        $rows = EmployeeImportRow::query()->orderBy('source_row')->get();
        $this->assertSame('AUTO_CANDIDATE', $rows[0]->mapping_status);
        $this->assertSame('Unknown Department', $rows[0]->normalized_payload['txt_dept']);
        $this->assertSame('Operations', $rows[1]->normalized_payload['txt_dept']);
        $this->assertDatabaseCount('employees', 0);
        foreach ($rows as $index => $row) {
            $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $row))->assertOk();
        }
        $this->actingAs($user)->post(route('data-pegawai.import-batches.process', $rows[0]->batch))
            ->assertOk()->assertJsonPath('processed', 2);

        $this->assertDatabaseHas('employees', [
            'sap' => 'SAPBAD001', 'txt_dept' => 'Unknown Department',
            'department_id' => null, 'unit_id' => null, 'position_id' => null,
        ]);
        $this->assertDatabaseHas('employees', [
            'sap' => 'SAPBAD002', 'txt_dept' => 'Operations',
            'organizational_unit' => 'Operations A', 'position' => 'Unknown Position',
            'department_id' => null, 'unit_id' => null, 'position_id' => null,
        ]);
        $this->assertDatabaseCount('departments', 1);
        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_import_ignores_inconsistent_optional_parent_changes_and_keeps_existing_relations(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'DPT-OLD', 'name' => 'Old Department', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-OLD', 'name' => 'Old Unit', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-OLD', 'name' => 'Old Position', 'active' => true]);
        Department::create(['code' => 'DPT-NEW', 'name' => 'New Department', 'active' => true]);
        $employee = Employee::create([
            ...$this->employeeAttributes('SAPPART001'),
            'txt_dept' => 'Old Department',
            'organizational_unit' => 'Old Unit',
            'position' => 'Old Position',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);
        $file = $this->employeeImportCsv([[
            'sap' => 'SAPPART001', 'TXT_DEPT' => 'New Department', 'Position ID' => (string) $position->id,
        ]]);

        $this->actingAs($user)->from(route('data-pegawai'))->post(route('data-pegawai.import'), ['file' => $file])
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'File disimpan untuk review. Batch #1: 1 baris, 0 invalid.');

        $row = EmployeeImportRow::query()->firstOrFail();
        $this->assertSame('Old Department', $employee->fresh()->txt_dept);
        $this->assertSame('Old Unit', $employee->fresh()->organizational_unit);
        $this->assertSame('Old Position', $employee->fresh()->position);
        $this->actingAs($user)->post(route('data-pegawai.import-rows.approve', $row))->assertOk();
        $this->actingAs($user)->post(route('data-pegawai.import-batches.process', $row->batch))->assertOk();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => $employee->name,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'txt_dept' => 'Old Department',
            'txt_dept' => 'New Department',
            'position_id_source' => (string) $position->id,
        ]);
    }

    public function test_super_admin_can_delete_selected_employees(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $selected = Employee::create($this->employeeAttributes('SAP205'));
        $remaining = Employee::create($this->employeeAttributes('SAP206'));

        $this->actingAs($user)->delete(route('data-pegawai.destroy-many'), [
            'employee_ids' => [$selected->id],
        ])->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'Berhasil menghapus 1 data pegawai.');

        $this->assertDatabaseMissing('employees', ['id' => $selected->id]);
        $this->assertDatabaseHas('employees', ['id' => $remaining->id]);
    }

    public function test_employee_create_and_update_requests_validate_and_authorize_data(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $employeeAttributes = $this->employeeAttributes('SAP212');

        $this->actingAs($superAdmin)->post(route('data-pegawai.store'), $employeeAttributes)
            ->assertRedirect(route('data-pegawai'));

        $employee = Employee::query()->where('sap', 'SAP212')->firstOrFail();
        $this->actingAs($superAdmin)->put(route('data-pegawai.update', $employee), [
            ...$employeeAttributes,
        ])->assertRedirect(route('data-pegawai'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'sap' => 'SAP212',
        ]);
        $this->assertNull($employee->fresh()->getAttribute('name'));

        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->post(route('data-pegawai.store'), $this->employeeAttributes('SAP213'))
            ->assertForbidden();
        $this->actingAs($admin)->put(route('data-pegawai.update', $employee), [
            ...$employeeAttributes,
        ])->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP213']);
    }

    public function test_employee_can_be_created_and_updated_with_consistent_organization_ids(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $firstDepartment = Department::create(['code' => 'DPT-A', 'name' => 'Department A', 'active' => true]);
        $firstUnit = $firstDepartment->units()->create(['code' => 'UNT-A', 'name' => 'Unit A', 'active' => true]);
        $firstPosition = $firstUnit->positions()->create(['code' => 'POS-A', 'name' => 'Position A', 'active' => true]);
        $secondDepartment = Department::create(['code' => 'DPT-B', 'name' => 'Department B', 'active' => true]);
        $secondUnit = $secondDepartment->units()->create(['code' => 'UNT-B', 'name' => 'Unit B', 'active' => true]);
        $secondPosition = $secondUnit->positions()->create(['code' => 'POS-B', 'name' => 'Position B', 'active' => true]);

        $this->actingAs($user)->post(route('data-pegawai.store'), [
            ...$this->employeeAttributes('SAPFK001'),
            'department_id' => $firstDepartment->id,
            'unit_id' => $firstUnit->id,
            'position_id' => $firstPosition->id,
        ])->assertRedirect(route('data-pegawai'));

        $employee = Employee::query()->where('sap', 'SAPFK001')->firstOrFail();
        $this->assertSame($firstDepartment->id, $employee->department()->firstOrFail()->id);
        $this->assertSame($firstUnit->id, $employee->unit()->firstOrFail()->id);
        $this->assertSame($firstPosition->id, $employee->position()->firstOrFail()->id);
        $this->assertTrue($firstDepartment->employees()->whereKey($employee->id)->exists());
        $this->assertTrue($firstUnit->employees()->whereKey($employee->id)->exists());
        $this->assertTrue($firstPosition->employees()->whereKey($employee->id)->exists());
        $this->assertNull($employee->getAttribute('department'));
        $this->assertNull($employee->organizational_unit);
        $this->assertNull($employee->position);

        $this->actingAs($user)->put(route('data-pegawai.update', $employee), [
            ...$this->employeeAttributes('SAPFK001'),
            'department_id' => $secondDepartment->id,
            'unit_id' => $secondUnit->id,
            'position_id' => $secondPosition->id,
        ])->assertRedirect(route('data-pegawai'));

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $secondDepartment->id,
            'unit_id' => $secondUnit->id,
            'position_id' => $secondPosition->id,
        ]);
        $this->assertNull($employee->fresh()->organizational_unit);
        $this->assertNull($employee->fresh()->position);
    }

    public function test_employee_requests_reject_units_and_positions_outside_the_selected_parent(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $department = Department::create(['code' => 'DPT-C', 'name' => 'Department C', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-C', 'name' => 'Unit C', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-C', 'name' => 'Position C', 'active' => true]);
        $otherDepartment = Department::create(['code' => 'DPT-D', 'name' => 'Department D', 'active' => true]);
        $otherUnit = $otherDepartment->units()->create(['code' => 'UNT-D', 'name' => 'Unit D', 'active' => true]);
        $otherPosition = $otherUnit->positions()->create(['code' => 'POS-D', 'name' => 'Position D', 'active' => true]);

        $this->actingAs($user)->from(route('data-pegawai'))->post(route('data-pegawai.store'), [
            ...$this->employeeAttributes('SAPFK002'),
            'department_id' => $department->id,
            'unit_id' => $otherUnit->id,
            'position_id' => $position->id,
        ])->assertRedirect(route('data-pegawai'))
            ->assertSessionHasErrors('unit_id');

        $this->post(route('data-pegawai.store'), [
            ...$this->employeeAttributes('SAPFK003'),
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $otherPosition->id,
        ])->assertSessionHasErrors('position_id');

        $this->assertDatabaseMissing('employees', ['sap' => 'SAPFK002']);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAPFK003']);
    }

    public function test_employee_master_filters_are_scoped_by_department_unit_and_position(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['code' => 'DPT-E', 'name' => 'Department E', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-E', 'name' => 'Unit E', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-E', 'name' => 'Position E', 'active' => true]);
        $employee = Employee::create([
            ...$this->employeeAttributes('SAPFK004'),
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);
        Employee::create($this->employeeAttributes('SAPFK005'));
        $this->assertDatabaseHas('employees', [
            'sap' => 'SAPFK004',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);

        $response = $this->actingAs($user)->get(route('data-pegawai', [
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]))->assertOk();
        $this->assertSame(1, $response->viewData('employees')->total());
        $response
            ->assertSee('SAPFK004')
            ->assertDontSee('SAPFK005')
            ->assertViewHas('employees', fn ($employees): bool => $employees->total() === 1
                && $employees->first()->id === $employee->id);
    }

    public function test_deleting_a_position_nulls_only_its_employee_fk_and_keeps_legacy_text(): void
    {
        $department = Department::create(['code' => 'DPT-DEL', 'name' => 'Department Delete', 'active' => true]);
        $unit = $department->units()->create(['code' => 'UNT-DEL', 'name' => 'Unit Delete', 'active' => true]);
        $position = $unit->positions()->create(['code' => 'POS-DEL', 'name' => 'Position Delete', 'active' => true]);
        $employee = Employee::create([
            ...$this->employeeAttributes('SAPDEL001'),
            'txt_dept' => 'Legacy Department',
            'organizational_unit' => 'Legacy Unit',
            'position' => 'Legacy Position',
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => $position->id,
        ]);

        $position->delete();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $department->id,
            'unit_id' => $unit->id,
            'position_id' => null,
            'txt_dept' => 'Legacy Department',
            'organizational_unit' => 'Legacy Unit',
            'position' => 'Legacy Position',
        ]);
    }

    public function test_employee_export_uses_canonical_organization_fields(): void
    {
        $user = User::factory()->create();
        Employee::create($this->employeeAttributes('SAPXLSFK', [
            'txt_dept' => 'Source Department',
            'organizational_unit' => 'Source Unit',
            'position' => 'Source Position',
        ]));
        Employee::create($this->employeeAttributes('SAPXLSLEG', [
            'txt_dept' => 'Other Source Department',
            'organizational_unit' => 'Unmapped Unit',
            'position' => 'Unmapped Position',
        ]));

        $response = $this->actingAs($user)->get(route('data-pegawai.export'));
        $archive = new ZipArchive;
        $download = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $download);
        $downloadPath = $download->getFile()->getPathname();
        $this->assertTrue($archive->open($downloadPath) === true);
        $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        if (file_exists($downloadPath)) {
            unlink($downloadPath);
        }

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('TXT_DEPT', $worksheet);
        $this->assertStringContainsString('Source Department', $worksheet);
        $this->assertStringContainsString('Source Unit', $worksheet);
        $this->assertStringContainsString('Source Position', $worksheet);
        $this->assertStringContainsString('Other Source Department', $worksheet);
        $this->assertStringContainsString('Unmapped Unit', $worksheet);
        $this->assertStringContainsString('Unmapped Position', $worksheet);
    }

    public function test_super_admin_can_delete_all_employees_without_deleting_other_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        Employee::create($this->employeeAttributes('SAP210'));
        Employee::create($this->employeeAttributes('SAP211'));

        $this->actingAs($user)->delete(route('data-pegawai.destroy-all'))
            ->assertRedirect(route('data-pegawai'))
            ->assertSessionHas('status', 'Berhasil menghapus seluruh data pegawai (2 data).');

        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_non_super_admin_cannot_import_or_bulk_delete_employees(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $employee = Employee::create($this->employeeAttributes('SAP207'));
        $file = UploadedFile::fake()->createWithContent(
            'sap.csv',
            "SAP,Name\nSAP208,Data Pegawai\n",
        );

        $this->actingAs($admin)->post(route('data-pegawai.import'), ['file' => $file])->assertForbidden();
        $this->actingAs($admin)->delete(route('data-pegawai.destroy-many'), [
            'employee_ids' => [$employee->id],
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('data-pegawai.destroy-all'))->assertForbidden();

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseMissing('employees', ['sap' => 'SAP208']);
    }

    /** @return array<string, mixed> */
    private function employeeAttributes(string $sap, array $overrides = []): array
    {
        return array_merge([
            'sap' => $sap,
        ], $overrides);
    }

    /** @param array<int, array<string, string>> $rows */
    private function employeeImportCsv(array $rows, ?array $headers = null): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($this->employeeImportValues($rows, $headers) as $values) {
            fputcsv($handle, $values, ',', '"', '\\');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('employees.csv', $contents);
    }

    /** @param array<int, array<string, string>> $rows */
    private function employeeImportXlsx(array $rows, ?array $headers = null): UploadedFile
    {
        $filePath = tempnam(sys_get_temp_dir(), 'employee-import-');
        $archive = new ZipArchive;
        $archive->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $worksheetRows = [];

        foreach ($this->employeeImportValues($rows, $headers) as $rowIndex => $values) {
            $cells = [];
            foreach ($values as $columnIndex => $value) {
                $cellReference = $this->excelColumnName($columnIndex + 1).($rowIndex + 1);
                $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                if (in_array(strtoupper($value), ['#REF!', '#VALUE!', '#DIV/0!', '#N/A', '#NAME?', '#NUM!', '#NULL!'], true)) {
                    $cells[] = '<c r="'.$cellReference.'" t="e"><v>'.$escapedValue.'</v></c>';
                } else {
                    $cells[] = '<c r="'.$cellReference.'" t="inlineStr"><is><t xml:space="preserve">'.$escapedValue.'</t></is></c>';
                }
            }

            $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        $archive->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.
                implode('', $worksheetRows).
                '</sheetData></worksheet>',
        );
        $archive->close();
        register_shutdown_function(static fn () => @unlink($filePath));

        return new UploadedFile(
            $filePath,
            'employees.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /** @param array<int, array<string, string>> $rows @return array<int, array<int, string>> */
    private function employeeImportValues(array $rows, ?array $headers = null): array
    {
        $headers ??= self::EMPLOYEE_IMPORT_HEADERS;
        $values = [$headers];

        foreach ($rows as $row) {
            $values[] = array_map(function (string $header) use ($row): string {
                if ($header === 'SAP') {
                    return (string) ($row['SAP'] ?? $row['sap'] ?? '');
                }

                if ($header === 'Position id') {
                    return (string) ($row['Position id'] ?? $row['Position ID'] ?? '');
                }

                return (string) ($row[$header] ?? '');
            }, $headers);
        }

        return $values;
    }

    private function excelColumnName(int $columnNumber): string
    {
        $columnName = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $columnName = chr(65 + $remainder).$columnName;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $columnName;
    }
}
