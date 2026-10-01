<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $normalizedRows = $this->normalizeExistingValues();

        DB::transaction(function () use ($normalizedRows): void {
            foreach ($normalizedRows as $employeeId => $values) {
                DB::table('employees')->where('id', $employeeId)->update($values);
            }
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->date('masa_kontrak')->nullable()->change();
            $table->date('organilk')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('masa_kontrak', 100)->nullable()->change();
            $table->string('organilk', 100)->nullable()->change();
        });
    }

    /** @return array<int, array{masa_kontrak: ?string, organilk: ?string}> */
    private function normalizeExistingValues(): array
    {
        foreach (['masa_kontrak', 'organilk'] as $column) {
            if (! Schema::hasColumn('employees', $column)) {
                throw new RuntimeException("Cannot convert employees.{$column}: column is missing.");
            }
        }

        $normalizedRows = [];

        foreach (DB::table('employees')->select(['id', 'masa_kontrak', 'organilk'])->orderBy('id')->get() as $employee) {
            $values = [];

            foreach (['masa_kontrak', 'organilk'] as $column) {
                $value = trim((string) ($employee->{$column} ?? ''));
                $values[$column] = $value === '' ? null : $this->parseDate($value, $column, (int) $employee->id);
            }

            $normalizedRows[(int) $employee->id] = $values;
        }

        return $normalizedRows;
    }

    private function parseDate(string $value, string $column, int $employeeId): string
    {
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial < 1 || $serial > 2958465) {
                throw new RuntimeException("Cannot convert employees.{$column} for employee id {$employeeId}: invalid Excel date serial.");
            }

            return Carbon::create(1899, 12, 30)->addDays((int) $serial)->toDateString();
        }

        foreach (['!Y-m-d', '!d-m-Y', '!j-n-Y', '!d.m.Y', '!j.n.Y', '!d/m/Y', '!j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date->toDateString();
            }
        }

        throw new RuntimeException("Cannot convert employees.{$column} for employee id {$employeeId}: value is not a valid date.");
    }
};
