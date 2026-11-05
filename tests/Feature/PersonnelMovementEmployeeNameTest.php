<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Services\Personnel\PersonnelMovementFormDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonnelMovementEmployeeNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_options_use_normalized_personal_number_as_name_without_n_plus_one_queries(): void
    {
        $namedEmployee = Employee::query()->create([
            'sap' => '1211',
            'personal_number' => '  TAUFAN MAULANA, ST.  ',
        ]);
        $namedEmployee->forceFill(['name' => 'Nama Employee Berbeda'])->save();

        $integerPersonalNumberEmployee = Employee::query()->create([
            'sap' => 'SAP-INTEGER-PN',
            'personal_number' => 4351,
        ]);

        $employeeWithoutName = Employee::query()->create([
            'sap' => 'SAP-WITHOUT-NAME',
            'personal_number' => '   ',
        ]);
        User::factory()->create([
            'employee_id' => $employeeWithoutName->id,
            'name' => 'Nama User Tidak Boleh Digunakan',
            'role' => UserRole::STAFF->value,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $employeeOptions = app(PersonnelMovementFormDataService::class)->employees();

        $employeeTableQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'from "employees"'));

        $namedOption = $employeeOptions->firstWhere('id', $namedEmployee->id);
        $integerOption = $employeeOptions->firstWhere('id', $integerPersonalNumberEmployee->id);
        $fallbackOption = $employeeOptions->firstWhere('id', $employeeWithoutName->id);

        $this->assertSame('TAUFAN MAULANA, ST.', $namedOption['personal_number']);
        $this->assertSame('TAUFAN MAULANA, ST.', $namedOption['name']);
        $this->assertSame('1211 — TAUFAN MAULANA, ST.', $namedOption['label']);
        $this->assertSame('SAP-INTEGER-PN — 4351', $integerOption['label']);
        $this->assertSame('', $fallbackOption['name']);
        $this->assertSame('SAP-WITHOUT-NAME — Nama belum tersedia', $fallbackOption['label']);
        $this->assertCount(1, $employeeTableQueries);
    }

    #[DataProvider('movementRouteProvider')]
    public function test_movement_add_employee_lists_use_sap_then_personal_number(string $routeName): void
    {
        $employee = Employee::query()->create([
            'sap' => '4351',
            'personal_number' => '  ARIFIN.R  ',
        ]);

        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin)->get(route($routeName))
            ->assertOk()
            ->assertViewHas('employeeOptions', function ($employeeOptions) use ($employee): bool {
                $option = $employeeOptions->firstWhere('id', $employee->id);

                return $option['personal_number'] === 'ARIFIN.R'
                    && $option['name'] === 'ARIFIN.R'
                    && $option['label'] === '4351 — ARIFIN.R';
            })
            ->assertSee('`${employee.sap} — ${employee.personal_number', escape: false);
    }

    /** @return array<string, array{string}> */
    public static function movementRouteProvider(): array
    {
        return [
            'mutasi' => ['mutasi.batches.create'],
            'promosi' => ['promosi.batches.create'],
            'demosi' => ['demosi.batches.create'],
        ];
    }
}
