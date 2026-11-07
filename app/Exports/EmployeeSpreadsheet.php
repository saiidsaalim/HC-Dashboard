<?php

namespace App\Exports;

use App\Models\Employee;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeSpreadsheet
{
    /** @var array<string, array<string, string>> */
    public const FIELD_GROUPS = [
        'Identitas Pegawai' => [
            'SAP' => 'sap',
            'ID Number' => 'id_number',
            'AGKN' => 'agkn',
            'Personal Number' => 'personal_number',
        ],
        'Informasi Organisasi' => [
            'Position id' => 'position_id_source',
            'Position' => 'position',
            'Employee Subgroup' => 'employee_subgroup',
            'Cost Ctr' => 'cost_ctr',
            'TXT_DIR' => 'txt_dir',
            'TXT_DEPT' => 'txt_dept',
            'TXT_BIRO' => 'txt_biro',
            'TXT_SECT' => 'txt_sect',
            'Personnel Area' => 'personnel_area',
            'abrevation position' => 'abrevation_position',
            'abrevation organization' => 'abrevation_organization',
            'Organizational Unit' => 'organizational_unit',
            'Cost Center' => 'cost_center',
            'Masa Kontrak' => 'masa_kontrak',
        ],
        'Data Pribadi' => [
            'Birth date' => 'birth_date',
            'Gender Key' => 'gender_key',
            'Religious' => 'religious',
            'Usia' => 'usia',
            'Tempat Lahir' => 'tempat_lahir',
            'Pendidikan' => 'pendidikan',
        ],
        'Kontak dan Masa Kerja' => [
            'E-mail' => 'email',
            'Hiring' => 'hiring',
            'Organilk' => 'organilk',
            'Alamat' => 'alamat',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const PERFORMANCE_TALENT_FIELD_GROUPS = [
        'Succession Planning' => [
            'Talent Mapping' => 'talent_mapping',
            'Succession Readiness' => 'succession_readiness',
            'IDP (%)' => 'idp_percentage',
        ],
    ];

    private const VALIDATION_LAST_ROW = 10000;

    /** @return array<string, array<string, string>> */
    public static function fieldGroups(bool $includePerformanceTalentFields = false): array
    {
        return $includePerformanceTalentFields
            ? [...self::FIELD_GROUPS, ...self::PERFORMANCE_TALENT_FIELD_GROUPS]
            : self::FIELD_GROUPS;
    }

    public function download(bool $includePerformanceTalentFields = false): BinaryFileResponse
    {
        $employees = Employee::query()
            ->select(array_values(array_merge(...array_values(self::fieldGroups($includePerformanceTalentFields)))))
            ->orderBy('sap')
            ->lazyById(500, 'sap');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'employee_export_');

        if ($temporaryPath === false) {
            abort(500, 'File sementara untuk ekspor tidak dapat dibuat.');
        }

        $spreadsheet = $this->workbook($employees, $includePerformanceTalentFields);
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download(
            $temporaryPath,
            'data-pegawai-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    /** @param iterable<int, Employee> $employees */
    private function workbook(iterable $employees, bool $includePerformanceTalentFields): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Data Pegawai');
        $fields = array_merge(...array_values(self::fieldGroups($includePerformanceTalentFields)));

        foreach (array_keys($fields) as $columnIndex => $header) {
            $dataSheet->setCellValueExplicit(
                [$columnIndex + 1, 1],
                $header,
                DataType::TYPE_STRING,
            );
        }

        foreach ($employees as $employeeIndex => $employee) {
            $row = $employeeIndex + 2;

            foreach (array_values($fields) as $columnIndex => $attribute) {
                $value = $employee->getAttribute($attribute);
                $coordinate = [$columnIndex + 1, $row];

                if ($value === null) {
                    $dataSheet->setCellValue($coordinate, null);
                } elseif ($attribute === 'talent_mapping') {
                    $dataSheet->setCellValue($coordinate, (int) $value);
                } elseif ($attribute === 'idp_percentage') {
                    $dataSheet->setCellValue($coordinate, (float) $value);
                } else {
                    $dataSheet->setCellValueExplicit(
                        $coordinate,
                        $this->exportValue($value),
                        DataType::TYPE_STRING,
                    );
                }
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($fields));
        $dataSheet->freezePane('A2');
        $dataSheet->setAutoFilter("A1:{$lastColumn}1");
        $dataSheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

        if ($includePerformanceTalentFields) {
            $this->addValidationLists($spreadsheet);
            $this->addListValidation($dataSheet, $fields, 'Talent Mapping', 'TalentMappingOptions');
            $this->addListValidation($dataSheet, $fields, 'Succession Readiness', 'SuccessionReadinessOptions');
            $this->addIdpValidation($dataSheet, $fields);
        }

        return $spreadsheet;
    }

    private function exportValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : (string) $value;
    }

    private function addValidationLists(Spreadsheet $spreadsheet): void
    {
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Dropdown Lists');

        $lists = [
            'TalentMappingOptions' => ['A', Employee::TALENT_MAPPING_OPTIONS],
            'SuccessionReadinessOptions' => ['B', Employee::SUCCESSION_READINESS_OPTIONS],
        ];

        foreach ($lists as $name => [$column, $options]) {
            foreach ($options as $index => $option) {
                $listSheet->setCellValue("{$column}".($index + 1), $option);
            }

            $spreadsheet->addNamedRange(new NamedRange(
                $name,
                $listSheet,
                "\${$column}\$1:\${$column}\$".count($options),
            ));
        }

        $listSheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
    }

    /** @param array<string, string> $fields */
    private function addListValidation(
        Worksheet $sheet,
        array $fields,
        string $header,
        string $namedRange,
    ): void {
        $column = $this->columnForHeader($fields, $header);
        $validation = (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Nilai tidak valid')
            ->setError("Pilih nilai {$header} dari daftar yang tersedia.")
            ->setFormula1($namedRange);

        $sheet->setDataValidation("{$column}2:{$column}".self::VALIDATION_LAST_ROW, $validation);
    }

    /** @param array<string, string> $fields */
    private function addIdpValidation(Worksheet $sheet, array $fields): void
    {
        $column = $this->columnForHeader($fields, 'IDP (%)');
        $validation = (new DataValidation)
            ->setType(DataValidation::TYPE_DECIMAL)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('IDP tidak valid')
            ->setError('Masukkan angka IDP antara 0 dan 100.')
            ->setFormula1('0')
            ->setFormula2('100');

        $range = "{$column}2:{$column}".self::VALIDATION_LAST_ROW;
        $sheet->setDataValidation($range, $validation);
    }

    /** @param array<string, string> $fields */
    private function columnForHeader(array $fields, string $header): string
    {
        $index = array_search($header, array_keys($fields), true);

        if ($index === false) {
            throw new \LogicException("Header {$header} is not configured.");
        }

        return Coordinate::stringFromColumnIndex($index + 1);
    }
}
