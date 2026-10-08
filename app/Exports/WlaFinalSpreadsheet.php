<?php

namespace App\Exports;

use App\Models\WlaAssessment;
use App\Services\Workforce\WlaFinalSnapshotService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class WlaFinalSpreadsheet
{
    public const string TEMPLATE_SHEET = 'A. Form WLA';

    public const int ACTIVITY_START_ROW = 40;

    public const int TEMPLATE_ACTIVITY_ROWS = 20;

    public function __construct(
        private WlaFinalSnapshotService $snapshotService,
        private ?string $templatePath = null,
    ) {}

    public function download(WlaAssessment $assessment): BinaryFileResponse
    {
        $temporaryPath = $this->createTemporaryFile($assessment);

        return response()->download(
            $temporaryPath,
            $this->downloadFilename($assessment),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    public function createTemporaryFile(WlaAssessment $assessment): string
    {
        $snapshot = $this->snapshotService->validatedSnapshot($assessment);
        $templatePath = $this->templatePath ?? storage_path('template/wla-template.xlsx');

        if (! is_file($templatePath)) {
            throw ValidationException::withMessages([
                'wla_export' => 'Template Excel WLA tidak tersedia.',
            ]);
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'wla_final_');

        if ($temporaryBase === false) {
            throw ValidationException::withMessages([
                'wla_export' => 'File sementara untuk ekspor WLA tidak dapat dibuat.',
            ]);
        }

        $temporaryPath = $temporaryBase.'.xlsx';
        @unlink($temporaryBase);

        try {
            $sourceWorkbook = IOFactory::load($templatePath);
            $spreadsheet = $this->cleanTemplateWorkbook($sourceWorkbook);
            $sourceWorkbook->disconnectWorksheets();
            $this->populateWorkbook($spreadsheet, $snapshot);
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($temporaryPath);
            $spreadsheet->disconnectWorksheets();

            if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                throw new \RuntimeException('Writer tidak menghasilkan file XLSX yang valid.');
            }

            return $temporaryPath;
        } catch (ValidationException $exception) {
            @unlink($temporaryPath);

            throw $exception;
        } catch (Throwable $exception) {
            @unlink($temporaryPath);
            Log::error('Ekspor Excel WLA Final gagal.', [
                'assessment_id' => $assessment->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'wla_export' => 'File Excel WLA tidak dapat dibuat. Silakan coba kembali.',
            ]);
        }
    }

    public function downloadFilename(WlaAssessment $assessment): string
    {
        $snapshot = $this->snapshotService->validatedSnapshot($assessment);
        $filename = implode('-', [
            'WLA',
            data_get($snapshot, 'period'),
            data_get($snapshot, 'position.code'),
            data_get($snapshot, 'assessment_code'),
        ]);
        $filename = preg_replace('/[^\pL\pN._-]+/u', '-', (string) $filename);
        $filename = trim((string) $filename, '.-_');

        return ($filename !== '' ? $filename : 'WLA-Final').'.xlsx';
    }

    /** @param array<string, mixed> $snapshot */
    private function populateWorkbook(Spreadsheet $spreadsheet, array $snapshot): void
    {
        $sheet = $spreadsheet->getSheetByName(self::TEMPLATE_SHEET);

        if (! $sheet instanceof Worksheet) {
            throw ValidationException::withMessages([
                'wla_export' => 'Sheet template WLA tidak ditemukan.',
            ]);
        }

        $sheet->setTitle($this->sheetTitle($snapshot));
        $this->removeTemplateCalculations($sheet);
        $this->writeIdentity($sheet, $snapshot);
        $this->writeCalendar($sheet, $snapshot);
        $lastRow = $this->writeActivitiesAndSummary($sheet, $snapshot);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea("A12:N{$lastRow}");
        $sheet->getPageMargins()
            ->setLeft(0.039370078740157)
            ->setRight(0.039370078740157)
            ->setTop(0.98425196850394)
            ->setBottom(0.98425196850394);
        $sheet->getSheetView()->setView('pageLayout');
        $sheet->garbageCollect();
        $spreadsheet->setActiveSheetIndex(0);
    }

    private function cleanTemplateWorkbook(Spreadsheet $sourceWorkbook): Spreadsheet
    {
        $sourceSheet = $sourceWorkbook->getSheetByName(self::TEMPLATE_SHEET);

        if (! $sourceSheet instanceof Worksheet) {
            throw ValidationException::withMessages([
                'wla_export' => 'Sheet template WLA tidak ditemukan.',
            ]);
        }

        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle(self::TEMPLATE_SHEET);

        for ($row = 1; $row <= 69; $row++) {
            $sourceRow = $sourceSheet->getRowDimension($row);
            $sheet->getRowDimension($row)
                ->setRowHeight($sourceRow->getRowHeight())
                ->setVisible($sourceRow->getVisible())
                ->setCollapsed($sourceRow->getCollapsed())
                ->setOutlineLevel($sourceRow->getOutlineLevel());

            for ($column = 1; $column <= 14; $column++) {
                $columnName = Coordinate::stringFromColumnIndex($column);
                $coordinate = "{$columnName}{$row}";
                $sourceCell = $sourceSheet->getCell($coordinate);
                $sheet->getCell($coordinate)->setValueExplicit(
                    $sourceCell->getValue(),
                    $sourceCell->getDataType(),
                );
                $sheet->getStyle($coordinate)->applyFromArray(
                    $sourceSheet->getStyle($coordinate)->exportArray(),
                );
            }
        }

        for ($column = 1; $column <= 14; $column++) {
            $columnName = Coordinate::stringFromColumnIndex($column);
            $sourceColumn = $sourceSheet->getColumnDimension($columnName);
            $sheet->getColumnDimension($columnName)
                ->setWidth($sourceColumn->getWidth())
                ->setVisible($sourceColumn->getVisible())
                ->setCollapsed($sourceColumn->getCollapsed())
                ->setOutlineLevel($sourceColumn->getOutlineLevel());
        }

        foreach ($sourceSheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);

            if ($start[0] <= 14 && $end[0] <= 14 && $start[1] <= 69 && $end[1] <= 69) {
                $sheet->mergeCells($range);
            }
        }

        $sheet->setPageSetup(clone $sourceSheet->getPageSetup());
        $sheet->setPageMargins(clone $sourceSheet->getPageMargins());
        $sheet->setHeaderFooter(clone $sourceSheet->getHeaderFooter());
        $sheet->setSheetView(clone $sourceSheet->getSheetView());
        $sheet->freezePane($sourceSheet->getFreezePane());

        return $workbook;
    }

    private function removeTemplateCalculations(Worksheet $sheet): void
    {
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);

            if ($cell->isFormula()) {
                $cell->setValueExplicit('', DataType::TYPE_STRING);
            }
        }

        foreach (array_keys($sheet->getDataValidationCollection()) as $coordinate) {
            $sheet->setDataValidation($coordinate);
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function writeIdentity(Worksheet $sheet, array $snapshot): void
    {
        $this->writeText($sheet, 'A12', 'ANALISIS BEBAN KERJA (WORK LOAD ANALYSIS)');
        $this->writeText($sheet, 'A13', 'Nama Posisi:');
        $this->writeText($sheet, 'B13', $this->identity($snapshot, 'position'));
        $this->writeText($sheet, 'A14', 'Departemen:');
        $this->writeText($sheet, 'B14', $this->identity($snapshot, 'department'));
        $this->writeText($sheet, 'A15', 'Unit:');
        $this->writeText($sheet, 'B15', $this->identity($snapshot, 'unit'));
        $this->writeText($sheet, 'A16', 'Kode / Periode:');
        $this->writeText($sheet, 'B16', data_get($snapshot, 'assessment_code').' / '.data_get($snapshot, 'period'));
        $this->writeText($sheet, 'F12', 'Jadwal WLA');
        $this->writeText($sheet, 'G12', data_get($snapshot, 'schedule.name'));
        $this->writeText($sheet, 'G13', data_get($snapshot, 'schedule.calculation_type_label'));
    }

    /** @param array<string, mixed> $snapshot */
    private function writeCalendar(Worksheet $sheet, array $snapshot): void
    {
        $rows = [
            19 => ['Jam kerja WLA dalam 1 hari', data_get($snapshot, 'schedule.wla_hours_per_day'), 'Jam/Hari'],
            20 => ['Kelompok kalkulasi jadwal', data_get($snapshot, 'schedule.calculation_type_label'), 'Kelompok'],
            21 => ['Total hari dalam tahun', data_get($snapshot, 'calendar.total_days'), 'Hari'],
            22 => ['Total minggu dalam tahun', '52', 'Minggu'],
            23 => ['Cuti tahunan', data_get($snapshot, 'calendar.annual_leave'), 'Hari'],
            24 => ['Libur nasional', data_get($snapshot, 'calendar.national_holiday'), 'Hari'],
            25 => ['Cuti bersama', data_get($snapshot, 'calendar.common_leave'), 'Hari'],
            26 => ['Jumlah hari Minggu', data_get($snapshot, 'calendar.sunday_days'), 'Hari'],
            27 => ['Jumlah hari Sabtu', data_get($snapshot, 'calendar.saturday_days'), 'Hari'],
            28 => ['Hari kerja dalam '.data_get($snapshot, 'period'), data_get($snapshot, 'working_days'), 'Hari'],
            29 => ['Jam kerja dalam '.data_get($snapshot, 'period'), data_get($snapshot, 'annual_working_hours'), 'Jam/Tahun'],
            30 => ['Faktor efisiensi rata-rata', data_get($snapshot, 'efficiency_factor'), 'Faktor'],
            31 => ['Total jam kerja efektif', data_get($snapshot, 'effective_annual_working_hours'), 'Jam/Tahun'],
        ];

        foreach ($rows as $row => [$label, $value, $unit]) {
            $this->writeText($sheet, "A{$row}", (string) ($row - 18));
            $this->writeText($sheet, "B{$row}", (string) $label);
            $this->writeText($sheet, "C{$row}", $value);
            $this->writeText($sheet, "D{$row}", (string) $unit);
        }

        foreach (range(32, 34) as $row) {
            $sheet->getCell("C{$row}")->setValueExplicit('', DataType::TYPE_STRING);
            $sheet->getCell("D{$row}")->setValueExplicit('', DataType::TYPE_STRING);
        }

        $this->writeText($sheet, 'A36', 'Nama Posisi:');
        $this->writeText($sheet, 'B36', $this->identity($snapshot, 'position'));
    }

    /** @param array<string, mixed> $snapshot */
    private function writeActivitiesAndSummary(Worksheet $sheet, array $snapshot): int
    {
        $activities = array_values($snapshot['activities']);
        $extraRows = max(0, count($activities) - self::TEMPLATE_ACTIVITY_ROWS);

        if ($extraRows > 0) {
            $sheet->insertNewRowBefore(60, $extraRows);

            for ($row = 60; $row < 60 + $extraRows; $row++) {
                foreach (range(1, 14) as $column) {
                    $columnName = Coordinate::stringFromColumnIndex($column);
                    $sheet->duplicateStyle($sheet->getStyle("{$columnName}59"), "{$columnName}{$row}");
                }

                $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension(59)->getRowHeight());
            }
        }

        $activityEndRow = self::ACTIVITY_START_ROW + max(self::TEMPLATE_ACTIVITY_ROWS, count($activities)) - 1;
        $summaryStartRow = $activityEndRow + 2;
        $summaryEndRow = $summaryStartRow + 5;

        $this->writeText($sheet, 'A37', 'No');
        $this->writeText($sheet, 'B37', 'Aktivitas');
        $this->writeText($sheet, 'C37', 'Frekuensi');
        $this->writeText($sheet, 'D37', 'Periode');
        $this->writeText($sheet, 'E37', 'Waktu per Aktivitas');
        $this->writeText($sheet, 'E38', '(Jam)');
        $this->writeText($sheet, 'M37', 'Beban Tahunan');
        $this->writeText($sheet, 'M38', '(Jam)');

        for ($row = self::ACTIVITY_START_ROW; $row <= $activityEndRow; $row++) {
            foreach (range('A', 'N') as $column) {
                $sheet->getCell("{$column}{$row}")->setValueExplicit('', DataType::TYPE_STRING);
            }
        }

        foreach ($activities as $index => $activity) {
            $row = self::ACTIVITY_START_ROW + $index;
            $this->writeText($sheet, "A{$row}", (string) ($index + 1));
            $this->writeText($sheet, "B{$row}", $activity['activity_name']);
            $this->writeText($sheet, "C{$row}", $activity['frequency']);
            $this->writeText($sheet, "D{$row}", $this->periodLabel((string) $activity['period_unit']));
            $this->writeText($sheet, "E{$row}", $activity['time_allocated_hours']);
            $this->writeText($sheet, "M{$row}", $activity['annual_workload_hours']);
            $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);

            if ($start[1] >= $summaryStartRow || $end[1] >= $summaryStartRow) {
                $sheet->unmergeCells($range);
            }
        }

        $sheet->getStyle("A{$summaryStartRow}:N{$summaryEndRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $summaryRows = [
            $summaryStartRow => ['Total Beban Kerja Tahunan', data_get($snapshot, 'total_annual_workload').' jam'],
            $summaryStartRow + 1 => ['FTE', data_get($snapshot, 'fte')],
            $summaryStartRow + 2 => ['Rekomendasi Jumlah Pegawai', data_get($snapshot, 'recommended_employees')],
            $summaryStartRow + 3 => ['Difinalisasi Oleh', data_get($snapshot, 'finalizer.name').' (ID '.data_get($snapshot, 'finalizer.id').')'],
            $summaryStartRow + 4 => ['Waktu Finalisasi', data_get($snapshot, 'finalized_at')],
            $summaryStartRow + 5 => ['Status', 'FINAL - HASIL DIBEKUKAN'],
        ];

        foreach ($summaryRows as $row => [$label, $value]) {
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->mergeCells("M{$row}:N{$row}");
            $this->writeText($sheet, "A{$row}", (string) $label);
            $this->writeText($sheet, "M{$row}", $value);
        }

        $sheet->getStyle("A{$summaryStartRow}:N".($summaryStartRow + 2))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2F0D9');
        $sheet->getStyle("A{$summaryStartRow}:N{$summaryEndRow}")->getFont()->setBold(true);

        return $summaryEndRow;
    }

    /** @param array<string, mixed> $snapshot */
    private function sheetTitle(array $snapshot): string
    {
        $title = data_get($snapshot, 'position.code').' '.data_get($snapshot, 'period');
        $title = str_replace(Worksheet::getInvalidCharacters(), '-', (string) $title);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        return mb_substr($title !== '' ? $title : 'WLA Final', 0, 31);
    }

    /** @param array<string, mixed> $snapshot */
    private function identity(array $snapshot, string $key): string
    {
        return trim(data_get($snapshot, "{$key}.code").' - '.data_get($snapshot, "{$key}.name"), ' -');
    }

    private function writeText(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->getCell($coordinate)->setValueExplicit(
            $this->safeText($value),
            DataType::TYPE_STRING,
        );
    }

    private function safeText(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function periodLabel(string $periodUnit): string
    {
        return match ($periodUnit) {
            'Day' => 'Hari',
            'Week' => 'Minggu',
            'Month' => 'Bulan',
            'Year' => 'Tahun',
            default => $periodUnit,
        };
    }
}
