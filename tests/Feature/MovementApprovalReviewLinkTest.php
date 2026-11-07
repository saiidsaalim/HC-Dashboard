<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\Permission;
use App\Models\User;
use App\Policies\EmployeeDemotionPolicy;
use App\Policies\EmployeeMutationPolicy;
use App\Policies\EmployeePromotionPolicy;
use App\Services\Authorization\RolePermissionCache;
use Database\Seeders\PermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovementApprovalReviewLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_selects_the_exact_record_beyond_pagination_and_conflicting_filters(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $employee = Employee::query()->create(['sap' => 'REVIEW-EMPLOYEE', 'name' => 'Pegawai Sama']);
        $target = $this->movement($modelClass, $employee, ['tmt' => '2020-01-01']);
        foreach (range(1, 21) as $index) {
            $this->movement($modelClass, $employee, ['tmt' => '2026-01-01']);
        }
        $originalAttributes = $target->refresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));

        $this->get(route($routeName))
            ->assertOk()
            ->assertViewHas($collectionName, fn ($records): bool => ! $records->contains('id', $target->id))
            ->assertDontSee('data-auto-open-review', escape: false);

        $response = $this->get(route($routeName, [
            'review' => $target->id,
            'search' => 'does not match this employee',
            'status' => 'final',
            'batch' => 999999,
            'page' => 9,
        ]))->assertOk()
            ->assertViewHas('reviewId', $target->id)
            ->assertViewHas('search', '')
            ->assertViewHas('filter', 'all')
            ->assertViewHas('batchId', null)
            ->assertViewHas($collectionName, fn ($records): bool => $records->currentPage() === 1
                && $records->total() === 1
                && $records->sole()->is($target)
                && $records->sole()->relationLoaded('employee')
                && $records->sole()->relationLoaded('approvals'));

        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $dialogs = (new DOMXPath($document))->query('//dialog[@data-auto-open-review]');
        $this->assertSame(1, $dialogs->length);
        $this->assertSame($dialogPrefix.$target->id, $dialogs->item(0)->getAttribute('id'));
        $this->assertTrue($dialogs->item(0)->hasAttribute('open'), 'The selected record remains visible without JavaScript.');
        $this->assertSame($originalAttributes, $target->fresh()->getAttributes());
        $this->assertSame(0, $target->approvals()->count());
    }

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_rejects_invalid_or_missing_ids_without_server_errors(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]));

        foreach (['', 'abc', '-1', '0', '1.5', '1e3', ' 1 ', (string) PHP_INT_MAX.'0', ['1'], '999999'] as $invalidId) {
            $this->get(route($routeName, ['review' => $invalidId]))->assertNotFound();
        }
    }

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_links_require_authentication(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $this->get(route($routeName, ['review' => 1]))->assertRedirect(route('login'));
    }

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_links_require_the_module_view_permission(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $this->seed(PermissionSeeder::class);
        $employee = Employee::query()->create(['sap' => 'DENIED-REVIEW']);
        $movement = $this->movement($modelClass, $employee);
        $actor = User::factory()->create(['role' => UserRole::ADMIN->value]);
        DB::table('permission_role')->where('role', UserRole::ADMIN->value)
            ->where('permission_id', Permission::query()->where('key', $viewPermission)->sole()->id)
            ->delete();
        app(RolePermissionCache::class)->clear();

        $this->actingAs($actor)->get(route($routeName, ['review' => $movement->id]))->assertForbidden();
    }

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_links_still_enforce_the_record_view_policy(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $employee = Employee::query()->create(['sap' => 'POLICY-REVIEW']);
        $movement = $this->movement($modelClass, $employee);
        $this->mock($policyClass, function (MockInterface $policy): void {
            $policy->shouldReceive('viewAny')->andReturnTrue();
            $policy->shouldReceive('view')->once()->andReturnFalse();
        });

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN->value]))
            ->get(route($routeName, ['review' => $movement->id]))->assertForbidden();
    }

    /** @param class-string<Model> $modelClass */
    #[DataProvider('movementModules')]
    public function test_review_links_still_require_access_to_the_hc_od_workspace(
        string $routeName,
        string $modelClass,
        string $collectionName,
        string $dialogPrefix,
        string $policyClass,
        string $viewPermission,
    ): void {
        $this->seed(PermissionSeeder::class);
        $employee = Employee::query()->create(['sap' => 'WORKSPACE-REVIEW']);
        $movement = $this->movement($modelClass, $employee);
        $actor = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $actor->permissionOverrides()->attach(
            Permission::query()->where('key', 'workspace.hc-od-career-management.view')->sole()->id,
            ['granted' => false],
        );

        $this->actingAs($actor)->get(route($routeName, ['review' => $movement->id]))->assertForbidden();
    }

    /** @return array<string, array{string, class-string<Model>, string, string, class-string, string}> */
    public static function movementModules(): array
    {
        return [
            'mutasi' => ['mutasi', EmployeeMutation::class, 'mutations', 'mutation-detail-', EmployeeMutationPolicy::class, 'mutations.view'],
            'promosi' => ['promosi', EmployeePromotion::class, 'promotions', 'promotion-detail-', EmployeePromotionPolicy::class, 'promotions.view'],
            'demosi' => ['demosi', EmployeeDemotion::class, 'demotions', 'demotion-detail-', EmployeeDemotionPolicy::class, 'demotions.view'],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    private function movement(string $modelClass, Employee $employee, array $attributes = []): Model
    {
        return $modelClass::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => $employee->name ?: 'Pegawai Review',
            'departemen_lama' => 'Department Lama',
            'departemen_baru' => 'Department Baru',
            'jabatan_lama' => 'Posisi Lama',
            'jabatan_baru' => 'Posisi Baru',
            'tmt' => '2026-01-01',
            'pg' => '5',
            'level_lama' => 'Staff',
            'jg_lama' => '5',
            'level_baru' => 'Manager',
            'jg_baru' => '6',
            'verification_status' => 'Draft',
            ...$attributes,
        ]);
    }
}
