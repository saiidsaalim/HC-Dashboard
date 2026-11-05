<?php

namespace App\Exports;

use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MutationBatchSpreadsheet
{
    public const int DATA_START_ROW = 10;

    public const int TEMPLATE_DATA_ROWS = 12;

    public function __construct(private ?string $templatePath = null) {}

    public function download(EmployeeMutationBatch $batch): BinaryFileResponse
    {
        return response()->download(
            $this->createTemporaryFile($batch),
            $this->downloadFilename($batch),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    public function createTemporaryFile(EmployeeMutationBatch $batch): string
    {
        $templatePath = $this->templatePath ?? storage_path('template/mutasi-batch-template.xlsx');

        if (! is_file($templatePath)) {
            throw ValidationException::withMessages([
                'mutation_export' => 'Template Excel Mutasi tidak tersedia.',
            ]);
        }

        $mutations = $batch->mutations()
            ->with('employee:id,position_id_source')
            ->orderBy('id')
            ->get();

        if ($mutations->isEmpty()) {
            throw ValidationException::withMessages([
                'mutation_export' => 'Batch Mutasi tidak memiliki pegawai untuk diekspor.',
            ]);
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'mutation_batch_');

        if ($temporaryBase === false) {
            throw ValidationException::withMessages([
                'mutation_export' => 'File sementara untuk ekspor Mutasi tidak dapat dibuat.',
            ]);
        }

        $temporaryPath = $temporaryBase.'.xlsx';
        @unlink($temporaryBase);

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $this->populateWorkbook($spreadsheet, $mutations);
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
            Log::error('Ekspor Excel batch Mutasi gagal.', [
                'batch_id' => $batch->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'mutation_export' => 'File Excel Mutasi tidak dapat dibuat. Silakan coba kembali.',
            ]);
        }
    }

    public function downloadFilename(EmployeeMutationBatch $batch): string
    {
        $filename = preg_replace(
            '/[^\pL\pN._-]+/u',
            '-',
            'Mutasi-'.$batch->batch_number.'-'.$batch->name,
        );
        $filename = trim((string) $filename, '.-_');

        return ($filename !== '' ? $filename : 'Mutasi-Batch').'.xlsx';
    }

    /** @param Collection<int, EmployeeMutation> $mutations */
    private function populateWorkbook(Spreadsheet $spreadsheet, Collection $mutations): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        if (! $sheet instanceof Worksheet) {
            throw ValidationException::withMessages([
                'mutation_export' => 'Sheet template Excel Mutasi tidak tersedia.',
            ]);
        }

        $this->removeTemplateCalculations($sheet);
        $extraRows = max(0, $mutations->count() - self::TEMPLATE_DATA_ROWS);

        if ($extraRows > 0) {
            $insertAt = self::DATA_START_ROW + self::TEMPLATE_DATA_ROWS;
            $sheet->insertNewRowBefore($insertAt, $extraRows);

            for ($row = $insertAt; $row < $insertAt + $extraRows; $row++) {
                $sheet->duplicateStyle(
                    $sheet->getStyle('A'.($insertAt - 1).':W'.($insertAt - 1)),
                    "A{$row}:W{$row}",
                );
                $sheet->getRowDimension($row)
                    ->setRowHeight($sheet->getRowDimension($insertAt - 1)->getRowHeight())
                    ->setVisible(true);
            }
        }

        $dataRowCount = max(self::TEMPLATE_DATA_ROWS, $mutations->count());
        $dataEndRow = self::DATA_START_ROW + $dataRowCount - 1;

        for ($row = self::DATA_START_ROW; $row <= $dataEndRow; $row++) {
            foreach (range('B', 'T') as $column) {
                $this->writeText($sheet, "{$column}{$row}", '');
            }
        }

        foreach ($mutations->values() as $index => $mutation) {
            $row = self::DATA_START_ROW + $index;
            $values = [
                'B' => $index + 1,
                'C' => $mutation->sap,
                'D' => $mutation->nama,
                'E' => $mutation->pg,
                'F' => $mutation->departemen_lama,
                'G' => $mutation->jabatan_lama,
                'H' => $mutation->level_lama,
                'I' => $mutation->jg_lama,
                'J' => $mutation->departemen_baru,
                'K' => $mutation->jabatan_baru,
                'L' => $mutation->level_baru,
                'M' => $mutation->jg_baru,
                'N' => '',
                'O' => $mutation->tmt?->locale('id')->translatedFormat('d F Y') ?? '',
                'P' => '',
                'Q' => '',
                'R' => $mutation->employee?->position_id_source,
                'S' => '',
                'T' => '',
            ];

            foreach ($values as $column => $value) {
                $this->writeText($sheet, "{$column}{$row}", $value);
            }
        }

        $sheet->garbageCollect();
        $spreadsheet->setActiveSheetIndex(0);
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

    private function writeText(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->getCell($coordinate)->setValueExplicit(
            $this->safeText($value),
            DataType::TYPE_STRING,
        );
    }

    private function safeText(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
