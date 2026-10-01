<?php

namespace App\Exports;

use App\Models\Employee;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

    public function download(): BinaryFileResponse
    {
        $employees = Employee::query()
            ->with(['organizationDepartment', 'unit', 'organizationPosition'])
            ->orderBy('sap')
            ->get();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'employee_export_');

        if ($temporaryPath === false) {
            abort(500, 'File sementara untuk ekspor tidak dapat dibuat.');
        }

        $archive = new ZipArchive;
        if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($temporaryPath);
            abort(500, 'File Excel tidak dapat dibuat.');
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $archive->addFromString('_rels/.rels', $this->packageRelationshipsXml());
        $archive->addFromString('xl/workbook.xml', $this->workbookXml());
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($employees));
        $archive->close();

        return response()->download(
            $temporaryPath,
            'data-pegawai-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '</Types>';
    }

    private function packageRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="Data Pegawai" sheetId="1" r:id="rId1"/></sheets>'.
            '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '</Relationships>';
    }

    /** @param Collection<int, Employee> $employees */
    private function worksheetXml($employees): string
    {
        $writer = new \XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8', 'yes');
        $writer->startElement('worksheet');
        $writer->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $writer->startElement('sheetData');

        $fields = array_merge(...array_values(self::FIELD_GROUPS));
        $rows = [array_keys($fields)];

        foreach ($employees as $employee) {
            $rows[] = array_map(
                fn (string $attribute): string => $this->exportValue($employee->getAttribute($attribute)),
                array_values($fields),
            );
        }

        foreach ($rows as $rowIndex => $values) {
            $rowNumber = $rowIndex + 1;
            $writer->startElement('row');
            $writer->writeAttribute('r', (string) $rowNumber);

            foreach ($values as $columnIndex => $value) {
                $writer->startElement('c');
                $writer->writeAttribute('r', $this->columnName($columnIndex + 1).$rowNumber);
                $writer->writeAttribute('t', 'inlineStr');
                $writer->startElement('is');
                $writer->startElement('t');
                $writer->writeAttribute('xml:space', 'preserve');
                $writer->text($value);
                $writer->endElement();
                $writer->endElement();
                $writer->endElement();
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function exportValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : (string) $value;
    }

    private function columnName(int $columnNumber): string
    {
        $name = '';

        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $name;
    }
}
