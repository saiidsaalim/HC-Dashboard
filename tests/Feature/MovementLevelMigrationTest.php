<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovementLevelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_level_column_rename_preserves_data_in_both_directions(): void
    {
        $table = 'employee_mutations';
        DB::table($table)->insert([
            'sap' => 'SAP-MIGRATION',
            'nama' => 'Pegawai Migration',
            'departemen_lama' => 'Department Lama',
            'jabatan_lama' => 'Position Lama',
            'departemen_baru' => 'Department Baru',
            'jabatan_baru' => 'Position Baru',
            'tmt' => '2026-11-03',
            'level_lama' => 'L1',
            'level_baru' => 'L2',
        ]);

        $migration = require database_path('migrations/2026_11_03_021431_rename_band_columns_to_level_in_employee_movements.php');
        $legacyPrefix = implode('', ['ba', 'nd']);
        $legacyOld = $legacyPrefix.'_lama';
        $legacyNew = $legacyPrefix.'_baru';

        $migration->down();

        $this->assertTrue(Schema::hasColumns($table, [$legacyOld, $legacyNew]));
        $this->assertFalse(Schema::hasColumn($table, 'level_lama'));
        $this->assertSame('L1', DB::table($table)->value($legacyOld));
        $this->assertSame('L2', DB::table($table)->value($legacyNew));

        $migration->up();

        $this->assertTrue(Schema::hasColumns($table, ['level_lama', 'level_baru']));
        $this->assertFalse(Schema::hasColumn($table, $legacyOld));
        $this->assertDatabaseHas($table, [
            'sap' => 'SAP-MIGRATION',
            'level_lama' => 'L1',
            'level_baru' => 'L2',
        ]);
    }
}
