<?php

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
        $this->assertSapDataIsCompatible();
        $this->ensureCanonicalEmployeeColumns();
        $this->copyLegacyEmployeeValues();

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('sap', 50)->nullable(false)->change();
            $table->string('id_number', 100)->nullable()->change();
            $table->string('agkn', 100)->nullable()->change();
            $table->string('position', 255)->nullable()->change();
            $table->string('position_id_source', 100)->nullable()->change();
            $table->string('personal_number', 100)->nullable()->change();
            $table->string('employee_subgroup', 100)->nullable()->change();
            $table->string('cost_ctr', 100)->nullable()->change();
            $table->string('txt_dir', 255)->nullable()->change();
            $table->string('txt_dept', 255)->nullable()->change();
            $table->string('txt_biro', 255)->nullable()->change();
            $table->string('txt_sect', 255)->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('gender_key', 20)->nullable()->change();
            $table->string('personnel_area', 100)->nullable()->change();
            $table->string('abrevation_position', 100)->nullable()->change();
            $table->string('abrevation_organization', 100)->nullable()->change();
            $table->string('organizational_unit', 255)->nullable()->change();
            $table->string('cost_center', 100)->nullable()->change();
            $table->string('masa_kontrak', 100)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
            $table->string('religious', 100)->nullable()->change();
            $table->integer('usia')->nullable()->change();
            $table->string('tempat_lahir', 255)->nullable()->change();
            $table->string('pendidikan', 255)->nullable()->change();
            $table->date('hiring')->nullable()->change();
            $table->string('organilk', 100)->nullable()->change();
            $table->text('alamat')->nullable()->change();
        });

        if (in_array('name', Schema::getColumnListing('employees'), true)) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('name')->nullable()->change();
            });
        }

        $this->ensureSapUniqueIndex();
        $this->addPromotionAndDemotionSapForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Canonical Employee migration is non-destructive and cannot be rolled back automatically.');
    }

    private function assertSapDataIsCompatible(): void
    {
        $this->assertColumnLength('employees', 'sap', 50);

        if (DB::table('employees')->whereNull('sap')->orWhereRaw('TRIM(sap) = ?', [''])->exists()) {
            throw new RuntimeException('Cannot enforce the SAP identifier contract: blank SAP exists in employees.');
        }

        if (DB::table('employees')->select('sap')->groupBy('sap')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot enforce the SAP identifier contract: duplicate SAP exists in employees.');
        }

        foreach ([
            'id_number' => 100, 'ID Number' => 100, 'agkn' => 100,
            'Position ID' => 100, 'position_id_source' => 100,
            'Personal Number' => 100, 'personal_number' => 100,
            'Position' => 255, 'position' => 255,
            'Employee Subgroup' => 100, 'employee_subgroup' => 100,
            'Cost Ctr' => 100, 'cost_ctr' => 100,
            'TXT_DIR' => 255, 'directorate' => 255, 'txt_dir' => 255,
            'TXT_DEPT' => 255, 'department' => 255, 'txt_dept' => 255,
            'TXT_BIRO' => 255, 'bureau' => 255, 'txt_biro' => 255,
            'TXT_SECT' => 255, 'section' => 255, 'txt_sect' => 255,
            'Gender Key' => 20, 'gender_key' => 20,
            'Personnel Area' => 100, 'personnel_area' => 100,
            'abrevation position' => 100, 'abrevation_position' => 100,
            'abrevation organization' => 100, 'abrevation_organization' => 100,
            'Organizational Unit' => 255, 'organizational_unit' => 255,
            'Cost Center' => 100, 'cost_center' => 100,
            'E-mail' => 255, 'email' => 255,
            'Religious' => 100, 'religion' => 100, 'religious' => 100,
            'Tempat Lahir' => 255, 'birth_place' => 255, 'tempat_lahir' => 255,
            'Pendidikan' => 255, 'education' => 255, 'pendidikan' => 255,
            'Organilk' => 100, 'organic_status' => 100, 'organilk' => 100,
        ] as $column => $length) {
            $this->assertColumnLength('employees', $column, $length);
        }

        foreach (['age', 'Usia', 'usia'] as $column) {
            if ($this->hasExactEmployeeColumn($column) && DB::table('employees')->where($column, '>', 2147483647)->exists()) {
                throw new RuntimeException("Cannot convert {$column} to signed INT: values exceed the supported range.");
            }
        }

        foreach (['employee_promotions', 'employee_demotions'] as $table) {
            $this->assertColumnLength($table, 'sap', 50);

            if (DB::table($table)->whereNull('sap')->orWhereRaw('TRIM(sap) = ?', [''])->exists()) {
                throw new RuntimeException("Cannot add {$table}.sap foreign key: blank SAP exists.");
            }

            if (DB::table($table)
                ->leftJoin('employees', 'employees.sap', '=', "{$table}.sap")
                ->whereNull('employees.id')
                ->exists()) {
                throw new RuntimeException("Cannot add {$table}.sap foreign key: orphan SAP values exist.");
            }
        }
    }

    private function ensureCanonicalEmployeeColumns(): void
    {
        foreach ([
            'TXT_DIR' => 'txt_dir',
            'TXT_DEPT' => 'txt_dept',
            'TXT_BIRO' => 'txt_biro',
            'TXT_SECT' => 'txt_sect',
            'Religious' => 'religious',
            'Usia' => 'usia',
            'Pendidikan' => 'pendidikan',
            'Hiring' => 'hiring',
            'Organilk' => 'organilk',
            'Alamat' => 'alamat',
        ] as $source => $target) {
            if ($this->hasExactEmployeeColumn($source) && ! $this->hasExactEmployeeColumn($target)) {
                $this->renameEmployeeColumn($source, $target);
            }
        }

        $columns = Schema::getColumnListing('employees');

        Schema::table('employees', function (Blueprint $table) use ($columns): void {
            if (! in_array('id_number', $columns, true)) {
                $table->string('id_number', 100)->nullable();
            }
            if (! in_array('agkn', $columns, true)) {
                $table->string('agkn', 100)->nullable();
            }
            if (! in_array('position_id_source', $columns, true)) {
                $table->string('position_id_source', 100)->nullable();
            }
            if (! in_array('personal_number', $columns, true)) {
                $table->string('personal_number', 100)->nullable();
            }
            if (! in_array('position', $columns, true)) {
                $table->string('position', 255)->nullable();
            }
            if (! in_array('employee_subgroup', $columns, true)) {
                $table->string('employee_subgroup', 100)->nullable();
            }
            if (! in_array('cost_ctr', $columns, true)) {
                $table->string('cost_ctr', 100)->nullable();
            }
            if (! in_array('txt_dir', $columns, true)) {
                $table->string('txt_dir', 255)->nullable();
            }
            if (! in_array('txt_dept', $columns, true)) {
                $table->string('txt_dept', 255)->nullable();
            }
            if (! in_array('txt_biro', $columns, true)) {
                $table->string('txt_biro', 255)->nullable();
            }
            if (! in_array('txt_sect', $columns, true)) {
                $table->string('txt_sect', 255)->nullable();
            }
            if (! in_array('birth_date', $columns, true)) {
                $table->date('birth_date')->nullable();
            }
            if (! in_array('gender_key', $columns, true)) {
                $table->string('gender_key', 20)->nullable();
            }
            if (! in_array('personnel_area', $columns, true)) {
                $table->string('personnel_area', 100)->nullable();
            }
            if (! in_array('abrevation_position', $columns, true)) {
                $table->string('abrevation_position', 100)->nullable();
            }
            if (! in_array('abrevation_organization', $columns, true)) {
                $table->string('abrevation_organization', 100)->nullable();
            }
            if (! in_array('organizational_unit', $columns, true)) {
                $table->string('organizational_unit', 255)->nullable();
            }
            if (! in_array('cost_center', $columns, true)) {
                $table->string('cost_center', 100)->nullable();
            }
            if (! in_array('masa_kontrak', $columns, true)) {
                $table->string('masa_kontrak', 100)->nullable();
            }
            if (! in_array('email', $columns, true)) {
                $table->string('email', 255)->nullable();
            }
            if (! in_array('religious', $columns, true)) {
                $table->string('religious', 100)->nullable();
            }
            if (! in_array('usia', $columns, true)) {
                $table->integer('usia')->nullable();
            }
            if (! in_array('tempat_lahir', $columns, true)) {
                $table->string('tempat_lahir', 255)->nullable();
            }
            if (! in_array('pendidikan', $columns, true)) {
                $table->string('pendidikan', 255)->nullable();
            }
            if (! in_array('hiring', $columns, true)) {
                $table->date('hiring')->nullable();
            }
            if (! in_array('organilk', $columns, true)) {
                $table->string('organilk', 100)->nullable();
            }
            if (! in_array('alamat', $columns, true)) {
                $table->text('alamat')->nullable();
            }
        });
    }

    private function copyLegacyEmployeeValues(): void
    {
        foreach ([
            'ID Number' => 'id_number',
            'Position ID' => 'position_id_source',
            'Personal Number' => 'personal_number',
            'Employee Subgroup' => 'employee_subgroup',
            'Cost Ctr' => 'cost_ctr',
            'Gender Key' => 'gender_key',
            'abrevation position' => 'abrevation_position',
            'abrevation organization' => 'abrevation_organization',
            'Birth date' => 'birth_date',
            'Personnel Area' => 'personnel_area',
            'Organizational Unit' => 'organizational_unit',
            'Cost Center' => 'cost_center',
            'Cost Center' => 'cost_center',
            'E-mail' => 'email',
            'Tempat Lahir' => 'tempat_lahir',
            'directorate' => 'txt_dir',
            'department' => 'txt_dept',
            'bureau' => 'txt_biro',
            'section' => 'txt_sect',
            'birth_place' => 'tempat_lahir',
            'gender' => 'gender_key',
            'religion' => 'religious',
            'age' => 'usia',
            'education' => 'pendidikan',
            'hiring_date' => 'hiring',
            'organic_status' => 'organilk',
            'address' => 'alamat',
        ] as $source => $target) {
            if (! $this->hasExactEmployeeColumn($source) || ! $this->hasExactEmployeeColumn($target) || strcasecmp($source, $target) === 0) {
                continue;
            }

            $wrappedSource = DB::connection()->getQueryGrammar()->wrap($source);
            DB::table('employees')
                ->whereNull($target)
                ->whereNotNull($source)
                ->update([$target => DB::raw($wrappedSource)]);
        }
    }

    private function ensureSapUniqueIndex(): void
    {
        $hasUniqueSapIndex = collect(Schema::getIndexes('employees'))->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['sap'],
        );

        if (! $hasUniqueSapIndex) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->unique('sap');
            });
        }
    }

    private function addPromotionAndDemotionSapForeignKeys(): void
    {
        foreach (['employee_promotions', 'employee_demotions'] as $relatedTable) {
            Schema::table($relatedTable, function (Blueprint $table) use ($relatedTable): void {
                $table->string('sap', 50)->nullable(false)->change();
                $hasSapForeignKey = collect(Schema::getForeignKeys($relatedTable))->contains(
                    fn (array $foreignKey): bool => $foreignKey['columns'] === ['sap']
                        && $foreignKey['foreign_table'] === 'employees'
                        && $foreignKey['foreign_columns'] === ['sap'],
                );

                if (! $hasSapForeignKey) {
                    $table->foreign('sap')
                        ->references('sap')
                        ->on('employees')
                        ->cascadeOnUpdate()
                        ->restrictOnDelete();
                }
            });
        }
    }

    private function assertColumnLength(string $table, string $column, int $length): void
    {
        if (! in_array($column, Schema::getColumnListing($table), true)) {
            return;
        }

        $lengthFunction = DB::connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        $wrappedColumn = DB::connection()->getQueryGrammar()->wrap($column);

        if (DB::table($table)->whereNotNull($column)->whereRaw("{$lengthFunction}({$wrappedColumn}) > ?", [$length])->exists()) {
            throw new RuntimeException("Cannot narrow {$table}.{$column}: values longer than {$length} characters exist.");
        }
    }

    private function hasExactEmployeeColumn(string $column): bool
    {
        return in_array($column, Schema::getColumnListing('employees'), true);
    }

    private function renameEmployeeColumn(string $source, string $target): void
    {
        Schema::table('employees', function (Blueprint $table) use ($source, $target): void {
            $table->renameColumn($source, $target);
        });
    }
};
