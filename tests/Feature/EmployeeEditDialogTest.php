<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeEditDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_dialog_requires_authentication_and_update_permission_before_employee_lookup(): void
    {
        $employee = Employee::query()->create(['sap' => 'DIALOG-PRIVATE', 'name' => 'Private dialog identity']);
        $url = route('data-pegawai', ['edit_employee' => $employee->id]);

        $this->get($url)->assertRedirect(route('login'));
        $staff = User::factory()->create(['role' => UserRole::STAFF->value]);

        $this->actingAs($staff)->get($url)->assertForbidden()->assertDontSee('Private dialog identity');
        $this->get(route('data-pegawai', ['edit_employee' => 999999]))->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'sap' => 'DIALOG-PRIVATE']);
    }

    #[DataProvider('employeeIndexRoutes')]
    public function test_authorized_edit_dialog_loads_on_demand_and_still_saves(string $routeName): void
    {
        $employee = Employee::query()->create(['sap' => 'DIALOG-EDIT', 'personal_number' => 'Original identity']);
        $otherEmployee = Employee::query()->create(['sap' => 'DIALOG-OTHER', 'personal_number' => 'Other identity']);
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin)->get(route($routeName))
            ->assertOk()
            ->assertSee('data-employee-edit-trigger', false)
            ->assertDontSee('<dialog id="edit-employee-', false);
        $this->get(route($routeName, ['edit_employee' => $employee->id]))
            ->assertOk()
            ->assertViewIs('employees._edit-dialog')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('<dialog id="edit-employee-'.$employee->id.'"', false)
            ->assertSee('Original identity')
            ->assertDontSee('Other identity')
            ->assertDontSee('<html', false);
        $this->put(route('data-pegawai.update', $employee), ['sap' => 'DIALOG-EDIT', 'personal_number' => 'Updated identity'])
            ->assertRedirect(route('data-pegawai'));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'personal_number' => 'Updated identity']);
        $this->assertDatabaseHas('employees', ['id' => $otherEmployee->id, 'personal_number' => 'Other identity']);
    }

    public function test_invalid_dialog_identifier_is_validated_and_unknown_employee_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));

        $this->getJson(route('data-pegawai', ['edit_employee' => ['invalid']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('edit_employee');
        $this->get(route('data-pegawai', ['edit_employee' => 999999]))->assertNotFound();
    }

    public function test_edit_fragment_escapes_employee_values_before_browser_insertion(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $employee = Employee::query()->create(['sap' => 'DIALOG-ESCAPED', 'personal_number' => $payload]);

        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]))
            ->get(route('data-pegawai', ['edit_employee' => $employee->id]))
            ->assertOk()
            ->assertSee($payload)
            ->assertDontSee($payload, false);
    }

    public function test_failed_edit_outside_the_filtered_page_keeps_old_values_and_reopens_its_dialog(): void
    {
        $employee = Employee::query()->create(['sap' => 'DIALOG-FILTERED', 'personal_number' => 'Outside current filter']);
        Employee::query()->create(['sap' => 'DIALOG-VISIBLE', 'personal_number' => 'Visible employee']);
        $indexUrl = route('data-pegawai', ['search' => 'Visible']);
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));

        $this->from($indexUrl)->put(route('data-pegawai.update', $employee), [
            'sap' => $employee->sap,
            'email' => 'invalid-email',
            '_form' => 'edit',
            '_employee_id' => $employee->id,
        ])->assertRedirect($indexUrl)->assertSessionHasErrors('email');

        $this->get($indexUrl)
            ->assertOk()
            ->assertViewHas('employees', fn ($employees): bool => $employees->total() === 1)
            ->assertSee('<dialog id="edit-employee-'.$employee->id.'"', false)
            ->assertSee('x-init="$nextTick(() => $el.showModal())"', false)
            ->assertSee('value="invalid-email"', false)
            ->assertSee('Periksa kembali data berikut:');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'email' => null]);
    }

    /** @return array<string, array{0: string}> */
    public static function employeeIndexRoutes(): array
    {
        return [
            'career management' => ['data-pegawai'],
            'performance and talent management' => ['performance-talent.data-pegawai.index'],
        ];
    }
}
