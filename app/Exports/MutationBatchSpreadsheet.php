<?php

namespace App\Exports;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeDemotionBatch;
use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
use App\Models\EmployeePromotion;
use App\Models\EmployeePromotionBatch;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function download(
        EmployeeMutationBatch|EmployeePromotionBatch|EmployeeDemotionBatch $batch,
    ): BinaryFileResponse {
        return response()->download(
            $this->createTemporaryFile($batch),
            $this->downloadFilename($batch),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    public function createTemporaryFile(
        EmployeeMutationBatch|EmployeePromotionBatch|EmployeeDemotionBatch $batch,
    ): string {
        $templatePath = $this->templatePath ?? storage_path('template/mutasi-batch-template.xlsx');
        $movementLabel = $this->movementLabel($batch);
        $errorKey = strtolower($movementLabel).'_export';

        if (! is_file($templatePath)) {
            throw ValidationException::withMessages([
                $errorKey => "Template Excel {$movementLabel} tidak tersedia.",
            ]);
        }

        $movements = $this->movements($batch)
            ->with('employee:id,position_id_source')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => "Batch {$movementLabel} tidak memiliki pegawai untuk diekspor.",
            ]);
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), strtolower($movementLabel).'_batch_');

        if ($temporaryBase === false) {
            throw ValidationException::withMessages([
                $errorKey => "File sementara untuk ekspor {$movementLabel} tidak dapat dibuat.",
            ]);
        }

        $temporaryPath = $temporaryBase.'.xlsx';
        @unlink($temporaryBase);

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $this->populateWorkbook($spreadsheet, $movements);
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
            Log::error("Ekspor Excel batch {$movementLabel} gagal.", [
                'batch_id' => $batch->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $errorKey => "File Excel {$movementLabel} tidak dapat dibuat. Silakan coba kembali.",
            ]);
        }
    }

    public function downloadFilename(
        EmployeeMutationBatch|EmployeePromotionBatch|EmployeeDemotionBatch $batch,
    ): string {
        $movementLabel = $this->movementLabel($batch);
        $filename = preg_replace(
            '/[^\pL\pN._-]+/u',
            '-',
            $movementLabel.'-'.$batch->batch_number.'-'.$batch->name,
        );
        $filename = trim((string) $filename, '.-_');

        return ($filename !== '' ? $filename : $movementLabel.'-Batch').'.xlsx';
    }

    /** @param Collection<int, EmployeeMutation|EmployeePromotion|EmployeeDemotion> $movements */
    private function populateWorkbook(Spreadsheet $spreadsheet, Collection $movements): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        if (! $sheet instanceof Worksheet) {
            throw ValidationException::withMessages([
                'mutation_export' => 'Sheet template Excel Mutasi tidak tersedia.',
            ]);
        }

        $this->removeTemplateCalculations($sheet);
        $extraRows = max(0, $movements->count() - self::TEMPLATE_DATA_ROWS);

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

        $dataRowCount = max(self::TEMPLATE_DATA_ROWS, $movements->count());
        $dataEndRow = self::DATA_START_ROW + $dataRowCount - 1;

        for ($row = self::DATA_START_ROW; $row <= $dataEndRow; $row++) {
            foreach (range('B', 'T') as $column) {
                $this->writeText($sheet, "{$column}{$row}", '');
            }
        }

        foreach ($movements->values() as $index => $movement) {
            $row = self::DATA_START_ROW + $index;
            $values = [
                'B' => $index + 1,
                'C' => $movement->sap,
                'D' => $movement->nama,
                'E' => $movement->pg,
                'F' => $movement->departemen_lama,
                'G' => $movement->jabatan_lama,
                'H' => $movement->level_lama,
                'I' => $movement->jg_lama,
                'J' => $movement->departemen_baru,
                'K' => $movement->jabatan_baru,
                'L' => $movement->level_baru,
                'M' => $movement->jg_baru,
                'N' => '',
                'O' => $movement->tmt?->locale('id')->translatedFormat('d F Y') ?? '',
                'P' => '',
                'Q' => '',
                'R' => $movement->employee?->position_id_source,
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

    private function movementLabel(
        EmployeeMutationBatch|EmployeePromotionBatch|EmployeeDemotionBatch $batch,
    ): string {
        return match (true) {
            $batch instanceof EmployeePromotionBatch => 'Promosi',
            $batch instanceof EmployeeDemotionBatch => 'Demosi',
            default => 'Mutasi',
        };
    }

    private function movements(
        EmployeeMutationBatch|EmployeePromotionBatch|EmployeeDemotionBatch $batch,
    ): HasMany {
        return match (true) {
            $batch instanceof EmployeePromotionBatch => $batch->promotions(),
            $batch instanceof EmployeeDemotionBatch => $batch->demotions(),
            default => $batch->mutations(),
        };
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
